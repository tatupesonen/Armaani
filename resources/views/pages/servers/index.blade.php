<?php

use App\Enums\GameInstallStatus;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Services\ServerProcessService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Servers')] class extends Component
{
    // List state
    public bool $confirmingDelete = false;

    public ?int $deletingServerId = null;

    // Create modal state
    public bool $showCreateModal = false;

    public string $createName = '';

    public int $createPort = 2302;

    public int $createQueryPort = 2303;

    public int $createMaxPlayers = 32;

    public string $createPassword = '';

    public string $createAdminPassword = '';

    public string $createDescription = '';

    public ?int $createActivePresetId = null;

    public ?int $createGameInstallId = null;

    public int $createHeadlessClientCount = 0;

    public string $createAdditionalParams = '';

    // Inline edit state
    public ?int $editingServerId = null;

    public string $editName = '';

    public int $editPort = 2302;

    public int $editQueryPort = 2303;

    public int $editMaxPlayers = 32;

    public string $editPassword = '';

    public string $editAdminPassword = '';

    public string $editDescription = '';

    public ?int $editActivePresetId = null;

    public ?int $editGameInstallId = null;

    public int $editHeadlessClientCount = 0;

    public string $editAdditionalParams = '';

    #[Computed]
    public function servers()
    {
        return Server::query()->with(['activePreset', 'gameInstall'])->get();
    }

    #[Computed]
    public function presets()
    {
        return ModPreset::query()->orderBy('name')->get();
    }

    #[Computed]
    public function gameInstalls()
    {
        return GameInstall::query()
            ->whereIn('installation_status', [GameInstallStatus::Installed->value, GameInstallStatus::Installing->value])
            ->orderBy('name')
            ->get();
    }

    public function getStatus(Server $server): string
    {
        return app(ServerProcessService::class)->getStatus($server)->value;
    }

    // --- Process control ---

    public function startServer(Server $server): void
    {
        app(ServerProcessService::class)->start($server);
    }

    public function stopServer(Server $server): void
    {
        app(ServerProcessService::class)->stop($server);
    }

    public function restartServer(Server $server): void
    {
        app(ServerProcessService::class)->restart($server);
    }

    // --- Delete ---

    public function confirmDelete(int $serverId): void
    {
        $this->confirmingDelete = true;
        $this->deletingServerId = $serverId;
    }

    public function deleteServer(): void
    {
        if ($this->deletingServerId) {
            Server::query()->find($this->deletingServerId)?->delete();
        }

        $this->confirmingDelete = false;
        $this->deletingServerId = null;

        unset($this->servers);
    }

    // --- Create ---

    public function openCreateModal(): void
    {
        $this->createName = '';
        $this->createPort = 2302;
        $this->createQueryPort = 2303;
        $this->createMaxPlayers = 32;
        $this->createPassword = '';
        $this->createAdminPassword = '';
        $this->createDescription = '';
        $this->createActivePresetId = null;
        $this->createGameInstallId = $this->gameInstalls->first()?->id;
        $this->createHeadlessClientCount = 0;
        $this->createAdditionalParams = '';
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function updatedCreatePort(int $value): void
    {
        $this->createQueryPort = $value + 1;
    }

    public function createServer(): void
    {
        $validated = $this->validate([
            'createName' => ['required', 'string', 'max:255'],
            'createPort' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('servers', 'port'), Rule::unique('servers', 'query_port')],
            'createQueryPort' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('servers', 'port'), Rule::unique('servers', 'query_port')],
            'createMaxPlayers' => ['required', 'integer', 'min:1', 'max:256'],
            'createPassword' => ['nullable', 'string', 'max:255'],
            'createAdminPassword' => ['nullable', 'string', 'max:255'],
            'createDescription' => ['nullable', 'string'],
            'createActivePresetId' => ['nullable', 'exists:mod_presets,id'],
            'createGameInstallId' => ['required', 'exists:game_installs,id'],
            'createHeadlessClientCount' => ['required', 'integer', 'min:0', 'max:10'],
            'createAdditionalParams' => ['nullable', 'string'],
        ], messages: [
            'createPort.unique' => 'This port is already allocated to another server.',
            'createQueryPort.unique' => 'This query port is already allocated to another server.',
        ]);

        Server::query()->create([
            'name' => $validated['createName'],
            'port' => $validated['createPort'],
            'query_port' => $validated['createQueryPort'],
            'max_players' => $validated['createMaxPlayers'],
            'password' => $validated['createPassword'] ?: null,
            'admin_password' => $validated['createAdminPassword'] ?: null,
            'description' => $validated['createDescription'] ?: null,
            'active_preset_id' => $validated['createActivePresetId'],
            'game_install_id' => $validated['createGameInstallId'],
            'headless_client_count' => $validated['createHeadlessClientCount'],
            'additional_params' => $validated['createAdditionalParams'] ?: null,
        ]);

        $this->showCreateModal = false;
        unset($this->servers);

        session()->flash('status', "Server '{$validated['createName']}' created successfully.");
    }

    // --- Inline edit ---

    public function startEditing(Server $server): void
    {
        $this->editingServerId = $server->id;
        $this->editName = $server->name;
        $this->editPort = $server->port;
        $this->editQueryPort = $server->query_port;
        $this->editMaxPlayers = $server->max_players;
        $this->editPassword = $server->password ?? '';
        $this->editAdminPassword = $server->admin_password ?? '';
        $this->editDescription = $server->description ?? '';
        $this->editActivePresetId = $server->active_preset_id;
        $this->editGameInstallId = $server->game_install_id;
        $this->editHeadlessClientCount = $server->headless_client_count;
        $this->editAdditionalParams = $server->additional_params ?? '';
        $this->resetErrorBag();
    }

    public function cancelEditing(): void
    {
        $this->editingServerId = null;
        $this->resetErrorBag();
    }

    public function updatedEditPort(int $value): void
    {
        $this->editQueryPort = $value + 1;
    }

    public function saveServer(): void
    {
        $server = Server::query()->findOrFail($this->editingServerId);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editPort' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('servers', 'port')->ignore($server->id), Rule::unique('servers', 'query_port')->ignore($server->id)],
            'editQueryPort' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('servers', 'port')->ignore($server->id), Rule::unique('servers', 'query_port')->ignore($server->id)],
            'editMaxPlayers' => ['required', 'integer', 'min:1', 'max:256'],
            'editPassword' => ['nullable', 'string', 'max:255'],
            'editAdminPassword' => ['nullable', 'string', 'max:255'],
            'editDescription' => ['nullable', 'string'],
            'editActivePresetId' => ['nullable', 'exists:mod_presets,id'],
            'editGameInstallId' => ['required', 'exists:game_installs,id'],
            'editHeadlessClientCount' => ['required', 'integer', 'min:0', 'max:10'],
            'editAdditionalParams' => ['nullable', 'string'],
        ], messages: [
            'editPort.unique' => 'This port is already allocated to another server.',
            'editQueryPort.unique' => 'This query port is already allocated to another server.',
        ]);

        $server->update([
            'name' => $validated['editName'],
            'port' => $validated['editPort'],
            'query_port' => $validated['editQueryPort'],
            'max_players' => $validated['editMaxPlayers'],
            'password' => $validated['editPassword'] ?: null,
            'admin_password' => $validated['editAdminPassword'] ?: null,
            'description' => $validated['editDescription'] ?: null,
            'active_preset_id' => $validated['editActivePresetId'],
            'game_install_id' => $validated['editGameInstallId'],
            'headless_client_count' => $validated['editHeadlessClientCount'],
            'additional_params' => $validated['editAdditionalParams'] ?: null,
        ]);

        $this->editingServerId = null;
        unset($this->servers);

        session()->flash('status', "Server '{$validated['editName']}' updated successfully.");
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Servers') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Manage your Arma 3 server instances.') }}</flux:text>
        </div>
        <flux:button variant="primary" wire:click="openCreateModal" icon="plus">
            {{ __('New Server') }}
        </flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" class="mb-4">
            {{ session('status') }}
        </flux:callout>
    @endif

    @if ($this->servers->isEmpty())
        <flux:callout>
            {{ __('No servers configured yet. Create your first server to get started.') }}
        </flux:callout>
    @else
        <div class="space-y-4" wire:poll.5s>
            @foreach ($this->servers as $server)
                @php $status = $this->getStatus($server); @endphp
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700" wire:key="server-{{ $server->id }}">
                    {{-- Server header row --}}
                    <div class="flex items-center justify-between p-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg">{{ $server->name }}</flux:heading>
                                <flux:badge :variant="$status === 'running' ? 'success' : 'secondary'" size="sm">
                                    {{ ucfirst($status) }}
                                </flux:badge>
                            </div>
                            <flux:text class="mt-1">
                                {{ __('Port') }}: {{ $server->port }} &middot;
                                {{ __('Players') }}: {{ $server->max_players }}
                                @if ($server->gameInstall)
                                    &middot; {{ __('Install') }}: {{ $server->gameInstall->name }}
                                    <span class="font-mono text-xs">({{ $server->gameInstall->branch }})</span>
                                @endif
                                @if ($server->activePreset)
                                    &middot; {{ __('Preset') }}: {{ $server->activePreset->name }}
                                @endif
                            </flux:text>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($status === 'running')
                                <flux:button size="sm" variant="danger" wire:click="stopServer({{ $server->id }})" wire:confirm="Are you sure you want to stop this server?" icon="stop">
                                    {{ __('Stop') }}
                                </flux:button>
                                <flux:button size="sm" wire:click="restartServer({{ $server->id }})" wire:confirm="Are you sure you want to restart this server?" icon="arrow-path">
                                    {{ __('Restart') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="primary" wire:click="startServer({{ $server->id }})" icon="play">
                                    {{ __('Start') }}
                                </flux:button>
                            @endif

                            @if ($editingServerId === $server->id)
                                <flux:button size="sm" wire:click="cancelEditing" icon="x-mark">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" wire:click="startEditing({{ $server->id }})" icon="pencil">
                                    {{ __('Configure') }}
                                </flux:button>
                            @endif

                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $server->id }})" icon="trash">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </div>

                    {{-- Inline configuration panel --}}
                    @if ($editingServerId === $server->id)
                        <div class="border-t border-zinc-200 dark:border-zinc-700 p-4 bg-zinc-50 dark:bg-zinc-800/50">
                            <form wire:submit="saveServer" class="space-y-4">
                                <flux:input wire:model="editName" :label="__('Server Name')" required />

                                <div class="grid grid-cols-2 gap-4">
                                    <flux:field>
                                        <flux:label>{{ __('Game Port') }}</flux:label>
                                        <flux:input wire:model.live="editPort" type="number" required />
                                        <flux:error name="editPort" />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>{{ __('Query Port') }}</flux:label>
                                        <flux:input wire:model="editQueryPort" type="number" required />
                                        <flux:error name="editQueryPort" />
                                    </flux:field>
                                </div>

                                <flux:input wire:model="editMaxPlayers" :label="__('Max Players')" type="number" required />

                                <div class="grid grid-cols-2 gap-4">
                                    <flux:input wire:model="editPassword" :label="__('Server Password')" type="text" :placeholder="__('Leave empty for no password')" />
                                    <flux:input wire:model="editAdminPassword" :label="__('Admin Password')" type="text" />
                                </div>

                                <flux:textarea wire:model="editDescription" :label="__('Description')" rows="2" />

                                <flux:field>
                                    <flux:label>{{ __('Game Install') }}</flux:label>
                                    <flux:select wire:model="editGameInstallId">
                                        @foreach ($this->gameInstalls as $install)
                                            <flux:select.option :value="$install->id">
                                                {{ $install->name }} ({{ $install->branch }})
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="editGameInstallId" />
                                    @if ($this->gameInstalls->isEmpty())
                                        <flux:description>{{ __('No game installs available. Add one on the Game Installs page.') }}</flux:description>
                                    @endif
                                </flux:field>

                                <flux:field>
                                    <flux:label>{{ __('Active Mod Preset') }}</flux:label>
                                    <flux:select wire:model="editActivePresetId">
                                        <flux:select.option :value="null">{{ __('None') }}</flux:select.option>
                                        @foreach ($this->presets as $preset)
                                            <flux:select.option :value="$preset->id">{{ $preset->name }} ({{ $preset->mods()->count() }} mods)</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="editActivePresetId" />
                                </flux:field>

                                <flux:input wire:model="editHeadlessClientCount" :label="__('Headless Clients')" type="number" min="0" max="10" />

                                <flux:textarea wire:model="editAdditionalParams" :label="__('Additional Launch Parameters')" rows="2" :placeholder="__('-loadMissionToMemory -enableHT')" />

                                <div class="flex items-center gap-2">
                                    <flux:button variant="primary" type="submit" icon="check">{{ __('Save') }}</flux:button>
                                    <flux:button wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Create modal --}}
    <flux:modal wire:model="showCreateModal" class="max-w-2xl">
        <flux:heading>{{ __('New Server') }}</flux:heading>
        <flux:text class="mt-1 mb-4">{{ __('Configure a new Arma 3 server instance.') }}</flux:text>

        <form wire:submit="createServer" class="space-y-4">
            <flux:input wire:model="createName" :label="__('Server Name')" required />

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Game Port') }}</flux:label>
                    <flux:input wire:model.live="createPort" type="number" required />
                    <flux:error name="createPort" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Query Port') }}</flux:label>
                    <flux:input wire:model="createQueryPort" type="number" required />
                    <flux:error name="createQueryPort" />
                </flux:field>
            </div>

            <flux:input wire:model="createMaxPlayers" :label="__('Max Players')" type="number" required />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="createPassword" :label="__('Server Password')" type="text" :placeholder="__('Leave empty for no password')" />
                <flux:input wire:model="createAdminPassword" :label="__('Admin Password')" type="text" />
            </div>

            <flux:textarea wire:model="createDescription" :label="__('Description')" rows="2" />

            <flux:field>
                <flux:label>{{ __('Game Install') }}</flux:label>
                <flux:select wire:model="createGameInstallId">
                    @foreach ($this->gameInstalls as $install)
                        <flux:select.option :value="$install->id">
                            {{ $install->name }} ({{ $install->branch }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="createGameInstallId" />
                @if ($this->gameInstalls->isEmpty())
                    <flux:description>{{ __('No game installs available. Add one on the Game Installs page first.') }}</flux:description>
                @endif
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Active Mod Preset') }}</flux:label>
                <flux:select wire:model="createActivePresetId">
                    <flux:select.option :value="null">{{ __('None') }}</flux:select.option>
                    @foreach ($this->presets as $preset)
                        <flux:select.option :value="$preset->id">{{ $preset->name }} ({{ $preset->mods()->count() }} mods)</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="createActivePresetId" />
            </flux:field>

            <flux:input wire:model="createHeadlessClientCount" :label="__('Headless Clients')" type="number" min="0" max="10" />

            <flux:textarea wire:model="createAdditionalParams" :label="__('Additional Launch Parameters')" rows="2" :placeholder="__('-loadMissionToMemory -enableHT')" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Create Server') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model="confirmingDelete">
        <flux:heading>{{ __('Delete Server') }}</flux:heading>
        <flux:text>{{ __('Are you sure you want to delete this server? This action cannot be undone.') }}</flux:text>
        <div class="flex justify-end gap-2 mt-4">
            <flux:button wire:click="$set('confirmingDelete', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteServer">{{ __('Delete') }}</flux:button>
        </div>
    </flux:modal>
</section>
