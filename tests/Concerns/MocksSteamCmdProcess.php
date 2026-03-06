<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\InvokedProcess;
use Mockery;

trait MocksSteamCmdProcess
{
    protected function makeInvokedProcess(bool $successful = true): InvokedProcess
    {
        $processResult = Mockery::mock(ProcessResult::class);
        $processResult->shouldReceive('successful')->andReturn($successful);
        $processResult->shouldReceive('output')->andReturn('');
        $processResult->shouldReceive('errorOutput')->andReturn($successful ? '' : 'SteamCMD failed');

        $invokedProcess = Mockery::mock(InvokedProcess::class);
        $invokedProcess->shouldReceive('running')->andReturn(false);
        $invokedProcess->shouldReceive('wait')->andReturn($processResult);

        return $invokedProcess;
    }
}
