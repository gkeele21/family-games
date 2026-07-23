<?php

namespace App\Http\Controllers\Scorekeeper;

use App\Http\Controllers\Controller;
use App\Models\GameType;
use App\Models\Scorekeeper\GameTemplate;
use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\Round;
use App\Models\Scorekeeper\ScoredGame;
use App\Models\User;
use App\Services\Scorekeeper\ScoreGameService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScoredGameController extends Controller
{
    public function __construct(private ScoreGameService $service)
    {
    }

    public function index(Request $request, Household $household): Response
    {
        $this->ensureMember($request, $household);

        return Inertia::render('Scorekeeper/ScoredGames/Index', [
            'household' => $household->only(['id', 'name']),
            'games'     => $household->scoredGames()
                ->with(['competitors.players:players.id,players.name', 'rounds.scores'])
                ->orderByDesc('started_at')
                ->get()
                ->map(function (ScoredGame $g) {
                    $players = $g->competitors->flatMap->players->unique('id')->values();
                    // Same short names as the scorecard: first name only,
                    // extended just enough to tell same-named people apart.
                    $display = $this->displayNames($players);

                    // Rank-1 finishers of completed games (ties → several).
                    // Individuals get their short name; teams keep the team name.
                    $winners = [];
                    if ($g->is_complete) {
                        $byId = $g->competitors->keyBy('id');
                        foreach ($this->service->standings($g) as $row) {
                            if ($row['rank'] !== 1) {
                                break;
                            }
                            $playerId = $g->team_based
                                ? null
                                : $byId[$row['competitor_id']]?->players->first()?->id;
                            $winners[] = $display[$playerId] ?? $row['name'];
                        }
                    }

                    return [
                        'id'          => $g->id,
                        'name'        => $g->template_name_snapshot,
                        'game_type'   => $g->base_game_type,
                        'is_complete' => $g->is_complete,
                        'started_at'  => $g->started_at,
                        'players'     => $players
                            ->map(fn ($p) => $display[$p->id] ?? $p->name)
                            ->values(),
                        'winners'     => $winners,
                    ];
                }),
        ]);
    }

    public function create(Request $request, Household $household): Response
    {
        $this->ensureMember($request, $household);

        $templates = GameTemplate::query()
            ->where(fn ($q) => $q->where('is_system', true)->orWhere('household_id', $household->id))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get(['id', 'name', 'target_score', 'low_score_wins', 'max_rounds', 'team_based', 'score_fields']);

        return Inertia::render('Scorekeeper/ScoredGames/Create', [
            'household' => $household->only(['id', 'name']),
            'templates' => $templates,
            'games'     => GameType::scorekeeper()
                ->visibleTo($household->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'players'   => $household->players()->where('is_guest', false)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Household $household)
    {
        $this->ensureMember($request, $household);

        // Template must be a system template or belong to this household.
        $template = GameTemplate::where('id', $request->input('game_template_id'))
            ->where(fn ($q) => $q->where('is_system', true)->orWhere('household_id', $household->id))
            ->firstOrFail();

        $rosterIds = $household->players()->pluck('players.id')->all();

        $validated = $request->validate([
            'played_at' => 'nullable|date',
        ]);

        $competitors = $template->team_based
            ? $this->teamCompetitors($request, $rosterIds)
            : $this->individualCompetitors($request, $household, $rosterIds);

        $game = $this->service->startFromTemplate(
            $household,
            $template,
            $competitors,
            $request->user(),
            $validated['played_at'] ?? null,
        );

        return redirect()
            ->route('scorekeeper.games.show', $game)
            ->with('success', 'Game started.');
    }

    /**
     * @param  array<int>  $rosterIds
     * @return array<int, array{name: string, player_ids: array<int>}>
     */
    private function individualCompetitors(Request $request, Household $household, array $rosterIds): array
    {
        $validated = $request->validate([
            'player_ids'   => 'required|array|min:2',
            'player_ids.*' => 'integer',
        ]);

        $ordered = array_values(array_filter(
            $validated['player_ids'],
            fn ($id) => in_array($id, $rosterIds, true),
        ));
        abort_unless(count($ordered) === count($validated['player_ids']), 422);

        $names = $household->players()->whereIn('players.id', $ordered)->pluck('name', 'id');

        return array_map(
            fn ($id) => ['name' => $names[$id] ?? 'Player', 'player_ids' => [$id]],
            $ordered,
        );
    }

    /**
     * @param  array<int>  $rosterIds
     * @return array<int, array{name: string, player_ids: array<int>}>
     */
    private function teamCompetitors(Request $request, array $rosterIds): array
    {
        $validated = $request->validate([
            'teams'                => 'required|array|min:2',
            'teams.*.name'         => 'required|string|max:255',
            'teams.*.player_ids'   => 'required|array|min:1',
            'teams.*.player_ids.*' => 'integer',
        ]);

        $seen = [];
        $competitors = [];
        foreach ($validated['teams'] as $team) {
            foreach ($team['player_ids'] as $id) {
                abort_unless(in_array($id, $rosterIds, true), 422);
                abort_if(in_array($id, $seen, true), 422, 'A player can only be on one team.');
                $seen[] = $id;
            }
            $competitors[] = ['name' => $team['name'], 'player_ids' => $team['player_ids']];
        }

        return $competitors;
    }

    public function show(Request $request, ScoredGame $scoredGame): Response
    {
        $this->ensureMember($request, $scoredGame->household);

        $scoredGame->load(['competitors.players', 'rounds.scores']);

        // First-name-only display, disambiguated across everyone in the game.
        $display = $this->displayNames($scoredGame->competitors->flatMap->players);

        $competitors = $scoredGame->competitors->map(fn ($c) => [
            'id'            => $c->id,
            // Individual columns show the player's short name; teams keep
            // their team name.
            'name'          => $scoredGame->team_based
                ? $c->name
                : ($display[$c->players->first()?->id] ?? $c->name),
            'display_order' => $c->display_order,
            'members'       => $c->players->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $display[$p->id] ?? $p->name,
                'has_account' => $p->user_id !== null,
            ])->values(),
        ])->values();

        $standings = $this->service->standings($scoredGame);
        if (! $scoredGame->team_based) {
            $byId = $scoredGame->competitors->keyBy('id');
            $standings = array_map(function ($row) use ($byId, $display) {
                $playerId = $byId[$row['competitor_id']]?->players->first()?->id;
                if ($playerId !== null && isset($display[$playerId])) {
                    $row['name'] = $display[$playerId];
                }

                return $row;
            }, $standings);
        }

        // Roster players not already in the game — offered for mid-game adds.
        $participatingIds = $scoredGame->competitors
            ->flatMap(fn ($c) => $c->players->pluck('id'))->all();
        $availablePlayers = $scoredGame->is_complete
            ? collect()
            : $scoredGame->household->players()
                ->where('is_guest', false)
                ->whereNotIn('players.id', $participatingIds)
                ->orderBy('name')
                ->get(['id', 'name']);

        $rounds = $scoredGame->rounds->map(fn (Round $round) => [
            'id'           => $round->id,
            'round_number' => $round->round_number,
            'scores'       => $round->scores->mapWithKeys(
                fn ($s) => [$s->competitor_id => (object) ($s->values ?? [])],
            ),
        ])->values();

        return Inertia::render('Scorekeeper/ScoredGames/Show', [
            'game' => [
                'id'             => $scoredGame->id,
                'name'           => $scoredGame->template_name_snapshot,
                'base_game_type' => $scoredGame->base_game_type,
                'target_score'   => $scoredGame->target_score,
                'low_score_wins' => $scoredGame->low_score_wins,
                'max_rounds'     => $scoredGame->max_rounds,
                'is_complete'    => $scoredGame->is_complete,
                'team_based'     => $scoredGame->team_based,
                'allow_self_scoring' => $scoredGame->allow_self_scoring,
                'started_at'     => $scoredGame->started_at?->toDateString(),
                'score_fields'   => $scoredGame->score_fields,
                'household_id'   => $scoredGame->household_id,
            ],
            'can' => [
                'score_all'          => $scoredGame->household->isScorer($request->user()),
                'self_competitor_id' => $this->selfCompetitorId($scoredGame, $request->user()),
            ],
            'household'      => $scoredGame->household->only(['id', 'name']),
            'competitors'    => $competitors,
            'availablePlayers' => $availablePlayers,
            'rounds'         => $rounds,
            'totals'         => $this->service->totals($scoredGame),
            'fieldSubtotals' => $this->service->fieldSubtotals($scoredGame),
            'standings'      => $standings,
            'completionMet'  => $this->service->completionMet($scoredGame),
        ]);
    }

    public function addRound(Request $request, ScoredGame $scoredGame)
    {
        $this->ensureScorer($request, $scoredGame->household);
        abort_if($scoredGame->is_complete, 403, 'Game is already complete.');

        $this->service->addRound($scoredGame);

        return back();
    }

    public function updateScores(Request $request, ScoredGame $scoredGame, Round $round)
    {
        $this->ensureMember($request, $scoredGame->household);
        abort_if($scoredGame->is_complete, 403, 'Game is already complete.');
        abort_unless($round->scored_game_id === $scoredGame->id, 404);

        $validated = $request->validate([
            'scores'     => 'required|array',
            'scores.*'   => 'array',
            'scores.*.*' => 'nullable|integer',
        ]);

        // Guests may only enter their own competitor's scores, and only when
        // the game's template allowed self-scoring.
        if (! $scoredGame->household->isScorer($request->user())) {
            abort_unless(
                $scoredGame->allow_self_scoring,
                403,
                'Only the scorekeeper can enter scores for this game.',
            );
            $selfId = $this->selfCompetitorId($scoredGame, $request->user());
            abort_unless($selfId, 403, 'You are not playing in this game.');
            foreach (array_keys($validated['scores']) as $competitorId) {
                abort_unless(
                    (int) $competitorId === $selfId,
                    403,
                    'You can only enter your own scores.',
                );
            }
        }

        $this->service->recordScores($round, $validated['scores']);

        return back();
    }

    /**
     * Correct the play date of a game (works on completed games too, so
     * history can be fixed after the fact). Scorers only.
     */
    public function updatePlayDate(Request $request, ScoredGame $scoredGame)
    {
        $this->ensureScorer($request, $scoredGame->household);

        $validated = $request->validate([
            'played_at' => 'required|date',
        ]);

        $scoredGame->update([
            'started_at' => \Carbon\Carbon::parse($validated['played_at']),
        ]);

        return back()->with('success', 'Play date updated.');
    }

    public function complete(Request $request, ScoredGame $scoredGame)
    {
        $this->ensureScorer($request, $scoredGame->household);

        $this->service->complete($scoredGame);

        return back()->with('success', 'Game completed.');
    }

    /**
     * Discard a game entirely (rounds and scores cascade). Works whether the
     * game is in progress or already complete.
     */
    public function destroy(Request $request, ScoredGame $scoredGame)
    {
        $this->ensureScorer($request, $scoredGame->household);

        $householdId = $scoredGame->household_id;
        $scoredGame->delete();

        return redirect()
            ->route('scorekeeper.households.games.index', $householdId)
            ->with('success', 'Game deleted.');
    }

    /**
     * Scorecard display names: first name only — extended with just enough of
     * the last name (letter by letter) to tell same-named people apart.
     *
     * @param  \Illuminate\Support\Collection  $players
     * @return array<int, string>  [player_id => display name]
     */
    private function displayNames($players): array
    {
        $parsed = $players->unique('id')->map(function ($p) {
            $parts = preg_split('/\s+/', trim($p->name)) ?: [];
            $first = array_shift($parts) ?: $p->name;

            return ['id' => $p->id, 'first' => $first, 'last' => implode('', $parts)];
        })->values();

        $out = [];
        foreach ($parsed->groupBy(fn ($n) => mb_strtolower($n['first'])) as $group) {
            if ($group->count() === 1) {
                $out[$group->first()['id']] = $group->first()['first'];
                continue;
            }

            // Same first name: extend last-name prefixes together until the
            // group is unambiguous (or we run out of letters).
            $maxLen = $group->max(fn ($n) => mb_strlen($n['last']));
            $len = 1;
            while ($len < $maxLen) {
                $candidates = $group->map(
                    fn ($n) => mb_strtolower(mb_substr($n['last'], 0, $len)),
                );
                if ($candidates->unique()->count() === $group->count()) {
                    break;
                }
                $len++;
            }

            foreach ($group as $n) {
                $suffix = mb_substr($n['last'], 0, $len);
                $out[$n['id']] = $n['first'].($suffix !== '' ? ' '.$suffix : '');
            }
        }

        return $out;
    }

    private function ensureMember(Request $request, Household $household): void
    {
        $isMember = $household->members()
            ->where('users.id', $request->user()->id)
            ->exists();

        abort_unless($isMember, 403);
    }

    private function ensureScorer(Request $request, Household $household): void
    {
        abort_unless($household->isScorer($request->user()), 403);
    }

    /**
     * The competitor (column) belonging to the user's linked player in this
     * game, if any.
     */
    private function selfCompetitorId(ScoredGame $game, User $user): ?int
    {
        $playerId = $game->household->players()
            ->where('user_id', $user->id)
            ->value('id');
        if (! $playerId) {
            return null;
        }

        return $game->competitors()
            ->whereHas('players', fn ($q) => $q->whereKey($playerId))
            ->value('id');
    }
}
