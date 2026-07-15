<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scored_game_id')->constrained('scored_games')->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->timestamps();

            $table->unique(['scored_game_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
