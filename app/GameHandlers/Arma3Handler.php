<?php

namespace App\GameHandlers;

use App\Attributes\HandlesGame;
use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Contracts\ManagesModAssets;
use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\Enums\GameType;
use App\Models\DifficultySettings;
use App\Models\NetworkSettings;
use App\Models\Server;
use App\Services\Renderer\TwigConfigRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

#[HandlesGame(GameType::Arma3)]
final class Arma3Handler implements DetectsServerState, GameHandler, ManagesModAssets, SupportsBackups, SupportsHeadlessClients, SupportsMissions
{
    public function __construct(
        protected TwigConfigRenderer $configRenderer,
    ) {}

    public function gameType(): GameType
    {
        return GameType::Arma3;
    }

    public function serverAppId(): int
    {
        return 233780;
    }

    public function gameId(): int
    {
        return 107410;
    }

    public function defaultPort(): int
    {
        return 2302;
    }

    public function defaultQueryPort(): int
    {
        return 2303;
    }

    public function branches(): array
    {
        return ['public', 'contact', 'creatordlc', 'profiling', 'performance', 'legacy'];
    }

    public function supportsWorkshopMods(): bool
    {
        return true;
    }

    public function requiresLowercaseConversion(): bool
    {
        return true;
    }

    public function buildLaunchCommand(Server $server): array
    {
        $binary = $this->getBinaryPath($server);
        $params = [
            $binary,
            '-port='.$server->port,
            '-name='.$this->getProfileName($server),
            '-profiles='.$server->getProfilesPath(),
            '-config='.$server->getProfilesPath().'/server.cfg',
            '-cfg='.$server->getProfilesPath().'/server_basic.cfg',
            '-nosplash',
            '-skipIntro',
            '-world=empty',
        ];

        foreach ($this->getModNames($server) as $modName) {
            $params[] = '-mod='.$modName;
        }

        if ($server->additional_params) {
            $additionalArgs = preg_split('/\s+/', trim($server->additional_params), -1, PREG_SPLIT_NO_EMPTY);
            array_push($params, ...$additionalArgs);
        }

        return $params;
    }

    public function generateConfigFiles(Server $server): void
    {
        $this->generateServerConfig($server);
        $this->generateBasicConfig($server);
        $this->generateProfileConfig($server);
    }

    public function getBinaryPath(Server $server): string
    {
        return $server->gameInstall->getInstallationPath().'/arma3server_x64';
    }

    public function getProfileName(Server $server): string
    {
        return 'arma3_'.$server->id;
    }

    public function getServerLogPath(Server $server): string
    {
        return $server->getProfilesPath().'/server.log';
    }

    // --- DetectsServerState ---

