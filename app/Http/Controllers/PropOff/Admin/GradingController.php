<?php

namespace App\Http\Controllers\PropOff\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropOff\Event;
use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestionAnswer;
use App\Services\PropOff\EntryService;
use App\Services\PropOff\LeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GradingController extends Controller
{
    protected $entryService;
    protected $leaderboardService;

    public function __construct(EntryService $entryService, LeaderboardService $leaderboardService)
    {
        $this->entryService = $entryService;
        $this->leaderboardService = $leaderboardService;
    }

    /**
     * Display grading interface for an event.
     */
    public function index(Event $event)
    {
        // Get all event questions for this event
        $questions = $event->eventQuestions()
            ->orderBy('display_order')
            ->get();

        // Get all groups for this event
        $groups = $event->groups()
            ->withCount('members')
            ->orderBy('name')
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'grading_source' => $group->grading_source,
                    'members_count' => $group->members_count,
                ];
            });

        // Get all group question answers for captain-graded groups
        $groupAnswers = GroupQuestionAnswer::whereHas('group', function ($query) use ($event) {
                $query->where('event_id', $event->id)
                    ->where('grading_source', 'captain');
            })
            ->with(['group'])
            ->get()
            ->groupBy('group_id');

        return Inertia::render('PropOff/Admin/Grading/Index', [
            'event' => $event,
            'questions' => $questions,
            'groups' => $groups,
            'groupAnswers' => $groupAnswers,
        ]);
    }

    /**
     * Set group-specific correct answer for a question (for captain-graded groups).
     */
    public function setAnswer(Request $request, Event $event, EventQuestion $eventQuestion)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:propoff_groups,id',
            'correct_answer' => 'required|string',
            'points_awarded' => 'nullable|integer|min:0',
            'is_void' => 'nullable|boolean',
        ]);

        // Ensure group uses captain grading
        $group = Group::findOrFail($validated['group_id']);
        if ($group->grading_source !== 'captain') {
            return back()->with('error', 'This group uses admin grading. Use event answers instead.');
        }

        // Find the group question
        $groupQuestion = $group->groupQuestions()
            ->where('event_question_id', $eventQuestion->id)
            ->first();

        if (!$groupQuestion) {
            return back()->with('error', 'Question not found for this group.');
        }

        GroupQuestionAnswer::updateOrCreate(
            [
                'group_id' => $validated['group_id'],
                'group_question_id' => $groupQuestion->id,
            ],
            [
                'correct_answer' => $validated['correct_answer'],
                'points_awarded' => $validated['points_awarded'] ?? null,
                'is_void' => $validated['is_void'] ?? false,
            ]
        );

        return back()->with('success', 'Answer set successfully for captain-graded group!');
    }

    /**
     * Bulk set answers for all questions in a group.
     */
    public function bulkSetAnswers(Request $request, Event $event, Group $group)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:propoff_event_questions,id',
            'answers.*.correct_answer' => 'required|string',
            'answers.*.points_awarded' => 'nullable|integer|min:0',
            'answers.*.is_void' => 'nullable|boolean',
        ]);

        foreach ($validated['answers'] as $answerData) {
            // Find the group question
            $groupQuestion = $group->groupQuestions()
                ->where('event_question_id', $answerData['question_id'])
                ->first();

            if (!$groupQuestion) {
                continue;
            }

            GroupQuestionAnswer::updateOrCreate(
                [
                    'group_id' => $group->id,
                    'group_question_id' => $groupQuestion->id,
                ],
                [
                    'correct_answer' => $answerData['correct_answer'],
                    'points_awarded' => $answerData['points_awarded'] ?? null,
                    'is_void' => $answerData['is_void'] ?? false,
                ]
            );
        }

        return back()->with('success', 'Answers set successfully for group!');
    }

    /**
     * Mark/unmark a question as void for a specific group (captain-graded groups).
     */
    public function toggleVoid(Request $request, Event $event, EventQuestion $eventQuestion, Group $group)
    {
        // Ensure group uses captain grading
        if ($group->grading_source !== 'captain') {
            return back()->with('error', 'This group uses admin grading. Use event answers instead.');
        }

        // Find the group question
        $groupQuestion = $group->groupQuestions()
            ->where('event_question_id', $eventQuestion->id)
            ->first();

        if (!$groupQuestion) {
            return back()->with('error', 'Question not found for this group.');
        }

        $answer = GroupQuestionAnswer::firstOrCreate(
            [
                'group_id' => $group->id,
                'group_question_id' => $groupQuestion->id,
            ],
            [
                'correct_answer' => '',
                'is_void' => false,
            ]
        );

        $answer->update(['is_void' => !$answer->is_void]);

        $status = $answer->is_void ? 'voided' : 'unvoided';
        return back()->with('success', "Question {$status} successfully!");
    }

    /**
     * Trigger score calculation for all entries in the event.
     */
    public function calculateScores(Event $event)
    {
        // Batch grade by group for efficiency
        $groups = $event->groups()->get();
        $gradedCount = 0;

        foreach ($groups as $group) {
            $entryCount = $group->entries()->count();
            $this->entryService->batchGradeGroup($group);
            $gradedCount += $entryCount;
        }

        // Update leaderboards
        $this->leaderboardService->recalculateEventLeaderboards($event);

        return back()->with('success', "Calculated scores for {$gradedCount} entries and updated leaderboards!");
    }

    /**
     * Export event results to CSV.
     */
    public function exportCSV(Event $event)
    {
        $entries = $event->entries()
            ->with(['user', 'group', 'userAnswers.question'])
            ->where('is_complete', true)
            ->get();

        $filename = "event_{$event->id}_results_" . now()->format('Y-m-d_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($entries, $event) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, [
                'Entry ID',
                'User Name',
                'User Email',
                'Group Name',
                'Total Score',
                'Possible Points',
                'Percentage',
                'Submitted At',
                'Questions Answered',
            ]);

            // Data rows
            foreach ($entries as $entry) {
                fputcsv($file, [
                    $entry->id,
                    $entry->user->name,
                    $entry->user->email,
                    $entry->group->name,
                    $entry->total_score,
                    $entry->possible_points,
                    round($entry->percentage, 2) . '%',
                    $entry->submitted_at->format('Y-m-d H:i:s'),
                    $entry->userAnswers->count(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export detailed answers to CSV.
     */
    public function exportDetailedCSV(Event $event, ?Group $group = null)
    {
        $query = $event->entries()
            ->with(['user', 'group', 'userAnswers.question', 'userAnswers.question.groupQuestionAnswers'])
            ->where('is_complete', true);

        if ($group) {
            $query->where('group_id', $group->id);
        }

        $entries = $query->get();

        $filename = "event_{$event->id}_detailed_" . ($group ? "group_{$group->id}_" : '') . now()->format('Y-m-d_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($entries) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, [
                'Entry ID',
                'User Name',
                'Group Name',
                'Question #',
                'Question Text',
                'User Answer',
                'Correct Answer',
                'Is Correct',
                'Points Earned',
                'Max Points',
                'Is Void',
            ]);

            // Data rows
            foreach ($entries as $entry) {
                foreach ($entry->userAnswers as $userAnswer) {
                    $question = $userAnswer->question;

                    // Get group-specific correct answer
                    $groupAnswer = $question->groupQuestionAnswers
                        ->where('group_id', $entry->group_id)
                        ->first();

                    // Use group-specific points if set, otherwise default question points
                    $maxPoints = ($groupAnswer && $groupAnswer->points_awarded !== null)
                        ? $groupAnswer->points_awarded
                        : $question->points;

                    fputcsv($file, [
                        $entry->id,
                        $entry->user->name,
                        $entry->group->name,
                        $question->order ?? $question->display_order ?? 'N/A',
                        $question->question_text,
                        $userAnswer->answer_text,
                        $groupAnswer->correct_answer ?? 'Not Set',
                        $userAnswer->is_correct ? 'Yes' : 'No',
                        $userAnswer->points_earned,
                        $maxPoints,
                        $groupAnswer && $groupAnswer->is_void ? 'Yes' : 'No',
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * View grading summary for a specific group.
     */
    public function groupSummary(Event $event, Group $group)
    {
        $questions = $event->eventQuestions()->orderBy('display_order')->get();

        // Get group-specific answers
        $groupQuestions = $group->groupQuestions()
            ->whereIn('event_question_id', $questions->pluck('id'))
            ->get()
            ->keyBy('event_question_id');

        $groupAnswers = GroupQuestionAnswer::where('group_id', $group->id)
            ->whereIn('group_question_id', $groupQuestions->pluck('id'))
            ->get()
            ->mapWithKeys(function ($answer) use ($groupQuestions) {
                $groupQuestion = $groupQuestions->get($answer->group_question_id);
                return [$groupQuestion?->event_question_id => $answer];
            });

        // Get entry statistics for this group
        $entries = $event->entries()
            ->where('group_id', $group->id)
            ->where('is_complete', true)
            ->get();

        $stats = [
            'total_entries' => $entries->count(),
            'average_score' => $entries->avg('percentage'),
            'highest_score' => $entries->max('percentage'),
            'lowest_score' => $entries->min('percentage'),
        ];

        return Inertia::render('PropOff/Admin/Grading/GroupSummary', [
            'event' => $event,
            'group' => $group,
            'questions' => $questions,
            'groupAnswers' => $groupAnswers,
            'stats' => $stats,
        ]);
    }
}
