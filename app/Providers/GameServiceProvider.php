<?php

namespace App\Providers;

use App\Attributes\Beta;
use App\Contracts\GameHandler;
use App\Contracts\SupportsRegisteredMods;
use App\Contracts\SupportsScenarios;
use App\GameManager;
use App\Models\ModPreset;
use App\Models\ReforgerScenario;
use App\Models\Server;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class GameServiceProvider extends ServiceProvider
{
    /**
     * Register the GameManager singleton.
     *
     * Handler registration is deferred into the singleton closure so no
     * filesystem scanning or class instantiation happens unless the
     * GameManager is actually resolved from the container.
     */
    public function register(): void
    {
        $this->app->singleton(GameManager::class, function (Container $app) {
            $manager = new GameManager($app);

            foreach (static::handlerMap() as $key => $class) {
                $manager->extend($key, fn () => $app->make($class));
            }

            return $manager;
        });
    }

    /**
     * Bootstrap services.
     *
     * Dynamically registers Eloquent relationships on Server and ModPreset
     * based on what each game handler declares. This means adding a new game
     * handler automatically wires up its settings/mod relationships without
     * editing the Server or ModPreset models.
     */
    public function boot(): void
    {
        $this->optimizes(
            optimize: 'game-handlers:cache',
            clear: 'game-handlers:clear',
        );

        $this->registerDynamicRelationships();
    }

    /**
     * Register settings and mod relationships from all game handlers.
     */
    protected function registerDynamicRelationships(): void
    {
        /** @var GameManager $gameManager */
        $gameManager = $this->app->make(GameManager::class);

        foreach ($gameManager->allHandlers() as $handler) {
            // Register settings HasOne on Server
            $settingsRelation = $handler->settingsRelationName();
            $settingsModel = $handler->settingsModelClass();

            if ($settingsRelation !== null && $settingsModel !== null) {
                Server::resolveRelationUsing($settingsRelation, function (Server $server) use ($settingsModel) {
                    return $server->hasOne($settingsModel);
                });
            }

            // Register registered mod BelongsToMany on ModPreset
            if ($handler instanceof SupportsRegisteredMods) {
                ModPreset::resolveRelationUsing(
                    $handler->registeredModRelationName(),
                    function (ModPreset $preset) use ($handler) {
                        return $preset->belongsToMany(
                            $handler->registeredModModelClass(),
                            $handler->registeredModPivotTable(),
                        );
                    },
                );
            }

            // Register scenario HasMany on Server
            if ($handler instanceof SupportsScenarios) {
                $scenarioRelation = $handler->value().'Scenarios';
                Server::resolveRelationUsing($scenarioRelation, function (Server $server) {
                    return $server->hasMany(ReforgerScenario::class);
                });
            }
        }
    }

    /**
     * Get the handler map, from cache if available.
     *
     * @return array<string, class-string<GameHandler>>
     */
    public static function handlerMap(): array
    {
        $cached = static::cachePath();

        if (file_exists($cached)) {
            return require $cached;
        }

        return static::discoverHandlers();
    }

    /**
     * Discover all game handler classes by scanning the GameHandlers directory.
     *
     * Each handler is resolved from the container to read its driver key via value().
     * The returned map is [driverKey => FQCN].
     *
     * @return array<string, class-string<GameHandler>>
     */
    public static function discoverHandlers(): array
    {
        $handlers = [];
        $isProduction = app()->isProduction();

        foreach (glob(app_path('GameHandlers/*.php')) ?: [] as $file) {
            /** @var class-string $class */
            $class = 'App\\GameHandlers\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($class) || ! is_subclass_of($class, GameHandler::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            if ($isProduction && $reflection->getAttributes(Beta::class) !== []) {
                continue;
            }

            /** @var GameHandler $handler */
            $handler = app()->make($class);
            $handlers[$handler->value()] = $class;
        }

        return $handlers;
    }

    /**
     * Get the path to the cached game handlers manifest.
     */
    public static function cachePath(): string
    {
        return app()->bootstrapPath('cache/game-handlers.php');
    }
}
