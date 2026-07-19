<?php

namespace Database\Factories\PropOff;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropOff\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventDate = fake()->dateTimeBetween('now', '+6 months');
        $lockDate = (clone $eventDate)->modify('-1 day');

        return [
            'name' => fake()->randomElement(['Super Bowl LVIII', 'NBA Finals 2024', 'World Series', 'March Madness']) . ' ' . fake()->year(),
            'description' => fake()->optional()->paragraph(),
            'category' => fake()->randomElement(['Sports', 'Entertainment', 'General']),
            'event_date' => $eventDate,
            'status' => fake()->randomElement(['draft', 'open', 'locked', 'in_progress', 'completed']),
            'lock_date' => $lockDate,
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
