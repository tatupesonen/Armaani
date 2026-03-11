<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->index('game_type');
            $table->index('status');
        });

        Schema::table('game_installs', function (Blueprint $table) {
            $table->index('game_type');
            $table->index('installation_status');
        });

        Schema::table('workshop_mods', function (Blueprint $table) {
            $table->index('installation_status');
        });
    }
};
