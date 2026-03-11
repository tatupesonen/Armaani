<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Arma3Settings>
 */
class Arma3SettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            // Server options
            'admin_password' => null,
            'verify_signatures' => true,
            'allowed_file_patching' => false,
            'battle_eye' => true,
            'persistent' => false,
            'von_enabled' => true,
            'additional_server_options' => null,
            // Difficulty settings
            'reduced_damage' => false,
            'group_indicators' => 2,
            'friendly_tags' => 2,
            'enemy_tags' => 0,
            'detected_mines' => 2,
            'commands' => 2,
            'waypoints' => 2,
            'tactical_ping' => 3,
            'weapon_info' => 2,
            'stance_indicator' => 2,
            'stamina_bar' => true,
            'weapon_crosshair' => true,
            'vision_aid' => false,
            'third_person_view' => 1,
            'camera_shake' => true,
            'score_table' => true,
            'death_messages' => true,
            'von_id' => true,
            'map_content' => true,
            'auto_report' => false,
            'ai_level_preset' => 1,
            'skill_ai' => 0.50,
            'precision_ai' => 0.50,
            // Network settings
            'max_msg_send' => 128,
            'max_size_guaranteed' => 512,
            'max_size_nonguaranteed' => 256,
            'min_bandwidth' => 131072,
            'max_bandwidth' => 10000000000,
            'min_error_to_send' => 0.001,
            'min_error_to_send_near' => 0.01,
            'max_packet_size' => 1400,
            'max_custom_file_size' => 0,
            'terrain_grid' => 25.0,
            'view_distance' => 0,
        ];
    }
}
