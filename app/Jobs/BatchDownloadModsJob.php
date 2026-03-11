<?php

namespace App\Jobs;

use App\Enums\InstallationStatus;
use App\Events\ModDownloadOutput;
use App\GameManager;
use App\Jobs\Concerns\InteractsWithFileSystem;
use App\Jobs\Concerns\InteractsWithModDownloads;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use App\Services\Steam\SteamCmdService;
use App\Services\Steam\SteamWorkshopService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BatchDownloadModsJob implements ShouldQueue
{
    use InteractsWithFileSystem;
    use InteractsWithModDownloads;
    use Queueable;

    public int $tries = 2;

    /**
     * @param  Collection<int, WorkshopMod>  $mods
     */
    public function __construct(public Collection $mods) {}

    /**
     * Dispatch download jobs for a collection of mods, respecting the configured batch size.
     * Single-mod batches use DownloadModJob; multi-mod batches use BatchDownloadModsJob.
     *
     * @param  Collection<int, WorkshopMod>  $mods
     */
    public static function dispatchInBatches(Collection $mods): void
    {
        if ($mods->isEmpty()) {
            return;
        }

        $batchSize = SteamAccount::current()?->mod_download_batch_size ?? 5;

        foreach ($mods->chunk($batchSize) as $batch) {
            if ($batch->count() === 1) {
                DownloadModJob::dispatch($batch->first());
            } else {
                self::dispatch($batch);
            }
        }
    }

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

        $workshop->syncMetadataForMany($this->mods);

        foreach ($this->mods as $mod) {
            $mod->update([
                'installation_status' => InstallationStatus::Installing,
                'progress_pct' => 0,
            ]);

            ModDownloadOutput::dispatch($mod->id, 0, 'Queued in batch download...');
        }

        $installDir = config('arma.mods_base_path');
        $workshopIds = $this->mods->pluck('workshop_id')->all();

        $handler = app(GameManager::class)->driver($this->mods->first()->game_type);
        $process = $steamCmd->startBatchDownloadMods($installDir, $workshopIds, $handler);

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
            foreach ($this->mods as $mod) {
                $this->finalizeSuccessfulMod($mod, $handler, "[BatchDownload] Mod {$mod->workshop_id}");
            }
        } else {
            foreach ($this->mods as $mod) {
                $this->markModFailed($mod, $result->errorOutput(), "[BatchDownload] Mod {$mod->workshop_id}");
            }
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
}
