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
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('host_user_id')->constrained('users')->onDelete('cascade');
            $table->string('name')->nullable(); // "Keele Family Game Night"
            $table->enum('status', ['lobby', 'playing', 'paused', 'completed'])->default('lobby');
            $table->json('settings')->nullable(); // Host overrides for default_config
            $table->string('invite_code', 10)->unique(); // For joining (e.g., "ABC123")
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('invite_code');
            $table->index('host_user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
