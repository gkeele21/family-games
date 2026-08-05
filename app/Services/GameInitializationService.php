<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Question;
use App\Models\SessionCard;
use App\Models\SessionQuestion;

class GameInitializationService
{
    public function initialize(GameSession $gameSession): void
    {
        $gameType = $gameSession->gameType;

        // Make (re)starting idempotent: clear any questions/cards materialized by
        // a prior start so we never accumulate duplicates. Null the game-state
        // pointers first so the deletes don't trip the foreign keys.
        $gameSession->gameState?->update([
            'current_question_id' => null,
            'current_card_id' => null,
        ]);
        $gameSession->sessionQuestions()->delete();
        $gameSession->sessionCards()->delete();

        match ($gameType->slug) {
            'oodles' => $this->initializeOodles($gameSession),
            'family-feud' => $this->initializeFamilyFeud($gameSession),
            'america-says' => $this->initializeAmericaSays($gameSession),
            default => throw new \InvalidArgumentException("Unknown game type: {$gameType->slug}"),
        };
    }

    protected function initializeOodles(GameSession $gameSession): void
    {
        $config = $gameSession->settings ?? $gameSession->gameType->default_config;
        $cardsPerGame = $config['cards_per_game'] ?? 15;
        $questionsMode = $config['questions_per_card_mode'] ?? 'random';
        $minQuestions = $config['min_questions_per_card'] ?? 3;
        $maxQuestions = $config['max_questions_per_card'] ?? 10;
        $fixedQuestions = $config['fixed_questions_per_card'] ?? null;
        $allowLetterReuse = $config['allow_letter_reuse'] ?? true;

        // Get available letters from questions
        $availableLetters = Question::where('game_type_id', $gameSession->game_type_id)
            ->where('is_active', true)
            ->whereNotNull('answer_letter')
            ->distinct()
            ->pluck('answer_letter')
            ->toArray();

        if (empty($availableLetters)) {
            throw new \RuntimeException('No questions available for Oodles');
        }

        // "Just for fun" bonus questions shown at the top of each card — no
        // points, no control. Shuffled once; repeats only if the pool runs dry.
        $bonusIds = Question::where('game_type_id', $gameSession->game_type_id)
            ->where('is_active', true)
            ->where('round_type', 'bonus')
            ->inRandomOrder()
            ->pluck('id')
            ->all();
        $bonusIndex = 0;

        $usedLetters = [];
        $usedQuestionIds = [];

        for ($cardNumber = 1; $cardNumber <= $cardsPerGame; $cardNumber++) {
            // Select a letter for this card
            $letterPool = $allowLetterReuse ? $availableLetters : array_diff($availableLetters, $usedLetters);

            if (empty($letterPool)) {
                // If we've used all letters and reuse is disabled, start over
                $letterPool = $availableLetters;
                $usedLetters = [];
            }

            $letter = $letterPool[array_rand($letterPool)];
            $usedLetters[] = $letter;

            // Create the session card
            $sessionCard = SessionCard::create([
                'game_session_id' => $gameSession->id,
                'card_number' => $cardNumber,
                'letter' => $letter,
                'bonus_question_id' => $bonusIds === []
                    ? null
                    : $bonusIds[$bonusIndex++ % count($bonusIds)],
                'status' => 'pending',
            ]);

            // Determine number of questions for this card
            if ($questionsMode === 'fixed' && $fixedQuestions) {
                $numQuestions = $fixedQuestions;
            } else {
                $numQuestions = rand($minQuestions, $maxQuestions);
            }

            // Get random questions for this letter (never reuse questions)
            $questions = Question::where('game_type_id', $gameSession->game_type_id)
                ->where('is_active', true)
                ->where('answer_letter', $letter)
                ->whereNotIn('id', $usedQuestionIds)
                ->inRandomOrder()
                ->limit($numQuestions)
                ->get();

            // Create session questions (only using available unique questions)
            foreach ($questions as $index => $question) {
                SessionQuestion::create([
                    'game_session_id' => $gameSession->id,
                    'session_card_id' => $sessionCard->id,
                    'question_id' => $question->id,
                    'display_order' => $index + 1,
                    'status' => 'pending',
                ]);

                // Track question usage statistics
                $question->incrementUsed();

                $usedQuestionIds[] = $question->id;
            }
        }

        // Set the first card as current
        $firstCard = $gameSession->sessionCards()->first();
        if ($firstCard) {
            $gameSession->gameState->update([
                'current_card_id' => $firstCard->id,
            ]);
        }
    }

