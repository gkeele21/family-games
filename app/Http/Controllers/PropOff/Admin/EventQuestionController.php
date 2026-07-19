<?php

namespace App\Http\Controllers\PropOff\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropOff\Event;
use App\Models\PropOff\EventAnswer;
use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\QuestionTemplate;
use App\Services\PropOff\EntryService;
use App\Services\PropOff\LeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventQuestionController extends Controller
{
    /**
     * Show the form for importing questions from templates.
     */
    public function importQuestions(Event $event)
    {
        // Get current questions
        $currentQuestions = $event->eventQuestions()->orderBy('display_order')->get();

        // Get next order (1-based)
        $nextOrder = ($event->eventQuestions()->max('display_order') ?? 0) + 1;

        return Inertia::render('PropOff/Admin/Events/ImportQuestions', [
            'event' => $event,
            'currentQuestions' => $currentQuestions,
            'nextOrder' => $nextOrder,
        ]);
    }

    /**
     * Search for templates by category.
     */
    public function searchTemplates(Request $request, Event $event)
    {
        $this->authorize('create', EventQuestion::class);

        $validated = $request->validate([
            'category' => 'required|string|max:100',
        ]);

        $searchCategory = trim($validated['category']);

        // Find templates where category contains the search term
        $templates = QuestionTemplate::where('category', 'LIKE', "%{$searchCategory}%")
            ->with('templateAnswers')
            ->orderBy('display_order')
            ->get();

        // Get IDs of templates already imported to this event
        $importedTemplateIds = EventQuestion::where('event_id', $event->id)
            ->whereNotNull('template_id')
            ->pluck('template_id')
            ->toArray();

        // Filter out already imported templates
        $filteredTemplates = $templates->filter(function($template) use ($importedTemplateIds) {
            return !in_array($template->id, $importedTemplateIds);
        })->values();

        return response()->json([
            'templates' => $filteredTemplates,
            'total' => $filteredTemplates->count(),
            'search_term' => $searchCategory,
        ]);
    }

    /**
     * Store a newly created question in storage.
     */
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,yes_no,numeric,text,ranked_answers',
            'options' => 'nullable|array',
            'points' => 'required|integer|min:1',
            'order' => 'required|integer|min:1',
            'template_id' => 'nullable|exists:propoff_question_templates,id',
            'event_answers' => 'nullable|array',
            'event_answers.*.correct_answer' => 'required|string',
            'event_answers.*.display_order' => 'required|integer|min:1',
        ]);

        $eventQuestion = $event->eventQuestions()->create([
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'options' => $validated['options'] ?? null,
            'points' => $validated['points'],
            'display_order' => $validated['order'],
            'template_id' => $validated['template_id'] ?? null,
        ]);

        // Create ranked answers for ranked_answers type
        if ($eventQuestion->question_type === 'ranked_answers' && isset($validated['event_answers'])) {
            foreach ($validated['event_answers'] as $answerData) {
                \App\Models\PropOff\EventAnswer::create([
                    'event_id' => $event->id,
                    'event_question_id' => $eventQuestion->id,
                    'correct_answer' => $answerData['correct_answer'],
                    'display_order' => $answerData['display_order'],
                ]);
            }
        }

        // Create group questions for all groups in this event
        foreach ($event->groups as $group) {
            \App\Models\PropOff\GroupQuestion::create([
                'group_id' => $group->id,
                'event_question_id' => $eventQuestion->id,
                'question_text' => $eventQuestion->question_text,
                'question_type' => $eventQuestion->question_type,
                'options' => $eventQuestion->options,
                'points' => $eventQuestion->points,
                'display_order' => $eventQuestion->display_order,
                'is_active' => true,
                'is_custom' => false,
            ]);
        }

        return redirect()->route('propoff.admin.events.show', $event)
            ->with('success', 'Question added successfully!');
    }

    /**
     * Create question from template with variable substitution.
     */
    public function createFromTemplate(Request $request, Event $event, QuestionTemplate $template)
    {
        $validated = $request->validate([
            'variable_values' => 'nullable|array',
            'order' => 'required|integer|min:1',
            'points' => 'nullable|integer|min:1',
        ]);

        $variables = $validated['variable_values'] ?? [];

        // Substitute variables in question text
        $questionText = $this->substituteVariables(
            $template->question_text,
            $variables
        );

        // Prepare options if multiple choice
        $options = null;
        if ($template->question_type === 'multiple_choice' && $template->default_options) {
            $options = array_map(function ($option) use ($variables) {
                // Handle both string and array formats
                if (is_string($option)) {
                    return [
                        'label' => $this->substituteVariables($option, $variables),
                        'points' => 0,
                    ];
                }
                return [
                    'label' => $this->substituteVariables($option['label'] ?? '', $variables),
                    'points' => $option['points'] ?? 0,
                ];
            }, $template->default_options);
        }

        // Create the question
        $eventQuestion = $event->eventQuestions()->create([
            'template_id' => $template->id,
            'question_text' => $questionText,
            'question_type' => $template->question_type,
            'options' => $options,
            'points' => $validated['points'] ?? $template->default_points,
            'display_order' => $validated['order'],
        ]);

        // Create group questions for all groups in this event
        foreach ($event->groups as $group) {
            \App\Models\PropOff\GroupQuestion::create([
                'group_id' => $group->id,
                'event_question_id' => $eventQuestion->id,
                'question_text' => $eventQuestion->question_text,
                'question_type' => $eventQuestion->question_type,
                'options' => $eventQuestion->options,
                'points' => $eventQuestion->points,
                'display_order' => $eventQuestion->display_order,
                'is_active' => true,
                'is_custom' => false,
            ]);
        }

        return redirect()->route('propoff.admin.events.show', $event)
            ->with('success', 'Question created from template successfully!');
    }

    /**
     * Update the specified question in storage.
     */
    public function update(Request $request, Event $event, EventQuestion $eventQuestion)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,yes_no,numeric,text,ranked_answers',
            'options' => 'nullable|array',
            'points' => 'required|integer|min:1',
            'display_order' => 'required|integer|min:1',
            'answers' => 'nullable|array',
            'answers.*.correct_answer' => 'required|string',
            'answers.*.display_order' => 'required|integer|min:1',
        ]);

        $eventQuestion->update([
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'options' => $validated['options'] ?? null,
            'points' => $validated['points'],
            'display_order' => $validated['display_order'],
        ]);

        // Update all associated group questions
        $eventQuestion->groupQuestions()->where('is_custom', false)->update([
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'options' => $validated['options'] ?? null,
            'points' => $validated['points'],
            'display_order' => $validated['display_order'],
        ]);

        // Update answers for ranked_answers type
        if ($eventQuestion->question_type === 'ranked_answers' && isset($validated['answers'])) {
            // Delete existing answers
            $eventQuestion->eventAnswers()->delete();

            // Create new answers
            foreach ($validated['answers'] as $answerData) {
                \App\Models\PropOff\EventAnswer::create([
                    'event_id' => $event->id,
                    'event_question_id' => $eventQuestion->id,
                    'correct_answer' => $answerData['correct_answer'],
                    'display_order' => $answerData['display_order'],
                ]);
            }
        }

        return redirect()->route('propoff.admin.events.show', $event)
            ->with('success', 'Question updated successfully!');
    }

    /**
     * Remove the specified question from storage.
     */
    public function destroy(Event $event, EventQuestion $eventQuestion)
    {
        $eventQuestion->delete();

        // Reorder remaining questions
        $this->reorderQuestionsAfterDelete($event, $eventQuestion->display_order);

        return redirect()->route('propoff.admin.events.show', $event)
            ->with('success', 'Question deleted successfully!');
    }

    /**
     * Remove all questions from an event.
     */
    public function destroyAll(Event $event)
    {
        $count = $event->eventQuestions()->count();

        // Delete all questions (cascades to group questions via foreign key)
        $event->eventQuestions()->delete();

        return redirect()->route('propoff.admin.events.import-questions', $event)
            ->with('success', "{$count} questions deleted successfully!");
    }

    /**
     * Reorder questions (drag and drop).
     * Supports two formats:
     * 1. Simple format: {question_id: X, new_order: Y}
     * 2. Bulk format: {event_questions: [{id: X, order: Y}, ...]}
     */
    public function reorder(Request $request, Event $event)
    {
        // Check if using simple format (single question reorder)
        if ($request->has('question_id') && $request->has('new_order')) {
            $validated = $request->validate([
                'question_id' => 'required|exists:propoff_event_questions,id',
                'new_order' => 'required|integer|min:1',
            ]);

            $question = EventQuestion::findOrFail($validated['question_id']);
            $oldOrder = $question->display_order;
            $newOrder = $validated['new_order'];

            // Move question to new position
            if ($oldOrder < $newOrder) {
                // Moving down - shift up questions in between
                EventQuestion::where('event_id', $event->id)
                    ->where('display_order', '>', $oldOrder)
                    ->where('display_order', '<=', $newOrder)
                    ->decrement('display_order');
            } else if ($oldOrder > $newOrder) {
                // Moving up - shift down questions in between
                EventQuestion::where('event_id', $event->id)
                    ->where('display_order', '>=', $newOrder)
                    ->where('display_order', '<', $oldOrder)
                    ->increment('display_order');
            }

            // Update the moved question
            $question->update(['display_order' => $newOrder]);

            // Update corresponding group questions
            \App\Models\PropOff\GroupQuestion::where('event_question_id', $question->id)
                ->where('is_custom', false)
                ->update(['display_order' => $newOrder]);

            return back()->with('success', 'Questions reordered successfully!');
        }

        // Bulk format (existing functionality)
        $validated = $request->validate([
            'event_questions' => 'required|array',
            'event_questions.*.id' => 'required|exists:propoff_event_questions,id',
            'event_questions.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['event_questions'] as $questionData) {
            EventQuestion::where('id', $questionData['id'])
                ->update(['display_order' => $questionData['order']]);

            // Update corresponding group questions
            \App\Models\PropOff\GroupQuestion::where('event_question_id', $questionData['id'])
                ->where('is_custom', false)
                ->update(['display_order' => $questionData['order']]);
        }

        return back()->with('success', 'Questions reordered successfully!');
    }

    /**
     * Duplicate a question.
     */
    public function duplicate(Event $event, EventQuestion $eventQuestion)
    {
        $newEventQuestion = $eventQuestion->replicate();
        $newEventQuestion->display_order = $event->eventQuestions()->max('display_order') + 1;
        $newEventQuestion->save();

        // Create group questions for all groups
        foreach ($event->groups as $group) {
            \App\Models\PropOff\GroupQuestion::create([
                'group_id' => $group->id,
                'event_question_id' => $newEventQuestion->id,
                'question_text' => $newEventQuestion->question_text,
                'question_type' => $newEventQuestion->question_type,
                'options' => $newEventQuestion->options,
                'points' => $newEventQuestion->points,
                'display_order' => $newEventQuestion->display_order,
                'is_active' => true,
                'is_custom' => false,
            ]);
        }

        return back()->with('success', 'Question duplicated successfully!');
    }

    /**
     * Bulk import questions from another event.
     */
    public function bulkImport(Request $request, Event $targetEvent)
    {
        $validated = $request->validate([
            'source_event_id' => 'required|exists:propoff_events,id',
            'event_question_ids' => 'required|array',
            'event_question_ids.*' => 'exists:propoff_event_questions,id',
        ]);

        $sourceQuestions = EventQuestion::whereIn('id', $validated['event_question_ids'])
            ->orderBy('display_order')
            ->get();

        $nextOrder = $targetEvent->eventQuestions()->max('display_order') + 1;

        foreach ($sourceQuestions as $question) {
            $newEventQuestion = $question->replicate();
            $newEventQuestion->event_id = $targetEvent->id;
            $newEventQuestion->display_order = $nextOrder++;
            $newEventQuestion->save();

            // Create group questions for all groups in target event
            foreach ($targetEvent->groups as $group) {
                \App\Models\PropOff\GroupQuestion::create([
                    'group_id' => $group->id,
                    'event_question_id' => $newEventQuestion->id,
                    'question_text' => $newEventQuestion->question_text,
                    'question_type' => $newEventQuestion->question_type,
                    'options' => $newEventQuestion->options,
                    'points' => $newEventQuestion->points,
                    'display_order' => $newEventQuestion->display_order,
                    'is_active' => true,
                    'is_custom' => false,
                ]);
            }
        }

        return redirect()->route('propoff.admin.events.show', $targetEvent)
            ->with('success', count($sourceQuestions) . ' questions imported successfully!');
    }

    /**
     * Create multiple questions from templates at once with consolidated variables.
     */
    public function bulkCreateFromTemplates(Request $request, Event $event)
    {
        $validated = $request->validate([
            'templates' => 'required|array',
            'templates.*.template_id' => 'required|exists:propoff_question_templates,id',
            'templates.*.variable_values' => 'nullable|array',
        ]);

        $nextOrder = $event->eventQuestions()->max('display_order') + 1;

        foreach ($validated['templates'] as $templateData) {
            $template = QuestionTemplate::with('templateAnswers')->findOrFail($templateData['template_id']);
            $variableValues = $templateData['variable_values'] ?? [];

            // Substitute variables in question text
            $questionText = $this->substituteVariables(
                $template->question_text,
                $variableValues
            );

            // Substitute variables in options (skip for ranked_answers type)
            $options = null;
            if ($template->question_type !== 'ranked_answers' && $template->default_options) {
                $options = collect($template->default_options)->map(function ($option) use ($variableValues) {
                    if (is_array($option) && isset($option['label'])) {
                        $option['label'] = $this->substituteVariables($option['label'], $variableValues);
                        return $option;
                    } else {
                        return $this->substituteVariables($option, $variableValues);
                    }
                })->toArray();
            }

            // Create event question
            $eventQuestion = EventQuestion::create([
                'event_id' => $event->id,
                'template_id' => $template->id,
                'question_text' => $questionText,
                'question_type' => $template->question_type,
                'options' => $options,
                'points' => $template->default_points,
                'display_order' => $nextOrder++,
            ]);

            // Import template answers if they exist
            if ($template->templateAnswers && $template->templateAnswers->count() > 0) {
                foreach ($template->templateAnswers as $templateAnswer) {
                    \App\Models\PropOff\EventAnswer::create([
                        'event_id' => $event->id,
                        'event_question_id' => $eventQuestion->id,
                        'correct_answer' => $templateAnswer->answer_text,
                        'display_order' => $templateAnswer->display_order,
                    ]);
                }
            }

            // Create group questions for all groups in this event
            foreach ($event->groups as $group) {
                \App\Models\PropOff\GroupQuestion::create([
                    'group_id' => $group->id,
                    'event_question_id' => $eventQuestion->id,
                    'question_text' => $questionText,
                    'question_type' => $template->question_type,
                    'options' => $options,
                    'points' => $template->default_points,
                    'display_order' => $eventQuestion->display_order,
                    'is_active' => true,
                    'is_custom' => false,
                ]);
            }
        }

        return redirect()->route('propoff.admin.events.show', $event)
            ->with('success', 'Questions imported successfully.');
    }

    /**
     * Set the correct answer for a question and recalculate scores.
     */
    public function setAnswer(Request $request, Event $event, EventQuestion $eventQuestion)
    {
        $validated = $request->validate([
            'answer' => 'required|string',
        ]);

        // Ensure question belongs to this event
        if ($eventQuestion->event_id !== $event->id) {
            abort(404);
        }

        // Check if event is locked (lock_date has passed)
        if (!$event->is_locked) {
            return back()->with('error', 'Cannot set answers until the event lock date has passed.');
        }

        // Create or update the event answer
        EventAnswer::updateOrCreate(
            [
                'event_id' => $event->id,
                'event_question_id' => $eventQuestion->id,
            ],
            [
                'correct_answer' => $validated['answer'],
                'is_void' => false,
                'set_at' => now(),
                'set_by' => $request->user()->id,
            ]
        );

        // Recalculate scores for all admin-graded groups
        $entryService = app(EntryService::class);
        $leaderboardService = app(LeaderboardService::class);

        $adminGroups = $event->groups()->where('grading_source', 'admin')->get();

        foreach ($adminGroups as $group) {
            $entries = $group->entries()->get();
            foreach ($entries as $entry) {
                $entryService->calculateScore($entry);
            }

            // Update leaderboard for this group
            $leaderboardService->updateLeaderboard($event, $group);
        }

        return back()->with('success', 'Answer saved and scores calculated');
    }

    /**
     * Replace {variable} with actual values.
     */
    private function substituteVariables(string $text, array $variables): string
    {
        foreach ($variables as $name => $value) {
            $text = str_replace("{{$name}}", $value, $text);
        }
        return $text;
    }

    /**
     * Reorder questions after deletion.
     */
    protected function reorderQuestionsAfterDelete(Event $event, int $deletedOrder)
    {
        $event->eventQuestions()
            ->where('display_order', '>', $deletedOrder)
            ->decrement('display_order');
    }
}
