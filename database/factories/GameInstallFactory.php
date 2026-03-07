<?php

namespace Database\Factories;

use App\Enums\InstallationStatus;
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
}
