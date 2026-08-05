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
            'gameTypes' => \App\Models\GameType::online()->get(['id', 'name', 'slug']),
            'questionData' => $this->questionSelectionData($gameSession),
        ]);
    }

    /**
     * Data backing the "Questions" setup card: the active question bank plus
     * per-category / per-difficulty counts so the host can filter (Random) or
     * hand-pick. Only built for games that pull from the shared bank.
     */
    protected function questionSelectionData(GameSession $gameSession): ?array
    {
        // The per-slot picker backs America Says and Family Feud. Oodles stays
        // random (no picker), so it gets no bank.
        if (!in_array($gameSession->gameType->slug, ['america-says', 'family-feud'], true)) {
            return null;
        }

        // One bank of every active question (Standard + Final). The picker
        // filters it client-side by source / round type; final slots pull the
        // Final questions matching their answer count.
        $bank = \App\Models\Question::where('game_type_id', $gameSession->game_type_id)
            ->where('is_active', true)
            ->withCount('answers')
            ->with('category:id,name')
            ->orderBy('question_text')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'round_type' => $q->round_type,
                'is_official' => $q->is_official,
                'answers_count' => $q->answers_count,
                'category' => $q->category?->name,
                'category_id' => $q->category_id,
                'difficulty' => $q->difficulty,
            ]);

        return ['bank' => $bank];
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
            'gameState.currentCard.bonusQuestion.answers',
            'gameState.activeTeam',
            'sessionCards',
        ]);

        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;
        $currentCard = $state?->currentCard;

        // Question progress for America Says / Family Feud — counted within the
        // current round (regular play) or the whole final / fast-money segment,
        // so it reads "Question 1 of 2" per round rather than lumping the whole
        // game together next to the "Round 1" label. Also flag whether this is
        // the last question of the session (used to reveal "End Game").
        $currentQuestionNumber = null;
        $totalQuestions = null;
        $hasPreviousQuestion = false;
        $isLastQuestion = false;
        if (in_array($gameSession->gameType->slug, ['america-says', 'family-feud'], true)) {
            $allQuestions = $gameSession->sessionQuestions()->orderBy('display_order')->get();
            if ($currentQuestion) {
                $segment = $currentQuestion->segment ?? 'main';
                $group = $segment === 'main'
                    ? $allQuestions->filter(fn ($q) => ($q->segment ?? 'main') === 'main' && $q->round_number === $currentQuestion->round_number)->values()
                    : $allQuestions->filter(fn ($q) => ($q->segment ?? 'main') === $segment)->values();
                $totalQuestions = $group->count();
                $pos = $group->search(fn ($q) => $q->id === $currentQuestion->id);
                $currentQuestionNumber = $pos === false ? null : $pos + 1;
                $hasPreviousQuestion = $allQuestions
                    ->contains(fn ($q) => $q->display_order < $currentQuestion->display_order);
                $isLastQuestion = !$allQuestions
                    ->contains(fn ($q) => $q->display_order > $currentQuestion->display_order);
            } else {
                $totalQuestions = $allQuestions->count();
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
            ],
            'currentQuestion' => $currentQuestion ? [
                'id' => $currentQuestion->id,
                'question_text' => $currentQuestion->question->question_text,
                'status' => $currentQuestion->status,
                'round_number' => $currentQuestion->round_number,
                'segment' => $currentQuestion->segment,
                'points_available' => (int) $currentQuestion->points_available,
                'control_status' => $currentQuestion->control_status,
                'controlling_team_id' => $currentQuestion->controlling_team_id,
                'controlling_team_ids' => $currentQuestion->getControllingTeamIdsArray(),
                'bonus_points' => (int) $currentQuestion->bonus_points,
                'bonus_awarded_team_id' => data_get($state?->getStateValue("bonus_q{$currentQuestion->id}"), 'team_id'),
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
                // "Just for fun" opener — no points, no control.
                'bonus_question' => $currentCard->bonusQuestion ? [
                    'question_text' => $currentCard->bonusQuestion->question_text,
                    'answer_text' => $currentCard->bonusQuestion->answers->first()?->answer_text,
                ] : null,
            ] : null,
            'totalCards' => $gameSession->sessionCards->count(),
            'currentQuestionNumber' => $currentQuestionNumber,
            'totalQuestions' => $totalQuestions,
            'hasPreviousQuestion' => $hasPreviousQuestion,
            'isLastQuestion' => $isLastQuestion,
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

    /**
     * Reset the current question's board: un-reveal every answer, reverse the
     * points each reveal awarded, and reverse a manually-awarded sweep bonus,
     * leaving scores as if the question had never been played.
     */
    public function resetBoard(GameSession $gameSession)
    {
        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['success' => true]);
        }

        // Reverse the points from every reveal, then remove the reveals.
        $reveals = $currentQuestion->answerReveals()->get();
        foreach ($reveals->whereNotNull('team_id')->groupBy('team_id') as $teamId => $teamReveals) {
            $team = Team::find($teamId);
            if (!$team) {
                continue;
            }
            $team->update([
                'total_score' => max(0, $team->total_score - (int) $teamReveals->sum('points_awarded')),
            ]);
        }
        $currentQuestion->answerReveals()->delete();

        // Reverse a manually-awarded sweep bonus for this question, if any.
        $bonusKey = "bonus_q{$currentQuestion->id}";
        $bonus = $state->getStateValue($bonusKey, null);
        if (is_array($bonus) && !empty($bonus['team_id'])) {
            $bonusTeam = Team::find($bonus['team_id']);
            if ($bonusTeam) {
                $bonusTeam->update([
                    'total_score' => max(0, $bonusTeam->total_score - (int) ($bonus['amount'] ?? 0)),
                ]);
            }
            $state->setStateValue($bonusKey, null);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Reset the entire current round: un-reveal every answer and reverse the
     * points (and any sweep bonus) for every question in the round, set them all
     * back to pending, and reactivate the round's first question — restoring the
     * scoreboard to exactly where it stood at the end of the previous round.
     *
     * A "round" is every session question sharing the current question's
     * round_number for regular play, or the whole final / fast-money segment.
     */
    public function resetRound(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['success' => true]);
        }

        $segment = $currentQuestion->segment ?? 'main';
        $query = $gameSession->sessionQuestions();
        if (in_array($segment, ['final', 'fast_money'], true)) {
            $query->where('segment', $segment);
        } else {
            $query->where('round_number', $currentQuestion->round_number);
        }
        $roundQuestions = $query->orderBy('display_order')->get();

        foreach ($roundQuestions as $sq) {
            // Reverse the points from every reveal on this question, then remove them.
            $reveals = $sq->answerReveals()->get();
            foreach ($reveals->whereNotNull('team_id')->groupBy('team_id') as $teamId => $teamReveals) {
                $team = Team::find($teamId);
                if (!$team) {
                    continue;
                }
                $team->update([
                    'total_score' => max(0, $team->total_score - (int) $teamReveals->sum('points_awarded')),
                ]);
            }
            $sq->answerReveals()->delete();

            // Reverse a manually-awarded sweep bonus for this question, if any.
            $bonusKey = "bonus_q{$sq->id}";
            $bonus = $state->getStateValue($bonusKey, null);
            if (is_array($bonus) && !empty($bonus['team_id'])) {
                $bonusTeam = Team::find($bonus['team_id']);
                if ($bonusTeam) {
                    $bonusTeam->update([
                        'total_score' => max(0, $bonusTeam->total_score - (int) ($bonus['amount'] ?? 0)),
                    ]);
                }
                $state->setStateValue($bonusKey, null);
            }

            $sq->update(['status' => 'pending']);
        }

        // Reactivate the round's first question so the round replays from the top.
        $first = $roundQuestions->first();
        if ($first) {
            $first->update(['status' => 'active']);
            $state->update([
                'current_question_id' => $first->id,
                'round_number' => $first->round_number ?? $state->round_number,
                'active_team_id' => $first->controlling_team_id ?? $state->active_team_id,
            ]);
        }

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

        // Per-answer value comes from the round (points_available). Fall back to
        // the stored answer points for older sessions initialized before rounds.
        $answer = $currentQuestion->question->answers()->find($validated['answer_id']);
        $points = $currentQuestion->points_available > 0
            ? $currentQuestion->points_available
            : ($answer->points ?? 0);

        // The team in control earns the points for a revealed answer.
        $teamId = $validated['team_id'] ?? $currentQuestion->controlling_team_id ?? $state->active_team_id;

        // Create the reveal
        $currentQuestion->answerReveals()->create([
            'answer_id' => $validated['answer_id'],
            'team_id' => $teamId,
            'revealed_at' => now(),
            'points_awarded' => $points,
        ]);

        // Track answer statistics
        $answer->recordReveal();

        // Award points to team
        $team = $teamId ? Team::find($teamId) : null;
        if ($team) {
            $team->addScore($points);
        }

        return response()->json([
            'success' => true,
            'points' => $points,
        ]);
    }

    /**
     * Undo a revealed answer: remove the reveal and reverse the points it
     * awarded to its team.
     */
    public function unrevealAnswer(Request $request, GameSession $gameSession)
    {
        $validated = $request->validate([
            'answer_id' => 'required|exists:answers,id',
        ]);

        $state = $gameSession->gameState;
        $currentQuestion = $state->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        $reveal = $currentQuestion->answerReveals()->where('answer_id', $validated['answer_id'])->first();
        if (!$reveal) {
            return response()->json(['error' => 'Answer is not revealed'], 400);
        }

        $team = $reveal->team_id ? Team::find($reveal->team_id) : null;
        if ($team) {
            $team->update(['total_score' => max(0, $team->total_score - (int) $reveal->points_awarded)]);
        }

        $reveal->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Set which team currently has control of the question. Driven by clicking
     * a team on the host scoreboard; this is what used to be "start steal" — it
     * simply hands the turn to another team, with no timer or point changes.
     */
    public function setControllingTeam(Request $request, GameSession $gameSession)
    {
        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        if (!$gameSession->teams()->whereKey($validated['team_id'])->exists()) {
            return response()->json(['error' => 'Invalid team'], 400);
        }

        $currentQuestion->update([
            'controlling_team_id' => $validated['team_id'],
            'controlling_team_ids' => null,
            'control_status' => 'team_control',
        ]);
        $state->update(['active_team_id' => $validated['team_id']]);

        return response()->json(['success' => true]);
    }

    /**
     * Award the current question's sweep bonus to a team (manual, admin-driven).
     * Recorded per-question in state_data so a Reset Round can reverse it.
     */
    public function awardBonus(Request $request, GameSession $gameSession)
    {
        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        $bonus = (int) $currentQuestion->bonus_points;
        if ($bonus <= 0) {
            return response()->json(['error' => 'This question has no bonus'], 400);
        }

        $key = "bonus_q{$currentQuestion->id}";
        if ($state->getStateValue($key, null) !== null) {
            return response()->json(['error' => 'Bonus already awarded for this question'], 400);
        }

        $team = Team::find($validated['team_id']);
        if (!$team) {
            return response()->json(['error' => 'Invalid team'], 400);
        }

        $team->addScore($bonus);
        $state->setStateValue($key, ['team_id' => $team->id, 'amount' => $bonus]);

        return response()->json(['success' => true, 'bonus' => $bonus]);
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

        // Get points for this question
        // Check points_mode: 'database' uses the answer's points, 'fixed' uses points_per_answer setting
        $pointsMode = $gameSession->getConfig('points_mode', 'fixed');

        if ($pointsMode === 'database') {
            // Get points from the answer in the database (first answer for Oodles questions)
            $answer = $currentQuestion->question->answers()->first();
            $totalPoints = $answer?->points ?? $gameSession->getConfig('points_per_answer', 100);
        } else {
            // Use the fixed points_per_answer setting, or points_available if set on the session question
            $totalPoints = $currentQuestion->points_available ?: $gameSession->getConfig('points_per_answer', 100);
        }

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

        // Check if this was the last question on the card (for Oodles)
        // Award bonus points if configured
        $bonusPoints = 0;
        if ($state->current_card_id) {
            $remainingQuestions = $gameSession->sessionQuestions()
                ->where('session_card_id', $state->current_card_id)
                ->where('status', 'pending')
                ->count();

            if ($remainingQuestions === 0) {
                // This was the last question on the card
                $lastQuestionBonus = $gameSession->getConfig('last_question_bonus', 0);
                if ($lastQuestionBonus > 0) {
                    // Award bonus to each winning team
                    foreach ($teams as $team) {
                        $team->addScore($lastQuestionBonus);
                    }
                    $bonusPoints = $lastQuestionBonus;
                }
            }
        }

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
            'bonus_points' => $bonusPoints,
            'total_points' => ($pointsPerTeam + $bonusPoints) * $teams->count(),
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
            $state->update([
                'current_question_id' => $nextQuestion->id,
                'round_number' => $nextQuestion->round_number ?? $state->round_number,
            ]);
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

    /**
     * Step back to the previous question. Non-destructive: the question we leave
     * keeps its reveals/points and is set back to 'pending' so "Next Question"
     * resumes it, and the previous question is reactivated with its board intact.
     */
    public function previousQuestion(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $currentQuestion = $state->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        // Previous question by display order, scoped to the current card for
        // card-based games (Oodles).
        $query = $state->current_card_id
            ? $state->currentCard->sessionQuestions()
            : $gameSession->sessionQuestions();
        $previous = $query
            ->where('display_order', '<', $currentQuestion->display_order)
            ->orderByDesc('display_order')
            ->first();

        if (!$previous) {
            return response()->json(['error' => 'Already at the first question'], 400);
        }

        $currentQuestion->update(['status' => 'pending']);
        $previous->update(['status' => 'active']);
        $state->update([
            'current_question_id' => $previous->id,
            'round_number' => $previous->round_number ?? $state->round_number,
            'active_team_id' => $previous->controlling_team_id ?? $state->active_team_id,
        ]);

        return response()->json(['success' => true]);
    }
}
