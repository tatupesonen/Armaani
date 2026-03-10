<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReforgerSettings extends Model
{
    /** @use HasFactory<\Database\Factories\ReforgerSettingsFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
        'scenario_id',
        'third_person_view_enabled',
        'backend_log_enabled',
        'max_fps',
        'cross_platform',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'third_person_view_enabled' => 'boolean',
            'backend_log_enabled' => 'boolean',
            'max_fps' => 'integer',
            'cross_platform' => 'boolean',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
