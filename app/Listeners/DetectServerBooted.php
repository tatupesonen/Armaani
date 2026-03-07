<?php

namespace App\Listeners;

use App\Enums\ServerStatus;
use App\Events\ServerLogOutput;
use App\Events\ServerStatusChanged;
use App\Models\Server;
use Illuminate\Support\Facades\Log;

class DetectServerBooted
{
    /**
     * Handle the event.
     *
     * When a server log line contains "Connected to Steam servers" and the
     * server is still in the Booting state, transition it to Running.
     */
    public function handle(ServerLogOutput $event): void
    {
        if (! str_contains($event->line, 'Connected to Steam servers')) {
            return;
        }

        $updated = Server::query()
            ->where('id', $event->serverId)
            ->where('status', ServerStatus::Booting)
            ->update(['status' => ServerStatus::Running]);

        if ($updated) {
            $serverName = Server::query()->where('id', $event->serverId)->value('name') ?? 'Server';
            Log::info("[Server:{$event->serverId}] Connected to Steam servers — status changed from Booting to Running");
            ServerStatusChanged::dispatch($event->serverId, ServerStatus::Running->value, $serverName);
        }
    }
}
