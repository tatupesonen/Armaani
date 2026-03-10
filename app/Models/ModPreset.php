<?php

namespace App\Models;

use App\Enums\GameType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property GameType $game_type
 */
class ModPreset extends Model
{
    /** @use HasFactory<\Database\Factories\ModPresetFactory> */
    use HasFactory;

    protected $fillable = [
        'game_type',
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'game_type' => GameType::class,
        ];
    }

    /** @return BelongsToMany<WorkshopMod, $this> */
    public function mods(): BelongsToMany
    {
        return $this->belongsToMany(WorkshopMod::class, 'mod_preset_workshop_mod');
    }

    /** @return BelongsToMany<ReforgerMod, $this> */
    public function reforgerMods(): BelongsToMany
    {
        return $this->belongsToMany(ReforgerMod::class, 'mod_preset_reforger_mod');
    }

    /** @return HasMany<Server, $this> */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'active_preset_id');
    }

    /**
     * @param  Builder<ModPreset>  $query
     */
    public function scopeForGame(Builder $query, GameType $gameType): Builder
    {
        return $query->where('game_type', $gameType);
    }
}
