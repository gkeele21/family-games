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
        Schema::create('session_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('card_number'); // Order in session (1, 2, 3...)
            $table->char('letter', 1); // The card's letter
            $table->enum('status', ['pending', 'active', 'completed', 'skipped'])->default('pending');
            $table->timestamps();

            $table->index('game_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_cards');
    }
};
