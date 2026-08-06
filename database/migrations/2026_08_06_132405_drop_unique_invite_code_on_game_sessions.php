<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invite codes are now friendly words drawn from a fixed list, and
     * uniqueness is enforced in application code only against still-joinable
     * games (see App\Support\GameCode). Dropping the global unique index lets
     * words be reused once a game is completed. A plain index on invite_code
     * remains (added in the original table migration) for fast lookups.
     */
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropUnique(['invite_code']);
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unique('invite_code');
        });
    }
};
