<?php

namespace App\Http\Controllers;

use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\Contracts\SupportsScenarios;
use App\Contracts\SupportsWorkshopMods;
use App\Contracts\WritesNativeLogs;
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
use App\Services\Server\ServerProcessService;
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
        $allHandlers = $this->gameManager->allHandlers();

        // Build eager-load list dynamically from handlers
        $settingsRelations = collect($allHandlers)
            ->map(fn (GameHandler $h) => $h->settingsRelationName())
            ->filter()
            ->values()
            ->all();

        $servers = Server::query()
            ->with(array_merge(['activePreset', 'gameInstall', 'backups'], $settingsRelations))
            ->orderBy('name')
            ->get()
            ->each(function (Server $server): void {
                $server->makeVisible(['password']);
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
            'gameTypes' => collect($allHandlers)->map(fn (GameHandler $handler) => [
                'value' => $handler->value(),
                'label' => $handler->label(),
                'defaultPort' => $handler->defaultPort(),
                'defaultQueryPort' => $handler->defaultQueryPort(),
                'supportsHeadlessClients' => $handler instanceof SupportsHeadlessClients,
                'supportsWorkshopMods' => $handler instanceof SupportsWorkshopMods,
                'supportsMissionUpload' => $handler instanceof SupportsMissions,
                'supportsAutoRestart' => $handler instanceof DetectsServerState && $handler->supportsAutoRestart(),
                'settingsSchema' => $handler->settingsSchema(),
            ])->values(),
        ]);
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $server = Server::query()->create($validated);

        $handler = $this->gameManager->driver($validated['game_type']);
        $handler->createRelatedSettings($server);
        $handler->updateRelatedSettings($server, $validated);

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
        if (! in_array($server->status, [ServerStatus::Stopped, ServerStatus::Crashed])) {
            return back()->with('error', 'Server can only be started when stopped or crashed.');
        }

        $server->transitionTo(ServerStatus::Starting);

        StartServerJob::dispatch($server);

        Log::info(auth_context()." started server: {$server->name}");

        return back();
    }

    public function stop(Server $server): RedirectResponse
    {
        if (! in_array($server->status, [ServerStatus::Running, ServerStatus::Booting])) {
            return back()->with('error', 'Server can only be stopped when running or booting.');
        }

        $server->transitionTo(ServerStatus::Stopping);

        StopServerJob::dispatch($server);

        Log::info(auth_context()." stopped server: {$server->name}");

        return back();
    }

    public function restart(Server $server): RedirectResponse
    {
        if (! in_array($server->status, [ServerStatus::Running, ServerStatus::Booting])) {
            return back()->with('error', 'Server can only be restarted when running or booting.');
        }

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

        if ($handler instanceof WritesNativeLogs) {
            return response()->json(['lines' => $this->readNativeLogLines($handler, $server)]);
        }

        $logPath = $handler->getServerLogPath($server);

        if (! $logPath || ! file_exists($logPath)) {
            return response()->json(['lines' => []]);
        }

        $lines = array_slice(file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -100);

        return response()->json(['lines' => $lines]);
    }

    /**
     * Read the last 100 lines from all native log files, merged and sorted by timestamp.
     *
     * @return list<string>
     */
    protected function readNativeLogLines(WritesNativeLogs $handler, Server $server): array
    {
        $baseDir = $handler->getNativeLogDirectory($server);
        $pattern = $handler->getNativeLogFilePattern();

        if (! is_dir($baseDir)) {
            return [];
        }

        $subdirs = glob($baseDir.'/logs_*', GLOB_ONLYDIR) ?: [];

        if ($subdirs === []) {
            return [];
        }

        sort($subdirs);
        $latestDir = end($subdirs);

        $logFiles = glob($latestDir.'/'.$pattern) ?: [];
        $allLines = [];

        foreach ($logFiles as $logFile) {
            if (! file_exists($logFile)) {
                continue;
            }

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            array_push($allLines, ...$lines);
        }

        // Native log lines are timestamped (e.g., "11:54:13.851 ENGINE ..."), so sorting
        // merges lines from different files in chronological order.
        sort($allLines);

        return array_slice($allLines, -100);
    }

    public function status(Server $server, ServerProcessService $processService): JsonResponse
    {
        return response()->json([
            'status' => $processService->getStatus($server)->value,
            'headlessClientCount' => $processService->getRunningHeadlessClientCount($server),
        ]);
    }

    public function scenarios(Server $server): JsonResponse
    {
        $handler = $this->gameManager->for($server);

        if (! $handler instanceof SupportsScenarios) {
            return response()->json(['scenarios' => []], 422);
        }

        return response()->json([
            'scenarios' => $handler->getScenarios($server),
        ]);
    }

    public function reloadScenarios(Server $server): JsonResponse
    {
        $handler = $this->gameManager->for($server);

        if (! $handler instanceof SupportsScenarios) {
            return response()->json(['scenarios' => []], 422);
        }

        return response()->json([
            'scenarios' => $handler->refreshScenarios($server),
        ]);
    }
}
