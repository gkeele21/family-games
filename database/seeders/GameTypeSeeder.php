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
            'description' => 'Survey-based game where teams try to guess all answers within a time limit. Features control timers, steal rounds, and flat scoring.',
            'default_config' => [
                'team_size' => 0, // 0 = unlimited, 1 = individual play
                'allow_team_selection' => false, // If true, players can pick their team
                'questions_per_game' => 10,
                'answers_per_question' => 7,
                'control_timer_seconds' => 30,
                'steal_timer_seconds' => 10,
                'steal_points_percentage' => 50,
                'points_per_answer' => 100,
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
