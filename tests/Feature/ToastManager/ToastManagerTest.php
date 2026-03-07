<?php

namespace Tests\Feature\ToastManager;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToastManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_toast_manager_renders_on_authenticated_pages(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('x-data="{', false);
        $response->assertSee("Livewire.on('toast'", false);
    }

    public function test_toast_manager_does_not_render_on_guest_pages(): void
    {
        $response = $this->get('/');

        $response->assertDontSee("Livewire.on('toast'");
    }

    public function test_seeds_alpine_with_booting_server(): void
    {
        Server::factory()->create(['name' => 'Booting Server', 'status' => ServerStatus::Booting]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Booting Server');
    }

    public function test_seeds_alpine_with_starting_server(): void
    {
        Server::factory()->create(['name' => 'Starting Server', 'status' => ServerStatus::Starting]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Starting Server');
    }

    public function test_excludes_running_servers_from_seed(): void
    {
        Server::factory()->create(['name' => 'Running Server', 'status' => ServerStatus::Running]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('\\u0022Running Server\\u0022', false);
    }

    public function test_seeds_alpine_with_stopping_server(): void
    {
        Server::factory()->create(['name' => 'Stopping Server', 'status' => ServerStatus::Stopping]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Stopping Server');
    }

    public function test_excludes_stopped_servers(): void
    {
        Server::factory()->create(['name' => 'Stopped Server', 'status' => ServerStatus::Stopped]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('\\u0022Stopped Server\\u0022', false);
    }

    public function test_seeds_multiple_active_servers(): void
    {
        Server::factory()->create(['name' => 'Alpha', 'status' => ServerStatus::Booting]);
        Server::factory()->create(['name' => 'Beta', 'status' => ServerStatus::Starting]);
        Server::factory()->create(['name' => 'Gamma', 'status' => ServerStatus::Stopped]);
        Server::factory()->create(['name' => 'Delta', 'status' => ServerStatus::Running]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('\\u0022Alpha\\u0022', false);
        $response->assertSee('\\u0022Beta\\u0022', false);
        $response->assertDontSee('\\u0022Gamma\\u0022', false);
        $response->assertDontSee('\\u0022Delta\\u0022', false);
    }

    public function test_subscribes_to_echo_server_status_channel(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee("Echo.channel('servers')", false);
        $response->assertSee('ServerStatusChanged', false);
    }
}
