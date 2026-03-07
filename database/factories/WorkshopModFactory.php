<?php

namespace Database\Factories;

use App\Enums\InstallationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkshopMod>
 */
class WorkshopModFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workshop_id' => fake()->unique()->numberBetween(100000, 9999999),
            'name' => fake()->words(3, true),
            'file_size' => fake()->numberBetween(1000000, 500000000),
            'installation_status' => InstallationStatus::Queued,
            'installed_at' => null,
        ];
    }

    public function installed(): static
    {
        return $this->state(fn (): array => [
            'installation_status' => InstallationStatus::Installed,
            'installed_at' => now(),
            'steam_updated_at' => now()->subDay(),
        ]);
    }

    public function outdated(): static
    {
        return $this->state(fn (): array => [
            'installation_status' => InstallationStatus::Installed,
            'installed_at' => now()->subDays(3),
            'steam_updated_at' => now(),
        ]);
    }

    public function installing(): static
    {
        return $this->state(fn (): array => [
            'installation_status' => InstallationStatus::Installing,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'installation_status' => InstallationStatus::Failed,
        ]);
    }
}
