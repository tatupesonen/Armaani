<?php

namespace App\Services;

use App\Models\SteamAccount;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class SteamCmdService
{
    protected string $steamcmdPath;

    public function __construct()
    {
        $this->steamcmdPath = config('arma.steamcmd_path');
    }

    /**
     * Build and run a SteamCMD command to install or update the Arma 3 server,
     * streaming output line-by-line to the given callback.
     *
     * The callback receives each output line as a string.
     *
     * @param  callable(string): void  $onOutput
     */
    public function installServer(string $installDir, string $branch = 'public', ?callable $onOutput = null): ProcessResult
    {
        $args = $this->baseArgs($installDir);

        $appUpdate = '+app_update '.config('arma.server_app_id');

        if ($branch !== 'public') {
            $appUpdate .= ' -beta '.$branch;
        }

        $args[] = $appUpdate.' validate';
        $args[] = '+quit';

        if ($onOutput === null) {
            return $this->run($args);
        }

        return Process::timeout(7200)
            ->run(array_merge([$this->steamcmdPath], $args), function (string $type, string $output) use ($onOutput): void {
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
    public function startDownloadMod(string $installDir, int $workshopId): InvokedProcess
    {
        $args = $this->baseArgs($installDir);
        $args[] = '+workshop_download_item '.config('arma.game_id').' '.$workshopId.' validate';
        $args[] = '+quit';

        return Process::timeout(3600)
            ->start(array_merge([$this->steamcmdPath], $args));
    }

    /**
     * Start a batched SteamCMD workshop mod download asynchronously.
     * Stacks multiple +workshop_download_item commands in a single SteamCMD invocation
     * so authentication and initialization only happen once per batch.
     *
     * @param  list<int>  $workshopIds
     */
    public function startBatchDownloadMods(string $installDir, array $workshopIds): InvokedProcess
    {
        $args = $this->baseArgs($installDir);

        $gameId = config('arma.game_id');

        foreach ($workshopIds as $workshopId) {
            $args[] = '+workshop_download_item '.$gameId.' '.$workshopId.' validate';
        }

        $args[] = '+quit';

        // Scale timeout by number of mods: 1 hour per mod in the batch
        $timeout = max(3600, count($workshopIds) * 3600);

        return Process::timeout($timeout)
            ->start(array_merge([$this->steamcmdPath], $args));
    }

    /**
     * Validate that the given Steam credentials work with SteamCMD.
     */
    public function validateCredentials(string $username, string $password): bool
    {
        $result = Process::timeout(60)->run([
            $this->steamcmdPath,
            '+login', $username, $password,
            '+quit',
        ]);

        return $result->successful();
    }

    /**
     * @param  list<string>  $args
     */
    protected function run(array $args): ProcessResult
    {
        return Process::timeout(3600)
            ->run(array_merge([$this->steamcmdPath], $args));
    }

    /**
     * Build the common SteamCMD arguments (install dir + login).
     *
     * @return list<string>
     */
    protected function baseArgs(string $installDir): array
    {
        $account = SteamAccount::current();

        if (! $account) {
            throw new RuntimeException('No Steam account configured. Please configure Steam credentials in Settings.');
        }

        return [
            '+force_install_dir', $installDir,
            '+login', $account->username, $account->password,
        ];
    }
}
