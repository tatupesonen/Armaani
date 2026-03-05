<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_installs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('branch')->default('public');
            $table->string('installation_status')->default('queued');
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_installs');
    }
};
