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
        Schema::create('session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('session_card_id')->nullable()->constrained()->onDelete('cascade'); // For Oodles
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('display_order')->default(0); // Order in session/card
            $table->enum('status', ['pending', 'active', 'completed', 'skipped'])->default('pending');
            $table->foreignId('controlling_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->enum('control_status', ['team_control', 'open', 'all_play'])->default('team_control');
            $table->unsignedInteger('points_available')->default(0); // Calculated when loaded
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->index('game_session_id');
            $table->index('session_card_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_questions');
    }
};
