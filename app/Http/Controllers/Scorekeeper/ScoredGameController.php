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
                ->orderByDesc('started_at')
                ->get()
                ->map(fn (ScoredGame $g) => [
                    'id'          => $g->id,
                    'name'        => $g->template_name_snapshot,
                    'is_complete' => $g->is_complete,
                    'started_at'  => $g->started_at,
                ]),
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

        $competitors = $template->team_based
            ? $this->teamCompetitors($request, $rosterIds)
            : $this->individualCompetitors($request, $household, $rosterIds);

        $game = $this->service->startFromTemplate($household, $template, $competitors, $request->user());

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

        $competitors = $scoredGame->competitors->map(fn ($c) => [
            'id'            => $c->id,
            'name'          => $c->name,
            'display_order' => $c->display_order,
            'members'       => $c->players->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'has_account' => $p->user_id !== null,
            ])->values(),
        ])->values();

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
            'standings'      => $this->service->standings($scoredGame),
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
