<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PropOff's hybrid auth on the shared users table: a global role tier
     * (manager/admin/user/guest — string, validated in app code) and the
     * passwordless guest identity. Guests may have neither email nor
     * password, so both become nullable (email stays unique — MySQL permits
     * multiple NULLs).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('email')->index();
            $table->string('guest_token', 32)->nullable()->unique()->after('remember_token');
            $table->string('avatar')->nullable()->after('guest_token');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['guest_token']);
            $table->dropColumn(['role', 'guest_token', 'avatar']);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
