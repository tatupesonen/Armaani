<?php

namespace Tests\Concerns;

use App\GameManager;
use App\Models\GameInstall;
use App\Models\Server;

trait CreatesGameScenarios
{
    /**
     * Create a server for any registered game type, with related settings from the handler.
     */
    protected function createServer(string $gameType, array $overrides = []): Server
    {
        $handler = app(GameManager::class)->driver($gameType);

        $install = GameInstall::factory()->installed()->forGame($gameType)->create();
        $server = Server::factory()->forGame($gameType)->create(array_merge([
            'game_install_id' => $install->id,
        ], $overrides));

        $handler->createRelatedSettings($server);

        return $server->refresh();
    }

    protected function createArma3Server(array $overrides = []): Server
    {
        return $this->createServer('arma3', $overrides);
    }

    protected function createReforgerServer(array $overrides = []): Server
    {
        return $this->createServer('reforger', $overrides);
    }

    protected function createDayZServer(array $overrides = []): Server
    {
        return $this->createServer('dayz', $overrides);
    }

    protected function createProjectZomboidServer(array $overrides = []): Server
    {
        return $this->createServer('projectzomboid', $overrides);
    }
}
