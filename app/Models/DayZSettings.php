<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayZSettings extends Model
{
    /** @use HasFactory<\Database\Factories\DayZSettingsFactory> */
    use HasFactory;

    protected $table = 'dayz_settings';

    protected $fillable = [
        'server_id',
        'respawn_time',
        'time_acceleration',
        'night_time_acceleration',
        'force_same_build',
        'third_person_view_enabled',
        'crosshair_enabled',
        'persistent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_acceleration' => 'decimal:2',
            'night_time_acceleration' => 'decimal:2',
            'force_same_build' => 'boolean',
            'third_person_view_enabled' => 'boolean',
            'crosshair_enabled' => 'boolean',
            'persistent' => 'boolean',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
