<?php

namespace Database\Factories;

use App\GameManager;
use App\Models\GameInstall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server>
 */
class ServerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $port = fake()->numberBetween(2302, 2400);

        return [
            'game_type' => 'arma3',
            'name' => fake()->words(2, true).' Server',
            'port' => $port,
            'query_port' => $port + 1,
            'max_players' => fake()->randomElement([16, 32, 64, 128]),
            'password' => null,
            'admin_password' => null,
            'description' => fake()->optional()->sentence(),
            'active_preset_id' => null,
            'game_install_id' => GameInstall::factory()->installed(),
            'additional_params' => null,
            'verify_signatures' => true,
            'allowed_file_patching' => false,
            'battle_eye' => true,
            'persistent' => false,
            'von_enabled' => true,
            'additional_server_options' => null,
            'auto_restart' => false,
        ];
    }

    public function withPassword(): static
    {
        return $this->state(fn (): array => [
            'password' => fake()->password(6, 12),
            'admin_password' => fake()->password(6, 12),
        ]);
    }

    /**
     * Create a server for any registered game type, pulling defaults from the handler.
     */
    public function forGame(string $gameType): static
    {
        $handler = app(GameManager::class)->driver($gameType);

        return $this->state(fn (): array => [
            'game_type' => $gameType,
            'port' => $handler->defaultPort(),
            'query_port' => $handler->defaultQueryPort(),
            'game_install_id' => GameInstall::factory()->installed()->forGame($gameType),
        ]);
    }

    public function forReforger(): static
    {
        return $this->forGame('reforger');
    }

    public function forDayZ(): static
    {
        return $this->forGame('dayz');
    }

    public function forProjectZomboid(): static
    {
        return $this->forGame('projectzomboid');
    }
}
