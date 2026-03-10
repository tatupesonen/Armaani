<?php

namespace App\GameHandlers;

use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Contracts\SteamGameHandler;
use App\Models\ModPreset;
use App\Models\ProjectZomboidSettings;
use App\Models\Server;
use App\Services\Renderer\TwigConfigRenderer;

final class ProjectZomboidHandler implements DetectsServerState, GameHandler, SteamGameHandler
{
    public function __construct(
        protected TwigConfigRenderer $configRenderer,
    ) {}

    public function value(): string
    {
        return 'projectzomboid';
    }

    public function label(): string
    {
        return 'Project Zomboid';
    }

    // --- Steam ---

    public function serverAppId(): int
    {
        return 380870;
    }

    public function gameId(): int
    {
        return 108600;
    }

    public function consumerAppId(): int
    {
        return 108600;
    }

    // --- Game Metadata ---

    public function defaultPort(): int
    {
        return 16261;
    }

    public function defaultQueryPort(): int
    {
        return 16262;
    }

    public function branches(): array
    {
        return ['public', 'unstable'];
    }

    public function supportsWorkshopMods(): bool
    {
        return true;
    }

    public function requiresLowercaseConversion(): bool
    {
        return false;
    }

    // --- Server Process ---

    public function buildLaunchCommand(Server $server): array
    {
        $binary = $this->getBinaryPath($server);
        $profileName = $this->getProfileName($server);
        $cachedir = $server->getProfilesPath();

        $params = [
            $binary,
            '-servername', $profileName,
            '-cachedir='.$cachedir,
            '-adminpassword', $server->admin_password,
        ];

        if ($server->additional_params) {
            $additionalArgs = preg_split('/\s+/', trim($server->additional_params), -1, PREG_SPLIT_NO_EMPTY);
            array_push($params, ...$additionalArgs);
        }

        return $params;
    }

    public function generateConfigFiles(Server $server): void
    {
        $this->generateServerConfig($server);
    }

    public function getBinaryPath(Server $server): string
    {
        return $server->gameInstall->getInstallationPath().'/start-server.sh';
    }

    public function getProfileName(Server $server): string
    {
        return 'pz_'.$server->id;
    }

    public function getServerLogPath(Server $server): string
    {
        return $server->getProfilesPath().'/server-console.txt';
    }

    // --- DetectsServerState ---

    public function getBootDetectionStrings(): array
    {
        return ['LuaNet: Initialization [DONE]'];
    }

    public function getModDownloadStartedString(): ?string
    {
        return null;
    }

    public function getModDownloadFinishedString(): ?string
    {
        return null;
    }

    public function getCrashDetectionStrings(): array
    {
        return [];
    }

    public function shouldAutoRestart(Server $server): bool
    {
        return false;
    }

    // --- UI Schema ---

