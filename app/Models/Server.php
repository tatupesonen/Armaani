<?php

namespace App\Models;

use App\Enums\ServerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Server extends Model
{
    /** @use HasFactory<\Database\Factories\ServerFactory> */
    use HasFactory;

    protected $fillable = [
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
        ];
    }

    public function activePreset(): BelongsTo
    {
        return $this->belongsTo(ModPreset::class, 'active_preset_id');
    }

    public function gameInstall(): BelongsTo
    {
        return $this->belongsTo(GameInstall::class);
    }

    public function difficultySettings(): HasOne
    {
        return $this->hasOne(DifficultySettings::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(ServerBackup::class)->latest();
    }

    /**
     * Get the per-server profiles directory path.
     * Arma 3 stores .vars profiles, RPT logs, bans, and config here.
     */
    public function getProfilesPath(): string
    {
        return config('arma.servers_base_path').'/'.$this->id;
    }

    public function getBinaryPath(): string
    {
        return $this->gameInstall->getInstallationPath();
    }

    /**
     * Get the Arma 3 profile name used for -name= launch param and profile directories.
     */
    public function getProfileName(): string
    {
        return 'arma3_'.$this->id;
    }
}
