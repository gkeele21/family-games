<?php

namespace App\Http\Controllers\PropOff\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropOff\QuestionTemplate;
use App\Models\PropOff\QuestionTemplateAnswer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuestionTemplateController extends Controller
{
    /**
     * Display a listing of question templates.
     */
    public function index(Request $request)
    {
        $query = QuestionTemplate::query();

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->where('question_type', $request->type);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('question_text', 'like', "%{$request->search}%");
            });
        }

        $templates = $query->orderBy('display_order')->paginate(50);

        // Get all unique categories
        $categories = QuestionTemplate::distinct('category')
            ->pluck('category')
            ->filter()
            ->values();

        return Inertia::render('PropOff/Admin/QuestionTemplates/Index', [
            'templates' => $templates,
            'categories' => $categories,
            'filters' => $request->only(['category', 'type', 'search']),
        ]);
    }

    /**
     * Show the form for creating a new template.
     */
    public function create()
    {
        return Inertia::render('PropOff/Admin/QuestionTemplates/TemplateForm');
    }

    /**
     * Store a newly created template in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,yes_no,numeric,text,ranked_answers',
            'default_options' => 'nullable|array',
            'variables' => 'nullable|array',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'default_points' => 'nullable|integer|min:1',
            'template_answers' => 'nullable|array|max:7',
            'template_answers.*.answer_text' => 'required|string|max:255',
            'template_answers.*.display_order' => 'required|integer|min:1|max:7',
        ]);

        // Get the highest display_order and add 1
        $maxDisplayOrder = QuestionTemplate::max('display_order') ?? 0;

        $template = QuestionTemplate::create([
            'title' => $validated['title'],
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'default_options' => $validated['default_options'] ?? null,
            'variables' => $validated['variables'] ?? null,
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'default_points' => $validated['default_points'] ?? null,
            'display_order' => $maxDisplayOrder + 1,
            'created_by' => auth()->id(),
        ]);

        // Save template answers if provided
        if (isset($validated['template_answers']) && is_array($validated['template_answers'])) {
            foreach ($validated['template_answers'] as $answerData) {
                QuestionTemplateAnswer::create([
                    'question_template_id' => $template->id,
                    'answer_text' => $answerData['answer_text'],
                    'display_order' => $answerData['display_order'],
                ]);
            }
        }

        return redirect()->route('propoff.admin.question-templates.index')
            ->with('success', 'Question template created successfully!');
    }

    /**
     * Display the specified template.
     */
    public function show(QuestionTemplate $question_template)
    {
        return Inertia::render('PropOff/Admin/QuestionTemplates/Show', [
            'template' => $question_template,
        ]);
    }

    /**
     * Show the form for editing the specified template.
     */
    public function edit(QuestionTemplate $question_template)
    {
        // Load template answers
        $question_template->load('templateAnswers');

        return Inertia::render('PropOff/Admin/QuestionTemplates/TemplateForm', [
            'template' => $question_template,
        ]);
    }

    /**
     * Update the specified template in storage.
     */
    public function update(Request $request, QuestionTemplate $question_template)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,yes_no,numeric,text,ranked_answers',
            'default_options' => 'nullable|array',
            'variables' => 'nullable|array',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'default_points' => 'nullable|integer|min:1',
            'template_answers' => 'nullable|array|max:7',
            'template_answers.*.answer_text' => 'required|string|max:255',
            'template_answers.*.display_order' => 'required|integer|min:1|max:7',
        ]);

        $question_template->update([
            'title' => $validated['title'],
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'default_options' => $validated['default_options'] ?? null,
            'variables' => $validated['variables'] ?? null,
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'default_points' => $validated['default_points'] ?? null,
        ]);

        // Update template answers
        // Delete existing answers
        $question_template->templateAnswers()->delete();

        // Create new answers if provided
        if (isset($validated['template_answers']) && is_array($validated['template_answers'])) {
            foreach ($validated['template_answers'] as $answerData) {
                QuestionTemplateAnswer::create([
                    'question_template_id' => $question_template->id,
                    'answer_text' => $answerData['answer_text'],
                    'display_order' => $answerData['display_order'],
                ]);
            }
        }

        return redirect()->route('propoff.admin.question-templates.index')
            ->with('success', 'Question template updated successfully!');
    }

    /**
     * Remove the specified template from storage.
     */
    public function destroy(QuestionTemplate $question_template)
    {
        $templateTitle = $question_template->title;
        $question_template->delete();

        return redirect()->route('propoff.admin.question-templates.index')
            ->with('success', "Template '{$templateTitle}' deleted successfully!");
    }

    /**
     * Duplicate a template.
     */
    public function duplicate(QuestionTemplate $template)
    {
        $newTemplate = $template->replicate();
        $newTemplate->title = $template->title . ' (Copy)';
        $newTemplate->save();

        return redirect()->route('propoff.admin.question-templates.edit', $newTemplate)
            ->with('success', 'Template duplicated successfully!');
    }

    /**
     * Reorder templates.
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:propoff_question_templates,id',
            'new_order' => 'required|integer|min:1',
        ]);

        $template = QuestionTemplate::findOrFail($validated['template_id']);
        $oldOrder = $template->display_order;
        $newOrder = $validated['new_order'];

        if ($oldOrder < $newOrder) {
            // Moving down - shift up templates in between
            QuestionTemplate::where('display_order', '>', $oldOrder)
                ->where('display_order', '<=', $newOrder)
                ->decrement('display_order');
        } elseif ($oldOrder > $newOrder) {
            // Moving up - shift down templates in between
            QuestionTemplate::where('display_order', '>=', $newOrder)
                ->where('display_order', '<', $oldOrder)
                ->increment('display_order');
        }

        $template->update(['display_order' => $newOrder]);

        return back()->with('success', 'Templates reordered successfully!');
    }

    /**
     * Preview template with variable substitution.
     */
    public function preview(Request $request, QuestionTemplate $template)
    {
        $variables = $request->input('variables', []);

        $questionText = $template->question_text;
        $options = $template->default_options ?? [];

        // Substitute variables
        foreach ($variables as $key => $value) {
            $questionText = str_replace("{{$key}}", $value, $questionText);

            // Substitute in options if they exist
            if (is_array($options)) {
                foreach ($options as &$option) {
                    $option = str_replace("{{$key}}", $value, $option);
                }
            }
        }

        return response()->json([
            'question_text' => $questionText,
            'options' => $options,
        ]);
    }
}
