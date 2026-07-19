<?php

namespace App\Http\Controllers\PropOff\Captain;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropOff\Captain\SetAnswerRequest;
use App\Models\PropOff\EventAnswer;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestion;
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
     * Set the correct answer for a specific question.
     */
    public function setAnswer(SetAnswerRequest $request, Group $group, GroupQuestion $groupQuestion)
    {
        // Ensure question belongs to this group
        if ($groupQuestion->group_id !== $group->id) {
            abort(404);
        }

        // Check if entry cutoff has passed (group must be locked to grade)
        if (!$group->is_locked) {
            return back()->with('error', 'Cannot set answers until the entry cutoff has passed.');
        }

        // Check if group uses captain grading
        if ($group->grading_source !== 'captain') {
            return back()->with('error', 'This group uses admin grading. Cannot set captain answers.');
        }

        // Create or update the answer
        GroupQuestionAnswer::updateOrCreate(
            [
                'group_id' => $group->id,
                'group_question_id' => $groupQuestion->id,
            ],
            [
                'question_id' => $groupQuestion->event_question_id, // Can be null for custom questions
                'correct_answer' => $request->correct_answer,
                'points_awarded' => $request->points_awarded,
                'is_void' => $request->is_void ?? false,
            ]
        );

        // Sync to admin grading if requested and question is linked to an event question
        if ($request->sync_to_admin && $groupQuestion->event_question_id) {
            EventAnswer::updateOrCreate(
                [
                    'event_id' => $group->event_id,
                    'event_question_id' => $groupQuestion->event_question_id,
                ],
                [
                    'correct_answer' => $request->correct_answer,
                    'is_void' => $request->is_void ?? false,
                    'set_at' => now(),
                    'set_by' => auth()->id(),
                ]
            );

            // Recalculate scores for all groups using admin grading (batch)
            $adminGroups = $group->event->groups()->where('grading_source', 'admin')->get();
            foreach ($adminGroups as $adminGroup) {
                $this->entryService->batchGradeGroup($adminGroup);
                $this->leaderboardService->updateLeaderboard($group->event, $adminGroup);
            }
        }

        // Batch recalculate scores for all entries in this group
        $this->entryService->batchGradeGroup($group);

        // Update leaderboard
        $this->leaderboardService->updateLeaderboard($group->event, $group);

        return back()->with('success', 'Answer set successfully! Scores recalculated.');
    }

    /**
     * Toggle void status for a question.
     */
    public function toggleVoid(Request $request, Group $group, GroupQuestion $groupQuestion)
    {
        // Ensure question belongs to this group
        if ($groupQuestion->group_id !== $group->id) {
            abort(404);
        }

        // Check if entry cutoff has passed (group must be locked to grade)
        if (!$group->is_locked) {
            return back()->with('error', 'Cannot modify answers until the entry cutoff has passed.');
        }

        // Check if group uses captain grading
        if ($group->grading_source !== 'captain') {
            return back()->with('error', 'This group uses admin grading. Cannot modify captain answers.');
        }

        $answer = GroupQuestionAnswer::where('group_id', $group->id)
            ->where('group_question_id', $groupQuestion->id)
            ->first();

        if (!$answer) {
            return back()->with('error', 'No answer set for this question yet.');
        }

        $answer->update(['is_void' => !$answer->is_void]);

        // Batch recalculate scores
        $this->entryService->batchGradeGroup($group);

        // Update leaderboard
        $this->leaderboardService->updateLeaderboard($group->event, $group);

        $status = $answer->is_void ? 'voided' : 'unvoided';

        return back()->with('success', "Question {$status} successfully! Scores recalculated.");
    }
}
