<?php

namespace App\Http\Controllers\PropOff\Captain;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropOff\Captain\StoreGroupQuestionRequest;
use App\Http\Requests\PropOff\Captain\UpdateGroupQuestionRequest;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GroupQuestionController extends Controller
{
    /**
     * Store a newly created custom question.
     */
    public function store(StoreGroupQuestionRequest $request, Group $group)
    {
        $groupQuestion = GroupQuestion::create([
            'group_id' => $group->id,
            'event_question_id' => null, // Custom question, not linked to event question
            'question_text' => $request->question_text,
            'question_type' => $request->question_type,
            'options' => $request->options,
            'points' => $request->points,
            'display_order' => $request->display_order ?? ($group->groupQuestions()->max('display_order') + 1 ?? 1),
            'is_active' => true,
            'is_custom' => true, // Mark as custom
        ]);

        return redirect()->route('propoff.groups.questions', $group)
            ->with('success', 'Custom question added successfully!');
    }

    /**
     * Update the specified question.
     */
    public function update(UpdateGroupQuestionRequest $request, Group $group, GroupQuestion $groupQuestion)
    {
        // Ensure question belongs to this group
        if ($groupQuestion->group_id !== $group->id) {
            abort(404);
        }

        $groupQuestion->update([
            'question_text' => $request->question_text,
            'question_type' => $request->question_type,
            'options' => $request->options,
            'points' => $request->points,
            'display_order' => $request->display_order ?? $groupQuestion->display_order,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('propoff.groups.questions', $group)
            ->with('success', 'Question updated successfully!');
    }

    /**
     * Remove the specified question (or deactivate it).
     */
    public function destroy(Request $request, Group $group, GroupQuestion $groupQuestion)
    {
        // Ensure question belongs to this group
        if ($groupQuestion->group_id !== $group->id) {
            abort(404);
        }

        // Check if question has user answers
        if ($groupQuestion->userAnswers()->exists()) {
            // Deactivate instead of delete if there are user answers
            $groupQuestion->update(['is_active' => false]);
            return back()->with('success', 'Question deactivated (has user answers).');
        }

        // Delete if no user answers
        $groupQuestion->delete();

        // Reorder remaining questions
        $group->groupQuestions()
            ->where('display_order', '>', $groupQuestion->display_order)
            ->decrement('display_order');

        return redirect()->route('propoff.groups.questions', $group)
            ->with('success', 'Question deleted successfully!');
    }

    /**
     * Toggle question active status.
     */
    public function toggleActive(Request $request, Group $group, GroupQuestion $groupQuestion)
    {
        // Ensure question belongs to this group
        if ($groupQuestion->group_id !== $group->id) {
            abort(404);
        }

        $groupQuestion->update([
            'is_active' => !$groupQuestion->is_active,
        ]);

        $status = $groupQuestion->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Question {$status} successfully!");
    }

    /**
     * Reorder questions.
     *
     * Supports two formats:
     * 1. Single question reorder (drag-and-drop): { question_id, new_order }
     * 2. Bulk reorder: { order: [id1, id2, ...] }
     */
    public function reorder(Request $request, Group $group)
    {
        // Check if using single-question format (drag-and-drop)
        if ($request->has('question_id') && $request->has('new_order')) {
            $validated = $request->validate([
                'question_id' => 'required|exists:propoff_group_questions,id',
                'new_order' => 'required|integer|min:1',
            ]);

            $question = GroupQuestion::where('id', $validated['question_id'])
                ->where('group_id', $group->id)
                ->firstOrFail();

            $oldOrder = $question->display_order;
            $newOrder = $validated['new_order'];

            if ($oldOrder < $newOrder) {
                // Moving down - shift up questions in between
                GroupQuestion::where('group_id', $group->id)
                    ->where('display_order', '>', $oldOrder)
                    ->where('display_order', '<=', $newOrder)
                    ->decrement('display_order');
            } elseif ($oldOrder > $newOrder) {
                // Moving up - shift down questions in between
                GroupQuestion::where('group_id', $group->id)
                    ->where('display_order', '<', $oldOrder)
                    ->where('display_order', '>=', $newOrder)
                    ->increment('display_order');
            }

            $question->update(['display_order' => $newOrder]);

            return back()->with('success', 'Questions reordered successfully!');
        }

        // Bulk reorder format
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|exists:propoff_group_questions,id',
        ]);

        // Update display_order for each question based on array position
        foreach ($validated['order'] as $index => $questionId) {
            GroupQuestion::where('id', $questionId)
                ->where('group_id', $group->id)
                ->update(['display_order' => $index + 1]);
        }

        return back()->with('success', 'Questions reordered successfully!');
    }

    /**
     * Duplicate a question.
     */
    public function duplicate(Request $request, Group $group, GroupQuestion $groupQuestion)
    {
        // Ensure question belongs to this group
        if ($groupQuestion->group_id !== $group->id) {
            abort(404);
        }

        $newQuestion = $groupQuestion->replicate();
        $newQuestion->display_order = $group->groupQuestions()->max('display_order') + 1;
        $newQuestion->is_custom = true; // Duplicated questions become custom
        $newQuestion->event_question_id = null; // Unlink from event question
        $newQuestion->save();

        return back()->with('success', 'Question duplicated successfully!');
    }
}
