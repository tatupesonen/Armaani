<?php

namespace App\Jobs;

use App\Enums\GameInstallStatus;
use App\Models\GameInstall;
use App\Services\SteamCmdService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class InstallServerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 7200;

    public function __construct(public GameInstall $gameInstall) {}

    public function handle(SteamCmdService $steamCmd): void
    {
        $installDir = $this->gameInstall->getInstallationPath();

        if (! is_dir($installDir)) {
            mkdir($installDir, 0755, true);
        }

        $this->gameInstall->update([
            'installation_status' => GameInstallStatus::Installing,
            'progress_pct' => 0,
        ]);

        $context = "[GameInstall:{$this->gameInstall->id} '{$this->gameInstall->name}']";

        Log::info("{$context} Starting installation (branch: {$this->gameInstall->branch}) to {$installDir}");

        $lastProgressUpdate = 0;

        $result = $steamCmd->installServer(
            $installDir,
            $this->gameInstall->branch,
            function (string $line) use (&$lastProgressUpdate, $context): void {
                Log::info("{$context} {$line}");

                // Parse: " Update state (0x61) downloading, progress: 44.53 (2397543803 / 5384428737)"
                if (preg_match('/progress:\s*([\d.]+)\s*\((\d+)\s*\/\s*(\d+)\)/', $line, $m)) {
                    $pct = (int) round((float) $m[1]);
                    $totalBytes = (int) $m[3];

                    // Throttle DB writes — only update every 2 percentage points
                    if ($pct >= $lastProgressUpdate + 2 || $pct === 100) {
                        $lastProgressUpdate = $pct;

                        $this->gameInstall->updateQuietly([
                            'progress_pct' => $pct,
                            'disk_size_bytes' => $totalBytes > 0 ? $totalBytes : $this->gameInstall->disk_size_bytes,
                        ]);
                    }
                }
            }
        );

        if ($result->successful()) {
            // After install, record actual disk size
            $diskSize = $this->getDirectorySize($installDir);

            $this->gameInstall->update([
                'installation_status' => GameInstallStatus::Installed,
                'progress_pct' => 100,
                'disk_size_bytes' => $diskSize > 0 ? $diskSize : $this->gameInstall->disk_size_bytes,
                'installed_at' => now(),
            ]);

            Log::info("Game install '{$this->gameInstall->name}' completed successfully (disk: {$diskSize} bytes)");
        } else {
            $this->gameInstall->update(['installation_status' => GameInstallStatus::Failed]);

            Log::error("Game installation failed for '{$this->gameInstall->name}': {$result->errorOutput()}");

            $this->fail(new \RuntimeException('SteamCMD failed: '.$result->errorOutput()));
        }
    }

    private function getDirectorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $result = \Illuminate\Support\Facades\Process::run(['du', '-sb', $path]);

        if (! $result->successful()) {
            return 0;
        }

        return (int) explode("\t", trim($result->output()))[0];
    }
}
