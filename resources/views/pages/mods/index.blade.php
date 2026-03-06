<?php

use App\Enums\InstallationStatus;
use App\Jobs\BatchDownloadModsJob;
use App\Jobs\DownloadModJob;
use App\Livewire\Concerns\AuditsActions;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Workshop Mods')] class extends Component
{
    use AuditsActions;

    public string $workshopId = '';

    public string $search = '';

    public string $sortStatus = '';

    /** @var list<int> */
    public array $selectedMods = [];

    /** @var array<int, bool> */
    public array $showLogs = [];

    #[Computed]
    public function mods()
    {
        $statusOrder = [
            InstallationStatus::Installing->value => 0,
            InstallationStatus::Queued->value => 1,
            InstallationStatus::Failed->value => 2,
            InstallationStatus::Installed->value => 3,
        ];

        $mods = WorkshopMod::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('workshop_id', 'like', '%'.$this->search.'%');
                });
            })
            ->orderByDesc('created_at')
            ->get();

        if ($this->sortStatus === 'asc') {
            return $mods->sortBy(fn (WorkshopMod $mod) => $statusOrder[$mod->installation_status->value] ?? 99)->values();
        }

        if ($this->sortStatus === 'desc') {
            return $mods->sortByDesc(fn (WorkshopMod $mod) => $statusOrder[$mod->installation_status->value] ?? 99)->values();
        }

        return $mods;
    }

    public function toggleSortStatus(): void
    {
        $this->sortStatus = match ($this->sortStatus) {
            '' => 'asc',
            'asc' => 'desc',
            'desc' => '',
        };

        unset($this->mods);
    }

    #[Computed]
    public function shouldPoll(): bool
    {
        return $this->mods->contains(fn (WorkshopMod $mod) => $mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued
        );
    }

    #[Computed]
    public function failedMods()
    {
        return $this->mods->filter(fn (WorkshopMod $mod) => $mod->installation_status === InstallationStatus::Failed);
    }

    #[Computed]
    public function selectableMods()
    {
        return $this->mods->filter(fn (WorkshopMod $mod) => $mod->installation_status !== InstallationStatus::Installing && $mod->installation_status !== InstallationStatus::Queued
        );
    }

    #[Computed]
    public function isAllSelected(): bool
    {
        return $this->selectableMods->isNotEmpty()
            && $this->selectableMods->every(fn (WorkshopMod $mod) => in_array($mod->id, $this->selectedMods));
    }

    public function toggleSelectAll(): void
    {
        if ($this->isAllSelected) {
            $this->selectedMods = [];
        } else {
            $this->selectedMods = $this->selectableMods->pluck('id')->all();
        }
    }

    public function updateSelected(): void
    {
        $mods = WorkshopMod::query()
            ->whereIn('id', $this->selectedMods)
            ->whereNotIn('installation_status', [InstallationStatus::Installing, InstallationStatus::Queued])
            ->get();

        if ($mods->isEmpty()) {
            return;
        }

        $batchSize = SteamAccount::query()->latest()->first()?->mod_download_batch_size ?? 5;

        foreach ($mods as $mod) {
            $mod->update(['installation_status' => InstallationStatus::Queued, 'progress_pct' => 0]);
        }

        foreach ($mods->chunk($batchSize) as $batch) {
            if ($batch->count() === 1) {
                DownloadModJob::dispatch($batch->first());
            } else {
                BatchDownloadModsJob::dispatch($batch);
            }
        }

        $this->auditLog('queued update for '.$mods->count().' mods in batches of '.$batchSize);

        $this->selectedMods = [];
        unset($this->mods, $this->failedMods, $this->selectableMods, $this->isAllSelected);
    }

    public function toggleLogs(int $modId): void
    {
        $mod = $this->mods->firstWhere('id', $modId);
        $isActive = $mod && ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued);
        $this->showLogs[$modId] = ! ($this->showLogs[$modId] ?? $isActive);
    }

    public function addMod(): void
    {
        $this->validate([
            'workshopId' => ['required', 'numeric', 'min:1'],
        ]);

        $workshopId = (int) $this->workshopId;

        $mod = WorkshopMod::query()->firstOrCreate(
            ['workshop_id' => $workshopId],
            [
                'name' => null,
                'installation_status' => InstallationStatus::Queued,
            ]
        );

        if ($mod->installation_status === InstallationStatus::Failed) {
            $mod->update(['installation_status' => InstallationStatus::Queued]);
        }

        if ($mod->installation_status !== InstallationStatus::Installed) {
            DownloadModJob::dispatch($mod);
            $this->auditLog("queued mod download: workshop ID {$workshopId}");
        }

        $this->workshopId = '';
        unset($this->mods);
    }

    public function retryMod(WorkshopMod $mod): void
    {
        $mod->update(['installation_status' => InstallationStatus::Queued]);

        DownloadModJob::dispatch($mod);
        $this->auditLog("retried mod download: '{$mod->name}' ({$mod->workshop_id})");
        unset($this->mods);
    }

    public function retryAllFailed(): void
    {
        $failedMods = WorkshopMod::query()
            ->where('installation_status', InstallationStatus::Failed)
            ->get();

        if ($failedMods->isEmpty()) {
            return;
        }

        $batchSize = SteamAccount::query()->latest()->first()?->mod_download_batch_size ?? 5;

        foreach ($failedMods as $mod) {
            $mod->update(['installation_status' => InstallationStatus::Queued]);
        }

        foreach ($failedMods->chunk($batchSize) as $batch) {
            if ($batch->count() === 1) {
                DownloadModJob::dispatch($batch->first());
            } else {
                BatchDownloadModsJob::dispatch($batch);
            }
        }

        $this->auditLog('retried all failed mods ('.$failedMods->count().' mods in batches of '.$batchSize.')');
        unset($this->mods, $this->failedMods);
    }

    public function deleteMod(WorkshopMod $mod): void
    {
        if ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued) {
            return;
        }

        $mod->presets()->detach();
        $mod->delete();

        $path = $mod->getInstallationPath();

        if (is_dir($path)) {
            \Illuminate\Support\Facades\Process::run(['rm', '-rf', $path]);
        }

        $this->auditLog("deleted mod '{$mod->name}' ({$mod->workshop_id})");

        unset($this->mods);
    }
}; ?>

