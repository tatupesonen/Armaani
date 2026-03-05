<?php

namespace App\Services;

use App\Models\SteamAccount;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class SteamCmdService
{
    /**
     * Build and run a SteamCMD command to install or update the Arma 3 server,
     * streaming output line-by-line to the given callback.
     *
     * The callback receives each output line as a string.
     *
     * @param  callable(string): void  $onOutput
     */
    public function installServer(string $installDir, string $branch = 'public', ?callable $onOutput = null): \Illuminate\Contracts\Process\ProcessResult
    {
        $args = $this->baseArgs($installDir);

        if ($branch !== 'public') {
            $args[] = '+app_update '.config('arma.server_app_id').' -beta '.$branch.' validate';
        } else {
            $args[] = '+app_update '.config('arma.server_app_id').' validate';
        }

        $args[] = '+quit';

        if ($onOutput === null) {
            return $this->run($args);
        }

        $steamcmdPath = config('arma.steamcmd_path');

        return \Illuminate\Support\Facades\Process::timeout(7200)
            ->run(array_merge([$steamcmdPath], $args), function (string $type, string $output) use ($onOutput): void {
                foreach (explode("\n", $output) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $onOutput($line);
                    }
                }
            });
    }

    /**
     * Start a SteamCMD workshop mod download asynchronously.
     * Returns a pending process so the caller can poll while it runs.
     */
    public function startDownloadMod(string $installDir, int $workshopId): \Illuminate\Process\InvokedProcess
    {
        $args = $this->baseArgs($installDir);
        $args[] = '+workshop_download_item '.config('arma.game_id').' '.$workshopId.' validate';
        $args[] = '+quit';

        $steamcmdPath = config('arma.steamcmd_path');

        return \Illuminate\Support\Facades\Process::timeout(3600)
            ->start(array_merge([$steamcmdPath], $args));
    }

    /**
     * Build and run a SteamCMD command to download a single workshop mod.
     */
    public function downloadMod(string $installDir, int $workshopId): \Illuminate\Contracts\Process\ProcessResult
    {
        $args = $this->baseArgs($installDir);
        $args[] = '+workshop_download_item '.config('arma.game_id').' '.$workshopId.' validate';
        $args[] = '+quit';

        return $this->run($args);
    }

    /**
     * Validate that the given Steam credentials work with SteamCMD.
     */
    public function validateCredentials(string $username, string $password): bool
    {
        $steamcmdPath = config('arma.steamcmd_path');

        $result = Process::timeout(60)->run([
            $steamcmdPath,
            '+login', $username, $password,
            '+quit',
        ]);

        return $result->successful();
    }

    /**
     * @param  list<string>  $args
     */
    protected function run(array $args): \Illuminate\Contracts\Process\ProcessResult
    {
        $steamcmdPath = config('arma.steamcmd_path');

        return Process::timeout(3600)
            ->run(array_merge([$steamcmdPath], $args));
    }

    /**
     * Build the common SteamCMD arguments (install dir + login).
     *
     * @return list<string>
     */
    protected function baseArgs(string $installDir): array
    {
        $account = SteamAccount::query()->latest()->first();

        if (! $account) {
            throw new RuntimeException('No Steam account configured. Please configure Steam credentials in Settings.');
        }

        return [
            '+force_install_dir', $installDir,
            '+login', $account->username, $account->password,
        ];
    }
}
