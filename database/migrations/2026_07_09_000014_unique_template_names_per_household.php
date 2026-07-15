<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De-duplicate existing names within each household (keep the oldest
        // as-is; suffix the rest) so the unique index can be added.
        $dupeGroups = DB::table('game_templates')
            ->selectRaw('household_id, name')
            ->groupBy('household_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupeGroups as $group) {
            $ids = DB::table('game_templates')
                ->where('name', $group->name)
                ->when(
                    $group->household_id === null,
                    fn ($q) => $q->whereNull('household_id'),
                    fn ($q) => $q->where('household_id', $group->household_id),
                )
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids->slice(1)->values() as $i => $id) {
                $suffix = $i + 2;
                $candidate = "{$group->name} ({$suffix})";
                while (
                    DB::table('game_templates')
                        ->where('name', $candidate)
                        ->when(
                            $group->household_id === null,
                            fn ($q) => $q->whereNull('household_id'),
                            fn ($q) => $q->where('household_id', $group->household_id),
                        )
                        ->exists()
                ) {
                    $suffix++;
                    $candidate = "{$group->name} ({$suffix})";
                }
                DB::table('game_templates')->where('id', $id)->update(['name' => $candidate]);
            }
        }

        // Enforces per-household uniqueness. System templates (household_id
        // NULL) are not covered by this index (MySQL allows repeated NULLs) —
        // their uniqueness is guaranteed by the seeder keying on name.
        Schema::table('game_templates', function (Blueprint $table) {
            $table->unique(['household_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('game_templates', function (Blueprint $table) {
            $table->dropUnique(['household_id', 'name']);
        });
    }
};