    public function getBootDetectionStrings(): array
    {
        return ['Connected to Steam servers'];
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

    // --- ManagesModAssets ---

    public function symlinkMods(Server $server): void
    {
        $preset = $server->activePreset;

        if (! $preset) {
            return;
        }

        $gameInstallPath = $server->gameInstall->getInstallationPath();

        // Remove existing mod symlinks (anything starting with @)
        $existingLinks = glob($gameInstallPath.'/@*') ?: [];
        foreach ($existingLinks as $link) {
            if (is_link($link)) {
                unlink($link);
            }
        }

        foreach ($preset->mods as $mod) {
            $modInstallPath = $mod->getInstallationPath();

            if (! is_dir($modInstallPath)) {
                Log::warning("[Server:{$server->id}] Mod '{$mod->name}' (ID {$mod->id}) directory not found at {$modInstallPath}, skipping symlink");

                continue;
            }

            $linkPath = $gameInstallPath.'/'.$mod->getNormalizedName();

            if (! file_exists($linkPath)) {
                symlink($modInstallPath, $linkPath);
                Log::info("[Server:{$server->id}] Symlinked mod {$mod->getNormalizedName()} -> {$modInstallPath}");
            }
        }
    }

    public function copyBiKeys(Server $server): void
    {
        $preset = $server->activePreset;

        if (! $preset) {
            return;
        }

        $keysPath = $server->gameInstall->getInstallationPath().'/keys';

        if (! is_dir($keysPath)) {
            mkdir($keysPath, 0755, true);
        }

        foreach ($preset->mods as $mod) {
            $modKeysPath = $mod->getInstallationPath().'/keys';

            if (! is_dir($modKeysPath)) {
                continue;
            }

            foreach (glob($modKeysPath.'/*.bikey') ?: [] as $bikeyFile) {
                $destPath = $keysPath.'/'.basename($bikeyFile);

                if (is_link($destPath) && ! file_exists($destPath)) {
                    unlink($destPath);
                }

                if (! file_exists($destPath)) {
                    symlink($bikeyFile, $destPath);
                    Log::info("[Server:{$server->id}] Symlinked BiKey ".basename($bikeyFile)." from mod '{$mod->name}'");
                }
            }
        }
    }

    // --- SupportsMissions ---

    public function symlinkMissions(Server $server): void
    {
        $missionsPath = config('arma.missions_base_path');
        $mpmissionsPath = $server->gameInstall->getInstallationPath().'/mpmissions';

        if (! is_dir($missionsPath)) {
            return;
        }

        if (! is_dir($mpmissionsPath)) {
            mkdir($mpmissionsPath, 0755, true);
        }

        $existingLinks = glob($mpmissionsPath.'/*.pbo') ?: [];
        foreach ($existingLinks as $link) {
            if (is_link($link)) {
                unlink($link);
            }
        }

        $pboFiles = glob($missionsPath.'/*.pbo') ?: [];
        foreach ($pboFiles as $pboFile) {
            $linkPath = $mpmissionsPath.'/'.basename($pboFile);
            symlink($pboFile, $linkPath);
        }
    }

    // --- SupportsHeadlessClients ---

    public function buildHeadlessClientCommand(Server $server, int $index): array
    {
        $binary = $this->getBinaryPath($server);

        $params = [
            $binary,
            '-client',
            '-connect=127.0.0.1',
            '-port='.$server->port,
            '-nosound',
            '-nosplash',
            '-skipIntro',
            '-world=empty',
        ];

        if ($server->password) {
            $params[] = '-password='.$server->password;
        }

        foreach ($this->getModNames($server) as $modName) {
            $params[] = '-mod='.$modName;
        }

        return $params;
    }

    // --- SupportsBackups ---

    public function getBackupFilePath(Server $server): string
    {
        $profileName = $this->getProfileName($server);

        return $server->getProfilesPath().'/home/'.$profileName.'/'.$profileName.'.vars.Arma3Profile';
    }

    public function getBackupDownloadFilename(Server $server): string
    {
        return 'arma3_'.$server->id.'.vars.Arma3Profile';
    }

    // --- UI Schema ---

    public function settingsSchema(): array
    {
        $neverLimitedAlways = [
            ['value' => '0', 'label' => 'Never'],
            ['value' => '1', 'label' => 'Limited'],
            ['value' => '2', 'label' => 'Always'],
        ];

        $neverFadeAlways = [
            ['value' => '0', 'label' => 'Never'],
            ['value' => '1', 'label' => 'Fade'],
            ['value' => '2', 'label' => 'Always'],
        ];

        return [
            // --- Server Rules ---
            [
                'title' => 'Server Rules',
                'showOnCreate' => true,
                'createLabel' => 'Arma 3 Options',
                'source' => 'server',
                'fields' => [
                    ['key' => 'verify_signatures', 'label' => 'Verify Signatures', 'type' => 'toggle', 'default' => true],
                    ['key' => 'allowed_file_patching', 'label' => 'Allow File Patching', 'type' => 'toggle', 'default' => false],
                    ['key' => 'battle_eye', 'label' => 'BattlEye Anti-Cheat', 'type' => 'toggle', 'default' => true],
                    ['key' => 'von_enabled', 'label' => 'Voice Over Network', 'type' => 'toggle', 'default' => true],
                    ['key' => 'persistent', 'label' => 'Persistent Server', 'type' => 'toggle', 'default' => false],
                    ['key' => 'auto_restart', 'label' => 'Auto-Restart on Crash', 'type' => 'toggle', 'default' => false],
                ],
            ],

            // --- Difficulty Settings ---
            [
                'title' => 'Difficulty Settings',
                'description' => 'HUD elements, third-person view, AI behavior, and gameplay options.',
                'collapsible' => true,
                'source' => 'difficulty_settings',
                'layout' => 'columns',
                'groups' => [
                    // Column 1: Boolean toggles
                    [
                        'fields' => [
                            ['key' => 'reduced_damage', 'label' => 'Reduced damage', 'type' => 'toggle', 'default' => false],
                            ['key' => 'stamina_bar', 'label' => 'Stamina bar', 'type' => 'toggle', 'default' => true],
                            ['key' => 'weapon_crosshair', 'label' => 'Weapon crosshair', 'type' => 'toggle', 'default' => true],
                            ['key' => 'vision_aid', 'label' => 'Vision aid', 'type' => 'toggle', 'default' => false],
                            ['key' => 'camera_shake', 'label' => 'Camera shake', 'type' => 'toggle', 'default' => true],
                            ['key' => 'score_table', 'label' => 'Score table', 'type' => 'toggle', 'default' => true],
                            ['key' => 'death_messages', 'label' => 'Killed by', 'type' => 'toggle', 'default' => true],
                            ['key' => 'von_id', 'label' => 'VON ID', 'type' => 'toggle', 'default' => true],
                            ['key' => 'map_content', 'label' => 'Extended map content', 'type' => 'toggle', 'default' => true],
                            ['key' => 'auto_report', 'label' => 'Auto report', 'type' => 'toggle', 'default' => false],
                        ],
                    ],
                    // Column 2: Situational awareness + AI
                    [
                        'fields' => [
                            ['key' => 'group_indicators', 'label' => 'Group indicators', 'type' => 'segmented', 'default' => 2, 'options' => $neverLimitedAlways],
                            ['key' => 'friendly_tags', 'label' => 'Friendly tags', 'type' => 'segmented', 'default' => 2, 'options' => $neverLimitedAlways],
                            ['key' => 'enemy_tags', 'label' => 'Enemy tags', 'type' => 'segmented', 'default' => 0, 'options' => $neverLimitedAlways],
                            ['key' => 'detected_mines', 'label' => 'Detected mines', 'type' => 'segmented', 'default' => 2, 'options' => $neverLimitedAlways],
                            ['type' => 'separator'],
                            ['key' => 'ai_level_preset', 'label' => 'AI level preset', 'type' => 'segmented', 'default' => 3, 'options' => [
                                ['value' => '0', 'label' => 'Low'],
                                ['value' => '1', 'label' => 'Normal'],
                                ['value' => '2', 'label' => 'High'],
                                ['value' => '3', 'label' => 'Custom'],
                            ]],
                            ['key' => 'skill_ai', 'label' => 'AI Skill', 'type' => 'number', 'default' => '0.50', 'min' => 0, 'max' => 1, 'step' => 0.05, 'storeAsString' => true, 'halfWidth' => true],
                            ['key' => 'precision_ai', 'label' => 'AI Precision', 'type' => 'number', 'default' => '0.50', 'min' => 0, 'max' => 1, 'step' => 0.05, 'storeAsString' => true, 'halfWidth' => true],
                        ],
                    ],
                    // Column 3: HUD & view settings
                    [
                        'fields' => [
                            ['key' => 'commands', 'label' => 'Commands', 'type' => 'segmented', 'default' => 2, 'options' => $neverFadeAlways],
                            ['key' => 'waypoints', 'label' => 'Waypoints', 'type' => 'segmented', 'default' => 2, 'options' => $neverFadeAlways],
                            ['key' => 'weapon_info', 'label' => 'Weapon info', 'type' => 'segmented', 'default' => 2, 'options' => $neverFadeAlways],
                            ['key' => 'stance_indicator', 'label' => 'Stance indicator', 'type' => 'segmented', 'default' => 2, 'options' => $neverFadeAlways],
                            ['key' => 'third_person_view', 'label' => 'Third person view', 'type' => 'segmented', 'default' => 1, 'options' => [
                                ['value' => '0', 'label' => 'Disabled'],
                                ['value' => '1', 'label' => 'Enabled'],
                                ['value' => '2', 'label' => 'Vehicles'],
                            ]],
                            ['key' => 'tactical_ping', 'label' => 'Tactical ping', 'type' => 'segmented', 'default' => 3, 'options' => [
                                ['value' => '0', 'label' => 'Off'],
                                ['value' => '1', 'label' => '3D'],
                                ['value' => '2', 'label' => 'Map'],
                                ['value' => '3', 'label' => 'Both'],
                            ]],
                        ],
                    ],
                ],
            ],

            // --- Network Settings ---
            [
                'title' => 'Network Settings',
                'description' => 'Bandwidth, packet sizes, terrain detail, and view distance tuning for server_basic.cfg.',
                'collapsible' => true,
                'source' => 'network_settings',
                'layout' => 'rows',
                'presets' => [
                    [
                        'label' => 'Reset to Default',
                        'variant' => 'ghost',
                        'icon' => 'reset',
                        'values' => [
                            'max_msg_send' => 128,
                            'max_size_guaranteed' => 512,
                            'max_size_nonguaranteed' => 256,
                            'min_bandwidth' => '131072',
                            'max_bandwidth' => '10000000000',
                            'min_error_to_send' => '0.0010',
                            'min_error_to_send_near' => '0.0100',
                            'max_packet_size' => 1400,
                            'max_custom_file_size' => 0,
                            'terrain_grid' => '25.0000',
                            'view_distance' => 0,
                        ],
                    ],
                    [
                        'label' => 'Apply High Performance',
                        'variant' => 'default',
                        'icon' => 'zap',
                        'values' => [
                            'max_msg_send' => 2048,
                            'max_size_guaranteed' => 512,
                            'max_size_nonguaranteed' => 256,
                            'min_bandwidth' => '5120000',
                            'max_bandwidth' => '104857600',
                            'min_error_to_send' => '0.0010',
                            'min_error_to_send_near' => '0.0100',
                            'max_packet_size' => 1400,
                            'max_custom_file_size' => 0,
                            'terrain_grid' => '3.1250',
                            'view_distance' => 0,
                        ],
                    ],
                ],
                'groups' => [
                    [
                        'columns' => 3,
                        'fields' => [
                            ['key' => 'max_msg_send', 'label' => 'MaxMsgSend', 'type' => 'number', 'default' => 128, 'min' => 1, 'max' => 10000, 'description' => 'Max packets per simulation cycle. Default: 128, high-perf: 2048.'],
                            ['key' => 'max_size_guaranteed', 'label' => 'MaxSizeGuaranteed', 'type' => 'number', 'default' => 512, 'min' => 1, 'max' => 4096, 'description' => 'Max guaranteed packet payload (bytes). Used for non-repetitive events. Default: 512.'],
                            ['key' => 'max_size_nonguaranteed', 'label' => 'MaxSizeNonguaranteed', 'type' => 'number', 'default' => 256, 'min' => 1, 'max' => 4096, 'description' => 'Max non-guaranteed packet payload (bytes). Used for position updates. Default: 256.'],
                        ],
                    ],
                    [
                        'columns' => 2,
                        'fields' => [
                            ['key' => 'min_bandwidth', 'label' => 'MinBandwidth', 'type' => 'text', 'default' => '131072', 'inputMode' => 'decimal', 'storeAsString' => true, 'description' => 'Guaranteed bandwidth (bps). Default: 131072, high-perf: 5120000.'],
                            ['key' => 'max_bandwidth', 'label' => 'MaxBandwidth', 'type' => 'text', 'default' => '10000000000', 'inputMode' => 'decimal', 'storeAsString' => true, 'description' => 'Max bandwidth cap (bps). High-perf: 104857600 (100 Mbps).'],
                        ],
                    ],
                    [
                        'columns' => 2,
                        'fields' => [
                            ['key' => 'min_error_to_send', 'label' => 'MinErrorToSend', 'type' => 'text', 'default' => '0.0010', 'inputMode' => 'decimal', 'storeAsString' => true, 'description' => 'Min error for distant unit updates. Smaller = smoother optics. Default: 0.001.'],
                            ['key' => 'min_error_to_send_near', 'label' => 'MinErrorToSendNear', 'type' => 'text', 'default' => '0.0100', 'inputMode' => 'decimal', 'storeAsString' => true, 'description' => 'Min error for near unit updates. Too large causes warping. Default: 0.01.'],
                        ],
                    ],
                    [
                        'columns' => 3,
                        'fields' => [
                            ['key' => 'max_packet_size', 'label' => 'MaxPacketSize', 'type' => 'number', 'default' => 1400, 'min' => 256, 'max' => 1500, 'description' => 'Max network packet size. Only change if router enforces lower. Default: 1400.'],
                            ['key' => 'max_custom_file_size', 'label' => 'MaxCustomFileSize', 'type' => 'number', 'default' => 0, 'min' => 0, 'description' => 'Users with custom face/sound larger than this (bytes) are kicked. 0 = no limit.'],
                            ['key' => 'view_distance', 'label' => 'View Distance', 'type' => 'number', 'default' => 0, 'min' => 0, 'description' => 'Server-side view distance override (meters). 0 = mission default.'],
                        ],
                    ],
                    [
                        'fields' => [
                            ['key' => 'terrain_grid', 'label' => 'Terrain Grid', 'type' => 'text', 'default' => '25.0000', 'inputMode' => 'decimal', 'storeAsString' => true, 'description' => 'Server-side terrain resolution. 25 = low detail, 3.125 = high detail. Default: 25.'],
                        ],
                    ],
                ],
            ],

            // --- Advanced ---
            [
                'advanced' => true,
                'fields' => [
                    ['key' => 'additional_params', 'label' => 'Additional Launch Parameters', 'type' => 'textarea', 'default' => '', 'rows' => 2, 'placeholder' => '-loadMissionToMemory -enableHT', 'source' => 'server'],
                    ['key' => 'additional_server_options', 'label' => 'Additional server.cfg Options', 'type' => 'textarea', 'default' => '', 'rows' => 3, 'placeholder' => 'Raw config directives appended to server.cfg'],
                ],
            ],
        ];
    }

    // --- DetectsServerState: Auto-Restart ---

    public function shouldAutoRestart(Server $server): bool
    {
        return (bool) $server->auto_restart;
    }

    // --- Validation ---

    public function serverValidationRules(?Server $server = null): array
    {
        return [
            'query_port' => [
                'required', 'integer', 'min:1', 'max:65535',
                Rule::unique('servers', 'query_port')->when($server, fn ($rule) => $rule->ignore($server->id)),
                Rule::unique('servers', 'port')->when($server, fn ($rule) => $rule->ignore($server->id)),
            ],
            'password' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
            'auto_restart' => ['boolean'],
            'verify_signatures' => ['boolean'],
            'allowed_file_patching' => ['boolean'],
            'battle_eye' => ['boolean'],
            'persistent' => ['boolean'],
            'von_enabled' => ['boolean'],
            'additional_params' => ['nullable', 'string', 'max:1000'],
            'additional_server_options' => ['nullable', 'string'],
        ];
    }

    public function settingsValidationRules(): array
    {
        return [
            // Difficulty settings
            'reduced_damage' => ['boolean'],
            'group_indicators' => ['integer', 'between:0,2'],
            'friendly_tags' => ['integer', 'between:0,2'],
            'enemy_tags' => ['integer', 'between:0,2'],
            'detected_mines' => ['integer', 'between:0,2'],
            'commands' => ['integer', 'between:0,2'],
            'waypoints' => ['integer', 'between:0,2'],
            'tactical_ping' => ['integer', 'between:0,3'],
            'weapon_info' => ['integer', 'between:0,2'],
            'stance_indicator' => ['integer', 'between:0,2'],
            'stamina_bar' => ['boolean'],
            'weapon_crosshair' => ['boolean'],
            'vision_aid' => ['boolean'],
            'third_person_view' => ['integer', 'between:0,2'],
            'camera_shake' => ['boolean'],
            'score_table' => ['boolean'],
            'death_messages' => ['boolean'],
            'von_id' => ['boolean'],
            'map_content' => ['boolean'],
            'auto_report' => ['boolean'],
            'ai_level_preset' => ['integer', 'between:0,3'],
            'skill_ai' => ['numeric', 'between:0,1'],
            'precision_ai' => ['numeric', 'between:0,1'],

            // Network settings
            'max_msg_send' => ['nullable', 'integer', 'min:1'],
            'max_size_guaranteed' => ['nullable', 'integer', 'min:1'],
            'max_size_nonguaranteed' => ['nullable', 'integer', 'min:1'],
            'min_bandwidth' => ['nullable', 'integer', 'min:0'],
            'max_bandwidth' => ['nullable', 'integer', 'min:0'],
            'min_error_to_send' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'min_error_to_send_near' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'max_packet_size' => ['nullable', 'integer', 'min:256'],
            'max_custom_file_size' => ['nullable', 'integer', 'min:0'],
            'terrain_grid' => ['nullable', 'numeric', 'min:0'],
            'view_distance' => ['nullable', 'integer', 'min:0'],
        ];
    }

    // --- Related Settings ---

    public function createRelatedSettings(Server $server): void
    {
        DifficultySettings::query()->create(['server_id' => $server->id]);
        NetworkSettings::query()->create(['server_id' => $server->id]);
    }

    public function updateRelatedSettings(Server $server, array $validated): void
    {
        $difficultyFields = collect($validated)->only(
            (new DifficultySettings)->getFillable()
        )->except('server_id')->toArray();

        $networkFields = collect($validated)->only(
            (new NetworkSettings)->getFillable()
        )->except('server_id')->toArray();

        if (! empty($difficultyFields)) {
            $server->difficultySettings()->updateOrCreate(
                ['server_id' => $server->id],
                $difficultyFields,
            );
        }

        if (! empty($networkFields)) {
            $server->networkSettings()->updateOrCreate(
                ['server_id' => $server->id],
                $networkFields,
            );
        }
    }

    /**
     * Get the list of normalized mod directory names from the server's active preset.
     *
     * @return array<int, string>
     */
    protected function getModNames(Server $server): array
    {
        $preset = $server->activePreset;

        if (! $preset) {
            return [];
        }

        return $preset->mods->map(fn ($mod) => $mod->getNormalizedName())->all();
    }

    /**
     * Generate and write server.cfg to the profiles directory.
     */
    protected function generateServerConfig(Server $server): void
    {
        $renderer = $this->configRenderer;

        $content = $renderer->render('arma3/server.cfg.twig', [
            'hostname' => addslashes($server->name),
            'password' => addslashes((string) $server->password),
            'admin_password' => addslashes((string) $server->admin_password),
            'max_players' => (int) $server->max_players,
            'verify_signatures' => $server->verify_signatures ? 2 : 0,
            'allowed_file_patching' => $server->allowed_file_patching ? 2 : 0,
            'disable_von' => $server->von_enabled ? 0 : 1,
            'persistent' => $server->persistent ? 1 : 0,
            'battle_eye' => $server->battle_eye ? 1 : 0,
            'motd_lines' => $server->description
                ? array_map(fn (string $line) => addslashes(trim($line)), explode("\n", $server->description))
                : null,
            'additional_server_options' => $server->additional_server_options ?: null,
        ]);

        file_put_contents(
            $server->getProfilesPath().'/server.cfg',
            $content
        );
    }

    /**
     * Generate and write server_basic.cfg (network tuning) to the profiles directory.
     */
    protected function generateBasicConfig(Server $server): void
    {
        $renderer = $this->configRenderer;
        $settings = $server->networkSettings ?? $this->getDefaultNetworkSettings();

        $content = $renderer->render('arma3/server_basic.cfg.twig', [
            'max_msg_send' => $settings->max_msg_send,
            'max_size_guaranteed' => $settings->max_size_guaranteed,
            'max_size_nonguaranteed' => $settings->max_size_nonguaranteed,
            'min_bandwidth' => $settings->min_bandwidth,
            'max_bandwidth' => $settings->max_bandwidth,
            'min_error_to_send' => $settings->min_error_to_send,
            'min_error_to_send_near' => $settings->min_error_to_send_near,
            'max_custom_file_size' => $settings->max_custom_file_size,
            'max_packet_size' => $settings->max_packet_size,
            'view_distance' => (int) $settings->view_distance,
            'terrain_grid' => (float) $settings->terrain_grid,
        ]);

        file_put_contents(
            $server->getProfilesPath().'/server_basic.cfg',
            $content
        );
    }

    /**
     * Generate the .Arma3Profile file with difficulty and AI settings.
     */
    protected function generateProfileConfig(Server $server): void
    {
        $renderer = $this->configRenderer;
        $settings = $server->difficultySettings ?? $this->getDefaultDifficultySettings();
        $profileName = $this->getProfileName($server);
        $profileDir = $server->getProfilesPath().'/home/'.$profileName;

        if (! is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }

        $content = $renderer->render('arma3/profile.twig', [
            'reduced_damage' => $settings->reduced_damage ? 1 : 0,
            'group_indicators' => $settings->group_indicators,
            'friendly_tags' => $settings->friendly_tags,
            'enemy_tags' => $settings->enemy_tags,
            'detected_mines' => $settings->detected_mines,
            'commands' => $settings->commands,
            'waypoints' => $settings->waypoints,
            'tactical_ping' => $settings->tactical_ping,
            'weapon_info' => $settings->weapon_info,
            'stance_indicator' => $settings->stance_indicator,
            'stamina_bar' => $settings->stamina_bar ? 1 : 0,
            'weapon_crosshair' => $settings->weapon_crosshair ? 1 : 0,
            'vision_aid' => $settings->vision_aid ? 1 : 0,
            'third_person_view' => $settings->third_person_view,
            'camera_shake' => $settings->camera_shake ? 1 : 0,
            'score_table' => $settings->score_table ? 1 : 0,
            'death_messages' => $settings->death_messages ? 1 : 0,
            'von_id' => $settings->von_id ? 1 : 0,
            'map_content' => $settings->map_content ? 1 : 0,
            'auto_report' => $settings->auto_report ? 1 : 0,
            'ai_level_preset' => $settings->ai_level_preset,
            'skill_ai' => $settings->skill_ai,
            'precision_ai' => $settings->precision_ai,
        ]);

        file_put_contents(
            $profileDir.'/'.$profileName.'.Arma3Profile',
            $content
        );
    }

    /**
     * Build a default NetworkSettings object for servers without custom network settings.
     */
    protected function getDefaultNetworkSettings(): NetworkSettings
    {
        $settings = new NetworkSettings;
        $settings->max_msg_send = 128;
        $settings->max_size_guaranteed = 512;
        $settings->max_size_nonguaranteed = 256;
        $settings->min_bandwidth = 131072;
        $settings->max_bandwidth = 10000000000;
        $settings->min_error_to_send = 0.001;
        $settings->min_error_to_send_near = 0.01;
        $settings->max_packet_size = 1400;
        $settings->max_custom_file_size = 0;
        $settings->terrain_grid = 25.0;
        $settings->view_distance = 0;

        return $settings;
    }

    /**
     * Build a default DifficultySettings object for servers without custom difficulty settings.
     */
    protected function getDefaultDifficultySettings(): DifficultySettings
    {
        $settings = new DifficultySettings;
        $settings->reduced_damage = false;
        $settings->group_indicators = 2;
        $settings->friendly_tags = 2;
        $settings->enemy_tags = 0;
        $settings->detected_mines = 2;
        $settings->commands = 2;
        $settings->waypoints = 2;
        $settings->tactical_ping = 3;
        $settings->weapon_info = 2;
        $settings->stance_indicator = 2;
        $settings->stamina_bar = true;
        $settings->weapon_crosshair = true;
        $settings->vision_aid = false;
        $settings->third_person_view = 1;
        $settings->camera_shake = true;
        $settings->score_table = true;
        $settings->death_messages = true;
        $settings->von_id = true;
        $settings->map_content = true;
        $settings->auto_report = false;
        $settings->ai_level_preset = 1;
        $settings->skill_ai = 0.50;
        $settings->precision_ai = 0.50;

        return $settings;
    }

    /**
     * Format a decimal value for config output, stripping unnecessary trailing zeros.
     */
    protected function formatDecimal(string|float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }
}
