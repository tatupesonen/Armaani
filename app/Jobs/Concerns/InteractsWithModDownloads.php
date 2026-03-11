<?php

namespace App\Jobs\Concerns;

use App\Contracts\GameHandler;
use App\Contracts\SupportsWorkshopMods;
use App\Enums\InstallationStatus;
use App\Events\ModDownloadOutput;
use App\Models\WorkshopMod;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Log;

trait InteractsWithModDownloads
{
    /**
     * Poll a running process for download progress on a single mod.
     *
     * @param  int  $lastProgressUpdate  The last progress percentage that was broadcast.
     * @return int The latest progress percentage.
     */
    protected function pollSingleModProgress(
        InvokedProcess $process,
        WorkshopMod $mod,
        string $modPath,
        int $expectedSize,
        int $lastProgressUpdate,
    ): int {
        while ($process->running()) {
            sleep(1);

            if ($expectedSize > 0) {
                $currentSize = $this->getDirectorySize($modPath);
                $pct = min(99, (int) round(($currentSize / $expectedSize) * 100));

                if ($pct >= $lastProgressUpdate + 1) {
                    $lastProgressUpdate = $pct;
                    $mod->updateQuietly(['progress_pct' => $pct]);

                    ModDownloadOutput::dispatch(
                        $mod->id,
                        $pct,
                        "Downloading... {$pct}% ({$currentSize} / {$expectedSize} bytes)",
                    );
                }
            }
        }

        return $lastProgressUpdate;
    }

    /**
     * Broadcast SteamCMD output lines after the process has completed.
     */
    protected function broadcastProcessOutput(InvokedProcess $process, int $modId, int $progressPct): void
    {
        $result = $process->wait();
        $output = trim($result->output().' '.$result->errorOutput());

        if ($output) {
            foreach (explode("\n", $output) as $outputLine) {
                $trimmed = trim($outputLine);
                if ($trimmed !== '') {
                    ModDownloadOutput::dispatch($modId, max($progressPct, 0), $trimmed);
                }
            }
        }
    }

    /**
     * Finalize a successfully downloaded mod (lowercase conversion, size update, status).
     */
    protected function finalizeSuccessfulMod(WorkshopMod $mod, GameHandler $handler, string $context): void
    {
        $modPath = $mod->getInstallationPath();

        if ($handler instanceof SupportsWorkshopMods && $handler->requiresLowercaseConversion()) {
            $this->convertToLowercase($modPath);
        }

        $actualSize = $this->getDirectorySize($modPath);

        $mod->update([
            'installation_status' => InstallationStatus::Installed,
            'progress_pct' => 100,
            'installed_at' => now(),
            'file_size' => $actualSize > 0 ? $actualSize : $mod->file_size,
        ]);

        Log::info("{$context} Downloaded successfully (disk: {$actualSize} bytes)");
        ModDownloadOutput::dispatch($mod->id, 100, 'Download completed successfully.');
    }

    /**
     * Mark a mod as failed and broadcast the failure.
     */
    protected function markModFailed(WorkshopMod $mod, string $errorOutput, string $context): void
    {
        Log::error("{$context} Download failed: {$errorOutput}");
        $mod->update(['installation_status' => InstallationStatus::Failed]);
        ModDownloadOutput::dispatch($mod->id, 0, 'Download failed: '.$errorOutput);
    }
}
