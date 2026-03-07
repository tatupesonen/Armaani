<?php

namespace Tests\Feature\Mods;

use App\Enums\InstallationStatus;
use App\Jobs\BatchDownloadModsJob;
use App\Jobs\DownloadModJob;
use App\Models\ModPreset;
use App\Models\SteamAccount;
use App\Models\User;
use App\Models\WorkshopMod;
use App\Services\SteamWorkshopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WorkshopModManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    private string $testModsBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testModsBasePath = sys_get_temp_dir().'/armaman_test_mods_'.uniqid();
        config(['arma.mods_base_path' => $this->testModsBasePath]);

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testModsBasePath);

        parent::tearDown();
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

        $path = $mod->getInstallationPath();
        @mkdir($path, 0755, true);
        $this->assertTrue(is_dir($path), 'Test setup: directory should exist before delete');

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('deleteMod', $mod->id);

        $this->assertDatabaseMissing('workshop_mods', ['id' => $mod->id]);
        $this->assertDirectoryDoesNotExist($path);
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

    public function test_retry_all_failed_batches_mods(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        SteamAccount::factory()->create(['mod_download_batch_size' => 5]);

        WorkshopMod::factory()->failed()->create();
        WorkshopMod::factory()->failed()->create();
        WorkshopMod::factory()->failed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('retryAllFailed');

        // 3 mods with batch size 5 → one BatchDownloadModsJob
        Queue::assertPushed(BatchDownloadModsJob::class, 1);
        Queue::assertPushed(BatchDownloadModsJob::class, function (BatchDownloadModsJob $job): bool {
            return $job->mods->count() === 3;
        });

        // All mods should be queued
        $this->assertEquals(0, WorkshopMod::where('installation_status', InstallationStatus::Failed)->count());
        $this->assertEquals(3, WorkshopMod::where('installation_status', InstallationStatus::Queued)->count());
    }

    public function test_retry_all_failed_uses_individual_job_for_single_mod(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        SteamAccount::factory()->create(['mod_download_batch_size' => 5]);

        WorkshopMod::factory()->failed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('retryAllFailed');

        Queue::assertPushed(DownloadModJob::class, 1);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_retry_all_failed_respects_batch_size_setting(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        SteamAccount::factory()->create(['mod_download_batch_size' => 2]);

        WorkshopMod::factory()->failed()->count(5)->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('retryAllFailed');

        // 5 mods with batch size 2 → 2 BatchDownloadModsJobs (2+2) + 1 DownloadModJob (1)
        Queue::assertPushed(BatchDownloadModsJob::class, 2);
        Queue::assertPushed(DownloadModJob::class, 1);
    }

    public function test_retry_all_failed_does_nothing_when_no_failed_mods(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        WorkshopMod::factory()->installed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('retryAllFailed');

        Queue::assertNotPushed(DownloadModJob::class);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_search_filters_mods_by_name(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->create(['name' => 'ACE3', 'workshop_id' => 463939057]);
        WorkshopMod::factory()->create(['name' => 'CBA_A3', 'workshop_id' => 450814997]);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->assertSee('ACE3')
            ->assertSee('CBA_A3')
            ->set('search', 'ACE')
            ->assertSee('ACE3')
            ->assertDontSee('CBA_A3');
    }

    public function test_search_filters_mods_by_workshop_id(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->create(['name' => 'ACE3', 'workshop_id' => 463939057]);
        WorkshopMod::factory()->create(['name' => 'CBA_A3', 'workshop_id' => 450814997]);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('search', '450814997')
            ->assertDontSee('ACE3')
            ->assertSee('CBA_A3');
    }

    public function test_search_shows_no_results_message(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->create(['name' => 'ACE3']);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('search', 'nonexistent')
            ->assertSee('No mods match your search');
    }

    public function test_update_selected_queues_download_jobs(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        SteamAccount::factory()->create(['mod_download_batch_size' => 5]);

        $mod1 = WorkshopMod::factory()->installed()->create();
        $mod2 = WorkshopMod::factory()->installed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('selectedMods', [$mod1->id, $mod2->id])
            ->call('updateSelected');

        Queue::assertPushed(BatchDownloadModsJob::class, 1);
        Queue::assertPushed(BatchDownloadModsJob::class, function (BatchDownloadModsJob $job) {
            return $job->mods->count() === 2;
        });

        $this->assertEquals(InstallationStatus::Queued, $mod1->fresh()->installation_status);
        $this->assertEquals(InstallationStatus::Queued, $mod2->fresh()->installation_status);
    }

    public function test_update_selected_respects_batch_size(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        SteamAccount::factory()->create(['mod_download_batch_size' => 2]);

        $mods = WorkshopMod::factory()->installed()->count(5)->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('selectedMods', $mods->pluck('id')->all())
            ->call('updateSelected');

        // 5 mods with batch size 2 → 2 BatchDownloadModsJobs (2+2) + 1 DownloadModJob (1)
        Queue::assertPushed(BatchDownloadModsJob::class, 2);
        Queue::assertPushed(DownloadModJob::class, 1);
    }

    public function test_update_selected_uses_individual_job_for_single_mod(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->installed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('selectedMods', [$mod->id])
            ->call('updateSelected');

        Queue::assertPushed(DownloadModJob::class, 1);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_update_selected_skips_installing_and_queued_mods(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        $installedMod = WorkshopMod::factory()->installed()->create();
        $installingMod = WorkshopMod::factory()->installing()->create();
        $queuedMod = WorkshopMod::factory()->create(); // default is queued

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('selectedMods', [$installedMod->id, $installingMod->id, $queuedMod->id])
            ->call('updateSelected');

        Queue::assertPushed(DownloadModJob::class, 1);

        $this->assertEquals(InstallationStatus::Queued, $installedMod->fresh()->installation_status);
        // Installing/Queued mods should remain unchanged
        $this->assertEquals(InstallationStatus::Installing, $installingMod->fresh()->installation_status);
    }

    public function test_update_selected_clears_selection_after(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->installed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('selectedMods', [$mod->id])
            ->call('updateSelected')
            ->assertSet('selectedMods', []);
    }

    public function test_update_selected_does_nothing_with_empty_selection(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('selectedMods', [])
            ->call('updateSelected');

        Queue::assertNotPushed(DownloadModJob::class);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_toggle_select_all_selects_all_selectable_mods(): void
    {
        $this->actingAs($this->user);

        $installed = WorkshopMod::factory()->installed()->create();
        $failed = WorkshopMod::factory()->failed()->create();
        WorkshopMod::factory()->installing()->create(); // not selectable

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->assertSet('selectedMods', [])
            ->call('toggleSelectAll')
            ->assertSet('selectedMods', [$installed->id, $failed->id]);
    }

    public function test_toggle_select_all_deselects_when_all_selected(): void
    {
        $this->actingAs($this->user);

        $mod1 = WorkshopMod::factory()->installed()->create();
        $mod2 = WorkshopMod::factory()->installed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('selectedMods', [$mod1->id, $mod2->id])
            ->call('toggleSelectAll')
            ->assertSet('selectedMods', []);
    }

    public function test_update_selected_can_update_failed_mods(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->failed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('selectedMods', [$mod->id])
            ->call('updateSelected');

        Queue::assertPushed(DownloadModJob::class, 1);
        $this->assertEquals(InstallationStatus::Queued, $mod->fresh()->installation_status);
    }

    public function test_clearing_search_shows_all_mods(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->create(['name' => 'ACE3']);
        WorkshopMod::factory()->create(['name' => 'CBA_A3']);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->set('search', 'ACE')
            ->assertDontSee('CBA_A3')
            ->set('search', '')
            ->assertSee('ACE3')
            ->assertSee('CBA_A3');
    }

    public function test_check_for_updates_fetches_steam_updated_at(): void
    {
        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->installed()->create(['workshop_id' => 463939057]);

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('getMultipleModDetails')
            ->once()
            ->andReturn([
                463939057 => [
                    'name' => $mod->name,
                    'file_size' => $mod->file_size,
                    'time_updated' => 1700000000,
                ],
            ]);
        $this->app->instance(SteamWorkshopService::class, $mock);

        Livewire::test('pages::mods.index')
            ->call('checkForUpdates');

        $mod->refresh();
        $this->assertNotNull($mod->steam_updated_at);
        $this->assertEquals(1700000000, $mod->steam_updated_at->timestamp);
    }

    public function test_check_for_updates_shows_outdated_count(): void
    {
        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->installed()->create([
            'workshop_id' => 463939057,
            'installed_at' => now()->subDays(3),
        ]);

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('getMultipleModDetails')
            ->once()
            ->andReturn([
                463939057 => [
                    'name' => $mod->name,
                    'file_size' => $mod->file_size,
                    'time_updated' => now()->timestamp,
                ],
            ]);
        $this->app->instance(SteamWorkshopService::class, $mock);

        Livewire::test('pages::mods.index')
            ->call('checkForUpdates')
            ->assertSee('1 mod(s) have updates available.');
    }

    public function test_check_for_updates_shows_up_to_date_message(): void
    {
        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->installed()->create([
            'workshop_id' => 463939057,
            'installed_at' => now(),
        ]);

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('getMultipleModDetails')
            ->once()
            ->andReturn([
                463939057 => [
                    'name' => $mod->name,
                    'file_size' => $mod->file_size,
                    'time_updated' => now()->subDay()->timestamp,
                ],
            ]);
        $this->app->instance(SteamWorkshopService::class, $mock);

        Livewire::test('pages::mods.index')
            ->call('checkForUpdates')
            ->assertSee('All mods are up to date.');
    }

    public function test_check_for_updates_only_checks_installed_mods(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->failed()->create(['workshop_id' => 100]);
        WorkshopMod::factory()->create(['workshop_id' => 200]); // queued

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldNotReceive('getMultipleModDetails');
        $this->app->instance(SteamWorkshopService::class, $mock);

        Livewire::test('pages::mods.index')
            ->call('checkForUpdates');
    }

    public function test_outdated_mods_display_update_available_badge(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->outdated()->create(['name' => 'Outdated Mod']);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->assertSee('Update available');
    }

    public function test_up_to_date_mods_do_not_display_update_available_badge(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->installed()->create(['name' => 'Fresh Mod']);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->assertDontSee('Update available');
    }

    public function test_update_all_outdated_queues_jobs_for_outdated_mods_only(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        SteamAccount::factory()->create(['mod_download_batch_size' => 5]);

        $outdated = WorkshopMod::factory()->outdated()->create();
        $upToDate = WorkshopMod::factory()->installed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('updateAllOutdated');

        Queue::assertPushed(DownloadModJob::class, 1);
        Queue::assertPushed(DownloadModJob::class, function (DownloadModJob $job) use ($outdated) {
            return $job->mod->id === $outdated->id;
        });

        $this->assertEquals(InstallationStatus::Queued, $outdated->fresh()->installation_status);
        $this->assertEquals(InstallationStatus::Installed, $upToDate->fresh()->installation_status);
    }

    public function test_update_all_outdated_does_nothing_when_no_outdated_mods(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        WorkshopMod::factory()->installed()->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('updateAllOutdated');

        Queue::assertNotPushed(DownloadModJob::class);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_update_all_outdated_respects_batch_size(): void
    {
        Queue::fake();

        $this->actingAs($this->user);

        SteamAccount::factory()->create(['mod_download_batch_size' => 2]);

        WorkshopMod::factory()->outdated()->count(5)->create();

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->call('updateAllOutdated');

        // 5 mods with batch size 2 → 2 BatchDownloadModsJobs (2+2) + 1 DownloadModJob (1)
        Queue::assertPushed(BatchDownloadModsJob::class, 2);
        Queue::assertPushed(DownloadModJob::class, 1);
    }

    public function test_mods_page_displays_steam_updated_at_and_installed_at(): void
    {
        $this->actingAs($this->user);

        WorkshopMod::factory()->installed()->create([
            'name' => 'Date Test Mod',
            'installed_at' => '2026-01-15 10:30:00',
            'steam_updated_at' => '2026-01-10 08:00:00',
        ]);

        $this->mockWorkshopService();

        Livewire::test('pages::mods.index')
            ->assertSee('Jan 10, 2026 08:00')
            ->assertSee('Jan 15, 2026 10:30');
    }

    protected function mockWorkshopService(): void
    {
        $mock = Mockery::mock(SteamWorkshopService::class);
        $this->app->instance(SteamWorkshopService::class, $mock);
    }
}
