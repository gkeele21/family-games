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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->text('question_text'); // The prompt shown to players
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->char('answer_letter', 1)->nullable(); // For Oodles: the letter (A-Z)
            $table->boolean('is_fast_money')->default(false); // For Family Feud Fast Money
            $table->json('metadata')->nullable(); // Game-specific data
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('times_used')->default(0); // Track popularity
            $table->timestamps();

            $table->index('game_type_id');
            $table->index('category_id');
            $table->index('answer_letter');
            $table->index('is_fast_money');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
