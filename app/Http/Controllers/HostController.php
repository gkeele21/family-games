<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\GameState;
use App\Models\SessionQuestion;
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
            'attendanceData' => $this->attendanceData($gameSession),
            // Whether this game has already been started (and thus has live
            // progress that a fresh Start would wipe). Drives the lobby's
            // "Resume vs. Restart" split button.
            'wasStarted' => $gameSession->started_at !== null,
        ]);
    }

    /**
     * Data backing the "Who's playing?" setup card: the host's household rosters,
     * who's currently marked present for this game, and — the whole point — which
     * questions each roster player has already been served in past completed
     * games. The picker uses that last map to flag/avoid repeats for tonight's
     * group. Only built for the bank-backed games (America Says / Family Feud).
     */
    protected function attendanceData(GameSession $gameSession): ?array
    {
        if (!in_array($gameSession->gameType->slug, ['america-says', 'family-feud'], true)) {
            return null;
        }

        $user = auth()->user();
        $households = $user->households()
            ->with(['players' => fn ($q) => $q->orderBy('name')])
            ->get();

        $present = $gameSession->players()->pluck('players.id');

        if ($households->isEmpty()) {
            return ['households' => [], 'present' => $present->values(), 'seenByPlayer' => (object) [], 'defaultHouseholdId' => null];
        }

        $rosterIds = $households->flatMap->players->pluck('id');

        // player_id => [question_id, ...] seen across completed games they attended
        // (excluding this session, which isn't played yet).
        $seenByPlayer = \DB::table('game_session_players as gsp')
            ->join('game_sessions as gs', 'gs.id', '=', 'gsp.game_session_id')
            ->join('session_questions as sq', 'sq.game_session_id', '=', 'gsp.game_session_id')
            ->where('gs.status', 'completed')
            ->where('gs.id', '!=', $gameSession->id)
            ->whereIn('gsp.player_id', $rosterIds)
            ->select('gsp.player_id', 'sq.question_id')
            ->distinct()
            ->get()
            ->groupBy('player_id')
            ->map(fn ($rows) => $rows->pluck('question_id')->values());

        // Default the picker to the household that already has attendees, else
        // the user's remembered household, else their first.
        $default = $present->isNotEmpty()
            ? $households->first(fn ($h) => $h->players->pluck('id')->intersect($present)->isNotEmpty())?->id
            : null;
        $default ??= $households->contains('id', $user->last_household_id)
            ? $user->last_household_id
            : $households->first()->id;

        return [
            'households' => $households->map(fn ($h) => [
                'id' => $h->id,
                'name' => $h->name,
                'players' => $h->players->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            ])->values(),
            'present' => $present->values(),
            'seenByPlayer' => $seenByPlayer->isEmpty() ? (object) [] : $seenByPlayer,
            'defaultHouseholdId' => $default,
        ];
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
                // How many completed games this question has appeared in — surfaced
                // in the picker and used to prefer least-used questions.
                'times_used' => (int) $q->times_used,
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

        // Whether the *next* question crosses into an America Says final round —
        // i.e. no regular questions remain but a final one is still to play. Used
        // to label the last regular round's advance button "Start Final Round".
        $finalQueued = false;
        if (in_array($gameSession->gameType->slug, ['america-says', 'family-feud'], true)
            && ($currentQuestion?->segment ?? 'main') !== 'final') {
            $pending = $gameSession->sessionQuestions()->where('status', 'pending');
            $finalQueued = (clone $pending)->where('segment', 'final')->exists()
                && !(clone $pending)->where('segment', '!=', 'final')->exists();
        }

        // The America Says final questions (F1..F4) with per-question progress —
        // backs the host's final navigator/steps and the reveal-the-misses flow.
        $finalQuestions = [];
        if ($gameSession->gameType->slug === 'america-says') {
            $finalQuestions = $gameSession->sessionQuestions()
                ->where('segment', 'final')
                ->with(['question.answers', 'answerReveals'])
                ->orderBy('display_order')
                ->get()
                ->map(fn ($sq) => [
                    'id' => $sq->id,
                    'question_text' => $sq->question->question_text,
                    'answers_needed' => $sq->answers_needed ?? $sq->question->answers->count(),
                    'status' => $sq->status,
                    'total_answers' => $sq->question->answers->count(),
                    'revealed_count' => $sq->answerReveals->count(),
                    'is_current' => $state?->current_question_id === $sq->id,
                ]);
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
            'finalQueued' => $finalQueued,
            'finalQuestions' => $finalQuestions,
        ]);
    }

    public function startTimer(GameSession $gameSession)
    {
        $state = $gameSession->gameState;
        // Start ~1s in the future so the board has a beat to reach the (cast) TV
        // before the clock ticks — otherwise casting latency eats the first few
        // seconds and the timer already reads low by the time it's on screen.
        $state->update([
            'timer_started_at' => now()->addSecond(),
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
        if (in_array($segment, ['final', 'fast_money', 'tiebreaker'], true)) {
            // Scope by segment, not round_number — final/tiebreaker questions have
            // no round_number, so a round_number match would sweep them all up.
            $query->where('segment', $segment);
        } else {
            $query->where('round_number', $currentQuestion->round_number);
        }
        $roundQuestions = $query->orderBy('display_order')->get()->values();

        // Regular rounds: rebuild who's primary per board from the rotation (the
        // same formula as init) so a reset restores the original "who's up",
        // undoing any steal hand-off or manual control change during play.
        $isRegular = !in_array($segment, ['final', 'fast_money', 'tiebreaker'], true);
        $teamsOrdered = $gameSession->teams()->orderBy('display_order')->orderBy('id')->get();
        $teamCount = max(1, $teamsOrdered->count());
        $roundIndex = ($currentQuestion->round_number ?? 1) - 1;

        // Prefer the round-start snapshot: restore the exact scores from before the
        // round, regardless of reveals, auto-sweeps, or hand-edits during it. Only
        // when there's no snapshot (older sessions) do we fall back to reversing
        // each reveal's points.
        $snapshot = $isRegular ? $state->roundStartScores((int) ($currentQuestion->round_number ?? 1)) : null;

        foreach ($roundQuestions as $slot => $sq) {
            if (!$snapshot) {
                // Fallback: reverse the points from every reveal on this question.
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
            }
            $sq->answerReveals()->delete();

            // Reverse a sweep bonus (manual or auto-awarded); when restoring from a
            // snapshot the points are already covered, but always clear the key so
            // the bonus can be re-earned on replay.
            $bonusKey = "bonus_q{$sq->id}";
            $bonus = $state->getStateValue($bonusKey, null);
            if (!$snapshot && is_array($bonus) && !empty($bonus['team_id'])) {
                $bonusTeam = Team::find($bonus['team_id']);
                if ($bonusTeam) {
                    $bonusTeam->update([
                        'total_score' => max(0, $bonusTeam->total_score - (int) ($bonus['amount'] ?? 0)),
                    ]);
                }
            }
            $state->setStateValue($bonusKey, null);

            $update = ['status' => 'pending'];
            if ($isRegular) {
                // Restore the rotation primary and forget the stashed pre-steal primary.
                $primary = $teamsOrdered[($slot + $roundIndex) % $teamCount] ?? $teamsOrdered->first();
                $update['controlling_team_id'] = $primary?->id;
                $update['controlling_team_ids'] = null;
                $update['control_status'] = 'team_control';
                $state->setStateValue("primary_q{$sq->id}", null);
            }
            $sq->update($update);
        }

        // Snapshot restore: set each team's score straight back to the round start.
        if ($snapshot) {
            foreach ($teamsOrdered as $team) {
                if (array_key_exists($team->id, $snapshot)) {
                    $team->update(['total_score' => (int) $snapshot[$team->id]]);
                }
            }
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

            if ($segment === 'final') {
                // Replay the whole final from its intro, clearing the skip/result
                // and restoring the full time budget.
                $state->update(['timer_started_at' => null, 'timer_duration' => (int) $gameSession->getConfig('final_round_seconds', 60)]);
                $stateData = $state->state_data ?? [];
                $stateData['phase'] = 'final_intro';
                $stateData['final_skip_used'] = false;
                $stateData['final_skipped_question_id'] = null;
                $stateData['final_result'] = null;
                $state->update(['state_data' => $stateData]);
            } elseif ($segment === 'tiebreaker') {
                // Replay the tie-off from its intro; keep the tied teams so the
                // host can re-run and re-declare the winner.
                $state->setStateValue('phase', 'tiebreaker_intro');
            } else {
                // Replay from the round intro (question hidden until "Show Question").
                $state->setStateValue('phase', 'intro');
            }
        }

        return response()->json(['success' => true]);
    }

    public function revealAnswer(Request $request, GameSession $gameSession)
    {
        $validated = $request->validate([
            'answer_id' => 'required|exists:answers,id',
            'team_id' => 'nullable|exists:teams,id',
            // When false, reveal the answer on the board but award no points and
            // attach it to no team — used to show answers that were never guessed
            // once a round is over.
            'award_points' => 'nullable|boolean',
        ]);

        $state = $gameSession->gameState;
        $currentQuestion = $state->currentQuestion;

        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        // The final round is pass/fail — reveals award no points and drive the
        // guided final flow (auto-pause + advance when a question is cleared).
        if (($currentQuestion->segment ?? 'main') === 'final') {
            return $this->revealFinalAnswer($state, $currentQuestion, $validated['answer_id']);
        }

        if (($currentQuestion->segment ?? 'main') === 'tiebreaker') {
            return $this->revealTiebreakerAnswer($state, $currentQuestion, $validated['answer_id']);
        }

        // Check if already revealed
        if ($currentQuestion->answerReveals()->where('answer_id', $validated['answer_id'])->exists()) {
            return response()->json(['error' => 'Answer already revealed'], 400);
        }

        $answer = $currentQuestion->question->answers()->find($validated['answer_id']);

        // Reveal-only (no points): show the answer but credit no team. The host
        // uses this to reveal answers neither team ever said.
        $awardPoints = $validated['award_points'] ?? true;
        if (!$awardPoints) {
            $currentQuestion->answerReveals()->create([
                'answer_id' => $validated['answer_id'],
                'team_id' => null,
                'revealed_at' => now(),
                'points_awarded' => 0,
            ]);
            $answer->recordReveal();

            return response()->json(['success' => true, 'points' => 0]);
        }

        // Per-answer value comes from the round (points_available). Fall back to
        // the stored answer points for older sessions initialized before rounds.
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

        // America Says regular round: the moment the last answer goes up, the board
        // is done — auto-advance to the scoreboard. If the PRIMARY cleared it during
        // their own timer (phase "question"), that's a sweep, so award the round's
        // sweep bonus to them. A board cleared during the steal earns no bonus.
        $this->maybeCompleteAmericaSaysBoard($state, $currentQuestion, $teamId);

        return response()->json([
            'success' => true,
            'points' => $points,
        ]);
    }

    /**
     * If every answer on an America Says regular-round board is now revealed, mark
     * it complete and drop the phase to "recap". A primary-phase sweep also banks
     * the configured sweep bonus for the team that cleared it (reusing the manual
     * bonus's state key so Reset Round still reverses it).
     */
    protected function maybeCompleteAmericaSaysBoard(GameState $state, SessionQuestion $question, ?int $teamId): void
    {
        if (($question->segment ?? 'main') !== 'main') {
            return;
        }

        $answerCount = $question->question->answers()->count();
        if ($answerCount === 0 || $question->answerReveals()->count() < $answerCount) {
            return; // Board not fully revealed yet.
        }

        $phase = $state->getStateValue('phase', 'question');
        if ($phase === 'question' && (int) $question->bonus_points > 0 && $teamId) {
            $bonusKey = "bonus_q{$question->id}";
            if ($state->getStateValue($bonusKey, null) === null) {
                $team = Team::find($teamId);
                if ($team) {
                    $team->addScore((int) $question->bonus_points);
                    $state->setStateValue($bonusKey, ['team_id' => $team->id, 'amount' => (int) $question->bonus_points]);
                }
            }
        }

        $question->update(['status' => 'completed']);
        $state->update(['timer_started_at' => null]);
        $state->setStateValue('phase', 'recap');
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

        // Un-revealing an answer on a question that was marked complete (the final
        // round marks a question done once every answer is shown) drops it back
        // below "all revealed", so it's no longer complete — clear the status so
        // its checkmark goes away.
        if ($currentQuestion->status === 'completed'
            && $currentQuestion->answerReveals()->count() < $currentQuestion->question->answers()->count()) {
            $currentQuestion->update(['status' => 'active']);
        }

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
        // Round snapshots exist only to power Reset Round mid-game; drop them now.
        $gameSession->gameState?->clearRoundScores();
        $gameSession->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Record who was present for this game (household roster players). Works at
     * any status so attendance can be fixed after the fact, not only at setup.
     */
    public function setAttendance(Request $request, GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'player_ids' => 'present|array',
            'player_ids.*' => 'integer|exists:players,id',
        ]);

        $gameSession->players()->sync($validated['player_ids']);

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
            // Crossing from regular play into the America Says final round: only
            // the team leading after the last regular round plays it, against a
            // single time budget. Set up the final and land on its intro beat.
            $enteringFinal = ($nextQuestion->segment ?? 'main') === 'final'
                && ($currentQuestion?->segment ?? 'main') !== 'final';
            if ($enteringFinal) {
                // Who earned the final round? The top scorer — unless there's a
                // tie for the lead, in which case a one-answer tiebreaker decides
                // who plays it first (the host declares the winner of the tie-off).
                $teams = $gameSession->teams()
                    ->orderByDesc('total_score')
                    ->orderBy('display_order')
                    ->get();
                $topScore = (int) ($teams->first()->total_score ?? 0);
                $tied = $teams->filter(fn ($t) => (int) $t->total_score === $topScore)->values();

                if ($tied->count() > 1) {
                    return $this->startTiebreaker($gameSession, $state, $tied->pluck('id')->all());
                }

                return $this->enterFinalRound($gameSession, $state, $teams->first());
            }

            $newRound = $nextQuestion->round_number ?? $state->round_number;
            // Every question opens on its "Get Ready" intro beat — the question and
            // answers stay hidden until the host hits "Show Question", then "Start
            // Timer". This keeps the intro on the 2nd+ question of a round too, and
            // avoids the brief answer-board flash on a same-round transition.
            $stateData['phase'] = 'intro';

            $state->update([
                'current_question_id' => $nextQuestion->id,
                'round_number' => $newRound,
                // Make the next board's primary (stamped at init by the rotation)
                // the active team, so its reveals score for the right side and the
                // "who's up" flip-flop happens without the host assigning control.
                'active_team_id' => $nextQuestion->controlling_team_id ?? $state->active_team_id,
                'state_data' => $stateData,
            ]);
            $nextQuestion->update(['status' => 'active']);

            // Snapshot this round's starting scores (the first board of a new round
            // is the first time we see it; later boards no-op) so Reset Round can
            // restore them exactly. Regular rounds only — final/tiebreaker excluded.
            if (($nextQuestion->segment ?? 'main') === 'main') {
                $state->snapshotRoundScoresIfAbsent(
                    (int) $newRound,
                    $gameSession->teams->mapWithKeys(fn ($t) => [$t->id => (int) $t->total_score])->all(),
                );
            }

            return response()->json(['success' => true, 'game_complete' => false, 'entering_final' => false]);
        }

        // No more questions - game is complete. Drop the round snapshots we no
        // longer need so the state row doesn't carry them forever.
        $state->clearRoundScores();
        $gameSession->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true, 'game_complete' => true]);
    }

    /**
     * Set up the America Says final round for a given team: point at the first
     * pending final question, land on its intro ("Get Ready") beat, and reset the
     * final's per-run flags. Shared by the normal (sole leader) path and the
     * tiebreaker resolution, so both enter the final identically.
     */
    protected function enterFinalRound(GameSession $gameSession, GameState $state, ?Team $finalTeam)
    {
        $nextQuestion = $gameSession->sessionQuestions()
            ->where('segment', 'final')
            ->where('status', 'pending')
            ->orderBy('display_order')
            ->first();

        if (!$nextQuestion) {
            // No final questions were materialized — nothing to play, so the game
            // is over (the leading/declared team is the winner by score).
            $gameSession->update(['status' => 'completed', 'completed_at' => now()]);

            return response()->json(['success' => true, 'game_complete' => true]);
        }

        $finalSeconds = (int) $gameSession->getConfig('final_round_seconds', 60);

        $stateData = $state->state_data ?? [];
        $stateData['phase'] = 'final_intro';
        $stateData['final_team_id'] = $finalTeam?->id;
        $stateData['final_skip_used'] = false;
        $stateData['final_skipped_question_id'] = null;
        $stateData['final_result'] = null;
        unset($stateData['tiebreaker_team_ids'], $stateData['tiebreaker_winner_id']);

        $state->update([
            'current_question_id' => $nextQuestion->id,
            'timer_started_at' => null,
            'timer_duration' => $finalSeconds,
            'active_team_id' => $finalTeam?->id,
            'state_data' => $stateData,
        ]);
        $nextQuestion->update(['status' => 'active']);

        return response()->json(['success' => true, 'game_complete' => false, 'entering_final' => true]);
    }

    /**
     * Round 3 ended in a tie for the lead. Queue a one-answer tie-off question
     * (the board looks just like Final question 1) and land on its intro so the
     * host can read the rules of the tie-off. The host then reveals the answer if
     * needed and declares which tied team won (tiebreakerResolve), and that team
     * plays the final. If no single-answer question exists, fall back to the first
     * tied team (by display order) so the game can still proceed.
     */
    protected function startTiebreaker(GameSession $gameSession, GameState $state, array $tiedTeamIds)
    {
        $question = $this->pickTiebreakerQuestion($gameSession);

        if (!$question) {
            $fallback = $gameSession->teams()
                ->whereIn('id', $tiedTeamIds)
                ->orderBy('display_order')
                ->first();

            return $this->enterFinalRound($gameSession, $state, $fallback);
        }

        $order = (int) $gameSession->sessionQuestions()->max('display_order') + 1;
        $tieQuestion = SessionQuestion::create([
            'game_session_id' => $gameSession->id,
            'question_id' => $question->id,
            'display_order' => $order,
            'status' => 'active',
            'segment' => 'tiebreaker',
            'answers_needed' => 1,
        ]);

        $stateData = $state->state_data ?? [];
        $stateData['phase'] = 'tiebreaker_intro';
        $stateData['tiebreaker_team_ids'] = array_values($tiedTeamIds);
        $stateData['final_team_id'] = null;

        $state->update([
            'current_question_id' => $tieQuestion->id,
            'timer_started_at' => null,
            'timer_duration' => 0,
            'active_team_id' => null,
            'state_data' => $stateData,
        ]);

        return response()->json(['success' => true, 'game_complete' => false, 'tiebreaker' => true]);
    }

    /**
     * Pick a random single-answer question for the tie-off — prefer a Final-type
     * one (so the board matches Final question 1), else any single-answer question.
     * Excludes questions already used this session (and any explicitly excluded,
     * e.g. the one being swapped out).
     */
    protected function pickTiebreakerQuestion(GameSession $gameSession, array $excludeIds = [])
    {
        $usedIds = array_merge(
            $gameSession->sessionQuestions()->pluck('question_id')->all(),
            $excludeIds
        );

        $base = fn () => \App\Models\Question::where('game_type_id', $gameSession->game_type_id)
            ->where('is_active', true)
            ->whereNotIn('id', $usedIds)
            ->has('answers', '=', 1);

        return $base()->where('round_type', 'final')->orderBy('times_used')->inRandomOrder()->first()
            ?? $base()->orderBy('times_used')->inRandomOrder()->first();
    }

    /**
     * Tie-off — show the question plaque on the board (answers hidden) so the host
     * can read it. Moves "tiebreaker_intro" → "tiebreaker_question".
     */
    public function tiebreakerShow(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $gameSession->gameState?->setStateValue('phase', 'tiebreaker_question');

        return response()->json(['success' => true]);
    }

    /**
     * Tie-off — reveal the (blank) answer board, like a regular round's Start
     * Timer. Moves "tiebreaker_question" (question plaque only) → "tiebreaker_play"
     * (the board of blank answer slots). Does NOT reveal the answer text.
     */
    public function tiebreakerRevealBoard(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $gameSession->gameState?->setStateValue('phase', 'tiebreaker_play');

        return response()->json(['success' => true]);
    }

    /**
     * Tie-off — advance from "answer revealed" to the declare-winner step. The
     * board doesn't change (answer stays up); this just moves the host's guided
     * step from "Answer Revealed" to "Declare Winner". Mirrors "Show Scores".
     */
    public function tiebreakerToDeclare(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $gameSession->gameState?->setStateValue('phase', 'tiebreaker_declare');

        return response()->json(['success' => true]);
    }

    /**
     * Tie-off — swap the queued question for a different random single-answer one
     * (the host didn't like the draw). Re-hides the answer (back to the plaque).
     */
    public function tiebreakerSwap(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $tieQuestion = $state?->currentQuestion;

        if (!$tieQuestion || ($tieQuestion->segment ?? '') !== 'tiebreaker') {
            return response()->json(['error' => 'No active tiebreaker question'], 400);
        }

        $replacement = $this->pickTiebreakerQuestion($gameSession, [$tieQuestion->question_id]);
        if (!$replacement) {
            return response()->json(['error' => 'No other question available to swap in'], 400);
        }

        $tieQuestion->answerReveals()->delete();
        $tieQuestion->update(['question_id' => $replacement->id]);

        // Re-hide the answer: keep the plaque up if the host was mid tie-off,
        // otherwise stay on the intro.
        if ($state->getStateValue('phase') === 'tiebreaker_play') {
            $state->setStateValue('phase', 'tiebreaker_question');
        }

        return response()->json(['success' => true]);
    }

    /**
     * Tie-off — the host declares which tied team won the buzz-off. This does NOT
     * jump straight into the final: it lands on a "tiebreaker_result" beat so the
     * display can crown the winner ("Team X Wins! — On to the Final Round"), just
     * like the regular round's recap. The host then presses "Start Final Round"
     * (tiebreakerToFinal) to actually begin it. The tiebreaker question is done.
     */
    public function tiebreakerResolve(Request $request, GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        $state = $gameSession->gameState;
        $tiedIds = $state?->getStateValue('tiebreaker_team_ids') ?? [];
        if (!in_array($validated['team_id'], $tiedIds)) {
            return response()->json(['error' => 'That team is not part of the tiebreaker'], 400);
        }

        $tieQuestion = $state?->currentQuestion;
        if ($tieQuestion && ($tieQuestion->segment ?? '') === 'tiebreaker') {
            $tieQuestion->update(['status' => 'completed']);
        }

        $stateData = $state->state_data ?? [];
        $stateData['phase'] = 'tiebreaker_result';
        $stateData['tiebreaker_winner_id'] = $validated['team_id'];
        $state->update(['state_data' => $stateData]);

        return response()->json(['success' => true]);
    }

    /**
     * Tie-off — begin the final round with the team that won the tie-off. Fired by
     * "Start Final Round" on the winner slide (tiebreaker_result), mirroring the
     * regular recap's "Start Final Round" button.
     */
    public function tiebreakerToFinal(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $winnerId = $state?->getStateValue('tiebreaker_winner_id');
        $winner = $winnerId ? $gameSession->teams()->whereKey($winnerId)->first() : null;

        return $this->enterFinalRound($gameSession, $state, $winner);
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
        // Stepping back shows that question again (guided America Says flow).
        $state->setStateValue('phase', 'question');

        return response()->json(['success' => true]);
    }

    /**
     * America Says guided flow — reveal the loaded question on the board.
     * Moves the phase from "intro" (Round N — Get Ready) to "question" so the
     * plaque + blank board appear; the timer stays idle until startTimer.
     */
    public function showQuestion(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $gameSession->gameState?->setStateValue('phase', 'question');

        return response()->json(['success' => true]);
    }

    /**
     * America Says guided flow — step back to the round intro ("Get Ready"), e.g.
     * the host clicked the Round Intro step to re-read the setup. Non-destructive:
     * reveals/points are untouched; only the board display returns to the intro.
     */
    public function roundIntro(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $gameSession->gameState?->setStateValue('phase', 'intro');

        return response()->json(['success' => true]);
    }

    /**
     * America Says — sound the "wrong answer" buzzer on the display. A wrong
     * guess has no board state to change (it simply isn't on the board), so we
     * bump a monotonic counter the display watches, playing its incorrect sound
     * once each time it advances.
     */
    public function buzzWrong(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        if ($state) {
            $current = (int) $state->getStateValue('wrong_buzz', 0);
            $state->setStateValue('wrong_buzz', $current + 1);
        }

        return response()->json(['success' => true]);
    }

    /**
     * America Says guided flow — end the current round and show the scoreboard.
     * Sets the phase to "recap"; the host then advances with nextQuestion
     * ("Start Round N+1"), which moves to the next round's intro.
     */
    public function endRound(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $gameSession->gameState?->setStateValue('phase', 'recap');

        return response()->json(['success' => true]);
    }

    /**
     * America Says regular round — hand the board to the OTHER team for the steal.
     * Fired automatically when the primary team's timer runs out (or manually by
     * the host). The steal is untimed: the stealing team keeps guessing while
     * correct; the first wrong guess (stealWrong) ends the board. Revealed answers
     * now score for the stealing team, since it becomes the active/controlling team.
     */
    public function stealStart(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;
        if (!$currentQuestion || ($currentQuestion->segment ?? 'main') !== 'main') {
            return response()->json(['error' => 'No active regular-round question'], 400);
        }

        // The stealing team is the one that isn't primary (2-team game).
        $primaryId = $currentQuestion->controlling_team_id ?? $state->active_team_id;
        $stealTeam = $gameSession->teams()->where('id', '!=', $primaryId)->orderBy('display_order')->first();

        if ($stealTeam) {
            // Remember who was primary so the display can label the board and a
            // manual correction can hand control back.
            $state->setStateValue("primary_q{$currentQuestion->id}", $primaryId);

            $currentQuestion->update([
                'controlling_team_id' => $stealTeam->id,
                'controlling_team_ids' => null,
                'control_status' => 'team_control',
            ]);
            // Stop the clock (the steal is untimed) and make the stealer active so
            // their reveals score.
            $state->update(['active_team_id' => $stealTeam->id, 'timer_started_at' => null]);
        }

        $state->setStateValue('phase', 'steal');

        return response()->json(['success' => true]);
    }

    /**
     * America Says regular round — the stealing team guessed wrong, so the board is
     * over: reveal whatever's still hidden for no points, sound the buzzer, and
     * drop to the scoreboard.
     */
    public function stealWrong(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;
        if (!$currentQuestion) {
            return response()->json(['error' => 'No active question'], 400);
        }

        $state->setStateValue('wrong_buzz', (int) $state->getStateValue('wrong_buzz', 0) + 1);
        $this->revealRemainingWithoutPoints($currentQuestion);
        $currentQuestion->update(['status' => 'completed']);
        $state->setStateValue('phase', 'recap');

        return response()->json(['success' => true]);
    }

    /**
     * Reveal every not-yet-revealed answer on a question for zero points and no
     * team — the "show the ones nobody got" board flip at the end of a round.
     */
    protected function revealRemainingWithoutPoints(SessionQuestion $question): void
    {
        $revealedIds = $question->answerReveals()->pluck('answer_id')->all();
        $answers = $question->question->answers()->whereNotIn('id', $revealedIds)->get();

        foreach ($answers as $answer) {
            $question->answerReveals()->create([
                'answer_id' => $answer->id,
                'team_id' => null,
                'revealed_at' => now(),
                'points_awarded' => 0,
            ]);
            $answer->recordReveal();
        }
    }

    // ---- America Says final round ---------------------------------------------
    // A single time budget (default 60s) covers all final questions. Each question
    // mirrors a regular round: the plaque is shown first (host reads it) with the
    // clock idle, then the host reveals the answers and the clock runs. The clock
    // banks its remaining time between questions — it auto-pauses when a question
    // is cleared (see revealFinalAnswer), the board keeps the revealed answers up,
    // and the host moves on when ready. The leading team plays for a pass/fail win.
    //
    // Phases: final_intro (Get Ready) → final_question (plaque only, clock idle) →
    // final_play (answers + clock) → final_cleared (revealed board stays, clock
    // banked) → back to final_question for the next one; final_review on timeout;
    // final_result at the end.

    /**
     * Show the current final question's plaque on the board (answers still hidden,
     * clock idle) so the host can read it aloud. Moves "final_intro" (or a jumped
     * state) to "final_question"; the host then hits Start to reveal + run the clock.
     */
    public function finalShowQuestion(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $gameSession->gameState?->setStateValue('phase', 'final_question');

        return response()->json(['success' => true]);
    }

    /**
     * Reveal the answer board and (re)start the clock for the shown final question.
     * Moves "final_question" → "final_play". The time budget was set when the final
     * began (or banked between questions), so this just resumes it from where it
     * stands — full budget on the first question, banked remainder thereafter.
     */
    public function finalStart(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        // Reveal the board now but start the clock ~1s later, so casting latency
        // doesn't eat the opening seconds (see startTimer).
        $state->update(['timer_started_at' => now()->addSecond()]);
        $state->setStateValue('phase', 'final_play');

        return response()->json(['success' => true]);
    }

    /**
     * Advance to the next final question after one is cleared: point at it and show
     * its plaque only (clock stays banked). Moves "final_cleared" → "final_question".
     */
    public function finalNext(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $next = $this->nextFinalQuestion($gameSession, $state);

        if (!$next) {
            // Nothing left — every question was cleared in time, so it's a win.
            $stateData = $state->state_data ?? [];
            $stateData['phase'] = 'final_result';
            $stateData['final_result'] = 'win';
            $state->update(['state_data' => $stateData]);

            return response()->json(['success' => true, 'complete' => true]);
        }

        // Land on a per-question "Get Ready" beat (like a regular round's intro):
        // the host presses Show Question to bring up the plaque, then reveals to
        // start the clock. Keeps every final question from jumping straight in.
        $stateData = $state->state_data ?? [];
        $stateData['phase'] = 'final_ready';
        $state->update(['current_question_id' => $next->id, 'state_data' => $stateData]);

        return response()->json(['success' => true, 'complete' => false]);
    }

    /**
     * Use the one allowed skip: bank the clock, park the current question for a
     * later revisit, and jump to the next unanswered question, showing its plaque
     * only so the host reads it before revealing ("final_question").
     */
    public function finalSkip(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;

        if (!$currentQuestion || ($currentQuestion->segment ?? 'main') !== 'final') {
            return response()->json(['error' => 'No active final question'], 400);
        }
        if ($state->getStateValue('final_skip_used')) {
            return response()->json(['error' => 'Skip already used'], 400);
        }

        // There must be another unanswered question to move to.
        $otherPending = $gameSession->sessionQuestions()
            ->where('segment', 'final')
            ->where('status', '!=', 'completed')
            ->where('id', '!=', $currentQuestion->id)
            ->orderBy('display_order')
            ->first();
        if (!$otherPending) {
            return response()->json(['error' => 'Nothing left to skip to'], 400);
        }

        // Bank the remaining time and record the skip.
        $state->update([
            'timer_started_at' => null,
            'timer_duration' => $state->getRemainingSeconds(),
            'current_question_id' => $otherPending->id,
        ]);
        $stateData = $state->state_data ?? [];
        $stateData['final_skip_used'] = true;
        $stateData['final_skipped_question_id'] = $currentQuestion->id;
        $stateData['phase'] = 'final_ready';
        $state->update(['state_data' => $stateData]);

        return response()->json(['success' => true]);
    }

    /**
     * The clock ran out before every answer was revealed — the team loses. Drops
     * into "review" mode on whatever question they were on: no clock, and the host
     * can reveal the missed answers one at a time and jump between the final
     * questions (finalSelect). The display shows a brief "Out of Time" flash first.
     */
    public function finalTimeout(GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $state = $gameSession->gameState;
        $state->update(['timer_started_at' => null, 'timer_duration' => 0]);

        $stateData = $state->state_data ?? [];
        $stateData['phase'] = 'final_review';
        $stateData['final_result'] = 'lose';
        $state->update(['state_data' => $stateData]);

        return response()->json(['success' => true]);
    }

    /**
     * Review mode — jump to a specific final question so the host can reveal its
     * answers. Only used once the clock has stopped (final_review).
     */
    public function finalSelect(Request $request, GameSession $gameSession)
    {
        if ($gameSession->host_user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'session_question_id' => 'required|exists:session_questions,id',
        ]);

        $sessionQuestion = $gameSession->sessionQuestions()
            ->where('id', $validated['session_question_id'])
            ->where('segment', 'final')
            ->first();

        if (!$sessionQuestion) {
            return response()->json(['error' => 'Not a final question in this game'], 404);
        }

        $gameSession->gameState->update(['current_question_id' => $sessionQuestion->id]);

        return response()->json(['success' => true]);
    }

    /**
     * Reveal one answer during the final round. No points are awarded; when the
     * question's last answer is revealed the clock auto-pauses (banking its time)
     * and the flow advances to the next question, or to a "win" if none remain.
     */
    /**
     * Reveal the tie-off answer on the board (no points, no team) and move to the
     * "tiebreaker_play" beat so the display shows it. Used when the host reveals
     * the answer to check the tied teams' guesses before declaring a winner.
     */
    protected function revealTiebreakerAnswer(GameState $state, SessionQuestion $currentQuestion, int $answerId)
    {
        if (!$currentQuestion->answerReveals()->where('answer_id', $answerId)->exists()) {
            $answer = $currentQuestion->question->answers()->find($answerId);
            if (!$answer) {
                return response()->json(['error' => 'Invalid answer'], 400);
            }
            $currentQuestion->answerReveals()->create([
                'answer_id' => $answerId,
                'team_id' => null,
                'revealed_at' => now(),
                'points_awarded' => 0,
            ]);
            $answer->recordReveal();
        }

        $state->setStateValue('phase', 'tiebreaker_play');

        return response()->json(['success' => true, 'points' => 0]);
    }

    protected function revealFinalAnswer(GameState $state, SessionQuestion $currentQuestion, int $answerId)
    {
        if ($currentQuestion->answerReveals()->where('answer_id', $answerId)->exists()) {
            return response()->json(['error' => 'Answer already revealed'], 400);
        }

        $answer = $currentQuestion->question->answers()->find($answerId);
        if (!$answer) {
            return response()->json(['error' => 'Invalid answer'], 400);
        }

        $currentQuestion->answerReveals()->create([
            'answer_id' => $answerId,
            'team_id' => null,
            'revealed_at' => now(),
            'points_awarded' => 0,
        ]);
        $answer->recordReveal();

        // In review mode (post-timeout) the host is just showing the missed
        // answers — no clock, no auto-advance. Mark the question done once every
        // answer is up, but stay on it so the host controls the navigation.
        if ($state->getStateValue('phase') !== 'final_play') {
            if ($currentQuestion->answerReveals()->count() >= $currentQuestion->question->answers()->count()) {
                $currentQuestion->update(['status' => 'completed']);
            }
            return response()->json(['success' => true, 'points' => 0]);
        }

        // Not all answers in yet — keep playing.
        $total = $currentQuestion->question->answers()->count();
        if ($currentQuestion->answerReveals()->count() < $total) {
            return response()->json(['success' => true, 'points' => 0]);
        }

        // Question cleared: mark it done, then decide where to go next.
        $currentQuestion->update(['status' => 'completed']);

        $next = $this->nextFinalQuestion($state->gameSession, $state);
        $stateData = $state->state_data ?? [];

        // Clearing a board ALWAYS pauses (banks) the clock — the team then gets to
        // re-read the next question before it resumes. This holds for the parked
        // (skipped) question too: looping back to it doesn't keep the clock running
        // or drop straight in; it goes through Get Ready like every other question.
        $state->update([
            'timer_started_at' => null,
            'timer_duration' => $state->getRemainingSeconds(),
        ]);

        if ($next) {
            // Stay on the just-cleared question so its revealed answers remain on
            // the board (no "Get Ready" flash). The host advances with finalNext
            // when ready, which lands on the next question's Get Ready beat.
            $stateData['phase'] = 'final_cleared';
            $state->update(['state_data' => $stateData]);
        } else {
            // Every final question cleared before time ran out — the team wins.
            $stateData['phase'] = 'final_result';
            $stateData['final_result'] = 'win';
            $state->update(['state_data' => $stateData]);
        }

        return response()->json(['success' => true, 'points' => 0]);
    }

    /**
     * The next final question to play: the first unanswered one in order,
     * skipping the parked question until every other question is done, then
     * serving the parked one last (the revisit). Null when the final is complete.
     */
    protected function nextFinalQuestion(GameSession $gameSession, GameState $state): ?SessionQuestion
    {
        $pending = $gameSession->sessionQuestions()
            ->where('segment', 'final')
            ->where('status', '!=', 'completed')
            ->orderBy('display_order')
            ->get();

        if ($pending->isEmpty()) {
            return null;
        }

        $skippedId = $state->getStateValue('final_skipped_question_id');
        $next = $pending->first(fn ($q) => $q->id !== $skippedId);

        // Only the parked question remains — bring it back for the revisit.
        return $next ?? $pending->first();
    }
}
