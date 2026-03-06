<?php

namespace App\Jobs;

use App\Enums\InstallationStatus;
use App\Events\ModDownloadOutput;
use App\Jobs\Concerns\InteractsWithFileSystem;
use App\Models\WorkshopMod;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BatchDownloadModsJob implements ShouldQueue
{
    use InteractsWithFileSystem;
    use Queueable;

    public int $tries = 2;

    /**
     * @param  Collection<int, WorkshopMod>  $mods
     */
    public function __construct(public Collection $mods) {}

    /**
     * Dynamic timeout: 1 hour per mod in the batch.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addSeconds(max(3600, $this->mods->count() * 3600));
    }

    public function handle(SteamCmdService $steamCmd, SteamWorkshopService $workshop): void
    {
        $modCount = $this->mods->count();
        Log::info("[BatchDownload] Starting batch download of {$modCount} mods");

        $this->fetchAllMetadata($workshop);

        foreach ($this->mods as $mod) {
            $mod->update([
                'installation_status' => InstallationStatus::Installing,
                'progress_pct' => 0,
            ]);

            ModDownloadOutput::dispatch($mod->id, 0, 'Queued in batch download...');
        }

        $installDir = config('arma.mods_base_path');
        $workshopIds = $this->mods->pluck('workshop_id')->all();

        $process = $steamCmd->startBatchDownloadMods($installDir, $workshopIds);

        ModDownloadOutput::dispatch(
            $this->mods->first()->id,
            0,
            "Starting batched SteamCMD download ({$modCount} mods)...",
        );

        /** @var array<int, int> $lastProgressUpdates */
        $lastProgressUpdates = $this->mods->pluck('id')->mapWithKeys(fn (int $id) => [$id => -1])->all();

        while ($process->running()) {
            sleep(1);

            foreach ($this->mods as $mod) {
                $expectedSize = $mod->file_size;

                if ($expectedSize <= 0) {
                    continue;
                }

                $modPath = $mod->getInstallationPath();
                $currentSize = $this->getDirectorySize($modPath);
                $pct = min(99, (int) round(($currentSize / $expectedSize) * 100));

                if ($pct >= $lastProgressUpdates[$mod->id] + 1) {
                    $lastProgressUpdates[$mod->id] = $pct;
                    $mod->updateQuietly(['progress_pct' => $pct]);

                    ModDownloadOutput::dispatch(
                        $mod->id,
                        $pct,
                        "Downloading... {$pct}% ({$currentSize} / {$expectedSize} bytes)",
                    );
                }
            }
        }

        $result = $process->wait();

        $output = trim($result->output().' '.$result->errorOutput());
        if ($output) {
            foreach (explode("\n", $output) as $outputLine) {
                $trimmed = trim($outputLine);
                if ($trimmed !== '') {
                    Log::info("[BatchDownload] {$trimmed}");
                }
            }
        }

        if ($result->successful()) {
            $this->processSuccessfulBatch();
        } else {
            $this->processFailedBatch($result->errorOutput());
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("[BatchDownload] Job failed: {$exception?->getMessage()}");

        foreach ($this->mods as $mod) {
            $mod->update(['installation_status' => InstallationStatus::Failed]);
            ModDownloadOutput::dispatch($mod->id, 0, 'Batch download failed: '.($exception?->getMessage() ?? 'Unknown error'));
        }
    }

    /**
     * Fetch name and expected file size from Steam API for all mods missing metadata.
     * Uses a single bulk API call instead of one request per mod.
     */
    protected function fetchAllMetadata(SteamWorkshopService $workshop): void
    {
        $modsNeedingMetadata = $this->mods->filter(fn (WorkshopMod $mod) => ! $mod->name || ! $mod->file_size);

        if ($modsNeedingMetadata->isEmpty()) {
            return;
        }

        $workshopIds = $modsNeedingMetadata->pluck('workshop_id')->all();
        $detailsMap = $workshop->getMultipleModDetails($workshopIds);

        foreach ($modsNeedingMetadata as $mod) {
            $details = $detailsMap[$mod->workshop_id] ?? null;

            if ($details) {
                $mod->update(array_filter([
                    'name' => $mod->name ?? $details['name'],
                    'file_size' => $mod->file_size ?? $details['file_size'],
                ]));
            }
        }
    }

    /**
     * Process all mods after a successful SteamCMD batch download.
     */
    private function processSuccessfulBatch(): void
    {
        foreach ($this->mods as $mod) {
            $modPath = $mod->getInstallationPath();

            $this->convertToLowercase($modPath);
            $actualSize = $this->getDirectorySize($modPath);

            $mod->update([
                'installation_status' => InstallationStatus::Installed,
                'progress_pct' => 100,
                'installed_at' => now(),
                'file_size' => $actualSize > 0 ? $actualSize : $mod->file_size,
            ]);

            Log::info("[BatchDownload] Mod {$mod->workshop_id} downloaded successfully (disk: {$actualSize} bytes)");
            ModDownloadOutput::dispatch($mod->id, 100, 'Download completed successfully.');
        }
    }

    /**
     * Mark all mods as failed after a SteamCMD batch download failure.
     */
    private function processFailedBatch(string $errorOutput): void
    {
        Log::error("[BatchDownload] Batch download failed: {$errorOutput}");

        foreach ($this->mods as $mod) {
            $mod->update(['installation_status' => InstallationStatus::Failed]);
            ModDownloadOutput::dispatch($mod->id, 0, 'Batch download failed: '.$errorOutput);
        }
    }
}
