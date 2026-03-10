<?php

namespace App\Services\Server;

use App\Contracts\ManagesModAssets;
use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\Enums\ServerStatus;
use App\GameManager;
use App\Models\Server;
use Illuminate\Support\Facades\Log;

if (! defined('SIGTERM')) {
    define('SIGTERM', 15);
}

if (! defined('SIGKILL')) {
    define('SIGKILL', 9);
}

class ServerProcessService
{
    public function __construct(
        protected GameManager $gameManager,
        protected ServerBackupService $backupService,
    ) {}

    /**
     * Start a game server instance.
     */
    public function start(Server $server): void
    {
        $handler = $this->gameManager->for($server);

        if ($this->isRunning($server)) {
            Log::info("{$server->logContext()} Server is already running, skipping start");

            return;
        }

        $profilesPath = $server->getProfilesPath();

        if (! is_dir($profilesPath)) {
            mkdir($profilesPath, 0755, true);
        }

        // Auto-backup profile data before overwriting configs (only for games that support it)
        if ($handler instanceof SupportsBackups) {
            $this->backupService->createFromServer($server, 'Auto-backup before start', isAutomatic: true);
        }

        $server->load('activePreset.mods');

        if ($handler instanceof ManagesModAssets) {
            $handler->symlinkMods($server);
            $handler->copyBiKeys($server);
        }

        if ($handler instanceof SupportsMissions) {
            $handler->symlinkMissions($server);
        }

        $handler->generateConfigFiles($server);

        $command = $handler->buildLaunchCommand($server);
        $pidFile = $this->getPidFilePath($server);
        $logFile = $handler->getServerLogPath($server);
        $binaryDir = $server->gameInstall->getInstallationPath();

        Log::info("{$server->logContext()} Starting server from {$binaryDir}");
        Log::info("{$server->logContext()} Launch command: ".implode(' ', $command));
        Log::info("{$server->logContext()} Log file: {$logFile}");

        // Truncate/create log file before the server process.
        file_put_contents($logFile, '');
        $this->startLogTail($server);

        // Start the server as a detached child process using proc_open.
        // Array-form proc_open bypasses the shell entirely, so the PID we
        // capture IS the server process — signals target it directly.
        $this->spawnProcess($command, $binaryDir, $logFile, $pidFile, $server->logContext());
    }

    /**
     * Stop a game server instance.
     */
    public function stop(Server $server): void
    {
        $pid = $this->getPid($server);

        if ($pid && $this->isProcessRunning($pid)) {
            Log::info("{$server->logContext()} Stopping server (PID {$pid})");
            $this->killProcessTree($pid, SIGTERM);

            $attempts = 0;
            while ($this->isProcessRunning($pid) && $attempts < 15) {
                usleep(500_000);
                $attempts++;
            }

            if ($this->isProcessRunning($pid)) {
                Log::warning("{$server->logContext()} Server did not stop gracefully, sending SIGKILL (PID {$pid})");
                $this->killProcessTree($pid, SIGKILL);
            }

            Log::info("{$server->logContext()} Server stopped");
        } else {
            Log::info("{$server->logContext()} Server was not running (no active PID)");
        }

        $this->stopLogTail($server);
        $this->cleanupPidFile($server);
    }

    /**
     * Restart a game server instance.
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

        // Booting is semi-transitional: trust DB while the process is alive,
        // but self-heal to Stopped if the process died during boot.
        if ($server->status === ServerStatus::Booting) {
            if ($this->isRunning($server)) {
                return ServerStatus::Booting;
            }

            $server->updateQuietly(['status' => ServerStatus::Stopped]);

            return ServerStatus::Stopped;
        }

        $isRunning = $this->isRunning($server);
        $expected = $isRunning ? ServerStatus::Running : ServerStatus::Stopped;

        if ($server->status !== $expected) {
            $server->updateQuietly(['status' => $expected]);
        }

        return $expected;
    }

    /**
     * Add a single headless client to a running server.
     * Returns the index assigned to the new HC, or null if the cap is reached or not supported.
     */
    public function addHeadlessClient(Server $server): ?int
    {
        $handler = $this->gameManager->for($server);

        if (! $handler instanceof SupportsHeadlessClients) {
            return null;
        }

        $runningIndices = $this->getRunningHcIndices($server);

        if (count($runningIndices) >= 10) {
            Log::warning("{$server->logContext()} Cannot add HC — already at maximum (10)");

            return null;
        }

        $nextIndex = $runningIndices === [] ? 0 : max($runningIndices) + 1;

        $server->load('activePreset.mods');
        $this->startHeadlessClient($server, $nextIndex);

        return $nextIndex;
    }

    /**
     * Remove the most recently added headless client (highest index).
     * Returns the index removed, or null if none are running.
     */
    public function removeHeadlessClient(Server $server): ?int
    {
        $runningIndices = $this->getRunningHcIndices($server);

        if ($runningIndices === []) {
            return null;
        }

        $index = max($runningIndices);
        $this->stopHeadlessClient($server, $index);

        return $index;
    }

