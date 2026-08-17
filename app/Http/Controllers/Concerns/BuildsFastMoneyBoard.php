<?php

namespace App\Http\Controllers\Concerns;

use App\Models\GameSession;
use App\Models\GameState;

/**
 * Builds the Family Feud Fast Money board (rows + running totals) from the state
 * stored under state_data.fast_money. Shared by the host controls and the TV
 * display so both read an identical board.
 *
 * The real-show flow is capture-then-reveal: the host first records what each
 * player said (hidden), then reveals each answer's TEXT and POINTS one at a time.
 * Each cell therefore carries three flags:
 *   - captured — the host recorded the answer (host-only knowledge)
 *   - shown    — the answer text is up on the TV (typewriter reveal)
 *   - scored   — the points are up on the TV and count toward the total
 * The display payload ($forHost = false) never leaks a captured answer before it's
 * shown/scored; $forHost also attaches each question's survey answers so the host
 * can pick what the player said.
 */
trait BuildsFastMoneyBoard
{
    protected function fastMoneyPayload(GameSession $gameSession, ?GameState $state, bool $forHost = false): ?array
    {
        if (!$state) {
            return null;
        }
        $fm = $state->getStateValue('fast_money');
        if (!is_array($fm)) {
            return null;
        }

        $questions = $gameSession->sessionQuestions()
            ->where('segment', 'fast_money')
            ->with('question.answers')
            ->orderBy('display_order')
            ->get();

        $stored = $fm['answers'] ?? [];
        $phase = $state->getStateValue('phase');
        $showPrevious = (bool) ($fm['show_previous'] ?? false);
        // On the TV, Player 1's board is hidden while Player 2 answers (P2 mustn't
        // see it) — until the host flashes it to the room with "Show Player 1".
        $hideP1OnDisplay = !$forHost
            && $phase === 'fast_money_p2_capture'
            && !$showPrevious;

        $p1Total = 0;
        $p2Total = 0;

        // Build one player's cell. Totals accrue SCORED points only, so the TV's
        // TOTAL box builds as the host reveals — matching what the room sees.
        $cell = function ($entry, int $player) use ($forHost, $hideP1OnDisplay) {
            if (!is_array($entry)) {
                return ['captured' => false, 'shown' => false, 'scored' => false, 'text' => null, 'points' => null];
            }
            $shown = (bool) ($entry['shown'] ?? false);
            $scored = (bool) ($entry['scored'] ?? false);
            $hidden = $hideP1OnDisplay && $player === 1;

            if ($forHost) {
                // The host always sees the captured answer + points to announce.
                return [
                    'captured' => true,
                    'shown' => $shown,
                    'scored' => $scored,
                    'answer_id' => $entry['answer_id'] ?? null,
                    'text' => $entry['text'] ?? null,
                    'points' => (int) ($entry['points'] ?? 0),
                ];
            }

            // Display: text only once shown, points only once scored (and nothing at
            // all while Player 1's board is held back during Player 2's capture).
            return [
                'captured' => true,
                'shown' => $shown && !$hidden,
                'scored' => $scored && !$hidden,
                'text' => ($shown && !$hidden) ? ($entry['text'] ?? null) : null,
                'points' => ($scored && !$hidden) ? (int) ($entry['points'] ?? 0) : null,
            ];
        };

        $rows = $questions->map(function ($sq) use ($stored, $cell, $forHost, &$p1Total, &$p2Total) {
            $a = $stored[(string) $sq->id] ?? [];
            $p1entry = $a['1'] ?? null;
            $p2entry = $a['2'] ?? null;

            // Totals build from SCORED points (what's on the TV right now).
            if (is_array($p1entry) && ($p1entry['scored'] ?? false)) {
                $p1Total += (int) ($p1entry['points'] ?? 0);
            }
            if (is_array($p2entry) && ($p2entry['scored'] ?? false)) {
                $p2Total += (int) ($p2entry['points'] ?? 0);
            }

            $row = [
                'id' => $sq->id,
                'question' => $sq->question->question_text,
                'p1' => $cell($p1entry, 1),
                'p2' => $cell($p2entry, 2),
            ];
            if ($forHost) {
                // Survey answers (ranked) so the host can pick what the player said.
                $row['answers'] = $sq->question->answers
                    ->sortBy('display_order')
                    ->map(fn ($ans) => [
                        'id' => $ans->id,
                        'text' => $ans->answer_text,
                        'points' => (int) ($ans->points ?? 0),
                    ])->values();
            }

            return $row;
        })->values();

        return [
            'target' => (int) ($fm['target'] ?? 200),
            'active_player' => (int) ($fm['active_player'] ?? 1),
            'show_previous' => $showPrevious,
            'p1_total' => $p1Total,
            'p2_total' => $p2Total,
            'combined_total' => $p1Total + $p2Total,
            'result' => $fm['result'] ?? null,
            'timer1_buzz' => (int) ($fm['timer1_buzz'] ?? 0),
            'timer2_buzz' => (int) ($fm['timer2_buzz'] ?? 0),
            // Bumped when the host taps an answer Player 1 already used (a duplicate)
            // — the display sounds the buzzer so the player guesses again.
            'duplicate_buzz' => (int) ($fm['duplicate_buzz'] ?? 0),
            'rows' => $rows,
        ];
    }
}
