<?php

namespace App\GameHandlers;

use App\Concerns\DetectsServerStateBehavior;
use App\Contracts\DetectsServerState;
use App\Contracts\DownloadsDirectly;
use App\Contracts\HasQueryPort;
use App\Models\FactorioSettings;
use App\Models\Server;
use App\Services\Renderer\JsonConfigRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

final class FactorioHandler extends AbstractGameHandler implements DetectsServerState, DownloadsDirectly, HasQueryPort
{
    use DetectsServerStateBehavior;

    public function __construct(
        protected JsonConfigRenderer $configRenderer,
    ) {
        parent::__construct(
            value: 'factorio',
            label: 'Factorio',
            defaultPort: 34197,
            defaultQueryPort: 27015,
            branches: ['stable', 'experimental'],
            settingsModelClass: FactorioSettings::class,
            settingsRelationName: 'factorioSettings',
        );
    }

    // --- DownloadsDirectly ---

    public function getDownloadUrl(string $branch): string
    {
        return "https://factorio.com/get-download/{$branch}/headless/linux64";
    }

    public function getArchiveStripComponents(): int
    {
        return 1;
    }

    // --- Server Process ---

    public function buildLaunchCommand(Server $server): array
    {
        $binary = $this->getBinaryPath($server);
        $profilesPath = $server->getProfilesPath();
        $settings = $server->factorioSettings;

        $params = [
            $binary,
            '--config', $profilesPath.'/config.ini',
            '--start-server', $profilesPath.'/saves/save.zip',
            '--server-settings', $profilesPath.'/server-settings.json',
            '--port', (string) $server->port,
        ];

        if ($settings?->rcon_password) {
            $params[] = '--rcon-port';
            $params[] = (string) $server->query_port;
            $params[] = '--rcon-password';
            $params[] = $settings->rcon_password;
        }

        if ($server->additional_params) {
            $additionalArgs = preg_split('/\s+/', trim($server->additional_params), -1, PREG_SPLIT_NO_EMPTY);
            array_push($params, ...$additionalArgs);
        }

        return $params;
    }

    public function generateConfigFiles(Server $server): void
    {
        $settings = $server->factorioSettings ?? $this->getDefaultSettings();
        $profilesPath = $server->getProfilesPath();

        $this->generateConfigIni($server, $profilesPath);
        $this->generateServerSettings($server, $settings, $profilesPath);
        $this->generateMapGenSettings($settings, $profilesPath);
        $this->generateMapSettings($settings, $profilesPath);
        $this->ensureSaveExists($server, $profilesPath);
    }

    public function getBinaryPath(Server $server): string
    {
        return $server->gameInstall->getInstallationPath().'/bin/x64/factorio';
    }

    public function getProfileName(Server $server): string
    {
        return 'factorio_'.$server->id;
    }

    public function getServerLogPath(Server $server): string
    {
        return $server->getProfilesPath().'/factorio-current.log';
    }

    // --- DetectsServerState ---

    public function getBootDetectionStrings(): array
    {
        return ['Hosting game at'];
    }

    public function getCrashDetectionStrings(): array
    {
        return [];
    }

    public function getModDownloadStartedString(): ?string
    {
        return null;
    }

    public function getModDownloadFinishedString(): ?string
    {
        return null;
    }

    // --- UI Schema ---

    public function settingsSchema(): array
    {
        $scaleOptions = [
            ['value' => 'none', 'label' => 'None'],
            ['value' => 'very-low', 'label' => 'Very Low'],
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'normal', 'label' => 'Normal'],
            ['value' => 'high', 'label' => 'High'],
            ['value' => 'very-high', 'label' => 'Very High'],
        ];

        $startingAreaOptions = [
            ['value' => 'very-small', 'label' => 'Very Small'],
            ['value' => 'small', 'label' => 'Small'],
            ['value' => 'normal', 'label' => 'Normal'],
            ['value' => 'big', 'label' => 'Big'],
            ['value' => 'very-big', 'label' => 'Very Big'],
        ];

