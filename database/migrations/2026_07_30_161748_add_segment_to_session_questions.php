<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mark which segment of a game a session question belongs to and, for the
     * America Says final round, how many top answers that slot requires.
     *
     *   segment: null/'main' = regular play, 'final' = America Says final round,
     *            'fast_money' = Family Feud Fast Money
     *   answers_needed: for a 'final' slot, the ladder position (1 → 4)
     *
     * This also gives Family Feud a valid home for its Fast Money marker, which
     * previously (mis)used the control_status enum.
     */
    public function up(): void
    {
        Schema::table('session_questions', function (Blueprint $table) {
            $table->string('segment')->nullable()->after('status');
            $table->unsignedTinyInteger('answers_needed')->nullable()->after('segment');
        });
    }

    public function down(): void
    {
        Schema::table('session_questions', function (Blueprint $table) {
            $table->dropColumn(['segment', 'answers_needed']);
        });
    }
};
