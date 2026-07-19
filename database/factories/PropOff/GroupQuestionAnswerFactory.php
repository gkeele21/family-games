<?php

namespace Database\Factories\PropOff;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropOff\GroupQuestionAnswer>
 */
class GroupQuestionAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => \App\Models\PropOff\Group::factory(),
            'group_question_id' => \App\Models\PropOff\GroupQuestion::factory(),
            'correct_answer' => fake()->sentence(5),
            'is_void' => fake()->boolean(5), // 5% chance of being voided
        ];
    }
}
