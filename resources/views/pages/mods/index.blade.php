<?php

use App\Enums\InstallationStatus;
use App\Jobs\DownloadModJob;
use App\Models\WorkshopMod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Workshop Mods')] class extends Component
{
    public string $workshopId = '';

    /** @var array<int, array{progress: int, lines: string[]}> */
    public array $modOutputs = [];

    /** @var array<int, bool> */
    public array $showLogs = [];

    #[Computed]
    public function mods()
    {
        return WorkshopMod::query()->orderByDesc('created_at')->get();
    }

    public function getListeners(): array
    {
        $listeners = [];

        foreach ($this->mods as $mod) {
            $listeners["echo:mod-download.{$mod->id},ModDownloadOutput"] = 'handleModOutput';
        }

        return $listeners;
    }

    public function handleModOutput(array $event): void
    {
        $id = $event['modId'];
        $line = $event['line'];
        $progress = $event['progressPct'];

        if (! isset($this->modOutputs[$id])) {
            $this->modOutputs[$id] = ['progress' => 0, 'lines' => []];
        }

        $this->modOutputs[$id]['progress'] = $progress;
        $this->modOutputs[$id]['lines'][] = $line;

        // Keep only the last 200 lines
        if (count($this->modOutputs[$id]['lines']) > 200) {
            $this->modOutputs[$id]['lines'] = array_slice($this->modOutputs[$id]['lines'], -200);
        }

        // If the download finished, refresh the mods list
        if (str_contains($line, 'completed successfully') || str_contains($line, 'failed')) {
            unset($this->mods);
        }
    }

    public function toggleLogs(int $modId): void
    {
        $this->showLogs[$modId] = ! ($this->showLogs[$modId] ?? false);
    }

    public function getProgress(int $modId): int
    {
        return $this->modOutputs[$modId]['progress'] ?? 0;
    }

    public function getLogLines(int $modId): array
    {
        return $this->modOutputs[$modId]['lines'] ?? [];
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
            Log::info('User '.auth()->id().' ('.auth()->user()->name.") queued mod download: workshop ID {$workshopId}");
        }

        $this->workshopId = '';
        unset($this->mods);
    }

    public function retryMod(WorkshopMod $mod): void
    {
        $mod->update(['installation_status' => InstallationStatus::Queued]);

        // Clear previous log output for this mod
        $this->modOutputs[$mod->id] = ['progress' => 0, 'lines' => []];

        DownloadModJob::dispatch($mod);
        Log::info('User '.auth()->id().' ('.auth()->user()->name.") retried mod download: '{$mod->name}' ({$mod->workshop_id})");
        unset($this->mods);
    }

    public function deleteMod(WorkshopMod $mod): void
    {
        $mod->presets()->detach();
        $mod->delete();

        $path = $mod->getInstallationPath();

        if (is_dir($path)) {
            \Illuminate\Support\Facades\Process::run(['rm', '-rf', $path]);
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") deleted mod '{$mod->name}' ({$mod->workshop_id})");

        unset($this->mods);
    }

    public function statusVariant(InstallationStatus $status): string
    {
        return match ($status) {
            InstallationStatus::Installed => 'success',
            InstallationStatus::Installing => 'warning',
            InstallationStatus::Queued => 'secondary',
            InstallationStatus::Failed => 'danger',
        };
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Workshop Mods') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Download and manage Steam Workshop mods.') }}</flux:text>
    </div>

    <form wire:submit="addMod" class="flex items-end gap-4 mb-6 max-w-xl">
        <div class="flex-1">
            <flux:input wire:model="workshopId" :label="__('Workshop ID')" :placeholder="__('e.g. 463939057')" />
        </div>
        <div>
            <flux:button variant="primary" type="submit" icon="plus">
                {{ __('Add Mod') }}
            </flux:button>
        </div>
    </form>

    @if ($this->mods->isEmpty())
        <flux:callout>
            {{ __('No mods added yet. Enter a Steam Workshop ID above to download a mod.') }}
        </flux:callout>
    @else
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-600 dark:text-zinc-300">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Workshop ID') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Size') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($this->mods as $mod)
                        <tr wire:key="mod-{{ $mod->id }}">
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
                                @if ($mod->installation_status === InstallationStatus::Installing)
                                    @php $progress = $this->getProgress($mod->id) ?: $mod->progress_pct; @endphp
                                    <div class="w-32">
                                        <div class="flex items-center gap-2 mb-1">
                                            <flux:badge variant="warning" size="sm">{{ __('Downloading') }}</flux:badge>
                                            <span class="text-xs font-medium">{{ $progress }}%</span>
                                        </div>
                                        <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                                            <div class="h-1.5 rounded-full bg-amber-500 transition-all duration-500" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </div>
                                @else
                                    <flux:badge :variant="$this->statusVariant($mod->installation_status)" size="sm">
                                        {{ ucfirst($mod->installation_status->value) }}
                                    </flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @if ($mod->installation_status === InstallationStatus::Installing || $mod->installation_status === InstallationStatus::Queued || ! empty($this->getLogLines($mod->id)))
                                        <flux:button size="sm" variant="ghost" wire:click="toggleLogs({{ $mod->id }})" icon="command-line">
                                            {{ __('Logs') }}
                                        </flux:button>
                                    @endif
                                    @if ($mod->installation_status === InstallationStatus::Failed)
                                        <flux:button size="sm" wire:click="retryMod({{ $mod->id }})" icon="arrow-path">
                                            {{ __('Retry') }}
                                        </flux:button>
                                    @endif
                                    @if ($mod->installation_status !== InstallationStatus::Installing)
                                        <flux:button size="sm" variant="danger" wire:click="deleteMod({{ $mod->id }})" wire:confirm="Delete this mod?" icon="trash">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if ($this->showLogs[$mod->id] ?? false)
                            <tr wire:key="mod-logs-{{ $mod->id }}">
                                <td colspan="5" class="px-4 py-3">
                                    @php $lines = $this->getLogLines($mod->id); @endphp
                                    <div class="rounded bg-zinc-900 text-zinc-100 p-3 font-mono text-xs max-h-48 overflow-y-auto" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                                        @forelse ($lines as $logLine)
                                            <div class="whitespace-pre-wrap break-all">{{ $logLine }}</div>
                                        @empty
                                            <div class="text-zinc-500">{{ __('Waiting for output...') }}</div>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
