<?php

namespace Database\Factories\PropOff;

use App\Models\PropOff\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropOff\CaptainInvitation>
 */
class CaptainInvitationFactory extends Factory
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
            'token' => Str::random(32),
            'max_uses' => fake()->numberBetween(5, 50),
            'times_used' => 0,
            'expires_at' => fake()->dateTimeBetween('now', '+1 month'),
            'created_by' => \App\Models\User::factory(),
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
