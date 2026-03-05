<?php

namespace Tests\Feature\Servers;

use App\Enums\ServerStatus;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\User;
use App\Services\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ServerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected GameInstall $gameInstall;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gameInstall = GameInstall::factory()->installed()->create();
    }

    public function test_servers_index_page_requires_authentication(): void
    {
        $this->get(route('servers.index'))->assertRedirect(route('login'));
    }

    public function test_servers_index_page_is_displayed(): void
    {
        $this->actingAs($this->user);

        $this->get(route('servers.index'))->assertOk();
    }

    public function test_servers_index_displays_existing_servers(): void
    {
        $this->actingAs($this->user);

        Server::factory()->create(['name' => 'Alpha Squad Server']);

        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->assertSee('Alpha Squad Server');
    }

    public function test_servers_index_shows_empty_state_when_no_servers(): void
    {
        $this->actingAs($this->user);

        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->assertSee('No servers configured yet');
    }

    public function test_user_can_create_server(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createName', 'New Test Server')
            ->set('createPort', 2302)
            ->set('createQueryPort', 2303)
            ->set('createMaxPlayers', 64)
            ->set('createPassword', 'secret')
            ->set('createAdminPassword', 'admin123')
            ->set('createDescription', 'A test server')
            ->set('createGameInstallId', $this->gameInstall->id)
            ->set('createHeadlessClientCount', 2)
            ->call('createServer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('servers', [
            'name' => 'New Test Server',
            'port' => 2302,
            'query_port' => 2303,
            'max_players' => 64,
            'game_install_id' => $this->gameInstall->id,
            'headless_client_count' => 2,
        ]);
    }

    public function test_create_server_requires_game_install(): void
    {
        $this->actingAs($this->user);

        // Explicitly null out createGameInstallId — setUp creates a GameInstall
        // which openCreateModal would otherwise pre-fill automatically.
        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createGameInstallId', null)
            ->set('createName', 'No Install Server')
            ->set('createPort', 2302)
            ->set('createQueryPort', 2303)
            ->set('createMaxPlayers', 32)
            ->call('createServer')
            ->assertHasErrors(['createGameInstallId']);
    }

    public function test_create_server_validates_required_fields(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createName', '')
            ->call('createServer')
            ->assertHasErrors(['createName']);
    }

    public function test_create_server_validates_port_range(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createName', 'Port Test')
            ->set('createPort', 99999)
            ->set('createGameInstallId', $this->gameInstall->id)
            ->call('createServer')
            ->assertHasErrors(['createPort']);
    }

    public function test_create_server_validates_max_players_range(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createName', 'Player Test')
            ->set('createMaxPlayers', 300)
            ->set('createGameInstallId', $this->gameInstall->id)
            ->call('createServer')
            ->assertHasErrors(['createMaxPlayers']);
    }

    public function test_create_server_validates_port_not_already_allocated(): void
    {
        $this->actingAs($this->user);

        Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createName', 'Conflict Server')
            ->set('createPort', 2302)
            ->set('createQueryPort', 2304)
            ->set('createGameInstallId', $this->gameInstall->id)
            ->call('createServer')
            ->assertHasErrors(['createPort']);
    }

    public function test_create_server_validates_query_port_not_already_allocated(): void
    {
        $this->actingAs($this->user);

        Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createName', 'Conflict Server')
            ->set('createPort', 2304)
            ->set('createQueryPort', 2303)
            ->set('createGameInstallId', $this->gameInstall->id)
            ->call('createServer')
            ->assertHasErrors(['createQueryPort']);
    }

    public function test_create_server_with_preset(): void
    {
        $this->actingAs($this->user);

        $preset = ModPreset::factory()->create();

        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createName', 'Modded Server')
            ->set('createPort', 2302)
            ->set('createQueryPort', 2303)
            ->set('createMaxPlayers', 32)
            ->set('createGameInstallId', $this->gameInstall->id)
            ->set('createActivePresetId', $preset->id)
            ->call('createServer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('servers', [
            'name' => 'Modded Server',
            'active_preset_id' => $preset->id,
        ]);
    }

    public function test_updating_create_port_auto_sets_query_port(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createPort', 2400)
            ->assertSet('createQueryPort', 2401);
    }

    public function test_create_server_validates_headless_client_count(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::servers.index')
            ->call('openCreateModal')
            ->set('createName', 'HC Test')
            ->set('createPort', 2302)
            ->set('createQueryPort', 2303)
            ->set('createMaxPlayers', 32)
            ->set('createGameInstallId', $this->gameInstall->id)
            ->set('createHeadlessClientCount', 15)
            ->call('createServer')
            ->assertHasErrors(['createHeadlessClientCount']);
    }

    public function test_user_can_edit_server_inline(): void
    {
        $this->actingAs($this->user);

        $server = Server::factory()->create(['name' => 'Original Name', 'port' => 2302, 'query_port' => 2303]);

        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->call('startEditing', $server->id)
            ->assertSet('editingServerId', $server->id)
            ->assertSet('editName', 'Original Name')
            ->set('editName', 'Updated Name')
            ->set('editMaxPlayers', 128)
            ->call('saveServer')
            ->assertHasNoErrors()
            ->assertSet('editingServerId', null);

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'name' => 'Updated Name',
            'max_players' => 128,
        ]);
    }

    public function test_edit_server_loads_existing_values(): void
    {
        $this->actingAs($this->user);

        $server = Server::factory()->create([
            'name' => 'Loaded Server',
            'port' => 2310,
            'max_players' => 48,
        ]);

        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->call('startEditing', $server->id)
            ->assertSet('editName', 'Loaded Server')
            ->assertSet('editPort', 2310)
            ->assertSet('editMaxPlayers', 48)
            ->assertSet('editGameInstallId', $server->game_install_id);
    }

    public function test_edit_server_validates_port_not_allocated_to_other_server(): void
    {
        $this->actingAs($this->user);

        Server::factory()->create(['port' => 2310, 'query_port' => 2311]);
        $server = Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->call('startEditing', $server->id)
            ->set('editPort', 2310)
            ->call('saveServer')
            ->assertHasErrors(['editPort']);
    }

    public function test_edit_server_allows_saving_own_port(): void
    {
        $this->actingAs($this->user);

        $server = Server::factory()->create(['port' => 2302, 'query_port' => 2303]);

        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->call('startEditing', $server->id)
            ->set('editName', 'Renamed')
            ->call('saveServer')
            ->assertHasNoErrors();
    }

    public function test_cancel_editing_clears_state(): void
    {
        $this->actingAs($this->user);

        $server = Server::factory()->create();

        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->call('startEditing', $server->id)
            ->assertSet('editingServerId', $server->id)
            ->call('cancelEditing')
            ->assertSet('editingServerId', null);
    }

    public function test_user_can_delete_server(): void
    {
        $this->actingAs($this->user);

        $server = Server::factory()->create();

        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->call('confirmDelete', $server->id)
            ->assertSet('confirmingDelete', true)
            ->assertSet('deletingServerId', $server->id)
            ->call('deleteServer');

        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }

    protected function mockServerProcessService(): void
    {
        $mock = Mockery::mock(ServerProcessService::class);
        $mock->shouldReceive('getStatus')->andReturn(ServerStatus::Stopped);
        $mock->shouldReceive('start')->andReturnNull();
        $mock->shouldReceive('stop')->andReturnNull();
        $mock->shouldReceive('restart')->andReturnNull();
        $this->app->instance(ServerProcessService::class, $mock);
    }
}
