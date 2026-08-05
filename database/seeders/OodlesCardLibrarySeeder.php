<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\GameType;
use App\Models\Question;
use Illuminate\Database\Seeder;

/**
 * Loads the Oodles card library from database/data/oodles_cards.json — the
 * output of the scan OCR pipeline run over the physical cards (personal-use
 * transcription; keep this data out of anything public).
 *
 * Each card contributes:
 *  - its letter clues as regular questions, difficulty 'medium'
 *  - its top "silly" question as a round_type 'bonus' question (no letter),
 *    shown during play as a just-for-fun opener worth no points
 *
 * Idempotent: rows matching an existing question_text (+letter for clues)
 * are skipped, so re-running after regenerating the JSON only adds new rows.
 */
class OodlesCardLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $oodles = GameType::where('slug', 'oodles')->first();
        if (!$oodles) {
            $this->command->error('Oodles game type not found. Run GameTypeSeeder first.');
            return;
        }

        $path = database_path('data/oodles_cards.json');
        if (!is_file($path)) {
            $this->command->warn('database/data/oodles_cards.json not found — nothing to seed.');
            return;
        }

        $cards = json_decode(file_get_contents($path), true);
        if (!is_array($cards)) {
            $this->command->error('oodles_cards.json is not valid JSON.');
            return;
        }

        $clues = 0;
        $bonuses = 0;

        foreach ($cards as $card) {
            $source = $card['source'] ?? null;

            // The card's just-for-fun opener.
            $silly = $card['silly'] ?? null;
            if (!empty($silly['q']) && !empty($silly['a'])) {
                $exists = Question::where('game_type_id', $oodles->id)
                    ->where('round_type', 'bonus')
                    ->where('question_text', $silly['q'])
                    ->exists();

                if (!$exists) {
                    $question = Question::create([
                        'game_type_id'  => $oodles->id,
                        'question_text' => $silly['q'],
                        'round_type'    => 'bonus',
                        'difficulty'    => 'medium',
                        'metadata'      => ['source_card' => $source],
                    ]);
                    Answer::create([
                        'question_id'   => $question->id,
                        'answer_text'   => $silly['a'],
                        'points'        => 0,
                        'display_order' => 1,
                    ]);
                    $bonuses++;
                }
            }

            foreach ($card['clues'] ?? [] as $clue) {
                if (empty($clue['q']) || empty($clue['a'])) {
                    continue;
                }

                $exists = Question::where('game_type_id', $oodles->id)
                    ->where('answer_letter', $card['letter'])
                    ->where('question_text', $clue['q'])
                    ->exists();
                if ($exists) {
                    continue;
                }

                $question = Question::create([
                    'game_type_id'  => $oodles->id,
                    'question_text' => $clue['q'],
                    'answer_letter' => $card['letter'],
                    'difficulty'    => 'medium',
                    'metadata'      => ['source_card' => $source],
                ]);
                Answer::create([
                    'question_id'   => $question->id,
                    'answer_text'   => $clue['a'],
                    'points'        => 200,
                    'display_order' => 1,
                ]);
                $clues++;
            }
        }

        $this->command->info("Card library: created {$clues} clues and {$bonuses} bonus questions from " . count($cards) . ' cards.');
    }
}
