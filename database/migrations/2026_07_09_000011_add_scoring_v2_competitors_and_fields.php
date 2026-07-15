<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $defaultFields = '[{"key":"score","label":"Score","counts_toward_total":true}]';

    public function up(): void
    {
        // 1. Config columns on template + game (game snapshots the template).
        Schema::table('game_templates', function (Blueprint $table) {
            $table->json('score_fields')->nullable()->after('max_rounds');
            $table->boolean('team_based')->default(false)->after('score_fields');
        });
        Schema::table('scored_games', function (Blueprint $table) {
            $table->json('score_fields')->nullable()->after('max_rounds');
            $table->boolean('team_based')->default(false)->after('score_fields');
        });
        DB::table('game_templates')->whereNull('score_fields')
            ->update(['score_fields' => $this->defaultFields]);
        DB::table('scored_games')->whereNull('score_fields')
            ->update(['score_fields' => $this->defaultFields]);

        // 2. Competitors (grid columns) + their player membership.
        Schema::create('scored_game_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scored_game_id')->constrained('scored_games')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('display_order');
            $table->timestamps();

            $table->unique(['scored_game_id', 'display_order']);
        });
        Schema::create('competitor_player', function (Blueprint $table) {
            $table->foreignId('competitor_id')->constrained('scored_game_competitors')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();

            $table->primary(['competitor_id', 'player_id']);
        });

        // 3. Convert existing individual games: one competitor per player.
        foreach (DB::table('scored_game_players')->get() as $sgp) {
            $player = DB::table('players')->where('id', $sgp->player_id)->first();
            $competitorId = DB::table('scored_game_competitors')->insertGetId([
                'scored_game_id' => $sgp->scored_game_id,
                'name'           => $player->name ?? 'Player',
                'display_order'  => $sgp->seat_order,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            DB::table('competitor_player')->insert([
                'competitor_id' => $competitorId,
                'player_id'     => $sgp->player_id,
            ]);
        }

        // 4. Rebuild round_scores keyed by competitor with a JSON value map.
        //    (rounds/round_scores are empty today, so no data conversion needed.)
        Schema::dropIfExists('round_scores');
        Schema::create('round_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained('scored_game_competitors')->cascadeOnDelete();
            $table->json('values');
            $table->timestamps();

            $table->unique(['round_id', 'competitor_id']);
        });

        // 5. Old per-player pivot is superseded by competitors.
        Schema::dropIfExists('scored_game_players');
    }

    public function down(): void
    {
        Schema::create('scored_game_players', function (Blueprint $table) {
            $table->foreignId('scored_game_id')->constrained('scored_games')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedSmallInteger('seat_order');
            $table->primary(['scored_game_id', 'player_id']);
            $table->unique(['scored_game_id', 'seat_order']);
        });

        Schema::dropIfExists('round_scores');
        Schema::create('round_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->integer('score');
            $table->timestamps();
            $table->unique(['round_id', 'player_id']);
        });

        Schema::dropIfExists('competitor_player');
        Schema::dropIfExists('scored_game_competitors');

        Schema::table('scored_games', function (Blueprint $table) {
            $table->dropColumn(['score_fields', 'team_based']);
        });
        Schema::table('game_templates', function (Blueprint $table) {
            $table->dropColumn(['score_fields', 'team_based']);
        });
    }
};
