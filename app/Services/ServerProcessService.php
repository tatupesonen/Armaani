<?php

namespace App\Services;

use App\Enums\ServerStatus;
use App\Models\Server;

class ServerProcessService
{
    /**
     * Start an Arma 3 server instance.
     */
    public function start(Server $server): void
    {
        if ($this->isRunning($server)) {
            return;
        }

        $command = $this->buildLaunchCommand($server);
        $pidFile = $this->getPidFilePath($server);

        $fullCommand = sprintf(
            'nohup %s > %s 2>&1 & echo $! > %s',
            $command,
            $server->getBinaryPath().'/server.log',
            $pidFile
        );

        exec($fullCommand);
    }

    /**
     * Stop an Arma 3 server instance.
     */
    public function stop(Server $server): void
    {
        $pid = $this->getPid($server);

        if ($pid && $this->isProcessRunning($pid)) {
            posix_kill($pid, SIGTERM);

            $waited = 0;
            while ($this->isProcessRunning($pid) && $waited < 15) {
                usleep(500000);
                $waited++;
            }

            if ($this->isProcessRunning($pid)) {
                posix_kill($pid, SIGKILL);
            }
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

        for ($i = 0; $i < $count; $i++) {
            $this->startHeadlessClient($server, $i);
        }
    }

    /**
     * Stop all headless clients for a server.
     */
    public function stopHeadlessClients(Server $server): void
    {
        $count = $server->headless_client_count;

        for ($i = 0; $i < $count; $i++) {
            $pidFile = $this->getHcPidFilePath($server, $i);

            if (file_exists($pidFile)) {
                $pid = (int) file_get_contents($pidFile);

                if ($this->isProcessRunning($pid)) {
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
        $params[] = '-config=server.cfg';
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

        $mods = $preset->mods()->get();

        return $mods->map(fn ($mod) => $mod->getNormalizedName())->implode(';');
    }

    protected function startHeadlessClient(Server $server, int $index): void
    {
        $binary = $server->getBinaryPath().'/arma3server_x64';
        $pidFile = $this->getHcPidFilePath($server, $index);

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

        $command = sprintf(
            'nohup %s %s > /dev/null 2>&1 & echo $! > %s',
            $binary,
            implode(' ', $params),
            $pidFile
        );

        exec($command);
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

    protected function cleanupPidFile(Server $server): void
    {
        $pidFile = $this->getPidFilePath($server);

        if (file_exists($pidFile)) {
            @unlink($pidFile);
        }
    }
}
