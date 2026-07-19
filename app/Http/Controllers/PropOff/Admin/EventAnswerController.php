<?php

namespace App\Http\Controllers\PropOff\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropOff\Admin\SetEventAnswerRequest;
use App\Models\PropOff\Event;
use App\Models\PropOff\EventAnswer;
use App\Models\PropOff\EventQuestion;
use App\Services\PropOff\EntryService;
use App\Services\PropOff\LeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventAnswerController extends Controller
{
    protected $entryService;
    protected $leaderboardService;

    public function __construct(EntryService $entryService, LeaderboardService $leaderboardService)
    {
        $this->entryService = $entryService;
        $this->leaderboardService = $leaderboardService;
    }

    /**
     * Display the event-level grading interface.
     */
    public function index(Request $request, Event $event)
    {
        // Get all event questions with their admin-set answers
        $questions = $event->eventQuestions()
            ->with('eventAnswers')
            ->orderBy('display_order')
            ->get()
            ->map(function ($question) {
                $answer = $question->eventAnswers->first();

                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'options' => $question->options,
                    'points' => $question->points,
                    'order' => $question->display_order,
                    'answer' => $answer ? [
                        'id' => $answer->id,
                        'correct_answer' => $answer->correct_answer,
                        'is_void' => $answer->is_void,
                        'set_at' => $answer->set_at,
                        'set_by' => $answer->setBy ? [
                            'id' => $answer->setBy->id,
                            'name' => $answer->setBy->name,
                        ] : null,
                    ] : null,
                ];
            });

        // Get groups using admin grading
        $adminGradingGroups = $event->groups()
            ->where('grading_source', 'admin')
            ->withCount('members')
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'members_count' => $group->members_count,
                ];
            });

        return Inertia::render('PropOff/Admin/EventAnswers/Index', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'status' => $event->status,
                'event_date' => $event->event_date,
            ],
            'questions' => $questions,
            'adminGradingGroups' => $adminGradingGroups,
        ]);
    }

    /**
     * Set the correct answer for a specific event question.
     */
    public function setAnswer(SetEventAnswerRequest $request, Event $event, EventQuestion $eventQuestion)
    {
        // Ensure question belongs to this event
        if ($eventQuestion->event_id !== $event->id) {
            abort(404);
        }

        // Check if event is locked (lock_date has passed)
        if (!$event->is_locked) {
            return back()->with('error', 'Cannot set answers until the event lock date has passed.');
        }

        // Create or update the event answer
        $eventAnswer = EventAnswer::updateOrCreate(
            [
                'event_id' => $event->id,
                'event_question_id' => $eventQuestion->id,
            ],
            [
                'correct_answer' => $request->correct_answer,
                'is_void' => $request->is_void ?? false,
                'set_at' => now(),
                'set_by' => $request->user()->id,
            ]
        );

        // Recalculate scores for all groups using admin grading
        $adminGroups = $event->groups()->where('grading_source', 'admin')->get();

        foreach ($adminGroups as $group) {
            $entries = $group->entries()->get();
            foreach ($entries as $entry) {
                $this->entryService->calculateScore($entry);
            }

            // Update leaderboard for this group
            $this->leaderboardService->updateLeaderboard($event, $group);
        }

        return back()->with('success', 'Event answer set successfully! Scores recalculated for admin-graded groups.');
    }

    /**
     * Bulk set answers for all event questions.
     */
    public function bulkSetAnswers(Request $request, Event $event)
    {
        // Check if event is locked (lock_date has passed)
        if (!$event->is_locked) {
            return back()->with('error', 'Cannot set answers until the event lock date has passed.');
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.event_question_id' => 'required|exists:propoff_event_questions,id',
            'answers.*.correct_answer' => 'required|string',
            'answers.*.is_void' => 'nullable|boolean',
        ]);

        foreach ($validated['answers'] as $answerData) {
            $eventQuestion = EventQuestion::find($answerData['event_question_id']);

            // Ensure question belongs to this event
            if ($eventQuestion->event_id !== $event->id) {
                continue;
            }

            EventAnswer::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'event_question_id' => $eventQuestion->id,
                ],
                [
                    'correct_answer' => $answerData['correct_answer'],
                    'is_void' => $answerData['is_void'] ?? false,
                    'set_at' => now(),
                    'set_by' => $request->user()->id,
                ]
            );
        }

        // Recalculate scores for all groups using admin grading
        $adminGroups = $event->groups()->where('grading_source', 'admin')->get();

        foreach ($adminGroups as $group) {
            $entries = $group->entries()->get();
            foreach ($entries as $entry) {
                $this->entryService->calculateScore($entry);
            }

            // Update leaderboard for this group
            $this->leaderboardService->updateLeaderboard($event, $group);
        }

        return back()->with('success', 'All event answers set successfully! Scores recalculated for admin-graded groups.');
    }

    /**
     * Toggle void status for an event question.
     */
    public function toggleVoid(Request $request, Event $event, EventQuestion $eventQuestion)
    {
        // Ensure question belongs to this event
        if ($eventQuestion->event_id !== $event->id) {
            abort(404);
        }

        // Check if event is locked (lock_date has passed)
        if (!$event->is_locked) {
            return back()->with('error', 'Cannot modify answers until the event lock date has passed.');
        }

        $eventAnswer = EventAnswer::where('event_id', $event->id)
            ->where('event_question_id', $eventQuestion->id)
            ->first();

        if (!$eventAnswer) {
            return back()->with('error', 'No answer set for this question yet.');
        }

        $eventAnswer->update(['is_void' => !$eventAnswer->is_void]);

        // Recalculate scores for all groups using admin grading
        $adminGroups = $event->groups()->where('grading_source', 'admin')->get();

        foreach ($adminGroups as $group) {
            $entries = $group->entries()->get();
            foreach ($entries as $entry) {
                $this->entryService->calculateScore($entry);
            }

            // Update leaderboard for this group
            $this->leaderboardService->updateLeaderboard($event, $group);
        }

        $status = $eventAnswer->is_void ? 'voided' : 'unvoided';

        return back()->with('success', "Question {$status} successfully! Scores recalculated for admin-graded groups.");
    }

    /**
     * Clear the answer for a specific event question.
     */
    public function clearAnswer(Request $request, Event $event, EventQuestion $eventQuestion)
    {
        // Ensure question belongs to this event
        if ($eventQuestion->event_id !== $event->id) {
            abort(404);
        }

        $eventAnswer = EventAnswer::where('event_id', $event->id)
            ->where('event_question_id', $eventQuestion->id)
            ->first();

        if ($eventAnswer) {
            $eventAnswer->delete();
        }

        return back()->with('success', 'Event answer cleared successfully!');
    }
}
