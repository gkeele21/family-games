<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The scorekeeper household the user last worked in — the Scorekeeper
     * entry point returns them there. Persisted (not session) so it survives
     * logins and follows the user across devices.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('last_household_id')
                ->nullable()
                ->constrained('households')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_household_id');
        });
    }
};
