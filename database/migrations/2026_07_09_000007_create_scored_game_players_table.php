<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scored_game_players', function (Blueprint $table) {
            $table->foreignId('scored_game_id')->constrained('scored_games')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedSmallInteger('seat_order');

            $table->primary(['scored_game_id', 'player_id']);
            $table->unique(['scored_game_id', 'seat_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scored_game_players');
    }
};
