<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained('households')->cascadeOnDelete();
            $table->string('name');
            // Free-form label (card OR board game), e.g. "Rummy 500", "Catan".
            $table->string('base_game_type')->nullable();
            $table->integer('target_score')->nullable();
            $table->boolean('low_score_wins')->default(false);
            $table->integer('max_rounds')->nullable();
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('household_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_templates');
    }
};
