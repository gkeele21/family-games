<?php

namespace Tests\Unit\PropOff\Services;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\EventAnswer;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestion;
use App\Models\PropOff\GroupQuestionAnswer;
use App\Models\User;
use App\Models\PropOff\UserAnswer;
use App\Services\PropOff\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EntryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EntryService();
    }

    /** @test */
    public function create_entry_creates_entry_with_correct_attributes()
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        $entry = $this->service->createEntry($event, $user, $group);

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertEquals($event->id, $entry->event_id);
        $this->assertEquals($user->id, $entry->user_id);
        $this->assertEquals($group->id, $entry->group_id);
        $this->assertEquals(0, $entry->total_score);
        $this->assertFalse($entry->is_complete);
        $this->assertNull($entry->submitted_at);
    }

    /** @test */
    public function save_answers_creates_user_answers()
    {
        $entry = Entry::factory()->create();
        $groupQuestion = GroupQuestion::factory()->create([
            'group_id' => $entry->group_id,
        ]);

        $answers = [
            [
                'group_question_id' => $groupQuestion->id,
                'answer_text' => 'Test Answer',
            ],
        ];

        $this->service->saveAnswers($entry, $answers);

        $this->assertEquals(1, $entry->userAnswers()->count());
        $userAnswer = $entry->userAnswers()->first();
        $this->assertEquals('Test Answer', $userAnswer->answer_text);
        $this->assertEquals($groupQuestion->id, $userAnswer->group_question_id);
    }

    /** @test */
    public function save_answers_updates_existing_answers()
    {
        $entry = Entry::factory()->create();
        $groupQuestion = GroupQuestion::factory()->create([
            'group_id' => $entry->group_id,
        ]);

        // Create initial answer
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $groupQuestion->id,
            'answer_text' => 'Old Answer',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        // Update with new answer
        $answers = [
            [
                'group_question_id' => $groupQuestion->id,
                'answer_text' => 'New Answer',
            ],
        ];

        $this->service->saveAnswers($entry, $answers);

        $this->assertEquals(1, $entry->userAnswers()->count());
        $userAnswer = $entry->userAnswers()->first();
        $this->assertEquals('New Answer', $userAnswer->answer_text);
    }

    /** @test */
    public function complete_entry_marks_entry_as_complete()
    {
        $entry = Entry::factory()->create(['is_complete' => false]);

        $this->service->completeEntry($entry);

        $this->assertTrue($entry->fresh()->is_complete);
        $this->assertNotNull($entry->fresh()->submitted_at);
    }

    /** @test */
    public function grade_entry_uses_captain_grading_when_source_is_captain()
    {
        $group = Group::factory()->create(['grading_source' => 'captain']);
        $entry = Entry::factory()->create(['group_id' => $group->id]);
        $groupQuestion = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        // Set captain's correct answer
        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $groupQuestion->id,
            'correct_answer' => 'Correct',
            'is_void' => false,
        ]);

        // User's answer
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $groupQuestion->id,
            'answer_text' => 'Correct',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        $this->service->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertTrue($userAnswer->is_correct);
        $this->assertEquals(10, $userAnswer->points_earned);
        $this->assertEquals(10, $entry->total_score);
        $this->assertEquals(100, $entry->percentage);
    }

    /** @test */
    public function grade_entry_uses_admin_grading_when_source_is_admin()
    {
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
        $eventQuestion = \App\Models\PropOff\EventQuestion::factory()->create([
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

        // Set admin's correct answer
        EventAnswer::create([
            'event_id' => $event->id,
            'event_question_id' => $eventQuestion->id,
            'correct_answer' => 'Correct',
            'is_void' => false,
        ]);

        // User's answer
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $groupQuestion->id,
            'answer_text' => 'Correct',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        $this->service->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertTrue($userAnswer->is_correct);
        $this->assertEquals(10, $userAnswer->points_earned);
        $this->assertEquals(10, $entry->total_score);
    }

    /** @test */
    public function grade_entry_handles_voided_questions()
    {
        $group = Group::factory()->create(['grading_source' => 'captain']);
        $entry = Entry::factory()->create(['group_id' => $group->id]);
        $groupQuestion = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'points' => 10,
        ]);

        // Set question as void
        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $groupQuestion->id,
            'correct_answer' => 'Answer',
            'is_void' => true,
        ]);

        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $groupQuestion->id,
            'answer_text' => 'Answer',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        $this->service->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertFalse($userAnswer->is_correct);
        $this->assertEquals(0, $userAnswer->points_earned);
        $this->assertEquals(0, $entry->total_score);
    }

    /** @test */
    public function grade_entry_handles_incorrect_answers()
    {
        $group = Group::factory()->create(['grading_source' => 'captain']);
        $entry = Entry::factory()->create(['group_id' => $group->id]);
        $groupQuestion = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $groupQuestion->id,
            'correct_answer' => 'Correct',
            'is_void' => false,
        ]);

        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $groupQuestion->id,
            'answer_text' => 'Wrong',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        $this->service->gradeEntry($entry);

        $entry->refresh();
        $userAnswer = $entry->userAnswers()->first();

        $this->assertFalse($userAnswer->is_correct);
        $this->assertEquals(0, $userAnswer->points_earned);
        $this->assertEquals(0, $entry->total_score);
        $this->assertEquals(0, $entry->percentage);
    }

    /** @test */
    public function grade_entry_handles_numeric_questions_with_tolerance()
    {
        $group = Group::factory()->create(['grading_source' => 'captain']);
        $entry = Entry::factory()->create(['group_id' => $group->id]);
        $groupQuestion = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'points' => 10,
            'question_type' => 'numeric',
        ]);

        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $groupQuestion->id,
            'correct_answer' => '100',
            'is_void' => false,
        ]);

        // Answer within tolerance
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $groupQuestion->id,
            'answer_text' => '100.005',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        $this->service->gradeEntry($entry);

        $userAnswer = $entry->userAnswers()->first();
        $this->assertTrue($userAnswer->is_correct);
        $this->assertEquals(10, $userAnswer->points_earned);
    }

    /** @test */
    public function grade_entry_calculates_percentage_correctly()
    {
        $group = Group::factory()->create(['grading_source' => 'captain']);
        $entry = Entry::factory()->create(['group_id' => $group->id]);

        // Create 2 questions worth 10 points each
        $question1 = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'points' => 10,
            'question_type' => 'text',
        ]);
        $question2 = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'points' => 10,
            'question_type' => 'text',
        ]);

        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $question1->id,
            'correct_answer' => 'Answer1',
            'is_void' => false,
        ]);
        GroupQuestionAnswer::create([
            'group_id' => $group->id,
            'group_question_id' => $question2->id,
            'correct_answer' => 'Answer2',
            'is_void' => false,
        ]);

        // User gets one correct, one wrong
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $question1->id,
            'answer_text' => 'Answer1',
            'points_earned' => 0,
            'is_correct' => false,
        ]);
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $question2->id,
            'answer_text' => 'Wrong',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        $this->service->gradeEntry($entry);

        $entry->refresh();
        // Current semantics: wrong answers are LOST possible points, so
        // possible = points still achievable (10), percentage = 100.
        $this->assertEquals(10, $entry->total_score);
        $this->assertEquals(10, $entry->possible_points);
        $this->assertEquals(100, $entry->percentage);
    }

    /** @test */
    public function can_edit_entry_returns_false_after_lock_date()
    {
        $event = Event::factory()->create(['lock_date' => now()->subDay()]);
        $entry = Entry::factory()->create(['event_id' => $event->id]);

        $this->assertFalse($this->service->canEditEntry($entry));
    }

    /** @test */
    public function can_edit_entry_returns_false_for_completed_events()
    {
        $event = Event::factory()->create(['status' => 'completed']);
        $entry = Entry::factory()->create(['event_id' => $event->id]);

        $this->assertFalse($this->service->canEditEntry($entry));
    }

    /** @test */
    public function can_edit_entry_returns_true_when_editable()
    {
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDay(),
        ]);
        $entry = Entry::factory()->create(['event_id' => $event->id]);

        $this->assertTrue($this->service->canEditEntry($entry));
    }
}
