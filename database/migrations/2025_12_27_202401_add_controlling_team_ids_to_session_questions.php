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
        Schema::table('session_questions', function (Blueprint $table) {
            // Add JSON column for multiple controlling teams (for All Play ties)
            $table->json('controlling_team_ids')->nullable()->after('controlling_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_questions', function (Blueprint $table) {
            $table->dropColumn('controlling_team_ids');
        });
    }
};
