<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who was present for a game session — a lightweight attendance roster that
     * links a game_session to household roster players (App\Models\Scorekeeper\
     * Player). No teams, no logins: just "these people were here." Mirrors
     * Scorekeeper's scored_game_players pivot.
     *
     * Its purpose is cross-game question history: joining a player's attended
     * sessions to those sessions' session_questions tells us whether that person
     * has already been served a given question.
     */
    public function up(): void
    {
        Schema::create('game_session_players', function (Blueprint $table) {
            $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();

            $table->primary(['game_session_id', 'player_id']);
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_session_players');
    }
};
