<?php

namespace App\Jobs;

use App\Enums\ServerStatus;
use App\Events\ServerStatusChanged;
use App\Models\Server;
use App\Services\ServerProcessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StopServerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public Server $server) {}

    public function handle(ServerProcessService $service): void
    {
        $context = "[Server:{$this->server->id} '{$this->server->name}']";

        Log::info("{$context} Stopping server via queued job");
        $service->stopAllHeadlessClients($this->server);
        $service->stop($this->server);

        $this->server->update(['status' => ServerStatus::Stopped]);
        ServerStatusChanged::dispatch($this->server->id, ServerStatus::Stopped->value, $this->server->name);
        Log::info("{$context} Server stopped successfully");
    }

    public function failed(?\Throwable $exception): void
    {
        $this->server->update(['status' => ServerStatus::Stopped]);
        ServerStatusChanged::dispatch($this->server->id, ServerStatus::Stopped->value, $this->server->name);
        Log::error("[Server:{$this->server->id}] StopServerJob failed: {$exception?->getMessage()}");
    }
}