    protected function initializeFamilyFeud(GameSession $gameSession): void
    {
        $config = $gameSession->settings ?? $gameSession->gameType->default_config;
        $roundsPerGame = (int) ($config['rounds_per_game'] ?? 4);
        $fastMoneyEnabled = $config['fast_money_enabled'] ?? true;

        // The host assigns a question to each slot in setup (one per round, plus
        // 5 Fast Money slots). Use those explicit picks; fall back to a random
        // eligible question for any slot left unset.
        $sel = is_array($config['question_selection'] ?? null) ? $config['question_selection'] : null;
        $usedIds = [];
        $order = 0;

        // Regular rounds: one question per round (a face-off both teams play).
        for ($roundIndex = 0; $roundIndex < $roundsPerGame; $roundIndex++) {
            $qid = $sel['regular'][$roundIndex][0]['id'] ?? null;
            $question = $qid ? Question::find($qid) : null;

            if (!$question) {
                $question = Question::where('game_type_id', $gameSession->game_type_id)
                    ->where('is_active', true)
                    ->where('round_type', '!=', 'final')
                    ->whereNotIn('id', $usedIds)
                    ->inRandomOrder()
                    ->first();
            }
            if (!$question) {
                break; // Nothing left to assign.
            }

            $usedIds[] = $question->id;
            $order++;
            SessionQuestion::create([
                'game_session_id' => $gameSession->id,
                'question_id' => $question->id,
                'display_order' => $order,
                'round_number' => $roundIndex + 1,
                'status' => 'pending',
                'segment' => 'main',
                'points_available' => $this->calculateRoundMultiplier($roundIndex + 1, $config),
            ]);
            $question->incrementUsed();
        }

        // Fast Money: 5 fixed slots drawn from the Final pool. Both players answer
        // the same set, so it's a flat list (no per-slot answer-count tiers).
        if ($fastMoneyEnabled) {
            for ($k = 0; $k < 5; $k++) {
                $qid = $sel['final'][$k]['id'] ?? null;
                $question = $qid ? Question::find($qid) : null;

                if (!$question) {
                    $question = Question::where('game_type_id', $gameSession->game_type_id)
                        ->where('is_active', true)
                        ->where('round_type', 'final')
                        ->whereNotIn('id', $usedIds)
                        ->inRandomOrder()
                        ->first();
                }
                if (!$question) {
                    continue; // No eligible Final question for this slot.
                }

                $usedIds[] = $question->id;
                $order++;
                SessionQuestion::create([
                    'game_session_id' => $gameSession->id,
                    'question_id' => $question->id,
                    'display_order' => $order,
                    'status' => 'pending',
                    'segment' => 'fast_money',
                ]);
                $question->incrementUsed();
            }
        }

        // Set the first question as current.
        $firstQuestion = $gameSession->sessionQuestions()->orderBy('display_order')->first();
        if ($firstQuestion) {
            $gameSession->gameState->update([
                'current_question_id' => $firstQuestion->id,
            ]);
        }
    }

