<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bump the America Says default control-round timer 30s → 40s. Casting latency
 * eats a few seconds off the front of every round (the board reaches the TV after
 * the clock has already started), so the effective play time was short. New games
 * pick this up via default_config; existing sessions keep whatever they copied.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setTimer(30, 40);
    }

    public function down(): void
    {
        $this->setTimer(40, 30);
    }

    private function setTimer(int $from, int $to): void
    {
        $row = DB::table('game_types')->where('slug', 'america-says')->first();
        if (!$row) {
            return;
        }

        $config = json_decode($row->default_config ?? '{}', true) ?: [];
        // Only flip it if it's still the old default (don't clobber a custom value).
        if (($config['control_timer_seconds'] ?? null) === $from) {
            $config['control_timer_seconds'] = $to;
            DB::table('game_types')->where('id', $row->id)->update([
                'default_config' => json_encode($config),
            ]);
        }
    }
};
