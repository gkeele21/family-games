<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each Oodles card gets a random "silly" bonus question (round_type
     * 'bonus') shown at the top of the card — just for fun, no points and no
     * effect on control.
     */
    public function up(): void
    {
        Schema::table('session_cards', function (Blueprint $table) {
            $table->foreignId('bonus_question_id')
                ->nullable()
                ->after('letter')
                ->constrained('questions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bonus_question_id');
        });
    }
};
