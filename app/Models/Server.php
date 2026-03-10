<?php

namespace App\Models;

use App\Enums\ServerStatus;
use App\Events\ServerStatusChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * @property string $game_type
 * @property ServerStatus $status
 *
 * Dynamic relationships registered by GameServiceProvider via resolveRelationUsing:
 *
 * @method \Illuminate\Database\Eloquent\Relations\HasOne arma3Settings()
 * @method \Illuminate\Database\Eloquent\Relations\HasOne reforgerSettings()
 * @method \Illuminate\Database\Eloquent\Relations\HasOne dayzSettings()
 * @method \Illuminate\Database\Eloquent\Relations\HasOne projectzomboidSettings()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany reforgerScenarios()
 *
 * @property \App\Models\Arma3Settings|null $arma3Settings
 * @property \App\Models\ReforgerSettings|null $reforgerSettings
 * @property \App\Models\DayZSettings|null $dayzSettings
 * @property \App\Models\ProjectZomboidSettings|null $projectzomboidSettings
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReforgerScenario> $reforgerScenarios
 */
class Server extends Model
{
    /** @use HasFactory<\Database\Factories\ServerFactory> */
    use HasFactory;

    protected $fillable = [
        'game_type',
        'name',
        'port',
        'query_port',
        'max_players',
        'password',
        'admin_password',
        'description',
        'active_preset_id',
        'game_install_id',
        'status',
        'additional_params',
        'verify_signatures',
        'allowed_file_patching',
        'battle_eye',
        'persistent',
        'von_enabled',
        'additional_server_options',
        'auto_restart',
    ];

    protected $hidden = [
        'password',
        'admin_password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ServerStatus::class,
            'verify_signatures' => 'boolean',
            'allowed_file_patching' => 'boolean',
            'battle_eye' => 'boolean',
            'persistent' => 'boolean',
            'von_enabled' => 'boolean',
            'auto_restart' => 'boolean',
        ];
    }

    /** @return BelongsTo<ModPreset, $this> */
    public function activePreset(): BelongsTo
    {
        return $this->belongsTo(ModPreset::class, 'active_preset_id');
    }

    /** @return BelongsTo<GameInstall, $this> */
    public function gameInstall(): BelongsTo
    {
        return $this->belongsTo(GameInstall::class);
    }

    /** @return HasMany<ServerBackup, $this> */
    public function backups(): HasMany
    {
        return $this->hasMany(ServerBackup::class)->latest();
    }

    /**
     * @param  Builder<Server>  $query
     */
    public function scopeForGame(Builder $query, string $gameType): Builder
    {
        return $query->where('game_type', $gameType);
    }

    /**
     * Get the per-server profiles directory path.
     * Arma 3 stores .vars profiles, RPT logs, bans, and config here.
     */
    public function getProfilesPath(): string
    {
        return config('arma.servers_base_path').'/'.$this->id;
    }

    /**
     * Transition the server to a new status, broadcast the change, and log it.
     */
    public function transitionTo(ServerStatus $status): void
    {
        $this->update(['status' => $status]);
        ServerStatusChanged::dispatch($this->id, $status->value, $this->name);
        Log::info("{$this->logContext()} Status changed to {$status->value}");
    }

    /**
     * Get a formatted log context string for this server.
     */
    public function logContext(): string
    {
        return "[Server:{$this->id} '{$this->name}']";
    }
}
