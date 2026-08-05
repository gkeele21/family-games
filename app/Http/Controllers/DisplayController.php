<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use Inertia\Inertia;
use Inertia\Response;

class DisplayController extends Controller
{
    /**
     * Show the display view for a game session.
     * Accessed via invite code so anyone with the link can view.
     */
    public function show(string $inviteCode): Response
    {
        $gameSession = GameSession::where('invite_code', strtoupper($inviteCode))
            ->whereIn('status', ['lobby', 'playing', 'paused', 'completed'])
            ->first();

        if (!$gameSession) {
            abort(404, 'Game not found.');
        }

        $gameSession->load([
            'gameType',
            'teams.members',
            'gameState.activeTeam',
            'gameState.currentCard',
        ]);

        return Inertia::render('Display/Game', [
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
            'teams' => $gameSession->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'color' => $team->color,
                'total_score' => $team->total_score,
                'display_order' => $team->display_order,
            ]),
        ]);
    }

    /**
     * Get the current game state for polling.
     */
    public function getState(string $inviteCode)
    {
        $gameSession = GameSession::where('invite_code', strtoupper($inviteCode))
            ->whereIn('status', ['lobby', 'playing', 'paused', 'completed'])
            ->first();

        if (!$gameSession) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        $gameSession->load([
            'teams',
            'gameState.currentQuestion.question.answers',
            'gameState.currentQuestion.answerReveals',
            'gameState.currentCard.bonusQuestion',
            'gameState.activeTeam',
        ]);

        $state = $gameSession->gameState;
        $currentQuestion = $state?->currentQuestion;

        // For display, we hide unrevealed answers (same as player view)
        $answers = [];
        if ($currentQuestion) {
            $revealedIds = $currentQuestion->revealedAnswerIds();
            $answers = $currentQuestion->question->answers->map(function ($answer) use ($revealedIds) {
                $revealed = in_array($answer->id, $revealedIds);
                return [
                    'id' => $answer->id,
                    'answer_text' => $revealed ? $answer->answer_text : $this->obfuscateAnswer($answer->answer_text),
                    'points' => $revealed ? $answer->points : null,
                    'display_order' => $answer->display_order,
                    'revealed' => $revealed,
                ];
            });
        }

        // Whether the just-played question is the last one in its round (every team
        // has now had their turn) — so the scores recap reads "End of Round N"
        // rather than mid-round running scores after only the first team's turn.
        $endOfRound = false;
        if ($currentQuestion && ($currentQuestion->segment ?? 'main') === 'main') {
            $endOfRound = !$gameSession->sessionQuestions()
                ->where('round_number', $currentQuestion->round_number)
                ->where('display_order', '>', $currentQuestion->display_order)
                ->exists();
        }

        // Whether the last regular round just ended and an America Says final is
        // next — the recap board celebrates the leading team before the final.
        $finalQueued = false;
        if (in_array($gameSession->gameType->slug, ['america-says', 'family-feud'], true)
            && ($currentQuestion?->segment ?? 'main') !== 'final') {
            $pending = $gameSession->sessionQuestions()->where('status', 'pending');
            $finalQueued = (clone $pending)->where('segment', 'final')->exists()
                && !(clone $pending)->where('segment', '!=', 'final')->exists();
        }

        // Get controlling team IDs from state_data if multiple teams
        $controllingTeamIds = [];
        if ($currentQuestion) {
            if ($currentQuestion->controlling_team_id) {
                $controllingTeamIds = [$currentQuestion->controlling_team_id];
            }
            $stateData = $state?->state_data ?? [];
            if (!empty($stateData['next_controlling_team_ids'])) {
                $controllingTeamIds = $stateData['next_controlling_team_ids'];
            }
        }

        return response()->json([
            'status' => $gameSession->status,
            'teams' => $gameSession->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'color' => $team->color,
                'total_score' => $team->total_score,
                'display_order' => $team->display_order,
            ]),
            'gameState' => [
                'round_number' => $state?->round_number,
                'active_team_id' => $state?->active_team_id,
                'active_team_name' => $state?->activeTeam?->name,
                'timer_started_at' => $state?->timer_started_at?->toIso8601String(),
                'timer_duration' => $state?->timer_duration,
                'remaining_seconds' => $state?->getRemainingSeconds(),
                // Guided America Says flow. Regular: 'intro' | 'question' | 'recap'.
                // Final: 'final_intro' | 'final_question' | 'final_play' |
                // 'final_cleared' | 'final_review' | 'final_result'.
                'phase' => $state?->getStateValue('phase'),
                // Final round: the lone team playing, and the pass/fail outcome.
                'final_team_id' => $state?->getStateValue('final_team_id'),
                'final_result' => $state?->getStateValue('final_result'),
                // True on the last regular round's recap, before the final begins.
                'final_queued' => $finalQueued,
                // True when the recap follows the last question of the round.
                'end_of_round' => $endOfRound,
            ],
            'currentQuestion' => $currentQuestion ? [
                'id' => $currentQuestion->id,
                'question_text' => $currentQuestion->question->question_text,
                'status' => $currentQuestion->status,
                'segment' => $currentQuestion->segment,
                'answers_needed' => $currentQuestion->answers_needed,
                'control_status' => $currentQuestion->control_status,
                'controlling_team_id' => $currentQuestion->controlling_team_id,
                'controlling_team_ids' => $controllingTeamIds,
                'answers' => $answers,
            ] : null,
            'currentCard' => $state?->currentCard ? [
                'id' => $state->currentCard->id,
                'card_number' => $state->currentCard->card_number,
                'letter' => $state->currentCard->letter,
                // "Just for fun" opener — question only; the host reads the
                // answer from their own screen.
                'bonus_question' => $state->currentCard->bonusQuestion ? [
                    'question_text' => $state->currentCard->bonusQuestion->question_text,
                    'answer_text' => null,
                ] : null,
            ] : null,
        ]);
    }

    /**
     * Obfuscate answer text for unrevealed answers.
     * First letter + ~1.5x underscores per hyphen-part; hyphens are kept and
     * words are separated by spaces (e.g. "SHOW-ME" -> "S____-M_"). The board
     * squishes the underscores into a continuous line, so the exact character
     * count stays hidden while hyphens still read correctly.
     */
    private function obfuscateAnswer(string $text): string
    {
        $words = array_map(function ($word) {
            $parts = array_map(function ($part) {
                if (mb_strlen($part) <= 1) {
                    return $part;
                }
                $firstLetter = mb_substr($part, 0, 1);
                $underscoreCount = (int) floor((mb_strlen($part) - 1) * 1.5);
                return $firstLetter . str_repeat('_', $underscoreCount);
            }, explode('-', $word));

            return implode('-', $parts);
        }, explode(' ', $text));

        return implode(' ', $words);
    }
}
