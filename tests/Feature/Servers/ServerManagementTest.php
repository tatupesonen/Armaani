<?php

namespace Tests\Feature\Servers;

use App\Contracts\GameHandler;
use App\Enums\ServerStatus;
use App\GameManager;
use App\Jobs\StartServerJob;
use App\Jobs\StopServerJob;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\User;
use App\Services\Server\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class ServerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected GameInstall $gameInstall;

    private string $testServersBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testServersBasePath = sys_get_temp_dir().'/armaani_test_servers_'.uniqid();
        config(['arma.servers_base_path' => $this->testServersBasePath]);

        $this->user = User::factory()->create();
        $this->gameInstall = GameInstall::factory()->installed()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testServersBasePath);

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function test_servers_index_page_requires_authentication(): void
    {
        $this->get(route('servers.index'))->assertRedirect(route('login'));
    }

    public function test_servers_index_page_is_displayed(): void
    {
        $this->actingAs($this->user)
            ->get(route('servers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/index')
                ->has('servers')
                ->has('presets')
                ->has('gameInstalls')
                ->has('gameTypes')
            );
    }

    public function test_servers_index_displays_existing_servers(): void
    {
        Server::factory()->create(['name' => 'Alpha Squad Server']);

        $this->actingAs($this->user)
            ->get(route('servers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/index')
                ->has('servers', 1)
                ->has('servers.0', fn (Assert $server) => $server
                    ->where('name', 'Alpha Squad Server')
                    ->etc()
                )
            );
    }

    public function test_servers_index_displays_profiles_path(): void
    {
        $server = Server::factory()->create(['name' => 'Path Server']);

        $this->actingAs($this->user)
            ->get(route('servers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('servers.0', fn (Assert $s) => $s
                    ->where('profiles_path', $server->getProfilesPath())
                    ->etc()
                )
            );
    }

    public function test_servers_index_shows_empty_state_when_no_servers(): void
    {
        $this->actingAs($this->user)
            ->get(route('servers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('servers', 0)
            );
    }

    public function test_servers_index_includes_arma3_settings(): void
    {
        $server = Server::factory()->create();
        $server->arma3Settings()->create(['max_msg_send' => 2048]);

        $this->actingAs($this->user)
            ->get(route('servers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('servers.0.arma3_settings', fn (Assert $ns) => $ns
                    ->where('max_msg_send', 2048)
                    ->etc()
                )
            );
    }

    // ---------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------

    public function test_user_can_create_server(): void
    {
        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'New Test Server',
                'port' => 2302,
                'query_port' => 2303,
                'max_players' => 64,
                'password' => 'secret',
                'admin_password' => 'admin123',
                'description' => 'A test server',
                'game_install_id' => $this->gameInstall->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('servers', [
            'name' => 'New Test Server',
            'port' => 2302,
            'query_port' => 2303,
            'max_players' => 64,
            'game_install_id' => $this->gameInstall->id,
        ]);
    }

    public function test_create_server_requires_game_install(): void
    {
        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'No Install Server',
                'port' => 2302,
                'query_port' => 2303,
                'max_players' => 32,
            ])
            ->assertSessionHasErrors(['game_install_id']);
    }

    public function test_create_server_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => '',
                'port' => 2302,
                'query_port' => 2303,
                'max_players' => 32,
                'game_install_id' => $this->gameInstall->id,
            ])
            ->assertSessionHasErrors(['name']);
    }

    public function test_create_server_validates_port_range(): void
    {
        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'Port Test',
                'port' => 99999,
                'query_port' => 2303,
                'max_players' => 32,
                'game_install_id' => $this->gameInstall->id,
            ])
            ->assertSessionHasErrors(['port']);
    }

    public function test_create_server_validates_max_players_range(): void
    {
        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'Player Test',
                'port' => 2302,
                'query_port' => 2303,
                'max_players' => 300,
                'game_install_id' => $this->gameInstall->id,
            ])
            ->assertSessionHasErrors(['max_players']);
    }

    public function test_create_server_validates_port_not_already_allocated(): void
    {
        Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'Conflict Server',
                'port' => 2302,
                'query_port' => 2304,
                'max_players' => 32,
                'game_install_id' => $this->gameInstall->id,
            ])
            ->assertSessionHasErrors(['port']);
    }

    public function test_create_server_validates_query_port_not_already_allocated(): void
    {
        Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'Conflict Server',
                'port' => 2304,
                'query_port' => 2303,
                'max_players' => 32,
                'game_install_id' => $this->gameInstall->id,
            ])
            ->assertSessionHasErrors(['query_port']);
    }

    public function test_create_server_rejects_port_matching_existing_query_port(): void
    {
        Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'Cross Conflict Server',
                'port' => 2303,
                'query_port' => 2350,
                'max_players' => 32,
                'game_install_id' => $this->gameInstall->id,
            ])
            ->assertSessionHasErrors(['port']);
    }

    public function test_create_server_rejects_query_port_matching_existing_port(): void
    {
        Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'Cross Conflict Server',
                'port' => 2350,
                'query_port' => 2302,
                'max_players' => 32,
                'game_install_id' => $this->gameInstall->id,
            ])
            ->assertSessionHasErrors(['query_port']);
    }

    public function test_create_server_with_preset(): void
    {
        $preset = ModPreset::factory()->create();

        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'Modded Server',
                'port' => 2302,
                'query_port' => 2303,
                'max_players' => 32,
                'game_install_id' => $this->gameInstall->id,
                'active_preset_id' => $preset->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('servers', [
            'name' => 'Modded Server',
            'active_preset_id' => $preset->id,
        ]);
    }

    public function test_create_server_creates_arma3_settings_with_defaults(): void
    {
        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'arma3',
                'name' => 'Network Test Server',
                'port' => 2350,
                'query_port' => 2351,
                'max_players' => 32,
                'game_install_id' => $this->gameInstall->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $server = Server::query()->where('name', 'Network Test Server')->first();
        $this->assertNotNull($server->arma3Settings);
        $this->assertEquals(128, $server->arma3Settings->max_msg_send);
        $this->assertEquals(512, $server->arma3Settings->max_size_guaranteed);
        $this->assertEquals(1400, $server->arma3Settings->max_packet_size);
    }

    public function test_create_reforger_server_creates_reforger_settings(): void
    {
        $reforgerInstall = GameInstall::factory()->installed()->reforger()->create();

        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'reforger',
                'name' => 'Reforger Server',
                'port' => 2001,
                'max_players' => 32,
                'game_install_id' => $reforgerInstall->id,
                'scenario_id' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $server = Server::query()->where('name', 'Reforger Server')->first();
        $this->assertNotNull($server);
        $this->assertNotNull($server->reforgerSettings);
        $this->assertTrue($server->reforgerSettings->third_person_view_enabled);
        $this->assertEquals('{ECC61978EDCC2B5A}Missions/23_Campaign.conf', $server->reforgerSettings->scenario_id);
    }

    public function test_create_reforger_server_without_scenario_id(): void
    {
        $reforgerInstall = GameInstall::factory()->installed()->reforger()->create();

        $this->actingAs($this->user)
            ->post(route('servers.store'), [
                'game_type' => 'reforger',
                'name' => 'Reforger No Scenario',
                'port' => 2001,
                'max_players' => 32,
                'game_install_id' => $reforgerInstall->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $server = Server::query()->where('name', 'Reforger No Scenario')->first();
        $this->assertNotNull($server);
        $this->assertNotNull($server->reforgerSettings);
        $this->assertNull($server->reforgerSettings->scenario_id);
    }

    // ---------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------

    public function test_user_can_update_server(): void
    {
        $server = Server::factory()->create(['name' => 'Original Name', 'port' => 2302, 'query_port' => 2303]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => 'Updated Name',
                'port' => 2302,
                'query_port' => 2303,
                'max_players' => 128,
                'game_install_id' => $server->game_install_id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'name' => 'Updated Name',
            'max_players' => 128,
        ]);
    }

    public function test_update_server_validates_port_not_allocated_to_other_server(): void
    {
        Server::factory()->create(['port' => 2310, 'query_port' => 2311]);
        $server = Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => $server->name,
                'port' => 2310,
                'query_port' => 2303,
                'max_players' => $server->max_players,
                'game_install_id' => $server->game_install_id,
            ])
            ->assertSessionHasErrors(['port']);
    }

    public function test_update_server_allows_saving_own_port(): void
    {
        $server = Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => 'Renamed',
                'port' => 2302,
                'query_port' => 2303,
                'max_players' => $server->max_players,
                'game_install_id' => $server->game_install_id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_update_server_saves_arma3_settings(): void
    {
        $server = Server::factory()->create();
        $server->arma3Settings()->create([]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => $server->name,
                'port' => $server->port,
                'query_port' => $server->query_port,
                'max_players' => $server->max_players,
                'game_install_id' => $server->game_install_id,
                'max_msg_send' => 2048,
                'min_bandwidth' => 5120000,
                'max_bandwidth' => 104857600,
                'terrain_grid' => 3.125,
                'view_distance' => 5000,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $server->refresh();
        $this->assertEquals(2048, $server->arma3Settings->max_msg_send);
        $this->assertEquals(5120000, $server->arma3Settings->min_bandwidth);
        $this->assertEquals(104857600, $server->arma3Settings->max_bandwidth);
        $this->assertEquals(5000, $server->arma3Settings->view_distance);
    }

    public function test_arma3_settings_validation_rejects_invalid_values(): void
    {
        $server = Server::factory()->create();
        $server->arma3Settings()->create([]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => $server->name,
                'port' => $server->port,
                'query_port' => $server->query_port,
                'max_players' => $server->max_players,
                'game_install_id' => $server->game_install_id,
                'max_msg_send' => 0,
                'max_packet_size' => 100,
            ])
            ->assertSessionHasErrors(['max_msg_send', 'max_packet_size']);
    }

    public function test_update_reforger_server_saves_reforger_settings(): void
    {
        $server = Server::factory()->forReforger()->create();
        $server->reforgerSettings()->create([]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => $server->name,
                'port' => $server->port,
                'query_port' => $server->query_port,
                'max_players' => $server->max_players,
                'game_install_id' => $server->game_install_id,
                'scenario_id' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf',
                'third_person_view_enabled' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $server->refresh();
        $this->assertEquals('{ECC61978EDCC2B5A}Missions/23_Campaign.conf', $server->reforgerSettings->scenario_id);
        $this->assertFalse($server->reforgerSettings->third_person_view_enabled);
    }

    public function test_update_reforger_server_saves_max_fps(): void
    {
        $server = Server::factory()->forReforger()->create();
        $server->reforgerSettings()->create([]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => $server->name,
                'port' => $server->port,
                'query_port' => $server->query_port,
                'max_players' => $server->max_players,
                'game_install_id' => $server->game_install_id,
                'scenario_id' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf',
                'third_person_view_enabled' => true,
                'max_fps' => 120,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $server->refresh();
        $this->assertEquals(120, $server->reforgerSettings->max_fps);
    }

    public function test_update_reforger_server_saves_cross_platform(): void
    {
        $server = Server::factory()->forReforger()->create();
        $server->reforgerSettings()->create([]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => $server->name,
                'port' => $server->port,
                'query_port' => $server->query_port,
                'max_players' => $server->max_players,
                'game_install_id' => $server->game_install_id,
                'scenario_id' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf',
                'third_person_view_enabled' => true,
                'cross_platform' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $server->refresh();
        $this->assertTrue($server->reforgerSettings->cross_platform);
    }

    public function test_update_reforger_server_validates_scenario_id_format(): void
    {
        $server = Server::factory()->forReforger()->create();
        $server->reforgerSettings()->create([]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => $server->name,
                'port' => $server->port,
                'query_port' => $server->query_port,
                'max_players' => $server->max_players,
                'game_install_id' => $server->game_install_id,
                'scenario_id' => 'invalid-format',
                'third_person_view_enabled' => true,
            ])
            ->assertSessionHasErrors(['scenario_id']);
    }

    public function test_update_reforger_server_allows_empty_scenario_id(): void
    {
        $server = Server::factory()->forReforger()->create();
        $server->reforgerSettings()->create([]);

        $this->actingAs($this->user)
            ->put(route('servers.update', $server), [
                'name' => $server->name,
                'port' => $server->port,
                'query_port' => $server->query_port,
                'max_players' => $server->max_players,
                'game_install_id' => $server->game_install_id,
                'scenario_id' => '',
                'third_person_view_enabled' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    // ---------------------------------------------------------------
    // Delete
    // ---------------------------------------------------------------

    public function test_user_can_delete_server(): void
    {
        $server = Server::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('servers.destroy', $server))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }

    public function test_user_cannot_delete_non_stopped_server(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Running]);

        $this->actingAs($this->user)
            ->delete(route('servers.destroy', $server))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('servers', ['id' => $server->id]);
    }

    // ---------------------------------------------------------------
    // Start / Stop / Restart
    // ---------------------------------------------------------------

    public function test_user_can_start_server(): void
    {
        Queue::fake();

        $server = Server::factory()->create();

        $this->actingAs($this->user)
            ->post(route('servers.start', $server))
            ->assertRedirect();

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'status' => ServerStatus::Starting->value,
        ]);

        Queue::assertPushed(StartServerJob::class, fn (StartServerJob $job) => $job->server->id === $server->id);
    }

    public function test_user_can_stop_server(): void
    {
        Queue::fake();

        $server = Server::factory()->create(['status' => ServerStatus::Running]);

        $this->actingAs($this->user)
            ->post(route('servers.stop', $server))
            ->assertRedirect();

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'status' => ServerStatus::Stopping->value,
        ]);

        Queue::assertPushed(StopServerJob::class, fn (StopServerJob $job) => $job->server->id === $server->id);
    }

    public function test_user_can_restart_server(): void
    {
        Bus::fake();

        $server = Server::factory()->create(['status' => ServerStatus::Running]);

        $this->actingAs($this->user)
            ->post(route('servers.restart', $server))
            ->assertRedirect();

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'status' => ServerStatus::Stopping->value,
        ]);

        Bus::assertChained([
            StopServerJob::class,
            StartServerJob::class,
        ]);
    }

    // ---------------------------------------------------------------
    // Headless Clients
    // ---------------------------------------------------------------

    public function test_add_headless_client_calls_service(): void
    {
        $server = Server::factory()->create();

        $mock = Mockery::mock(ServerProcessService::class);
        $mock->shouldReceive('addHeadlessClient')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $server->id))
            ->andReturn(0);
        $this->app->instance(ServerProcessService::class, $mock);

        $this->actingAs($this->user)
            ->post(route('servers.headless-client.add', $server))
            ->assertRedirect();
    }

    public function test_remove_headless_client_calls_service(): void
    {
        $server = Server::factory()->create();

        $mock = Mockery::mock(ServerProcessService::class);
        $mock->shouldReceive('removeHeadlessClient')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $server->id))
            ->andReturn(0);
        $this->app->instance(ServerProcessService::class, $mock);

        $this->actingAs($this->user)
            ->post(route('servers.headless-client.remove', $server))
            ->assertRedirect();
    }

    // ---------------------------------------------------------------
    // Server Log (JSON endpoint)
    // ---------------------------------------------------------------

    public function test_server_log_returns_log_lines(): void
    {
        $server = Server::factory()->create();

        $logPath = $server->getProfilesPath().'/server.log';
        @mkdir(dirname($logPath), 0755, true);
        file_put_contents($logPath, "Line 1\nLine 2\nLine 3\n");

        $handler = Mockery::mock(GameHandler::class);
        $handler->shouldReceive('getServerLogPath')
            ->with(Mockery::on(fn ($s) => $s->id === $server->id))
            ->andReturn($logPath);

        $gameManager = Mockery::mock(GameManager::class);
        $gameManager->shouldReceive('for')
            ->with(Mockery::on(fn ($s) => $s->id === $server->id))
            ->andReturn($handler);
        $this->app->instance(GameManager::class, $gameManager);

        $this->actingAs($this->user)
            ->get(route('servers.log', $server))
            ->assertOk()
            ->assertJson(['lines' => ['Line 1', 'Line 2', 'Line 3']]);
    }

    public function test_server_log_returns_empty_when_no_log_file(): void
    {
        $server = Server::factory()->create();

        $handler = Mockery::mock(GameHandler::class);
        $handler->shouldReceive('getServerLogPath')
            ->andReturn('/nonexistent/path/server.log');

        $gameManager = Mockery::mock(GameManager::class);
        $gameManager->shouldReceive('for')->andReturn($handler);
        $this->app->instance(GameManager::class, $gameManager);

        $this->actingAs($this->user)
            ->get(route('servers.log', $server))
            ->assertOk()
            ->assertJson(['lines' => []]);
    }

    // ---------------------------------------------------------------
    // Server Status (JSON endpoint)
    // ---------------------------------------------------------------

    public function test_server_status_endpoint_returns_status(): void
    {
        $server = Server::factory()->create();

        $mock = Mockery::mock(ServerProcessService::class);
        $mock->shouldReceive('getStatus')->andReturn(ServerStatus::Running);
        $mock->shouldReceive('getRunningHeadlessClientCount')->andReturn(2);
        $this->app->instance(ServerProcessService::class, $mock);

        $this->actingAs($this->user)
            ->get(route('servers.status', $server))
            ->assertOk()
            ->assertJson([
                'status' => ServerStatus::Running->value,
                'headlessClientCount' => 2,
            ]);
    }

    // ---------------------------------------------------------------
    // Launch Command (JSON endpoint)
    // ---------------------------------------------------------------

    public function test_launch_command_endpoint_returns_command(): void
    {
        $server = Server::factory()->create();

        $handler = Mockery::mock(GameHandler::class);
        $handler->shouldReceive('buildLaunchCommand')
            ->with(Mockery::on(fn ($s) => $s->id === $server->id))
            ->andReturn(['/path/to/arma3server', '-port=2302']);

        $gameManager = Mockery::mock(GameManager::class);
        $gameManager->shouldReceive('for')->andReturn($handler);
        $this->app->instance(GameManager::class, $gameManager);

        $this->actingAs($this->user)
            ->get(route('servers.launch-command', $server))
            ->assertOk()
            ->assertJson(['command' => '/path/to/arma3server -port=2302']);
    }
}
