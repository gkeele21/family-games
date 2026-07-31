<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Difficulty is optional — you shouldn't have to assign one (e.g. when adding
     * real game-show questions). Make it nullable with no default.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE questions MODIFY difficulty ENUM('easy','medium','hard') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE questions MODIFY difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium'");
    }
};
