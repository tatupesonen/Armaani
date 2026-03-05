<?php

namespace App\Models;

use App\Enums\GameInstallStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            'installation_status' => GameInstallStatus::class,
            'progress_pct' => 'integer',
            'disk_size_bytes' => 'integer',
            'installed_at' => 'datetime',
        ];
    }

    /**
     * Get the installation directory path for this game install.
     */
    public function getInstallationPath(): string
    {
        return config('arma.servers_base_path').'/game/'.$this->id;
    }
}
