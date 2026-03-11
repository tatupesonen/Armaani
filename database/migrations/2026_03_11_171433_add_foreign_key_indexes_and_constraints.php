<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- FK column indexes (SQLite does not auto-index FK columns) ---

        Schema::table('servers', function (Blueprint $table) {
            $table->index('game_install_id');
            $table->index('active_preset_id');
        });

        Schema::table('server_backups', function (Blueprint $table) {
            $table->index('server_id');
        });

        Schema::table('arma3_settings', function (Blueprint $table) {
            $table->unique('server_id');
        });

        Schema::table('reforger_settings', function (Blueprint $table) {
            $table->unique('server_id');
        });

        Schema::table('project_zomboid_settings', function (Blueprint $table) {
            $table->unique('server_id');
        });

        Schema::table('factorio_settings', function (Blueprint $table) {
            $table->unique('server_id');
        });

        Schema::table('dayz_settings', function (Blueprint $table) {
            $table->unique('server_id');
        });

        Schema::table('mod_preset_workshop_mod', function (Blueprint $table) {
            $table->index('workshop_mod_id');
        });

        Schema::table('mod_preset_reforger_mod', function (Blueprint $table) {
            $table->index('reforger_mod_id');
        });

    }
};
