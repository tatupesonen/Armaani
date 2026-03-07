<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NetworkSettings>
 */
class NetworkSettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
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
