<?php

namespace Tests\Feature\PropOff;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\EventAnswer;
use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestion;
use App\Models\PropOff\GroupQuestionAnswer;
use App\Models\User;
use App\Models\PropOff\UserAnswer;
use App\Services\PropOff\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualGradingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected EntryService $entryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entryService = new EntryService();
    }

    /** @test */
    public function captain_grading_mode_uses_group_question_answers()
    {
        // Create captain-graded group
        $group = Group::factory()->create(['grading_source' => 'captain']);
        $entry = Entry::factory()->create(['group_id' => $group->id]);
        $question = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        // Captain sets answer
        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $question->id,
            'correct_answer' => 'Captain Answer',
            'is_void' => false,
        ]);

        // User submits answer
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $question->id,
            'answer_text' => 'Captain Answer',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        // Grade entry
        $this->entryService->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertTrue($userAnswer->is_correct);
        $this->assertEquals(10, $userAnswer->points_earned);
    }

    /** @test */
    public function admin_grading_mode_uses_event_answers()
    {
        // Create admin-graded group
        $event = Event::factory()->create();
        $group = Group::factory()->create([
            'event_id' => $event->id,
            'grading_source' => 'admin',
        ]);
        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
        ]);

        // Create event question
        $eventQuestion = EventQuestion::factory()->create([
            'event_id' => $event->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        // Create group question linked to event question
        $groupQuestion = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'event_question_id' => $eventQuestion->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        // Admin sets answer
        EventAnswer::create([
            'event_id' => $event->id,
            'event_question_id' => $eventQuestion->id,
            'correct_answer' => 'Admin Answer',
            'is_void' => false,
        ]);

        // User submits answer
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $groupQuestion->id,
            'answer_text' => 'Admin Answer',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        // Grade entry
        $this->entryService->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertTrue($userAnswer->is_correct);
        $this->assertEquals(10, $userAnswer->points_earned);
    }

    /** @test */
    public function captain_grading_ignores_admin_answers()
    {
        // Create captain-graded group
        $event = Event::factory()->create();
        $group = Group::factory()->create([
            'event_id' => $event->id,
            'grading_source' => 'captain',
        ]);
        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
        ]);

        $eventQuestion = EventQuestion::factory()->create([
            'event_id' => $event->id,
            'points' => 10,
        ]);

        $question = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'event_question_id' => $eventQuestion->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        // Admin sets one answer
        EventAnswer::create([
            'event_id' => $event->id,
            'event_question_id' => $eventQuestion->id,
            'correct_answer' => 'Admin Answer',
            'is_void' => false,
        ]);

        // Captain sets different answer
        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $question->id,
            'correct_answer' => 'Captain Answer',
            'is_void' => false,
        ]);

        // User submits captain's answer (not admin's)
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $question->id,
            'answer_text' => 'Captain Answer',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        // Grade entry - should use captain's answer
        $this->entryService->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertTrue($userAnswer->is_correct);
        $this->assertEquals(10, $userAnswer->points_earned);
    }

    /** @test */
    public function admin_grading_uses_event_answers_not_captain_answers()
    {
        // Create admin-graded group
        $event = Event::factory()->create();
        $group = Group::factory()->create([
            'event_id' => $event->id,
            'grading_source' => 'admin',
        ]);
        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
        ]);

        $eventQuestion = EventQuestion::factory()->create([
            'event_id' => $event->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        $question = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'event_question_id' => $eventQuestion->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        // Admin sets one answer
        EventAnswer::create([
            'event_id' => $event->id,
            'event_question_id' => $eventQuestion->id,
            'correct_answer' => 'Admin Answer',
            'is_void' => false,
        ]);

        // Captain also sets answer (should be ignored)
        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $question->id,
            'correct_answer' => 'Captain Answer',
            'is_void' => false,
        ]);

        // User submits admin's answer
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $question->id,
            'answer_text' => 'Admin Answer',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        // Grade entry - should use admin's answer
        $this->entryService->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertTrue($userAnswer->is_correct);
        $this->assertEquals(10, $userAnswer->points_earned);
    }

    /** @test */
    public function custom_captain_questions_only_work_with_captain_grading()
    {
        // Create captain-graded group with custom question (no event_question_id)
        $group = Group::factory()->create(['grading_source' => 'captain']);
        $entry = Entry::factory()->create(['group_id' => $group->id]);

        $customQuestion = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'event_question_id' => null, // Custom question
            'points' => 10,
            'question_type' => 'text',
        ]);

        // Captain sets answer for custom question
        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $customQuestion->id,
            'correct_answer' => 'Custom Answer',
            'is_void' => false,
        ]);

        // User submits answer
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $customQuestion->id,
            'answer_text' => 'Custom Answer',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        // Grade entry
        $this->entryService->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertTrue($userAnswer->is_correct);
        $this->assertEquals(10, $userAnswer->points_earned);
    }

    /** @test */
    public function captain_custom_points_override_base_points()
    {
        $group = Group::factory()->create(['grading_source' => 'captain']);
        $entry = Entry::factory()->create(['group_id' => $group->id]);

        $question = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'points' => 10, // Base points
            'question_type' => 'text',
        ]);

        // Captain sets custom points
        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $question->id,
            'correct_answer' => 'Answer',
            'is_void' => false,
            'points_awarded' => 15, // Custom points
        ]);

        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $question->id,
            'answer_text' => 'Answer',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        $this->entryService->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertTrue($userAnswer->is_correct);
        $this->assertEquals(15, $userAnswer->points_earned); // Custom points, not 10
    }

    /** @test */
    public function switching_grading_modes_changes_scoring_source()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create([
            'event_id' => $event->id,
            'grading_source' => 'captain',
        ]);
        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
        ]);

        $eventQuestion = EventQuestion::factory()->create([
            'event_id' => $event->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        $question = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'event_question_id' => $eventQuestion->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        EventAnswer::create([
            'event_id' => $event->id,
            'event_question_id' => $eventQuestion->id,
            'correct_answer' => 'Admin Answer',
            'is_void' => false,
        ]);

        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $question->id,
            'correct_answer' => 'Captain Answer',
            'is_void' => false,
        ]);

        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $question->id,
            'answer_text' => 'Captain Answer',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        // Grade with captain mode
        $this->entryService->gradeEntry($entry);
        $entry->refresh();
        $this->assertTrue($entry->userAnswers()->first()->is_correct);

        // Switch to admin mode
        $group->update(['grading_source' => 'admin']);
        $entry->load('group'); // Refresh relationship to pick up new grading_source
        $entry->userAnswers()->update(['is_correct' => false, 'points_earned' => 0]);

        // Re-grade with admin mode - should now be incorrect
        $this->entryService->gradeEntry($entry);
        $entry->refresh();
        $this->assertFalse($entry->userAnswers()->first()->is_correct);
    }
}
