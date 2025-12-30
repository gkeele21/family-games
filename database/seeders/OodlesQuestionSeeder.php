<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\GameType;
use App\Models\Question;
use Illuminate\Database\Seeder;

class OodlesQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $oodles = GameType::where('slug', 'oodles')->first();

        if (!$oodles) {
            $this->command->error('Oodles game type not found. Run GameTypeSeeder first.');
            return;
        }

        $questions = [
            // Letter A
            ['letter' => 'A', 'question' => 'Metal leftover wrap', 'answer' => 'Aluminum Foil', 'points' => 100],
            ['letter' => 'A', 'question' => 'All purpose pill, take two', 'answer' => 'Aspirin', 'points' => 100],
            ['letter' => 'A', 'question' => 'Hello or good-bye, it\'s the same in Hawaii', 'answer' => 'Aloha', 'points' => 100],
            ['letter' => 'A', 'question' => 'The machine that makes Arizona bearable', 'answer' => 'Air Conditioner', 'points' => 200],
            ['letter' => 'A', 'question' => '"Heel" of a Greek guy', 'answer' => 'Achilles', 'points' => 200],
            ['letter' => 'A', 'question' => 'Plop, plop, fizz, fizz', 'answer' => 'Alka Seltzer', 'points' => 100],
            ['letter' => 'A', 'question' => 'The little star-like symbol meaning "see below"', 'answer' => 'Asterisk', 'points' => 200],
            ['letter' => 'A', 'question' => 'Uh, oh! Does your superior officer know where you are?', 'answer' => 'AWOL', 'points' => 200],
            ['letter' => 'A', 'question' => 'Brave who came home 755 times', 'answer' => 'Hank Aaron', 'points' => 300],
            ['letter' => 'A', 'question' => 'Tumblers carrying and flipping other tumblers', 'answer' => 'Acrobats', 'points' => 100],

            // Letter B
            ['letter' => 'B', 'question' => 'Yellow fruit that monkeys love', 'answer' => 'Banana', 'points' => 100],
            ['letter' => 'B', 'question' => 'Flying mammal that loves the night', 'answer' => 'Bat', 'points' => 100],
            ['letter' => 'B', 'question' => 'What you put your groceries in at the store', 'answer' => 'Bag', 'points' => 100],
            ['letter' => 'B', 'question' => 'James Bond\'s drink order: shaken, not stirred', 'answer' => 'Martini... Beefeater', 'points' => 300],
            ['letter' => 'B', 'question' => 'The Dark Knight of Gotham', 'answer' => 'Batman', 'points' => 100],
            ['letter' => 'B', 'question' => 'Morning meal, first of the day', 'answer' => 'Breakfast', 'points' => 100],
            ['letter' => 'B', 'question' => 'This flying insect makes honey', 'answer' => 'Bee', 'points' => 100],
            ['letter' => 'B', 'question' => 'Ruth hit 714 of them', 'answer' => 'Home Runs... Babe', 'points' => 200],
            ['letter' => 'B', 'question' => 'Burger chain with a king mascot', 'answer' => 'Burger King', 'points' => 100],
            ['letter' => 'B', 'question' => 'Inflatable party decoration', 'answer' => 'Balloon', 'points' => 100],

            // Letter C
            ['letter' => 'C', 'question' => 'Birthday dessert with candles', 'answer' => 'Cake', 'points' => 100],
            ['letter' => 'C', 'question' => 'Feline that purrs', 'answer' => 'Cat', 'points' => 100],
            ['letter' => 'C', 'question' => 'Hot beverage from beans', 'answer' => 'Coffee', 'points' => 100],
            ['letter' => 'C', 'question' => 'Circus performer who makes you laugh', 'answer' => 'Clown', 'points' => 100],
            ['letter' => 'C', 'question' => 'Sweet treat made from cacao', 'answer' => 'Chocolate', 'points' => 100],
            ['letter' => 'C', 'question' => 'Orange vegetable rabbits love', 'answer' => 'Carrot', 'points' => 100],
            ['letter' => 'C', 'question' => 'Device for taking photos', 'answer' => 'Camera', 'points' => 100],
            ['letter' => 'C', 'question' => 'Crusty crustacean that walks sideways', 'answer' => 'Crab', 'points' => 100],
            ['letter' => 'C', 'question' => 'Kent who works at the Daily Planet', 'answer' => 'Clark', 'points' => 200],
            ['letter' => 'C', 'question' => 'Red fizzy drink from Atlanta', 'answer' => 'Coca-Cola', 'points' => 100],

            // Letter D
            ['letter' => 'D', 'question' => 'Man\'s best friend', 'answer' => 'Dog', 'points' => 100],
            ['letter' => 'D', 'question' => 'Fried ring-shaped pastry', 'answer' => 'Donut', 'points' => 100],
            ['letter' => 'D', 'question' => 'Quacking waterfowl', 'answer' => 'Duck', 'points' => 100],
            ['letter' => 'D', 'question' => 'What vampires fear most', 'answer' => 'Daylight', 'points' => 200],
            ['letter' => 'D', 'question' => 'Bambi was one', 'answer' => 'Deer', 'points' => 100],
            ['letter' => 'D', 'question' => 'Prehistoric reptile, extinct now', 'answer' => 'Dinosaur', 'points' => 100],
            ['letter' => 'D', 'question' => 'Six-sided gaming cube', 'answer' => 'Dice', 'points' => 100],
            ['letter' => 'D', 'question' => 'Ocean mammal that\'s very smart', 'answer' => 'Dolphin', 'points' => 100],
            ['letter' => 'D', 'question' => 'Where wizards fought at Hogwarts', 'answer' => 'Dumbledore', 'points' => 200],
            ['letter' => 'D', 'question' => 'Sweet treat after dinner', 'answer' => 'Dessert', 'points' => 100],

            // Letter E
            ['letter' => 'E', 'question' => 'What comes out of a chicken', 'answer' => 'Egg', 'points' => 100],
            ['letter' => 'E', 'question' => 'Dumbo was a flying one', 'answer' => 'Elephant', 'points' => 100],
            ['letter' => 'E', 'question' => 'Where Elvis lives on forever', 'answer' => 'Elvis', 'points' => 200],
            ['letter' => 'E', 'question' => 'Bunny holiday in spring', 'answer' => 'Easter', 'points' => 100],
            ['letter' => 'E', 'question' => 'The planet we live on', 'answer' => 'Earth', 'points' => 100],
            ['letter' => 'E', 'question' => 'Bald bird, America\'s symbol', 'answer' => 'Eagle', 'points' => 100],
            ['letter' => 'E', 'question' => 'The moving stairs at the mall', 'answer' => 'Escalator', 'points' => 100],
            ['letter' => 'E', 'question' => 'What you send electronically', 'answer' => 'Email', 'points' => 100],
            ['letter' => 'E', 'question' => 'Big gray animal with a trunk', 'answer' => 'Elephant', 'points' => 100],
            ['letter' => 'E', 'question' => 'Pointy feature on Mr. Spock', 'answer' => 'Ears', 'points' => 100],

            // Letter F
            ['letter' => 'F', 'question' => 'Sly woodland creature', 'answer' => 'Fox', 'points' => 100],
            ['letter' => 'F', 'question' => 'Feathered flyer', 'answer' => 'Bird... no wait, Fowl', 'points' => 200],
            ['letter' => 'F', 'question' => 'Where you buy produce and meat', 'answer' => 'Farmers Market', 'points' => 200],
            ['letter' => 'F', 'question' => 'What firefighters fight', 'answer' => 'Fire', 'points' => 100],
            ['letter' => 'F', 'question' => 'Green hopping amphibian', 'answer' => 'Frog', 'points' => 100],
            ['letter' => 'F', 'question' => 'The stars and stripes', 'answer' => 'Flag', 'points' => 100],
            ['letter' => 'F', 'question' => 'What grows in your garden', 'answer' => 'Flowers', 'points' => 100],
            ['letter' => 'F', 'question' => 'The meal after breakfast', 'answer' => 'Lunch... no, that\'s wrong - Feast', 'points' => 200],
            ['letter' => 'F', 'question' => 'Gump ran across America', 'answer' => 'Forrest', 'points' => 200],
            ['letter' => 'F', 'question' => 'What a fish uses to swim', 'answer' => 'Fins', 'points' => 100],

            // Letter G
            ['letter' => 'G', 'question' => 'Of Teutonic origins', 'answer' => 'German', 'points' => 200],
            ['letter' => 'G', 'question' => 'All Lennon was saying', 'answer' => 'Give Peace a Chance', 'points' => 300],
            ['letter' => 'G', 'question' => 'Stranded on an island for three seasons', 'answer' => 'Gilligan', 'points' => 200],
            ['letter' => 'G', 'question' => 'Bowling lane edge', 'answer' => 'Gutter', 'points' => 100],
            ['letter' => 'G', 'question' => 'A bicycle, old tires, a hose, and maybe a car', 'answer' => 'Garage', 'points' => 100],
            ['letter' => 'G', 'question' => 'Hollywood\'s slap-happy Hungarian', 'answer' => 'Zsa Zsa Gabor', 'points' => 300],
            ['letter' => 'G', 'question' => 'It\'s between the tiles', 'answer' => 'Grout', 'points' => 100],
            ['letter' => 'G', 'question' => 'Likely base of little people cookies', 'answer' => 'Gingerbread', 'points' => 200],
            ['letter' => 'G', 'question' => 'Chew goo', 'answer' => 'Gum', 'points' => 100],
            ['letter' => 'G', 'question' => 'Big ape who climbed the Empire State', 'answer' => 'King Kong... no, Gorilla', 'points' => 100],

            // Letter H
            ['letter' => 'H', 'question' => 'Animal skin and go seek', 'answer' => 'Hide', 'points' => 100],
            ['letter' => 'H', 'question' => 'This Oliver had several residences', 'answer' => 'Holmes', 'points' => 200],
            ['letter' => 'H', 'question' => 'Film director John\'s location in Texas', 'answer' => 'Houston', 'points' => 200],
            ['letter' => 'H', 'question' => 'What smokers and woodmen do', 'answer' => 'Hack', 'points' => 100],
            ['letter' => 'H', 'question' => 'A dog is always happy to see one', 'answer' => 'Hydrant', 'points' => 100],
            ['letter' => 'H', 'question' => 'Game that involves jumping over a bottle of whiskey', 'answer' => 'Hopscotch', 'points' => 200],
            ['letter' => 'H', 'question' => 'Favorite sandwich of actors and actresses', 'answer' => 'Ham', 'points' => 100],
            ['letter' => 'H', 'question' => 'A red-letter day with free pay', 'answer' => 'Holiday', 'points' => 100],
            ['letter' => 'H', 'question' => 'Big spender\'s place on a hog', 'answer' => 'High', 'points' => 200],
            ['letter' => 'H', 'question' => 'Sweet substance bees make', 'answer' => 'Honey', 'points' => 100],

            // Letter J
            ['letter' => 'J', 'question' => 'Detergent for every happy dish', 'answer' => 'Joy', 'points' => 100],
            ['letter' => 'J', 'question' => 'It really has a yen for cars', 'answer' => 'Japan', 'points' => 200],
            ['letter' => 'J', 'question' => 'Try to keep up with them - especially in the suburbs', 'answer' => 'Joneses', 'points' => 200],
            ['letter' => 'J', 'question' => 'Funny playing card', 'answer' => 'Joker', 'points' => 100],
            ['letter' => 'J', 'question' => 'Seafood restaurant oxymoron', 'answer' => 'Jumbo Shrimp', 'points' => 200],
            ['letter' => 'J', 'question' => 'A senior comes before this - except in high school', 'answer' => 'Junior', 'points' => 100],
            ['letter' => 'J', 'question' => 'Astro\'s comic owners', 'answer' => 'Jetsons', 'points' => 200],
            ['letter' => 'J', 'question' => 'An alcoholic drink that\'s usually in mint condition', 'answer' => 'Julep', 'points' => 200],
            ['letter' => 'J', 'question' => 'Sleeveless dress that\'s perfect for Leap Year', 'answer' => 'Jumper', 'points' => 200],
            ['letter' => 'J', 'question' => 'Denim pants invented by Levi', 'answer' => 'Jeans', 'points' => 100],

            // Letter M
            ['letter' => 'M', 'question' => 'White liquid from cows', 'answer' => 'Milk', 'points' => 100],
            ['letter' => 'M', 'question' => 'The cheese that stands alone', 'answer' => 'Farmer... no, Mozzarella', 'points' => 200],
            ['letter' => 'M', 'question' => 'Dorothy\'s home state', 'answer' => 'Kansas... no, wait - Midwest', 'points' => 200],
            ['letter' => 'M', 'question' => 'Fuzzy yellow Twinkie filling', 'answer' => 'Marshmallow', 'points' => 100],
            ['letter' => 'M', 'question' => 'Earth\'s natural satellite', 'answer' => 'Moon', 'points' => 100],
            ['letter' => 'M', 'question' => 'Place where you see art on walls', 'answer' => 'Museum', 'points' => 100],
            ['letter' => 'M', 'question' => 'Michael Jackson\'s moonwalk dance move', 'answer' => 'Moonwalk', 'points' => 200],
            ['letter' => 'M', 'question' => 'Dracula turns into this flying creature', 'answer' => 'Bat... no - Mist', 'points' => 200],
            ['letter' => 'M', 'question' => 'What you spread on toast with peanut butter', 'answer' => 'Marmalade', 'points' => 200],
            ['letter' => 'M', 'question' => 'Marilyn\'s surname before the fame', 'answer' => 'Monroe', 'points' => 200],
        ];

        foreach ($questions as $q) {
            $question = Question::create([
                'game_type_id' => $oodles->id,
                'question_text' => $q['question'],
                'answer_letter' => $q['letter'],
                'difficulty' => $q['points'] <= 100 ? 'easy' : ($q['points'] <= 200 ? 'medium' : 'hard'),
            ]);

            Answer::create([
                'question_id' => $question->id,
                'answer_text' => $q['answer'],
                'points' => $q['points'],
                'display_order' => 1,
            ]);
        }

        $this->command->info('Created ' . count($questions) . ' Oodles questions.');
    }
}
