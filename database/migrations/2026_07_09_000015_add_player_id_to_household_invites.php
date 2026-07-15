<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_invites', function (Blueprint $table) {
            // An invite may target an existing roster player; accepting links
            // the new account to that player. If the player is deleted first,
            // the invite degrades to a plain household invite.
            $table->foreignId('player_id')->nullable()->after('role')
                ->constrained('players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('household_invites', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
            $table->dropColumn('player_id');
        });
    }
};
