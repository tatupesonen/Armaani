<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordWebhookService
{
    /**
     * Send a message to the configured Discord webhook.
     *
     * @return array{success: bool, error: string|null}
     */
    public function send(string $content, ?string $username = null): array
    {
        $webhookUrl = $this->getWebhookUrl();

        if ($webhookUrl === null) {
            return ['success' => false, 'error' => 'No Discord webhook configured.'];
        }

        $payload = ['content' => $content];

        if ($username !== null) {
            $payload['username'] = $username;
        }

        try {
            $response = Http::post($webhookUrl, $payload);

            if ($response->successful()) {
                return ['success' => true, 'error' => null];
            }

            Log::warning('[DiscordWebhook] Request failed with status '.$response->status().': '.$response->body());

            return ['success' => false, 'error' => 'Discord returned HTTP '.$response->status()];
        } catch (\Exception $e) {
            Log::error('[DiscordWebhook] Exception: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a test message to verify the webhook is working.
     *
     * @return array{success: bool, error: string|null}
     */
    public function sendTestMessage(): array
    {
        return $this->send(
            'This is a test notification from **Armaani** server manager. Your webhook is working correctly!',
            'Armaani',
        );
    }

    /**
     * Check whether a webhook URL is configured.
     */
    public function isConfigured(): bool
    {
        return $this->getWebhookUrl() !== null;
    }

    protected function getWebhookUrl(): ?string
    {
        return AppSetting::query()->first()?->discord_webhook_url ?: null;
    }
}
