<?php

namespace App\Http\Controllers\Scorekeeper;

use App\Http\Controllers\Controller;
use App\Models\GameType;
use App\Models\Scorekeeper\GameTemplate;
use App\Models\Scorekeeper\Household;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GameTemplateController extends Controller
{
    /**
     * System templates + this household's custom templates.
     */
    public function index(Request $request, Household $household): Response
    {
        $this->ensureMember($request, $household);

        $templates = GameTemplate::query()
            ->with('gameType:id,name')
            ->where(function ($q) use ($household) {
                $q->where('is_system', true)
                    ->orWhere('is_global', true)
                    ->orWhere('household_id', $household->id);
            })
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return Inertia::render('Scorekeeper/Templates/Index', [
            'household' => $household->only(['id', 'name']),
            'templates' => $templates,
            'games'     => GameType::scorekeeper()
                ->visibleTo($household->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Household $household)
    {
        $this->ensureMember($request, $household);

        $data = $this->validatedTemplateData($request, $household);
        $gameTypeId = $this->resolveGameTypeId($request, $household);

        $template = GameTemplate::create([
            ...$data,
            'game_type_id'       => $gameTypeId,
            'household_id'       => $household->id,
            'is_system'          => false,
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->syncGlobalPromotion($template);

        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, GameTemplate $template)
    {
        $this->ensureCanMutate($request, $template);

        $data = $this->validatedTemplateData($request, $template->household, $template);
        $gameTypeId = $this->resolveGameTypeId($request, $template->household);

        $template->update([...$data, 'game_type_id' => $gameTypeId]);

        $this->syncGlobalPromotion($template->fresh());

        return back()->with('success', 'Template updated.');
    }

    public function destroy(Request $request, GameTemplate $template)
    {
        $this->ensureCanMutate($request, $template);

        $householdId = $template->household_id;
        $template->delete();

        return redirect()
            ->route('scorekeeper.households.templates.index', $householdId)
            ->with('success', 'Template deleted.');
    }

    private function validatedTemplateData(
        Request $request,
        Household $household,
        ?GameTemplate $ignore = null,
    ): array {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // Unique within the household so pickers stay unambiguous.
                Rule::unique('game_templates', 'name')
                    ->where(fn ($q) => $q->where('household_id', $household->id))
                    ->ignore($ignore?->id),
            ],
            'target_score'                      => 'nullable|integer|min:0',
            'low_score_wins'                    => 'boolean',
            'max_rounds'                        => 'nullable|integer|min:1',
            'team_based'                        => 'boolean',
            'allow_self_scoring'                => 'boolean',
            'is_global'                         => 'boolean',
            'score_fields'                      => 'required|array|min:1',
            'score_fields.*.label'              => 'required|string|max:255',
            'score_fields.*.counts_toward_total' => 'boolean',
            'score_fields.*.color'              => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $anyCounts = collect($data['score_fields'])
            ->contains(fn ($f) => $f['counts_toward_total'] ?? false);

        if (! $anyCounts) {
            throw ValidationException::withMessages([
                'score_fields' => 'At least one score field must count toward the total.',
            ]);
        }

        $data['score_fields'] = $this->withGeneratedKeys($data['score_fields']);

        return $data;
    }

    /**
     * Turn each field's label into a stable, unique key within the template.
     */
    private function withGeneratedKeys(array $fields): array
    {
        $used = [];

        return array_map(function ($field) use (&$used) {
            $base = Str::slug($field['label'], '_') ?: 'field';
            $key = $base;
            $i = 2;
            while (in_array($key, $used, true)) {
                $key = $base.$i;
                $i++;
            }
            $used[] = $key;

            return [
                'key'                 => $key,
                'label'               => $field['label'],
                'counts_toward_total' => (bool) ($field['counts_toward_total'] ?? false),
                'color'               => $field['color'] ?? null,
            ];
        }, $fields);
    }

    /**
     * Copy a template into another household the user belongs to (or the same
     * household, as duplicate-to-edit).
     */
    public function copy(Request $request, GameTemplate $template)
    {
        abort_if($template->is_system, 422, 'System templates are already available everywhere.');

        // Source must be visible to the user: globally shared, or in one of
        // their households.
        if (! $template->is_global) {
            $this->ensureMember($request, $template->household);
        }

        $validated = $request->validate([
            'target_household_id' => 'required|integer',
        ]);
        $target = $request->user()->households()
            ->whereKey($validated['target_household_id'])
            ->first();
        abort_unless($target, 403, 'You are not a member of that household.');

        // Resolve the game in the target scope: system/global games are
        // shared; a household-owned game is recreated in the target.
        $gameTypeId = null;
        if ($source = $template->gameType) {
            if ($source->household_id === null || $source->is_global) {
                $gameTypeId = $source->id;
            } else {
                $gameTypeId = GameType::firstOrCreate(
                    [
                        'kind'         => 'scorekeeper',
                        'household_id' => $target->id,
                        'name'         => $source->name,
                    ],
                    ['slug' => Str::slug($source->name), 'is_global' => false],
                )->id;
            }
        }

        // Unique name in the target (same suffixing as the dedupe migration).
        $name = $template->name;
        $suffix = 2;
        while (
            GameTemplate::where('household_id', $target->id)->where('name', $name)->exists()
        ) {
            $name = "{$template->name} ({$suffix})";
            $suffix++;
        }

        GameTemplate::create([
            'household_id'       => $target->id,
            'name'               => $name,
            'game_type_id'       => $gameTypeId,
            'target_score'       => $template->target_score,
            'low_score_wins'     => $template->low_score_wins,
            'max_rounds'         => $template->max_rounds,
            'score_fields'       => $template->score_fields,
            'team_based'         => $template->team_based,
            'allow_self_scoring' => $template->allow_self_scoring,
            'is_global'          => false,
            'is_system'          => false,
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', "Copied \"{$template->name}\" to {$target->name} as \"{$name}\".");
    }

    /**
     * Resolve the template's game: an existing visible scorekeeper game, or a
     * newly created household game (pick-or-add). A template is always for a
     * specific game, so one of the two is required.
     */
    private function resolveGameTypeId(Request $request, Household $household): int
    {
        $validated = $request->validate([
            'game_type_id'  => 'nullable|integer',
            'new_game_name' => 'nullable|string|max:255',
        ]);

        if (empty($validated['game_type_id']) && empty($validated['new_game_name'])) {
            throw ValidationException::withMessages([
                'game_type_id' => 'Pick the game this template is for (or add a new one).',
            ]);
        }

        if (! empty($validated['new_game_name'])) {
            $name = trim($validated['new_game_name']);

            return GameType::create([
                'name'         => $name,
                'slug'         => Str::slug($name),
                'kind'         => 'scorekeeper',
                'household_id' => $household->id,
                'is_global'    => false,
            ])->id;
        }

        $game = GameType::scorekeeper()
            ->visibleTo($household->id)
            ->find($validated['game_type_id']);
        abort_unless($game, 422, 'Invalid game.');

        return $game->id;
    }

    /**
     * A globally shared template must point at a globally visible game, so
     * promote its (household-owned) game to global as well.
     */
    private function syncGlobalPromotion(GameTemplate $template): void
    {
        if (! $template->is_global || ! $template->game_type_id) {
            return;
        }
        $game = $template->gameType;
        if ($game && $game->household_id !== null && ! $game->is_global) {
            $game->update(['is_global' => true]);
        }
    }

    private function ensureMember(Request $request, Household $household): void
    {
        $isMember = $household->members()
            ->where('users.id', $request->user()->id)
            ->exists();

        abort_unless($isMember, 403);
    }

    /**
     * Only members of the owning household may mutate. System templates are
     * read-only.
     */
    private function ensureCanMutate(Request $request, GameTemplate $template): void
    {
        abort_if($template->is_system, 403, 'System templates cannot be modified.');
        abort_unless($template->household, 403);
        $this->ensureMember($request, $template->household);
    }
}
