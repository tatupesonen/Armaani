<?php

use App\Enums\GameInstallStatus;
use App\Enums\ServerStatus;
use App\Jobs\StartServerJob;
use App\Jobs\StopServerJob;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Services\ServerProcessService;
use Illuminate\Support\Facades\Log;
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

    public string $createAdditionalParams = '';

    public bool $createVerifySignatures = true;

    public bool $createAllowedFilePatching = false;

    public bool $createBattleEye = true;

    public bool $createPersistent = false;

    public bool $createVonEnabled = true;

    public string $createAdditionalServerOptions = '';

    /** @var array<int, bool> */
    public array $showLogs = [];

    /** @var array<int, bool> */
    public array $showCommand = [];

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

    public string $editAdditionalParams = '';

    public bool $editVerifySignatures = true;

    public bool $editAllowedFilePatching = false;

    public bool $editBattleEye = true;

    public bool $editPersistent = false;

    public bool $editVonEnabled = true;

    public string $editAdditionalServerOptions = '';

    // Difficulty settings (edit only — created with defaults on server create)
    public bool $editReducedDamage = false;

    public int $editGroupIndicators = 2;

    public int $editFriendlyTags = 2;

    public int $editEnemyTags = 0;

    public int $editDetectedMines = 2;

    public int $editCommands = 2;

    public int $editWaypoints = 2;

    public int $editTacticalPing = 3;

    public int $editWeaponInfo = 2;

    public int $editStanceIndicator = 2;

    public bool $editStaminaBar = true;

    public bool $editWeaponCrosshair = true;

    public bool $editVisionAid = false;

    public int $editThirdPersonView = 1;

    public bool $editCameraShake = true;

    public bool $editScoreTable = true;

    public bool $editDeathMessages = true;

    public bool $editVonId = true;

    public bool $editMapContent = true;

    public bool $editAutoReport = false;

    public int $editAiLevelPreset = 1;

    public string $editSkillAi = '0.50';

    public string $editPrecisionAi = '0.50';

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

    public function toggleServerLogs(int $serverId): void
    {
        $status = $this->getStatus(Server::query()->findOrFail($serverId));
        $isShowing = $this->showLogs[$serverId] ?? ($status === 'running');
        $this->showLogs[$serverId] = ! $isShowing;
    }

    public function toggleCommand(int $serverId): void
    {
        $this->showCommand[$serverId] = ! ($this->showCommand[$serverId] ?? false);
    }

    public function getLaunchCommand(Server $server): string
    {
        return app(ServerProcessService::class)->buildLaunchCommand($server);
    }

    /** @return string[] */
    public function loadServerLog(int $serverId): array
    {
        $server = Server::query()->find($serverId);

        if (! $server) {
            return [];
        }

        $logPath = app(ServerProcessService::class)->getServerLogPath($server);

        if (! file_exists($logPath)) {
            return ['No log file found.'];
        }

        // Read last 100 lines as initial content before WebSocket takes over
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $lines ? array_slice($lines, -100) : [];
    }

    public function getStatus(Server $server): string
    {
        return app(ServerProcessService::class)->getStatus($server)->value;
    }

    // --- Process control ---

    public function startServer(Server $server): void
    {
        $server->update(['status' => ServerStatus::Starting]);
        StartServerJob::dispatch($server);
        Log::info('User '.auth()->id().' ('.auth()->user()->name.") queued start for server '{$server->name}'");
    }

    public function stopServer(Server $server): void
    {
        $server->update(['status' => ServerStatus::Stopping]);
        StopServerJob::dispatch($server);
        Log::info('User '.auth()->id().' ('.auth()->user()->name.") queued stop for server '{$server->name}'");
    }

    public function restartServer(Server $server): void
    {
        $server->update(['status' => ServerStatus::Stopping]);
        StartServerJob::dispatch($server, restart: true);
        Log::info('User '.auth()->id().' ('.auth()->user()->name.") queued restart for server '{$server->name}'");
    }

    // --- Headless clients ---

    public function addHeadlessClient(Server $server): void
    {
        $service = app(ServerProcessService::class);

        if (! $service->isRunning($server)) {
            return;
        }

        $index = $service->addHeadlessClient($server);

        if ($index !== null) {
            Log::info('User '.auth()->id().' ('.auth()->user()->name.") added HC #{$index} to server '{$server->name}'");
        }
    }

    public function removeHeadlessClient(Server $server): void
    {
        $service = app(ServerProcessService::class);

        if (! $service->isRunning($server)) {
            return;
        }

        $index = $service->removeHeadlessClient($server);

        if ($index !== null) {
            Log::info('User '.auth()->id().' ('.auth()->user()->name.") removed HC #{$index} from server '{$server->name}'");
        }
    }

    public function getHeadlessClientCount(Server $server): int
    {
        return app(ServerProcessService::class)->getRunningHeadlessClientCount($server);
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
            $server = Server::query()->find($this->deletingServerId);

            if ($server) {
                $server->delete();
                Log::info('User '.auth()->id().' ('.auth()->user()->name.") deleted server '{$server->name}'");
            }
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
        $this->createAdditionalParams = '';
        $this->createVerifySignatures = true;
        $this->createAllowedFilePatching = false;
        $this->createBattleEye = true;
        $this->createPersistent = false;
        $this->createVonEnabled = true;
        $this->createAdditionalServerOptions = '';
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
            'createAdditionalParams' => ['nullable', 'string'],
            'createVerifySignatures' => ['boolean'],
            'createAllowedFilePatching' => ['boolean'],
            'createBattleEye' => ['boolean'],
            'createPersistent' => ['boolean'],
            'createVonEnabled' => ['boolean'],
            'createAdditionalServerOptions' => ['nullable', 'string'],
        ], messages: [
            'createPort.unique' => 'This port is already allocated to another server.',
            'createQueryPort.unique' => 'This query port is already allocated to another server.',
        ]);

        $server = Server::query()->create([
            'name' => $validated['createName'],
            'port' => $validated['createPort'],
            'query_port' => $validated['createQueryPort'],
            'max_players' => $validated['createMaxPlayers'],
            'password' => $validated['createPassword'] ?: null,
            'admin_password' => $validated['createAdminPassword'] ?: null,
            'description' => $validated['createDescription'] ?: null,
            'active_preset_id' => $validated['createActivePresetId'],
            'game_install_id' => $validated['createGameInstallId'],
            'additional_params' => $validated['createAdditionalParams'] ?: null,
            'verify_signatures' => $validated['createVerifySignatures'],
            'allowed_file_patching' => $validated['createAllowedFilePatching'],
            'battle_eye' => $validated['createBattleEye'],
            'persistent' => $validated['createPersistent'],
            'von_enabled' => $validated['createVonEnabled'],
            'additional_server_options' => $validated['createAdditionalServerOptions'] ?: null,
        ]);

        $server->difficultySettings()->create([]);

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") created server '{$validated['createName']}' (port: {$validated['createPort']})");

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
        $this->editAdditionalParams = $server->additional_params ?? '';
        $this->editVerifySignatures = $server->verify_signatures;
        $this->editAllowedFilePatching = $server->allowed_file_patching;
        $this->editBattleEye = $server->battle_eye;
        $this->editPersistent = $server->persistent;
        $this->editVonEnabled = $server->von_enabled;
        $this->editAdditionalServerOptions = $server->additional_server_options ?? '';

        $difficulty = $server->difficultySettings ?? $server->difficultySettings()->create([])->refresh();
        $this->editReducedDamage = $difficulty->reduced_damage;
        $this->editGroupIndicators = $difficulty->group_indicators;
        $this->editFriendlyTags = $difficulty->friendly_tags;
        $this->editEnemyTags = $difficulty->enemy_tags;
        $this->editDetectedMines = $difficulty->detected_mines;
        $this->editCommands = $difficulty->commands;
        $this->editWaypoints = $difficulty->waypoints;
        $this->editTacticalPing = $difficulty->tactical_ping;
        $this->editWeaponInfo = $difficulty->weapon_info;
        $this->editStanceIndicator = $difficulty->stance_indicator;
        $this->editStaminaBar = $difficulty->stamina_bar;
        $this->editWeaponCrosshair = $difficulty->weapon_crosshair;
        $this->editVisionAid = $difficulty->vision_aid;
        $this->editThirdPersonView = $difficulty->third_person_view;
        $this->editCameraShake = $difficulty->camera_shake;
        $this->editScoreTable = $difficulty->score_table;
        $this->editDeathMessages = $difficulty->death_messages;
        $this->editVonId = $difficulty->von_id;
        $this->editMapContent = $difficulty->map_content;
        $this->editAutoReport = $difficulty->auto_report;
        $this->editAiLevelPreset = $difficulty->ai_level_preset;
        $this->editSkillAi = (string) $difficulty->skill_ai;
        $this->editPrecisionAi = (string) $difficulty->precision_ai;

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
            'editAdditionalParams' => ['nullable', 'string'],
            'editVerifySignatures' => ['boolean'],
            'editAllowedFilePatching' => ['boolean'],
            'editBattleEye' => ['boolean'],
            'editPersistent' => ['boolean'],
            'editVonEnabled' => ['boolean'],
            'editAdditionalServerOptions' => ['nullable', 'string'],
            'editReducedDamage' => ['boolean'],
            'editGroupIndicators' => ['integer', 'min:0', 'max:2'],
            'editFriendlyTags' => ['integer', 'min:0', 'max:2'],
            'editEnemyTags' => ['integer', 'min:0', 'max:2'],
            'editDetectedMines' => ['integer', 'min:0', 'max:2'],
            'editCommands' => ['integer', 'min:0', 'max:2'],
            'editWaypoints' => ['integer', 'min:0', 'max:2'],
            'editTacticalPing' => ['integer', 'min:0', 'max:3'],
            'editWeaponInfo' => ['integer', 'min:0', 'max:2'],
            'editStanceIndicator' => ['integer', 'min:0', 'max:2'],
            'editStaminaBar' => ['boolean'],
            'editWeaponCrosshair' => ['boolean'],
            'editVisionAid' => ['boolean'],
            'editThirdPersonView' => ['integer', 'min:0', 'max:2'],
            'editCameraShake' => ['boolean'],
            'editScoreTable' => ['boolean'],
            'editDeathMessages' => ['boolean'],
            'editVonId' => ['boolean'],
            'editMapContent' => ['boolean'],
            'editAutoReport' => ['boolean'],
            'editAiLevelPreset' => ['integer', 'min:0', 'max:3'],
            'editSkillAi' => ['numeric', 'min:0', 'max:1'],
            'editPrecisionAi' => ['numeric', 'min:0', 'max:1'],
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
            'additional_params' => $validated['editAdditionalParams'] ?: null,
            'verify_signatures' => $validated['editVerifySignatures'],
            'allowed_file_patching' => $validated['editAllowedFilePatching'],
            'battle_eye' => $validated['editBattleEye'],
            'persistent' => $validated['editPersistent'],
            'von_enabled' => $validated['editVonEnabled'],
            'additional_server_options' => $validated['editAdditionalServerOptions'] ?: null,
        ]);

        $server->difficultySettings()->updateOrCreate(['server_id' => $server->id], [
            'reduced_damage' => $validated['editReducedDamage'],
            'group_indicators' => $validated['editGroupIndicators'],
            'friendly_tags' => $validated['editFriendlyTags'],
            'enemy_tags' => $validated['editEnemyTags'],
            'detected_mines' => $validated['editDetectedMines'],
            'commands' => $validated['editCommands'],
            'waypoints' => $validated['editWaypoints'],
            'tactical_ping' => $validated['editTacticalPing'],
            'weapon_info' => $validated['editWeaponInfo'],
            'stance_indicator' => $validated['editStanceIndicator'],
            'stamina_bar' => $validated['editStaminaBar'],
            'weapon_crosshair' => $validated['editWeaponCrosshair'],
            'vision_aid' => $validated['editVisionAid'],
            'third_person_view' => $validated['editThirdPersonView'],
            'camera_shake' => $validated['editCameraShake'],
            'score_table' => $validated['editScoreTable'],
            'death_messages' => $validated['editDeathMessages'],
            'von_id' => $validated['editVonId'],
            'map_content' => $validated['editMapContent'],
            'auto_report' => $validated['editAutoReport'],
            'ai_level_preset' => $validated['editAiLevelPreset'],
            'skill_ai' => $validated['editSkillAi'],
            'precision_ai' => $validated['editPrecisionAi'],
        ]);

        Log::info('User '.auth()->id().' ('.auth()->user()->name.") updated server '{$validated['editName']}'");

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
                                <flux:badge :variant="match($status) { 'running' => 'success', 'starting', 'stopping' => 'warning', default => 'secondary' }" size="sm">
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
                            @if (in_array($status, ['starting', 'stopping']))
                                <flux:button size="sm" disabled icon="arrow-path" class="animate-spin-slow">
                                    {{ ucfirst($status) }}...
                                </flux:button>
                            @elseif ($status === 'running')
                                <flux:button size="sm" variant="danger" wire:click="stopServer({{ $server->id }})" icon="stop">
                                    {{ __('Stop') }}
                                </flux:button>
                                <flux:button size="sm" wire:click="restartServer({{ $server->id }})" icon="arrow-path">
                                    {{ __('Restart') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="primary" wire:click="startServer({{ $server->id }})" icon="play">
                                    {{ __('Start') }}
                                </flux:button>
                            @endif

                            <flux:button size="sm" variant="ghost" wire:click="toggleServerLogs({{ $server->id }})" icon="command-line">
                                {{ __('Logs') }}
                            </flux:button>

                            <flux:button size="sm" variant="ghost" wire:click="toggleCommand({{ $server->id }})" icon="code-bracket">
                                {{ __('Command') }}
                            </flux:button>

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

                    {{-- Headless client controls --}}
                    @if ($status === 'running')
                        @php $hcCount = $this->getHeadlessClientCount($server); @endphp
                        <div class="flex items-center gap-2 px-4 pb-3">
                            <flux:text class="text-sm font-medium">{{ __('Headless Clients') }}</flux:text>
                            <flux:button size="xs" variant="ghost" wire:click="removeHeadlessClient({{ $server->id }})" icon="minus" :disabled="$hcCount < 1" />
                            <flux:badge :variant="$hcCount > 0 ? 'primary' : 'secondary'" size="sm">{{ $hcCount }}</flux:badge>
                            <flux:button size="xs" variant="ghost" wire:click="addHeadlessClient({{ $server->id }})" icon="plus" :disabled="$hcCount >= 10" />
                        </div>
                    @endif

                    {{-- Server log panel --}}
                    @if ($this->showLogs[$server->id] ?? ($status === 'running'))
                        <div class="border-t border-zinc-200 dark:border-zinc-700 p-4"
                            x-data="{
                                lines: [],
                                channel: null,
                                maxLines: 200,
                                init() {
                                    $wire.loadServerLog({{ $server->id }}).then(initialLines => {
                                        this.lines = initialLines;
                                        this.$nextTick(() => this.scrollToBottom());
                                    });
                                    this.channel = window.Echo.channel('server-log.{{ $server->id }}');
                                    this.channel.listen('ServerLogOutput', (event) => {
                                        this.lines.push(event.line);
                                        if (this.lines.length > this.maxLines) {
                                            this.lines = this.lines.slice(-this.maxLines);
                                        }
                                        this.$nextTick(() => this.scrollToBottom());
                                    });
                                },
                                scrollToBottom() {
                                    if (this.$refs.logContainer) {
                                        this.$refs.logContainer.scrollTop = this.$refs.logContainer.scrollHeight;
                                    }
                                },
                                destroy() {
                                    if (this.channel) {
                                        window.Echo.leave('server-log.{{ $server->id }}');
                                        this.channel = null;
                                    }
                                }
                            }"
                            wire:key="server-logs-{{ $server->id }}"
                        >
                            <flux:text class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Server Log') }}</flux:text>
                            <div class="rounded bg-zinc-900 text-zinc-100 p-3 font-mono text-xs max-h-[32rem] overflow-y-auto" x-ref="logContainer">
                                <template x-if="lines.length === 0">
                                    <div class="text-zinc-500">{{ __('Waiting for output...') }}</div>
                                </template>
                                <template x-for="(line, index) in lines" :key="index">
                                    <div class="whitespace-pre-wrap break-all" x-text="line"></div>
                                </template>
                            </div>
                        </div>
                    @endif

                    {{-- Launch command panel --}}
                    @if ($this->showCommand[$server->id] ?? false)
                        <div class="border-t border-zinc-200 dark:border-zinc-700 p-4" wire:key="server-command-{{ $server->id }}">
                            <flux:text class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Launch Command') }}</flux:text>
                            <div class="rounded bg-zinc-900 text-zinc-100 p-3 font-mono text-xs overflow-x-auto select-all whitespace-pre-wrap break-all">{{ $this->getLaunchCommand($server) }}</div>
                        </div>
                    @endif

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

                                <flux:separator />

                                <flux:heading size="lg">{{ __('Server Rules') }}</flux:heading>

                                <div class="space-y-3">
                                    <flux:switch wire:model="editVerifySignatures" label="{{ __('Verify Signatures') }}" description="{{ __('Kick players with unsigned or modified addon files (verifySignatures=2). Disable for lenient modded servers.') }}" />
                                    <flux:separator variant="subtle" />
                                    <flux:switch wire:model="editAllowedFilePatching" label="{{ __('Allow File Patching') }}" description="{{ __('Allow clients to use file patching (allowedFilePatching=2). Required by some mods like ACE.') }}" />
                                    <flux:separator variant="subtle" />
                                    <flux:switch wire:model="editBattleEye" label="{{ __('BattlEye Anti-Cheat') }}" description="{{ __('Enable BattlEye anti-cheat protection. May conflict with some mod setups.') }}" />
                                    <flux:separator variant="subtle" />
                                    <flux:switch wire:model="editVonEnabled" label="{{ __('Voice Over Network') }}" description="{{ __('Enable in-game voice communication.') }}" />
                                    <flux:separator variant="subtle" />
                                    <flux:switch wire:model="editPersistent" label="{{ __('Persistent Server') }}" description="{{ __('Keep the server running even when no players are connected.') }}" />
                                </div>

                                <flux:separator />

                                <flux:heading size="lg">{{ __('Difficulty Settings') }}</flux:heading>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    {{-- Column 1: Boolean toggles --}}
                                    <div class="space-y-3">
                                        <flux:switch wire:model="editReducedDamage" label="{{ __('Reduced damage') }}" />
                                        <flux:switch wire:model="editStaminaBar" label="{{ __('Stamina bar') }}" />
                                        <flux:switch wire:model="editWeaponCrosshair" label="{{ __('Weapon crosshair') }}" />
                                        <flux:switch wire:model="editVisionAid" label="{{ __('Vision aid') }}" />
                                        <flux:switch wire:model="editCameraShake" label="{{ __('Camera shake') }}" />
                                        <flux:switch wire:model="editScoreTable" label="{{ __('Score table') }}" />
                                        <flux:switch wire:model="editDeathMessages" label="{{ __('Killed by') }}" />
                                        <flux:switch wire:model="editVonId" label="{{ __('VON ID') }}" />
                                        <flux:switch wire:model="editMapContent" label="{{ __('Extended map content') }}" />
                                        <flux:switch wire:model="editAutoReport" label="{{ __('Auto report') }}" />
                                    </div>

                                    {{-- Column 2: Situational awareness + AI --}}
                                    <div class="space-y-4">
                                        <flux:radio.group wire:model="editGroupIndicators" label="{{ __('Group indicators') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Never') }}" />
                                            <flux:radio value="1" label="{{ __('Limited') }}" />
                                            <flux:radio value="2" label="{{ __('Always') }}" />
                                        </flux:radio.group>

                                        <flux:radio.group wire:model="editFriendlyTags" label="{{ __('Friendly tags') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Never') }}" />
                                            <flux:radio value="1" label="{{ __('Limited') }}" />
                                            <flux:radio value="2" label="{{ __('Always') }}" />
                                        </flux:radio.group>

                                        <flux:radio.group wire:model="editEnemyTags" label="{{ __('Enemy tags') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Never') }}" />
                                            <flux:radio value="1" label="{{ __('Limited') }}" />
                                            <flux:radio value="2" label="{{ __('Always') }}" />
                                        </flux:radio.group>

                                        <flux:radio.group wire:model="editDetectedMines" label="{{ __('Detected mines') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Never') }}" />
                                            <flux:radio value="1" label="{{ __('Limited') }}" />
                                            <flux:radio value="2" label="{{ __('Always') }}" />
                                        </flux:radio.group>

                                        <flux:separator variant="subtle" />

                                        <flux:radio.group wire:model="editAiLevelPreset" label="{{ __('AI level preset') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Low') }}" />
                                            <flux:radio value="1" label="{{ __('Normal') }}" />
                                            <flux:radio value="2" label="{{ __('High') }}" />
                                            <flux:radio value="3" label="{{ __('Custom') }}" />
                                        </flux:radio.group>

                                        <div class="grid grid-cols-2 gap-3">
                                            <flux:input wire:model="editSkillAi" :label="__('AI Skill')" type="number" min="0" max="1" step="0.05" />
                                            <flux:input wire:model="editPrecisionAi" :label="__('AI Precision')" type="number" min="0" max="1" step="0.05" />
                                        </div>
                                    </div>

                                    {{-- Column 3: HUD & view settings --}}
                                    <div class="space-y-4">
                                        <flux:radio.group wire:model="editCommands" label="{{ __('Commands') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Never') }}" />
                                            <flux:radio value="1" label="{{ __('Fade') }}" />
                                            <flux:radio value="2" label="{{ __('Always') }}" />
                                        </flux:radio.group>

                                        <flux:radio.group wire:model="editWaypoints" label="{{ __('Waypoints') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Never') }}" />
                                            <flux:radio value="1" label="{{ __('Fade') }}" />
                                            <flux:radio value="2" label="{{ __('Always') }}" />
                                        </flux:radio.group>

                                        <flux:radio.group wire:model="editWeaponInfo" label="{{ __('Weapon info') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Never') }}" />
                                            <flux:radio value="1" label="{{ __('Fade') }}" />
                                            <flux:radio value="2" label="{{ __('Always') }}" />
                                        </flux:radio.group>

                                        <flux:radio.group wire:model="editStanceIndicator" label="{{ __('Stance indicator') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Never') }}" />
                                            <flux:radio value="1" label="{{ __('Fade') }}" />
                                            <flux:radio value="2" label="{{ __('Always') }}" />
                                        </flux:radio.group>

                                        <flux:radio.group wire:model="editThirdPersonView" label="{{ __('Third person view') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Disabled') }}" />
                                            <flux:radio value="1" label="{{ __('Enabled') }}" />
                                            <flux:radio value="2" label="{{ __('Vehicles') }}" />
                                        </flux:radio.group>

                                        <flux:radio.group wire:model="editTacticalPing" label="{{ __('Tactical ping') }}" variant="segmented" size="sm">
                                            <flux:radio value="0" label="{{ __('Off') }}" />
                                            <flux:radio value="1" label="{{ __('3D') }}" />
                                            <flux:radio value="2" label="{{ __('Map') }}" />
                                            <flux:radio value="3" label="{{ __('Both') }}" />
                                        </flux:radio.group>
                                    </div>
                                </div>

                                <flux:separator />

                                <flux:heading size="lg">{{ __('Advanced') }}</flux:heading>

                                <flux:textarea wire:model="editAdditionalParams" :label="__('Additional Launch Parameters')" rows="2" :placeholder="__('-loadMissionToMemory -enableHT')" />

                                <flux:textarea wire:model="editAdditionalServerOptions" :label="__('Additional server.cfg Options')" rows="3" :placeholder="__('Raw config directives appended to server.cfg')" />

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

            <flux:separator />

            <flux:heading size="lg">{{ __('Server Rules') }}</flux:heading>

            <div class="space-y-3">
                <flux:switch wire:model="createVerifySignatures" label="{{ __('Verify Signatures') }}" description="{{ __('Kick players with unsigned or modified addon files (verifySignatures=2). Disable for lenient modded servers.') }}" />
                <flux:separator variant="subtle" />
                <flux:switch wire:model="createAllowedFilePatching" label="{{ __('Allow File Patching') }}" description="{{ __('Allow clients to use file patching (allowedFilePatching=2). Required by some mods like ACE.') }}" />
                <flux:separator variant="subtle" />
                <flux:switch wire:model="createBattleEye" label="{{ __('BattlEye Anti-Cheat') }}" description="{{ __('Enable BattlEye anti-cheat protection. May conflict with some mod setups.') }}" />
                <flux:separator variant="subtle" />
                <flux:switch wire:model="createVonEnabled" label="{{ __('Voice Over Network') }}" description="{{ __('Enable in-game voice communication.') }}" />
                <flux:separator variant="subtle" />
                <flux:switch wire:model="createPersistent" label="{{ __('Persistent Server') }}" description="{{ __('Keep the server running even when no players are connected.') }}" />
            </div>

            <flux:separator />

            <flux:heading size="lg">{{ __('Advanced') }}</flux:heading>

            <flux:textarea wire:model="createAdditionalParams" :label="__('Additional Launch Parameters')" rows="2" :placeholder="__('-loadMissionToMemory -enableHT')" />

            <flux:textarea wire:model="createAdditionalServerOptions" :label="__('Additional server.cfg Options')" rows="3" :placeholder="__('Raw config directives appended to server.cfg')" />

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
