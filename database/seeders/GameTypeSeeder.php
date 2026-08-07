<?php

namespace Database\Seeders;

use App\Models\GameType;
use Illuminate\Database\Seeder;

class GameTypeSeeder extends Seeder
{
    public function run(): void
    {
        GameType::create([
            'name' => 'Family Feud',
            'slug' => 'family-feud',
            'display_order' => 2,
            'description' => 'Survey-based game where teams compete to guess the most popular answers. Features face-offs, strikes, steals, and an optional Fast Money round.',
            'default_config' => [
                'team_size' => 0, // 0 = unlimited, 1 = individual play
                'allow_team_selection' => false, // If true, players can pick their team
                'rounds_per_game' => 4,
                'face_off_mode' => 'buzzer', // 'buzzer' or 'rotation'
                'max_strikes' => 3,
                'steal_mode' => 'one_guess', // 'one_guess' or 'timed'
                'steal_timer_seconds' => 30,
                'round_multipliers' => [
                    '1' => 1,
                    '2' => 1,
                    '3' => 2,
                    '4' => 3,
                ],
                'fast_money_enabled' => true,
                'fast_money_player1_seconds' => 20,
                'fast_money_player2_seconds' => 25,
                'fast_money_target_score' => 200,
                'play_or_pass_enabled' => true,
                'answers_per_question' => 8,
                'winning_condition' => 'most_points_after_rounds',
            ],
        ]);

        GameType::create([
            'name' => 'America Says',
            'slug' => 'america-says',
            'display_order' => 1,
            'description' => 'Survey-based game where teams try to guess all answers within a time limit. Features control timers, steal rounds, and flat scoring.',
            'default_config' => [
                'team_size' => 0, // 0 = unlimited, 1 = individual play
                'number_of_teams' => 2, // teams auto-created in the lobby (Team A, Team B…)
                'allow_team_selection' => false, // If true, players can pick their team
                'answers_per_question' => 7,
                'control_timer_seconds' => 40,
                // Final round: 4 questions revealing the top 1 → 4 answers, one 60s
                // clock. Questions are pulled at random, each needing at least as
                // many answers as its slot requires. (Gameplay wired up later.)
                'final_round_enabled' => true,
                'final_round_questions' => 4,
                'final_round_seconds' => 60,
                // Per-round scoring: a round plays one question per team. Each correct
                // answer scores points_per_answer; sweeping the whole board adds bonus_points.
                'rounds' => 3,
                'round_scoring' => [
                    ['points_per_answer' => 100, 'bonus_points' => 1000],
                    ['points_per_answer' => 200, 'bonus_points' => 2000],
                    ['points_per_answer' => 300, 'bonus_points' => 3000],
                ],
                'points_per_answer' => 100, // fallback for legacy/flat scoring
                'gameplay_mode' => 'host_reveal', // 'host_reveal' or 'team_buzzer'
                'winning_condition' => 'most_points_after_questions',
                'winning_condition_options' => [
                    'first_to_points' => null,
                    'questions_to_play' => 10,
                ],
            ],
        ]);

        GameType::create([
            'name' => 'Oodles',
            'slug' => 'oodles',
            'display_order' => 3,
            'description' => 'Word-guessing game with cards. Each card has questions where all answers start with the same letter. Teams control questions and can earn control through steals.',
            'default_config' => [
                'team_size' => 0, // 0 = unlimited, 1 = individual play
                'allow_team_selection' => false, // If true, players can pick their team
                'cards_per_game' => 15,
                'questions_per_card_mode' => 'random', // 'random' or 'fixed'
                'fixed_questions_per_card' => null,
                'min_questions_per_card' => 3,
                'max_questions_per_card' => 10,
                'allow_letter_reuse' => true,
                'control_timer_seconds' => 10,
                'all_play_timer_seconds' => 10,
                'steal_points_percentage' => 100,
                'points_mode' => 'fixed', // 'fixed' = use points_per_answer, 'database' = use points from question
                'points_per_answer' => 100,
                'last_question_bonus' => 0, // Bonus points for answering the last question on a card correctly
                'multi_team_scoring' => 'full', // 'full' = all teams get full points, 'split' = points split among teams
                'winning_condition' => 'most_points_after_cards',
                'winning_condition_options' => [
                    'first_to_points' => null,
                    'cards_to_play' => 15,
                ],
            ],
        ]);
    }
}
