<?php

namespace App\Jobs;

use App\Enums\InstallationStatus;
use App\Models\WorkshopMod;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DownloadModJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(public WorkshopMod $mod) {}

    public function handle(SteamCmdService $steamCmd, SteamWorkshopService $workshop): void
    {
        $context = "[Mod:{$this->mod->id} '{$this->mod->workshop_id}']";

        Log::info("{$context} Starting download");

        $this->fetchMetadata($workshop);

        $this->mod->update([
            'installation_status' => InstallationStatus::Installing,
            'progress_pct' => 0,
        ]);

        $installDir = config('arma.mods_base_path');
        $modPath = $this->mod->getInstallationPath();
        $expectedSize = $this->mod->file_size;

        $process = $steamCmd->startDownloadMod($installDir, $this->mod->workshop_id);

        $lastProgressUpdate = -1;

        while ($process->running()) {
            sleep(2);

            if ($expectedSize && $expectedSize > 0) {
                $currentSize = $this->getDirectorySize($modPath);
                $pct = min(99, (int) round(($currentSize / $expectedSize) * 100));

                if ($pct >= $lastProgressUpdate + 2) {
                    $lastProgressUpdate = $pct;
                    $this->mod->updateQuietly(['progress_pct' => $pct]);
                }
            }
        }

        $result = $process->wait();

        if ($result->successful()) {
            $actualSize = $this->getDirectorySize($modPath);

            $this->mod->update([
                'installation_status' => InstallationStatus::Installed,
                'progress_pct' => 100,
                'installed_at' => now(),
                'file_size' => $actualSize > 0 ? $actualSize : $this->mod->file_size,
            ]);

            Log::info("{$context} Downloaded successfully (disk: {$actualSize} bytes)");
        } else {
            Log::error("{$context} Download failed: {$result->errorOutput()}");
            $this->mod->update(['installation_status' => InstallationStatus::Failed]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("[Mod:{$this->mod->id} '{$this->mod->workshop_id}'] Job failed: {$exception?->getMessage()}");
        $this->mod->update(['installation_status' => InstallationStatus::Failed]);
    }

    /**
     * Fetch name and expected file size from Steam API if not already set.
     */
    protected function fetchMetadata(SteamWorkshopService $workshop): void
    {
        if ($this->mod->name && $this->mod->file_size) {
            return;
        }

        $details = $workshop->getModDetails($this->mod->workshop_id);

        if ($details) {
            $this->mod->update(array_filter([
                'name' => $this->mod->name ?? $details['name'],
                'file_size' => $this->mod->file_size ?? $details['file_size'],
            ]));
        }
    }

    private function getDirectorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $result = Process::run(['du', '-sb', $path]);

        if (! $result->successful()) {
            return 0;
        }

        return (int) explode("\t", trim($result->output()))[0];
    }
}
