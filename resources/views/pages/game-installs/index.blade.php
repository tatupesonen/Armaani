<?php

use App\Enums\InstallationStatus;
use App\Jobs\InstallServerJob;
use App\Livewire\Concerns\AuditsActions;
use App\Models\GameInstall;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Game Installs')] class extends Component
{
    use AuditsActions;

    public bool $showCreateModal = false;

    public string $name = 'Arma 3 Server';

    public string $branch = 'public';

    public bool $confirmingDelete = false;

    public ?int $deletingInstallId = null;

    /** @var array<int, bool> */
    public array $showLogs = [];

    #[Computed]
    public function installs()
    {
        return GameInstall::query()->orderBy('name')->get();
    }

    #[Computed]
    public function shouldPoll(): bool
    {
        return $this->installs->contains(fn (GameInstall $install) => $install->installation_status === InstallationStatus::Installing || $install->installation_status === InstallationStatus::Queued
        );
    }

    public function toggleLogs(int $installId): void
    {
        $install = $this->installs->firstWhere('id', $installId);
        $isActive = $install && ($install->installation_status === InstallationStatus::Installing || $install->installation_status === InstallationStatus::Queued);
        $this->showLogs[$installId] = ! ($this->showLogs[$installId] ?? $isActive);
    }

    public function openCreateModal(): void
    {
        $this->name = 'Arma 3 Server';
        $this->branch = 'public';
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch' => ['required', 'string', 'max:64'],
        ]);

        $install = GameInstall::query()->create([
            ...$validated,
            'installation_status' => InstallationStatus::Queued,
        ]);

        InstallServerJob::dispatch($install);

        $this->auditLog("queued game install '{$install->name}' (branch: {$install->branch})");

        $this->showCreateModal = false;
        unset($this->installs);

        session()->flash('status', "Game install '{$install->name}' queued.");
    }

    public function reinstall(GameInstall $gameInstall): void
    {
        $gameInstall->update(['installation_status' => InstallationStatus::Queued]);

        InstallServerJob::dispatch($gameInstall);

        $this->auditLog("queued reinstall of '{$gameInstall->name}' (branch: {$gameInstall->branch})");

        unset($this->installs);

        session()->flash('status', "Re-install queued for '{$gameInstall->name}'.");
    }

    public function confirmDelete(int $installId): void
    {
        $this->confirmingDelete = true;
        $this->deletingInstallId = $installId;
    }

    public function deleteInstall(): void
    {
        if ($this->deletingInstallId) {
            $install = GameInstall::query()->find($this->deletingInstallId);

            if ($install) {
                if ($install->installation_status === InstallationStatus::Installing || $install->installation_status === InstallationStatus::Queued) {
                    return;
                }

                $path = $install->getInstallationPath();
                $install->delete();

                if (is_dir($path)) {
                    \Illuminate\Support\Facades\Process::run(['rm', '-rf', $path]);
                }

                $this->auditLog("deleted game install '{$install->name}'");
            }
        }

        $this->confirmingDelete = false;
        $this->deletingInstallId = null;

        unset($this->installs);
    }
}; ?>

