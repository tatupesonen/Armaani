<?php

namespace App\Services;

use App\Enums\ServerStatus;
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

        $this->symlinkMods($server);
        $this->symlinkMissions($server);
        $this->copyBiKeys($server);
        $this->generateServerConfig($server);

        $command = $this->buildLaunchCommand($server);
        $pidFile = $this->getPidFilePath($server);
        $logFile = $this->getServerLogPath($server);
        $binaryDir = $server->getBinaryPath();

        Log::info("{$context} Starting server from {$binaryDir}");
        Log::info("{$context} Launch command: {$command}");
        Log::info("{$context} Log file: {$logFile}");

        // Truncate/create log file and start tail BEFORE the server process,
        // so the tail is already watching when the server writes its first lines.
        file_put_contents($logFile, '');
        $this->startLogTail($server);

        $fullCommand = sprintf(
            'cd %s && nohup %s > %s 2>&1 & echo $! > %s',
            escapeshellarg($binaryDir),
            $command,
            escapeshellarg($logFile),
            escapeshellarg($pidFile)
        );

        exec($fullCommand);

        $pid = $this->getPid($server);
        Log::info("{$context} Process started with PID {$pid}");
    }

    /**
     * Stop an Arma 3 server instance.
     */
    public function stop(Server $server): void
    {
        $context = "[Server:{$server->id} '{$server->name}']";

        $this->stopLogTail($server);

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
     */
    public function getStatus(Server $server): ServerStatus
    {
        return $this->isRunning($server) ? ServerStatus::Running : ServerStatus::Stopped;
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
    protected function buildLaunchCommand(Server $server): string
    {
        $binary = $server->getBinaryPath().'/arma3server_x64';
        $params = [];

        $params[] = '-port='.$server->port;
        $params[] = '-name=arma3_'.$server->id;
        $params[] = '-profiles='.$server->getProfilesPath();
        $params[] = '-config='.$server->getProfilesPath().'/server.cfg';
        $params[] = '-nosplash';
        $params[] = '-skipIntro';
        $params[] = '-world=empty';

        if ($server->password) {
            $params[] = '-password='.$server->password;
        }

        $modParams = $this->buildModParams($server);

        if ($modParams) {
            $params[] = '-mod='.$modParams;
        }

        if ($server->additional_params) {
            $params[] = $server->additional_params;
        }

        return $binary.' '.implode(' ', $params);
    }

    /**
     * Build the -mod= parameter string from the server's active preset.
     */
    protected function buildModParams(Server $server): string
    {
        $preset = $server->activePreset;

        if (! $preset) {
            return '';
        }

        return $preset->mods->map(fn ($mod) => $mod->getNormalizedName())->implode(';');
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
        $lines[] = 'verifySignatures = 2;';
        $lines[] = 'allowedFilePatching = 0;';
        $lines[] = '';
        $lines[] = '// INGAME SETTINGS';
        $lines[] = 'disableVoN = 0;';
        $lines[] = 'vonCodec = 1;';
        $lines[] = 'vonCodecQuality = 30;';
        $lines[] = 'persistent = 0;';
        $lines[] = 'timeStampFormat = "short";';
        $lines[] = 'BattlEye = 1;';
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

        $mpmissionsPath = $server->getBinaryPath().'/mpmissions';
        $missionFiles = is_dir($mpmissionsPath)
            ? glob($mpmissionsPath.'/*.pbo') ?: []
            : [];

        if (! empty($missionFiles)) {
            $lines[] = '';
            $lines[] = '// MISSIONS';
            $lines[] = 'class Missions {';

            foreach ($missionFiles as $index => $missionFile) {
                $template = basename($missionFile, '.pbo');
                $classIndex = $index + 1;
                $lines[] = "    class Mission{$classIndex} {";
                $lines[] = '        template = "'.addslashes($template).'";';
                $lines[] = '        difficulty = "Regular";';
                $lines[] = '    };';
            }

            $lines[] = '};';
        }

        file_put_contents(
            $server->getProfilesPath().'/server.cfg',
            implode("\n", $lines)."\n"
        );
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

        $preset->load('mods');

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
     * Copy .bikey files from all mods in the active preset to the game install's keys/ directory.
     * BiKeys are required for signature verification (verifySignatures=2).
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

        $preset->load('mods');

        foreach ($preset->mods as $mod) {
            $modInstallPath = $mod->getInstallationPath();

            if (! is_dir($modInstallPath)) {
                continue;
            }

            $bikeyFiles = $this->findBiKeyFiles($modInstallPath);

            foreach ($bikeyFiles as $bikeyFile) {
                $destPath = $keysPath.'/'.basename($bikeyFile);
                copy($bikeyFile, $destPath);
                Log::info("[Server:{$server->id}] Copied BiKey ".basename($bikeyFile)." from mod '{$mod->name}'");
            }
        }
    }

    /**
     * Recursively find all .bikey files in a directory.
     *
     * @return array<string>
     */
    protected function findBiKeyFiles(string $directory): array
    {
        $bikeyFiles = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'bikey') {
                $bikeyFiles[] = $file->getPathname();
            }
        }

        return $bikeyFiles;
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

        $modParams = $this->buildModParams($server);

        if ($modParams) {
            $params[] = '-mod='.$modParams;
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
