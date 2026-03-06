<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SteamAccount extends Model
{
    /** @use HasFactory<\Database\Factories\SteamAccountFactory> */
    use HasFactory;

    /**
     * Get the current (most recent) SteamAccount.
     */
    public static function current(): ?self
    {
        return static::query()->latest()->first();
    }

    protected $fillable = [
        'username',
        'password',
        'auth_token',
        'steam_api_key',
        'mod_download_batch_size',
    ];

    protected $hidden = [
        'password',
        'auth_token',
        'steam_api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'auth_token' => 'encrypted',
            'steam_api_key' => 'encrypted',
            'mod_download_batch_size' => 'integer',
        ];
    }
}