<section class="w-full" @if($this->shouldPoll) wire:poll.5s @endif>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Game Installs') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Manage Arma 3 dedicated server installations.') }}</flux:text>
        </div>
        <flux:button variant="primary" wire:click="openCreateModal" icon="plus">
            {{ __('New Install') }}
        </flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" class="mb-4">
            {{ session('status') }}
        </flux:callout>
    @endif

    @if ($this->installs->isEmpty())
        <flux:callout>
            {{ __('No game installs yet. Create one to download the Arma 3 dedicated server files via SteamCMD.') }}
        </flux:callout>
    @else
        <div class="space-y-4">
            @foreach ($this->installs as $install)
                <x-log-viewer
                    channel="game-install.{{ $install->id }}"
                    event="GameInstallOutput"
                    :track-progress="true"
                    max-height="max-h-64"
                    class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4"
                    wire:key="install-{{ $install->id }}"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg">{{ $install->name }}</flux:heading>
                                <flux:badge :variant="$install->installation_status->badgeVariant()" size="sm">
                                    {{ ucfirst($install->installation_status->value) }}
                                </flux:badge>
                            </div>
                            <flux:text class="mt-1">
                                {{ __('Branch') }}: <span class="font-mono">{{ $install->branch }}</span>
                                @if ($install->disk_size_bytes > 0)
                                    &middot; {{ number_format($install->disk_size_bytes / 1073741824, 1) }} GB
                                @endif
                                @if ($install->installed_at)
                                    &middot; {{ __('Last installed') }}: {{ $install->installed_at->diffForHumans() }}
                                @endif
                            </flux:text>
                            <flux:text class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                {{ $install->getInstallationPath() }}
                            </flux:text>

                            @if ($install->installation_status === InstallationStatus::Installing || $install->installation_status === InstallationStatus::Queued)
                                <div class="mt-2 w-64">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $install->installation_status === InstallationStatus::Queued ? __('Queued...') : __('Downloading...') }}
                                        </span>
                                        <span class="text-xs font-medium" x-text="progress + '%'"></span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                                        <div class="h-1.5 rounded-full bg-amber-500 transition-all duration-500" x-bind:style="'width: ' + progress + '%'"></div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($install->installation_status === InstallationStatus::Installing || $install->installation_status === InstallationStatus::Queued)
                                <flux:button size="sm" variant="ghost" wire:click="toggleLogs({{ $install->id }})" icon="command-line">
                                    {{ __('Logs') }}
                                </flux:button>
                            @endif
                            @if ($install->installation_status !== InstallationStatus::Installing)
                                <flux:button
                                    size="sm"
                                    wire:click="reinstall({{ $install->id }})"
                                    wire:confirm="{{ __('Re-install/update this game install? The queue will start a fresh SteamCMD run.') }}"
                                    icon="arrow-down-tray"
                                >
                                    {{ $install->installation_status === InstallationStatus::Installed ? __('Update') : __('Install') }}
                                </flux:button>
                            @endif
                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $install->id }})" icon="trash" :disabled="$install->installation_status === InstallationStatus::Installing || $install->installation_status === InstallationStatus::Queued">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </div>

                    <x-slot:log>
                        @if ($this->showLogs[$install->id] ?? ($install->installation_status === InstallationStatus::Installing || $install->installation_status === InstallationStatus::Queued))
                            <div class="mt-3 rounded bg-zinc-900 text-zinc-100 p-3 font-mono text-xs max-h-64 overflow-y-auto" x-ref="logContainer" wire:key="logs-{{ $install->id }}">
                                <template x-if="lines.length === 0">
                                    <div class="text-zinc-500">{{ __('Waiting for output...') }}</div>
                                </template>
                                <template x-for="(line, index) in lines" :key="index">
                                    <div class="whitespace-pre-wrap break-all" x-text="line"></div>
                                </template>
                            </div>
                        @endif
                    </x-slot:log>
                </x-log-viewer>
            @endforeach
        </div>
    @endif

    {{-- Create modal --}}
    <flux:modal wire:model="showCreateModal" class="max-w-md">
        <flux:heading>{{ __('New Game Install') }}</flux:heading>
        <flux:text class="mt-1 mb-4">{{ __('Downloads the Arma 3 dedicated server via SteamCMD. Only the public branch is officially supported.') }}</flux:text>

        <form wire:submit="create" class="space-y-4">
            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('Arma 3 Server')" required />

            <flux:field>
                <flux:label>{{ __('Branch') }}</flux:label>
                <flux:select wire:model="branch">
                    <flux:select.option value="public">public — stable</flux:select.option>
                    <flux:select.option value="contact">contact — Contact DLC</flux:select.option>
                    <flux:select.option value="creatordlc">creatordlc — Creator DLC</flux:select.option>
                    <flux:select.option value="profiling">profiling — Performance Profiling</flux:select.option>
                    <flux:select.option value="performance">performance — Performance (legacy)</flux:select.option>
                    <flux:select.option value="legacy">legacy</flux:select.option>
                </flux:select>
                <flux:error name="branch" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="arrow-down-tray">{{ __('Create & Install') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model="confirmingDelete">
        <flux:heading>{{ __('Delete Game Install') }}</flux:heading>
        <flux:text>{{ __('Are you sure you want to delete this game install? This will also permanently remove all server files from disk.') }}</flux:text>
        <div class="flex justify-end gap-2 mt-4">
            <flux:button wire:click="$set('confirmingDelete', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteInstall">{{ __('Delete') }}</flux:button>
        </div>
    </flux:modal>
</section>
