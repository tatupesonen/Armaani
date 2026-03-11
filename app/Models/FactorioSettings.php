<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactorioSettings extends Model
{
    /** @use HasFactory<\Database\Factories\FactorioSettingsFactory> */
    use HasFactory;

    protected $table = 'factorio_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',

        // RCON
        'rcon_password',

        // Server settings
        'visibility_public',
        'visibility_lan',
        'require_user_verification',
        'max_upload_kbps',
        'max_heartbeats_per_second',
        'ignore_player_limit_for_returning',
        'allow_commands',
        'autosave_interval',
        'autosave_slots',
        'afk_autokick_interval',
        'auto_pause',
        'only_admins_can_pause',
        'autosave_only_on_server',
        'non_blocking_saving',
        'tags',

        // Map generation: resources
        'coal_frequency',
        'coal_size',
        'coal_richness',
        'copper_ore_frequency',
        'copper_ore_size',
        'copper_ore_richness',
        'crude_oil_frequency',
        'crude_oil_size',
        'crude_oil_richness',
        'enemy_base_frequency',
        'enemy_base_size',
        'enemy_base_richness',
        'iron_ore_frequency',
        'iron_ore_size',
        'iron_ore_richness',
        'stone_frequency',
        'stone_size',
        'stone_richness',
        'trees_frequency',
        'trees_size',
        'trees_richness',
        'uranium_ore_frequency',
        'uranium_ore_size',
        'uranium_ore_richness',

        // Map generation: terrain
        'map_width',
        'map_height',
        'starting_area',
        'peaceful_mode',
        'map_seed',
        'water',
        'terrain_segmentation',
        'cliff_elevation_0',
        'cliff_elevation_interval',
        'cliff_richness',

        // Map settings: gameplay
        'pollution_enabled',
        'evolution_enabled',
        'evolution_time_factor',
        'evolution_destroy_factor',
        'evolution_pollution_factor',
        'expansion_enabled',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility_public' => 'boolean',
            'visibility_lan' => 'boolean',
            'require_user_verification' => 'boolean',
            'max_upload_kbps' => 'integer',
            'max_heartbeats_per_second' => 'integer',
            'ignore_player_limit_for_returning' => 'boolean',
            'autosave_interval' => 'integer',
            'autosave_slots' => 'integer',
            'afk_autokick_interval' => 'integer',
            'auto_pause' => 'boolean',
            'only_admins_can_pause' => 'boolean',
            'autosave_only_on_server' => 'boolean',
            'non_blocking_saving' => 'boolean',
            'map_width' => 'integer',
            'map_height' => 'integer',
            'peaceful_mode' => 'boolean',
            'cliff_elevation_0' => 'decimal:2',
            'cliff_elevation_interval' => 'decimal:2',
            'pollution_enabled' => 'boolean',
            'evolution_enabled' => 'boolean',
            'expansion_enabled' => 'boolean',
        ];
    }
}
