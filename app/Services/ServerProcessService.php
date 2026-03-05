<?php

namespace App\Services;

use App\Enums\ServerStatus;
use App\Models\DifficultySettings;
use App\Models\Server;
use Illuminate\Support\Facades\Log;

class ServerProcessService
{
    /**
     * Start an Arma 3 server instance.
     */
    public function start(Server $server): void
    {
        $context = "[Server:{$server->id} '{$server->name}']";

        if ($this->isRunning($server)) {
            Log::info("{$context} Server is already running, skipping start");

            return;
        }

        $profilesPath = $server->getProfilesPath();

        if (! is_dir($profilesPath)) {
            mkdir($profilesPath, 0755, true);
        }

        $server->load('activePreset.mods');
        $this->symlinkMods($server);
        $this->symlinkMissions($server);
        $this->copyBiKeys($server);
        $this->generateServerConfig($server);
        $this->generateBasicConfig($server);
        $this->generateProfileConfig($server);

        $command = $this->buildLaunchCommand($server);
        $pidFile = $this->getPidFilePath($server);
        $logFile = $this->getServerLogPath($server);
        $binaryDir = $server->getBinaryPath();

        Log::info("{$context} Starting server from {$binaryDir}");
        Log::info("{$context} Launch command: {$command}");
        Log::info("{$context} Log file: {$logFile}");

        // Truncate/create log file before the server process.
        file_put_contents($logFile, '');
        // TODO: Re-enable log tailing once it no longer freezes `composer run dev`.
        // $this->startLogTail($server);

        // Start the server as a detached child process using proc_open.
        // The 'exec' prefix replaces the shell with the server binary so
        // the PID we capture IS the server process — signals target it directly.
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];

        $process = proc_open('exec '.$command, $descriptors, $pipes, $binaryDir);

