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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->onDelete('cascade');
            $table->string('name'); // "Team A"
            $table->string('color', 7)->default('#3B82F6'); // Hex color for UI
            $table->unsignedInteger('display_order')->default(0); // Rotation order
            $table->integer('total_score')->default(0); // Denormalized for quick access
            $table->timestamps();

            $table->index('game_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
