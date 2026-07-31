<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give game types an explicit display order for the picker. Defaults high so
     * unranked games sort after the curated ones; the online games are ranked
     * America Says → Family Feud → Oodles.
     */
    public function up(): void
    {
        Schema::table('game_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('display_order')->default(100)->after('slug');
        });

        $order = ['america-says' => 1, 'family-feud' => 2, 'oodles' => 3];
        foreach ($order as $slug => $position) {
            DB::table('game_types')->where('slug', $slug)->update(['display_order' => $position]);
        }
    }

    public function down(): void
    {
        Schema::table('game_types', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
    }
};
