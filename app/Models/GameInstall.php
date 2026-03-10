<?php

namespace App\Models;

use App\Enums\GameType;
use App\Enums\InstallationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property GameType $game_type
 * @property InstallationStatus $installation_status
 */
class GameInstall extends Model
{
    /** @use HasFactory<\Database\Factories\GameInstallFactory> */
    use HasFactory;

    protected $fillable = [
        'game_type',
        'name',
        'branch',
        'build_id',
        'installation_status',
        'progress_pct',
        'disk_size_bytes',
        'installed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'game_type' => GameType::class,
            'installation_status' => InstallationStatus::class,
            'progress_pct' => 'integer',
            'disk_size_bytes' => 'integer',
            'installed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<GameInstall>  $query
     */
    public function scopeForGame(Builder $query, GameType $gameType): Builder
    {
        return $query->where('game_type', $gameType);
    }

    /** @return HasMany<Server, $this> */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /**
     * Get the installation directory path for this game install.
     */
    public function getInstallationPath(): string
    {
        return config('arma.games_base_path').'/'.$this->id;
    }
}
