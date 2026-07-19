<?php

namespace App\Http\Controllers\PropOff;

use App\Http\Controllers\Controller;

use App\Models\PropOff\Event;
use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\QuestionTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventQuestionController extends Controller
{
    /**
     * Display a listing of questions for an event.
     */
    public function index(Event $event)
    {
        $this->authorize('view', $event);

        $eventQuestions = $event->eventQuestions()
            ->with('template')
            ->orderBy('display_order')
            ->get();

        return Inertia::render('PropOff/EventQuestions/Index', [
            'event' => $event,
            'eventQuestions' => $eventQuestions,
        ]);
    }

    /**
     * Show the form for creating a new question.
     */
    public function create(Event $event)
    {
        $this->authorize('update', $event);

        $templates = QuestionTemplate::where('created_by', auth()->id())
            ->orWhere('is_favorite', true)
            ->get();

        return Inertia::render('PropOff/EventQuestions/Create', [
            'event' => $event,
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created question in storage.
     */
    public function store(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'template_id' => 'nullable|exists:propoff_question_templates,id',
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,yes_no,numeric,text',
            'options' => 'nullable|array',
            'options.*' => 'string',
            'points' => 'required|integer|min:1',
        ]);

        // Get the next order
        $maxOrder = $event->eventQuestions()->max('display_order') ?? 0;

        $eventQuestion = $event->eventQuestions()->create([
            'template_id' => $validated['template_id'] ?? null,
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'options' => $validated['options'] ?? null,
            'points' => $validated['points'],
            'display_order' => $maxOrder + 1,
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

        return redirect()->route('events.event-questions.index', $event)
            ->with('success', 'Question added successfully!');
    }

    /**
     * Create a question from a template.
     */
    public function createFromTemplate(Event $event, QuestionTemplate $template)
    {
        $this->authorize('update', $event);

        $maxOrder = $event->eventQuestions()->max('display_order') ?? 0;

        $eventQuestion = $event->eventQuestions()->create([
            'template_id' => $template->id,
            'question_text' => $template->question_text,
            'question_type' => $template->question_type,
            'options' => $template->default_options,
            'points' => $template->default_points,
            'display_order' => $maxOrder + 1,
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

        return redirect()->route('events.event-questions.index', $event)
            ->with('success', 'Question created from template!');
    }

    /**
     * Display the specified question.
     */
    public function show(Event $event, EventQuestion $eventQuestion)
    {
        $this->authorize('view', $event);

        $eventQuestion->load('template', 'groupQuestions');

        return Inertia::render('PropOff/EventQuestions/Show', [
            'event' => $event,
            'eventQuestion' => $eventQuestion,
        ]);
    }

    /**
     * Show the form for editing the specified question.
     */
    public function edit(Event $event, EventQuestion $eventQuestion)
    {
        $this->authorize('update', $event);

        return Inertia::render('PropOff/EventQuestions/Edit', [
            'event' => $event,
            'eventQuestion' => $eventQuestion,
        ]);
    }

    /**
     * Update the specified question in storage.
     */
    public function update(Request $request, Event $event, EventQuestion $eventQuestion)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,yes_no,numeric,text',
            'options' => 'nullable|array',
            'options.*' => 'string',
            'points' => 'required|integer|min:1',
        ]);

        $eventQuestion->update($validated);

        // Update all associated group questions
        $eventQuestion->groupQuestions()->update([
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'options' => $validated['options'] ?? null,
            'points' => $validated['points'],
        ]);

        return redirect()->route('events.event-questions.index', $event)
            ->with('success', 'Question updated successfully!');
    }

    /**
     * Remove the specified question from storage.
     */
    public function destroy(Event $event, EventQuestion $eventQuestion)
    {
        $this->authorize('update', $event);

        $questionOrder = $eventQuestion->display_order;
        $eventQuestion->delete();

        // Reorder remaining questions
        $event->eventQuestions()
            ->where('display_order', '>', $questionOrder)
            ->decrement('display_order');

        return redirect()->route('events.event-questions.index', $event)
            ->with('success', 'Question deleted successfully!');
    }

    /**
     * Reorder questions.
     */
    public function reorder(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'event_questions' => 'required|array',
            'event_questions.*.id' => 'required|exists:propoff_event_questions,id',
            'event_questions.*.order' => 'required|integer|min:1',
        ]);

        foreach ($validated['event_questions'] as $questionData) {
            EventQuestion::where('id', $questionData['id'])
                ->where('event_id', $event->id)
                ->update(['display_order' => $questionData['order']]);
        }

        return back()->with('success', 'Questions reordered successfully!');
    }
}
