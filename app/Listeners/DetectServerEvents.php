<?php

namespace App\Listeners;

use App\Contracts\DetectsServerState;
use App\Enums\ServerStatus;
use App\Events\ServerLogOutput;
use App\Events\ServerStatusChanged;
use App\GameManager;
use App\Jobs\SendDiscordWebhookJob;
use App\Jobs\StartServerJob;
use App\Jobs\StopServerJob;
use App\Models\Server;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DetectServerEvents
{
    public function __construct(
        private GameManager $gameManager,
    ) {}

    /**
     * Handle the event.
     *
     * Detects game-specific log strings to transition server status:
     * - Mod download started: Booting → DownloadingMods
     * - Mod download finished: DownloadingMods → Booting
     * - Boot detection: Booting/DownloadingMods → Running
     * - Crash detection: Running/Booting/DownloadingMods → Crashed (with optional auto-restart)
     */
    public function handle(ServerLogOutput $event): void
    {
        $server = Server::query()->find($event->serverId);

        if (! $server) {
            return;
        }

        $handler = $this->gameManager->for($server);

        if (! $handler instanceof DetectsServerState) {
            return;
        }

        // Check for mod download started (Booting → DownloadingMods)
        $modDownloadStarted = $handler->getModDownloadStartedString();
        if ($modDownloadStarted !== null && str_contains($event->line, $modDownloadStarted)) {
            $this->transitionStatus($server, ServerStatus::Booting, ServerStatus::DownloadingMods);

            return;
        }

        // Check for mod download finished (DownloadingMods → Booting)
        $modDownloadFinished = $handler->getModDownloadFinishedString();
        if ($modDownloadFinished !== null && str_contains($event->line, $modDownloadFinished)) {
            $this->transitionStatus($server, ServerStatus::DownloadingMods, ServerStatus::Booting);

            return;
        }

        // Check for boot detection (Booting/DownloadingMods → Running)
        $bootStrings = $handler->getBootDetectionStrings();
        if ($bootStrings !== [] && Str::contains($event->line, $bootStrings)) {
            $this->transitionStatus($server, [ServerStatus::Booting, ServerStatus::DownloadingMods], ServerStatus::Running);

            return;
        }

        // Check for crash detection (Running/Booting/DownloadingMods → Crashed)
        $crashStrings = $handler->getCrashDetectionStrings();
        if ($crashStrings !== [] && Str::contains($event->line, $crashStrings)) {
            $transitioned = $this->transitionStatus(
                $server,
                [ServerStatus::Running, ServerStatus::Booting, ServerStatus::DownloadingMods],
                ServerStatus::Crashed,
            );

            if ($transitioned) {
                SendDiscordWebhookJob::dispatch(
                    "**{$server->name}** has crashed.\n> {$event->line}",
                    'Armaani',
                );

                if ($server->auto_restart) {
                    Log::info("{$server->logContext()} Auto-restart enabled — queuing restart");
                    Bus::chain([
                        new StopServerJob($server),
                        new StartServerJob($server),
                    ])->dispatch();
                }
            }
        }
    }

    /**
     * Attempt to transition a server's status atomically.
     * Logs and broadcasts the change if the transition succeeds.
     *
     * @param  ServerStatus|array<int, ServerStatus>  $fromStatuses
     */
    private function transitionStatus(Server $server, ServerStatus|array $fromStatuses, ServerStatus $toStatus): bool
    {
        $query = Server::query()->where('id', $server->id);

        if (is_array($fromStatuses)) {
            $query->whereIn('status', $fromStatuses);
        } else {
            $query->where('status', $fromStatuses);
        }

        if (! $query->update(['status' => $toStatus])) {
            return false;
        }

        Log::info("{$server->logContext()} Status changed to {$toStatus->value}");
        ServerStatusChanged::dispatch($server->id, $toStatus->value, $server->name);

        return true;
    }
}
