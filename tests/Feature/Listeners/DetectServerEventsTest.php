<?php

namespace Tests\Feature\Listeners;

use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Enums\ServerStatus;
use App\Events\ServerLogOutput;
use App\Events\ServerStatusChanged;
use App\GameManager;
use App\Jobs\SendDiscordWebhookJob;
use App\Jobs\StartServerJob;
use App\Jobs\StopServerJob;
use App\Listeners\DetectServerEvents;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class DetectServerEventsTest extends TestCase
{
    use RefreshDatabase;

    private DetectServerEvents $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = app(DetectServerEvents::class);
    }

    public function test_listener_is_registered_for_server_log_output_event(): void
    {
        Event::fake();
        Event::assertListening(ServerLogOutput::class, DetectServerEvents::class);
    }

    public function test_transitions_booting_server_to_running_on_steam_connection_message(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['name' => 'Boot Test', 'status' => ServerStatus::Booting]);

        $this->listener->handle(new ServerLogOutput($server->id, '15:42:30 Connected to Steam servers'));

        $this->assertEquals(ServerStatus::Running, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'running'
                && $event->serverName === 'Boot Test';
        });
    }

    public function test_ignores_log_lines_without_steam_connection_message(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Booting]);

        $this->listener->handle(new ServerLogOutput($server->id, '15:42:30 BattlEye Server: Initialized'));

        $this->assertEquals(ServerStatus::Booting, $server->fresh()->status);
    }

    public function test_does_not_change_status_if_server_is_already_running(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['status' => ServerStatus::Running]);

        $this->listener->handle(new ServerLogOutput($server->id, '15:42:30 Connected to Steam servers'));

        $this->assertEquals(ServerStatus::Running, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    public function test_does_not_change_stopped_server_on_steam_connection_message(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Stopped]);

        $this->listener->handle(new ServerLogOutput($server->id, '15:42:30 Connected to Steam servers'));

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);
    }

    public function test_transitions_booting_to_downloading_mods_on_mod_download_start(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create([
            'name' => 'Reforger Test',
            'game_type' => 'reforger',
            'status' => ServerStatus::Booting,
        ]);

        $this->listener->handle(new ServerLogOutput($server->id, 'Addon Download started'));

        $this->assertEquals(ServerStatus::DownloadingMods, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'downloading_mods'
                && $event->serverName === 'Reforger Test';
        });
    }

    public function test_transitions_downloading_mods_to_booting_on_mod_download_finish(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create([
            'name' => 'Reforger Test',
            'game_type' => 'reforger',
            'status' => ServerStatus::DownloadingMods,
        ]);

        $this->listener->handle(new ServerLogOutput($server->id, 'Required addons are ready to use.'));

        $this->assertEquals(ServerStatus::Booting, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'booting'
                && $event->serverName === 'Reforger Test';
        });
    }

    public function test_transitions_downloading_mods_to_running_on_boot_detection(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create([
            'name' => 'Reforger Test',
            'game_type' => 'reforger',
            'status' => ServerStatus::DownloadingMods,
        ]);

        $this->listener->handle(new ServerLogOutput($server->id, 'Server registered with addr 1.2.3.4:2001'));

        $this->assertEquals(ServerStatus::Running, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'running';
        });
    }

    public function test_does_not_transition_to_downloading_mods_for_non_reforger_server(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create([
            'game_type' => 'arma3',
            'status' => ServerStatus::Booting,
        ]);

        $this->listener->handle(new ServerLogOutput($server->id, 'Addon Download started'));

        $this->assertEquals(ServerStatus::Booting, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    // --- Crash Detection ---
    // These tests mock GameManager, so the listener must be resolved AFTER
    // the mock is registered (constructor injection captures the dependency).

    public function test_transitions_running_server_to_crashed_on_crash_detection_string(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $listener = $this->makeCrashListener('Segmentation fault');

        $server = Server::factory()->create([
            'name' => 'Crash Test',
            'status' => ServerStatus::Running,
        ]);

        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Crashed, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'crashed'
                && $event->serverName === 'Crash Test';
        });
    }

    public function test_crash_detection_dispatches_discord_webhook_job(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $listener = $this->makeCrashListener('Segmentation fault');

        $server = Server::factory()->create([
            'name' => 'My Server',
            'status' => ServerStatus::Running,
        ]);

        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        Bus::assertDispatched(SendDiscordWebhookJob::class, function (SendDiscordWebhookJob $job) {
            return str_contains($job->content, '**My Server** has crashed')
                && str_contains($job->content, 'Segmentation fault (core dumped)')
                && $job->username === 'Armaani';
        });
    }

    public function test_crash_detection_dispatches_restart_when_auto_restart_enabled(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $listener = $this->makeCrashListener('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Running,
            'auto_restart' => true,
        ]);

        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Crashed, $server->fresh()->status);

        Bus::assertChained([
            StopServerJob::class,
            StartServerJob::class,
        ]);
    }

    public function test_crash_detection_does_not_dispatch_restart_when_auto_restart_disabled(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $listener = $this->makeCrashListener('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Running,
            'auto_restart' => false,
        ]);

        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Crashed, $server->fresh()->status);

        Bus::assertNotDispatched(StopServerJob::class);
        Bus::assertNotDispatched(StartServerJob::class);
    }

    public function test_crash_detection_transitions_booting_server_to_crashed(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $listener = $this->makeCrashListener('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Booting,
        ]);

        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Crashed, $server->fresh()->status);
    }

    public function test_crash_detection_does_not_affect_stopped_server(): void
    {
        Event::fake([ServerStatusChanged::class]);
        $listener = $this->makeCrashListener('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Stopped,
        ]);

        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    public function test_crash_detection_does_not_affect_already_crashed_server(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $listener = $this->makeCrashListener('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Crashed,
        ]);

        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Crashed, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
        Bus::assertNothingDispatched();
    }

    public function test_no_crash_detection_when_handler_returns_null_crash_string(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create([
            'status' => ServerStatus::Running,
        ]);

        $this->listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Running, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    /**
     * Mock the GameManager with a crash detection string and return a fresh listener.
     * Must resolve the listener AFTER registering the mock (constructor injection).
     */
    private function makeCrashListener(string $crashString): DetectServerEvents
    {
        $handler = Mockery::mock(GameHandler::class.', '.DetectsServerState::class);
        $handler->shouldReceive('getModDownloadStartedString')->andReturnNull();
        $handler->shouldReceive('getModDownloadFinishedString')->andReturnNull();
        $handler->shouldReceive('getBootDetectionStrings')->andReturn([]);
        $handler->shouldReceive('getCrashDetectionStrings')->andReturn([$crashString]);

        $manager = Mockery::mock(GameManager::class);
        $manager->shouldReceive('for')->andReturn($handler);
        $this->app->instance(GameManager::class, $manager);

        return app(DetectServerEvents::class);
    }
}
