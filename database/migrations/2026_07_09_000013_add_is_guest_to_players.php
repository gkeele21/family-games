<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // A guest belongs to the household (for scoping) but is hidden from
            // the roster — a one-off player added to a single game.
            $table->boolean('is_guest')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('is_guest');
        });
    }
};
