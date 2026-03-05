<?php

namespace Tests\Feature\Presets;

use App\Models\ModPreset;
use App\Models\User;
use App\Models\WorkshopMod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModPresetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_presets_page_requires_authentication(): void
    {
        $this->get(route('presets.index'))->assertRedirect(route('login'));
    }

    public function test_presets_page_is_displayed(): void
    {
        $this->actingAs($this->user);

        $this->get(route('presets.index'))->assertOk();
    }

    public function test_presets_page_displays_existing_presets(): void
    {
        $this->actingAs($this->user);

        ModPreset::factory()->create(['name' => 'My Combat Preset']);

        Livewire::test('pages::presets.index')
            ->assertSee('My Combat Preset');
    }

    public function test_presets_page_shows_empty_state(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::presets.index')
            ->assertSee('No presets yet');
    }

    public function test_create_preset_page_is_displayed(): void
    {
        $this->actingAs($this->user);

        $this->get(route('presets.create'))->assertOk();
    }

    public function test_user_can_create_preset(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::presets.create')
            ->set('name', 'New Preset')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('presets.index'));

        $this->assertDatabaseHas('mod_presets', ['name' => 'New Preset']);
    }

    public function test_create_preset_validates_required_name(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::presets.create')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_create_preset_validates_unique_name(): void
    {
        $this->actingAs($this->user);

        ModPreset::factory()->create(['name' => 'Duplicate Name']);

        Livewire::test('pages::presets.create')
            ->set('name', 'Duplicate Name')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_user_can_create_preset_with_mods(): void
    {
        $this->actingAs($this->user);

        $mod1 = WorkshopMod::factory()->installed()->create();
        $mod2 = WorkshopMod::factory()->installed()->create();

        Livewire::test('pages::presets.create')
            ->set('name', 'Modded Preset')
            ->set('selectedMods', [$mod1->id, $mod2->id])
            ->call('save')
            ->assertHasNoErrors();

        $preset = ModPreset::where('name', 'Modded Preset')->first();
        $this->assertNotNull($preset);
        $this->assertCount(2, $preset->mods);
    }

    public function test_edit_preset_page_is_displayed(): void
    {
        $this->actingAs($this->user);

        $preset = ModPreset::factory()->create();

        $this->get(route('presets.edit', $preset))->assertOk();
    }

    public function test_edit_preset_loads_existing_values(): void
    {
        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->installed()->create();
        $preset = ModPreset::factory()->create(['name' => 'Existing Preset']);
        $preset->mods()->attach($mod);

        Livewire::test('pages::presets.edit', ['modPreset' => $preset])
            ->assertSet('name', 'Existing Preset')
            ->assertSet('selectedMods', [$mod->id]);
    }

    public function test_user_can_update_preset(): void
    {
        $this->actingAs($this->user);

        $preset = ModPreset::factory()->create(['name' => 'Old Name']);

        Livewire::test('pages::presets.edit', ['modPreset' => $preset])
            ->set('name', 'Updated Name')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('presets.index'));

        $this->assertDatabaseHas('mod_presets', [
            'id' => $preset->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_preset_can_change_mods(): void
    {
        $this->actingAs($this->user);

        $mod1 = WorkshopMod::factory()->installed()->create();
        $mod2 = WorkshopMod::factory()->installed()->create();
        $mod3 = WorkshopMod::factory()->installed()->create();

        $preset = ModPreset::factory()->create();
        $preset->mods()->attach([$mod1->id, $mod2->id]);

        Livewire::test('pages::presets.edit', ['modPreset' => $preset])
            ->set('selectedMods', [$mod2->id, $mod3->id])
            ->call('save')
            ->assertHasNoErrors();

        $preset->refresh();
        $modIds = $preset->mods->pluck('id')->sort()->values()->all();
        $this->assertEquals([$mod2->id, $mod3->id], $modIds);
    }

    public function test_update_preset_validates_unique_name_excluding_self(): void
    {
        $this->actingAs($this->user);

        ModPreset::factory()->create(['name' => 'Other Preset']);
        $preset = ModPreset::factory()->create(['name' => 'My Preset']);

        Livewire::test('pages::presets.edit', ['modPreset' => $preset])
            ->set('name', 'Other Preset')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_update_preset_allows_keeping_own_name(): void
    {
        $this->actingAs($this->user);

        $preset = ModPreset::factory()->create(['name' => 'Keep Name']);

        Livewire::test('pages::presets.edit', ['modPreset' => $preset])
            ->set('name', 'Keep Name')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_user_can_delete_preset(): void
    {
        $this->actingAs($this->user);

        $preset = ModPreset::factory()->create();

        Livewire::test('pages::presets.index')
            ->call('deletePreset', $preset->id);

        $this->assertDatabaseMissing('mod_presets', ['id' => $preset->id]);
    }

    public function test_deleting_preset_does_not_delete_mods(): void
    {
        $this->actingAs($this->user);

        $mod = WorkshopMod::factory()->installed()->create();
        $preset = ModPreset::factory()->create();
        $preset->mods()->attach($mod);

        Livewire::test('pages::presets.index')
            ->call('deletePreset', $preset->id);

        $this->assertDatabaseMissing('mod_presets', ['id' => $preset->id]);
        $this->assertDatabaseHas('workshop_mods', ['id' => $mod->id]);
    }

    public function test_presets_index_shows_mod_count(): void
    {
        $this->actingAs($this->user);

        $mods = WorkshopMod::factory()->installed()->count(3)->create();
        $preset = ModPreset::factory()->create(['name' => 'Three Mod Preset']);
        $preset->mods()->attach($mods->pluck('id'));

        Livewire::test('pages::presets.index')
            ->assertSee('3 mods');
    }
}