<section class="w-full" @if($this->shouldPoll) wire:poll.5s @endif>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Workshop Mods') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Download and manage Steam Workshop mods.') }}</flux:text>
    </div>

    <div class="flex items-end justify-between gap-4 mb-6">
        <form wire:submit="addMod" class="flex items-end gap-4 max-w-xl">
            <div class="flex-1">
                <flux:input wire:model="workshopId" :label="__('Workshop ID')" :placeholder="__('e.g. 463939057')" />
            </div>
            <div>
                <flux:button variant="primary" type="submit" icon="plus">
                    {{ __('Add Mod') }}
                </flux:button>
            </div>
        </form>

        <div class="flex items-center gap-2">
            @if (count($this->selectedMods) > 0)
                <flux:button wire:click="updateSelected" wire:confirm="{{ __('Update :count selected mods? This will re-download them from Steam Workshop.', ['count' => count($this->selectedMods)]) }}" icon="arrow-path" variant="primary">
                    {{ __('Update Selected') }} ({{ count($this->selectedMods) }})
                </flux:button>
            @endif

            @if ($this->failedMods->isNotEmpty())
                <flux:button wire:click="retryAllFailed" wire:confirm="{{ __('Retry all :count failed mods?', ['count' => $this->failedMods->count()]) }}" icon="arrow-path" variant="outline">
                    {{ __('Retry All Failed') }} ({{ $this->failedMods->count() }})
                </flux:button>
            @endif
        </div>
    </div>

    @if ($this->mods->isEmpty() && $this->search === '')
        <flux:callout>
            {{ __('No mods added yet. Enter a Steam Workshop ID above to download a mod.') }}
        </flux:callout>
    @else
        <div class="mb-4 max-w-sm">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by name or workshop ID...') }}" icon="magnifying-glass" clearable />
        </div>

        @if ($this->mods->isEmpty())
            <flux:callout>
                {{ __('No mods match your search.') }}
            </flux:callout>
        @else
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-600 dark:text-zinc-300">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <flux:checkbox wire:click="toggleSelectAll" :checked="$this->isAllSelected" />
                        </th>
                        <th class="px-4 py-3 font-medium">{{ __('Workshop ID') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Size') }}</th>
                        <th class="px-4 py-3 font-medium">
                            <button wire:click="toggleSortStatus" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">
                                {{ __('Status') }}
                                @if ($sortStatus === 'asc')
                                    <flux:icon name="chevron-up" class="size-3.5" />
                                @elseif ($sortStatus === 'desc')
                                    <flux:icon name="chevron-down" class="size-3.5" />
                                @else
                                    <flux:icon name="chevron-up-down" class="size-3.5 opacity-40" />
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                @foreach ($this->mods as $mod)
                    <x-log-viewer
                        channel="mod-download.{{ $mod->id }}"
                        event="ModDownloadOutput"
                        :track-progress="true"
                        max-height="max-h-48"
                        tag="tbody"
                        class="divide-y divide-zinc-200 dark:divide-zinc-700"
                        wire:key="mod-group-{{ $mod->id }}"
                    >
                        <tr wire:key="mod-{{ $mod->id }}">
                            <td class="px-4 py-3">
                                <flux:checkbox wire:model.live="selectedMods" :value="$mod->id" :disabled="$mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued" />
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $mod->workshop_id }}</td>
                            <td class="px-4 py-3">{{ $mod->name ?? __('Fetching...') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($mod->file_size)
                                    {{ number_format($mod->file_size / 1048576, 1) }} MB
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-4 py-3">
                            @if ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued)
                                <div class="w-32">
                                    <div class="flex items-center gap-2 mb-1">
                                        <flux:badge :variant="$mod->installation_status === InstallationStatus::Queued ? 'secondary' : 'warning'" size="sm">
                                            {{ $mod->installation_status === InstallationStatus::Queued ? __('Queued') : __('Downloading') }}
                                        </flux:badge>
                                        <span class="text-xs font-medium" x-text="progress + '%'"></span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                                        <div class="h-1.5 rounded-full bg-amber-500 transition-all duration-500" x-bind:style="'width: ' + progress + '%'"></div>
                                    </div>
                                </div>
                            @else
                                    <flux:badge :variant="$mod->installation_status->badgeVariant()" size="sm">
                                        {{ ucfirst($mod->installation_status->value) }}
                                    </flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @if ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued)
                                        <flux:button size="sm" variant="ghost" wire:click="toggleLogs({{ $mod->id }})" icon="command-line">
                                            {{ __('Logs') }}
                                        </flux:button>
                                    @endif
                                    @if ($mod->installation_status === InstallationStatus::Failed)
                                        <flux:button size="sm" wire:click="retryMod({{ $mod->id }})" icon="arrow-path">
                                            {{ __('Retry') }}
                                        </flux:button>
                                    @endif
                                    <flux:button size="sm" variant="danger" wire:click="deleteMod({{ $mod->id }})" wire:confirm="Delete this mod?" icon="trash" :disabled="$mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>

                        <x-slot:log>
                            @if ($this->showLogs[$mod->id] ?? ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued))
                                <tr wire:key="mod-logs-{{ $mod->id }}">
                                    <td colspan="6" class="px-4 py-3">
                                        <div class="rounded bg-zinc-900 text-zinc-100 p-3 font-mono text-xs max-h-48 overflow-y-auto" x-ref="logContainer">
                                            <template x-if="lines.length === 0">
                                                <div class="text-zinc-500">{{ __('Waiting for output...') }}</div>
                                            </template>
                                            <template x-for="(line, index) in lines" :key="index">
                                                <div class="whitespace-pre-wrap break-all" x-text="line"></div>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </x-slot:log>
                    </x-log-viewer>
                @endforeach
            </table>
        </div>
        @endif
    @endif
</section>
