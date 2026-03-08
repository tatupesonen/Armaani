<?php

namespace App\Jobs;

use App\Services\DiscordWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDiscordWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public string $content,
        public ?string $username = null,
    ) {}

    public function handle(DiscordWebhookService $discord): void
    {
        $discord->send($this->content, $this->username);
    }
}