    /**
     * Stop all running headless clients for a server.
     * Globs PID files so it catches all HCs regardless of expected count.
     */
    public function stopAllHeadlessClients(Server $server): void
    {
        $pidFiles = glob(storage_path('app/server_'.$server->id.'_hc_*.pid')) ?: [];

        foreach ($pidFiles as $pidFile) {
            $this->terminateProcess($pidFile, $server->logContext());
        }
    }

    /**
     * Get the number of currently running headless clients.
     * Cleans up stale PID files for crashed HCs as a side effect.
     */
    public function getRunningHeadlessClientCount(Server $server): int
    {
        return count($this->getRunningHcIndices($server));
    }

    /**
     * Get the path to the server's log file via the game handler.
     */
    public function getServerLogPath(Server $server): string
    {
        return $this->gameManager->for($server)->getServerLogPath($server);
    }

    /**
     * Get the path to a headless client's log file.
     */
    public function getHeadlessClientLogPath(Server $server, int $index): string
    {
        return $server->getProfilesPath().'/hc_'.$index.'.log';
    }

    /**
     * Get sorted indices of all running headless clients.
     * Removes stale PID files (crashed HCs) as a side effect.
     *
     * @return int[]
     */
    protected function getRunningHcIndices(Server $server): array
    {
        $pidFiles = glob(storage_path('app/server_'.$server->id.'_hc_*.pid')) ?: [];
        $runningIndices = [];

        foreach ($pidFiles as $pidFile) {
            $pid = (int) trim(file_get_contents($pidFile));

            if ($pid > 0 && $this->isProcessRunning($pid)) {
                // Extract index from filename: server_{id}_hc_{index}.pid
                if (preg_match('/hc_(\d+)\.pid$/', $pidFile, $matches)) {
                    $runningIndices[] = (int) $matches[1];
                }
            } else {
                @unlink($pidFile);
            }
        }

        sort($runningIndices);

        return $runningIndices;
    }

    /**
     * Stop a single headless client by index.
     */
    protected function stopHeadlessClient(Server $server, int $index): void
    {
        $context = "{$server->logContext()} HC:{$index}";
        $this->terminateProcess($this->getHcPidFilePath($server, $index), $context);
    }

    protected function startHeadlessClient(Server $server, int $index): void
    {
        $handler = $this->gameManager->for($server);
        assert($handler instanceof SupportsHeadlessClients);

        $context = "{$server->logContext()} HC:{$index}";
        $binaryDir = $server->gameInstall->getInstallationPath();
        $pidFile = $this->getHcPidFilePath($server, $index);
        $logFile = $this->getHeadlessClientLogPath($server, $index);

        $command = $handler->buildHeadlessClientCommand($server, $index);

        Log::info("{$context} Starting headless client: ".implode(' ', $command));

        file_put_contents($logFile, '');

        $this->spawnProcess($command, $binaryDir, $logFile, $pidFile, $context);
    }

    /**
     * Spawn a detached process via proc_open, capture its PID, and write a PID file.
     * Array-form proc_open bypasses the shell so the PID targets the actual binary.
     *
     * @param  array<int, string>  $command  The binary path followed by arguments.
     * @return int|null The PID of the spawned process, or null on failure.
     */
    protected function spawnProcess(array $command, string $workingDir, string $logFile, string $pidFile, string $context): ?int
    {
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $workingDir);

        if (is_resource($process)) {
            $status = proc_get_status($process);
            $pid = $status['pid'];
            file_put_contents($pidFile, (string) $pid);
            Log::info("{$context} Process started with PID {$pid}");

            return $pid;
        }

        Log::error("{$context} Failed to start process");

        return null;
    }

    /**
     * Read a PID file, send SIGTERM to the process if running, and remove the PID file.
     */
    protected function terminateProcess(string $pidFile, string $context = ''): void
    {
        if (! file_exists($pidFile)) {
            return;
        }

        $pid = (int) trim(file_get_contents($pidFile));

        if ($pid > 0 && $this->isProcessRunning($pid)) {
            if ($context !== '') {
                Log::info("{$context} Stopping process (PID {$pid})");
            }
            posix_kill($pid, SIGTERM);
        }

        @unlink($pidFile);
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

    /**
     * Send a signal to a process and all its descendants.
     *
     * Game servers launched via wrapper scripts (e.g. Project Zomboid's start-server.sh)
     * spawn child processes. Killing only the parent leaves children orphaned.
     */
    protected function killProcessTree(int $pid, int $signal): void
    {
        $childPids = [];
        exec("pgrep -P {$pid}", $childPids);

        foreach ($childPids as $childPid) {
            $this->killProcessTree((int) $childPid, $signal);
        }

        posix_kill($pid, $signal);
    }

    protected function getPidFilePath(Server $server): string
    {
        return storage_path('app/server_'.$server->id.'.pid');
    }

    protected function getHcPidFilePath(Server $server, int $index): string
    {
        return storage_path('app/server_'.$server->id.'_hc_'.$index.'.pid');
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
        $this->terminateProcess($this->getLogTailPidFilePath($server));
    }

    protected function getLogTailPidFilePath(Server $server): string
    {
        return storage_path('app/server_'.$server->id.'_tail.pid');
    }
}
