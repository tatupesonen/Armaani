<?php

use App\Enums\InstallationStatus;
use App\Enums\ServerStatus;
use App\Jobs\StartServerJob;
use App\Jobs\StopServerJob;
use App\Livewire\Concerns\AuditsActions;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\ServerBackup;
use App\Services\ServerBackupService;
use App\Services\ServerProcessService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Servers')] class extends Component
{
    use AuditsActions;
    use WithFileUploads;

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

    // Backup state
    public bool $confirmingRestore = false;

    public ?int $restoringBackupId = null;

    public string $backupName = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    #[Validate(['backupUploadFile' => ['required', 'file', 'max:10240']])]
    public $backupUploadFile = null;

    public string $backupUploadName = '';

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
            ->whereIn('installation_status', [InstallationStatus::Installed->value, InstallationStatus::Installing->value])
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

    #[On('echo:servers,ServerStatusChanged')]
    public function onServerStatusChanged(): void
    {
        // Re-render triggered automatically by Livewire.
    }

    // --- Process control ---

    public function startServer(Server $server): void
    {
        $server->update(['status' => ServerStatus::Starting]);
        StartServerJob::dispatch($server);
        $this->auditLog("queued start for server '{$server->name}'");
    }

    public function stopServer(Server $server): void
    {
        $server->update(['status' => ServerStatus::Stopping]);
        StopServerJob::dispatch($server);
        $this->auditLog("queued stop for server '{$server->name}'");
    }

    public function restartServer(Server $server): void
    {
        $server->update(['status' => ServerStatus::Stopping]);
        StartServerJob::dispatch($server, restart: true);
        $this->auditLog("queued restart for server '{$server->name}'");
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
            $this->auditLog("added HC #{$index} to server '{$server->name}'");
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
            $this->auditLog("removed HC #{$index} from server '{$server->name}'");
        }
    }

    public function getHeadlessClientCount(Server $server): int
    {
        return app(ServerProcessService::class)->getRunningHeadlessClientCount($server);
    }

    // --- Delete ---

    public function confirmDelete(int $serverId): void
    {
        $server = Server::query()->findOrFail($serverId);

        if ($this->getStatus($server) !== 'stopped') {
            return;
        }

        $this->confirmingDelete = true;
        $this->deletingServerId = $serverId;
    }

    public function deleteServer(): void
    {
        if ($this->deletingServerId) {
            $server = Server::query()->find($this->deletingServerId);

            if ($server && $this->getStatus($server) === 'stopped') {
                $server->delete();
                $this->auditLog("deleted server '{$server->name}'");
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

        $this->auditLog("created server '{$validated['createName']}' (port: {$validated['createPort']})");

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

        $this->auditLog("updated server '{$validated['editName']}'");

        $this->editingServerId = null;
        unset($this->servers);

        session()->flash('status', "Server '{$validated['editName']}' updated successfully.");
    }

    // --- Backups ---

    public function getBackups(Server $server)
    {
        return $server->backups()->get();
    }

    public function createBackup(Server $server): void
    {
        $service = app(ServerBackupService::class);
        $backup = $service->createFromServer($server, $this->backupName ?: null);

        $this->backupName = '';

        if ($backup) {
            $this->auditLog("created backup for server '{$server->name}'");
            session()->flash('backup-status-'.$server->id, __('Backup created successfully.'));
        } else {
            session()->flash('backup-error-'.$server->id, __('No .vars.Arma3Profile file found for this server. Start the server at least once first.'));
        }
    }

    public function uploadBackup(Server $server): void
    {
        $this->validate([
            'backupUploadFile' => ['required', 'file', 'max:10240'],
        ]);

        $data = file_get_contents($this->backupUploadFile->getRealPath());

        app(ServerBackupService::class)->createFromUpload(
            $server,
            $data,
            $this->backupUploadName ?: $this->backupUploadFile->getClientOriginalName(),
        );

        $this->auditLog("uploaded backup for server '{$server->name}'");

        $this->backupUploadFile = null;
        $this->backupUploadName = '';

        session()->flash('backup-status-'.$server->id, __('Backup uploaded successfully.'));
    }

    public function confirmRestore(int $backupId): void
    {
        $this->confirmingRestore = true;
        $this->restoringBackupId = $backupId;
    }

    public function restoreBackup(): void
    {
        if (! $this->restoringBackupId) {
            return;
        }

        $backup = ServerBackup::query()->findOrFail($this->restoringBackupId);
        $server = $backup->server;

        if ($this->getStatus($server) !== 'stopped') {
            session()->flash('backup-error-'.$server->id, __('Cannot restore while the server is running. Stop it first.'));
            $this->confirmingRestore = false;
            $this->restoringBackupId = null;

            return;
        }

        app(ServerBackupService::class)->restore($backup);

        $this->auditLog("restored backup #{$backup->id} for server '{$server->name}'");

        $this->confirmingRestore = false;
        $this->restoringBackupId = null;

        session()->flash('backup-status-'.$server->id, __('Backup restored successfully.'));
    }

    public function deleteBackup(ServerBackup $backup): void
    {
        $server = $backup->server;
        $backupId = $backup->id;
        $backup->delete();

        $this->auditLog("deleted backup #{$backupId} from server '{$server->name}'");

        session()->flash('backup-status-'.$server->id, __('Backup deleted.'));
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
                <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700" wire:key="server-{{ $server->id }}">
                    {{-- Server header row --}}
                    <div class="relative flex items-center justify-between p-4">
                        {{-- Status gradient overlays — always present, cross-fade on status change --}}
                        <div class="absolute inset-0 bg-gradient-to-r from-amber-400/20 to-zinc-300/5 transition-opacity duration-700 dark:from-amber-500/15 dark:to-zinc-600/5 {{ $status === 'starting' ? 'opacity-100' : 'opacity-0' }}"></div>
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-400/20 to-zinc-300/5 transition-opacity duration-700 dark:from-blue-500/15 dark:to-zinc-600/5 {{ $status === 'booting' ? 'opacity-100' : 'opacity-0' }}"></div>
                        <div class="absolute inset-0 bg-gradient-to-r from-emerald-400/20 to-zinc-300/5 transition-opacity duration-700 dark:from-emerald-500/15 dark:to-zinc-600/5 {{ $status === 'running' ? 'opacity-100' : 'opacity-0' }}"></div>
                        <div class="absolute inset-0 bg-gradient-to-r from-red-400/20 to-zinc-300/5 transition-opacity duration-700 dark:from-red-500/15 dark:to-zinc-600/5 {{ $status === 'stopping' ? 'opacity-100' : 'opacity-0' }}"></div>

                        <div class="relative">
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg">{{ $server->name }}</flux:heading>
                                <flux:badge :variant="match($status) { 'running' => 'success', 'starting', 'stopping', 'booting' => 'warning', default => 'secondary' }" size="sm">
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
                            <flux:text class="mt-0.5 font-mono text-xs text-zinc-400 dark:text-zinc-500">
                                {{ $server->getProfilesPath() }}
                            </flux:text>
                        </div>

                        <div class="relative flex items-center gap-2">
                            @if (in_array($status, ['starting', 'stopping']))
                                <flux:button size="sm" disabled>
                                    <flux:icon.arrow-path class="size-4 animate-spin" />
                                    {{ ucfirst($status) }}...
                                </flux:button>
                            @elseif ($status === 'booting')
                                <flux:button size="sm" disabled>
                                    <flux:icon.arrow-path class="size-4 animate-spin" />
                                    {{ __('Booting') }}...
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="stopServer({{ $server->id }})" :loading="false" icon="stop">
                                    {{ __('Stop') }}
                                </flux:button>
                            @elseif ($status === 'running')
                                <flux:button size="sm" variant="danger" wire:click="stopServer({{ $server->id }})" :loading="false" icon="stop">
                                    {{ __('Stop') }}
                                </flux:button>
                                <flux:button size="sm" wire:click="restartServer({{ $server->id }})" :loading="false" icon="arrow-path">
                                    {{ __('Restart') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="primary" wire:click="startServer({{ $server->id }})" :loading="false" icon="play">
                                    {{ __('Start') }}
                                </flux:button>
                            @endif

                            <flux:button size="sm" variant="ghost" wire:click="toggleServerLogs({{ $server->id }})" :loading="false" icon="command-line">
                                {{ __('Logs') }}
                            </flux:button>

                            <flux:button size="sm" variant="ghost" wire:click="toggleCommand({{ $server->id }})" :loading="false" icon="code-bracket">
                                {{ __('Command') }}
                            </flux:button>

                            @if ($editingServerId === $server->id)
                                <flux:button size="sm" wire:click="cancelEditing" :loading="false" icon="x-mark">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" wire:click="startEditing({{ $server->id }})" :loading="false" icon="pencil">
                                    {{ __('Configure') }}
                                </flux:button>
                            @endif

                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $server->id }})" :loading="false" icon="trash" :disabled="$status !== 'stopped'">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </div>

                    {{-- Headless client controls --}}
                    @if ($status === 'running')
                        @php $hcCount = $this->getHeadlessClientCount($server); @endphp
                        <div class="flex items-center gap-2 px-4 py-3">
                            <flux:text class="text-sm font-medium">{{ __('Headless Clients') }}</flux:text>
                            <flux:button size="xs" variant="ghost" wire:click="removeHeadlessClient({{ $server->id }})" :loading="false" icon="minus" :disabled="$hcCount < 1" />
                            <flux:badge :variant="$hcCount > 0 ? 'primary' : 'secondary'" size="sm">{{ $hcCount }}</flux:badge>
                            <flux:button size="xs" variant="ghost" wire:click="addHeadlessClient({{ $server->id }})" :loading="false" icon="plus" :disabled="$hcCount >= 10" />
                        </div>
                    @endif

                    {{-- Server log panel --}}
                    @if ($this->showLogs[$server->id] ?? in_array($status, ['booting', 'running']))
                        <x-log-viewer
                            channel="server-log.{{ $server->id }}"
                            event="ServerLogOutput"
                            max-height="max-h-[32rem]"
                            wire-load-method="loadServerLog({{ $server->id }})"
                            class="border-t border-zinc-200 dark:border-zinc-700 p-4"
                            wire:key="server-logs-{{ $server->id }}"
                        >
                            <flux:text class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">{{ __('Server Log') }}</flux:text>
                        </x-log-viewer>
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

                                @include('pages.servers.partials.form-fields', ['prefix' => 'edit'])

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

                            {{-- Backups section (outside config form) --}}
                            <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                                <flux:heading size="lg" class="mb-4">{{ __('State Backups') }}</flux:heading>
                                <flux:text class="mb-4">{{ __('Back up and restore the .vars.Arma3Profile file, which stores this server\'s runtime state.') }}</flux:text>

                                @if (session('backup-status-'.$server->id))
                                    <flux:callout variant="success" class="mb-4">
                                        {{ session('backup-status-'.$server->id) }}
                                    </flux:callout>
                                @endif

                                @if (session('backup-error-'.$server->id))
                                    <flux:callout variant="danger" class="mb-4">
                                        {{ session('backup-error-'.$server->id) }}
                                    </flux:callout>
                                @endif

                                {{-- Create backup from current state --}}
                                <div class="flex items-end gap-3 mb-4">
                                    <div class="flex-1 max-w-xs">
                                        <flux:input wire:model="backupName" :label="__('Backup Name')" :placeholder="__('Optional label')" size="sm" />
                                    </div>
                                    <flux:button size="sm" wire:click="createBackup({{ $server->id }})" icon="arrow-down-on-square" :disabled="$status !== 'stopped'">
                                        {{ __('Backup Current State') }}
                                    </flux:button>
                                </div>

                                {{-- Upload a .vars file --}}
                                <div class="flex items-end gap-3 mb-6"
                                    x-data="{ uploading: false, progress: 0 }"
                                    x-on:livewire-upload-start="uploading = true; progress = 0"
                                    x-on:livewire-upload-finish="uploading = false"
                                    x-on:livewire-upload-cancel="uploading = false"
                                    x-on:livewire-upload-error="uploading = false"
                                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                                >
                                    <div class="flex-1 max-w-xs">
                                        <flux:input wire:model="backupUploadName" :label="__('Upload Name')" :placeholder="__('Optional label')" size="sm" />
                                    </div>
                                    <div class="flex-1 max-w-xs">
                                        <flux:field>
                                            <flux:label>{{ __('.vars File') }}</flux:label>
                                            <input type="file" wire:model="backupUploadFile"
                                                class="block w-full text-sm text-zinc-500 dark:text-zinc-400
                                                    file:mr-4 file:py-1.5 file:px-3
                                                    file:rounded-lg file:border-0
                                                    file:text-sm file:font-semibold
                                                    file:bg-zinc-100 file:text-zinc-700
                                                    dark:file:bg-zinc-700 dark:file:text-zinc-200
                                                    hover:file:bg-zinc-200 dark:hover:file:bg-zinc-600
                                                    file:cursor-pointer cursor-pointer"
                                            />
                                            <flux:error name="backupUploadFile" />
                                        </flux:field>
                                    </div>
                                    <flux:button size="sm" wire:click="uploadBackup({{ $server->id }})" icon="arrow-up-tray" x-bind:disabled="uploading" :disabled="!$backupUploadFile">
                                        {{ __('Upload') }}
                                    </flux:button>
                                    <template x-if="uploading">
                                        <div class="text-xs text-zinc-500" x-text="progress + '%'"></div>
                                    </template>
                                </div>

                                {{-- Backup list --}}
                                @php $backups = $this->getBackups($server); @endphp
                                @if ($backups->isEmpty())
                                    <flux:text class="text-sm text-zinc-500">{{ __('No backups yet.') }}</flux:text>
                                @else
                                    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">{{ __('Name') }}</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">{{ __('Date') }}</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">{{ __('Size') }}</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">{{ __('Type') }}</th>
                                                    <th class="px-3 py-2 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">{{ __('Actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                                @foreach ($backups as $backup)
                                                    <tr wire:key="backup-{{ $backup->id }}">
                                                        <td class="px-3 py-2 text-sm">
                                                            {{ $backup->name ?? __('Unnamed') }}
                                                        </td>
                                                        <td class="px-3 py-2 text-sm text-zinc-500">
                                                            {{ $backup->created_at->format('M j, Y g:i A') }}
                                                        </td>
                                                        <td class="px-3 py-2 text-sm text-zinc-500 font-mono">
                                                            {{ Number::fileSize($backup->file_size) }}
                                                        </td>
                                                        <td class="px-3 py-2 text-sm">
                                                            <flux:badge size="sm" :variant="$backup->is_automatic ? 'secondary' : 'primary'">
                                                                {{ $backup->is_automatic ? __('Auto') : __('Manual') }}
                                                            </flux:badge>
                                                        </td>
                                                        <td class="px-3 py-2 text-right">
                                                            <div class="flex items-center justify-end gap-1">
                                                                <flux:button size="xs" variant="ghost" :href="route('servers.backups.download', $backup)" icon="arrow-down-tray">
                                                                </flux:button>
                                                                <flux:button size="xs" variant="ghost" wire:click="confirmRestore({{ $backup->id }})" icon="arrow-uturn-left" :disabled="$status !== 'stopped'">
                                                                </flux:button>
                                                                <flux:button size="xs" variant="ghost" wire:click="deleteBackup({{ $backup->id }})" icon="trash">
                                                                </flux:button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <flux:text class="mt-2 text-xs text-zinc-400">
                                        {{ __('Retention limit: :count backups per server.', ['count' => config('arma.max_backups_per_server') ?: __('Unlimited')]) }}
                                    </flux:text>
                                @endif
                            </div>
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

            @include('pages.servers.partials.form-fields', ['prefix' => 'create'])

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

    {{-- Restore backup confirmation modal --}}
    <flux:modal wire:model="confirmingRestore">
        <flux:heading>{{ __('Restore Backup') }}</flux:heading>
        <flux:text>{{ __('Are you sure you want to restore this backup? This will overwrite the server\'s current .vars.Arma3Profile state. The server must be stopped.') }}</flux:text>
        <div class="flex justify-end gap-2 mt-4">
            <flux:button wire:click="$set('confirmingRestore', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="restoreBackup" icon="arrow-uturn-left">{{ __('Restore') }}</flux:button>
        </div>
    </flux:modal>
</section>
