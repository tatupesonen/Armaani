<?php

namespace Tests\Feature\GameInstalls;

use App\Enums\GameInstallStatus;
use App\Jobs\InstallServerJob;
use App\Models\GameInstall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class GameInstallManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_game_installs_page_requires_authentication(): void
    {
        $this->get(route('game-installs.index'))->assertRedirect(route('login'));
    }

    public function test_game_installs_page_is_displayed(): void
    {
        $this->actingAs($this->user);

        $this->get(route('game-installs.index'))->assertOk();
    }

    public function test_game_installs_shows_empty_state_when_none_exist(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::game-installs.index')
            ->assertSee('No game installs yet');
    }

    public function test_game_installs_displays_existing_installs(): void
    {
        $this->actingAs($this->user);

        GameInstall::factory()->create(['name' => 'Main Server Install']);

        Livewire::test('pages::game-installs.index')
            ->assertSee('Main Server Install');
    }

    public function test_user_can_create_game_install_and_job_is_dispatched(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        Livewire::test('pages::game-installs.index')
            ->call('openCreateModal')
            ->set('name', 'My Arma Install')
            ->set('branch', 'public')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('game_installs', [
            'name' => 'My Arma Install',
            'branch' => 'public',
        ]);

        Queue::assertPushed(InstallServerJob::class);
    }

    public function test_create_validates_required_name(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::game-installs.index')
            ->call('openCreateModal')
            ->set('name', '')
            ->call('create')
            ->assertHasErrors(['name']);
    }

    public function test_reinstall_queues_job_and_resets_status(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        $install = GameInstall::factory()->installed()->create();

        Livewire::test('pages::game-installs.index')
            ->call('reinstall', $install->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('game_installs', [
            'id' => $install->id,
            'installation_status' => GameInstallStatus::Queued->value,
        ]);

        Queue::assertPushed(InstallServerJob::class, function (InstallServerJob $job) use ($install) {
            return $job->gameInstall->id === $install->id;
        });
    }

    public function test_user_can_delete_game_install(): void
    {
        $this->actingAs($this->user);

        $install = GameInstall::factory()->installed()->create();

        $path = $install->getInstallationPath();
        @mkdir($path, 0755, true);
        $this->assertTrue(is_dir($path), 'Test setup: directory should exist before delete');

        Livewire::test('pages::game-installs.index')
            ->call('confirmDelete', $install->id)
            ->assertSet('confirmingDelete', true)
            ->assertSet('deletingInstallId', $install->id)
            ->call('deleteInstall');

        $this->assertDatabaseMissing('game_installs', ['id' => $install->id]);
        $this->assertDirectoryDoesNotExist($path);
    }
}
