<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $game_type
 *
 * Dynamic relationships registered by GameServiceProvider via resolveRelationUsing:
 *
 * @method \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\ReforgerMod, $this> reforgerMods()
 *
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReforgerMod> $reforgerMods
 */
class ModPreset extends Model
{
    /** @use HasFactory<\Database\Factories\ModPresetFactory> */
    use HasFactory;

    protected $fillable = [
        'game_type',
        'name',
    ];

    /** @return BelongsToMany<WorkshopMod, $this> */
    public function mods(): BelongsToMany
    {
        return $this->belongsToMany(WorkshopMod::class, 'mod_preset_workshop_mod');
    }

    /** @return HasMany<Server, $this> */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'active_preset_id');
    }

    /**
     * @param  Builder<ModPreset>  $query
     * @return Builder<ModPreset>
     */
    public function scopeForGame(Builder $query, string $gameType): Builder
    {
        return $query->where('game_type', $gameType);
    }
}
