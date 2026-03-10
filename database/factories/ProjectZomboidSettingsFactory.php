<?php

namespace Database\Factories;

use App\Models\ProjectZomboidSettings;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectZomboidSettings>
 */
class ProjectZomboidSettingsFactory extends Factory
{
    protected $model = ProjectZomboidSettings::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory()->forProjectZomboid(),
            'pvp' => true,
            'pause_empty' => true,
            'global_chat' => true,
            'open' => true,
            'map' => 'Muldraugh, KY',
            'safety_system' => true,
            'show_safety' => true,
            'sleep_allowed' => false,
            'sleep_needed' => false,
            'announce_death' => false,
            'do_lua_checksum' => true,
            'max_accounts_per_user' => 0,
            'login_queue_enabled' => false,
            'deny_login_on_overloaded_server' => true,
        ];
    }
}
