<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\Leaderboard;
use App\Models\Scorekeeper\ScoredGame;
use App\Services\Scorekeeper\ScoreGameService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private ScoreGameService $scoreGameService)
    {
    }

    public function index(): Response
    {
        $user = Auth::user();
        $householdIds = $user->households()->pluck('households.id');

        // Active trivia games (lobby or playing)
        $activeTriviaGames = GameSession::with(['gameType', 'teams'])
            ->where('host_user_id', $user->id)
            ->whereIn('status', ['lobby', 'playing', 'paused'])
            ->get()
            ->map(fn (GameSession $g) => [
                'kind'             => 'trivia',
                'id'               => $g->id,
                'name'             => $g->name,
                'status'           => $g->status,
                'invite_code'      => $g->invite_code,
                'code'             => null,
                'competitor_count' => $g->teams->count(),
                'team_based'       => true,
                'game_type'        => ['name' => $g->gameType->name, 'slug' => $g->gameType->slug],
                'updated_at'       => $g->updated_at,
            ]);

        // In-progress scorekeeper games from any household the user belongs to
        $activeScoredGames = ScoredGame::withCount('competitors')
            ->whereIn('household_id', $householdIds)
            ->where('is_complete', false)
            ->get()
            ->map(fn (ScoredGame $g) => [
                'kind'             => 'scorekeeper',
                'id'               => $g->id,
                'name'             => $g->template_name_snapshot,
                'status'           => 'scoring',
                'invite_code'      => null,
                'code'             => null,
                'competitor_count' => $g->competitors_count,
                'team_based'       => (bool) $g->team_based,
                'game_type'        => ['name' => $g->base_game_type ?: 'Scorekeeper', 'slug' => 'scorekeeper'],
                'updated_at'       => $g->updated_at,
            ]);

        // PropOff groups the user plays in whose event is still underway
        $activePropOffGames = $user->propoffGroups()
            ->with('event')
            ->withCount('users')
            ->whereHas('event', fn ($q) => $q->whereIn('status', ['open', 'locked', 'in_progress']))
            ->get()
            ->map(fn ($group) => [
                'kind'             => 'propoff',
                'id'               => $group->id,
                'name'             => $group->event->name,
                'status'           => $group->event->status,
                'invite_code'      => $group->code,
                'code'             => $group->code,
                'competitor_count' => $group->users_count,
                'team_based'       => false,
                'game_type'        => ['name' => 'PropOff', 'slug' => 'propoff'],
                'updated_at'       => $group->event->updated_at,
            ]);

        $activeGames = $activeTriviaGames
            ->concat($activeScoredGames)
            ->concat($activePropOffGames)
            ->sortByDesc('updated_at')
            ->values();

        // Recent completed trivia games. "Who played" comes from the attendance
        // roster (game_session_players), which is editable from this list.
        $recentTriviaGames = GameSession::with(['gameType', 'teams', 'players'])
            ->where('host_user_id', $user->id)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get()
            ->map(function (GameSession $g) {
                $winner = $g->teams->sortByDesc('total_score')->first();
                $players = $g->players->sortBy('name');

                return [
                    'kind'               => 'trivia',
                    'id'                 => $g->id,
                    'code'               => null,
                    'name'               => $g->name ?: $g->gameType->name,
                    'game_type'          => ['name' => $g->gameType->name, 'slug' => $g->gameType->slug],
                    'finished_at'        => $g->completed_at ?? $g->created_at,
                    'player_count'       => $players->count(),
                    'players'            => $players->pluck('name')->values(),
                    'present_player_ids' => $players->pluck('id')->values(),
                    'winners'            => $winner
                        ? [['name' => $winner->name, 'color' => $winner->color, 'score' => $winner->total_score]]
                        : [],
                ];
            });

        // Recent completed scorekeeper games (rank-1 finishers; ties → several winners)
        $recentScoredGames = ScoredGame::with(['competitors.players:players.id,players.name', 'rounds.scores'])
            ->whereIn('household_id', $householdIds)
            ->where('is_complete', true)
            ->orderByDesc('ended_at')
            ->limit(5)
            ->get()
            ->map(function (ScoredGame $g) {
                $players = $g->competitors->flatMap->players->unique('id')->values();
                $display = $this->scoreGameService->displayNames($players);

                // Individuals get their short name; teams keep the team name.
                $byId = $g->competitors->keyBy('id');
                $winners = collect($this->scoreGameService->standings($g))
                    ->takeWhile(fn (array $row) => $row['rank'] === 1)
                    ->map(function (array $row) use ($g, $byId, $display) {
                        $playerId = $g->team_based
                            ? null
                            : $byId[$row['competitor_id']]?->players->first()?->id;

                        return [
                            'name'  => $display[$playerId] ?? $row['name'],
                            'color' => null,
                            'score' => $row['total'],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'kind'         => 'scorekeeper',
                    'id'           => $g->id,
                    'code'         => null,
                    'name'         => $g->template_name_snapshot,
                    'game_type'    => ['name' => $g->base_game_type ?: 'Scorekeeper', 'slug' => 'scorekeeper'],
                    'finished_at'  => $g->ended_at ?? $g->started_at,
                    'player_count' => $players->count(),
                    'players'      => $players
                        ->map(fn ($p) => $display[$p->id] ?? $p->name)
                        ->values(),
                    'winners'      => $winners,
                ];
            });

        // Recent completed PropOff events (winners = leaderboard rank 1)
        $recentPropOffGames = $user->propoffGroups()
            ->with('event')
            ->whereHas('event', fn ($q) => $q->where('status', 'completed'))
            ->get()
            ->sortByDesc(fn ($group) => $group->event->event_date)
            ->take(5)
            ->map(function ($group) {
                $playerCount = Leaderboard::where('event_id', $group->event_id)
                    ->where('group_id', $group->id)
                    ->count()
                    ?: Entry::where('group_id', $group->id)->where('is_complete', true)->count();

                $winnerRows = Leaderboard::with('user')
                    ->where('event_id', $group->event_id)
                    ->where('group_id', $group->id)
                    ->where('rank', 1)
                    ->get();
                $display = $this->scoreGameService->displayNames(
                    $winnerRows->map(fn ($row) => $row->user)->filter(),
                );

                return [
                    'kind'         => 'propoff',
                    'id'           => $group->id,
                    'code'         => $group->code,
                    'name'         => $group->event->name,
                    'game_type'    => ['name' => 'PropOff', 'slug' => 'propoff'],
                    'finished_at'  => $group->event->event_date ?? $group->event->updated_at,
                    'player_count' => $playerCount,
                    'players'      => [],
                    'winners'      => $winnerRows
                        ->map(fn ($row) => [
                            'name'  => $display[$row->user_id] ?? $row->user?->name ?? '—',
                            'color' => null,
                            'score' => $row->total_score,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $recentGames = $recentTriviaGames
            ->concat($recentScoredGames)
            ->concat($recentPropOffGames)
            ->sortByDesc('finished_at')
            ->take(5)
            ->values();

        // Stats: hosted = trivia sessions, scorekeeper games, and PropOff events
        // the user created; completed adds PropOff events they finished an entry for.
        $totalGamesHosted = GameSession::where('host_user_id', $user->id)->count()
            + ScoredGame::where('created_by_user_id', $user->id)->count()
            + Event::where('created_by', $user->id)->count();

        $propoffCompleted = Entry::where('user_id', $user->id)
            ->where('is_complete', true)
            ->count();
        $completedGames = GameSession::where('host_user_id', $user->id)
            ->where('status', 'completed')
            ->count()
            + ScoredGame::where('created_by_user_id', $user->id)
                ->where('is_complete', true)
                ->count()
            + $propoffCompleted;

        // Favorite game type (most played across trivia, scorekeeper, and PropOff)
        $triviaTypeCounts = GameSession::where('host_user_id', $user->id)
            ->selectRaw('game_type_id, COUNT(*) as count')
            ->groupBy('game_type_id')
            ->with('gameType')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->gameType->name => (int) $row->count]);

        $scoredTypeCounts = ScoredGame::where('created_by_user_id', $user->id)
            ->selectRaw("COALESCE(NULLIF(base_game_type, ''), template_name_snapshot) as type_name, COUNT(*) as count")
            ->groupBy('type_name')
            ->pluck('count', 'type_name');

        $propoffTypeCounts = $propoffCompleted > 0
            ? collect(['PropOff' => $propoffCompleted])
            : collect();

        $favoriteGameType = $triviaTypeCounts
            ->mergeRecursive($scoredTypeCounts)
            ->mergeRecursive($propoffTypeCounts)
            ->map(fn ($count) => is_array($count) ? array_sum($count) : (int) $count)
            ->sortDesc()
            ->keys()
            ->first();

        // Household rosters for the "who played" editor on recent trivia games.
        $attendanceRosters = $user->households()
            ->with(['players' => fn ($q) => $q->orderBy('name')])
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'name' => $h->name,
                'players' => $h->players->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            ])->values();

        return Inertia::render('Dashboard', [
            'activeGames' => $activeGames,
            'recentGames' => $recentGames,
            'attendanceRosters' => $attendanceRosters,
            'stats' => [
                'totalGamesHosted' => $totalGamesHosted,
                'completedGames' => $completedGames,
                'favoriteGameType' => $favoriteGameType,
            ],
        ]);
    }
}
