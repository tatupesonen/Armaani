<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

trait InteractsWithFileSystem
{
    protected function getDirectorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $result = Process::run(['du', '-sb', $path]);

        if (! $result->successful()) {
            return 0;
        }

        return (int) explode("\t", trim($result->output()))[0];
    }

    protected function convertToLowercase(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $newName = dirname($item->getPathname()).'/'.strtolower($item->getFilename());

            if ($item->getPathname() !== $newName) {
                rename($item->getPathname(), $newName);
            }
        }
    }
}
