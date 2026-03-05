<?php

namespace App\Jobs;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\ServerProcessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StartServerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Server $server, public bool $restart = false) {}

    public function handle(ServerProcessService $service): void
    {
        $context = "[Server:{$this->server->id} '{$this->server->name}']";

        if ($this->restart) {
            Log::info("{$context} Restarting server (stop phase)");
            $service->stop($this->server);
            sleep(2);
        }

        Log::info("{$context} Starting server via queued job");
        $service->start($this->server);

        if ($service->isRunning($this->server)) {
            $this->server->update(['status' => ServerStatus::Running]);
            Log::info("{$context} Server started successfully");
        } else {
            $this->server->update(['status' => ServerStatus::Stopped]);
            Log::error("{$context} Server failed to start");
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->server->update(['status' => ServerStatus::Stopped]);
        Log::error("[Server:{$this->server->id}] StartServerJob failed: {$exception?->getMessage()}");
    }
}
