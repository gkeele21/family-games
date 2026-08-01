<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Difficulty is optional — you shouldn't have to assign one (e.g. when adding
     * real game-show questions). Make it nullable with no default.
     */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE questions MODIFY difficulty ENUM('easy','medium','hard') NULL DEFAULT NULL");

            return;
        }

        // sqlite (tests) has no MODIFY/ENUM — it stores the enum as a string.
        Schema::table('questions', function (Blueprint $table) {
            $table->string('difficulty')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE questions MODIFY difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium'");

            return;
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->string('difficulty')->nullable(false)->default('medium')->change();
        });
    }
};