        if (is_resource($process)) {
            $status = proc_get_status($process);
            $pid = $status['pid'];
            file_put_contents($pidFile, (string) $pid);
            Log::info("{$context} Process started with PID {$pid}");
        } else {
            Log::error("{$context} Failed to start server process");
        }
    }

    /**
     * Stop an Arma 3 server instance.
     */
    public function stop(Server $server): void
    {
        $context = "[Server:{$server->id} '{$server->name}']";

        // TODO: Re-enable log tailing once it no longer freezes `composer run dev`.
        // $this->stopLogTail($server);

        $pid = $this->getPid($server);

        if ($pid && $this->isProcessRunning($pid)) {
            Log::info("{$context} Stopping server (PID {$pid})");
            posix_kill($pid, SIGTERM);

            $waited = 0;
            while ($this->isProcessRunning($pid) && $waited < 15) {
                usleep(500000);
                $waited++;
            }

            if ($this->isProcessRunning($pid)) {
                Log::warning("{$context} Server did not stop gracefully, sending SIGKILL (PID {$pid})");
                posix_kill($pid, SIGKILL);
            }

            Log::info("{$context} Server stopped");
        } else {
            Log::info("{$context} Server was not running (no active PID)");
        }

        $this->cleanupPidFile($server);
    }

    /**
     * Restart an Arma 3 server instance.
     */
    public function restart(Server $server): void
    {
        $this->stop($server);
        sleep(2);
        $this->start($server);
    }

    /**
     * Check if a server instance is currently running.
     */
    public function isRunning(Server $server): bool
    {
        $pid = $this->getPid($server);

        return $pid && $this->isProcessRunning($pid);
    }

    /**
     * Get the current status of a server.
     *
     * Transitional states (Starting, Stopping) are trusted from the DB column.
     * Stable states are verified against the actual PID and corrected if needed.
     */
    public function getStatus(Server $server): ServerStatus
    {
        if (in_array($server->status, [ServerStatus::Starting, ServerStatus::Stopping])) {
            return $server->status;
        }

        $isRunning = $this->isRunning($server);
        $expected = $isRunning ? ServerStatus::Running : ServerStatus::Stopped;

        if ($server->status !== $expected) {
            $server->updateQuietly(['status' => $expected]);
        }

        return $expected;
    }

    /**
     * Start headless clients for the server.
     */
    public function startHeadlessClients(Server $server): void
    {
        $count = $server->headless_client_count;

        if ($count > 0) {
            Log::info("[Server:{$server->id} '{$server->name}'] Starting {$count} headless client(s)");
        }

        for ($i = 0; $i < $count; $i++) {
            $this->startHeadlessClient($server, $i);
        }
    }

    /**
     * Stop all headless clients for a server.
     */
    public function stopHeadlessClients(Server $server): void
    {
        $context = "[Server:{$server->id} '{$server->name}']";
        $count = $server->headless_client_count;

        for ($i = 0; $i < $count; $i++) {
            $pidFile = $this->getHcPidFilePath($server, $i);

            if (file_exists($pidFile)) {
                $pid = (int) file_get_contents($pidFile);

                if ($this->isProcessRunning($pid)) {
                    Log::info("{$context} Stopping headless client {$i} (PID {$pid})");
                    posix_kill($pid, SIGTERM);
                }

                @unlink($pidFile);
            }
        }
    }

    /**
     * Build the Arma 3 server launch command string.
     */
    public function buildLaunchCommand(Server $server): string
    {
        $binary = $server->getBinaryPath().'/arma3server_x64';
        $params = [];

        $params[] = '-port='.$server->port;
        $params[] = '-name=arma3_'.$server->id;
        $params[] = '-profiles='.$server->getProfilesPath();
        $params[] = '-config='.$server->getProfilesPath().'/server.cfg';
        $params[] = '-cfg='.$server->getProfilesPath().'/server_basic.cfg';
        $params[] = '-nosplash';
        $params[] = '-skipIntro';
        $params[] = '-world=empty';

        foreach ($this->getModNames($server) as $modName) {
            $params[] = '-mod='.$modName;
        }

        if ($server->additional_params) {
            $params[] = $server->additional_params;
        }

        return $binary.' '.implode(' ', $params);
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
     * The file is always regenerated on start so config changes take effect immediately.
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
     * The file is always regenerated on start so config changes take effect immediately.
     */
    protected function generateBasicConfig(Server $server): void
    {
        $lines = [];

        $lines[] = '// BASIC NETWORK CONFIGURATION';
        $lines[] = 'MaxMsgSend = 128;';
        $lines[] = 'MaxSizeGuaranteed = 512;';
        $lines[] = 'MaxSizeNonguaranteed = 256;';
        $lines[] = 'MinBandwidth = 131072;';
        $lines[] = 'MaxBandwidth = 10000000000;';
        $lines[] = 'MinErrorToSend = 0.001;';
        $lines[] = 'MinErrorToSendNear = 0.01;';
        $lines[] = 'MaxCustomFileSize = 0;';
        $lines[] = '';
        $lines[] = 'class sockets {';
        $lines[] = '    maxPacketSize = 1400;';
        $lines[] = '};';

        file_put_contents(
            $server->getProfilesPath().'/server_basic.cfg',
            implode("\n", $lines)."\n"
        );
    }

    /**
     * Generate the .Arma3Profile file with difficulty and AI settings.
     * Written to {profiles}/home/{name}/{name}.Arma3Profile where {name} matches
     * the -name= launch parameter (arma3_{id}).
     */
    protected function generateProfileConfig(Server $server): void
    {
        $settings = $server->difficultySettings ?? $this->getDefaultDifficultySettings();
        $profileName = 'arma3_'.$server->id;
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
     * Build a default DifficultySettings object (not persisted) for servers
     * that don't have custom difficulty settings configured yet.
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
     * Symlink all PBO mission files from the shared missions pool
     * into the game install's mpmissions directory.
     */
    protected function symlinkMissions(Server $server): void
    {
        $missionsPath = config('arma.missions_base_path');
        $mpmissionsPath = $server->getBinaryPath().'/mpmissions';

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

    /**
     * Create symlinks for all mods in the active preset into the game install directory.
     * Each mod is symlinked as {game_install_dir}/@NormalizedName -> {mod_download_path}.
     */
    protected function symlinkMods(Server $server): void
    {
        $preset = $server->activePreset;

        if (! $preset) {
            return;
        }

        $gameInstallPath = $server->getBinaryPath();

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

    /**
     * Symlink .bikey files from all mods in the active preset to the game install's keys/ directory.
     * BiKeys are required for signature verification (verifySignatures=2).
     * Only the conventional keys/ subdirectory within each mod is checked (no recursive scan).
     */
    protected function copyBiKeys(Server $server): void
    {
        $preset = $server->activePreset;

        if (! $preset) {
            return;
        }

        $keysPath = $server->getBinaryPath().'/keys';

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

    protected function startHeadlessClient(Server $server, int $index): void
    {
        $context = "[Server:{$server->id} '{$server->name}' HC:{$index}]";
        $binary = $server->getBinaryPath().'/arma3server_x64';
        $binaryDir = $server->getBinaryPath();
        $pidFile = $this->getHcPidFilePath($server, $index);
        $logFile = $this->getHeadlessClientLogPath($server, $index);

        $params = [
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

        Log::info("{$context} Starting headless client");

        $command = sprintf(
            'cd %s && nohup %s %s > %s 2>&1 & echo $! > %s',
            escapeshellarg($binaryDir),
            $binary,
            implode(' ', $params),
            escapeshellarg($logFile),
            escapeshellarg($pidFile)
        );

        exec($command);

        Log::info("{$context} Headless client started");
    }

    protected function getPid(Server $server): ?int
    {
        $pidFile = $this->getPidFilePath($server);

        if (! file_exists($pidFile)) {
            return null;
        }

        $pid = (int) trim(file_get_contents($pidFile));

        return $pid > 0 ? $pid : null;
    }

    protected function isProcessRunning(int $pid): bool
    {
        return posix_kill($pid, 0);
    }

    protected function getPidFilePath(Server $server): string
    {
        return storage_path('app/server_'.$server->id.'.pid');
    }

    protected function getHcPidFilePath(Server $server, int $index): string
    {
        return storage_path('app/server_'.$server->id.'_hc_'.$index.'.pid');
    }

    /**
     * Get the path to the server's log file.
     */
    public function getServerLogPath(Server $server): string
    {
        return $server->getProfilesPath().'/server.log';
    }

    /**
     * Get the path to a headless client's log file.
     */
    public function getHeadlessClientLogPath(Server $server, int $index): string
    {
        return $server->getProfilesPath().'/hc_'.$index.'.log';
    }

    protected function cleanupPidFile(Server $server): void
    {
        @unlink($this->getPidFilePath($server));
    }

    /**
     * Start a background process that tails the server log and broadcasts new lines.
     */
    protected function startLogTail(Server $server): void
    {
        $pidFile = $this->getLogTailPidFilePath($server);
        $artisan = base_path('artisan');

        $command = sprintf(
            'nohup php %s server:tail-log %d > /dev/null 2>&1 & echo $! > %s',
            $artisan,
            $server->id,
            $pidFile
        );

        exec($command);
    }

    /**
     * Stop the log tail process for a server.
     */
    protected function stopLogTail(Server $server): void
    {
        $pidFile = $this->getLogTailPidFilePath($server);

        if (file_exists($pidFile)) {
            $pid = (int) trim(file_get_contents($pidFile));

            if ($pid > 0 && $this->isProcessRunning($pid)) {
                posix_kill($pid, SIGTERM);
            }

            @unlink($pidFile);
        }
    }

    protected function getLogTailPidFilePath(Server $server): string
    {
        return storage_path('app/server_'.$server->id.'_tail.pid');
    }
}
