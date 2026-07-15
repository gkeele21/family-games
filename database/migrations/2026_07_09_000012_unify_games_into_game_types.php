<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend the shared catalog.
        Schema::table('game_types', function (Blueprint $table) {
            $table->enum('kind', ['online', 'scorekeeper'])->default('online')->after('slug');
            // NULL household_id = system/global game (all households see it).
            $table->foreignId('household_id')->nullable()->after('kind')
                ->constrained('households')->cascadeOnDelete();
            $table->boolean('is_global')->default(false)->after('household_id');
            $table->dropUnique(['slug']); // households may reuse names/slugs
            $table->index('slug');
        });
        DB::table('game_types')->update(['kind' => 'online']);

        // 2. Seed system scorekeeper games from system templates' games.
        $names = DB::table('game_templates')
            ->where('is_system', true)
            ->whereNotNull('base_game_type')
            ->distinct()
            ->pluck('base_game_type');
        foreach ($names as $name) {
            $exists = DB::table('game_types')
                ->where('kind', 'scorekeeper')->whereNull('household_id')->where('name', $name)
                ->exists();
            if (! $exists) {
                DB::table('game_types')->insert([
                    'name' => $name, 'slug' => Str::slug($name), 'kind' => 'scorekeeper',
                    'household_id' => null, 'is_global' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // 3. Templates reference a game instead of storing a string.
        Schema::table('game_templates', function (Blueprint $table) {
            $table->foreignId('game_type_id')->nullable()->after('base_game_type')
                ->constrained('game_types')->nullOnDelete();
            $table->boolean('is_global')->default(false)->after('game_type_id');
        });

        foreach (DB::table('game_templates')->get() as $tpl) {
            if (! $tpl->base_game_type) {
                continue;
            }
            // Prefer a matching system/global game; else scope to the household.
            $game = DB::table('game_types')
                ->where('kind', 'scorekeeper')->whereNull('household_id')
                ->where('name', $tpl->base_game_type)->first();
            if (! $game && $tpl->household_id) {
                $game = DB::table('game_types')
                    ->where('kind', 'scorekeeper')->where('household_id', $tpl->household_id)
                    ->where('name', $tpl->base_game_type)->first();
                if (! $game) {
                    $id = DB::table('game_types')->insertGetId([
                        'name' => $tpl->base_game_type, 'slug' => Str::slug($tpl->base_game_type),
                        'kind' => 'scorekeeper', 'household_id' => $tpl->household_id, 'is_global' => false,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $game = (object) ['id' => $id];
                }
            }
            if ($game) {
                DB::table('game_templates')->where('id', $tpl->id)->update(['game_type_id' => $game->id]);
            }
        }

        Schema::table('game_templates', function (Blueprint $table) {
            $table->dropColumn('base_game_type');
        });
    }

    public function down(): void
    {
        Schema::table('game_templates', function (Blueprint $table) {
            $table->string('base_game_type')->nullable()->after('name');
        });
        foreach (DB::table('game_templates')->whereNotNull('game_type_id')->get() as $tpl) {
            $g = DB::table('game_types')->find($tpl->game_type_id);
            DB::table('game_templates')->where('id', $tpl->id)
                ->update(['base_game_type' => $g->name ?? null]);
        }
        Schema::table('game_templates', function (Blueprint $table) {
            $table->dropForeign(['game_type_id']);
            $table->dropColumn(['game_type_id', 'is_global']);
        });

        DB::table('game_types')->where('kind', 'scorekeeper')->delete();

        Schema::table('game_types', function (Blueprint $table) {
            $table->dropForeign(['household_id']);
            $table->dropIndex(['slug']);
            $table->dropColumn(['kind', 'household_id', 'is_global']);
            $table->unique('slug');
        });
    }
};
