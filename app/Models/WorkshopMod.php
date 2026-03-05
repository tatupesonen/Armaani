<?php

namespace App\Models;

use App\Enums\InstallationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkshopMod extends Model
{
    /** @use HasFactory<\Database\Factories\WorkshopModFactory> */
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'name',
        'file_size',
        'installation_status',
        'progress_pct',
        'installed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workshop_id' => 'integer',
            'file_size' => 'integer',
            'installation_status' => InstallationStatus::class,
            'progress_pct' => 'integer',
            'installed_at' => 'datetime',
        ];
    }

    public function presets(): BelongsToMany
    {
        return $this->belongsToMany(ModPreset::class, 'mod_preset_workshop_mod');
    }

    /**
     * Get the normalized mod name for use in symlinks and launch params.
     * Strips non-alphanumeric chars and prepends @.
     */
    public function getNormalizedName(): string
    {
        $name = $this->name ?? (string) $this->workshop_id;
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name);
        $name = preg_replace('/\s+/', '_', $name);

        return '@'.$name;
    }

    /**
     * Get the path where SteamCMD downloads this mod's files.
     */
    public function getInstallationPath(): string
    {
        return config('arma.mods_base_path').'/steamapps/workshop/content/'.config('arma.game_id').'/'.$this->workshop_id;
    }
}
