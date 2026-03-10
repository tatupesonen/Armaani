<?php

namespace App;

use App\Attributes\HandlesGame;
use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\Models\Server;
use Illuminate\Support\Manager;
use ReflectionClass;

class GameManager extends Manager
{
    /** @var array<string, class-string<GameHandler>>|null */
    private ?array $handlerMap = null;

    public function getDefaultDriver(): string
    {
        return GameType::Arma3->value;
    }

    /**
     * Resolve the handler for a server's game type.
     */
    public function for(Server $server): GameHandler
    {
        return $this->driver($server->game_type->value);
    }

    /**
     * Create a driver instance by discovering handlers via the HandlesGame attribute.
     */
    protected function createDriver(mixed $driver): GameHandler
    {
        $map = $this->discoverHandlers();

        if (! isset($map[$driver])) {
            throw new \InvalidArgumentException("No handler registered for [{$driver}].");
        }

        return new $map[$driver];
    }

    /**
     * Scan the GameHandlers directory and build a map of game type values to handler classes.
     * Results are cached for the lifetime of this manager instance.
     *
     * @return array<string, class-string<GameHandler>>
     */
    private function discoverHandlers(): array
    {
        if ($this->handlerMap !== null) {
            return $this->handlerMap;
        }

        $this->handlerMap = [];
        $path = app_path('GameHandlers');

        foreach (glob($path.'/*.php') as $file) {
            $class = 'App\\GameHandlers\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($class) || ! is_subclass_of($class, GameHandler::class)) {
                continue;
            }

            $attributes = (new ReflectionClass($class))->getAttributes(HandlesGame::class);

            if ($attributes === []) {
                continue;
            }

            $gameType = $attributes[0]->newInstance()->gameType;
            $this->handlerMap[$gameType->value] = $class;
        }

        return $this->handlerMap;
    }
}
