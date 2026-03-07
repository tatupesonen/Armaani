<?php

namespace Tests\Feature\Listeners;

use App\Enums\ServerStatus;
use App\Events\ServerLogOutput;
use App\Events\ServerStatusChanged;
use App\Listeners\DetectServerBooted;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DetectServerBootedTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_is_registered_for_server_log_output_event(): void
    {
        Event::fake();
        Event::assertListening(ServerLogOutput::class, DetectServerBooted::class);
    }

    public function test_transitions_booting_server_to_running_on_steam_connection_message(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['name' => 'Boot Test', 'status' => ServerStatus::Booting]);

        $listener = new DetectServerBooted;
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

        $listener = new DetectServerBooted;
        $listener->handle(new ServerLogOutput($server->id, '15:42:30 BattlEye Server: Initialized'));

        $this->assertEquals(ServerStatus::Booting, $server->fresh()->status);
    }

    public function test_does_not_change_status_if_server_is_already_running(): void
    {
        Event::fake([ServerStatusChanged::class]);

        $server = Server::factory()->create(['status' => ServerStatus::Running]);

        $listener = new DetectServerBooted;
        $listener->handle(new ServerLogOutput($server->id, '15:42:30 Connected to Steam servers'));

        $this->assertEquals(ServerStatus::Running, $server->fresh()->status);

        Event::assertNotDispatched(ServerStatusChanged::class);
    }

    public function test_does_not_change_stopped_server_on_steam_connection_message(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Stopped]);

        $listener = new DetectServerBooted;
        $listener->handle(new ServerLogOutput($server->id, '15:42:30 Connected to Steam servers'));

        $this->assertEquals(ServerStatus::Stopped, $server->fresh()->status);
    }
}
