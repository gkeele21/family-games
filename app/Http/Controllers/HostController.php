<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HostController extends Controller
{
    public function lobby(GameSession $gameSession): Response
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403, 'You are not the host of this game.');
        }

        $gameSession->load(['gameType', 'teams.members.user', 'gameState', 'sessionPlayers.user']);

        // Get user's friends
        $friends = auth()->user()->friends()->get()->map(fn ($friend) => [
            'id' => $friend->id,
            'name' => $friend->name,
            'first_name' => $friend->first_name,
            'nickname' => $friend->pivot->nickname,
        ]);

        // Get players who joined via invite code but aren't on a team yet
        $waitingPlayers = $gameSession->unassignedPlayers()->with('user')->get();

        // Merge default config with session-specific settings
        $config = array_merge(
            $gameSession->gameType->default_config ?? [],
            $gameSession->settings ?? []
        );

        return Inertia::render('Host/Lobby', [
            'gameSession' => $gameSession,
            'config' => $config,
            'friends' => $friends,
            'waitingPlayers' => $waitingPlayers,
        ]);
    }

    public function game(GameSession $gameSession): Response
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403, 'You are not the host of this game.');
        }

        $gameSession->load([
            'gameType',
            'teams.members',
            'gameState.currentQuestion.question.answers',
            'gameState.currentCard',
            'gameState.activeTeam',
            'sessionQuestions.question.answers',
            'sessionCards',
        ]);

        return Inertia::render('Host/Game', [
            'gameSession' => [
                'id' => $gameSession->id,
                'name' => $gameSession->name,
                'status' => $gameSession->status,
                'invite_code' => $gameSession->invite_code,
                'game_type' => [
                    'name' => $gameSession->gameType->name,
                    'slug' => $gameSession->gameType->slug,
                ],
            ],
            'config' => array_merge(
                $gameSession->gameType->default_config ?? [],
                $gameSession->settings ?? []
            ),
        ]);
    }

    public function getState(GameSession $gameSession)
    {
        $gameSession->load([
            'teams',
            'gameState.currentQuestion.question.answers',
            'gameState.currentQuestion.answerReveals',
            'gameState.currentCard.sessionQuestions.question',
            'gameState.activeTeam',
            'sessionCards',
        ]);

        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;
        $currentCard = $state?->currentCard;

        // Get question progress for America Says
        $currentQuestionNumber = null;
        $totalQuestions = null;
        if ($gameSession->gameType->slug === 'america-says') {
            $allQuestions = $gameSession->sessionQuestions()->orderBy('display_order')->get();
            $totalQuestions = $allQuestions->count();
            if ($currentQuestion) {
                $currentQuestionNumber = $allQuestions->search(fn ($q) => $q->id === $currentQuestion->id) + 1;
            }
        }

        // Get questions for the current card (Oodles)
        $cardQuestions = [];
        if ($currentCard) {
            $cardQuestions = $currentCard->sessionQuestions->map(fn ($sq) => [
                'id' => $sq->id,
                'question_text' => $sq->question->question_text,
                'display_order' => $sq->display_order,
                'status' => $sq->status,
                'is_current' => $state->current_question_id === $sq->id,
            ]);
        }

        return response()->json([
            'status' => $gameSession->status,
            'teams' => $gameSession->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'color' => $team->color,
                'total_score' => $team->total_score,
            ]),
            'gameState' => [
                'round_number' => $state?->round_number,
                'active_team_id' => $state?->active_team_id,
                'timer_started_at' => $state?->timer_started_at?->toIso8601String(),
                'timer_duration' => $state?->timer_duration,
                'remaining_seconds' => $state?->getRemainingSeconds(),
                'state_data' => $state?->state_data,
                'is_steal_round' => $state?->getStateValue('is_steal_round', false),
                'steal_points_percentage' => $gameSession->getConfig('steal_points_percentage', 50),
            ],
            'currentQuestion' => $currentQuestion ? [
                'id' => $currentQuestion->id,
                'question_text' => $currentQuestion->question->question_text,
                'status' => $currentQuestion->status,
                'control_status' => $currentQuestion->control_status,
                'controlling_team_id' => $currentQuestion->controlling_team_id,
                'controlling_team_ids' => $currentQuestion->getControllingTeamIdsArray(),
                'answers' => $currentQuestion->question->answers->map(fn ($answer) => [
                    'id' => $answer->id,
                    'answer_text' => $answer->answer_text,
                    'points' => $answer->points,
                    'display_order' => $answer->display_order,
                    'revealed' => $currentQuestion->answerReveals->contains('answer_id', $answer->id),
                ]),
                'revealed_answer_ids' => $currentQuestion->revealedAnswerIds(),
            ] : null,
            'currentCard' => $currentCard ? [
                'id' => $currentCard->id,
                'card_number' => $currentCard->card_number,
                'letter' => $currentCard->letter,
                'status' => $currentCard->status,
                'questions' => $cardQuestions,
            ] : null,
            'totalCards' => $gameSession->sessionCards->count(),
            'currentQuestionNumber' => $currentQuestionNumber,
            'totalQuestions' => $totalQuestions,
        ]);
    }

    public function startTimer(GameSession $gameSession)
    {
        $state = $gameSession->gameState;
        $state->update([
            'timer_started_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function pauseTimer(GameSession $gameSession)
    {
        $state = $gameSession->gameState;
        $remaining = $state->getRemainingSeconds();

        $state->update([
            'timer_started_at' => null,
            'timer_duration' => $remaining,
        ]);

        return response()->json(['success' => true, 'remaining' => $remaining]);
    }

    public function resetTimer(GameSession $gameSession)
    {
        $state = $gameSession->gameState;
        $defaultDuration = $gameSession->getConfig('control_timer_seconds', 30);

        $state->update([
            'timer_started_at' => null,
            'timer_duration' => $defaultDuration,
        ]);

        return response()->json(['success' => true]);
    }

    public function revealAnswer(Request $request, GameSession $gameSession)
    {
        $validated = $request->validate([
            'answer_id' => 'required|exists:answers,id',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $state = $gameSession->gameState;
        $currentQuestion = $state->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        // Check if already revealed
        if ($currentQuestion->answerReveals()->where('answer_id', $validated['answer_id'])->exists()) {
            return response()->json(['error' => 'Answer already revealed'], 400);
        }

        // Get the answer to find points
        $answer = $currentQuestion->question->answers()->find($validated['answer_id']);
        $basePoints = $answer->points ?? 0;

        // Check if we're in steal round - reduce points if so
        $isStealRound = $state->getStateValue('is_steal_round', false);
        if ($isStealRound) {
            $stealPercentage = $gameSession->getConfig('steal_points_percentage', 50);
            $points = (int) floor($basePoints * $stealPercentage / 100);
        } else {
            $points = $basePoints;
        }

        // Determine which team gets points (use controlling team if not specified)
        $teamId = $validated['team_id'] ?? $currentQuestion->controlling_team_id ?? $state->active_team_id;

        // Create the reveal
        $currentQuestion->answerReveals()->create([
            'answer_id' => $validated['answer_id'],
            'team_id' => $teamId,
            'revealed_at' => now(),
            'points_awarded' => $points,
        ]);

        // Award points to team
        if ($teamId) {
            $team = Team::find($teamId);
            $team?->addScore($points);
        }

        return response()->json([
            'success' => true,
            'points' => $points,
            'base_points' => $basePoints,
            'is_steal_round' => $isStealRound,
        ]);
    }

    public function startStealRound(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $currentQuestion = $state->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        // Get all teams and find the next team
        $teams = $gameSession->teams()->orderBy('display_order')->get();
        $currentTeamId = $currentQuestion->controlling_team_id ?? $state->active_team_id;

        // Find the next team in rotation
        $currentIndex = $teams->search(fn($t) => $t->id === $currentTeamId);
        $nextIndex = ($currentIndex + 1) % $teams->count();
        $nextTeam = $teams[$nextIndex];

        // Update controlling team for the steal round
        // Keep control_status as 'team_control' since a team does have control
        // The is_steal_round flag in state_data indicates it's a steal round
        $currentQuestion->update([
            'controlling_team_id' => $nextTeam->id,
            'controlling_team_ids' => null,
            'control_status' => 'team_control',
        ]);

        // Update game state
        $state->update(['active_team_id' => $nextTeam->id]);

        // Set steal round flag and reset timer
        $stealTimerSeconds = $gameSession->getConfig('steal_timer_seconds', 10);
        $state->setStateValue('is_steal_round', true);
        $state->update([
            'timer_duration' => $stealTimerSeconds,
            'timer_started_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'steal_team_id' => $nextTeam->id,
            'steal_team_name' => $nextTeam->name,
            'steal_timer_seconds' => $stealTimerSeconds,
        ]);
    }

    public function endStealRound(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;

        // Clear steal round flag
        $state->setStateValue('is_steal_round', false);

        // Stop the timer
        $state->update(['timer_started_at' => null]);

        return response()->json(['success' => true]);
    }

    public function endGame(GameSession $gameSession)
    {
        $gameSession->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function setControllingTeams(Request $request, GameSession $gameSession)
    {
        $validated = $request->validate([
            'team_ids' => 'required|array|min:1',
            'team_ids.*' => 'exists:teams,id',
        ]);

        $state = $gameSession->gameState;
        $currentQuestion = $state->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        // Verify all teams belong to this game session
        $validTeamIds = $gameSession->teams()->pluck('id')->toArray();
        foreach ($validated['team_ids'] as $teamId) {
            if (!in_array($teamId, $validTeamIds)) {
                return response()->json(['error' => 'Invalid team'], 400);
            }
        }

        $currentQuestion->setControllingTeams($validated['team_ids']);

        return response()->json([
            'success' => true,
            'controlling_team_ids' => $currentQuestion->getControllingTeamIdsArray(),
        ]);
    }

    public function selectQuestion(Request $request, GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'session_question_id' => 'required|exists:session_questions,id',
        ]);

        // Verify the question belongs to this game session
        $sessionQuestion = $gameSession->sessionQuestions()
            ->where('id', $validated['session_question_id'])
            ->first();

        if (!$sessionQuestion) {
            return response()->json(['error' => 'Question not found in this game'], 404);
        }

        // Get fresh game state to ensure we have active_team_id
        $gameSession->load('gameState');
        $state = $gameSession->gameState;

        // Update the game state to set this as the current question
        $state->update([
            'current_question_id' => $sessionQuestion->id,
        ]);

        // Check if there are multiple controlling teams from a previous All Play tie
        $stateData = $state->state_data ?? [];
        $nextControllingTeamIds = $stateData['next_controlling_team_ids'] ?? null;

        if ($nextControllingTeamIds && count($nextControllingTeamIds) > 1) {
            // Multiple teams have control (All Play tie situation)
            $sessionQuestion->update([
                'status' => 'active',
                'controlling_team_id' => null,
                'controlling_team_ids' => $nextControllingTeamIds,
                'control_status' => 'team_control',
            ]);

            // Clear the next_controlling_team_ids from state
            unset($stateData['next_controlling_team_ids']);
            $state->update(['state_data' => $stateData]);
        } else {
            // Single team has control (normal case)
            $controllingTeamId = $state->active_team_id;
            if (!$controllingTeamId) {
                $firstTeam = $gameSession->teams()->orderBy('display_order')->first();
                $controllingTeamId = $firstTeam?->id;

                // Also set it on the state for future use
                if ($controllingTeamId) {
                    $state->update(['active_team_id' => $controllingTeamId]);
                }
            }

            // Clear any leftover next_controlling_team_ids
            if (isset($stateData['next_controlling_team_ids'])) {
                unset($stateData['next_controlling_team_ids']);
                $state->update(['state_data' => $stateData]);
            }

            // Mark the question as active and set the controlling team
            $sessionQuestion->update([
                'status' => 'active',
                'controlling_team_id' => $controllingTeamId,
                'controlling_team_ids' => null,
                'control_status' => 'team_control',
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function nextCard(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $currentCard = $state->currentCard;

        if (!$currentCard) {
            return response()->json(['error' => 'No current card'], 400);
        }

        // Mark current card as completed
        $currentCard->update(['status' => 'completed']);

        // Clear current question
        $state->update(['current_question_id' => null]);

        // Find the next card
        $nextCard = $gameSession->sessionCards()
            ->where('card_number', '>', $currentCard->card_number)
            ->orderBy('card_number')
            ->first();

        if (!$nextCard) {
            // No more cards - game is complete
            $gameSession->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            return response()->json(['success' => true, 'game_complete' => true]);
        }

        // Set the next card as current
        $state->update(['current_card_id' => $nextCard->id]);

        return response()->json(['success' => true, 'game_complete' => false]);
    }

    public function markCorrect(Request $request, GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        // Accept either a single team_id or an array of team_ids
        $validated = $request->validate([
            'team_id' => 'required_without:team_ids|exists:teams,id',
            'team_ids' => 'required_without:team_id|array|min:1',
            'team_ids.*' => 'exists:teams,id',
        ]);

        // Load the game state and current question
        $gameSession->load('gameState.currentQuestion');
        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        // Get the team IDs (handle both single and multiple)
        $teamIds = $validated['team_ids'] ?? [$validated['team_id']];

        // Verify all teams belong to this game
        $teams = $gameSession->teams()->whereIn('id', $teamIds)->get();
        if ($teams->count() !== count($teamIds)) {
            return response()->json(['error' => 'Invalid team(s)'], 400);
        }

        // Get points for this question (default 100 for Oodles)
        // Use ?: instead of ?? because points_available defaults to 0 in the database
        $totalPoints = $currentQuestion->points_available ?: $gameSession->getConfig('points_per_answer', 100);

        // Check multi-team scoring setting: 'full' = all get full points, 'split' = divide among teams
        $multiTeamScoring = $gameSession->getConfig('multi_team_scoring', 'full');

        if ($teams->count() > 1 && $multiTeamScoring === 'split') {
            // Split points among winning teams (round down, but ensure at least 1 point each)
            $pointsPerTeam = max(1, (int) floor($totalPoints / $teams->count()));
        } else {
            // All teams get full points
            $pointsPerTeam = $totalPoints;
        }

        // Award points to each winning team
        foreach ($teams as $team) {
            $team->addScore($pointsPerTeam);
        }

        // Mark question as completed
        $currentQuestion->update(['status' => 'completed']);

        // Clear current question from game state
        $state->update(['current_question_id' => null]);

        // Set the winning team(s) as the active/controlling team for the next question
        if ($teams->count() === 1) {
            // Single winner - set as active team
            $state->update(['active_team_id' => $teams->first()->id]);
        } else {
            // Multiple winners - store them for the next question's control
            // They'll all have control on the next question
            $state->update([
                'state_data' => array_merge($state->state_data ?? [], [
                    'next_controlling_team_ids' => $teams->pluck('id')->toArray(),
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'team_names' => $teams->pluck('name')->toArray(),
            'points_per_team' => $pointsPerTeam,
            'total_points' => $pointsPerTeam * $teams->count(),
        ]);
    }

    public function markWrong(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $currentQuestion = $state->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        // Switch to All Play mode - clear controlling team
        $currentQuestion->update([
            'control_status' => 'all_play',
            'controlling_team_id' => null,
            'controlling_team_ids' => null,
        ]);

        // Reset and start the timer for All Play
        $defaultDuration = $gameSession->getConfig('control_timer_seconds', 30);
        $state->update([
            'timer_duration' => $defaultDuration,
            'timer_started_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function updateTeamScore(Request $request, GameSession $gameSession, Team $team)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        // Verify the team belongs to this game session
        if ($team->game_session_id !== $gameSession->id) {
            return response()->json(['error' => 'Team does not belong to this game session'], 400);
        }

        $validated = $request->validate([
            'score' => 'required|integer|min:0',
        ]);

        $team->update(['total_score' => $validated['score']]);

        return response()->json([
            'success' => true,
            'team_id' => $team->id,
            'new_score' => $team->total_score,
        ]);
    }

    public function nextQuestion(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $currentQuestion = $state->currentQuestion;

        if ($currentQuestion) {
            // Mark current question as completed
            $currentQuestion->update(['status' => 'completed']);
        }

        // Determine the controlling team for the next question
        // Use active_team_id from state (the team that had/has control)
        $stateData = $state->state_data ?? [];
        $nextControllingTeamIds = $stateData['next_controlling_team_ids'] ?? null;

        // For card-based games (Oodles), find next question in current card
        if ($state->current_card_id) {
            $currentCard = $state->currentCard;
            $nextQuestion = $currentCard->sessionQuestions()
                ->where('status', 'pending')
                ->orderBy('display_order')
                ->first();

            if ($nextQuestion) {
                $state->update(['current_question_id' => $nextQuestion->id]);

                // Set up controlling team(s) for the next question
                if ($nextControllingTeamIds && count($nextControllingTeamIds) > 1) {
                    // Multiple teams have control (from All Play tie)
                    $nextQuestion->update([
                        'status' => 'active',
                        'controlling_team_id' => null,
                        'controlling_team_ids' => $nextControllingTeamIds,
                        'control_status' => 'team_control',
                    ]);
                    // Clear the next_controlling_team_ids from state
                    unset($stateData['next_controlling_team_ids']);
                    $state->update(['state_data' => $stateData]);
                } else {
                    // Single team has control (use active_team_id)
                    $nextQuestion->update([
                        'status' => 'active',
                        'controlling_team_id' => $state->active_team_id,
                        'controlling_team_ids' => null,
                        'control_status' => 'team_control',
                    ]);
                }

                return response()->json(['success' => true, 'card_complete' => false]);
            }

            // No more questions on this card
            return response()->json(['success' => true, 'card_complete' => true]);
        }

        // For non-card games (Family Feud, America Says), find next question
        $nextQuestion = $gameSession->sessionQuestions()
            ->where('status', 'pending')
            ->orderBy('display_order')
            ->first();

        if ($nextQuestion) {
            $state->update(['current_question_id' => $nextQuestion->id]);
            $nextQuestion->update(['status' => 'active']);
            return response()->json(['success' => true, 'game_complete' => false]);
        }

        // No more questions - game is complete
        $gameSession->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true, 'game_complete' => true]);
    }
}
