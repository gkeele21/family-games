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
            'gameState.currentCard',
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
            ]),
            'gameState' => [
                'round_number' => $state?->round_number,
                'active_team_id' => $state?->active_team_id,
                'active_team_name' => $state?->activeTeam?->name,
                'timer_started_at' => $state?->timer_started_at?->toIso8601String(),
                'timer_duration' => $state?->timer_duration,
                'remaining_seconds' => $state?->getRemainingSeconds(),
            ],
            'currentQuestion' => $currentQuestion ? [
                'id' => $currentQuestion->id,
                'question_text' => $currentQuestion->question->question_text,
                'status' => $currentQuestion->status,
                'control_status' => $currentQuestion->control_status,
                'controlling_team_id' => $currentQuestion->controlling_team_id,
                'controlling_team_ids' => $controllingTeamIds,
                'answers' => $answers,
            ] : null,
            'currentCard' => $state?->currentCard ? [
                'id' => $state->currentCard->id,
                'card_number' => $state->currentCard->card_number,
                'letter' => $state->currentCard->letter,
            ] : null,
        ]);
    }

    /**
     * Obfuscate answer text for unrevealed answers.
     * Shows first letter + underscores.
     */
    private function obfuscateAnswer(string $text): string
    {
        $words = explode(' ', $text);
        $obfuscated = array_map(function ($word) {
            if (strlen($word) <= 1) {
                return $word;
            }
            $firstLetter = mb_substr($word, 0, 1);
            $underscoreCount = (int) floor((mb_strlen($word) - 1) * 1.5);
            return $firstLetter . str_repeat('_', $underscoreCount);
        }, $words);

        return implode(' ', $obfuscated);
    }
}
