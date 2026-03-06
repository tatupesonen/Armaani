<?php

namespace App\Models;

use App\Enums\InstallationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameInstall extends Model
{
    /** @use HasFactory<\Database\Factories\GameInstallFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'branch',
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
            'installation_status' => InstallationStatus::class,
            'progress_pct' => 'integer',
            'disk_size_bytes' => 'integer',
            'installed_at' => 'datetime',
        ];
    }

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
