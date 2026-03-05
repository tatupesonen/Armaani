<?php

namespace App\Console\Commands;

use App\Events\ServerLogOutput;
use App\Models\Server;
use Illuminate\Console\Command;

class TailServerLog extends Command
{
    protected $signature = 'server:tail-log {server}';

    protected $description = 'Tail a running server log file and broadcast new lines via WebSocket';

    public function handle(): int
    {
        $server = Server::query()->findOrFail($this->argument('server'));
        $logPath = $server->getProfilesPath().'/server.log';

        if (! file_exists($logPath)) {
            $this->error("Log file not found: {$logPath}");

            return self::FAILURE;
        }

        $this->info("Tailing log for server '{$server->name}' (ID: {$server->id})");
        $this->info("File: {$logPath}");

        $handle = fopen($logPath, 'r');

        if (! $handle) {
            $this->error("Could not open log file: {$logPath}");

            return self::FAILURE;
        }

        fseek($handle, 0, SEEK_END);

        while (true) {
            $line = fgets($handle);

            if ($line !== false) {
                $trimmed = rtrim($line, "\r\n");

                if ($trimmed !== '') {
                    ServerLogOutput::dispatch($server->id, $trimmed);
                }
            } else {
                clearstatcache(false, $logPath);
                $currentSize = filesize($logPath);
                $position = ftell($handle);

                if ($currentSize < $position) {
                    fseek($handle, 0);
                }

                usleep(250_000);
            }
        }

        // @codeCoverageIgnoreStart
        fclose($handle);

        return self::SUCCESS;
        // @codeCoverageIgnoreEnd
    }
}
