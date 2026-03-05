<?php

namespace Database\Factories;

use App\Enums\GameInstallStatus;
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
            'name' => 'Arma 3 Server',
            'branch' => 'public',
            'installation_status' => GameInstallStatus::Queued,
            'installed_at' => null,
        ];
    }

    public function installed(): static
    {
        return $this->state(fn (): array => [
            'installation_status' => GameInstallStatus::Installed,
            'installed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'installation_status' => GameInstallStatus::Failed,
            'installed_at' => null,
        ]);
    }
}
