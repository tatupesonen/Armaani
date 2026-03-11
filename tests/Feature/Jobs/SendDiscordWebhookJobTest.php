<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendDiscordWebhookJob;
use App\Models\AppSetting;
use App\Services\Discord\DiscordWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendDiscordWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_send_to_discord_service(): void
    {
        $mock = $this->mock(DiscordWebhookService::class);
        $mock->shouldReceive('send')
            ->once()
            ->with('Server started', null)
            ->andReturn(['success' => true, 'error' => null]);

        $job = new SendDiscordWebhookJob('Server started');
        $job->handle($mock);
    }

    public function test_it_passes_optional_username(): void
    {
        $mock = $this->mock(DiscordWebhookService::class);
        $mock->shouldReceive('send')
            ->once()
            ->with('Server started', 'Armaani')
            ->andReturn(['success' => true, 'error' => null]);

        $job = new SendDiscordWebhookJob('Server started', 'Armaani');
        $job->handle($mock);
    }

    public function test_it_is_queued_with_correct_properties(): void
    {
        $job = new SendDiscordWebhookJob('test content');

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(10, $job->backoff);
    }

    public function test_it_can_be_dispatched_to_queue(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        SendDiscordWebhookJob::dispatch('Server restarted', 'Armaani');

        \Illuminate\Support\Facades\Queue::assertPushed(SendDiscordWebhookJob::class, function ($job) {
            return $job->content === 'Server restarted' && $job->username === 'Armaani';
        });
    }

    public function test_service_returns_error_when_no_webhook_configured(): void
    {
        Http::fake();

        $service = new DiscordWebhookService;
        $result = $service->send('test');

        $this->assertFalse($result['success']);
        $this->assertEquals('No Discord webhook configured.', $result['error']);

        Http::assertNothingSent();
    }

    public function test_service_sends_to_configured_webhook(): void
    {
        AppSetting::factory()->withDiscordWebhook()->create();

        Http::fake([
            'discord.com/*' => Http::response(null, 204),
        ]);

        $service = new DiscordWebhookService;
        $result = $service->send('Server started');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);

        Http::assertSentCount(1);
    }

    public function test_service_handles_http_failure(): void
    {
        AppSetting::factory()->withDiscordWebhook()->create();

        Http::fake([
            'discord.com/*' => Http::response('Rate limited', 429),
        ]);

        $service = new DiscordWebhookService;
        $result = $service->send('Server started');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('429', $result['error']);
    }

    public function test_service_includes_username_in_payload(): void
    {
        AppSetting::factory()->withDiscordWebhook()->create();

        Http::fake([
            'discord.com/*' => Http::response(null, 204),
        ]);

        $service = new DiscordWebhookService;
        $service->send('Test message', 'Armaani');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $body = json_decode($request->body(), true);

            return $body['content'] === 'Test message' && $body['username'] === 'Armaani';
        });
    }

    public function test_is_configured_returns_false_without_webhook(): void
    {
        $service = new DiscordWebhookService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_with_webhook(): void
    {
        AppSetting::factory()->withDiscordWebhook()->create();

        $service = new DiscordWebhookService;

        $this->assertTrue($service->isConfigured());
    }
}
