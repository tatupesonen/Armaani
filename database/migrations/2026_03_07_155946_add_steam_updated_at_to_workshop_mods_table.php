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
        Schema::table('workshop_mods', function (Blueprint $table) {
            $table->timestamp('steam_updated_at')->nullable()->after('installed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_mods', function (Blueprint $table) {
            $table->dropColumn('steam_updated_at');
        });
    }
};
