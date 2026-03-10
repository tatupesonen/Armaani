<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DifficultySettings extends Model
{
    /** @use HasFactory<\Database\Factories\DifficultySettingsFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
