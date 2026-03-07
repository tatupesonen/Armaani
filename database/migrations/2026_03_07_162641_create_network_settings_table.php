<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('network_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();

            // Messaging
            $table->unsignedInteger('max_msg_send')->default(128);
            $table->unsignedInteger('max_size_guaranteed')->default(512);
            $table->unsignedInteger('max_size_nonguaranteed')->default(256);

            // Bandwidth (bytes per second)
            $table->unsignedBigInteger('min_bandwidth')->default(131072);
            $table->unsignedBigInteger('max_bandwidth')->default(10000000000);

            // Error thresholds for network updates
            $table->decimal('min_error_to_send', 8, 4)->default(0.001);
            $table->decimal('min_error_to_send_near', 8, 4)->default(0.01);

            // Sockets
            $table->unsignedInteger('max_packet_size')->default(1400);

            // Custom content
            $table->unsignedInteger('max_custom_file_size')->default(0);

            // Terrain grid resolution (12.5 = low detail, 3.125 = high detail)
            $table->decimal('terrain_grid', 8, 4)->default(25.0);

            // View distance override for server (0 = use mission default)
            $table->unsignedInteger('view_distance')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_settings');
    }
};
