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
        Schema::table('game_installs', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_pct')->default(0)->after('installation_status');
            $table->unsignedBigInteger('disk_size_bytes')->default(0)->after('progress_pct');
        });
    }

    public function down(): void
    {
        Schema::table('game_installs', function (Blueprint $table) {
            $table->dropColumn(['progress_pct', 'disk_size_bytes']);
        });
    }
};
