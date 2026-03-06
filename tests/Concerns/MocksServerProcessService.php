<?php

namespace Tests\Concerns;

use App\Enums\ServerStatus;
use App\Services\ServerProcessService;
use Mockery;

trait MocksServerProcessService
{
    protected function mockServerProcessService(ServerStatus $status = ServerStatus::Stopped): void
    {
        $mock = Mockery::mock(ServerProcessService::class);
        $mock->shouldReceive('getStatus')->andReturn($status);
        $mock->shouldReceive('isRunning')->andReturn($status === ServerStatus::Running);
        $mock->shouldReceive('start')->andReturnNull();
        $mock->shouldReceive('stop')->andReturnNull();
        $mock->shouldReceive('restart')->andReturnNull();
        $mock->shouldReceive('getRunningHeadlessClientCount')->andReturn(0);
        $mock->shouldReceive('buildLaunchCommand')->andReturn('/fake/binary --test');
        $mock->shouldReceive('getServerLogPath')->andReturn('/tmp/fake.log');
        $mock->shouldReceive('addHeadlessClient')->andReturn(0);
        $mock->shouldReceive('removeHeadlessClient')->andReturn(0);
        $this->app->instance(ServerProcessService::class, $mock);
    }
}
