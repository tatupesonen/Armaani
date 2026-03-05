<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SteamAccount extends Model
{
    /** @use HasFactory<\Database\Factories\SteamAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'username',
        'password',
        'auth_token',
        'steam_api_key',
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
        ];
    }
}
