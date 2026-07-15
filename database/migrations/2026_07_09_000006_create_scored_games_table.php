<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scored_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('game_template_id')->nullable()->constrained('game_templates')->nullOnDelete();
            // Config is snapshotted so later template edits don't rewrite history.
            $table->string('template_name_snapshot');
            $table->string('base_game_type')->nullable();
            $table->integer('target_score')->nullable();
            $table->boolean('low_score_wins')->default(false);
            $table->integer('max_rounds')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('household_id');
            $table->index(['household_id', 'is_complete']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scored_games');
    }
};
