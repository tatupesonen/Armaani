<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReforgerMod extends Model
{
    /** @use HasFactory<\Database\Factories\ReforgerModFactory> */
    use HasFactory;

    protected $fillable = [
        'mod_id',
        'name',
    ];

    /** @return BelongsToMany<ModPreset, $this> */
    public function presets(): BelongsToMany
    {
        return $this->belongsToMany(ModPreset::class, 'mod_preset_reforger_mod');
    }
}
