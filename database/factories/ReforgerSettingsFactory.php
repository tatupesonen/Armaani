<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReforgerSettings>
 */
class ReforgerSettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory()->forReforger(),
            'admin_password' => null,
            'battle_eye' => true,
            'scenario_id' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf',
            'third_person_view_enabled' => true,
            'cross_platform' => false,
        ];
    }
}
