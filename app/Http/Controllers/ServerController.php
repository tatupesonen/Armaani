<?php

namespace App\Http\Controllers;

use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\Enums\GameType;
use App\Enums\InstallationStatus;
use App\Enums\ServerStatus;
use App\GameManager;
use App\Http\Requests\Server\StoreServerRequest;
use App\Http\Requests\Server\UpdateServerRequest;
use App\Jobs\StartServerJob;
use App\Jobs\StopServerJob;
use App\Models\GameInstall;
use App\Models\ModPreset;
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
                $server->setAttribute('supports_backups', $this->gameManager->for($server) instanceof SupportsBackups);
                $server->setAttribute('profiles_path', $server->getProfilesPath());
            });

        return Inertia::render('servers/index', [
            'servers' => $servers,
            'presets' => ModPreset::query()->orderBy('name')->get(),
            'gameInstalls' => GameInstall::query()
                ->whereIn('installation_status', [InstallationStatus::Installed, InstallationStatus::Installing])
                ->orderBy('name')
                ->get(),
            'gameTypes' => collect(GameType::cases())->map(function (GameType $gt) {
                $handler = $this->gameManager->driver($gt->value);

                return [
                    'value' => $gt->value,
                    'label' => $gt->label(),
                    'defaultPort' => $handler->defaultPort(),
                    'defaultQueryPort' => $handler->defaultQueryPort(),
                    'supportsHeadlessClients' => $handler instanceof SupportsHeadlessClients,
                    'supportsWorkshopMods' => $handler->supportsWorkshopMods(),
                    'supportsMissionUpload' => $handler instanceof SupportsMissions,
                ];
            }),
        ]);
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $gameType = GameType::from($validated['game_type']);

        $server = Server::query()->create($validated);

        $handler = $this->gameManager->driver($gameType->value);
        $handler->createRelatedSettings($server);

        Log::info(auth_context()." created server: {$server->name}");

        return back()->with('success', "Server '{$server->name}' created.");
    }

    public function update(UpdateServerRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        $serverFields = collect($validated)->only($server->getFillable())->toArray();
        $server->update($serverFields);

        $handler = $this->gameManager->for($server);
        $handler->updateRelatedSettings($server, $validated);

        Log::info(auth_context()." updated server: {$server->name}");

        return back()->with('success', "Server '{$server->name}' updated.");
    }

    public function destroy(Server $server): RedirectResponse
    {
        if ($server->status !== ServerStatus::Stopped) {
            return back()->with('error', 'Cannot delete a running server. Stop it first.');
        }

        Log::info(auth_context()." deleted server: {$server->name}");

        $server->delete();

        return back()->with('success', 'Server deleted.');
    }

    public function start(Server $server): RedirectResponse
    {
        $server->transitionTo(ServerStatus::Starting);

        StartServerJob::dispatch($server);

        Log::info(auth_context()." started server: {$server->name}");

        return back();
    }

    public function stop(Server $server): RedirectResponse
    {
        $server->transitionTo(ServerStatus::Stopping);

        StopServerJob::dispatch($server);

        Log::info(auth_context()." stopped server: {$server->name}");

        return back();
    }

    public function restart(Server $server): RedirectResponse
    {
        $server->transitionTo(ServerStatus::Stopping);

        Bus::chain([
            new StopServerJob($server),
            new StartServerJob($server),
        ])->dispatch();

        Log::info(auth_context()." restarted server: {$server->name}");

        return back();
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
