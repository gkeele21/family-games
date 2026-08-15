<?php

namespace App\Http\Controllers\Concerns;

use App\Models\GameSession;
use App\Models\GameState;

/**
 * Builds the Family Feud Fast Money board (rows + running totals) from the state
 * stored under state_data.fast_money. Shared by the host controls and the TV
 * display so both read an identical board; $forHost also attaches each question's
 * survey answers so the host can reveal what the player said.
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
        $p1Total = 0;
        $p2Total = 0;

        $cell = function ($entry) {
            if (!is_array($entry)) {
                return ['revealed' => false];
            }
            return [
                'revealed' => true,
                'text' => $entry['text'] ?? null,
                'points' => (int) ($entry['points'] ?? 0),
                'duplicate' => (bool) ($entry['duplicate'] ?? false),
            ];
        };

        $rows = $questions->map(function ($sq) use ($stored, $cell, $forHost, &$p1Total, &$p2Total) {
            $a = $stored[(string) $sq->id] ?? [];
            $p1 = $a['1'] ?? null;
            $p2 = $a['2'] ?? null;
            $p1Total += (int) ($p1['points'] ?? 0);
            $p2Total += (int) ($p2['points'] ?? 0);

            $row = [
                'id' => $sq->id,
                'question' => $sq->question->question_text,
                'p1' => $cell($p1),
                'p2' => $cell($p2),
            ];
            if ($forHost) {
                // Survey answers (ranked) so the host can reveal what the player said.
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
            'p1_total' => $p1Total,
            'p2_total' => $p2Total,
            'combined_total' => $p1Total + $p2Total,
            'result' => $fm['result'] ?? null,
            'timer1_buzz' => (int) ($fm['timer1_buzz'] ?? 0),
            'timer2_buzz' => (int) ($fm['timer2_buzz'] ?? 0),
            'rows' => $rows,
        ];
    }
}
