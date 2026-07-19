<?php

namespace Database\Factories\PropOff;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropOff\Leaderboard>
 */
class LeaderboardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalScore = fake()->numberBetween(0, 100);
        $possiblePoints = fake()->numberBetween($totalScore, 100);
        $percentage = $possiblePoints > 0 ? round(($totalScore / $possiblePoints) * 100, 2) : 0;

        return [
            'game_id' => \App\Models\Game::factory(),
            'group_id' => fake()->optional()->randomElement(\App\Models\PropOff\Group::pluck('id')->toArray()), // NULL for global leaderboard
            'user_id' => \App\Models\User::factory(),
            'rank' => fake()->numberBetween(1, 100),
            'total_score' => $totalScore,
            'possible_points' => $possiblePoints,
            'percentage' => $percentage,
            'answered_count' => fake()->numberBetween(1, 50),
        ];
    }
}
