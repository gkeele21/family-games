<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_user', function (Blueprint $table) {
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner', 'member', 'guest']);
            $table->timestamps();

            $table->primary(['household_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_user');
    }
};
