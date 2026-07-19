<?php

namespace Database\Factories\PropOff;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropOff\Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Group',
            'code' => strtoupper(fake()->unique()->bothify('???-###')),
            'description' => fake()->optional()->sentence(10),
            'is_public' => fake()->boolean(30), // 30% chance of being public
            'event_id' => \App\Models\PropOff\Event::factory(),
            'grading_source' => fake()->randomElement(['captain', 'admin']),
            'entry_cutoff' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
