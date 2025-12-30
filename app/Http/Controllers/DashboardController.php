<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        // Active games (lobby or playing)
        $activeGames = GameSession::with(['gameType', 'teams'])
            ->where('host_user_id', $user->id)
            ->whereIn('status', ['lobby', 'playing', 'paused'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Recent completed games
        $recentGames = GameSession::with(['gameType', 'teams'])
            ->where('host_user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        // Stats
        $totalGamesHosted = GameSession::where('host_user_id', $user->id)->count();
        $completedGames = GameSession::where('host_user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        // Favorite game type (most played)
        $favoriteGameType = GameSession::where('host_user_id', $user->id)
            ->selectRaw('game_type_id, COUNT(*) as count')
            ->groupBy('game_type_id')
            ->orderByDesc('count')
            ->with('gameType')
            ->first();

        return Inertia::render('Dashboard', [
            'activeGames' => $activeGames,
            'recentGames' => $recentGames,
            'stats' => [
                'totalGamesHosted' => $totalGamesHosted,
                'completedGames' => $completedGames,
                'favoriteGameType' => $favoriteGameType?->gameType?->name ?? null,
            ],
        ]);
    }
}
