<?php

namespace App\Services\Mod;

use App\Models\ReforgerScenario;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReforgerScenarioService
{
    private const PROCESS_TIMEOUT_SECONDS = 30;

    private const DELIMITER = '--------------------------------------------------';

    /**
     * Get stored scenarios for a server from the database.
     * If none exist yet, runs discovery automatically.
     *
     * @return array<int, array{value: string, name: string, isOfficial: bool}>
     */
    public function getScenarios(Server $server): array
    {
        $scenarios = $server->reforgerScenarios;

        if ($scenarios->isEmpty()) {
            return $this->refreshScenarios($server);
        }

        return $scenarios->map(fn (ReforgerScenario $s) => [
            'value' => $s->value,
            'name' => $s->name,
            'isOfficial' => $s->is_official,
        ])->all();
    }

    /**
     * Run scenario discovery and upsert results into the database.
     *
     * @return array<int, array{value: string, name: string, isOfficial: bool}>
     */
    public function refreshScenarios(Server $server): array
    {
        $discovered = $this->discoverScenarios($server);

        if (! empty($discovered)) {
            DB::transaction(function () use ($server, $discovered): void {
                $server->reforgerScenarios()->delete();

                $server->reforgerScenarios()->insert(
                    array_map(fn (array $scenario): array => [
                        'server_id' => $server->id,
                        'value' => $scenario['value'],
                        'name' => $scenario['name'],
                        'is_official' => $scenario['isOfficial'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $discovered),
                );
            });
        }

        return $discovered;
    }

    /**
     * Run the Reforger server binary with -listScenarios and parse the output.
     * Uses -profile to include the server's downloaded mod addons for mod scenario discovery.
     *
     * @return array<int, array{value: string, name: string, isOfficial: bool}>
     */
    protected function discoverScenarios(Server $server): array
    {
        $gameInstall = $server->gameInstall;
        $binaryPath = $gameInstall->getInstallationPath().'/ArmaReforgerServer';

        if (! file_exists($binaryPath)) {
            Log::warning("[ReforgerScenarios] Binary not found: {$binaryPath}");

            return [];
        }

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $profilePath = $server->getProfilesPath();

        $process = proc_open(
            [$binaryPath, '-listScenarios', '-logStats', '1', '-profile', $profilePath],
            $descriptors,
            $pipes,
            $gameInstall->getInstallationPath(),
        );

        if (! is_resource($process)) {
            Log::error('[ReforgerScenarios] Failed to start scenario discovery process');

            return [];
        }

        $scenarios = $this->parseOutput($pipes[1], $process);

        // Clean up
        if (is_resource($pipes[1])) {
            fclose($pipes[1]);
        }

        // Force kill in case it's still running
        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process, 9);
        }
        proc_close($process);

        Log::info('[ReforgerScenarios] Discovered '.count($scenarios).' scenarios');

        return $scenarios;
    }

    /**
     * Parse the -listScenarios output stream.
     *
     * Output is delimited by lines of dashes.
     * Delimiters 2-3 contain official scenarios, 4-5 contain workshop scenarios.
     * After delimiter 5, we stop and kill the process.
     *
     * @param  resource  $pipe
     * @param  resource  $process
     * @return array<int, array{value: string, name: string, isOfficial: bool}>
     */
    protected function parseOutput($pipe, $process): array
    {
        $scenarios = [];
        $delimiterCount = 0;
        $startTime = time();

        stream_set_blocking($pipe, false);

        while (true) {
            // Timeout protection
            if ((time() - $startTime) > self::PROCESS_TIMEOUT_SECONDS) {
                Log::warning('[ReforgerScenarios] Process timed out after '.self::PROCESS_TIMEOUT_SECONDS.'s');
                break;
            }

            $line = fgets($pipe);

            if ($line === false) {
                $status = proc_get_status($process);
                if (! $status['running']) {
                    break;
                }
                usleep(50000); // 50ms

                continue;
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_contains($line, self::DELIMITER)) {
                $delimiterCount++;

                if ($delimiterCount >= 5) {
                    break;
                }

                continue;
            }

            // Between delimiters 2-3: official scenarios
            // Between delimiters 4-5: workshop/mod scenarios
            if ($delimiterCount === 2 || $delimiterCount === 4) {
                $isOfficial = $delimiterCount === 2;
                $scenario = $this->parseLine($line);

                if ($scenario !== null) {
                    $scenarios[] = [
                        ...$scenario,
                        'isOfficial' => $isOfficial,
                    ];
                }
            }
        }

        return $scenarios;
    }

    /**
     * Parse a single scenario line into value and name.
     *
     * Format: "{HASH}Missions/ScenarioName.conf  Optional Human Name"
     *
     * @return array{value: string, name: string}|null
     */
    protected function parseLine(string $line): ?array
    {
        // Strip any leading log prefix (e.g. "SCRIPT       : ")
        if (preg_match('/:\s+(\{.+)/', $line, $prefixMatch)) {
            $line = $prefixMatch[1];
        }

        // Must start with a resource GUID
        if (! preg_match('/^\{[0-9A-F]{16}\}/', $line)) {
            return null;
        }

        // Split on whitespace: first part is the scenario ID, rest is the name
        $parts = preg_split('/\s+/', $line, 2);
        $value = $parts[0];
        $name = isset($parts[1]) ? trim($parts[1], " \t\n\r\0\x0B()") : $value;

        return [
            'value' => $value,
            'name' => $name,
        ];
    }
}
