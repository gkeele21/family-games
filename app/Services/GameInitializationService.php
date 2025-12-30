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

            // Get random questions for this letter
            $questions = Question::where('game_type_id', $gameSession->game_type_id)
                ->where('is_active', true)
                ->where('answer_letter', $letter)
                ->whereNotIn('id', $usedQuestionIds)
                ->inRandomOrder()
                ->limit($numQuestions)
                ->get();

            // If we don't have enough unused questions, allow reuse
            if ($questions->count() < $numQuestions) {
                $needed = $numQuestions - $questions->count();
                $additionalQuestions = Question::where('game_type_id', $gameSession->game_type_id)
                    ->where('is_active', true)
                    ->where('answer_letter', $letter)
                    ->whereIn('id', $usedQuestionIds)
                    ->inRandomOrder()
                    ->limit($needed)
                    ->get();
                $questions = $questions->concat($additionalQuestions);
            }

            // Create session questions
            foreach ($questions as $index => $question) {
                SessionQuestion::create([
                    'game_session_id' => $gameSession->id,
                    'session_card_id' => $sessionCard->id,
                    'question_id' => $question->id,
                    'display_order' => $index + 1,
                    'status' => 'pending',
                ]);

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

        // Get random questions for regular rounds
        $regularQuestions = Question::where('game_type_id', $gameSession->game_type_id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('is_fast_money', false)
                    ->orWhereNull('is_fast_money');
            })
            ->inRandomOrder()
            ->limit($roundsPerGame)
            ->get();

        // Create session questions for regular rounds
        foreach ($regularQuestions as $index => $question) {
            SessionQuestion::create([
                'game_session_id' => $gameSession->id,
                'question_id' => $question->id,
                'display_order' => $index + 1,
                'status' => 'pending',
                'points_available' => $this->calculateRoundMultiplier($index + 1, $config),
            ]);
        }

        // Add fast money questions if enabled
        if ($fastMoneyEnabled) {
            $fastMoneyQuestions = Question::where('game_type_id', $gameSession->game_type_id)
                ->where('is_active', true)
                ->where('is_fast_money', true)
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
                    'control_status' => 'fast_money',
                ]);
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
        $questionsPerGame = $config['questions_per_game'] ?? 10;

        // Get random questions
        $questions = Question::where('game_type_id', $gameSession->game_type_id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit($questionsPerGame)
            ->get();

        // Create session questions
        foreach ($questions as $index => $question) {
            SessionQuestion::create([
                'game_session_id' => $gameSession->id,
                'question_id' => $question->id,
                'display_order' => $index + 1,
                'status' => 'pending',
            ]);
        }

        // Set the first question as current
        $firstQuestion = $gameSession->sessionQuestions()->first();
        if ($firstQuestion) {
            $gameSession->gameState->update([
                'current_question_id' => $firstQuestion->id,
            ]);
        }
    }

    protected function calculateRoundMultiplier(int $round, array $config): int
    {
        $multipliers = $config['round_multipliers'] ?? [];
        return $multipliers[(string) $round] ?? 1;
    }
}
