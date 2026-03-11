<?php

namespace Tests\Feature\Presets;

use App\Jobs\BatchDownloadModsJob;
use App\Jobs\DownloadModJob;
use App\Models\ModPreset;
use App\Models\ReforgerMod;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use App\Services\Steam\SteamWorkshopService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class ModPresetManagementTest extends TestCase
{
    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function test_presets_page_requires_authentication(): void
    {
        $this->asGuest()->get(route('presets.index'))->assertRedirect(route('login'));
    }

    public function test_presets_page_is_displayed(): void
    {
        $this->get(route('presets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('presets/index'));
    }

    public function test_presets_page_displays_existing_presets(): void
    {
        ModPreset::factory()->create(['name' => 'My Combat Preset']);

        $this->get(route('presets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presets/index')
                ->has('presets', 1)
                ->where('presets.0.name', 'My Combat Preset')
            );
    }

    public function test_presets_page_shows_empty_state(): void
    {
        $this->get(route('presets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presets/index')
                ->has('presets', 0)
            );
    }

    public function test_presets_index_shows_mod_count(): void
    {
        $mods = WorkshopMod::factory()->installed()->count(3)->create();
        $preset = ModPreset::factory()->create(['name' => 'Three Mod Preset']);
        $preset->mods()->attach($mods->pluck('id'));

        $this->get(route('presets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presets/index')
                ->has('presets', 1)
                ->where('presets.0.mods_count', 3)
            );
    }

    public function test_presets_are_ordered_by_name(): void
    {
        ModPreset::factory()->create(['name' => 'Zulu Preset']);
        ModPreset::factory()->create(['name' => 'Alpha Preset']);
        ModPreset::factory()->create(['name' => 'Mike Preset']);

        $this->get(route('presets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presets/index')
                ->has('presets', 3)
                ->where('presets.0.name', 'Alpha Preset')
                ->where('presets.1.name', 'Mike Preset')
                ->where('presets.2.name', 'Zulu Preset')
            );
    }

    // ---------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------

    public function test_create_preset_page_is_displayed(): void
    {
        $this->get(route('presets.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presets/create')
                ->has('gameTypes')
                ->has('workshopMods')
                ->has('registeredMods')
            );
    }

    public function test_create_preset_page_lists_available_mods(): void
    {
        WorkshopMod::factory()->installed()->create(['name' => 'ACE3']);
        ReforgerMod::factory()->create(['name' => 'Reforger Mod']);

        $this->get(route('presets.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presets/create')
                ->has('workshopMods', 1)
                ->has('registeredMods.reforger', 1)
            );
    }

    // ---------------------------------------------------------------
    // Store
    // ---------------------------------------------------------------

    public function test_user_can_create_preset(): void
    {
        $this->post(route('presets.store'), [
            'game_type' => 'arma3',
            'name' => 'New Preset',
            'mod_ids' => [],
        ])
            ->assertRedirect(route('presets.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('mod_presets', [
            'name' => 'New Preset',
            'game_type' => 'arma3',
        ]);
    }

    public function test_create_preset_validates_required_name(): void
    {
        $this->post(route('presets.store'), [
            'game_type' => 'arma3',
            'name' => '',
        ])
            ->assertSessionHasErrors(['name']);
    }

    public function test_create_preset_validates_required_game_type(): void
    {
        $this->post(route('presets.store'), [
            'name' => 'Test Preset',
        ])
            ->assertSessionHasErrors(['game_type']);
    }

    public function test_create_preset_validates_unique_name_per_game_type(): void
    {
        ModPreset::factory()->create(['name' => 'Duplicate Name', 'game_type' => 'arma3']);

        $this->post(route('presets.store'), [
            'game_type' => 'arma3',
            'name' => 'Duplicate Name',
        ])
            ->assertSessionHasErrors(['name']);
    }

    public function test_create_preset_allows_same_name_for_different_game_types(): void
    {
        ModPreset::factory()->create(['name' => 'Shared Name', 'game_type' => 'arma3']);

        $this->post(route('presets.store'), [
            'game_type' => 'dayz',
            'name' => 'Shared Name',
            'mod_ids' => [],
        ])
            ->assertRedirect(route('presets.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('mod_presets', 2);
    }

    public function test_user_can_create_preset_with_mods(): void
    {
        $mod1 = WorkshopMod::factory()->installed()->create();
        $mod2 = WorkshopMod::factory()->installed()->create();

        $this->post(route('presets.store'), [
            'game_type' => 'arma3',
            'name' => 'Modded Preset',
            'mod_ids' => [$mod1->id, $mod2->id],
        ])
            ->assertRedirect(route('presets.index'));

        $preset = ModPreset::where('name', 'Modded Preset')->first();
        $this->assertNotNull($preset);
        $this->assertCount(2, $preset->mods);
    }

    public function test_user_can_create_reforger_preset_with_reforger_mods(): void
    {
        $mod1 = ReforgerMod::factory()->create();
        $mod2 = ReforgerMod::factory()->create();

        $this->post(route('presets.store'), [
            'game_type' => 'reforger',
            'name' => 'Reforger Preset',
            'reforger_mod_ids' => [$mod1->id, $mod2->id],
        ])
            ->assertRedirect(route('presets.index'));

        $preset = ModPreset::where('name', 'Reforger Preset')->first();
        $this->assertNotNull($preset);
        $this->assertCount(2, $preset->reforgerMods);
        $this->assertCount(0, $preset->mods);
    }

    public function test_create_preset_validates_mod_ids_exist(): void
    {
        $this->post(route('presets.store'), [
            'game_type' => 'arma3',
            'name' => 'Bad Mods Preset',
            'mod_ids' => [99999],
        ])
            ->assertSessionHasErrors(['mod_ids.0']);
    }

    // ---------------------------------------------------------------
    // Edit
    // ---------------------------------------------------------------

    public function test_edit_preset_page_is_displayed(): void
    {
        $preset = ModPreset::factory()->create();

        $this->get(route('presets.edit', $preset))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presets/edit')
                ->has('preset')
                ->has('workshopMods')
                ->has('registeredMods')
                ->has('modSections')
            );
    }

    public function test_edit_preset_loads_existing_values(): void
    {
        $mod = WorkshopMod::factory()->installed()->create();
        $preset = ModPreset::factory()->create(['name' => 'Existing Preset']);
        $preset->mods()->attach($mod);

        $this->get(route('presets.edit', $preset))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('presets/edit')
                ->where('preset.name', 'Existing Preset')
                ->has('preset.mods', 1)
                ->where('preset.mods.0.id', $mod->id)
            );
    }

    // ---------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------

    public function test_user_can_update_preset(): void
    {
        $preset = ModPreset::factory()->create(['name' => 'Old Name']);

        $this->put(route('presets.update', $preset), [
            'name' => 'Updated Name',
            'mod_ids' => [],
        ])
            ->assertRedirect(route('presets.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('mod_presets', [
            'id' => $preset->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_preset_can_change_mods(): void
    {
        $mod1 = WorkshopMod::factory()->installed()->create();
        $mod2 = WorkshopMod::factory()->installed()->create();
        $mod3 = WorkshopMod::factory()->installed()->create();

        $preset = ModPreset::factory()->create();
        $preset->mods()->attach([$mod1->id, $mod2->id]);

        $this->put(route('presets.update', $preset), [
            'name' => $preset->name,
            'mod_ids' => [$mod2->id, $mod3->id],
        ])
            ->assertRedirect(route('presets.index'));

        $preset->refresh();
        $modIds = $preset->mods->pluck('id')->sort()->values()->all();
        $this->assertEquals([$mod2->id, $mod3->id], $modIds);
    }

    public function test_update_preset_validates_unique_name_excluding_self(): void
    {
        ModPreset::factory()->create(['name' => 'Other Preset']);
        $preset = ModPreset::factory()->create(['name' => 'My Preset']);

        $this->put(route('presets.update', $preset), [
            'name' => 'Other Preset',
        ])
            ->assertSessionHasErrors(['name']);
    }

    public function test_update_preset_allows_keeping_own_name(): void
    {
        $preset = ModPreset::factory()->create(['name' => 'Keep Name']);

        $this->put(route('presets.update', $preset), [
            'name' => 'Keep Name',
            'mod_ids' => [],
        ])
            ->assertRedirect(route('presets.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_update_reforger_preset_syncs_reforger_mods(): void
    {
        $preset = ModPreset::factory()->reforger()->create();
        $mod1 = ReforgerMod::factory()->create();
        $mod2 = ReforgerMod::factory()->create();

        $this->put(route('presets.update', $preset), [
            'name' => $preset->name,
            'reforger_mod_ids' => [$mod1->id, $mod2->id],
        ])
            ->assertRedirect(route('presets.index'));

        $preset->refresh();
        $this->assertCount(2, $preset->reforgerMods);
        $this->assertCount(0, $preset->mods);
    }

    // ---------------------------------------------------------------
    // Destroy
    // ---------------------------------------------------------------

    public function test_user_can_delete_preset(): void
    {
        $preset = ModPreset::factory()->create();

        $this->delete(route('presets.destroy', $preset))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('mod_presets', ['id' => $preset->id]);
    }

    public function test_deleting_preset_does_not_delete_mods(): void
    {
        $mod = WorkshopMod::factory()->installed()->create();
        $preset = ModPreset::factory()->create();
        $preset->mods()->attach($mod);

        $this->delete(route('presets.destroy', $preset));

        $this->assertDatabaseMissing('mod_presets', ['id' => $preset->id]);
        $this->assertDatabaseHas('workshop_mods', ['id' => $mod->id]);
    }

    // ---------------------------------------------------------------
    // Import
    // ---------------------------------------------------------------

    public function test_import_preset_requires_file(): void
    {
        $this->post(route('presets.import'), [])
            ->assertSessionHasErrors(['import_file']);
    }

    public function test_user_can_import_preset_from_html(): void
    {
        Queue::fake();

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getMultipleModDetails')
                ->once()
                ->andReturn([
                    463939057 => ['name' => 'ACE3', 'file_size' => 100000],
                    820924072 => ['name' => 'CBA_A3', 'file_size' => 50000],
                ]);
        });

        SteamAccount::factory()->create();

        $htmlContent = $this->makePresetHtml('Test Import Preset', [463939057, 820924072]);
        $file = UploadedFile::fake()->createWithContent('preset.html', $htmlContent);

        $this->post(route('presets.import'), [
            'import_file' => $file,
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $preset = ModPreset::where('name', 'Test Import Preset')->first();
        $this->assertNotNull($preset);
        $this->assertCount(2, $preset->mods);
    }

    public function test_import_preset_with_custom_name(): void
    {
        Queue::fake();

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getMultipleModDetails')
                ->once()
                ->andReturn([
                    463939057 => ['name' => 'ACE3', 'file_size' => 100000],
                ]);
        });

        SteamAccount::factory()->create();

        $htmlContent = $this->makePresetHtml('Original Name', [463939057]);
        $file = UploadedFile::fake()->createWithContent('preset.html', $htmlContent);

        $this->post(route('presets.import'), [
            'import_file' => $file,
            'import_name' => 'Custom Name',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('mod_presets', ['name' => 'Custom Name']);
        $this->assertDatabaseMissing('mod_presets', ['name' => 'Original Name']);
    }

    public function test_import_preset_rejects_invalid_html(): void
    {
        $file = UploadedFile::fake()->createWithContent('preset.html', '<html><body>No mods here</body></html>');

        $this->post(route('presets.import'), [
            'import_file' => $file,
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['import_file']);
    }

    public function test_import_preset_queues_download_jobs_for_uninstalled_mods(): void
    {
        Queue::fake();

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getMultipleModDetails')
                ->once()
                ->andReturn([
                    463939057 => ['name' => 'ACE3', 'file_size' => 100000],
                ]);
        });

        SteamAccount::factory()->create();

        $htmlContent = $this->makePresetHtml('Download Preset', [463939057]);
        $file = UploadedFile::fake()->createWithContent('preset.html', $htmlContent);

        $this->post(route('presets.import'), [
            'import_file' => $file,
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(DownloadModJob::class);
    }

    public function test_import_preset_does_not_requeue_installed_mods(): void
    {
        Queue::fake();

        WorkshopMod::factory()->installed()->create([
            'workshop_id' => 463939057,
            'game_type' => 'arma3',
        ]);

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getMultipleModDetails')
                ->once()
                ->andReturn([
                    463939057 => ['name' => 'ACE3', 'file_size' => 100000],
                ]);
        });

        SteamAccount::factory()->create();

        $htmlContent = $this->makePresetHtml('Already Installed Preset', [463939057]);
        $file = UploadedFile::fake()->createWithContent('preset.html', $htmlContent);

        $this->post(route('presets.import'), [
            'import_file' => $file,
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertNotPushed(DownloadModJob::class);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_import_preset_batches_large_mod_lists(): void
    {
        Queue::fake();

        $workshopIds = range(100000, 100009);
        $metadata = [];
        foreach ($workshopIds as $id) {
            $metadata[$id] = ['name' => "Mod {$id}", 'file_size' => 1000];
        }

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) use ($metadata) {
            $mock->shouldReceive('getMultipleModDetails')
                ->once()
                ->andReturn($metadata);
        });

        SteamAccount::factory()->create(['mod_download_batch_size' => 5]);

        $htmlContent = $this->makePresetHtml('Big Preset', $workshopIds);
        $file = UploadedFile::fake()->createWithContent('preset.html', $htmlContent);

        $this->post(route('presets.import'), [
            'import_file' => $file,
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(BatchDownloadModsJob::class, 2);
    }

    public function test_import_preset_with_duplicate_name_returns_error(): void
    {
        Queue::fake();

        ModPreset::factory()->create(['name' => 'Existing Preset', 'game_type' => 'arma3']);

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getMultipleModDetails')
                ->once()
                ->andReturn([
                    463939057 => ['name' => 'ACE3', 'file_size' => 100000],
                ]);
        });

        SteamAccount::factory()->create();

        $htmlContent = $this->makePresetHtml('Existing Preset', [463939057]);
        $file = UploadedFile::fake()->createWithContent('preset.html', $htmlContent);

        $this->post(route('presets.import'), [
            'import_file' => $file,
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['import_name']);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makePresetHtml(string $name, array $workshopIds): string
    {
        $links = '';
        foreach ($workshopIds as $id) {
            $links .= '<a href="https://steamcommunity.com/sharedfiles/filedetails/?id='.$id.'">Mod '.$id.'</a>'."\n";
        }

        return <<<HTML
        <html>
        <head>
            <meta name="arma:presetName" content="{$name}">
        </head>
        <body>
            {$links}
        </body>
        </html>
        HTML;
    }
}
