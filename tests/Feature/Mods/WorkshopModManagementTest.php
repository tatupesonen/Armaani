<?php

namespace Tests\Feature\Mods;

use App\Enums\InstallationStatus;
use App\Jobs\DownloadModJob;
use App\Models\ModPreset;
use App\Models\User;
use App\Models\WorkshopMod;
use App\Services\SteamWorkshopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WorkshopModManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_mods_page_requires_authentication(): void
    {
        $this->get(route('mods.index'))->assertRedirect(route('login'));
    }

    public function test_mods_page_is_displayed(): void
    {
        $this->actingAs($this->user);

        $this->get(route('mods.index'))->assertOk();
    }

    public function test_mods_page_displays_existing_mods(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->create(['workshop_id' => 463939057, 'name' => 'ACE3']);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->assertSee('463939057')
            ->assertSee('ACE3');
    }

    public function test_mods_page_shows_empty_state(): void
    {
        $this->actingAs($this->user);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->assertSee('No mods added yet');
    }

    public function test_user_can_add_mod_by_workshop_id(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('workshopId', '463939057')
            ->call('addMod')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('workshop_mods', ['workshop_id' => 463939057]);
        Queue::assertPushed(DownloadModJob::class);
    }

    public function test_add_mod_validates_workshop_id(): void
    {
        $this->actingAs($this->user);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('workshopId', '')
            ->call('addMod')
            ->assertHasErrors(['workshopId']);
    }

    public function test_add_mod_validates_numeric_workshop_id(): void
    {
        $this->actingAs($this->user);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('workshopId', 'not-a-number')
            ->call('addMod')
            ->assertHasErrors(['workshopId']);
    }

    public function test_adding_duplicate_mod_does_not_create_new_record(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        WorkshopMod::factory()->installed()->create(['workshop_id' => 463939057]);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('workshopId', '463939057')
            ->call('addMod');

        $this->assertEquals(1, WorkshopMod::where('workshop_id', 463939057)->count());
        Queue::assertNotPushed(DownloadModJob::class);
    }

    public function test_adding_failed_mod_requeues_download(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        WorkshopMod::factory()->failed()->create(['workshop_id' => 463939057]);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('workshopId', '463939057')
            ->call('addMod');

        $mod = WorkshopMod::where('workshop_id', 463939057)->first();
        $this->assertEquals(InstallationStatus::Queued, $mod->installation_status);
        Queue::assertPushed(DownloadModJob::class);
    }

    public function test_user_can_retry_failed_mod(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->failed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('retryMod', $mod->id);

        $this->assertEquals(InstallationStatus::Queued, $mod->fresh()->installation_status);
        Queue::assertPushed(DownloadModJob::class);
    }

    public function test_user_can_delete_mod(): void
    {
        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->installed()->create();

        // Create a temporary directory so the rm -rf branch is exercised.
        $path = $mod->getInstallationPath();
        mkdir($path, 0755, true);

        Process::fake(['rm *' => Process::result()]);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('deleteMod', $mod->id);

        $this->assertDatabaseMissing('workshop_mods', ['id' => $mod->id]);
        Process::assertRan(fn ($p) => in_array('rm', (array) $p->command));

        // Clean up in case Process::fake didn't actually delete it.
        if (is_dir($path)) {
            rmdir($path);
        }
    }

    public function test_deleting_mod_detaches_from_presets(): void
    {
        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->installed()->create();
        $preset = ModPreset::factory()->create();
        $preset->mods()->attach($mod);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('deleteMod', $mod->id);

        $this->assertDatabaseMissing('workshop_mods', ['id' => $mod->id]);
        $this->assertDatabaseMissing('mod_preset_workshop_mod', ['workshop_mod_id' => $mod->id]);
    }

    protected function mockWorkshopService(): void
    {
        $mock = Mockery::mock(SteamWorkshopService::class);
        $this->app->instance(SteamWorkshopService::class, $mock);
    }
}
