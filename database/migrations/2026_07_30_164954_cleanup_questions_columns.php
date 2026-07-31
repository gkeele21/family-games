<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tidy the questions table:
     *  - Replace the Family-Feud-specific `is_fast_money` boolean with a
     *    game-neutral `round_type` ('regular' | 'final'). Fast Money is simply
     *    Family Feud's name for a final-round question, so those become 'final'.
     *  - Drop the unused per-question accuracy stats (`times_correct`,
     *    `times_wrong`) — we don't track at that level. `times_used` stays.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('round_type')->default('regular')->after('difficulty');
        });

        DB::table('questions')->where('is_fast_money', true)->update(['round_type' => 'final']);

        Schema::table('questions', function (Blueprint $table) {
            $table->index('round_type');
            $table->dropIndex(['is_fast_money']);
            $table->dropColumn(['is_fast_money', 'times_correct', 'times_wrong']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->boolean('is_fast_money')->default(false)->after('answer_letter');
            $table->unsignedInteger('times_correct')->default(0)->after('times_used');
            $table->unsignedInteger('times_wrong')->default(0)->after('times_correct');
            $table->index('is_fast_money');
        });

        DB::table('questions')->where('round_type', 'final')->update(['is_fast_money' => true]);

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['round_type']);
            $table->dropColumn('round_type');
        });
    }
};