        $resourceRow = function (string $resource, string $label) use ($scaleOptions) {
            return [
                'columns' => 3,
                'label' => $label,
                'fields' => [
                    ['key' => "{$resource}_frequency", 'label' => 'Frequency', 'type' => 'segmented', 'default' => 'normal', 'options' => $scaleOptions],
                    ['key' => "{$resource}_size", 'label' => 'Size', 'type' => 'segmented', 'default' => 'normal', 'options' => $scaleOptions],
                    ['key' => "{$resource}_richness", 'label' => 'Richness', 'type' => 'segmented', 'default' => 'normal', 'options' => $scaleOptions],
                ],
            ];
        };

        return [
            // --- Server Rules ---
            [
                'title' => 'Server Rules',
                'showOnCreate' => true,
                'createLabel' => 'Factorio Options',
                'fields' => [
                    ['key' => 'query_port', 'label' => 'RCON Port', 'type' => 'number', 'default' => $this->defaultQueryPort(), 'min' => 1, 'max' => 65535, 'description' => 'Remote console port. Only used when RCON password is set.', 'source' => 'server'],
                    ['key' => 'password', 'label' => 'Server Password', 'type' => 'text', 'default' => '', 'placeholder' => 'Leave empty for no password', 'source' => 'server'],
                    ['key' => 'rcon_password', 'label' => 'RCON Password', 'type' => 'text', 'default' => '', 'placeholder' => 'Leave empty to disable RCON', 'source' => 'factorio_settings'],
                ],
            ],

            // --- Server Settings ---
            [
                'title' => 'Server Settings',
                'collapsible' => true,
                'source' => 'factorio_settings',
                'fields' => [
                    ['key' => 'visibility_public', 'label' => 'Public', 'type' => 'toggle', 'default' => true, 'description' => 'Show in the public server browser.'],
                    ['key' => 'visibility_lan', 'label' => 'LAN', 'type' => 'toggle', 'default' => true, 'description' => 'Show in the LAN server browser.'],
                    ['key' => 'require_user_verification', 'label' => 'Verify Users', 'type' => 'toggle', 'default' => true, 'description' => 'Verify user accounts with factorio.com.'],
                    ['type' => 'separator'],
                    ['key' => 'max_upload_kbps', 'label' => 'Max Upload (KB/s)', 'type' => 'number', 'default' => 0, 'min' => 0, 'description' => '0 = unlimited.'],
                    ['key' => 'max_heartbeats_per_second', 'label' => 'Max Heartbeats/s', 'type' => 'number', 'default' => 60, 'min' => 6, 'max' => 240, 'description' => 'Network heartbeat frequency.'],
                    ['key' => 'ignore_player_limit_for_returning', 'label' => 'Ignore Limit for Returning', 'type' => 'toggle', 'default' => false, 'description' => 'Allow returning players even when server is full.'],
                    ['key' => 'allow_commands', 'label' => 'Allow Commands', 'type' => 'segmented', 'default' => 'admins-only', 'options' => [
                        ['value' => 'true', 'label' => 'All'],
                        ['value' => 'admins-only', 'label' => 'Admins'],
                        ['value' => 'false', 'label' => 'None'],
                    ]],
                    ['type' => 'separator'],
                    ['key' => 'autosave_interval', 'label' => 'Autosave (min)', 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 60],
                    ['key' => 'autosave_slots', 'label' => 'Autosave Slots', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 100],
                    ['key' => 'afk_autokick_interval', 'label' => 'AFK Kick (min)', 'type' => 'number', 'default' => 0, 'min' => 0, 'description' => '0 = disabled.'],
                    ['key' => 'auto_pause', 'label' => 'Auto-Pause', 'type' => 'toggle', 'default' => true, 'description' => 'Pause when no players connected.'],
                    ['key' => 'only_admins_can_pause', 'label' => 'Admin-Only Pause', 'type' => 'toggle', 'default' => true],
                    ['key' => 'autosave_only_on_server', 'label' => 'Server-Only Autosave', 'type' => 'toggle', 'default' => true],
                    ['key' => 'non_blocking_saving', 'label' => 'Non-Blocking Save', 'type' => 'toggle', 'default' => false],
                    ['key' => 'tags', 'label' => 'Tags', 'type' => 'text', 'default' => '', 'placeholder' => 'vanilla, modded, ...', 'description' => 'Comma-separated tags for the server browser.'],
                ],
            ],

            // --- Map Generation: Resources ---
            [
                'title' => 'Map Generation: Resources',
                'description' => 'Resource frequency, size, and richness. Changes only apply to new saves.',
                'collapsible' => true,
                'source' => 'factorio_settings',
                'layout' => 'rows',
                'groups' => [
                    $resourceRow('coal', 'Coal'),
                    $resourceRow('copper_ore', 'Copper Ore'),
                    $resourceRow('crude_oil', 'Crude Oil'),
                    $resourceRow('iron_ore', 'Iron Ore'),
                    $resourceRow('stone', 'Stone'),
                    $resourceRow('trees', 'Trees'),
                    $resourceRow('uranium_ore', 'Uranium Ore'),
                    $resourceRow('enemy_base', 'Enemy Base'),
                ],
            ],

            // --- Map Generation: Terrain ---
            [
                'title' => 'Map Generation: Terrain',
                'description' => 'Terrain generation settings. Changes only apply to new saves.',
                'collapsible' => true,
                'source' => 'factorio_settings',
                'layout' => 'rows',
                'groups' => [
                    [
                        'columns' => 2,
                        'fields' => [
                            ['key' => 'map_width', 'label' => 'Map Width', 'type' => 'number', 'default' => 0, 'min' => 0, 'description' => '0 = infinite.'],
                            ['key' => 'map_height', 'label' => 'Map Height', 'type' => 'number', 'default' => 0, 'min' => 0, 'description' => '0 = infinite.'],
                        ],
                    ],
                    [
                        'fields' => [
                            ['key' => 'starting_area', 'label' => 'Starting Area', 'type' => 'segmented', 'default' => 'normal', 'options' => $startingAreaOptions],
                            ['key' => 'water', 'label' => 'Water', 'type' => 'segmented', 'default' => 'normal', 'options' => $scaleOptions],
                            ['key' => 'terrain_segmentation', 'label' => 'Terrain Segmentation', 'type' => 'segmented', 'default' => 'normal', 'options' => $scaleOptions],
                        ],
                    ],
                    [
                        'columns' => 2,
                        'fields' => [
                            ['key' => 'cliff_elevation_0', 'label' => 'Cliff Base Height', 'type' => 'number', 'default' => 10, 'min' => 0, 'step' => 0.1],
                            ['key' => 'cliff_elevation_interval', 'label' => 'Cliff Interval', 'type' => 'number', 'default' => 40, 'min' => 1, 'step' => 0.1],
                        ],
                    ],
                    [
                        'fields' => [
                            ['key' => 'cliff_richness', 'label' => 'Cliff Frequency', 'type' => 'segmented', 'default' => 'normal', 'options' => $scaleOptions],
                            ['key' => 'peaceful_mode', 'label' => 'Peaceful Mode', 'type' => 'toggle', 'default' => false],
                            ['key' => 'map_seed', 'label' => 'Map Seed', 'type' => 'text', 'default' => '', 'placeholder' => 'Random', 'description' => 'Leave empty for random.'],
                        ],
                    ],
                ],
            ],

            // --- Map Settings: Gameplay ---
            [
                'title' => 'Map Settings: Gameplay',
                'description' => 'Runtime enemy behavior. Changes only apply to new saves.',
                'collapsible' => true,
                'source' => 'factorio_settings',
                'fields' => [
                    ['key' => 'pollution_enabled', 'label' => 'Pollution', 'type' => 'toggle', 'default' => true],
                    ['key' => 'evolution_enabled', 'label' => 'Evolution', 'type' => 'toggle', 'default' => true],
                    ['key' => 'evolution_time_factor', 'label' => 'Evolution Time Factor', 'type' => 'number', 'default' => 0.000004, 'min' => 0, 'step' => 0.000001],
                    ['key' => 'evolution_destroy_factor', 'label' => 'Evolution Destroy Factor', 'type' => 'number', 'default' => 0.002, 'min' => 0, 'step' => 0.001],
                    ['key' => 'evolution_pollution_factor', 'label' => 'Evolution Pollution Factor', 'type' => 'number', 'default' => 0.0000009, 'min' => 0, 'step' => 0.0000001],
                    ['key' => 'expansion_enabled', 'label' => 'Enemy Expansion', 'type' => 'toggle', 'default' => true],
                ],
            ],

            // --- Advanced ---
            [
                'advanced' => true,
                'fields' => [
                    ['key' => 'additional_params', 'label' => 'Additional Launch Parameters', 'type' => 'textarea', 'default' => '', 'rows' => 2, 'placeholder' => '--no-auto-pause', 'source' => 'server'],
                ],
            ],
        ];
    }

    // --- Validation ---

    /**
     * @return array<string, mixed>
     */
    public function serverValidationRules(?Server $server = null): array
    {
        return [
            ...parent::serverValidationRules($server),
            'password' => ['nullable', 'string', 'max:255'],
            'additional_params' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsValidationRules(): array
    {
        $scaleRule = 'in:none,very-low,low,normal,high,very-high';
        $startingAreaRule = 'in:very-small,small,normal,big,very-big';

        return [
            // RCON
            'rcon_password' => ['nullable', 'string', 'max:255'],

            // Server settings
            'visibility_public' => ['boolean'],
            'visibility_lan' => ['boolean'],
            'require_user_verification' => ['boolean'],
            'max_upload_kbps' => ['integer', 'min:0'],
            'max_heartbeats_per_second' => ['integer', 'min:6', 'max:240'],
            'ignore_player_limit_for_returning' => ['boolean'],
            'allow_commands' => ['string', 'in:true,false,admins-only'],
            'autosave_interval' => ['integer', 'min:1', 'max:60'],
            'autosave_slots' => ['integer', 'min:1', 'max:100'],
            'afk_autokick_interval' => ['integer', 'min:0'],
            'auto_pause' => ['boolean'],
            'only_admins_can_pause' => ['boolean'],
            'autosave_only_on_server' => ['boolean'],
            'non_blocking_saving' => ['boolean'],
            'tags' => ['nullable', 'string', 'max:500'],

            // Map generation: resources
            'coal_frequency' => ['string', $scaleRule],
            'coal_size' => ['string', $scaleRule],
            'coal_richness' => ['string', $scaleRule],
            'copper_ore_frequency' => ['string', $scaleRule],
            'copper_ore_size' => ['string', $scaleRule],
            'copper_ore_richness' => ['string', $scaleRule],
            'crude_oil_frequency' => ['string', $scaleRule],
            'crude_oil_size' => ['string', $scaleRule],
            'crude_oil_richness' => ['string', $scaleRule],
            'enemy_base_frequency' => ['string', $scaleRule],
            'enemy_base_size' => ['string', $scaleRule],
            'enemy_base_richness' => ['string', $scaleRule],
            'iron_ore_frequency' => ['string', $scaleRule],
            'iron_ore_size' => ['string', $scaleRule],
            'iron_ore_richness' => ['string', $scaleRule],
            'stone_frequency' => ['string', $scaleRule],
            'stone_size' => ['string', $scaleRule],
            'stone_richness' => ['string', $scaleRule],
            'trees_frequency' => ['string', $scaleRule],
            'trees_size' => ['string', $scaleRule],
            'trees_richness' => ['string', $scaleRule],
            'uranium_ore_frequency' => ['string', $scaleRule],
            'uranium_ore_size' => ['string', $scaleRule],
            'uranium_ore_richness' => ['string', $scaleRule],

            // Map generation: terrain
            'map_width' => ['integer', 'min:0'],
            'map_height' => ['integer', 'min:0'],
            'starting_area' => ['string', $startingAreaRule],
            'peaceful_mode' => ['boolean'],
            'map_seed' => ['nullable', 'string', 'max:20'],
            'water' => ['string', $scaleRule],
            'terrain_segmentation' => ['string', $scaleRule],
            'cliff_elevation_0' => ['numeric', 'min:0'],
            'cliff_elevation_interval' => ['numeric', 'min:1'],
            'cliff_richness' => ['string', $scaleRule],

            // Map settings: gameplay
            'pollution_enabled' => ['boolean'],
            'evolution_enabled' => ['boolean'],
            'evolution_time_factor' => ['numeric', 'min:0'],
            'evolution_destroy_factor' => ['numeric', 'min:0'],
            'evolution_pollution_factor' => ['numeric', 'min:0'],
            'expansion_enabled' => ['boolean'],
        ];
    }

    // --- Config Generation ---

    /**
     * Generate a per-server config.ini that redirects write-data to the server's
     * profiles directory, keeping the shared game install directory clean.
     */
    protected function generateConfigIni(Server $server, string $profilesPath): void
    {
        $installPath = $server->gameInstall->getInstallationPath();

        $ini = implode("\n", [
            '[path]',
            "read-data={$installPath}/data",
            "write-data={$profilesPath}",
        ]);

        file_put_contents($profilesPath.'/config.ini', $ini."\n");
    }

    /**
     * Generate the server-settings.json file.
     */
    protected function generateServerSettings(Server $server, FactorioSettings $settings, string $profilesPath): void
    {
        $tags = [];
        if ($settings->tags) {
            $tags = array_map('trim', explode(',', $settings->tags));
            $tags = array_filter($tags, fn (string $tag): bool => $tag !== '');
            $tags = array_values($tags);
        }

        $config = [
            'name' => $server->name,
            'description' => (string) $server->description,
            'tags' => $tags,
            'max_players' => (int) $server->max_players,
            'visibility' => [
                'public' => $settings->visibility_public,
                'lan' => $settings->visibility_lan,
            ],
            'username' => '',
            'password' => '',
            'token' => '',
            'game_password' => (string) $server->password,
            'require_user_verification' => $settings->require_user_verification,
            'max_upload_in_kilobytes_per_second' => (int) $settings->max_upload_kbps,
            'max_upload_slots' => 5,
            'minimum_latency_in_ticks' => 0,
            'max_heartbeats_per_second' => (int) $settings->max_heartbeats_per_second,
            'ignore_player_limit_for_returning_players' => $settings->ignore_player_limit_for_returning,
            'allow_commands' => $settings->allow_commands,
            'autosave_interval' => (int) $settings->autosave_interval,
            'autosave_slots' => (int) $settings->autosave_slots,
            'afk_autokick_interval' => (int) $settings->afk_autokick_interval,
            'auto_pause' => $settings->auto_pause,
            'only_admins_can_pause_the_game' => $settings->only_admins_can_pause,
            'autosave_only_on_server' => $settings->autosave_only_on_server,
            'non_blocking_saving' => $settings->non_blocking_saving,
            'minimum_segment_size' => 25,
            'minimum_segment_size_peer_count' => 20,
            'maximum_segment_size' => 100,
            'maximum_segment_size_peer_count' => 10,
        ];

        file_put_contents(
            $profilesPath.'/server-settings.json',
            $this->configRenderer->render('factorio/server-settings.json', $config),
        );
    }

    /**
     * Generate the map-gen-settings.json file (used for save creation).
     */
    protected function generateMapGenSettings(FactorioSettings $settings, string $profilesPath): void
    {
        $config = [
            'terrain_segmentation' => $this->scaleToMultiplier($settings->terrain_segmentation),
            'water' => $this->scaleToMultiplier($settings->water),
            'width' => (int) $settings->map_width,
            'height' => (int) $settings->map_height,
            'starting_area' => $this->scaleToMultiplier($settings->starting_area),
            'peaceful_mode' => $settings->peaceful_mode,
            'autoplace_controls' => [
                'coal' => $this->buildAutoplaceControl($settings, 'coal'),
                'copper-ore' => $this->buildAutoplaceControl($settings, 'copper_ore'),
                'crude-oil' => $this->buildAutoplaceControl($settings, 'crude_oil'),
                'enemy-base' => $this->buildAutoplaceControl($settings, 'enemy_base'),
                'iron-ore' => $this->buildAutoplaceControl($settings, 'iron_ore'),
                'stone' => $this->buildAutoplaceControl($settings, 'stone'),
                'trees' => $this->buildAutoplaceControl($settings, 'trees'),
                'uranium-ore' => $this->buildAutoplaceControl($settings, 'uranium_ore'),
            ],
            'cliff_settings' => [
                'name' => 'cliff',
                'cliff_elevation_0' => (float) $settings->cliff_elevation_0,
                'cliff_elevation_interval' => (float) $settings->cliff_elevation_interval,
                'richness' => $this->scaleToMultiplier($settings->cliff_richness),
            ],
            'starting_points' => [['x' => 0, 'y' => 0]],
            'seed' => $settings->map_seed !== null && $settings->map_seed !== '' ? (int) $settings->map_seed : null,
        ];

        file_put_contents(
            $profilesPath.'/map-gen-settings.json',
            $this->configRenderer->render('factorio/map-gen-settings.json', $config),
        );
    }

    /**
     * Generate the map-settings.json file (runtime gameplay settings, used for save creation).
     */
    protected function generateMapSettings(FactorioSettings $settings, string $profilesPath): void
    {
        // Factorio 2.0 requires ALL sub-keys when a section is present — it does
        // not merge with built-in defaults.  We expose a subset of these as
        // user-facing settings and hardcode the Factorio 2.0 defaults for the rest.
        $config = [
            'difficulty_settings' => [
                'technology_price_multiplier' => 1,
                'spoil_time_modifier' => 1,
            ],
            'pollution' => [
                'enabled' => $settings->pollution_enabled,
                'diffusion_ratio' => 0.02,
                'min_to_diffuse' => 15,
                'ageing' => 1,
                'expected_max_per_chunk' => 150,
                'min_to_show_per_chunk' => 50,
                'min_pollution_to_damage_trees' => 60,
                'pollution_with_max_forest_damage' => 150,
                'pollution_per_tree_damage' => 50,
                'pollution_restored_per_tree_damage' => 10,
                'max_pollution_to_restore_trees' => 20,
                'enemy_attack_pollution_consumption_modifier' => 1,
            ],
            'enemy_evolution' => [
                'enabled' => $settings->evolution_enabled,
                'time_factor' => (float) $settings->evolution_time_factor,
                'destroy_factor' => (float) $settings->evolution_destroy_factor,
                'pollution_factor' => (float) $settings->evolution_pollution_factor,
            ],
            'enemy_expansion' => [
                'enabled' => $settings->expansion_enabled,
                'max_expansion_distance' => 7,
                'friendly_base_influence_radius' => 2,
                'enemy_building_influence_radius' => 2,
                'building_coefficient' => 0.1,
                'other_base_coefficient' => 2.0,
                'neighbouring_chunk_coefficient' => 0.5,
                'neighbouring_base_chunk_coefficient' => 0.4,
                'max_colliding_tiles_coefficient' => 0.9,
                'settler_group_min_size' => 5,
                'settler_group_max_size' => 20,
                'min_expansion_cooldown' => 14400,
                'max_expansion_cooldown' => 216000,
            ],
            'unit_group' => [
                'min_group_gathering_time' => 3600,
                'max_group_gathering_time' => 36000,
                'max_wait_time_for_late_members' => 7200,
                'max_group_radius' => 30.0,
                'min_group_radius' => 5.0,
                'max_member_speedup_when_behind' => 1.4,
                'max_member_slowdown_when_ahead' => 0.6,
                'max_group_slowdown_factor' => 0.3,
                'max_group_member_fallback_factor' => 3,
                'member_disown_distance' => 10,
                'tick_tolerance_when_member_arrives' => 60,
                'max_gathering_unit_groups' => 30,
                'max_unit_group_size' => 200,
            ],
            'steering' => [
                'default' => [
                    'radius' => 1.2,
                    'separation_force' => 0.005,
                    'separation_factor' => 1.2,
                    'force_unit_fuzzy_goto_behavior' => false,
                ],
                'moving' => [
                    'radius' => 3.0,
                    'separation_force' => 0.01,
                    'separation_factor' => 3.0,
                    'force_unit_fuzzy_goto_behavior' => false,
                ],
            ],
            'path_finder' => [
                'fwd2bwd_ratio' => 5,
                'goal_pressure_ratio' => 2,
                'max_steps_worked_per_tick' => 1000,
                'max_work_done_per_tick' => 8000,
                'use_path_cache' => true,
                'short_cache_size' => 5,
                'long_cache_size' => 25,
                'short_cache_min_cacheable_distance' => 10,
                'short_cache_min_algo_steps_to_cache' => 50,
                'long_cache_min_cacheable_distance' => 30,
                'cache_max_connect_to_cache_steps_multiplier' => 100,
                'cache_accept_path_start_distance_ratio' => 0.2,
                'cache_accept_path_end_distance_ratio' => 0.15,
                'negative_cache_accept_path_start_distance_ratio' => 0.3,
                'negative_cache_accept_path_end_distance_ratio' => 0.3,
                'cache_path_start_distance_rating_multiplier' => 10,
                'cache_path_end_distance_rating_multiplier' => 20,
                'stale_enemy_with_same_destination_collision_penalty' => 30,
                'ignore_moving_enemy_collision_distance' => 5,
                'enemy_with_different_destination_collision_penalty' => 30,
                'general_entity_collision_penalty' => 10,
                'general_entity_subsequent_collision_penalty' => 3,
                'extended_collision_penalty' => 3,
                'max_clients_to_accept_any_new_request' => 10,
                'max_clients_to_accept_short_new_request' => 100,
                'direct_distance_to_consider_short_request' => 100,
                'short_request_max_steps' => 1000,
                'short_request_ratio' => 0.5,
                'min_steps_to_check_path_find_termination' => 2000,
                'start_to_goal_cost_multiplier_to_terminate_path_find' => 2000.0,
                'overload_levels' => [0, 100, 500],
                'overload_multipliers' => [2, 3, 4],
                'negative_path_cache_delay_interval' => 20,
            ],
            'asteroids' => [
                'spawning_rate' => 1,
                'max_ray_portals_expanded_per_tick' => 100,
            ],
            'max_failed_behavior_count' => 3,
        ];

        file_put_contents(
            $profilesPath.'/map-settings.json',
            $this->configRenderer->render('factorio/map-settings.json', $config),
        );
    }

    /**
     * Create an initial save file if none exists yet.
     */
    protected function ensureSaveExists(Server $server, string $profilesPath): void
    {
        $savesDir = $profilesPath.'/saves';

        if (! is_dir($savesDir)) {
            mkdir($savesDir, 0755, true);
        }

        $savePath = $savesDir.'/save.zip';

        if (file_exists($savePath)) {
            return;
        }

        Log::info("{$server->logContext()} Creating initial Factorio save file");

        $result = Process::timeout(120)->run([
            $this->getBinaryPath($server),
            '--config', $profilesPath.'/config.ini',
            '--create', $savePath,
            '--map-gen-settings', $profilesPath.'/map-gen-settings.json',
            '--map-settings', $profilesPath.'/map-settings.json',
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to create Factorio save: '.($result->errorOutput() ?: $result->output()));
        }

        Log::info("{$server->logContext()} Save file created at {$savePath}");
    }

    /**
     * Build default settings when no FactorioSettings record exists.
     */
    protected function getDefaultSettings(): FactorioSettings
    {
        return new FactorioSettings;
    }

    /**
     * Build an autoplace control array for the given resource from settings.
     *
     * @return array{frequency: float, size: float, richness: float}
     */
    protected function buildAutoplaceControl(FactorioSettings $settings, string $resource): array
    {
        return [
            'frequency' => $this->scaleToMultiplier($settings->{"{$resource}_frequency"} ?? 'normal'),
            'size' => $this->scaleToMultiplier($settings->{"{$resource}_size"} ?? 'normal'),
            'richness' => $this->scaleToMultiplier($settings->{"{$resource}_richness"} ?? 'normal'),
        ];
    }

    /**
     * Convert a named scale value to the Factorio numeric multiplier.
     *
     * Factorio uses: 0 (none), 1/6 (very-low), 1/2 (low), 1 (normal), 2 (high), 6 (very-high).
     */
    protected function scaleToMultiplier(string $scale): float
    {
        return match ($scale) {
            'none' => 0,
            'very-low', 'very-small' => 1 / 6,
            'low', 'small' => 0.5,
            'normal' => 1,
            'high', 'big' => 2,
            'very-high', 'very-big' => 6,
            default => 1,
        };
    }
}
