<?php

namespace Tests\Concerns;

use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\GameManager;
use Mockery;

trait MocksGameManager
{
    protected function mockGameManager(?GameType $gameType = null): void
    {
        $handler = Mockery::mock(GameHandler::class);
        $handler->shouldReceive('supportsHeadlessClients')->andReturn($gameType === GameType::Arma3);
        $handler->shouldReceive('getBackupFilePath')->andReturn(
            $gameType === GameType::Arma3 ? '/fake/backup/path' : null
        );
        $handler->shouldReceive('getBootDetectionStrings')->andReturn(
            $gameType === GameType::Arma3 ? ['Connected to Steam servers'] : []
        );
        $handler->shouldReceive('getCrashDetectionStrings')->andReturn([]);
        $handler->shouldReceive('getModDownloadStartedString')->andReturnNull();
        $handler->shouldReceive('getModDownloadFinishedString')->andReturnNull();
        $handler->shouldReceive('getServerLogPath')->andReturn('/tmp/fake.log');
        $handler->shouldReceive('getBinaryPath')->andReturn('/tmp/fake_binary');
        $handler->shouldReceive('getProfileName')->andReturn('arma3_1');
        $handler->shouldReceive('buildLaunchCommand')->andReturn(['/tmp/fake_binary', '-port=2302']);
        $handler->shouldReceive('generateConfigFiles')->andReturnNull();
        $handler->shouldReceive('symlinkMods')->andReturnNull();
        $handler->shouldReceive('symlinkMissions')->andReturnNull();
        $handler->shouldReceive('copyBiKeys')->andReturnNull();
        $handler->shouldReceive('buildHeadlessClientCommand')->andReturn(
            $gameType === GameType::Arma3 ? ['/tmp/fake_binary', '-client'] : null
        );
        $handler->shouldReceive('getBackupDownloadFilename')->andReturn('backup_file');
        $handler->shouldReceive('serverValidationRules')->andReturn([]);
        $handler->shouldReceive('settingsValidationRules')->andReturn([]);
        $handler->shouldReceive('gameType')->andReturn($gameType ?? GameType::Arma3);

        $manager = Mockery::mock(GameManager::class);
        $manager->shouldReceive('for')->andReturn($handler);
        $manager->shouldReceive('driver')->andReturn($handler);
        $this->app->instance(GameManager::class, $manager);
    }
}
