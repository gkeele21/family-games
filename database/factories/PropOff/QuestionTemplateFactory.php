<?php

namespace Database\Factories\PropOff;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropOff\QuestionTemplate>
 */
class QuestionTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $questionType = fake()->randomElement(['multiple_choice', 'yes_no', 'numeric', 'text']);

        return [
            'title' => fake()->sentence(4),
            'question_text' => 'Will {team1} score more than {points} points against {team2}?',
            'question_type' => $questionType,
            'category' => fake()->randomElement(['Scoring', 'Player Performance', 'Game Outcome', 'Statistics']),
            'default_points' => fake()->numberBetween(1, 5),
            'variables' => ['team1', 'team2', 'points'],
            'default_options' => $questionType === 'multiple_choice' ? ['Yes', 'No', 'Maybe'] : null,
            'is_favorite' => fake()->boolean(20), // 20% chance of being favorite
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
