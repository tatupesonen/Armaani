<?php

namespace Tests\Feature\Mods;

use App\Enums\InstallationStatus;
use App\Jobs\BatchDownloadModsJob;
use App\Jobs\DownloadModJob;
use App\Models\ModPreset;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use App\Services\Steam\SteamWorkshopService;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\Concerns\UsesTestPaths;
use Tests\TestCase;

class WorkshopModManagementTest extends TestCase
{
    use UsesTestPaths;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestPaths(['mods']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTestPaths();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function test_mods_page_requires_authentication(): void
    {
        $this->asGuest()->get(route('mods.index'))->assertRedirect(route('login'));
    }

    public function test_mods_page_is_displayed(): void
    {
        $this->get(route('mods.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('mods/index')
                ->has('mods')
                ->has('filters')
                ->has('installedStats')
            );
    }

    public function test_mods_page_displays_existing_mods(): void
    {
        WorkshopMod::factory()->create(['workshop_id' => 463939057, 'name' => 'ACE3']);

        $this->get(route('mods.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mods', 1)
                ->has('mods.0', fn (Assert $mod) => $mod
                    ->where('name', 'ACE3')
                    ->where('workshop_id', 463939057)
                    ->etc()
                )
            );
    }

    public function test_mods_page_shows_empty_state(): void
    {
        $this->get(route('mods.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mods', 0)
            );
    }

    public function test_mods_page_displays_is_outdated_attribute(): void
    {
        WorkshopMod::factory()->outdated()->create(['name' => 'Outdated Mod']);

        $this->get(route('mods.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mods.0', fn (Assert $mod) => $mod
                    ->where('is_outdated', true)
                    ->etc()
                )
            );
    }

    public function test_up_to_date_mods_have_is_outdated_false(): void
    {
        WorkshopMod::factory()->installed()->create(['name' => 'Fresh Mod']);

        $this->get(route('mods.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mods.0', fn (Assert $mod) => $mod
                    ->where('is_outdated', false)
                    ->etc()
                )
            );
    }

    public function test_installed_stats_shows_count_and_total_size(): void
    {
        WorkshopMod::factory()->installed()->create(['file_size' => 1073741824]); // 1 GB
        WorkshopMod::factory()->installed()->create(['file_size' => 2147483648]); // 2 GB
        WorkshopMod::factory()->failed()->create(['file_size' => 500000000]);

        $this->get(route('mods.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('installedStats.count', 2)
                ->where('installedStats.total_size', 1073741824 + 2147483648)
            );
    }

    public function test_installed_stats_zero_when_no_installed_mods(): void
    {
        WorkshopMod::factory()->failed()->create();

        $this->get(route('mods.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('installedStats.count', 0)
                ->where('installedStats.total_size', 0)
            );
    }

    // ---------------------------------------------------------------
    // Search & Sort (via query params)
    // ---------------------------------------------------------------

    public function test_search_filters_mods_by_name(): void
    {
        WorkshopMod::factory()->create(['name' => 'ACE3', 'workshop_id' => 463939057]);
        WorkshopMod::factory()->create(['name' => 'CBA_A3', 'workshop_id' => 450814997]);

        $this->get(route('mods.index', ['search' => 'ACE']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mods', 1)
                ->has('mods.0', fn (Assert $mod) => $mod
                    ->where('name', 'ACE3')
                    ->etc()
                )
            );
    }

    public function test_search_filters_mods_by_workshop_id(): void
    {
        WorkshopMod::factory()->create(['name' => 'ACE3', 'workshop_id' => 463939057]);
        WorkshopMod::factory()->create(['name' => 'CBA_A3', 'workshop_id' => 450814997]);

        $this->get(route('mods.index', ['search' => '450814997']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mods', 1)
                ->has('mods.0', fn (Assert $mod) => $mod
                    ->where('name', 'CBA_A3')
                    ->etc()
                )
            );
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        WorkshopMod::factory()->create(['name' => 'ACE3']);

        $this->get(route('mods.index', ['search' => 'nonexistent']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mods', 0)
            );
    }

    public function test_sort_by_file_size_ascending(): void
    {
        WorkshopMod::factory()->installed()->create(['name' => 'Large Mod', 'file_size' => 9000000]);
        WorkshopMod::factory()->installed()->create(['name' => 'Small Mod', 'file_size' => 1000000]);

        $this->get(route('mods.index', ['sort_by' => 'file_size', 'sort_direction' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mods', 2)
                ->where('mods.0.name', 'Small Mod')
                ->where('mods.1.name', 'Large Mod')
            );
    }

    public function test_sort_by_file_size_descending(): void
    {
        WorkshopMod::factory()->installed()->create(['name' => 'Large Mod', 'file_size' => 9000000]);
        WorkshopMod::factory()->installed()->create(['name' => 'Small Mod', 'file_size' => 1000000]);

        $this->get(route('mods.index', ['sort_by' => 'file_size', 'sort_direction' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mods.0.name', 'Large Mod')
                ->where('mods.1.name', 'Small Mod')
            );
    }

    public function test_sort_by_installed_at(): void
    {
        WorkshopMod::factory()->installed()->create(['name' => 'Old Mod', 'installed_at' => '2025-01-01']);
        WorkshopMod::factory()->installed()->create(['name' => 'New Mod', 'installed_at' => '2026-06-01']);

        $this->get(route('mods.index', ['sort_by' => 'installed_at', 'sort_direction' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mods.0.name', 'Old Mod')
                ->where('mods.1.name', 'New Mod')
            );
    }

    // ---------------------------------------------------------------
    // Add Mod
    // ---------------------------------------------------------------

    public function test_user_can_add_mod_by_workshop_id(): void
    {
        Queue::fake();

        $this->post(route('mods.store'), ['workshop_id' => 463939057, 'game_type' => 'arma3'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_mods', ['workshop_id' => 463939057]);
        Queue::assertPushed(DownloadModJob::class);
    }

    public function test_add_mod_validates_workshop_id_required(): void
    {
        $this->post(route('mods.store'), ['workshop_id' => ''])
            ->assertSessionHasErrors(['workshop_id']);
    }

    public function test_add_mod_validates_numeric_workshop_id(): void
    {
        $this->post(route('mods.store'), ['workshop_id' => 'not-a-number'])
            ->assertSessionHasErrors(['workshop_id']);
    }

    public function test_adding_duplicate_mod_does_not_create_new_record(): void
    {
        Queue::fake();

        WorkshopMod::factory()->installed()->create(['workshop_id' => 463939057]);

        $this->post(route('mods.store'), ['workshop_id' => 463939057, 'game_type' => 'arma3'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(1, WorkshopMod::where('workshop_id', 463939057)->count());
        Queue::assertNotPushed(DownloadModJob::class);
    }

    public function test_adding_failed_mod_requeues_download(): void
    {
        Queue::fake();

        WorkshopMod::factory()->failed()->create(['workshop_id' => 463939057]);

        $this->post(route('mods.store'), ['workshop_id' => 463939057, 'game_type' => 'arma3'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $mod = WorkshopMod::where('workshop_id', 463939057)->first();
        $this->assertEquals(InstallationStatus::Queued, $mod->installation_status);
        Queue::assertPushed(DownloadModJob::class);
    }

    // ---------------------------------------------------------------
    // Retry
    // ---------------------------------------------------------------

    public function test_user_can_retry_failed_mod(): void
    {
        Queue::fake();

        $mod = WorkshopMod::factory()->failed()->create();

        $this->post(route('mods.retry', $mod))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(InstallationStatus::Queued, $mod->fresh()->installation_status);
        Queue::assertPushed(DownloadModJob::class);
    }

    // ---------------------------------------------------------------
    // Delete
    // ---------------------------------------------------------------

    public function test_user_can_delete_mod(): void
    {
        $mod = WorkshopMod::factory()->installed()->create();

        $path = $mod->getInstallationPath();
        @mkdir($path, 0755, true);
        $this->assertTrue(is_dir($path), 'Test setup: directory should exist before delete');

        $this->delete(route('mods.destroy', $mod))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('workshop_mods', ['id' => $mod->id]);
        $this->assertDirectoryDoesNotExist($path);
    }

    public function test_deleting_mod_detaches_from_presets(): void
    {
        $mod = WorkshopMod::factory()->installed()->create();
        $preset = ModPreset::factory()->create();
        $preset->mods()->attach($mod);

        $this->delete(route('mods.destroy', $mod));

        $this->assertDatabaseMissing('workshop_mods', ['id' => $mod->id]);
        $this->assertDatabaseMissing('mod_preset_workshop_mod', ['workshop_mod_id' => $mod->id]);
    }

    // ---------------------------------------------------------------
    // Retry All Failed
    // ---------------------------------------------------------------

    public function test_retry_all_failed_batches_mods(): void
    {
        Queue::fake();

        SteamAccount::factory()->create(['mod_download_batch_size' => 5]);

        WorkshopMod::factory()->failed()->count(3)->create();

        $this->post(route('mods.retry-all-failed'))
            ->assertRedirect()
            ->assertSessionHas('success');

        // 3 mods with batch size 5 -> one BatchDownloadModsJob
        Queue::assertPushed(BatchDownloadModsJob::class, 1);
        Queue::assertPushed(BatchDownloadModsJob::class, fn (BatchDownloadModsJob $job) => $job->mods->count() === 3);

        $this->assertEquals(0, WorkshopMod::where('installation_status', InstallationStatus::Failed)->count());
        $this->assertEquals(3, WorkshopMod::where('installation_status', InstallationStatus::Queued)->count());
    }

    public function test_retry_all_failed_uses_individual_job_for_single_mod(): void
    {
        Queue::fake();

        SteamAccount::factory()->create(['mod_download_batch_size' => 5]);

        WorkshopMod::factory()->failed()->create();

        $this->post(route('mods.retry-all-failed'))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(DownloadModJob::class, 1);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_retry_all_failed_respects_batch_size_setting(): void
    {
        Queue::fake();

        SteamAccount::factory()->create(['mod_download_batch_size' => 2]);

        WorkshopMod::factory()->failed()->count(5)->create();

        $this->post(route('mods.retry-all-failed'))
            ->assertRedirect()
            ->assertSessionHas('success');

        // 5 mods with batch size 2 -> 2 BatchDownloadModsJobs (2+2) + 1 DownloadModJob (1)
        Queue::assertPushed(BatchDownloadModsJob::class, 2);
        Queue::assertPushed(DownloadModJob::class, 1);
    }

    public function test_retry_all_failed_does_nothing_when_no_failed_mods(): void
    {
        Queue::fake();

        WorkshopMod::factory()->installed()->create();

        $this->post(route('mods.retry-all-failed'))
            ->assertRedirect()
            ->assertSessionHas('info');

        Queue::assertNotPushed(DownloadModJob::class);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    // ---------------------------------------------------------------
    // Update Selected
    // ---------------------------------------------------------------

    public function test_update_selected_queues_download_jobs(): void
    {
        Queue::fake();

        SteamAccount::factory()->create(['mod_download_batch_size' => 5]);

        $mod1 = WorkshopMod::factory()->installed()->create();
        $mod2 = WorkshopMod::factory()->installed()->create();

        $this->post(route('mods.update-selected'), ['mod_ids' => [$mod1->id, $mod2->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(BatchDownloadModsJob::class, 1);
        Queue::assertPushed(BatchDownloadModsJob::class, fn (BatchDownloadModsJob $job) => $job->mods->count() === 2);

        $this->assertEquals(InstallationStatus::Queued, $mod1->fresh()->installation_status);
        $this->assertEquals(InstallationStatus::Queued, $mod2->fresh()->installation_status);
    }

    public function test_update_selected_respects_batch_size(): void
    {
        Queue::fake();

        SteamAccount::factory()->create(['mod_download_batch_size' => 2]);

        $mods = WorkshopMod::factory()->installed()->count(5)->create();

        $this->post(route('mods.update-selected'), ['mod_ids' => $mods->pluck('id')->all()])
            ->assertRedirect()
            ->assertSessionHas('success');

        // 5 mods with batch size 2 -> 2 BatchDownloadModsJobs (2+2) + 1 DownloadModJob (1)
        Queue::assertPushed(BatchDownloadModsJob::class, 2);
        Queue::assertPushed(DownloadModJob::class, 1);
    }

    public function test_update_selected_uses_individual_job_for_single_mod(): void
    {
        Queue::fake();

        $mod = WorkshopMod::factory()->installed()->create();

        $this->post(route('mods.update-selected'), ['mod_ids' => [$mod->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(DownloadModJob::class, 1);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_update_selected_skips_installing_and_queued_mods(): void
    {
        Queue::fake();

        $installedMod = WorkshopMod::factory()->installed()->create();
        $installingMod = WorkshopMod::factory()->installing()->create();
        $queuedMod = WorkshopMod::factory()->create(); // default is queued

        $this->post(route('mods.update-selected'), [
            'mod_ids' => [$installedMod->id, $installingMod->id, $queuedMod->id],
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(DownloadModJob::class, 1);

        $this->assertEquals(InstallationStatus::Queued, $installedMod->fresh()->installation_status);
        $this->assertEquals(InstallationStatus::Installing, $installingMod->fresh()->installation_status);
    }

    public function test_update_selected_validates_mod_ids_required(): void
    {
        Queue::fake();

        $this->post(route('mods.update-selected'), ['mod_ids' => []])
            ->assertSessionHasErrors(['mod_ids']);

        Queue::assertNotPushed(DownloadModJob::class);
    }

    public function test_update_selected_can_update_failed_mods(): void
    {
        Queue::fake();

        $mod = WorkshopMod::factory()->failed()->create();

        $this->post(route('mods.update-selected'), ['mod_ids' => [$mod->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(DownloadModJob::class, 1);
        $this->assertEquals(InstallationStatus::Queued, $mod->fresh()->installation_status);
    }

    // ---------------------------------------------------------------
    // Check For Updates
    // ---------------------------------------------------------------

    public function test_check_for_updates_fetches_steam_updated_at(): void
    {
        $mod = WorkshopMod::factory()->installed()->create(['workshop_id' => 463939057]);

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getMultipleModDetails')
                ->once()
                ->andReturn([
                    463939057 => ['name' => 'Test Mod', 'file_size' => 1000, 'time_updated' => 1700000000, 'game_type' => null],
                ]);
        });

        $this->post(route('mods.check-for-updates'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $mod->refresh();
        $this->assertNotNull($mod->steam_updated_at);
        $this->assertEquals(1700000000, $mod->steam_updated_at->timestamp);
    }

    public function test_check_for_updates_only_checks_installed_mods(): void
    {
        WorkshopMod::factory()->failed()->create(['workshop_id' => 100]);
        WorkshopMod::factory()->create(['workshop_id' => 200]); // queued

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('getMultipleModDetails');
        });

        $this->post(route('mods.check-for-updates'))
            ->assertRedirect()
            ->assertSessionHas('info');
    }

    // ---------------------------------------------------------------
    // Update All Outdated
    // ---------------------------------------------------------------

    public function test_update_all_outdated_queues_jobs_for_outdated_mods_only(): void
    {
        Queue::fake();

        SteamAccount::factory()->create(['mod_download_batch_size' => 5]);

        $outdated = WorkshopMod::factory()->outdated()->create();
        $upToDate = WorkshopMod::factory()->installed()->create();

        $this->post(route('mods.update-all-outdated'))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(DownloadModJob::class, 1);
        Queue::assertPushed(DownloadModJob::class, fn (DownloadModJob $job) => $job->mod->id === $outdated->id);

        $this->assertEquals(InstallationStatus::Queued, $outdated->fresh()->installation_status);
        $this->assertEquals(InstallationStatus::Installed, $upToDate->fresh()->installation_status);
    }

    public function test_update_all_outdated_does_nothing_when_no_outdated_mods(): void
    {
        Queue::fake();

        WorkshopMod::factory()->installed()->create();

        $this->post(route('mods.update-all-outdated'))
            ->assertRedirect()
            ->assertSessionHas('info');

        Queue::assertNotPushed(DownloadModJob::class);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_update_all_outdated_respects_batch_size(): void
    {
        Queue::fake();

        SteamAccount::factory()->create(['mod_download_batch_size' => 2]);

        WorkshopMod::factory()->outdated()->count(5)->create();

        $this->post(route('mods.update-all-outdated'))
            ->assertRedirect()
            ->assertSessionHas('success');

        // 5 mods with batch size 2 -> 2 BatchDownloadModsJobs (2+2) + 1 DownloadModJob (1)
        Queue::assertPushed(BatchDownloadModsJob::class, 2);
        Queue::assertPushed(DownloadModJob::class, 1);
    }

    // ---------------------------------------------------------------
    // Additional edge cases: validation
    // ---------------------------------------------------------------

    public function test_add_mod_rejects_negative_workshop_id(): void
    {
        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('getModDetails');
        });

        SteamAccount::factory()->create();

        $this->post(route('mods.store'), [
            'workshop_id' => -1,
            'game_type' => 'arma3',
        ])
            ->assertSessionHasErrors(['workshop_id']);
    }

    public function test_add_mod_rejects_invalid_game_type(): void
    {
        SteamAccount::factory()->create();

        $this->post(route('mods.store'), [
            'workshop_id' => 463939057,
            'game_type' => 'invalid_game',
        ])
            ->assertSessionHasErrors(['game_type']);
    }
}
