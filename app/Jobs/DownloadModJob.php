<?php

namespace App\Jobs;

use App\Enums\InstallationStatus;
use App\Events\ModDownloadOutput;
use App\GameManager;
use App\Jobs\Concerns\InteractsWithFileSystem;
use App\Jobs\Concerns\InteractsWithModDownloads;
use App\Models\WorkshopMod;
use App\Services\Steam\SteamCmdService;
use App\Services\Steam\SteamWorkshopService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DownloadModJob implements ShouldQueue
{
    use InteractsWithFileSystem;
    use InteractsWithModDownloads;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(public WorkshopMod $mod) {}

    public function handle(SteamCmdService $steamCmd, SteamWorkshopService $workshop): void
    {
        $context = "[Mod:{$this->mod->id} '{$this->mod->workshop_id}']";

        Log::info("{$context} Starting download");

        $workshop->syncMetadata($this->mod);

        $this->mod->update([
            'installation_status' => InstallationStatus::Installing,
            'progress_pct' => 0,
        ]);

        $installDir = config('arma.mods_base_path');
        $modPath = $this->mod->getInstallationPath();
        $expectedSize = $this->mod->file_size;

        $handler = app(GameManager::class)->driver($this->mod->game_type);

        $process = $steamCmd->startDownloadMod($installDir, $this->mod->workshop_id, $handler);

        ModDownloadOutput::dispatch($this->mod->id, 0, 'Starting SteamCMD download...');

        $lastProgress = $this->pollSingleModProgress($process, $this->mod, $modPath, $expectedSize, -1);

        $this->broadcastProcessOutput($process, $this->mod->id, $lastProgress);

        $result = $process->wait();

        if ($result->successful()) {
            $this->finalizeSuccessfulMod($this->mod, $handler, $context);
        } else {
            $this->markModFailed($this->mod, $result->errorOutput(), $context);
            $this->fail(new \RuntimeException('SteamCMD failed: '.$result->errorOutput()));
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("[Mod:{$this->mod->id} '{$this->mod->workshop_id}'] Job failed: {$exception?->getMessage()}");
        $this->mod->update(['installation_status' => InstallationStatus::Failed]);
    }
}
