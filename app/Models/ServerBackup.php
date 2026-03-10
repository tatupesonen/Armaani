<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerBackup extends Model
{
    /** @use HasFactory<\Database\Factories\ServerBackupFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'file_size',
        'is_automatic',
        'data',
    ];

    /** @var list<string> */
    protected $hidden = [
        'data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_automatic' => 'boolean',
            'file_size' => 'integer',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
