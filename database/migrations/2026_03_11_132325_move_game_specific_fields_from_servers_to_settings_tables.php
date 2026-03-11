<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add game-specific columns to arma3_settings
        Schema::table('arma3_settings', function (Blueprint $table) {
            $table->string('admin_password')->nullable()->after('server_id');
            $table->boolean('verify_signatures')->default(true)->after('admin_password');
            $table->boolean('allowed_file_patching')->default(false)->after('verify_signatures');
            $table->boolean('battle_eye')->default(true)->after('allowed_file_patching');
            $table->boolean('persistent')->default(false)->after('battle_eye');
            $table->boolean('von_enabled')->default(true)->after('persistent');
            $table->text('additional_server_options')->nullable()->after('von_enabled');
        });

        // 2. Add battle_eye and admin_password to reforger_settings
        Schema::table('reforger_settings', function (Blueprint $table) {
            $table->string('admin_password')->nullable()->after('server_id');
            $table->boolean('battle_eye')->default(true)->after('admin_password');
        });

        // 3. Add admin_password to project_zomboid_settings
        Schema::table('project_zomboid_settings', function (Blueprint $table) {
            $table->string('admin_password')->nullable()->after('server_id');
        });

        // 4. Copy data from servers to arma3_settings for Arma 3 servers
        DB::statement('
            UPDATE arma3_settings
            SET
                admin_password = (SELECT s.admin_password FROM servers s WHERE s.id = arma3_settings.server_id),
                verify_signatures = (SELECT s.verify_signatures FROM servers s WHERE s.id = arma3_settings.server_id),
                allowed_file_patching = (SELECT s.allowed_file_patching FROM servers s WHERE s.id = arma3_settings.server_id),
                battle_eye = (SELECT s.battle_eye FROM servers s WHERE s.id = arma3_settings.server_id),
                persistent = (SELECT s.persistent FROM servers s WHERE s.id = arma3_settings.server_id),
                von_enabled = (SELECT s.von_enabled FROM servers s WHERE s.id = arma3_settings.server_id),
                additional_server_options = (SELECT s.additional_server_options FROM servers s WHERE s.id = arma3_settings.server_id)
        ');

        // 5. Copy data from servers to reforger_settings for Reforger servers
        DB::statement('
            UPDATE reforger_settings
            SET
                admin_password = (SELECT s.admin_password FROM servers s WHERE s.id = reforger_settings.server_id),
                battle_eye = (SELECT s.battle_eye FROM servers s WHERE s.id = reforger_settings.server_id)
        ');

        // 6. Copy data from servers to project_zomboid_settings for PZ servers
        DB::statement('
            UPDATE project_zomboid_settings
            SET
                admin_password = (SELECT s.admin_password FROM servers s WHERE s.id = project_zomboid_settings.server_id)
        ');

        // 7. Null out query_port for servers whose game type doesn't use HasQueryPort (DayZ)
        DB::statement("
            UPDATE servers SET query_port = NULL WHERE game_type = 'dayz'
        ");

        // 8. Drop moved columns from servers and make query_port nullable
        Schema::table('servers', function (Blueprint $table) {
            $table->integer('query_port')->nullable()->default(null)->change();
            $table->dropColumn([
                'admin_password',
                'verify_signatures',
                'allowed_file_patching',
                'battle_eye',
                'persistent',
                'von_enabled',
                'additional_server_options',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
