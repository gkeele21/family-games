<?php

namespace Tests\Unit\PropOff\Models;

use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestion;
use App\Models\PropOff\GroupQuestionAnswer;
use App\Models\PropOff\UserAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupQuestionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_a_group()
    {
        $group = Group::factory()->create();
        $groupQuestion = GroupQuestion::factory()->create(['group_id' => $group->id]);

        $this->assertInstanceOf(Group::class, $groupQuestion->group);
        $this->assertEquals($group->id, $groupQuestion->group->id);
    }

    /** @test */
    public function it_belongs_to_an_event_question()
    {
        $eventQuestion = EventQuestion::factory()->create();
        $groupQuestion = GroupQuestion::factory()->create([
            'event_question_id' => $eventQuestion->id,
        ]);

        $this->assertInstanceOf(EventQuestion::class, $groupQuestion->eventQuestion);
        $this->assertEquals($eventQuestion->id, $groupQuestion->eventQuestion->id);
    }

    /** @test */
    public function it_can_be_a_custom_question_with_no_event_question()
    {
        $groupQuestion = GroupQuestion::factory()->create([
            'event_question_id' => null,
            'is_custom' => true,
        ]);

        $this->assertNull($groupQuestion->eventQuestion);
        $this->assertTrue($groupQuestion->is_custom);
    }

    /** @test */
    public function it_has_many_user_answers()
    {
        $groupQuestion = GroupQuestion::factory()->create();
        $userAnswer1 = UserAnswer::factory()->create(['group_question_id' => $groupQuestion->id]);
        $userAnswer2 = UserAnswer::factory()->create(['group_question_id' => $groupQuestion->id]);

        $this->assertCount(2, $groupQuestion->userAnswers);
        $this->assertTrue($groupQuestion->userAnswers->contains($userAnswer1));
        $this->assertTrue($groupQuestion->userAnswers->contains($userAnswer2));
    }

    /** @test */
    public function it_has_one_group_question_answer()
    {
        $groupQuestion = GroupQuestion::factory()->create();
        $groupAnswer = GroupQuestionAnswer::create([
            'group_id' => $groupQuestion->group_id,
            'group_question_id' => $groupQuestion->id,
            'correct_answer' => 'Test Answer',
            'is_void' => false,
        ]);

        $this->assertInstanceOf(GroupQuestionAnswer::class, $groupQuestion->groupQuestionAnswer);
        $this->assertEquals($groupAnswer->id, $groupQuestion->groupQuestionAnswer->id);
    }

    /** @test */
    public function active_scope_returns_only_active_questions()
    {
        $group = Group::factory()->create();

        // Active questions
        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_active' => true,
        ]);

        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_active' => true,
        ]);

        // Inactive question
        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_active' => false,
        ]);

        $activeQuestions = GroupQuestion::where('group_id', $group->id)->active()->get();

        $this->assertCount(2, $activeQuestions);
        foreach ($activeQuestions as $question) {
            $this->assertTrue($question->is_active);
        }
    }

    /** @test */
    public function custom_scope_returns_only_custom_questions()
    {
        $group = Group::factory()->create();

        // Custom questions
        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_custom' => true,
            'event_question_id' => null,
        ]);

        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_custom' => true,
            'event_question_id' => null,
        ]);

        // Standard question (from event)
        $eventQuestion = EventQuestion::factory()->create();
        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_custom' => false,
            'event_question_id' => $eventQuestion->id,
        ]);

        $customQuestions = GroupQuestion::where('group_id', $group->id)->custom()->get();

        $this->assertCount(2, $customQuestions);
        foreach ($customQuestions as $question) {
            $this->assertTrue($question->is_custom);
        }
    }

    /** @test */
    public function standard_scope_returns_only_non_custom_questions()
    {
        $group = Group::factory()->create();
        $eventQuestion = EventQuestion::factory()->create();

        // Standard questions (from event)
        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_custom' => false,
            'event_question_id' => $eventQuestion->id,
        ]);

        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_custom' => false,
            'event_question_id' => $eventQuestion->id,
        ]);

        // Custom question
        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_custom' => true,
            'event_question_id' => null,
        ]);

        $standardQuestions = GroupQuestion::where('group_id', $group->id)->standard()->get();

        $this->assertCount(2, $standardQuestions);
        foreach ($standardQuestions as $question) {
            $this->assertFalse($question->is_custom);
        }
    }

    /** @test */
    public function scopes_can_be_chained()
    {
        $group = Group::factory()->create();

        // Active custom question
        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_active' => true,
            'is_custom' => true,
        ]);

        // Inactive custom question
        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_active' => false,
            'is_custom' => true,
        ]);

        // Active standard question
        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'is_active' => true,
            'is_custom' => false,
        ]);

        // Get only active custom questions
        $activeCustomQuestions = GroupQuestion::where('group_id', $group->id)
            ->active()
            ->custom()
            ->get();

        $this->assertCount(1, $activeCustomQuestions);
        $this->assertTrue($activeCustomQuestions->first()->is_active);
        $this->assertTrue($activeCustomQuestions->first()->is_custom);
    }

    /** @test */
    public function it_casts_options_to_array()
    {
        $options = [
            ['label' => 'Yes', 'points' => 2],
            ['label' => 'No', 'points' => 0],
        ];

        $groupQuestion = GroupQuestion::factory()->create([
            'question_type' => 'multiple_choice',
            'options' => $options,
        ]);

        $this->assertIsArray($groupQuestion->options);
        $this->assertEquals($options, $groupQuestion->options);
    }

    /** @test */
    public function it_casts_is_active_to_boolean()
    {
        $groupQuestion = GroupQuestion::factory()->create(['is_active' => true]);

        $this->assertIsBool($groupQuestion->is_active);
        $this->assertTrue($groupQuestion->is_active);
    }

    /** @test */
    public function it_casts_is_custom_to_boolean()
    {
        $groupQuestion = GroupQuestion::factory()->create(['is_custom' => true]);

        $this->assertIsBool($groupQuestion->is_custom);
        $this->assertTrue($groupQuestion->is_custom);
    }

    /** @test */
    public function it_can_be_ordered_by_display_order()
    {
        $group = Group::factory()->create();

        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'display_order' => 3,
        ]);

        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'display_order' => 1,
        ]);

        GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'display_order' => 2,
        ]);

        $questions = GroupQuestion::where('group_id', $group->id)
            ->orderBy('display_order')
            ->get();

        $this->assertEquals(1, $questions[0]->display_order);
        $this->assertEquals(2, $questions[1]->display_order);
        $this->assertEquals(3, $questions[2]->display_order);
    }

    /** @test */
    public function it_stores_question_type_correctly()
    {
        $types = ['text', 'multiple_choice', 'numeric', 'yes_no'];

        foreach ($types as $type) {
            $question = GroupQuestion::factory()->create(['question_type' => $type]);
            $this->assertEquals($type, $question->question_type);
        }
    }

    /** @test */
    public function it_stores_points_as_integer()
    {
        $groupQuestion = GroupQuestion::factory()->create(['points' => 15]);

        $this->assertIsInt($groupQuestion->points);
        $this->assertEquals(15, $groupQuestion->points);
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $group = Group::factory()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $attributes = [
            'group_id' => $group->id,
            'event_question_id' => $eventQuestion->id,
            'question_text' => 'Test Question?',
            'question_type' => 'text',
            'options' => ['option1', 'option2'],
            'points' => 10,
            'display_order' => 1,
            'is_active' => true,
            'is_custom' => false,
        ];

        $groupQuestion = GroupQuestion::create($attributes);

        $this->assertEquals($group->id, $groupQuestion->group_id);
        $this->assertEquals($eventQuestion->id, $groupQuestion->event_question_id);
        $this->assertEquals('Test Question?', $groupQuestion->question_text);
        $this->assertEquals('text', $groupQuestion->question_type);
        $this->assertEquals(['option1', 'option2'], $groupQuestion->options);
        $this->assertEquals(10, $groupQuestion->points);
        $this->assertEquals(1, $groupQuestion->display_order);
        $this->assertTrue($groupQuestion->is_active);
        $this->assertFalse($groupQuestion->is_custom);
    }

    /** @test */
    public function active_scope_works_with_count()
    {
        $group = Group::factory()->create();

        GroupQuestion::factory()->count(3)->create([
            'group_id' => $group->id,
            'is_active' => true,
        ]);

        GroupQuestion::factory()->count(2)->create([
            'group_id' => $group->id,
            'is_active' => false,
        ]);

        $activeCount = GroupQuestion::where('group_id', $group->id)->active()->count();

        $this->assertEquals(3, $activeCount);
    }

    /** @test */
    public function it_differentiates_between_custom_and_event_questions()
    {
        $group = Group::factory()->create();
        $eventQuestion = EventQuestion::factory()->create();

        // Custom question (captain created)
        $customQuestion = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'event_question_id' => null,
            'is_custom' => true,
            'question_text' => 'Custom Captain Question',
        ]);

        // Standard question (from event)
        $standardQuestion = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'event_question_id' => $eventQuestion->id,
            'is_custom' => false,
            'question_text' => 'Event Question',
        ]);

        $this->assertTrue($customQuestion->is_custom);
        $this->assertNull($customQuestion->event_question_id);

        $this->assertFalse($standardQuestion->is_custom);
        $this->assertNotNull($standardQuestion->event_question_id);
    }
}
