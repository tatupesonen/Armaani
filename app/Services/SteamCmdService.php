<?php

namespace App\Services;

use App\Enums\GameType;
use App\Models\SteamAccount;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Log;
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
     * Build and run a SteamCMD command to install or update a game server,
     * streaming output line-by-line to the given callback.
     *
     * The callback receives each output line as a string.
     *
     * @param  callable(string): void  $onOutput
     */
    public function installServer(string $installDir, string $branch = 'public', ?callable $onOutput = null, ?GameType $gameType = null): ProcessResult
    {
        $args = $this->baseArgs($installDir);

        $appId = ($gameType ?? GameType::default())->serverAppId();
        $appUpdate = '+app_update '.$appId;

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
                    $line = self::stripAnsi(trim($line));
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
    public function startDownloadMod(string $installDir, int $workshopId, ?GameType $gameType = null): InvokedProcess
    {
        $args = $this->baseArgs($installDir);
        $gameId = ($gameType ?? GameType::default())->gameId();
        $args[] = '+workshop_download_item '.$gameId.' '.$workshopId.' validate';
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
    public function startBatchDownloadMods(string $installDir, array $workshopIds, ?GameType $gameType = null): InvokedProcess
    {
        $args = $this->baseArgs($installDir);

        $gameId = ($gameType ?? GameType::default())->gameId();

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
     *
     * SteamCMD may return non-zero exit codes even on successful login,
     * so we parse the output text for the actual login result.
     */
    public function validateCredentials(string $username, string $password): bool
    {
        Log::info('SteamCMD credential validation starting', [
            'username' => $username,
            'steamcmd_path' => $this->steamcmdPath,
        ]);

        $result = Process::timeout(60)
            ->env(['HOME' => config('arma.home_path')])
            ->run([
                $this->steamcmdPath,
                '+login', $username, $password,
                '+quit',
            ]);

        $stdout = self::stripAnsi($result->output());
        $stderr = self::stripAnsi($result->errorOutput());

        Log::info('SteamCMD credential validation result', [
            'exit_code' => $result->exitCode(),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ]);

        $output = $stdout.' '.$stderr;

        return (bool) preg_match('/Logging in user .+\.\.\.(OK|Logged in OK)/i', $output);
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

    /**
     * Strip ANSI escape sequences (SGR codes like [0m, [1m) from a string.
     */
    public static function stripAnsi(string $text): string
    {
        return preg_replace('/\x1b\[[0-9;]*m/', '', $text) ?? $text;
    }
}
