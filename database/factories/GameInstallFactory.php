<?php

namespace Database\Factories;

use App\Enums\InstallationStatus;
use App\GameManager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameInstall>
 */
class GameInstallFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_type' => 'arma3',
            'name' => 'Arma 3 Server',
            'branch' => 'public',
            'installation_status' => InstallationStatus::Queued,
            'progress_pct' => 0,
            'disk_size_bytes' => 0,
            'installed_at' => null,
        ];
    }

    public function installed(): static
    {
        return $this->state(fn (): array => [
            'installation_status' => InstallationStatus::Installed,
            'installed_at' => now(),
            'build_id' => (string) fake()->numberBetween(10000000, 99999999),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'installation_status' => InstallationStatus::Failed,
            'installed_at' => null,
        ]);
    }

    /**
     * Create a game install for any registered game type, pulling the label from the handler.
     */
    public function forGame(string $gameType): static
    {
        $handler = app(GameManager::class)->driver($gameType);

        return $this->state(fn (): array => [
            'game_type' => $gameType,
            'name' => $handler->label().' Server',
        ]);
    }

    public function reforger(): static
    {
        return $this->forGame('reforger');
    }

    public function dayz(): static
    {
        return $this->forGame('dayz');
    }

    public function projectZomboid(): static
    {
        return $this->forGame('projectzomboid');
    }
}
