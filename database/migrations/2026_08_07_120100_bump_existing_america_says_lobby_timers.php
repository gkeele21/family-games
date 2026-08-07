<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Existing America Says games still in the lobby snapshotted the old 30s timer
 * into their settings when they were created, so they'd keep showing 30 even
 * after the default was bumped to 40. Bump those lobby sessions (and their game
 * state) to 40 — but only where they're still on the old default, so a host who
 * intentionally set a custom timer keeps it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->retimeLobbySessions(30, 40);
    }

    public function down(): void
    {
        $this->retimeLobbySessions(40, 30);
    }

    private function retimeLobbySessions(int $from, int $to): void
    {
        $typeId = DB::table('game_types')->where('slug', 'america-says')->value('id');
        if (!$typeId) {
            return;
        }

        $sessions = DB::table('game_sessions')
            ->where('game_type_id', $typeId)
            ->where('status', 'lobby')
            ->get();

        foreach ($sessions as $session) {
            $settings = json_decode($session->settings ?? '{}', true) ?: [];
            if (($settings['control_timer_seconds'] ?? null) !== $from) {
                continue;
            }

            $settings['control_timer_seconds'] = $to;
            DB::table('game_sessions')->where('id', $session->id)->update([
                'settings' => json_encode($settings),
            ]);

            DB::table('game_states')
                ->where('game_session_id', $session->id)
                ->where('timer_duration', $from)
                ->update(['timer_duration' => $to]);
        }
    }
};
