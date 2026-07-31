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
        $roundsPerGame = $config['rounds_per_game'] ?? 4;
        $fastMoneyEnabled = $config['fast_money_enabled'] ?? true;

        // Regular-round questions, honoring the host's selection (random filters
        // or hand-picked). selectQuestions() already excludes the final pool.
        $regularQuestions = $this->selectQuestions($gameSession, $roundsPerGame);

        // Create session questions for regular rounds
        foreach ($regularQuestions as $index => $question) {
            SessionQuestion::create([
                'game_session_id' => $gameSession->id,
                'question_id' => $question->id,
                'display_order' => $index + 1,
                'status' => 'pending',
                'segment' => 'main',
                'points_available' => $this->calculateRoundMultiplier($index + 1, $config),
            ]);

            // Track question usage statistics
            $question->incrementUsed();
        }

        // Add fast money questions if enabled
        if ($fastMoneyEnabled) {
            $fastMoneyQuestions = Question::where('game_type_id', $gameSession->game_type_id)
                ->where('is_active', true)
                ->where('round_type', 'final')
                ->inRandomOrder()
                ->limit(5) // Typically 5 questions for fast money
                ->get();

            $startOrder = $roundsPerGame + 1;
            foreach ($fastMoneyQuestions as $index => $question) {
                SessionQuestion::create([
                    'game_session_id' => $gameSession->id,
                    'question_id' => $question->id,
                    'display_order' => $startOrder + $index,
                    'status' => 'pending',
                    'segment' => 'final',
                ]);

                // Track question usage statistics
                $question->incrementUsed();
            }
        }

        // Set the first question as current
        $firstQuestion = $gameSession->sessionQuestions()->first();
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

        // Pull enough questions for every slot (rounds x teams), honoring the
        // host's question selection (random-with-filters or hand-picked).
        $needed = count($roundScoring) * $teamCount;
        $questions = $this->selectQuestions($gameSession, $needed);

        $order = 0;
        foreach ($roundScoring as $roundIndex => $round) {
            $roundNumber = $roundIndex + 1;

            // One question per team in this round.
            for ($slot = 0; $slot < $teamCount; $slot++) {
                $question = $questions->get($order);
                if (!$question) {
                    break 2; // Not enough questions in the bank; stop gracefully.
                }
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

        // Reserve the final round: slots requiring the top 1..N answers. Each slot
        // draws a random unused question with at least that many answers (a 2-answer
        // question can't fill a slot needing 3). Gameplay is wired up later.
        if ($config['final_round_enabled'] ?? true) {
            $finalCount = (int) ($config['final_round_questions'] ?? 4);
            $usedIds = SessionQuestion::where('game_session_id', $gameSession->id)->pluck('question_id')->all();

            for ($n = 1; $n <= $finalCount; $n++) {
                // Prefer questions authored for the final round; otherwise fall
                // back to any regular question with enough answers (capacity rule).
                $finalQuestion = Question::where('game_type_id', $gameSession->game_type_id)
                        ->where('is_active', true)
                        ->where('round_type', 'final')
                        ->whereNotIn('id', $usedIds)
                        ->has('answers', '>=', $n)
                        ->inRandomOrder()
                        ->first()
                    ?? Question::where('game_type_id', $gameSession->game_type_id)
                        ->where('is_active', true)
                        ->whereNotIn('id', $usedIds)
                        ->has('answers', '>=', $n)
                        ->inRandomOrder()
                        ->first();

                if (!$finalQuestion) {
                    break; // Not enough eligible questions; stop reserving.
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
     * Choose the questions for a session based on its `question_selection` config:
     *   - hand_picked: exactly the chosen questions, in the chosen order
     *   - random + category: random from the selected categories
     *   - random + difficulty: random at the selected difficulty
     *   - random + any (default): random from the whole active bank
     * Falls back to whole-bank random when nothing is configured.
     */
    protected function selectQuestions(GameSession $gameSession, int $needed): \Illuminate\Support\Collection
    {
        $config = $gameSession->settings ?? $gameSession->gameType->default_config ?? [];
        $sel = $config['question_selection'] ?? null;

        // Regular play draws from non-final questions; final-round questions
        // (Family Feud "Fast Money", America Says final) are a separate pool.
        $base = Question::where('game_type_id', $gameSession->game_type_id)
            ->where('is_active', true)
            ->where('round_type', '!=', 'final');

        if (is_array($sel) && ($sel['mode'] ?? 'random') === 'hand_picked' && !empty($sel['question_ids'])) {
            $ids = array_values($sel['question_ids']);
            $found = (clone $base)->whereIn('id', $ids)->get()->keyBy('id');

            // Preserve the host's chosen order, dropping any now-inactive picks.
            return collect($ids)
                ->map(fn ($id) => $found->get($id))
                ->filter()
                ->take($needed)
                ->values();
        }

        if (is_array($sel) && ($sel['mode'] ?? 'random') === 'random') {
            $filter = $sel['filter'] ?? 'any';
            if ($filter === 'category' && !empty($sel['category_ids'])) {
                $base->whereIn('category_id', array_values($sel['category_ids']));
            } elseif ($filter === 'difficulty' && !empty($sel['difficulty'])) {
                $base->where('difficulty', $sel['difficulty']);
            }
        }

        return $base->inRandomOrder()->limit($needed)->get();
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
