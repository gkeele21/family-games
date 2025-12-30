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
        Schema::create('game_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('current_question_id')->nullable()->constrained('session_questions')->onDelete('set null');
            $table->foreignId('current_card_id')->nullable()->constrained('session_cards')->onDelete('set null');
            $table->foreignId('active_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->unsignedInteger('round_number')->default(1);
            $table->timestamp('timer_started_at')->nullable();
            $table->unsignedInteger('timer_duration')->default(30); // seconds
            $table->json('state_data')->nullable(); // Game-specific state
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_states');
    }
};
