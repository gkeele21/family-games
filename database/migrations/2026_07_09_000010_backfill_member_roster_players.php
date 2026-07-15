<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill: give every existing household member a roster player linked to
     * their account, so members created before this change appear as
     * selectable players when starting a game.
     */
    public function up(): void
    {
        foreach (DB::table('household_user')->get() as $membership) {
            $alreadyOnRoster = DB::table('players')
                ->where('household_id', $membership->household_id)
                ->where('user_id', $membership->user_id)
                ->exists();

            if ($alreadyOnRoster) {
                continue;
            }

            $user = DB::table('users')->where('id', $membership->user_id)->first();
            if (! $user) {
                continue;
            }

            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

            DB::table('players')->insert([
                'household_id' => $membership->household_id,
                'user_id'      => $membership->user_id,
                'name'         => $name !== '' ? $name : 'Player',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No-op: we can't distinguish backfilled players from hand-added ones.
    }
};
