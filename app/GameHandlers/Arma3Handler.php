<?php

namespace App\GameHandlers;

use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\Models\DifficultySettings;
use App\Models\NetworkSettings;
use App\Models\Server;
use Illuminate\Support\Facades\Log;

class Arma3Handler implements GameHandler
{
    public function gameType(): GameType
    {
        return GameType::Arma3;
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

    public function getBootDetectionString(): ?string
    {
        return 'Connected to Steam servers';
    }

    public function getModDownloadStartedString(): ?string
    {
        return null;
    }

    public function getModDownloadFinishedString(): ?string
    {
        return null;
    }

    public function getCrashDetectionString(): ?string
    {
        return null;
    }

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

                if (! file_exists($destPath)) {
                    symlink($bikeyFile, $destPath);
                    Log::info("[Server:{$server->id}] Symlinked BiKey ".basename($bikeyFile)." from mod '{$mod->name}'");
                }
            }
        }
    }

    public function supportsHeadlessClients(): bool
    {
        return true;
    }

    public function buildHeadlessClientCommand(Server $server, int $index): ?array
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

    public function getBackupFilePath(Server $server): ?string
    {
        $profileName = $this->getProfileName($server);

        return $server->getProfilesPath().'/home/'.$profileName.'/'.$profileName.'.vars.Arma3Profile';
    }

    public function getBackupDownloadFilename(Server $server): string
    {
        return 'arma3_'.$server->id.'.vars.Arma3Profile';
    }

    public function serverValidationRules(): array
    {
        return [
            'verify_signatures' => ['boolean'],
            'allowed_file_patching' => ['boolean'],
            'battle_eye' => ['boolean'],
            'persistent' => ['boolean'],
            'von_enabled' => ['boolean'],
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
        $lines = [];

        $lines[] = '// GLOBAL SETTINGS';
        $lines[] = 'hostname = "'.addslashes($server->name).'";';
        $lines[] = 'password = "'.addslashes((string) $server->password).'";';
        $lines[] = 'passwordAdmin = "'.addslashes((string) $server->admin_password).'";';
        $lines[] = '';
        $lines[] = '// JOINING RULES';
        $lines[] = 'maxPlayers = '.(int) $server->max_players.';';
        $lines[] = 'kickDuplicate = 1;';
        $lines[] = 'verifySignatures = '.($server->verify_signatures ? '2' : '0').';';
        $lines[] = 'allowedFilePatching = '.($server->allowed_file_patching ? '2' : '0').';';
        $lines[] = '';
        $lines[] = '// INGAME SETTINGS';
        $lines[] = 'disableVoN = '.($server->von_enabled ? '0' : '1').';';
        $lines[] = 'vonCodec = 1;';
        $lines[] = 'vonCodecQuality = 30;';
        $lines[] = 'persistent = '.($server->persistent ? '1' : '0').';';
        $lines[] = 'timeStampFormat = "short";';
        $lines[] = 'BattlEye = '.($server->battle_eye ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '// SIGNATURE VERIFICATION';
        $lines[] = 'onUnsignedData = "kick (_this select 0)";';
        $lines[] = 'onHackedData = "kick (_this select 0)";';
        $lines[] = 'onDifferentData = "";';
        $lines[] = '';
        $lines[] = '// HEADLESS CLIENT';
        $lines[] = 'headlessClients[] = {"127.0.0.1"};';
        $lines[] = 'localClient[] = {"127.0.0.1"};';

        if ($server->description) {
            $lines[] = '';
            $lines[] = '// MOTD';
            $motdLines = explode("\n", $server->description);
            $lines[] = 'motd[] = {';
            $motdEntries = array_map(
                fn (string $line) => '    "'.addslashes(trim($line)).'"',
                $motdLines
            );
            $lines[] = implode(",\n", $motdEntries);
            $lines[] = '};';
        }

        if ($server->additional_server_options) {
            $lines[] = '';
            $lines[] = '// ADDITIONAL OPTIONS';
            $lines[] = $server->additional_server_options;
        }

        file_put_contents(
            $server->getProfilesPath().'/server.cfg',
            implode("\n", $lines)."\n"
        );
    }

    /**
     * Generate and write server_basic.cfg (network tuning) to the profiles directory.
     */
    protected function generateBasicConfig(Server $server): void
    {
        $settings = $server->networkSettings ?? $this->getDefaultNetworkSettings();

        $lines = [];

        $lines[] = '// BASIC NETWORK CONFIGURATION';
        $lines[] = 'MaxMsgSend = '.$settings->max_msg_send.';';
        $lines[] = 'MaxSizeGuaranteed = '.$settings->max_size_guaranteed.';';
        $lines[] = 'MaxSizeNonguaranteed = '.$settings->max_size_nonguaranteed.';';
        $lines[] = 'MinBandwidth = '.$settings->min_bandwidth.';';
        $lines[] = 'MaxBandwidth = '.$settings->max_bandwidth.';';
        $lines[] = 'MinErrorToSend = '.$this->formatDecimal($settings->min_error_to_send).';';
        $lines[] = 'MinErrorToSendNear = '.$this->formatDecimal($settings->min_error_to_send_near).';';
        $lines[] = 'MaxCustomFileSize = '.$settings->max_custom_file_size.';';
        $lines[] = '';
        $lines[] = 'class sockets {';
        $lines[] = '    maxPacketSize = '.$settings->max_packet_size.';';
        $lines[] = '};';

        if ((int) $settings->view_distance > 0) {
            $lines[] = '';
            $lines[] = '// SERVER VIEW DISTANCE';
            $lines[] = 'viewDistance = '.$settings->view_distance.';';
        }

        if ((float) $settings->terrain_grid > 0) {
            $lines[] = '';
            $lines[] = '// TERRAIN GRID';
            $lines[] = 'terrainGrid = '.$this->formatDecimal($settings->terrain_grid).';';
        }

        file_put_contents(
            $server->getProfilesPath().'/server_basic.cfg',
            implode("\n", $lines)."\n"
        );
    }

    /**
     * Generate the .Arma3Profile file with difficulty and AI settings.
     */
    protected function generateProfileConfig(Server $server): void
    {
        $settings = $server->difficultySettings ?? $this->getDefaultDifficultySettings();
        $profileName = $this->getProfileName($server);
        $profileDir = $server->getProfilesPath().'/home/'.$profileName;

        if (! is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }

        $lines = [];
        $lines[] = 'version=1;';
        $lines[] = 'blood=1;';
        $lines[] = 'singleVoice=0;';
        $lines[] = 'gamma=1;';
        $lines[] = 'brightness=1;';
        $lines[] = 'volumeCD=5;';
        $lines[] = 'volumeFX=5;';
        $lines[] = 'volumeSpeech=5;';
        $lines[] = 'volumeVoN=5;';
        $lines[] = 'soundEnableEAX=1;';
        $lines[] = 'soundEnableHW=0;';
        $lines[] = 'volumeMapDucking=1;';
        $lines[] = 'volumeUI=1;';
        $lines[] = 'class DifficultyPresets';
        $lines[] = '{';
        $lines[] = '    class CustomDifficulty';
        $lines[] = '    {';
        $lines[] = '        class Options';
        $lines[] = '        {';
        $lines[] = '            /* Simulation */';
        $lines[] = '            reducedDamage = '.($settings->reduced_damage ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '            /* Situational awareness */';
        $lines[] = '            groupIndicators = '.$settings->group_indicators.';';
        $lines[] = '            friendlyTags = '.$settings->friendly_tags.';';
        $lines[] = '            enemyTags = '.$settings->enemy_tags.';';
        $lines[] = '            detectedMines = '.$settings->detected_mines.';';
        $lines[] = '            commands = '.$settings->commands.';';
        $lines[] = '            waypoints = '.$settings->waypoints.';';
        $lines[] = '            tacticalPing = '.$settings->tactical_ping.';';
        $lines[] = '';
        $lines[] = '            /* Personal awareness */';
        $lines[] = '            weaponInfo = '.$settings->weapon_info.';';
        $lines[] = '            stanceIndicator = '.$settings->stance_indicator.';';
        $lines[] = '            staminaBar = '.($settings->stamina_bar ? '1' : '0').';';
        $lines[] = '            weaponCrosshair = '.($settings->weapon_crosshair ? '1' : '0').';';
        $lines[] = '            visionAid = '.($settings->vision_aid ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '            /* View */';
        $lines[] = '            thirdPersonView = '.$settings->third_person_view.';';
        $lines[] = '            cameraShake = '.($settings->camera_shake ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '            /* Multiplayer */';
        $lines[] = '            scoreTable = '.($settings->score_table ? '1' : '0').';';
        $lines[] = '            deathMessages = '.($settings->death_messages ? '1' : '0').';';
        $lines[] = '            vonID = '.($settings->von_id ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '            /* Misc */';
        $lines[] = '            mapContent = '.($settings->map_content ? '1' : '0').';';
        $lines[] = '            autoReport = '.($settings->auto_report ? '1' : '0').';';
        $lines[] = '            multipleSaves = 0;';
        $lines[] = '        };';
        $lines[] = '';
        $lines[] = '        aiLevelPreset = '.$settings->ai_level_preset.';';
        $lines[] = '    };';
        $lines[] = '';
        $lines[] = '    class CustomAILevel';
        $lines[] = '    {';
        $lines[] = '        skillAI = '.$settings->skill_ai.';';
        $lines[] = '        precisionAI = '.$settings->precision_ai.';';
        $lines[] = '    };';
        $lines[] = '};';

        file_put_contents(
            $profileDir.'/'.$profileName.'.Arma3Profile',
            implode("\n", $lines)."\n"
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