    public function settingsSchema(): array
    {
        return [
            // --- Server Rules ---
            [
                'title' => 'Server Rules',
                'showOnCreate' => true,
                'createLabel' => 'Project Zomboid Options',
                'fields' => [
                    ['key' => 'password', 'label' => 'Server Password', 'type' => 'text', 'default' => '', 'placeholder' => 'Leave empty for no password', 'source' => 'server'],
                    ['key' => 'admin_password', 'label' => 'Admin Password', 'type' => 'text', 'default' => '', 'placeholder' => 'Required for server startup', 'required' => true, 'source' => 'server'],
                    ['type' => 'separator'],
                    ['key' => 'open', 'label' => 'Public Server', 'type' => 'toggle', 'default' => true, 'source' => 'projectzomboid_settings', 'description' => 'Show in the public server browser.'],
                    ['key' => 'pvp', 'label' => 'PVP', 'type' => 'toggle', 'default' => true, 'source' => 'projectzomboid_settings'],
                    ['key' => 'pause_empty', 'label' => 'Pause When Empty', 'type' => 'toggle', 'default' => true, 'source' => 'projectzomboid_settings', 'description' => 'Pause the server when no players are connected.'],
                    ['key' => 'global_chat', 'label' => 'Global Chat', 'type' => 'toggle', 'default' => true, 'source' => 'projectzomboid_settings'],
                    ['key' => 'safety_system', 'label' => 'PVP Safety System', 'type' => 'toggle', 'default' => true, 'source' => 'projectzomboid_settings', 'description' => 'Players can toggle PVP safety to prevent being attacked.'],
                    ['key' => 'show_safety', 'label' => 'Show Safety Status', 'type' => 'toggle', 'default' => true, 'source' => 'projectzomboid_settings', 'description' => 'Show other players\' PVP safety status.'],
                ],
            ],

            // --- Gameplay Settings ---
            [
                'title' => 'Gameplay Settings',
                'collapsible' => true,
                'source' => 'projectzomboid_settings',
                'fields' => [
                    ['key' => 'map', 'label' => 'Map', 'type' => 'text', 'default' => 'Muldraugh, KY', 'placeholder' => 'Muldraugh, KY', 'description' => 'Map name. Use semicolons to combine maps.'],
                    ['key' => 'sleep_allowed', 'label' => 'Sleep Allowed', 'type' => 'toggle', 'default' => false, 'description' => 'Allow players to sleep.'],
                    ['key' => 'sleep_needed', 'label' => 'Sleep Needed', 'type' => 'toggle', 'default' => false, 'description' => 'Players need to sleep to survive.'],
                    ['key' => 'announce_death', 'label' => 'Announce Death', 'type' => 'toggle', 'default' => false, 'description' => 'Broadcast a message when a player dies.'],
                    ['key' => 'do_lua_checksum', 'label' => 'Lua Checksum', 'type' => 'toggle', 'default' => true, 'description' => 'Verify client Lua file integrity.'],
                    ['type' => 'separator'],
                    ['key' => 'max_accounts_per_user', 'label' => 'Max Accounts Per User', 'type' => 'number', 'default' => 0, 'min' => 0, 'max' => 50, 'description' => 'Max characters per Steam account. 0 = unlimited.'],
                    ['key' => 'login_queue_enabled', 'label' => 'Login Queue', 'type' => 'toggle', 'default' => false, 'description' => 'Queue players when the server is full.'],
                    ['key' => 'deny_login_on_overloaded_server', 'label' => 'Deny Login When Overloaded', 'type' => 'toggle', 'default' => true, 'description' => 'Prevent new logins when the server is overloaded.'],
                ],
            ],

            // --- Advanced ---
            [
                'advanced' => true,
                'fields' => [
                    ['key' => 'additional_params', 'label' => 'Additional Launch Parameters', 'type' => 'textarea', 'default' => '', 'rows' => 2, 'placeholder' => '-Xms4g -Xmx4g', 'source' => 'server'],
                ],
            ],
        ];
    }

    // --- Validation ---

