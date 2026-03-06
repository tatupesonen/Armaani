<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerBackup;
use Illuminate\Support\Facades\Log;

class ServerBackupService
{
    /**
     * Get the path to the .vars.Arma3Profile file for a server.
     */
    public function getVarsFilePath(Server $server): string
    {
        $profileName = $server->getProfileName();

        return $server->getProfilesPath().'/home/'.$profileName.'/'.$profileName.'.vars.Arma3Profile';
    }

    /**
     * Create a backup from the server's current .vars.Arma3Profile file.
     *
     * @return ServerBackup|null The backup, or null if no .vars file exists.
     */
    public function createFromServer(Server $server, ?string $name = null, bool $isAutomatic = false): ?ServerBackup
    {
        $varsPath = $this->getVarsFilePath($server);

        if (! file_exists($varsPath)) {
            Log::info("[Server:{$server->id} '{$server->name}'] No .vars.Arma3Profile file found, skipping backup");

            return null;
        }

        return $this->persistBackup($server, file_get_contents($varsPath), $name, $isAutomatic);
    }

    /**
     * Create a backup from uploaded file data.
     */
    public function createFromUpload(Server $server, string $data, ?string $name = null): ServerBackup
    {
        return $this->persistBackup($server, $data, $name, isAutomatic: false);
    }

    /**
     * Persist a backup record, log it, and prune old backups.
     */
    private function persistBackup(Server $server, string $data, ?string $name, bool $isAutomatic): ServerBackup
    {
        $fileSize = strlen($data);

        $backup = $server->backups()->create([
            'name' => $name,
            'file_size' => $fileSize,
            'is_automatic' => $isAutomatic,
            'data' => $data,
        ]);

        $type = $isAutomatic ? 'automatic' : 'manual';
        Log::info("[Server:{$server->id} '{$server->name}'] Created {$type} backup #{$backup->id} ({$fileSize} bytes)");

        $this->pruneOldBackups($server);

        return $backup;
    }

    /**
     * Restore a backup by writing its data to the server's .vars.Arma3Profile path.
     */
    public function restore(ServerBackup $backup): void
    {
        $server = $backup->server;
        $varsPath = $this->getVarsFilePath($server);
        $varsDir = dirname($varsPath);

        if (! is_dir($varsDir)) {
            mkdir($varsDir, 0755, true);
        }

        file_put_contents($varsPath, $backup->data);

        Log::info("[Server:{$server->id} '{$server->name}'] Restored backup #{$backup->id}");
    }

    /**
     * Delete old backups when the configured limit is exceeded.
     */
    public function pruneOldBackups(Server $server): void
    {
        $maxBackups = (int) config('arma.max_backups_per_server');

        if ($maxBackups <= 0) {
            return;
        }

        $count = $server->backups()->count();

        if ($count <= $maxBackups) {
            return;
        }

        $toDelete = $server->backups()
            ->reorder('created_at', 'asc')
            ->limit($count - $maxBackups)
            ->get();

        foreach ($toDelete as $backup) {
            Log::info("[Server:{$server->id} '{$server->name}'] Pruning old backup #{$backup->id}");
            $backup->delete();
        }
    }
}
