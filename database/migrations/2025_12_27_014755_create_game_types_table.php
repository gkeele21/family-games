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
        Schema::create('game_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Family Feud", "America Says", "Oodles"
            $table->string('slug')->unique(); // "family-feud", "america-says", "oodles"
            $table->text('description')->nullable();
            $table->json('default_config')->nullable(); // Default timer, rounds, team size, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_types');
    }
};
