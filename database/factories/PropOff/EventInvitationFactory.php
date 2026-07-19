<?php

namespace Database\Factories\PropOff;

use App\Models\PropOff\Event;
use App\Models\PropOff\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropOff\EventInvitation>
 */
class EventInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'group_id' => Group::factory(),
            'token' => Str::random(32),
            'max_uses' => fake()->numberBetween(10, 100),
            'times_used' => 0,
            'expires_at' => fake()->dateTimeBetween('now', '+1 month'),
        ];
    }

    /**
     * Indicate that the invitation is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the invitation is at max uses.
     */
    public function maxedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'times_used' => $attributes['max_uses'],
        ]);
    }
}
