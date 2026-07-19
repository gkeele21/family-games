<?php

namespace Database\Factories\PropOff;

use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropOff\GroupQuestion>
 */
class GroupQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $questionType = fake()->randomElement(['multiple_choice', 'yes_no', 'numeric', 'text']);

        $options = null;
        if ($questionType === 'multiple_choice') {
            $options = json_encode([
                ['label' => 'Option A', 'points' => 0],
                ['label' => 'Option B', 'points' => 5],
                ['label' => 'Option C', 'points' => 10],
            ]);
        } elseif ($questionType === 'yes_no') {
            $options = json_encode([
                ['label' => 'Yes', 'points' => 0],
                ['label' => 'No', 'points' => 0],
            ]);
        }

        return [
            'group_id' => Group::factory(),
            'event_question_id' => fake()->optional(0.7)->randomElement([EventQuestion::factory()]),
            'question_text' => fake()->sentence() . '?',
            'question_type' => $questionType,
            'options' => $options,
            'points' => fake()->numberBetween(5, 20),
            'display_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the question is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the question is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
