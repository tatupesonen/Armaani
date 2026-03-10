<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkSettings extends Model
{
    /** @use HasFactory<\Database\Factories\NetworkSettingsFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
        'max_msg_send',
        'max_size_guaranteed',
        'max_size_nonguaranteed',
        'min_bandwidth',
        'max_bandwidth',
        'min_error_to_send',
        'min_error_to_send_near',
        'max_packet_size',
        'max_custom_file_size',
        'terrain_grid',
        'view_distance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_msg_send' => 'integer',
            'max_size_guaranteed' => 'integer',
            'max_size_nonguaranteed' => 'integer',
            'min_bandwidth' => 'integer',
            'max_bandwidth' => 'integer',
            'min_error_to_send' => 'decimal:4',
            'min_error_to_send_near' => 'decimal:4',
            'max_packet_size' => 'integer',
            'max_custom_file_size' => 'integer',
            'terrain_grid' => 'decimal:4',
            'view_distance' => 'integer',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
