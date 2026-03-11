<?php

namespace Tests\Feature\Services;

use App\Contracts\GameHandler;
use App\GameManager;
use App\Services\Installers\HttpGameInstaller;
use App\Services\Installers\InstallerResolver;
use App\Services\Installers\SteamGameInstaller;
use Tests\TestCase;

class InstallerResolverTest extends TestCase
{
    private InstallerResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new InstallerResolver;
    }

    public function test_throws_for_handler_without_installer_interface(): void
    {
        $handler = $this->createMock(GameHandler::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No installer registered for handler');

        $this->resolver->resolve($handler);
    }

    public function test_arma3_handler_resolves_to_steam_installer(): void
    {
        $handler = app(GameManager::class)->driver('arma3');

        $installer = $this->resolver->resolve($handler);

        $this->assertInstanceOf(SteamGameInstaller::class, $installer);
    }

    public function test_reforger_handler_resolves_to_steam_installer(): void
    {
        $handler = app(GameManager::class)->driver('reforger');

        $installer = $this->resolver->resolve($handler);

        $this->assertInstanceOf(SteamGameInstaller::class, $installer);
    }

    public function test_project_zomboid_handler_resolves_to_steam_installer(): void
    {
        $handler = app(GameManager::class)->driver('projectzomboid');

        $installer = $this->resolver->resolve($handler);

        $this->assertInstanceOf(SteamGameInstaller::class, $installer);
    }

    public function test_factorio_handler_resolves_to_http_installer(): void
    {
        $handler = app(GameManager::class)->driver('factorio');

        $installer = $this->resolver->resolve($handler);

        $this->assertInstanceOf(HttpGameInstaller::class, $installer);
    }
}
