<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectZomboidSettings extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectZomboidSettingsFactory> */
    use HasFactory;

    protected $table = 'project_zomboid_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',
        'pvp',
        'pause_empty',
        'global_chat',
        'open',
        'map',
        'safety_system',
        'show_safety',
        'sleep_allowed',
        'sleep_needed',
        'announce_death',
        'do_lua_checksum',
        'max_accounts_per_user',
        'login_queue_enabled',
        'deny_login_on_overloaded_server',
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
            'pvp' => 'boolean',
            'pause_empty' => 'boolean',
            'global_chat' => 'boolean',
            'open' => 'boolean',
            'safety_system' => 'boolean',
            'show_safety' => 'boolean',
            'sleep_allowed' => 'boolean',
            'sleep_needed' => 'boolean',
            'announce_death' => 'boolean',
            'do_lua_checksum' => 'boolean',
            'login_queue_enabled' => 'boolean',
            'deny_login_on_overloaded_server' => 'boolean',
        ];
    }
}
