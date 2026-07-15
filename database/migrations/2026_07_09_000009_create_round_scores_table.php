<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_scores', function (Blueprint $table) {
            // Single-column PK so Eloquent updateOrCreate can overwrite scores;
            // uniqueness of (round, player) is enforced separately.
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->integer('score');
            $table->timestamps();

            $table->unique(['round_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_scores');
    }
};
