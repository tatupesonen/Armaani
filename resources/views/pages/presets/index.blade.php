<?php

use App\Models\ModPreset;
use App\Services\PresetImportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Mod Presets')] class extends Component
{
    use WithFileUploads;

    public $importFile = null;

    public bool $showImportModal = false;

    #[Computed]
    public function presets()
    {
        return ModPreset::query()->withCount('mods')->orderBy('name')->get();
    }

    public function importPreset(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'max:2048'],
        ]);

        $htmlContent = file_get_contents($this->importFile->getRealPath());

        try {
            $preset = app(PresetImportService::class)->importFromHtml($htmlContent);

            session()->flash('status', "Preset '{$preset->name}' imported. Mod downloads have been queued.");
        } catch (\InvalidArgumentException $e) {
            $this->addError('importFile', $e->getMessage());

            return;
        }

        $this->showImportModal = false;
        $this->importFile = null;
        unset($this->presets);
    }

    public function deletePreset(ModPreset $preset): void
    {
        $preset->delete();
        unset($this->presets);
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Mod Presets') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Organize mods into presets to assign to servers.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button wire:click="$set('showImportModal', true)" icon="arrow-up-tray">
                {{ __('Import HTML') }}
            </flux:button>
            <flux:button variant="primary" :href="route('presets.create')" wire:navigate icon="plus">
                {{ __('New Preset') }}
            </flux:button>
        </div>
    </div>

    @if (session('status'))
        <flux:callout variant="success" class="mb-4">
            {{ session('status') }}
        </flux:callout>
    @endif

    @if ($this->presets->isEmpty())
        <flux:callout>
            {{ __('No presets yet. Create one manually or import from an Arma 3 Launcher HTML file.') }}
        </flux:callout>
    @else
        <div class="space-y-3">
            @foreach ($this->presets as $preset)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 flex items-center justify-between" wire:key="preset-{{ $preset->id }}">
                    <div>
                        <flux:heading>{{ $preset->name }}</flux:heading>
                        <flux:text class="mt-1">{{ $preset->mods_count }} {{ __('mods') }}</flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button size="sm" :href="route('presets.edit', $preset)" wire:navigate icon="pencil">
                            {{ __('Edit') }}
                        </flux:button>
                        <flux:button size="sm" variant="danger" wire:click="deletePreset({{ $preset->id }})" wire:confirm="Delete this preset?" icon="trash">
                            {{ __('Delete') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model="showImportModal">
        <flux:heading>{{ __('Import Arma 3 Launcher Preset') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Upload an HTML preset file exported from the Arma 3 Launcher. All mods will be queued for download.') }}</flux:text>

        <form wire:submit="importPreset" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>{{ __('HTML Preset File') }}</flux:label>
                <input type="file" wire:model="importFile" accept=".html,.htm" class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-semibold dark:file:bg-zinc-700 dark:file:text-zinc-200" />
                <flux:error name="importFile" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showImportModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Import') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
