<?php

namespace Tests\Feature\Listeners;

use App\Contracts\GameHandler;
use App\Enums\ServerStatus;
use App\Events\ServerLogOutput;
use App\Events\ServerStatusChanged;
use App\GameManager;
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

    public function test_listener_is_registered_for_server_log_output_event(): void
    {
        Event::fake();
        Event::assertListening(ServerLogOutput::class, DetectServerEvents::class);
    }

    public function test_transitions_booting_server_to_running_on_steam_connection_message(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['name' => 'Boot Test', 'status' => ServerStatus::Booting]);

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, '15:42:30 Connected to Steam servers'));

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

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, '15:42:30 BattlEye Server: Initialized'));

        $this->assertEquals(ServerStatus::Booting, $server->fresh()->status);
    }

    public function test_does_not_change_status_if_server_is_already_running(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['status' => ServerStatus::Running]);

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, '15:42:30 Connected to Steam servers'));

        $this->assertEquals(ServerStatus::Running, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    public function test_does_not_change_stopped_server_on_steam_connection_message(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Stopped]);

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, '15:42:30 Connected to Steam servers'));

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

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, 'Addon Download started'));

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

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, 'Required addons are ready to use.'));

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

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, 'Game::LoadEntities took: 5.2s'));

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

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, 'Addon Download started'));

        $this->assertEquals(ServerStatus::Booting, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    // --- Crash Detection ---

    public function test_transitions_running_server_to_crashed_on_crash_detection_string(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $this->mockGameManagerWithCrashString('Segmentation fault');

        $server = Server::factory()->create([
            'name' => 'Crash Test',
            'status' => ServerStatus::Running,
        ]);

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Crashed, $server->fresh()->status);

        Event::assertDispatched(ServerStatusChanged::class, function (ServerStatusChanged $event) use ($server) {
            return $event->serverId === $server->id
                && $event->status === 'crashed'
                && $event->serverName === 'Crash Test';
        });
    }

    public function test_crash_detection_dispatches_restart_when_auto_restart_enabled(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $this->mockGameManagerWithCrashString('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Running,
            'auto_restart' => true,
        ]);

        $listener = new DetectServerEvents;
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
        $this->mockGameManagerWithCrashString('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Running,
            'auto_restart' => false,
        ]);

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Crashed, $server->fresh()->status);

        Bus::assertNothingDispatched();
    }

    public function test_crash_detection_transitions_booting_server_to_crashed(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $this->mockGameManagerWithCrashString('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Booting,
        ]);

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Crashed, $server->fresh()->status);
    }

    public function test_crash_detection_does_not_affect_stopped_server(): void
    {
        Event::fake([ServerStatusChanged::class]);
        $this->mockGameManagerWithCrashString('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Stopped,
        ]);

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    public function test_crash_detection_does_not_affect_already_crashed_server(): void
    {
        Event::fake([ServerStatusChanged::class]);
        Bus::fake();
        $this->mockGameManagerWithCrashString('Segmentation fault');

        $server = Server::factory()->create([
            'status' => ServerStatus::Crashed,
        ]);

        $listener = new DetectServerEvents;
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

        $listener = new DetectServerEvents;
        $listener->handle(new ServerLogOutput($server->id, 'Segmentation fault (core dumped)'));

        $this->assertEquals(ServerStatus::Running, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    /**
     * Mock the GameManager to return a handler with a specific crash detection string.
     */
    private function mockGameManagerWithCrashString(string $crashString): void
    {
        $handler = Mockery::mock(GameHandler::class);
        $handler->shouldReceive('getModDownloadStartedString')->andReturnNull();
        $handler->shouldReceive('getModDownloadFinishedString')->andReturnNull();
        $handler->shouldReceive('getBootDetectionString')->andReturnNull();
        $handler->shouldReceive('getCrashDetectionString')->andReturn($crashString);

        $manager = Mockery::mock(GameManager::class);
        $manager->shouldReceive('for')->andReturn($handler);
        $this->app->instance(GameManager::class, $manager);
    }
}
