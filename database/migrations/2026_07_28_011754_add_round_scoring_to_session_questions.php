<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-round scoring fields to session_questions.
     *
     * These are game-agnostic: `round_number` groups questions into rounds
     * (also drives "Round N" display), and `bonus_points` is a generic bonus
     * pot each game's engine interprets its own way (America Says awards it on
     * a board sweep). Per-answer value continues to use the existing
     * `points_available` column.
     */
    public function up(): void
    {
        Schema::table('session_questions', function (Blueprint $table) {
            $table->unsignedInteger('round_number')->nullable()->after('display_order');
            $table->unsignedInteger('bonus_points')->default(0)->after('points_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_questions', function (Blueprint $table) {
            $table->dropColumn(['round_number', 'bonus_points']);
        });
    }
};