    public function serverValidationRules(?Server $server = null): array
    {
        return [
            'password' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['required', 'string', 'max:255'],
            'additional_params' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function settingsValidationRules(): array
    {
        return [
            'pvp' => ['boolean'],
            'pause_empty' => ['boolean'],
            'global_chat' => ['boolean'],
            'open' => ['boolean'],
            'map' => ['nullable', 'string', 'max:500'],
            'safety_system' => ['boolean'],
            'show_safety' => ['boolean'],
            'sleep_allowed' => ['boolean'],
            'sleep_needed' => ['boolean'],
            'announce_death' => ['boolean'],
            'do_lua_checksum' => ['boolean'],
            'max_accounts_per_user' => ['integer', 'min:0', 'max:50'],
            'login_queue_enabled' => ['boolean'],
            'deny_login_on_overloaded_server' => ['boolean'],
        ];
    }

    // --- Related Settings ---

    /** @phpstan-ignore return.unusedType */
    public function settingsModelClass(): ?string
    {
        return ProjectZomboidSettings::class;
    }

    /** @phpstan-ignore return.unusedType */
    public function settingsRelationName(): ?string
    {
        return 'projectzomboidSettings';
    }

    public function createRelatedSettings(Server $server): void
    {
        ProjectZomboidSettings::query()->create(['server_id' => $server->id]);
    }

    public function updateRelatedSettings(Server $server, array $validated): void
    {
        $fields = collect($validated)->only(
            (new ProjectZomboidSettings)->getFillable()
        )->except('server_id')->toArray();

        if (! empty($fields)) {
            $server->projectzomboidSettings()->updateOrCreate(
                ['server_id' => $server->id],
                $fields,
            );
        }
    }

    // --- Mod Presets ---

    /**
     * @return list<array{type: 'workshop'|'registered', label: string, relationship: string, formField: string}>
     */
    public function modSections(): array
    {
        return [
            [
                'type' => 'workshop',
                'label' => 'Workshop Mods',
                'relationship' => 'mods',
                'formField' => 'mod_ids',
            ],
        ];
    }

    public function syncPresetMods(ModPreset $preset, array $validated): void
    {
        $preset->mods()->sync($validated['mod_ids'] ?? []);
    }

    public function getPresetModCount(ModPreset $preset): int
    {
        return $preset->mods()->count();
    }

    // --- Config Generation ---

    /**
     * Generate the PZ server INI config at {cachedir}/Server/{profile_name}.ini.
     */
    protected function generateServerConfig(Server $server): void
    {
        $settings = $server->projectzomboidSettings ?? $this->getDefaultSettings();
        $profileName = $this->getProfileName($server);
        $serverDir = $server->getProfilesPath().'/Server';

        if (! is_dir($serverDir)) {
            mkdir($serverDir, 0755, true);
        }

        $preset = $server->activePreset;
        $workshopIds = '';
        $modIds = '';

        if ($preset) {
            $workshopIds = $preset->mods->pluck('workshop_id')->implode(';');
            $modIds = $preset->mods->pluck('name')->implode(';');
        }

        $content = $this->configRenderer->render('projectzomboid/server.ini.twig', [
            'public_name' => addslashes($server->name),
            'public_description' => addslashes((string) $server->description),
            'max_players' => (int) $server->max_players,
            'password' => (string) $server->password,
            'default_port' => $server->port,
            'steam_port_1' => $server->query_port,
            'steam_port_2' => $server->query_port + 1,
            'pvp' => $settings->pvp,
            'pause_empty' => $settings->pause_empty,
            'global_chat' => $settings->global_chat,
            'open' => $settings->open,
            'map' => $settings->map,
            'safety_system' => $settings->safety_system,
            'show_safety' => $settings->show_safety,
            'sleep_allowed' => $settings->sleep_allowed,
            'sleep_needed' => $settings->sleep_needed,
            'announce_death' => $settings->announce_death,
            'do_lua_checksum' => $settings->do_lua_checksum,
            'max_accounts_per_user' => $settings->max_accounts_per_user,
            'login_queue_enabled' => $settings->login_queue_enabled,
            'deny_login_on_overloaded_server' => $settings->deny_login_on_overloaded_server,
            'workshop_items' => $workshopIds,
            'mod_ids' => $modIds,
        ]);

        file_put_contents(
            $serverDir.'/'.$profileName.'.ini',
            $content,
        );
    }

    /**
     * Build a default ProjectZomboidSettings object for servers without saved settings.
     */
    protected function getDefaultSettings(): ProjectZomboidSettings
    {
        $settings = new ProjectZomboidSettings;
        $settings->pvp = true;
        $settings->pause_empty = true;
        $settings->global_chat = true;
        $settings->open = true;
        $settings->map = 'Muldraugh, KY';
        $settings->safety_system = true;
        $settings->show_safety = true;
        $settings->sleep_allowed = false;
        $settings->sleep_needed = false;
        $settings->announce_death = false;
        $settings->do_lua_checksum = true;
        $settings->max_accounts_per_user = 0;
        $settings->login_queue_enabled = false;
        $settings->deny_login_on_overloaded_server = true;

        return $settings;
    }
}
