<?php

namespace Database\Seeders;

use App\Models\Scorekeeper\GameTemplate;
use Illuminate\Database\Seeder;

class SystemGameTemplateSeeder extends Seeder
{
    /**
     * System (built-in) scoring templates, available to every household.
     * Generalized beyond card games — a couple of board games are included.
     * Idempotent: keyed on (name, is_system) so it can be re-run safely.
     */
    public function run(): void
    {
        $templates = [
            // Card games
            ['name' => 'Rummy 500',  'base_game_type' => 'Rummy 500',  'target_score' => 500, 'low_score_wins' => false, 'max_rounds' => null],
            ['name' => 'Hearts',     'base_game_type' => 'Hearts',     'target_score' => 100, 'low_score_wins' => true,  'max_rounds' => null],
            ['name' => 'Spades',     'base_game_type' => 'Spades',     'target_score' => 500, 'low_score_wins' => false, 'max_rounds' => null],
            ['name' => 'Gin Rummy',  'base_game_type' => 'Gin Rummy',  'target_score' => 100, 'low_score_wins' => false, 'max_rounds' => null],
            ['name' => 'Phase 10',   'base_game_type' => 'Phase 10',   'target_score' => null, 'low_score_wins' => true,  'max_rounds' => 10],
            // Board games (proves generalization)
            ['name' => 'Yahtzee',    'base_game_type' => 'Yahtzee',    'target_score' => null, 'low_score_wins' => false, 'max_rounds' => 13],
            ['name' => 'Catan',      'base_game_type' => 'Catan',      'target_score' => 10,  'low_score_wins' => false, 'max_rounds' => null],
        ];

        foreach ($templates as $t) {
            GameTemplate::updateOrCreate(
                ['name' => $t['name'], 'is_system' => true],
                [
                    ...$t,
                    'household_id'       => null,
                    'is_system'          => true,
                    'created_by_user_id' => null,
                ],
            );
        }
    }
}
