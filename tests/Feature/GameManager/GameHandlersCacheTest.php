<?php

namespace Tests\Feature\GameManager;

use App\Contracts\GameHandler;
use App\GameHandlers\Arma3Handler;
use App\GameHandlers\DayZHandler;
use App\GameHandlers\ReforgerHandler;
use App\GameManager;
use App\Providers\GameServiceProvider;
use Tests\TestCase;

class GameHandlersCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure no stale cache from previous tests.
        @unlink(GameServiceProvider::cachePath());
    }

    protected function tearDown(): void
    {
        // Always clean up the cache file after each test.
        @unlink(GameServiceProvider::cachePath());

        parent::tearDown();
    }

    public function test_cache_command_writes_manifest_file(): void
    {
        $this->assertFileDoesNotExist(GameServiceProvider::cachePath());

        $this->artisan('game-handlers:cache')
            ->expectsOutputToContain('Game handlers cached successfully')
            ->assertExitCode(0);

        $this->assertFileExists(GameServiceProvider::cachePath());
    }

    public function test_cache_manifest_contains_all_handlers(): void
    {
        $this->artisan('game-handlers:cache')->assertExitCode(0);

        $manifest = require GameServiceProvider::cachePath();

        $this->assertArrayHasKey('arma3', $manifest);
        $this->assertArrayHasKey('reforger', $manifest);
        $this->assertArrayHasKey('dayz', $manifest);
        $this->assertSame(Arma3Handler::class, $manifest['arma3']);
        $this->assertSame(ReforgerHandler::class, $manifest['reforger']);
        $this->assertSame(DayZHandler::class, $manifest['dayz']);
    }

    public function test_clear_command_removes_manifest_file(): void
    {
        $this->artisan('game-handlers:cache')->assertExitCode(0);
        $this->assertFileExists(GameServiceProvider::cachePath());

        $this->artisan('game-handlers:clear')
            ->expectsOutputToContain('Game handler cache cleared successfully')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist(GameServiceProvider::cachePath());
    }

    public function test_clear_command_succeeds_when_no_cache_exists(): void
    {
        $this->assertFileDoesNotExist(GameServiceProvider::cachePath());

        $this->artisan('game-handlers:clear')->assertExitCode(0);
    }

    public function test_cache_command_clears_stale_cache_before_writing(): void
    {
        // Write a stale manifest.
        file_put_contents(
            GameServiceProvider::cachePath(),
            '<?php return ["stale" => "StaleHandler"];'.PHP_EOL,
        );

        $this->artisan('game-handlers:cache')->assertExitCode(0);

        $manifest = require GameServiceProvider::cachePath();

        $this->assertArrayNotHasKey('stale', $manifest);
        $this->assertArrayHasKey('arma3', $manifest);
    }

    public function test_discover_handlers_returns_correct_map(): void
    {
        $handlers = GameServiceProvider::discoverHandlers();

        $this->assertCount(4, $handlers);

        foreach ($handlers as $key => $class) {
            $this->assertIsString($key);
            $this->assertTrue(
                is_subclass_of($class, GameHandler::class),
                "{$class} should implement GameHandler",
            );
        }
    }

    public function test_handler_map_reads_from_cache_when_available(): void
    {
        // Write a custom manifest to prove it's read from cache, not discovered.
        file_put_contents(
            GameServiceProvider::cachePath(),
            '<?php return ["cached_key" => "App\\\\GameHandlers\\\\Arma3Handler"];'.PHP_EOL,
        );

        $map = GameServiceProvider::handlerMap();

        $this->assertArrayHasKey('cached_key', $map);
        $this->assertSame('App\\GameHandlers\\Arma3Handler', $map['cached_key']);
    }

    public function test_handler_map_falls_back_to_discovery_without_cache(): void
    {
        $this->assertFileDoesNotExist(GameServiceProvider::cachePath());

        $map = GameServiceProvider::handlerMap();

        $this->assertArrayHasKey('arma3', $map);
        $this->assertArrayHasKey('reforger', $map);
        $this->assertArrayHasKey('dayz', $map);
    }

    public function test_game_manager_works_with_cached_manifest(): void
    {
        $this->artisan('game-handlers:cache')->assertExitCode(0);

        // Rebuild the singleton so it reads from the cache.
        $this->app->forgetInstance(GameManager::class);
        $manager = $this->app->make(GameManager::class);

        $this->assertInstanceOf(Arma3Handler::class, $manager->driver('arma3'));
        $this->assertInstanceOf(ReforgerHandler::class, $manager->driver('reforger'));
        $this->assertInstanceOf(DayZHandler::class, $manager->driver('dayz'));
    }

    public function test_optimize_command_caches_game_handlers(): void
    {
        @unlink(GameServiceProvider::cachePath());

        $this->artisan('optimize')->assertExitCode(0);

        $this->assertFileExists(GameServiceProvider::cachePath());

        // Clean up other optimize artifacts.
        $this->artisan('optimize:clear');
    }
}
