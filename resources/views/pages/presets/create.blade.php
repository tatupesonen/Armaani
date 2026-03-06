<?php

use App\Livewire\Concerns\AuditsActions;
use App\Models\ModPreset;
use App\Models\WorkshopMod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Preset')] class extends Component
{
    use AuditsActions;

    public string $name = '';

    /** @var list<int> */
    public array $selectedMods = [];

    #[Computed]
    public function availableMods()
    {
        return WorkshopMod::query()->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:mod_presets,name'],
            'selectedMods' => ['array'],
            'selectedMods.*' => ['exists:workshop_mods,id'],
        ]);

        $preset = ModPreset::query()->create(['name' => $this->name]);
        $preset->mods()->sync($this->selectedMods);

        $this->auditLog("created preset '{$preset->name}' with ".count($this->selectedMods).' mods');

        session()->flash('status', "Preset '{$this->name}' created successfully.");

        $this->redirect(route('presets.index'), navigate: true);
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Create Preset') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Create a named collection of workshop mods.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6 max-w-2xl">
        <flux:input wire:model="name" :label="__('Preset Name')" required />

        @include('pages.presets.partials.form-fields', ['availableMods' => $this->availableMods])

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">{{ __('Create Preset') }}</flux:button>
            <flux:button :href="route('presets.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
