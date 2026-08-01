<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\GameType;
use App\Models\Question;
use Illuminate\Database\Seeder;

/**
 * Questions transcribed from scanned physical Oodles cards (1992 M.B. Co.).
 * Each entry keeps a `source_card` ref in metadata so the clues from one
 * physical card can be traced back to it. Idempotent: existing question text
 * for the same letter is skipped, so this seeder is safe to re-run.
 */
class OodlesScannedCardSeeder extends Seeder
{
    public function run(): void
    {
        $oodles = GameType::where('slug', 'oodles')->first();

        if (!$oodles) {
            $this->command->error('Oodles game type not found. Run GameTypeSeeder first.');
            return;
        }

        $questions = [
            // Card A-421 — bonus: "What did a member of the NBA Nets need after
            // his uniform was stolen?" ANSWER: A NEW NEW JERSEY JERSEY
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'Everglade critter likely to become a wallet', 'answer' => 'Alligator', 'points' => 100],
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'Free from Dear Abby and your mother-in-law', 'answer' => 'Advice', 'points' => 100],
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'An officially sanctioned group of armed men in uniform', 'answer' => 'Army', 'points' => 100],
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'In the \'60s, you loved it or left it', 'answer' => 'America', 'points' => 100],
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'First Golden Girl to go from the show', 'answer' => 'Bea Arthur', 'points' => 300],
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'Captain with a whale of a challenge', 'answer' => 'Ahab', 'points' => 200],
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'You squeeze the music out of it', 'answer' => 'Accordion', 'points' => 100],
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'Seasoning for everyone and everything', 'answer' => 'Allspice', 'points' => 200],
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'A very populated continent', 'answer' => 'Asia', 'points' => 100],
            ['card' => 'A-421', 'letter' => 'A', 'question' => 'She got lost in the clouds', 'answer' => 'Amelia Earhart', 'points' => 200],

            // Card H-587 — bonus: "What do you call a steak cooked in a
            // vegetarian household?" ANSWER: RARE
            ['card' => 'H-587', 'letter' => 'H', 'question' => 'You ain\'t nothing but a dog', 'answer' => 'Hound', 'points' => 100],
            ['card' => 'H-587', 'letter' => 'H', 'question' => 'First son of the rich, often apparent', 'answer' => 'Heir', 'points' => 200],
            ['card' => 'H-587', 'letter' => 'H', 'question' => 'Merle sure looks tired', 'answer' => 'Haggard', 'points' => 300],
            ['card' => 'H-587', 'letter' => 'H', 'question' => 'Warm canine, or barbecue item', 'answer' => 'Hot Dog', 'points' => 100],
            ['card' => 'H-587', 'letter' => 'H', 'question' => 'They thought Mrs. Brown had a lovely daughter', 'answer' => 'Herman\'s Hermits', 'points' => 300],
            ['card' => 'H-587', 'letter' => 'H', 'question' => 'School chore often done between commercials', 'answer' => 'Homework', 'points' => 100],
            ['card' => 'H-587', 'letter' => 'H', 'question' => 'It can ache, break or burn', 'answer' => 'Heart', 'points' => 100],
            ['card' => 'H-587', 'letter' => 'H', 'question' => 'Beatles\' S.O.S. movie', 'answer' => 'Help!', 'points' => 200],
            ['card' => 'H-587', 'letter' => 'H', 'question' => 'He signed the Declaration, then went into insurance', 'answer' => 'John Hancock', 'points' => 200],
            ['card' => 'H-587', 'letter' => 'H', 'question' => '"Book \'em, Danno"', 'answer' => 'Hawaii Five-O', 'points' => 200],

            // Card A-479 — bonus: "True or False: The Prince of Tides is a book
            // about a guy who's really great with laundry detergents." ANSWER: FALSE
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'Colorado town for a ski spree', 'answer' => 'Aspen', 'points' => 100],
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'Doctor who likes to stick it to you', 'answer' => 'Acupuncturist', 'points' => 200],
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'What plus what equals something', 'answer' => 'Addition', 'points' => 100],
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'Reserved for assemblies and school plays', 'answer' => 'Auditorium', 'points' => 100],
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'King who liked to hang around knights', 'answer' => 'Arthur', 'points' => 100],
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'Pennsylvania buggy farmers', 'answer' => 'Amish', 'points' => 100],
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'Public proclamations soliciting business', 'answer' => 'Advertising', 'points' => 200],
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'It\'s about me, and I wrote it', 'answer' => 'Autobiography', 'points' => 100],
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'Person who takes up space', 'answer' => 'Astronomer', 'points' => 200],
            ['card' => 'A-479', 'letter' => 'A', 'question' => 'Linkletter\'s favorite school subject', 'answer' => 'Art', 'points' => 200],

            // Card O-453 — bonus: "True or False: Fu Man Chu is a tough Chinese
            // cookie." ANSWER: FALSE
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'King with a complex', 'answer' => 'Oedipus', 'points' => 200],
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'Buttoned-down university', 'answer' => 'Oxford', 'points' => 100],
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'Messy Madison', 'answer' => 'Oscar', 'points' => 200],
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'Airplane bathroom sign', 'answer' => 'Occupied', 'points' => 100],
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'Citizen Welles', 'answer' => 'Orson', 'points' => 200],
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'Pacific, Indian or Billy', 'answer' => 'Ocean', 'points' => 100],
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'Mayberry\'s #1 son', 'answer' => 'Opie', 'points' => 200],
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'Stan\'s main man', 'answer' => 'Ollie', 'points' => 200],
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'Popeye\'s salad dressing', 'answer' => 'Olive Oil', 'points' => 100],
            ['card' => 'O-453', 'letter' => 'O', 'question' => 'He docked at the bay', 'answer' => 'Otis Redding', 'points' => 200],
        ];

        $created = 0;

        foreach ($questions as $q) {
            $exists = Question::where('game_type_id', $oodles->id)
                ->where('answer_letter', $q['letter'])
                ->where('question_text', $q['question'])
                ->exists();

            if ($exists) {
                continue;
            }

            $question = Question::create([
                'game_type_id' => $oodles->id,
                'question_text' => $q['question'],
                'answer_letter' => $q['letter'],
                'difficulty' => $q['points'] <= 100 ? 'easy' : ($q['points'] <= 200 ? 'medium' : 'hard'),
                'metadata' => ['source_card' => $q['card']],
            ]);

            Answer::create([
                'question_id' => $question->id,
                'answer_text' => $q['answer'],
                'points' => $q['points'],
                'display_order' => 1,
            ]);

            $created++;
        }

        $this->command->info("Created {$created} Oodles questions from scanned cards (" . (count($questions) - $created) . ' already existed).');
    }
}
