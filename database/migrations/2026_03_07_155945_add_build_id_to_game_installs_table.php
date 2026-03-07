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
            $table->string('build_id')->nullable()->after('branch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_installs', function (Blueprint $table) {
            $table->dropColumn('build_id');
        });
    }
};
