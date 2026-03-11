<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Arma3Settings extends Model
{
    /** @use HasFactory<\Database\Factories\Arma3SettingsFactory> */
    use HasFactory;

    protected $table = 'arma3_settings';

    protected $fillable = [
        'server_id',
        // Server options (moved from servers table)
        'admin_password',
        'verify_signatures',
        'allowed_file_patching',
        'battle_eye',
        'persistent',
        'von_enabled',
        'additional_server_options',
        // Difficulty settings
        'reduced_damage',
        'group_indicators',
        'friendly_tags',
        'enemy_tags',
        'detected_mines',
        'commands',
        'waypoints',
        'tactical_ping',
        'weapon_info',
        'stance_indicator',
        'stamina_bar',
        'weapon_crosshair',
        'vision_aid',
        'third_person_view',
        'camera_shake',
        'score_table',
        'death_messages',
        'von_id',
        'map_content',
        'auto_report',
        'ai_level_preset',
        'skill_ai',
        'precision_ai',
        // Network settings
        'max_msg_send',
        'max_size_guaranteed',
        'max_size_nonguaranteed',
        'min_bandwidth',
        'max_bandwidth',
        'min_error_to_send',
        'min_error_to_send_near',
        'max_packet_size',
        'max_custom_file_size',
        'terrain_grid',
        'view_distance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Server options
            'verify_signatures' => 'boolean',
            'allowed_file_patching' => 'boolean',
            'battle_eye' => 'boolean',
            'persistent' => 'boolean',
            'von_enabled' => 'boolean',
            // Difficulty
            'reduced_damage' => 'boolean',
            'group_indicators' => 'integer',
            'friendly_tags' => 'integer',
            'enemy_tags' => 'integer',
            'detected_mines' => 'integer',
            'commands' => 'integer',
            'waypoints' => 'integer',
            'tactical_ping' => 'integer',
            'weapon_info' => 'integer',
            'stance_indicator' => 'integer',
            'stamina_bar' => 'boolean',
            'weapon_crosshair' => 'boolean',
            'vision_aid' => 'boolean',
            'third_person_view' => 'integer',
            'camera_shake' => 'boolean',
            'score_table' => 'boolean',
            'death_messages' => 'boolean',
            'von_id' => 'boolean',
            'map_content' => 'boolean',
            'auto_report' => 'boolean',
            'ai_level_preset' => 'integer',
            'skill_ai' => 'decimal:2',
            'precision_ai' => 'decimal:2',
            // Network
            'max_msg_send' => 'integer',
            'max_size_guaranteed' => 'integer',
            'max_size_nonguaranteed' => 'integer',
            'min_bandwidth' => 'integer',
            'max_bandwidth' => 'integer',
            'min_error_to_send' => 'decimal:4',
            'min_error_to_send_near' => 'decimal:4',
            'max_packet_size' => 'integer',
            'max_custom_file_size' => 'integer',
            'terrain_grid' => 'decimal:4',
            'view_distance' => 'integer',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
