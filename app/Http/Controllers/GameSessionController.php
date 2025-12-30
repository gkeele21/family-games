<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\GameState;
use App\Models\GameType;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\GameInitializationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameSessionController extends Controller
{
    public function index(): Response
    {
        $sessions = GameSession::with(['gameType', 'host', 'teams'])
            ->where('host_user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        $gameTypes = GameType::all();

        return Inertia::render('GameSessions/Index', [
            'sessions' => $sessions,
            'gameTypes' => $gameTypes,
        ]);
    }

    public function create(): Response
    {
        $gameTypes = GameType::all();

        return Inertia::render('GameSessions/Create', [
            'gameTypes' => $gameTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'game_type_id' => 'required|exists:game_types,id',
            'name' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
        ]);

        $session = GameSession::create([
            'game_type_id' => $validated['game_type_id'],
            'host_user_id' => auth()->id(),
            'name' => $validated['name'] ?? null,
            'settings' => $validated['settings'] ?? null,
            'status' => 'lobby',
        ]);

        // Create initial game state
        GameState::create([
            'game_session_id' => $session->id,
            'round_number' => 1,
            'timer_duration' => $session->getConfig('control_timer_seconds', 30),
        ]);

        return redirect()->route('host.lobby', $session);
    }

    public function addTeam(Request $request, GameSession $gameSession)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        $order = $gameSession->teams()->max('display_order') ?? 0;

        $team = Team::create([
            'game_session_id' => $gameSession->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#3B82F6',
            'display_order' => $order + 1,
        ]);

        return back()->with('success', 'Team added successfully');
    }

    public function removeTeam(GameSession $gameSession, Team $team)
    {
        if ($team->game_session_id !== $gameSession->id) {
            abort(403);
        }

        $team->delete();

        return back()->with('success', 'Team removed successfully');
    }

    public function reorderTeams(Request $request, GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'team_ids' => 'required|array',
            'team_ids.*' => 'exists:teams,id',
        ]);

        // Update display_order for each team
        foreach ($validated['team_ids'] as $index => $teamId) {
            Team::where('id', $teamId)
                ->where('game_session_id', $gameSession->id)
                ->update(['display_order' => $index + 1]);
        }

        return back()->with('success', 'Team order updated');
    }

    public function addTeamMember(Request $request, GameSession $gameSession, Team $team)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        if ($team->game_session_id !== $gameSession->id) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'required|in:guest,friend,session_player',
            'guest_name' => 'required_if:type,guest|nullable|string|max:255',
            'user_id' => 'required_if:type,friend|nullable|exists:users,id',
            'session_player_id' => 'required_if:type,session_player|nullable|exists:session_players,id',
        ]);

        if ($validated['type'] === 'guest') {
            TeamMember::create([
                'team_id' => $team->id,
                'guest_name' => $validated['guest_name'],
            ]);
        } elseif ($validated['type'] === 'friend') {
            // Check if user is already on a team in this session
            $existingMember = TeamMember::whereHas('team', function ($query) use ($gameSession) {
                $query->where('game_session_id', $gameSession->id);
            })->where('user_id', $validated['user_id'])->first();

            if ($existingMember) {
                return back()->withErrors(['user_id' => 'This user is already on a team.']);
            }

            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $validated['user_id'],
            ]);
        } elseif ($validated['type'] === 'session_player') {
            $sessionPlayer = $gameSession->sessionPlayers()->find($validated['session_player_id']);

            if (!$sessionPlayer) {
                return back()->withErrors(['session_player_id' => 'Player not found in this session.']);
            }

            // Create team member from session player
            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $sessionPlayer->user_id,
                'guest_name' => $sessionPlayer->guest_name,
            ]);

            // Update session player with team assignment
            $sessionPlayer->update(['team_id' => $team->id]);
        }

        return back()->with('success', 'Team member added successfully');
    }

    public function removeTeamMember(GameSession $gameSession, Team $team, TeamMember $teamMember)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        if ($team->game_session_id !== $gameSession->id || $teamMember->team_id !== $team->id) {
            abort(403);
        }

        // If this member was from a session player, unassign them from the team
        if ($teamMember->user_id) {
            $gameSession->sessionPlayers()
                ->where('user_id', $teamMember->user_id)
                ->update(['team_id' => null]);
        }

        $teamMember->delete();

        return back()->with('success', 'Team member removed successfully');
    }

    public function updateSettings(Request $request, GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        if ($gameSession->status !== 'lobby') {
            return back()->withErrors(['settings' => 'Cannot update settings after game has started.']);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        // Merge with existing settings
        $currentSettings = $gameSession->settings ?? [];
        $newSettings = array_merge($currentSettings, $validated['settings']);

        $gameSession->update([
            'settings' => $newSettings,
        ]);

        return back()->with('success', 'Settings updated successfully');
    }

    public function startGame(GameSession $gameSession, GameInitializationService $initService)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        if ($gameSession->teams()->count() < 1) {
            return back()->withErrors(['teams' => 'At least one team is required to start the game.']);
        }

        // Initialize questions/cards based on game type
        try {
            $initService->initialize($gameSession);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['questions' => $e->getMessage()]);
        }

        $gameSession->update([
            'status' => 'playing',
            'started_at' => now(),
        ]);

        // Initialize game state based on game type
        $state = $gameSession->gameState;
        $teams = $gameSession->teams()->orderBy('display_order')->get();

        $state->update([
            'active_team_id' => $teams->first()?->id,
            'state_data' => [
                'team_order' => $teams->pluck('id')->toArray(),
                'team_rotation_index' => 0,
            ],
        ]);

        return redirect()->route('host.game', $gameSession);
    }

    public function destroy(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        // Only allow deletion of games in lobby status
        if ($gameSession->status !== 'lobby') {
            return back()->withErrors(['delete' => 'Cannot delete a game that has already started.']);
        }

        $gameSession->delete();

        return redirect()->route('games.index')->with('success', 'Game cancelled successfully.');
    }
}
