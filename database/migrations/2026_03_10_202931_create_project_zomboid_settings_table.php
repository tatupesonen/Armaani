<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_zomboid_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->boolean('pvp')->default(true);
            $table->boolean('pause_empty')->default(true);
            $table->boolean('global_chat')->default(true);
            $table->boolean('open')->default(true);
            $table->string('map')->default('Muldraugh, KY');
            $table->boolean('safety_system')->default(true);
            $table->boolean('show_safety')->default(true);
            $table->boolean('sleep_allowed')->default(false);
            $table->boolean('sleep_needed')->default(false);
            $table->boolean('announce_death')->default(false);
            $table->boolean('do_lua_checksum')->default(true);
            $table->integer('max_accounts_per_user')->default(0);
            $table->boolean('login_queue_enabled')->default(false);
            $table->boolean('deny_login_on_overloaded_server')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_zomboid_settings');
    }
};
