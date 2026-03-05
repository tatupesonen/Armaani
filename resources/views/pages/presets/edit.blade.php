<?php

use App\Models\ModPreset;
use App\Models\WorkshopMod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Preset')] class extends Component
{
    public ModPreset $modPreset;

    public string $name = '';

    /** @var list<int> */
    public array $selectedMods = [];

    #[Computed]
    public function availableMods()
    {
        return WorkshopMod::query()->orderBy('name')->get();
    }

    public function mount(ModPreset $modPreset): void
    {
        $this->modPreset = $modPreset;
        $this->name = $modPreset->name;
        $this->selectedMods = $modPreset->mods()->pluck('workshop_mods.id')->all();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:mod_presets,name,'.$this->modPreset->id],
            'selectedMods' => ['array'],
            'selectedMods.*' => ['exists:workshop_mods,id'],
        ]);

        $this->modPreset->update(['name' => $this->name]);
        $this->modPreset->mods()->sync($this->selectedMods);

        session()->flash('status', "Preset '{$this->name}' updated successfully.");

        $this->redirect(route('presets.index'), navigate: true);
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Preset') }}: {{ $modPreset->name }}</flux:heading>
        <flux:text class="mt-2">{{ __('Update the preset name and mod selection.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6 max-w-2xl">
        <flux:input wire:model="name" :label="__('Preset Name')" required />

        @if ($this->availableMods->isNotEmpty())
            <flux:field>
                <flux:label>{{ __('Select Mods') }}</flux:label>
                <div class="mt-2 max-h-80 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($this->availableMods as $mod)
                        <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="mod-{{ $mod->id }}">
                            <flux:checkbox wire:model="selectedMods" :value="$mod->id" />
                            <div>
                                <div class="text-sm font-medium">{{ $mod->name ?? __('Mod') . ' #' . $mod->workshop_id }}</div>
                                <div class="text-xs text-zinc-500">{{ __('ID') }}: {{ $mod->workshop_id }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </flux:field>
        @else
            <flux:callout>
                {{ __('No mods available. Add mods from the Workshop Mods page first.') }}
            </flux:callout>
        @endif

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">{{ __('Update Preset') }}</flux:button>
            <flux:button :href="route('presets.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
