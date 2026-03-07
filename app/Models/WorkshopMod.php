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
        'steam_updated_at',
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
            'steam_updated_at' => 'datetime',
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
        $name = preg_replace('/[^A-Za-z0-9_]/', '', trim($name));

        return '@'.$name;
    }

    /**
     * Determine if this mod has a newer version available on the Workshop.
     */
    public function isOutdated(): bool
    {
        if (! $this->steam_updated_at || ! $this->installed_at) {
            return false;
        }

        return $this->steam_updated_at->gt($this->installed_at);
    }

    /**
     * Get the path where SteamCMD downloads this mod's files.
     */
    public function getInstallationPath(): string
    {
        return config('arma.mods_base_path').'/steamapps/workshop/content/'.config('arma.game_id').'/'.$this->workshop_id;
    }
}
