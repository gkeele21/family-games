<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_templates', function (Blueprint $table) {
            $table->boolean('allow_self_scoring')->default(false)->after('team_based');
        });
        Schema::table('scored_games', function (Blueprint $table) {
            // Snapshotted from the template at game start.
            $table->boolean('allow_self_scoring')->default(false)->after('team_based');
        });
    }

    public function down(): void
    {
        Schema::table('game_templates', function (Blueprint $table) {
            $table->dropColumn('allow_self_scoring');
        });
        Schema::table('scored_games', function (Blueprint $table) {
            $table->dropColumn('allow_self_scoring');
        });
    }
};
