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
        // Add statistics columns to questions table
        Schema::table('questions', function (Blueprint $table) {
            // times_used already exists, add correct/wrong tracking
            $table->unsignedInteger('times_correct')->default(0)->after('times_used');
            $table->unsignedInteger('times_wrong')->default(0)->after('times_correct');
        });

        // Add statistics columns to answers table
        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedInteger('times_revealed')->default(0)->after('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['times_correct', 'times_wrong']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('times_revealed');
        });
    }
};
