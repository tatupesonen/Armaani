<?php

namespace Database\Factories;

use App\Enums\GameType;
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
            'game_type' => GameType::Arma3,
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

    public function forReforger(): static
    {
        return $this->state(fn (): array => [
            'game_type' => GameType::ArmaReforger,
            'game_install_id' => GameInstall::factory()->installed()->reforger(),
            'port' => 2001,
            'query_port' => 17777,
        ]);
    }

    public function forDayZ(): static
    {
        return $this->state(fn (): array => [
            'game_type' => GameType::DayZ,
            'game_install_id' => GameInstall::factory()->installed()->dayz(),
            'query_port' => 27016,
        ]);
    }
}
