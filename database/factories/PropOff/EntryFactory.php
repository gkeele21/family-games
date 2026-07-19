<?php

namespace Database\Factories\PropOff;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropOff\Entry>
 */
class EntryFactory extends Factory
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
        $isComplete = fake()->boolean(70); // 70% chance of being complete

        return [
            'event_id' => \App\Models\PropOff\Event::factory(),
            'user_id' => \App\Models\User::factory(),
            'group_id' => \App\Models\PropOff\Group::factory(),
            'total_score' => $totalScore,
            'possible_points' => $possiblePoints,
            'percentage' => $percentage,
            'is_complete' => $isComplete,
            'submitted_at' => $isComplete ? fake()->dateTimeBetween('-1 month', 'now') : null,
        ];
    }
}
