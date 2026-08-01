<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GameType;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    /**
     * Question Library — browse and manage the question bank for one game type
     * at a time. Filtering (search / category / difficulty / active) happens
     * client-side, so we hand over the full bank for the selected game.
     */
    public function index(Request $request): Response
    {
        $gameTypes = GameType::online()->withCount('questions')->orderBy('id')->get()
            ->map(fn (GameType $gt) => [
                'id' => $gt->id,
                'name' => $gt->name,
                'slug' => $gt->slug,
                'questions_count' => $gt->questions_count,
            ]);

        $slug = $request->query('game', $gameTypes->first()['slug'] ?? 'america-says');
        $active = GameType::where('slug', $slug)->first()
            ?? GameType::online()->firstOrFail();

        $categories = Category::where('game_type_id', $active->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $questions = Question::where('game_type_id', $active->id)
            ->with(['answers:id,question_id,answer_text,points,display_order', 'category:id,name'])
            ->orderBy('question_text')
            ->get()
            ->map(fn (Question $q) => $this->present($q));

        return Inertia::render('Questions/Index', [
            'gameTypes' => $gameTypes,
            'activeSlug' => $active->slug,
            'categories' => $categories,
            'questions' => $questions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuestion($request);
        $gameType = GameType::findOrFail($validated['game_type_id']);

        $question = DB::transaction(function () use ($validated, $gameType) {
            $question = Question::create([
                'game_type_id' => $gameType->id,
                'category_id' => $validated['category_id'] ?? null,
                'question_text' => $validated['question_text'],
                'difficulty' => $validated['difficulty'] ?? null,
                'round_type' => $validated['round_type'] ?? 'regular',
                'answer_letter' => ($validated['answer_letter'] ?? '') ?: null,
                'is_active' => $validated['is_active'] ?? true,
                'is_official' => $validated['is_official'] ?? false,
                'created_by' => auth()->id(),
            ]);
            $this->syncAnswers($question, $validated['answers'] ?? []);

            return $question;
        });

        return back()->with('success', "Added \"{$question->question_text}\".");
    }

    public function update(Request $request, Question $question)
    {
        $validated = $this->validateQuestion($request, $question);

        DB::transaction(function () use ($validated, $question) {
            $question->update([
                'category_id' => $validated['category_id'] ?? null,
                'question_text' => $validated['question_text'],
                'difficulty' => $validated['difficulty'] ?? null,
                'round_type' => $validated['round_type'] ?? 'regular',
                'answer_letter' => ($validated['answer_letter'] ?? '') ?: null,
                'is_active' => $validated['is_active'] ?? true,
                'is_official' => $validated['is_official'] ?? false,
            ]);
            $this->syncAnswers($question, $validated['answers'] ?? []);
        });

        return back()->with('success', 'Question saved.');
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return back()->with('success', 'Question deleted.');
    }

    /**
     * Inline-create a category for a game type (used from the question editor).
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'game_type_id' => 'required|exists:game_types,id',
            'name' => 'required|string|max:255',
        ]);

        Category::firstOrCreate([
            'game_type_id' => $validated['game_type_id'],
            'name' => $validated['name'],
        ]);

        return back();
    }

    // ---- helpers ----

    protected function present(Question $q): array
    {
        return [
            'id' => $q->id,
            'question_text' => $q->question_text,
            'difficulty' => $q->difficulty,
            'round_type' => $q->round_type,
            'answer_letter' => $q->answer_letter,
            'is_active' => $q->is_active,
            'is_official' => $q->is_official,
            'times_used' => $q->times_used,
            'category_id' => $q->category_id,
            'category' => $q->category ? ['id' => $q->category->id, 'name' => $q->category->name] : null,
            'answers' => $q->answers->map(fn ($a) => [
                'id' => $a->id,
                'answer_text' => $a->answer_text,
                'points' => $a->points,
                'display_order' => $a->display_order,
            ])->values(),
        ];
    }

    protected function validateQuestion(Request $request, ?Question $question = null): array
    {
        return $request->validate([
            'game_type_id' => [$question ? 'sometimes' : 'required', 'exists:game_types,id'],
            'category_id' => 'nullable|exists:categories,id',
            'question_text' => 'required|string|max:500',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'round_type' => 'nullable|in:regular,final',
            'answer_letter' => 'nullable|string|max:1',
            'is_active' => 'boolean',
            'is_official' => 'boolean',
            'answers' => 'array',
            'answers.*.id' => 'nullable|integer',
            'answers.*.answer_text' => 'required|string|max:255',
            'answers.*.points' => 'nullable|integer|min:0',
        ]);
    }

    /**
     * Reconcile a question's answers to the submitted list: update existing rows
     * (preserving reveal stats), create new ones, and drop any that were removed.
     * Order follows submission order.
     */
    protected function syncAnswers(Question $question, array $answers): void
    {
        $keepIds = [];

        foreach (array_values($answers) as $i => $a) {
            $text = trim($a['answer_text'] ?? '');
            if ($text === '') {
                continue;
            }
            $attributes = [
                'answer_text' => $text,
                'display_order' => $i + 1,
            ];
            // Only touch points when the editor sends them (Family Feud); leave
            // America Says answers' stored points untouched.
            if (array_key_exists('points', $a) && $a['points'] !== null) {
                $attributes['points'] = (int) $a['points'];
            }

            if (!empty($a['id']) && ($existing = $question->answers()->find($a['id']))) {
                $existing->update($attributes);
                $keepIds[] = $existing->id;
            } else {
                $created = $question->answers()->create($attributes + ['points' => $attributes['points'] ?? 0]);
                $keepIds[] = $created->id;
            }
        }

        $question->answers()->whereNotIn('id', $keepIds)->delete();
    }
}
