<?php

use App\Livewire\Concerns\AuditsActions;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Missions')] class extends Component
{
    use AuditsActions;
    use WithFileUploads;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    #[Validate(['missions.*' => ['required', 'file', 'max:524288']])]
    public array $missions = [];

    public function getMissionsPath(): string
    {
        return config('arma.missions_base_path');
    }

    public function listMissions(): array
    {
        $path = $this->getMissionsPath();

        if (! is_dir($path)) {
            return [];
        }

        $files = glob($path.'/*.pbo');

        if ($files === false) {
            return [];
        }

        $missions = [];

        foreach ($files as $file) {
            $missions[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'created_at' => filemtime($file),
            ];
        }

        usort($missions, fn (array $a, array $b) => $b['created_at'] <=> $a['created_at']);

        return $missions;
    }

    public function uploadMissions(): void
    {
        $this->validate();

        $path = $this->getMissionsPath();

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $uploaded = 0;

        foreach ($this->missions as $file) {
            $originalName = $file->getClientOriginalName();

            if (! str_ends_with(strtolower($originalName), '.pbo')) {
                continue;
            }

            file_put_contents($path.'/'.$originalName, file_get_contents($file->getRealPath()));

            $uploaded++;
        }

        $this->missions = [];

        if ($uploaded > 0) {
            $this->auditLog("uploaded {$uploaded} mission file(s)");
            $this->dispatch('toast', message: __(':count mission(s) uploaded successfully.', ['count' => $uploaded]), variant: 'success');
        }
    }

    public function deleteMission(string $filename): void
    {
        $path = $this->getMissionsPath().'/'.basename($filename);

        if (file_exists($path)) {
            unlink($path);
            $this->auditLog("deleted mission '{$filename}'");
        }
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Missions') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Upload and manage PBO mission files. Missions are symlinked into each game install when a server starts.') }}</flux:text>
    </div>

    <form wire:submit="uploadMissions" class="mb-6 max-w-xl"
        x-data="{ uploading: false, progress: 0 }"
        x-on:livewire-upload-start="uploading = true; progress = 0"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-cancel="uploading = false"
        x-on:livewire-upload-error="uploading = false"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
    >
        <div class="flex items-end gap-4">
            <div class="flex-1">
                <flux:field>
                    <flux:label>{{ __('PBO Files') }}</flux:label>
                    <input type="file" wire:model="missions" multiple accept=".pbo"
                        class="block w-full text-sm text-zinc-500 dark:text-zinc-400
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-lg file:border-0
                            file:text-sm file:font-semibold
                            file:bg-zinc-100 file:text-zinc-700
                            dark:file:bg-zinc-700 dark:file:text-zinc-200
                            hover:file:bg-zinc-200 dark:hover:file:bg-zinc-600
                            file:cursor-pointer cursor-pointer"
                    />
                    <flux:error name="missions.*" />
                </flux:field>
            </div>
            <div>
                <flux:button variant="primary" type="submit" icon="arrow-up-tray" x-bind:disabled="uploading">
                    {{ __('Upload') }}
                </flux:button>
            </div>
        </div>
        <div x-show="uploading" x-cloak class="mt-3">
            <div class="flex items-center gap-3">
                <div class="flex-1 h-2 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                    <div class="h-full rounded-full bg-blue-500 transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div>
                </div>
                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300 tabular-nums" x-text="progress + '%'"></span>
            </div>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Uploading files to server...') }}</p>
        </div>
    </form>

    @php $missionList = $this->listMissions(); @endphp

    @if (empty($missionList))
        <flux:callout>
            {{ __('No missions uploaded yet. Upload PBO files above to get started.') }}
        </flux:callout>
    @else
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-600 dark:text-zinc-300">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Filename') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Size') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Uploaded') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($missionList as $mission)
                        <tr wire:key="mission-{{ $mission['name'] }}">
                            <td class="px-4 py-3 font-mono text-xs">{{ $mission['name'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ number_format($mission['size'] / 1048576, 1) }} MB
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ \Carbon\Carbon::createFromTimestamp($mission['created_at'])->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <flux:button size="sm" variant="ghost" :href="route('missions.download', $mission['name'])" icon="arrow-down-tray">
                                        {{ __('Download') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="deleteMission('{{ $mission['name'] }}')" wire:confirm="{{ __('Delete this mission?') }}" icon="trash">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
