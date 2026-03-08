<?php

namespace Tests\Feature\GameInstalls;

use App\Enums\InstallationStatus;
use App\Jobs\InstallServerJob;
use App\Models\GameInstall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GameInstallManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    private string $testGamesBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testGamesBasePath = sys_get_temp_dir().'/armaani_test_games_'.uniqid();
        config(['arma.games_base_path' => $this->testGamesBasePath]);

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testGamesBasePath);

        parent::tearDown();
    }

    public function test_game_installs_page_requires_authentication(): void
    {
        $this->get(route('game-installs.index'))->assertRedirect(route('login'));
    }

    public function test_game_installs_page_is_displayed(): void
    {
        $this->actingAs($this->user)
            ->get(route('game-installs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('game-installs/index')
                ->has('installs')
                ->has('gameTypes')
            );
    }

    public function test_game_installs_shows_empty_state_when_none_exist(): void
    {
        $this->actingAs($this->user)
            ->get(route('game-installs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('game-installs/index')
                ->has('installs', 0)
            );
    }

    public function test_game_installs_displays_existing_installs(): void
    {
        GameInstall::factory()->create(['name' => 'Main Server Install']);

        $this->actingAs($this->user)
            ->get(route('game-installs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('game-installs/index')
                ->has('installs', 1)
                ->has('installs.0', fn (Assert $install) => $install
                    ->where('name', 'Main Server Install')
                    ->etc()
                )
            );
    }

    public function test_user_can_create_game_install_and_job_is_dispatched(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post(route('game-installs.store'), [
                'game_type' => 'arma3',
                'name' => 'My Arma Install',
                'branch' => 'public',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('game_installs', [
            'name' => 'My Arma Install',
            'branch' => 'public',
        ]);

        Queue::assertPushed(InstallServerJob::class);
    }

    public function test_create_validates_required_name(): void
    {
        $this->actingAs($this->user)
            ->post(route('game-installs.store'), [
                'game_type' => 'arma3',
                'name' => '',
                'branch' => 'public',
            ])
            ->assertSessionHasErrors(['name']);
    }

    public function test_reinstall_queues_job_and_resets_status(): void
    {
        Queue::fake();

        $install = GameInstall::factory()->installed()->create();

        $this->actingAs($this->user)
            ->post(route('game-installs.reinstall', $install))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('game_installs', [
            'id' => $install->id,
            'installation_status' => InstallationStatus::Queued->value,
        ]);

        Queue::assertPushed(InstallServerJob::class, function (InstallServerJob $job) use ($install) {
            return $job->gameInstall->id === $install->id;
        });
    }

    public function test_user_can_delete_game_install(): void
    {
        $install = GameInstall::factory()->installed()->create();

        $path = $install->getInstallationPath();
        @mkdir($path, 0755, true);
        $this->assertTrue(is_dir($path), 'Test setup: directory should exist before delete');

        $this->actingAs($this->user)
            ->delete(route('game-installs.destroy', $install))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('game_installs', ['id' => $install->id]);
        $this->assertDirectoryDoesNotExist($path);
    }
}
