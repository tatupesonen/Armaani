<?php

namespace App\Http\Controllers;

use App\Enums\GameType;
use App\Enums\ServerStatus;
use App\Events\ServerStatusChanged;
use App\GameManager;
use App\Http\Requests\Server\StoreServerRequest;
use App\Http\Requests\Server\UpdateServerRequest;
use App\Jobs\StartServerJob;
use App\Jobs\StopServerJob;
use App\Models\DayZSettings;
use App\Models\DifficultySettings;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\NetworkSettings;
use App\Models\ReforgerSettings;
use App\Models\Server;
use App\Services\ReforgerScenarioService;
use App\Services\ServerProcessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function __construct(
        private GameManager $gameManager,
    ) {}

    public function index(): Response
    {

        $servers = Server::query()
            ->with(['activePreset', 'gameInstall', 'difficultySettings', 'networkSettings', 'reforgerSettings', 'dayzSettings', 'backups'])
            ->orderBy('name')
            ->get()
            ->each(function (Server $server): void {
                $server->makeVisible(['password', 'admin_password']);
                $server->setAttribute('supports_backups', $this->gameManager->for($server)->getBackupFilePath($server) !== null);
                $server->setAttribute('profiles_path', $server->getProfilesPath());
            });

        return Inertia::render('servers/index', [
            'servers' => $servers,
            'presets' => ModPreset::query()->orderBy('name')->get(),
            'gameInstalls' => GameInstall::query()
                ->whereIn('installation_status', ['installed', 'installing'])
                ->orderBy('name')
                ->get(),
            'gameTypes' => collect(GameType::cases())->map(fn (GameType $gt) => [
                'value' => $gt->value,
                'label' => $gt->label(),
                'defaultPort' => $gt->defaultPort(),
                'defaultQueryPort' => $gt->defaultQueryPort(),
                'supportsHeadlessClients' => $gt->supportsHeadlessClients(),
                'supportsWorkshopMods' => $gt->supportsWorkshopMods(),
                'supportsMissionUpload' => $gt->supportsMissionUpload(),
            ]),
        ]);
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $gameType = GameType::from($validated['game_type']);

        $server = Server::query()->create($validated);

        if ($gameType === GameType::Arma3) {
            DifficultySettings::query()->create(['server_id' => $server->id]);
            NetworkSettings::query()->create(['server_id' => $server->id]);
        }

        if ($gameType === GameType::ArmaReforger) {
            ReforgerSettings::query()->create(['server_id' => $server->id]);
        }

        if ($gameType === GameType::DayZ) {
            DayZSettings::query()->create(['server_id' => $server->id]);
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") created server: {$server->name}");

        return back()->with('success', "Server '{$server->name}' created.");
    }

    public function update(UpdateServerRequest $request, Server $server): RedirectResponse
    {
        $gameType = $server->game_type;
        $validated = $request->validated();

        $serverFields = collect($validated)->only($server->getFillable())->toArray();
        $server->update($serverFields);

        if ($gameType === GameType::Arma3) {
            $difficultyFields = collect($validated)->only(
                (new DifficultySettings)->getFillable()
            )->except('server_id')->toArray();

            $networkFields = collect($validated)->only(
                (new NetworkSettings)->getFillable()
            )->except('server_id')->toArray();

            if (! empty($difficultyFields)) {
                $server->difficultySettings()->updateOrCreate(
                    ['server_id' => $server->id],
                    $difficultyFields,
                );
            }

            if (! empty($networkFields)) {
                $server->networkSettings()->updateOrCreate(
                    ['server_id' => $server->id],
                    $networkFields,
                );
            }
        }

        if ($gameType === GameType::ArmaReforger) {
            $reforgerFields = collect($validated)->only(
                (new ReforgerSettings)->getFillable()
            )->except('server_id')->toArray();

            if (! empty($reforgerFields)) {
                $server->reforgerSettings()->updateOrCreate(
                    ['server_id' => $server->id],
                    $reforgerFields,
                );
            }
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") updated server: {$server->name}");

        return back()->with('success', "Server '{$server->name}' updated.");
    }

    public function destroy(Server $server): RedirectResponse
    {
        if ($server->status !== ServerStatus::Stopped) {
            return back()->with('error', 'Cannot delete a running server. Stop it first.');
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") deleted server: {$server->name}");

        $server->delete();

        return back()->with('success', 'Server deleted.');
    }

    public function start(Server $server): RedirectResponse
    {
        $server->update(['status' => ServerStatus::Starting]);
        $this->broadcastStatusChange($server, ServerStatus::Starting);

        StartServerJob::dispatch($server);

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") started server: {$server->name}");

        return back();
    }

    public function stop(Server $server): RedirectResponse
    {
        $server->update(['status' => ServerStatus::Stopping]);
        $this->broadcastStatusChange($server, ServerStatus::Stopping);

        StopServerJob::dispatch($server);

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") stopped server: {$server->name}");

        return back();
    }

    public function restart(Server $server): RedirectResponse
    {
        $server->update(['status' => ServerStatus::Stopping]);
        $this->broadcastStatusChange($server, ServerStatus::Stopping);

        Bus::chain([
            new StopServerJob($server),
            new StartServerJob($server),
        ])->dispatch();

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") restarted server: {$server->name}");

        return back();
    }

    private function broadcastStatusChange(Server $server, ServerStatus $status): void
    {
        try {
            event(new ServerStatusChanged($server->id, $status->value, $server->name));
        } catch (\Illuminate\Broadcasting\BroadcastException $e) {
            Log::warning("Failed to broadcast server status change: {$e->getMessage()}");
        }
    }

    public function addHeadlessClient(Server $server, ServerProcessService $processService): RedirectResponse
    {
        $processService->addHeadlessClient($server);

        return back();
    }

    public function removeHeadlessClient(Server $server, ServerProcessService $processService): RedirectResponse
    {
        $processService->removeHeadlessClient($server);

        return back();
    }

    public function launchCommand(Server $server): JsonResponse
    {
        $handler = $this->gameManager->for($server);

        return response()->json([
            'command' => implode(' ', $handler->buildLaunchCommand($server)),
        ]);
    }

    public function serverLog(Server $server): JsonResponse
    {
        $handler = $this->gameManager->for($server);
        $logPath = $handler->getServerLogPath($server);

        if (! $logPath || ! file_exists($logPath)) {
            return response()->json(['lines' => []]);
        }

        $lines = array_slice(file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -100);

        return response()->json(['lines' => $lines]);
    }

    public function status(Server $server, ServerProcessService $processService): JsonResponse
    {
        return response()->json([
            'status' => $processService->getStatus($server)->value,
            'headlessClientCount' => $processService->getRunningHeadlessClientCount($server),
        ]);
    }

    public function reforgerScenarios(Server $server, ReforgerScenarioService $scenarioService): JsonResponse
    {
        if ($server->game_type !== GameType::ArmaReforger) {
            return response()->json(['scenarios' => []], 422);
        }

        return response()->json([
            'scenarios' => $scenarioService->getScenarios($server),
        ]);
    }

    public function reloadReforgerScenarios(Server $server, ReforgerScenarioService $scenarioService): JsonResponse
    {
        if ($server->game_type !== GameType::ArmaReforger) {
            return response()->json(['scenarios' => []], 422);
        }

        return response()->json([
            'scenarios' => $scenarioService->refreshScenarios($server),
        ]);
    }
}