    protected function initializeAmericaSays(GameSession $gameSession): void
    {
        $config = $gameSession->settings ?? $gameSession->gameType->default_config;

        // A round plays one question per team, so questions per round = team count.
        $teamCount = max(1, $gameSession->teams()->count());

        // Build the per-round scoring plan. When the host has configured
        // round_scoring (from the setup screen), use it. Otherwise fall back to
        // a flat plan derived from the older questions_per_game / points_per_answer
        // settings, so existing games keep working before the setup UI lands.
        $roundScoring = $this->resolveRoundScoring($config, $teamCount);

        // The host assigns a question to each slot in setup (round x team, plus
        // the final round). Use those explicit picks; fall back to a random
        // eligible question for any slot left unset.
        $sel = is_array($config['question_selection'] ?? null) ? $config['question_selection'] : null;
        $usedIds = [];
        $order = 0;

        foreach ($roundScoring as $roundIndex => $round) {
            $roundNumber = $roundIndex + 1;

            // One question per team in this round.
            for ($slot = 0; $slot < $teamCount; $slot++) {
                $qid = $sel['regular'][$roundIndex][$slot]['id'] ?? null;
                $question = $qid ? Question::find($qid) : null;

                if (!$question) {
                    $question = Question::where('game_type_id', $gameSession->game_type_id)
                        ->where('is_active', true)
                        ->where('round_type', '!=', 'final')
                        ->whereNotIn('id', $usedIds)
                        ->inRandomOrder()
                        ->first();
                }
                if (!$question) {
                    break 2; // Nothing left to assign.
                }

                $usedIds[] = $question->id;
                $order++;
                SessionQuestion::create([
                    'game_session_id' => $gameSession->id,
                    'question_id' => $question->id,
                    'display_order' => $order,
                    'round_number' => $roundNumber,
                    'status' => 'pending',
                    'segment' => 'main',
                    'points_available' => $round['points_per_answer'],
                    'bonus_points' => $round['bonus_points'],
                ]);
                $question->incrementUsed();
            }
        }

        // Final round: slot N needs a Final question with exactly N answers. Use
        // the host's pick, else a random eligible Final question. A slot with no
        // eligible question is skipped.
        if ($config['final_round_enabled'] ?? true) {
            $finalCount = (int) ($config['final_round_questions'] ?? 4);

            for ($n = 1; $n <= $finalCount; $n++) {
                $qid = $sel['final'][$n - 1]['id'] ?? null;
                $finalQuestion = $qid ? Question::find($qid) : null;

                if (!$finalQuestion) {
                    $finalQuestion = Question::where('game_type_id', $gameSession->game_type_id)
                        ->where('is_active', true)
                        ->where('round_type', 'final')
                        ->whereNotIn('id', $usedIds)
                        ->has('answers', '=', $n)
                        ->inRandomOrder()
                        ->first();
                }
                if (!$finalQuestion) {
                    continue; // No eligible Final question for this slot.
                }

                $usedIds[] = $finalQuestion->id;
                $order++;
                SessionQuestion::create([
                    'game_session_id' => $gameSession->id,
                    'question_id' => $finalQuestion->id,
                    'display_order' => $order,
                    'status' => 'pending',
                    'segment' => 'final',
                    'answers_needed' => $n,
                ]);
                $finalQuestion->incrementUsed();
            }
        }

        // Set the first question as current and sync the round number.
        $firstQuestion = $gameSession->sessionQuestions()->orderBy('display_order')->first();
        if ($firstQuestion) {
            $gameSession->gameState->update([
                'current_question_id' => $firstQuestion->id,
                'round_number' => $firstQuestion->round_number ?? 1,
            ]);
        }
    }

    /**
     * Resolve the per-round scoring plan as a list of
     * ['points_per_answer' => int, 'bonus_points' => int], one entry per round.
     */
    protected function resolveRoundScoring(array $config, int $teamCount): array
    {
        $configured = $config['round_scoring'] ?? null;

        if (is_array($configured) && count($configured) > 0) {
            return array_map(function ($round) {
                return [
                    'points_per_answer' => (int) ($round['points_per_answer'] ?? 100),
                    'bonus_points' => (int) ($round['bonus_points'] ?? 0),
                ];
            }, array_values($configured));
        }

        // Fallback: flat scoring spread across rounds derived from legacy settings.
        $flatPoints = (int) ($config['points_per_answer'] ?? 100);
        $questionsPerGame = (int) ($config['questions_per_game'] ?? 10);
        $rounds = max(1, (int) ceil($questionsPerGame / $teamCount));

        return array_fill(0, $rounds, [
            'points_per_answer' => $flatPoints,
            'bonus_points' => 0,
        ]);
    }

    protected function calculateRoundMultiplier(int $round, array $config): int
    {
        $multipliers = $config['round_multipliers'] ?? [];
        return $multipliers[(string) $round] ?? 1;
    }
}
