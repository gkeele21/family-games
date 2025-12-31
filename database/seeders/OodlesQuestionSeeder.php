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
            ['letter' => 'B', 'question' => 'The Dark Knight of Gotham', 'answer' => 'Batman', 'points' => 100],
            ['letter' => 'B', 'question' => 'Morning meal, first of the day', 'answer' => 'Breakfast', 'points' => 100],
            ['letter' => 'B', 'question' => 'This flying insect makes honey', 'answer' => 'Bee', 'points' => 100],
            ['letter' => 'B', 'question' => 'Fast food chain with a king mascot', 'answer' => 'Burger King', 'points' => 100],
            ['letter' => 'B', 'question' => 'Inflatable party decoration', 'answer' => 'Balloon', 'points' => 100],
            ['letter' => 'B', 'question' => 'Dracula turns into this flying creature', 'answer' => 'Bat', 'points' => 100],

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
            ['letter' => 'D', 'question' => 'He ruled Hogwarts', 'answer' => 'Dumbledore', 'points' => 200],
            ['letter' => 'D', 'question' => 'Sweet treat after dinner', 'answer' => 'Dessert', 'points' => 100],

            // Letter E
            ['letter' => 'E', 'question' => 'What comes out of a chicken', 'answer' => 'Egg', 'points' => 100],
            ['letter' => 'E', 'question' => 'Dumbo was a flying one', 'answer' => 'Elephant', 'points' => 100],
            ['letter' => 'E', 'question' => 'Famous hip-swinger musician', 'answer' => 'Elvis', 'points' => 200],
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
            ['letter' => 'K', 'question' => 'Big ape who climbed the Empire State', 'answer' => 'King Kong', 'points' => 100],

            // Letter H
            ['letter' => 'H', 'question' => 'Animal skin and go seek', 'answer' => 'Hide', 'points' => 100],
            ['letter' => 'H', 'question' => 'Film director John\'s location in Texas', 'answer' => 'Houston', 'points' => 200],
            ['letter' => 'H', 'question' => 'What smokers and woodmen do', 'answer' => 'Hack', 'points' => 100],
            ['letter' => 'H', 'question' => 'A dog is always happy to see one', 'answer' => 'Hydrant', 'points' => 100],
            ['letter' => 'H', 'question' => 'Game that involves jumping over a bottle of whiskey', 'answer' => 'Hopscotch', 'points' => 200],
            ['letter' => 'H', 'question' => 'Favorite sandwich of actors and actresses', 'answer' => 'Ham', 'points' => 100],
            ['letter' => 'H', 'question' => 'A red-letter day with free pay', 'answer' => 'Holiday', 'points' => 100],
            ['letter' => 'H', 'question' => 'Big spender\'s place on a hog', 'answer' => 'High', 'points' => 200],
            ['letter' => 'H', 'question' => 'Sweet substance bees make', 'answer' => 'Honey', 'points' => 100],
            ['letter' => 'H', 'question' => 'Ruth hit 714 of them', 'answer' => 'Home Runs', 'points' => 100],

            // Letter J
            ['letter' => 'J', 'question' => 'Detergent for every happy dish', 'answer' => 'Joy', 'points' => 100],
            ['letter' => 'J', 'question' => 'It really has a yen for cars', 'answer' => 'Japan', 'points' => 200],
            ['letter' => 'J', 'question' => 'Try to keep up with them - especially in the suburbs', 'answer' => 'Joneses', 'points' => 200],
            ['letter' => 'J', 'question' => 'Funny playing card', 'answer' => 'Joker', 'points' => 100],
            ['letter' => 'J', 'question' => 'Seafood restaurant oxymoron', 'answer' => 'Jumbo Shrimp', 'points' => 200],
            ['letter' => 'J', 'question' => 'A senior comes before this - except in high school', 'answer' => 'Junior', 'points' => 100],
            ['letter' => 'J', 'question' => 'Astro\'s comic owners', 'answer' => 'Jetsons', 'points' => 200],
            ['letter' => 'J', 'question' => 'Sleeveless dress that\'s perfect for Leap Year', 'answer' => 'Jumper', 'points' => 200],
            ['letter' => 'J', 'question' => 'Denim pants invented by Levi', 'answer' => 'Jeans', 'points' => 100],

            // Letter M
            ['letter' => 'M', 'question' => 'White liquid from cows', 'answer' => 'Milk', 'points' => 100],
            ['letter' => 'M', 'question' => 'The cheese that stands alone', 'answer' => 'Mozzarella', 'points' => 200],
            ['letter' => 'M', 'question' => 'Fuzzy yellow Twinkie filling', 'answer' => 'Marshmallow', 'points' => 100],
            ['letter' => 'M', 'question' => 'Earth\'s natural satellite', 'answer' => 'Moon', 'points' => 100],
            ['letter' => 'M', 'question' => 'Place where you see art on walls', 'answer' => 'Museum', 'points' => 100],
            ['letter' => 'M', 'question' => 'Michael Jackson\'s backwards dance move', 'answer' => 'Moonwalk', 'points' => 200],
            ['letter' => 'M', 'question' => 'What you spread on toast with peanut butter', 'answer' => 'Marmalade', 'points' => 200],
            ['letter' => 'M', 'question' => 'Marilyn\'s surname before the fame', 'answer' => 'Monroe', 'points' => 200],

            // Letter N
            ['letter' => 'N', 'question' => 'Where birds build their homes', 'answer' => 'Nest', 'points' => 100],
            ['letter' => 'N', 'question' => 'Daily publication with headlines', 'answer' => 'Newspaper', 'points' => 100],
            ['letter' => 'N', 'question' => 'Owl\'s active hours', 'answer' => 'Night', 'points' => 100],
            ['letter' => 'N', 'question' => 'Hammer\'s partner for hanging pictures', 'answer' => 'Nail', 'points' => 100],
            ['letter' => 'N', 'question' => 'Italian pasta strings', 'answer' => 'Noodles', 'points' => 100],
            ['letter' => 'N', 'question' => 'Explosive material Alfred invented', 'answer' => 'Nobel... Nitroglycerin', 'points' => 300],
            ['letter' => 'N', 'question' => 'What you blow when you have a cold', 'answer' => 'Nose', 'points' => 100],
            ['letter' => 'N', 'question' => 'Math digits from 0 to 9', 'answer' => 'Numbers', 'points' => 100],
            ['letter' => 'N', 'question' => 'Caretaker for young children', 'answer' => 'Nanny', 'points' => 100],
            ['letter' => 'N', 'question' => 'Edible tree seed, like almonds', 'answer' => 'Nuts', 'points' => 100],
            ['letter' => 'N', 'question' => 'Country shaped like a boot\'s neighbor', 'answer' => 'Netherlands', 'points' => 200],
            ['letter' => 'N', 'question' => 'Sailor\'s workplace', 'answer' => 'Navy', 'points' => 100],
            ['letter' => 'N', 'question' => 'Biblical boat builder', 'answer' => 'Noah', 'points' => 100],
            ['letter' => 'N', 'question' => 'Compass direction toward the pole', 'answer' => 'North', 'points' => 100],
            ['letter' => 'N', 'question' => 'Sir Isaac\'s falling apple discovery', 'answer' => 'Newton', 'points' => 200],
            ['letter' => 'N', 'question' => 'City that never sleeps', 'answer' => 'New York', 'points' => 100],
            ['letter' => 'N', 'question' => 'Paper napkin or cloth one', 'answer' => 'Napkin', 'points' => 100],
            ['letter' => 'N', 'question' => 'Scary bad dream', 'answer' => 'Nightmare', 'points' => 100],
            ['letter' => 'N', 'question' => 'Jewelry worn around the throat', 'answer' => 'Necklace', 'points' => 100],
            ['letter' => 'N', 'question' => 'Florence\'s famous nurse', 'answer' => 'Nightingale', 'points' => 200],
            ['letter' => 'N', 'question' => 'Zero, zilch, zip', 'answer' => 'Nothing', 'points' => 100],
            ['letter' => 'N', 'question' => 'Atomic power source', 'answer' => 'Nuclear', 'points' => 200],
            ['letter' => 'N', 'question' => 'Evergreen Christmas tree type', 'answer' => 'Noble Fir', 'points' => 300],
            ['letter' => 'N', 'question' => 'They come from Neptune', 'answer' => 'Neptunians', 'points' => 300],

            // Letter O
            ['letter' => 'O', 'question' => 'Citrus fruit used for juice', 'answer' => 'Orange', 'points' => 100],
            ['letter' => 'O', 'question' => 'Large body of salt water', 'answer' => 'Ocean', 'points' => 100],
            ['letter' => 'O', 'question' => 'Eight-armed sea creature', 'answer' => 'Octopus', 'points' => 100],
            ['letter' => 'O', 'question' => 'Cookie with cream filling', 'answer' => 'Oreo', 'points' => 100],
            ['letter' => 'O', 'question' => 'Night bird that hoots', 'answer' => 'Owl', 'points' => 100],
            ['letter' => 'O', 'question' => 'Black and white tuxedo bird', 'answer' => 'Orca', 'points' => 200],
            ['letter' => 'O', 'question' => 'Breakfast food laid by chickens', 'answer' => 'Omelet', 'points' => 100],
            ['letter' => 'O', 'question' => 'Musical instrument with pipes', 'answer' => 'Organ', 'points' => 100],
            ['letter' => 'O', 'question' => 'Greek mountain of the gods', 'answer' => 'Olympus', 'points' => 200],
            ['letter' => 'O', 'question' => 'Vegetable that makes you cry', 'answer' => 'Onion', 'points' => 100],
            ['letter' => 'O', 'question' => 'Athlete who competes for gold', 'answer' => 'Olympian', 'points' => 200],
            ['letter' => 'O', 'question' => 'Africa\'s largest bird', 'answer' => 'Ostrich', 'points' => 100],
            ['letter' => 'O', 'question' => 'Emerald City\'s wizard location', 'answer' => 'Oz', 'points' => 100],
            ['letter' => 'O', 'question' => 'Obi-Wan\'s first name', 'answer' => 'Obi', 'points' => 200],
            ['letter' => 'O', 'question' => 'Oklahoma\'s postal abbreviation', 'answer' => 'OK', 'points' => 100],
            ['letter' => 'O', 'question' => 'Opposite of closed', 'answer' => 'Open', 'points' => 100],
            ['letter' => 'O', 'question' => 'Medical doctor for bones', 'answer' => 'Orthopedic', 'points' => 300],
            ['letter' => 'O', 'question' => 'Garden green pod vegetable', 'answer' => 'Okra', 'points' => 200],
            ['letter' => 'O', 'question' => 'Tree you\'ll find acorns under', 'answer' => 'Oak', 'points' => 100],
            ['letter' => 'O', 'question' => 'Pledge of loyalty', 'answer' => 'Oath', 'points' => 100],
            ['letter' => 'O', 'question' => 'Eye doctor', 'answer' => 'Optometrist', 'points' => 200],
            ['letter' => 'O', 'question' => 'Musical in a foreign language', 'answer' => 'Opera', 'points' => 100],
            ['letter' => 'O', 'question' => 'Popeye\'s girlfriend', 'answer' => 'Olive Oyl', 'points' => 100],
            ['letter' => 'O', 'question' => 'Solitary loner', 'answer' => 'Outcast', 'points' => 200],

            // Letter P
            ['letter' => 'P', 'question' => 'Thin, flat Italian dish with toppings', 'answer' => 'Pizza', 'points' => 100],
            ['letter' => 'P', 'question' => 'Salty movie snack', 'answer' => 'Popcorn', 'points' => 100],
            ['letter' => 'P', 'question' => 'Bird that talks and lives on a pirate\'s shoulder', 'answer' => 'Parrot', 'points' => 100],
            ['letter' => 'P', 'question' => 'Carved Halloween vegetable', 'answer' => 'Pumpkin', 'points' => 100],
            ['letter' => 'P', 'question' => 'Black and white bamboo eater', 'answer' => 'Panda', 'points' => 100],
            ['letter' => 'P', 'question' => 'Breakfast hotcakes', 'answer' => 'Pancakes', 'points' => 100],
            ['letter' => 'P', 'question' => 'Royalty\'s home', 'answer' => 'Palace', 'points' => 100],
            ['letter' => 'P', 'question' => 'Spread that goes with jelly', 'answer' => 'Peanut Butter', 'points' => 100],
            ['letter' => 'P', 'question' => 'Pink farm animal', 'answer' => 'Pig', 'points' => 100],
            ['letter' => 'P', 'question' => 'Tropical fruit with spiky top', 'answer' => 'Pineapple', 'points' => 100],
            ['letter' => 'P', 'question' => 'Writing utensil with ink', 'answer' => 'Pen', 'points' => 100],
            ['letter' => 'P', 'question' => 'Italian noodles', 'answer' => 'Pasta', 'points' => 100],
            ['letter' => 'P', 'question' => 'Instrument with 88 keys', 'answer' => 'Piano', 'points' => 100],
            ['letter' => 'V', 'question' => 'Painter who cut off his ear', 'answer' => 'Van Gogh', 'points' => 200],
            ['letter' => 'P', 'question' => 'Legume in a pod', 'answer' => 'Pea', 'points' => 100],
            ['letter' => 'P', 'question' => 'Egyptian monument with a sphinx', 'answer' => 'Pyramid', 'points' => 100],
            ['letter' => 'S', 'question' => 'Peter Parker\'s superhero name', 'answer' => 'Spider-Man', 'points' => 100],
            ['letter' => 'P', 'question' => 'Captain Hook\'s nemesis', 'answer' => 'Peter Pan', 'points' => 100],
            ['letter' => 'P', 'question' => 'Ocean\'s deepest part', 'answer' => 'Pacific... Mariana Trench', 'points' => 300],
            ['letter' => 'P', 'question' => 'Dill, bread and butter, or kosher', 'answer' => 'Pickle', 'points' => 100],
            ['letter' => 'P', 'question' => 'Baby\'s soothing device', 'answer' => 'Pacifier', 'points' => 100],
            ['letter' => 'P', 'question' => 'Flightless Antarctic bird', 'answer' => 'Penguin', 'points' => 100],
            ['letter' => 'P', 'question' => 'What you use to take photos', 'answer' => 'Phone', 'points' => 100],
            ['letter' => 'P', 'question' => 'Yellow Pokémon mascot', 'answer' => 'Pikachu', 'points' => 100],
            ['letter' => 'P', 'question' => 'Greek god of the sea', 'answer' => 'Poseidon', 'points' => 200],
            ['letter' => 'P', 'question' => 'Thanksgiving side dish mashed', 'answer' => 'Potatoes', 'points' => 100],
            ['letter' => 'P', 'question' => 'What a mailman delivers', 'answer' => 'Packages', 'points' => 100],
            ['letter' => 'P', 'question' => 'State known for Philly cheesesteaks', 'answer' => 'Pennsylvania', 'points' => 100],
            ['letter' => 'P', 'question' => 'Quill and ink writing surface', 'answer' => 'Parchment', 'points' => 200],

            // Letter Q
            ['letter' => 'Q', 'question' => 'Royal woman married to a king', 'answer' => 'Queen', 'points' => 100],
            ['letter' => 'Q', 'question' => 'Feathered arrow projectile', 'answer' => 'Quiver', 'points' => 200],
            ['letter' => 'Q', 'question' => 'Twenty-five cents', 'answer' => 'Quarter', 'points' => 100],
            ['letter' => 'Q', 'question' => 'Trivia or test', 'answer' => 'Quiz', 'points' => 100],
            ['letter' => 'Q', 'question' => 'Soft bedding cover', 'answer' => 'Quilt', 'points' => 100],
            ['letter' => 'Q', 'question' => 'Fast, speedy', 'answer' => 'Quick', 'points' => 100],
            ['letter' => 'Q', 'question' => 'Famous hunchback cathedral in Paris', 'answer' => 'Quasimodo', 'points' => 200],
            ['letter' => 'Q', 'question' => 'Shaking like an earthquake', 'answer' => 'Quake', 'points' => 100],
            ['letter' => 'Q', 'question' => 'Music group of four singers', 'answer' => 'Quartet', 'points' => 100],
            ['letter' => 'Q', 'question' => 'Short-tailed game bird', 'answer' => 'Quail', 'points' => 100],
            ['letter' => 'Q', 'question' => 'Line waiting for service', 'answer' => 'Queue', 'points' => 100],
            ['letter' => 'Q', 'question' => 'Famous fictional spy\'s gadget master', 'answer' => 'Q (James Bond)', 'points' => 200],
            ['letter' => 'Q', 'question' => 'Australian airline', 'answer' => 'Qantas', 'points' => 200],
            ['letter' => 'Q', 'question' => 'Strange and unusual', 'answer' => 'Quirky', 'points' => 100],
            ['letter' => 'Q', 'question' => 'South American empire builders', 'answer' => 'Quechua', 'points' => 300],

            // Letter R
            ['letter' => 'R', 'question' => 'Colors in the sky after rain', 'answer' => 'Rainbow', 'points' => 100],
            ['letter' => 'R', 'question' => 'Hopping marsupial from Australia', 'answer' => 'Kangaroo...Roo', 'points' => 200],
            ['letter' => 'R', 'question' => 'Long-eared hopping animal', 'answer' => 'Rabbit', 'points' => 100],
            ['letter' => 'R', 'question' => 'Precipitation from clouds', 'answer' => 'Rain', 'points' => 100],
            ['letter' => 'R', 'question' => 'Gemstone that\'s deep red', 'answer' => 'Ruby', 'points' => 100],
            ['letter' => 'R', 'question' => 'Shiny metal robot material', 'answer' => 'Robot', 'points' => 100],
            ['letter' => 'R', 'question' => 'Hot dog or bun ingredient', 'answer' => 'Roll', 'points' => 100],
            ['letter' => 'R', 'question' => 'Type of music Elvis sang', 'answer' => 'Rock and Roll', 'points' => 100],
            ['letter' => 'R', 'question' => 'Space vehicle', 'answer' => 'Rocket', 'points' => 100],
            ['letter' => 'R', 'question' => 'McDonald\'s clown mascot', 'answer' => 'Ronald', 'points' => 100],
            ['letter' => 'R', 'question' => 'Cupid\'s flower', 'answer' => 'Rose', 'points' => 100],
            ['letter' => 'R', 'question' => 'What tires are made of', 'answer' => 'Rubber', 'points' => 100],
            ['letter' => 'R', 'question' => 'Sherwood Forest outlaw', 'answer' => 'Robin Hood', 'points' => 100],
            ['letter' => 'R', 'question' => 'What Santa\'s helpers fly', 'answer' => 'Reindeer', 'points' => 100],
            ['letter' => 'R', 'question' => 'Slithering legless reptile', 'answer' => 'Rattlesnake', 'points' => 200],
            ['letter' => 'R', 'question' => 'Juliet\'s forbidden love', 'answer' => 'Romeo', 'points' => 100],
            ['letter' => 'R', 'question' => 'Wrestling ring shape', 'answer' => 'Ring', 'points' => 100],
            ['letter' => 'R', 'question' => 'Woody Woodpecker or Daffy Duck\'s mate', 'answer' => 'Roadrunner', 'points' => 200],
            ['letter' => 'R', 'question' => 'Spaghetti or tomato sauce in a jar', 'answer' => 'Ragu', 'points' => 200],
            ['letter' => 'R', 'question' => 'Baby\'s noise making toy', 'answer' => 'Rattle', 'points' => 100],
            ['letter' => 'S', 'question' => 'State with Mount Rushmore', 'answer' => 'South Dakota', 'points' => 100],
            ['letter' => 'R', 'question' => 'Word for king in Latin languages', 'answer' => 'Rex', 'points' => 200],
            ['letter' => 'M', 'question' => 'Pirate\'s buried treasure finder', 'answer' => 'Map', 'points' => 100],

            // Letter S
            ['letter' => 'S', 'question' => 'Yellow celestial body that gives us light', 'answer' => 'Sun', 'points' => 100],
            ['letter' => 'S', 'question' => 'Eight-legged web spinner', 'answer' => 'Spider', 'points' => 100],
            ['letter' => 'S', 'question' => 'Legless slithering reptile', 'answer' => 'Snake', 'points' => 100],
            ['letter' => 'S', 'question' => 'Slow-moving shelled garden creature', 'answer' => 'Snail', 'points' => 100],
            ['letter' => 'S', 'question' => 'White frozen precipitation', 'answer' => 'Snow', 'points' => 100],
            ['letter' => 'S', 'question' => 'Ocean predator with fins', 'answer' => 'Shark', 'points' => 100],
            ['letter' => 'S', 'question' => 'Footwear for athletes', 'answer' => 'Sneakers', 'points' => 100],
            ['letter' => 'S', 'question' => 'Strawberry or chocolate treat', 'answer' => 'Smoothie', 'points' => 100],
            ['letter' => 'S', 'question' => 'Man of Steel superhero', 'answer' => 'Superman', 'points' => 100],
            ['letter' => 'S', 'question' => 'Beach granules', 'answer' => 'Sand', 'points' => 100],
            ['letter' => 'S', 'question' => 'Italian cold cut meat', 'answer' => 'Salami', 'points' => 100],
            ['letter' => 'S', 'question' => 'Underwater vessel', 'answer' => 'Submarine', 'points' => 100],
            ['letter' => 'S', 'question' => 'Type of pasta shaped like shells', 'answer' => 'Shells', 'points' => 100],
            ['letter' => 'T', 'question' => 'Winnie the Pooh\'s bouncing friend', 'answer' => 'Tigger', 'points' => 200],
            ['letter' => 'S', 'question' => 'Where kids go to learn', 'answer' => 'School', 'points' => 100],
            ['letter' => 'S', 'question' => 'Season after winter', 'answer' => 'Spring', 'points' => 100],
            ['letter' => 'S', 'question' => 'Twilight Zone host Rod', 'answer' => 'Serling', 'points' => 200],
            ['letter' => 'L', 'question' => 'Scottish monster\'s lake', 'answer' => 'Loch Ness', 'points' => 200],
            ['letter' => 'P', 'question' => 'SpongeBob\'s starfish friend', 'answer' => 'Patrick', 'points' => 100],
            ['letter' => 'H', 'question' => 'Bart and Lisa\'s dad', 'answer' => 'Homer Simpson', 'points' => 100],
            ['letter' => 'S', 'question' => 'Green Eggs and Ham author', 'answer' => 'Dr. Seuss', 'points' => 100],
            ['letter' => 'S', 'question' => 'Night sky twinkler', 'answer' => 'Star', 'points' => 100],
            ['letter' => 'S', 'question' => 'Vessel that sails the seas', 'answer' => 'Ship', 'points' => 100],
            ['letter' => 'S', 'question' => 'What you walk down on each floor', 'answer' => 'Stairs', 'points' => 100],
            ['letter' => 'S', 'question' => 'Fizzy soft drink', 'answer' => 'Soda', 'points' => 100],
            ['letter' => 'S', 'question' => 'Leafy green salad vegetable', 'answer' => 'Spinach', 'points' => 100],
            ['letter' => 'S', 'question' => 'Peanuts beagle', 'answer' => 'Snoopy', 'points' => 100],
            ['letter' => 'S', 'question' => 'What Spider-Man shoots', 'answer' => 'Silk... Spiderweb', 'points' => 100],
            ['letter' => 'P', 'question' => 'Thanksgiving boat passengers', 'answer' => 'Pilgrims', 'points' => 100],
            ['letter' => 'S', 'question' => 'City by the Golden Gate', 'answer' => 'San Francisco', 'points' => 100],

            // Letter T
            ['letter' => 'T', 'question' => 'Striped jungle cat', 'answer' => 'Tiger', 'points' => 100],
            ['letter' => 'T', 'question' => 'Red fruit often mistaken for a vegetable', 'answer' => 'Tomato', 'points' => 100],
            ['letter' => 'T', 'question' => 'November holiday for giving thanks', 'answer' => 'Thanksgiving', 'points' => 100],
            ['letter' => 'T', 'question' => 'Device that shows moving pictures', 'answer' => 'Television', 'points' => 100],
            ['letter' => 'T', 'question' => 'Hot beverage from leaves', 'answer' => 'Tea', 'points' => 100],
            ['letter' => 'T', 'question' => 'What you brush twice a day', 'answer' => 'Teeth', 'points' => 100],
            ['letter' => 'T', 'question' => 'Portable shelter for camping', 'answer' => 'Tent', 'points' => 100],
            ['letter' => 'T', 'question' => 'Tall structure in Paris', 'answer' => 'Eiffel Tower', 'points' => 100],
            ['letter' => 'T', 'question' => 'Vehicle that travels on tracks', 'answer' => 'Train', 'points' => 100],
            ['letter' => 'T', 'question' => 'Large woody plant', 'answer' => 'Tree', 'points' => 100],
            ['letter' => 'F', 'question' => 'Eating utensil with prongs', 'answer' => 'Fork', 'points' => 100],
            ['letter' => 'T', 'question' => 'Shelled reptile that moves slowly', 'answer' => 'Turtle', 'points' => 100],
            ['letter' => 'T', 'question' => 'Mexican folded shell meal', 'answer' => 'Taco', 'points' => 100],
            ['letter' => 'T', 'question' => 'Sport with rackets and balls', 'answer' => 'Tennis', 'points' => 100],
            ['letter' => 'T', 'question' => 'Soft bathroom tissue', 'answer' => 'Toilet Paper', 'points' => 100],
            ['letter' => 'T', 'question' => 'Lone Star state', 'answer' => 'Texas', 'points' => 100],
            ['letter' => 'T', 'question' => 'Cruise ship disaster of 1912', 'answer' => 'Titanic', 'points' => 100],
            ['letter' => 'T', 'question' => 'Baby frog', 'answer' => 'Tadpole', 'points' => 100],
            ['letter' => 'R', 'question' => 'What\'s on your finger for wedding', 'answer' => 'Ring', 'points' => 100],
            ['letter' => 'T', 'question' => 'Bread browning appliance', 'answer' => 'Toaster', 'points' => 100],
            ['letter' => 'T', 'question' => 'Prehistoric T-Rex was one', 'answer' => 'Tyrannosaurus', 'points' => 200],
            ['letter' => 'H', 'question' => 'Japanese city hit by the bomb', 'answer' => 'Hiroshima', 'points' => 200],
            ['letter' => 'T', 'question' => 'What covers a roof', 'answer' => 'Tiles', 'points' => 100],
            ['letter' => 'T', 'question' => 'Peter Pan\'s fairy friend', 'answer' => 'Tinkerbell', 'points' => 100],
            ['letter' => 'T', 'question' => 'Greek mythological city attacked by horse', 'answer' => 'Troy', 'points' => 200],
            ['letter' => 'T', 'question' => 'What you use to make a call', 'answer' => 'Telephone', 'points' => 100],
            ['letter' => 'B', 'question' => 'Dancing shoes with ribbons', 'answer' => 'Ballet', 'points' => 200],
            ['letter' => 'T', 'question' => 'What the dentist uses', 'answer' => 'Tools', 'points' => 100],
            ['letter' => 'N', 'question' => 'City in Tennessee with country music', 'answer' => 'Nashville', 'points' => 100],
            ['letter' => 'T', 'question' => 'Thanksgiving main course bird', 'answer' => 'Turkey', 'points' => 100],

            // Letter U
            ['letter' => 'U', 'question' => 'Rain protection you carry', 'answer' => 'Umbrella', 'points' => 100],
            ['letter' => 'U', 'question' => 'Mythical one-horned horse', 'answer' => 'Unicorn', 'points' => 100],
            ['letter' => 'U', 'question' => 'Below your shirt garment', 'answer' => 'Underwear', 'points' => 100],
            ['letter' => 'U', 'question' => 'Above and beyond everything', 'answer' => 'Universe', 'points' => 100],
            ['letter' => 'U', 'question' => 'Sam wants you for the army', 'answer' => 'Uncle Sam', 'points' => 100],
            ['letter' => 'U', 'question' => 'Married state of couples', 'answer' => 'Union', 'points' => 200],
            ['letter' => 'U', 'question' => 'Not down, but...', 'answer' => 'Up', 'points' => 100],
            ['letter' => 'U', 'question' => 'School after high school', 'answer' => 'University', 'points' => 100],
            ['letter' => 'U', 'question' => 'Hawaiian stringed instrument', 'answer' => 'Ukulele', 'points' => 100],
            ['letter' => 'U', 'question' => 'United Kingdom resident', 'answer' => 'UK', 'points' => 100],
            ['letter' => 'U', 'question' => 'Referee calls unfair play', 'answer' => 'Unsportsmanlike', 'points' => 200],
            ['letter' => 'U', 'question' => 'Alien spacecraft', 'answer' => 'UFO', 'points' => 100],
            ['letter' => 'U', 'question' => 'Nether world of Greek mythology', 'answer' => 'Underworld', 'points' => 200],
            ['letter' => 'U', 'question' => 'Not typical or ordinary', 'answer' => 'Unusual', 'points' => 100],
            ['letter' => 'U', 'question' => 'Not the lower class', 'answer' => 'Upper Class', 'points' => 100],

            // Letter V
            ['letter' => 'V', 'question' => 'Blood-sucking creature of the night', 'answer' => 'Vampire', 'points' => 100],
            ['letter' => 'V', 'question' => 'Romantic holiday in February', 'answer' => 'Valentine\'s Day', 'points' => 100],
            ['letter' => 'V', 'question' => 'Stringed instrument played with a bow', 'answer' => 'Violin', 'points' => 100],
            ['letter' => 'V', 'question' => 'Flower arrangement in a container', 'answer' => 'Vase', 'points' => 100],
            ['letter' => 'V', 'question' => 'Italian city with canals', 'answer' => 'Venice', 'points' => 100],
            ['letter' => 'V', 'question' => 'Cleaning appliance that sucks', 'answer' => 'Vacuum', 'points' => 100],
            ['letter' => 'V', 'question' => 'Green climbing plant', 'answer' => 'Vine', 'points' => 100],
            ['letter' => 'V', 'question' => 'Doctor\'s animal patient specialist', 'answer' => 'Veterinarian', 'points' => 200],
            ['letter' => 'V', 'question' => 'Word for winning', 'answer' => 'Victory', 'points' => 100],
            ['letter' => 'V', 'question' => 'Star Wars villain in black', 'answer' => 'Vader', 'points' => 100],
            ['letter' => 'V', 'question' => 'Mount that erupted over Pompeii', 'answer' => 'Vesuvius', 'points' => 200],
            ['letter' => 'V', 'question' => 'State that\'s for lovers', 'answer' => 'Virginia', 'points' => 100],
            ['letter' => 'V', 'question' => 'Las Vegas destination', 'answer' => 'Vegas', 'points' => 100],
            ['letter' => 'V', 'question' => 'Opposite of invisible', 'answer' => 'Visible', 'points' => 100],
            ['letter' => 'B', 'question' => 'Male voice between tenor and bass', 'answer' => 'Baritone', 'points' => 200],
            ['letter' => 'V', 'question' => 'Mountain range peak view', 'answer' => 'Vista', 'points' => 200],
            ['letter' => 'V', 'question' => 'Ancient Roman greeting', 'answer' => 'Vale', 'points' => 300],
            ['letter' => 'V', 'question' => 'Sour wine used in dressings', 'answer' => 'Vinegar', 'points' => 100],
            ['letter' => 'V', 'question' => 'What Superman is not', 'answer' => 'Villain', 'points' => 100],
            ['letter' => 'V', 'question' => 'Small rural community', 'answer' => 'Village', 'points' => 100],

            // Letter W
            ['letter' => 'W', 'question' => 'H2O liquid', 'answer' => 'Water', 'points' => 100],
            ['letter' => 'W', 'question' => 'Ocean\'s largest mammal', 'answer' => 'Whale', 'points' => 100],
            ['letter' => 'W', 'question' => 'Time-telling wrist device', 'answer' => 'Watch', 'points' => 100],
            ['letter' => 'W', 'question' => 'Glass opening in a wall', 'answer' => 'Window', 'points' => 100],
            ['letter' => 'W', 'question' => 'Moving air current', 'answer' => 'Wind', 'points' => 100],
            ['letter' => 'W', 'question' => 'Cold season after fall', 'answer' => 'Winter', 'points' => 100],
            ['letter' => 'W', 'question' => 'Dorothy\'s Oz enemy', 'answer' => 'Wicked Witch', 'points' => 100],
            ['letter' => 'W', 'question' => 'Howling canine predator', 'answer' => 'Wolf', 'points' => 100],
            ['letter' => 'W', 'question' => 'Cinderella\'s fairy godmother has one', 'answer' => 'Wand', 'points' => 100],
            ['letter' => 'W', 'question' => 'Round shape like a tire', 'answer' => 'Wheel', 'points' => 100],
            ['letter' => 'S', 'question' => 'Day of rest in Judaism', 'answer' => 'Sabbath', 'points' => 100],
            ['letter' => 'W', 'question' => 'What you pour from a bottle', 'answer' => 'Wine', 'points' => 100],
            ['letter' => 'W', 'question' => 'Marry someone', 'answer' => 'Wed', 'points' => 100],
            ['letter' => 'W', 'question' => 'Enchanting spell caster', 'answer' => 'Wizard', 'points' => 100],
            ['letter' => 'W', 'question' => 'George who chopped the cherry tree', 'answer' => 'Washington', 'points' => 100],
            ['letter' => 'W', 'question' => 'Flat round breakfast food', 'answer' => 'Waffle', 'points' => 100],
            ['letter' => 'W', 'question' => 'Laundry cleaning appliance', 'answer' => 'Washer', 'points' => 100],
            ['letter' => 'W', 'question' => 'Spider\'s silky creation', 'answer' => 'Web', 'points' => 100],
            ['letter' => 'W', 'question' => 'What boxers do in the ring', 'answer' => 'Wrestling', 'points' => 100],
            ['letter' => 'W', 'question' => 'Wild west frontier', 'answer' => 'West', 'points' => 100],
            ['letter' => 'B', 'question' => 'Charlie Brown\'s dog type', 'answer' => 'Beagle', 'points' => 200],
            ['letter' => 'P', 'question' => 'Bathing beauty of Baywatch', 'answer' => 'Pamela Anderson', 'points' => 200],
            ['letter' => 'C', 'question' => 'Oprah\'s talk show city', 'answer' => 'Chicago', 'points' => 100],
            ['letter' => 'W', 'question' => 'What athletes do at crossfit', 'answer' => 'Workout', 'points' => 100],

            // Letter X
            ['letter' => 'X', 'question' => 'Medical imaging that sees bones', 'answer' => 'X-ray', 'points' => 100],
            ['letter' => 'X', 'question' => 'Wolverine\'s mutant team', 'answer' => 'X-Men', 'points' => 100],
            ['letter' => 'X', 'question' => 'Wooden percussion instrument', 'answer' => 'Xylophone', 'points' => 100],
            ['letter' => 'X', 'question' => 'Ancient Persian king', 'answer' => 'Xerxes', 'points' => 300],
            ['letter' => 'X', 'question' => 'Copy machine brand', 'answer' => 'Xerox', 'points' => 100],
            ['letter' => 'X', 'question' => 'Fear of strangers', 'answer' => 'Xenophobia', 'points' => 300],
            ['letter' => 'X', 'question' => 'Extra large size abbreviation', 'answer' => 'XL', 'points' => 100],
            ['letter' => 'X', 'question' => 'Love and this on a letter', 'answer' => 'XO', 'points' => 100],
            ['letter' => 'X', 'question' => 'Microsoft\'s gaming console', 'answer' => 'Xbox', 'points' => 100],

            // Letter Y
            ['letter' => 'Y', 'question' => 'Color of the sun and bananas', 'answer' => 'Yellow', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Opposite of old', 'answer' => 'Young', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Fuzzy string you knit with', 'answer' => 'Yarn', 'points' => 100],
            ['letter' => 'Y', 'question' => 'What you do when sleepy', 'answer' => 'Yawn', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Fermented milk product', 'answer' => 'Yogurt', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Egg center', 'answer' => 'Yolk', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Abominable snowman', 'answer' => 'Yeti', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Happy exclamation', 'answer' => 'Yay', 'points' => 100],
            ['letter' => 'Y', 'question' => 'What pirates say', 'answer' => 'Yo ho ho', 'points' => 100],
            ['letter' => 'Y', 'question' => '365 days', 'answer' => 'Year', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Green Star Wars Jedi master', 'answer' => 'Yoda', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Casual greeting', 'answer' => 'Yo', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Internet video website', 'answer' => 'YouTube', 'points' => 100],
            ['letter' => 'Y', 'question' => 'New York baseball team', 'answer' => 'Yankees', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Yelling loudly', 'answer' => 'Yelling', 'points' => 100],

            // Letter Z
            ['letter' => 'Z', 'question' => 'Black and white striped horse', 'answer' => 'Zebra', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Where animals live in enclosures', 'answer' => 'Zoo', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Mask-wearing Spanish swordsman', 'answer' => 'Zorro', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Nothing, nada', 'answer' => 'Zero', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Greek god of thunder', 'answer' => 'Zeus', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Fast sliding fastener', 'answer' => 'Zipper', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Walking dead creature', 'answer' => 'Zombie', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Postal code numbers', 'answer' => 'Zip Code', 'points' => 100],
            ['letter' => 'B', 'question' => 'What bees do', 'answer' => 'Buzz', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Enthusiastic passion', 'answer' => 'Zeal', 'points' => 200],
            ['letter' => 'Z', 'question' => 'Time region (Eastern, Pacific, etc.)', 'answer' => 'Zone', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Lemon or orange flavoring', 'answer' => 'Zest', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Quick lightning bolt shape', 'answer' => 'Zigzag', 'points' => 100],
            ['letter' => 'Z', 'question' => 'Italian squash vegetable', 'answer' => 'Zucchini', 'points' => 100],

            // Additional A questions
            ['letter' => 'A', 'question' => 'Largest online retailer', 'answer' => 'Amazon', 'points' => 100],
            ['letter' => 'A', 'question' => 'Green bumpy fruit for guacamole', 'answer' => 'Avocado', 'points' => 100],
            ['letter' => 'G', 'question' => 'Tall animal from Africa', 'answer' => 'Giraffe', 'points' => 100],
            ['letter' => 'A', 'question' => 'Month of April showers', 'answer' => 'April', 'points' => 100],
            ['letter' => 'A', 'question' => 'Type of nut in almond milk', 'answer' => 'Almond', 'points' => 100],
            ['letter' => 'A', 'question' => 'Wonder Woman superhero', 'answer' => 'Amazon', 'points' => 200],
            ['letter' => 'A', 'question' => 'Cold continent at the bottom', 'answer' => 'Antarctica', 'points' => 100],
            ['letter' => 'T', 'question' => 'Farm machine for plowing', 'answer' => 'Tractor', 'points' => 100],
            ['letter' => 'A', 'question' => 'Greek goddess of wisdom', 'answer' => 'Athena', 'points' => 200],
            ['letter' => 'A', 'question' => 'Land down under', 'answer' => 'Australia', 'points' => 100],

            // Additional B questions
            ['letter' => 'B', 'question' => 'Game with pins and a heavy ball', 'answer' => 'Bowling', 'points' => 100],
            ['letter' => 'B', 'question' => 'Type of bread for burgers', 'answer' => 'Bun', 'points' => 100],
            ['letter' => 'B', 'question' => 'Book that\'s sacred to Christians', 'answer' => 'Bible', 'points' => 100],
            ['letter' => 'B', 'question' => 'Sweet treat at a bakery', 'answer' => 'Brownie', 'points' => 100],
            ['letter' => 'B', 'question' => 'Winged insects that pollinate', 'answer' => 'Butterflies', 'points' => 100],
            ['letter' => 'B', 'question' => 'Capital of Germany', 'answer' => 'Berlin', 'points' => 200],
            ['letter' => 'B', 'question' => 'Musical group with four musicians', 'answer' => 'Band', 'points' => 100],
            ['letter' => 'B', 'question' => 'Baby\'s wheeled transport', 'answer' => 'Buggy', 'points' => 100],
            ['letter' => 'B', 'question' => 'British music invasion group', 'answer' => 'Beatles', 'points' => 100],
            ['letter' => 'B', 'question' => 'Large hairy bovine', 'answer' => 'Bison', 'points' => 200],

            // Additional C questions
            ['letter' => 'C', 'question' => 'Dessert after dinner', 'answer' => 'Cheesecake', 'points' => 100],
            ['letter' => 'C', 'question' => 'Transportation vehicle with wheels', 'answer' => 'Car', 'points' => 100],
            ['letter' => 'C', 'question' => 'Crunchy orange vegetable stick', 'answer' => 'Carrot', 'points' => 100],
            ['letter' => 'C', 'question' => 'December 25th holiday', 'answer' => 'Christmas', 'points' => 100],
            ['letter' => 'C', 'question' => 'Dairy product from milk', 'answer' => 'Cheese', 'points' => 100],
            ['letter' => 'C', 'question' => 'Sweet baked treat for kids', 'answer' => 'Cookie', 'points' => 100],
            ['letter' => 'C', 'question' => 'Circus performer who juggles', 'answer' => 'Clown', 'points' => 100],
            ['letter' => 'I', 'question' => 'Frozen water cube', 'answer' => 'Ice', 'points' => 100],
            ['letter' => 'C', 'question' => 'Corn on the...', 'answer' => 'Cob', 'points' => 100],
            ['letter' => 'C', 'question' => 'Classic Disney princess in a pumpkin', 'answer' => 'Cinderella', 'points' => 100],

            // Additional D questions
            ['letter' => 'D', 'question' => 'Doctor who works on teeth', 'answer' => 'Dentist', 'points' => 100],
            ['letter' => 'D', 'question' => 'Dry waterless region', 'answer' => 'Desert', 'points' => 100],
            ['letter' => 'D', 'question' => 'Playing cards suit with red shape', 'answer' => 'Diamonds', 'points' => 100],
            ['letter' => 'D', 'question' => 'Scrooge McDuck\'s vault is full of', 'answer' => 'Dollars', 'points' => 100],
            ['letter' => 'D', 'question' => 'Canine that guards the house', 'answer' => 'Dog', 'points' => 100],
            ['letter' => 'D', 'question' => 'Mythical fire-breathing creature', 'answer' => 'Dragon', 'points' => 100],
            ['letter' => 'D', 'question' => 'Musical group\'s percussion player', 'answer' => 'Drummer', 'points' => 100],
            ['letter' => 'D', 'question' => 'Baby\'s bottom cover', 'answer' => 'Diaper', 'points' => 100],
            ['letter' => 'D', 'question' => 'Walt who created Mickey', 'answer' => 'Disney', 'points' => 100],

            // Additional E questions
            ['letter' => 'E', 'question' => 'What powers most homes', 'answer' => 'Electricity', 'points' => 100],
            ['letter' => 'E', 'question' => 'Tall structure that goes up and down', 'answer' => 'Elevator', 'points' => 100],
            ['letter' => 'E', 'question' => 'Breakfast omelet ingredient', 'answer' => 'Eggs', 'points' => 100],
            ['letter' => 'E', 'question' => 'Continent with France and Spain', 'answer' => 'Europe', 'points' => 100],
            ['letter' => 'E', 'question' => 'Fire truck or ambulance', 'answer' => 'Emergency', 'points' => 100],
            ['letter' => 'E', 'question' => 'Wrapped and sealed letter container', 'answer' => 'Envelope', 'points' => 100],
            ['letter' => 'E', 'question' => 'Motor that powers a car', 'answer' => 'Engine', 'points' => 100],
            ['letter' => 'E', 'question' => 'What you call yourself', 'answer' => 'Ego', 'points' => 200],
            ['letter' => 'E', 'question' => 'Night before a holiday', 'answer' => 'Eve', 'points' => 100],
            ['letter' => 'E', 'question' => 'Escape plan for fires', 'answer' => 'Exit', 'points' => 100],

            // Additional F questions
            ['letter' => 'F', 'question' => 'Red engine that fights blazes', 'answer' => 'Firetruck', 'points' => 100],
            ['letter' => 'F', 'question' => 'What you catch fish with', 'answer' => 'Fishing Rod', 'points' => 100],
            ['letter' => 'I', 'question' => 'Frozen water', 'answer' => 'Ice', 'points' => 100],
            ['letter' => 'F', 'question' => 'What birds have that help them fly', 'answer' => 'Feathers', 'points' => 100],
            ['letter' => 'F', 'question' => 'Place where crops grow', 'answer' => 'Farm', 'points' => 100],
            ['letter' => 'M', 'question' => 'Simba\'s dad in Lion King', 'answer' => 'Mufasa', 'points' => 100],
            ['letter' => 'F', 'question' => 'French delicacy of fried potatoes', 'answer' => 'French Fries', 'points' => 100],
            ['letter' => 'F', 'question' => 'Eating with fingers', 'answer' => 'Finger Food', 'points' => 100],
            ['letter' => 'B', 'question' => 'Fluttering insect in a garden', 'answer' => 'Butterfly', 'points' => 100],

            // Additional I questions (new letter)
            ['letter' => 'I', 'question' => 'Frozen dairy dessert', 'answer' => 'Ice Cream', 'points' => 100],
            ['letter' => 'I', 'question' => 'Country shaped like a boot', 'answer' => 'Italy', 'points' => 100],
            ['letter' => 'I', 'question' => 'World wide web', 'answer' => 'Internet', 'points' => 100],
            ['letter' => 'I', 'question' => 'Frozen water cube', 'answer' => 'Ice', 'points' => 100],
            ['letter' => 'I', 'question' => 'Tropical vacation spot', 'answer' => 'Island', 'points' => 100],
            ['letter' => 'I', 'question' => 'What gets you into buildings', 'answer' => 'ID', 'points' => 100],
            ['letter' => 'I', 'question' => 'Shot that prevents disease', 'answer' => 'Immunization', 'points' => 200],
            ['letter' => 'I', 'question' => 'What you use for ironing clothes', 'answer' => 'Iron', 'points' => 100],
            ['letter' => 'I', 'question' => 'Color between blue and violet', 'answer' => 'Indigo', 'points' => 200],

            // Additional K questions (new letter)
            ['letter' => 'K', 'question' => 'Flying toy on a string', 'answer' => 'Kite', 'points' => 100],
            ['letter' => 'K', 'question' => 'Royalty who wears a crown', 'answer' => 'King', 'points' => 100],
            ['letter' => 'K', 'question' => 'Australian hopping animal', 'answer' => 'Kangaroo', 'points' => 100],
            ['letter' => 'K', 'question' => 'Sharp tool for cutting', 'answer' => 'Knife', 'points' => 100],
            ['letter' => 'K', 'question' => 'Small child', 'answer' => 'Kid', 'points' => 100],
            ['letter' => 'O', 'question' => 'Japanese art of folding paper', 'answer' => 'Origami', 'points' => 100],
            ['letter' => 'K', 'question' => 'Type of Asian martial arts', 'answer' => 'Karate', 'points' => 100],
            ['letter' => 'O', 'question' => 'Hot dog brand', 'answer' => 'Oscar Mayer', 'points' => 100],
            ['letter' => 'K', 'question' => 'What locksmiths make', 'answer' => 'Keys', 'points' => 100],
            ['letter' => 'H', 'question' => 'Tomato condiment brand', 'answer' => 'Heinz', 'points' => 100],
            ['letter' => 'K', 'question' => 'Playground ball game', 'answer' => 'Kickball', 'points' => 100],
            ['letter' => 'K', 'question' => 'Dorothy\'s home state', 'answer' => 'Kansas', 'points' => 100],
            ['letter' => 'D', 'question' => 'Shrek\'s green friend', 'answer' => 'Donkey', 'points' => 100],
            ['letter' => 'K', 'question' => 'What you blow a kiss with', 'answer' => 'Kiss', 'points' => 100],
            ['letter' => 'K', 'question' => 'Fuzzy fruit with brown skin', 'answer' => 'Kiwi', 'points' => 100],

            // Additional L questions (new letter)
            ['letter' => 'L', 'question' => 'King of the jungle', 'answer' => 'Lion', 'points' => 100],
            ['letter' => 'L', 'question' => 'Yellow citrus fruit', 'answer' => 'Lemon', 'points' => 100],
            ['letter' => 'L', 'question' => 'Where you borrow books', 'answer' => 'Library', 'points' => 100],
            ['letter' => 'L', 'question' => 'Illumination device in your home', 'answer' => 'Lamp', 'points' => 100],
            ['letter' => 'L', 'question' => 'Green juicy fruit like a lemon', 'answer' => 'Lime', 'points' => 100],
            ['letter' => 'L', 'question' => 'Pasta in sheets', 'answer' => 'Lasagna', 'points' => 100],
            ['letter' => 'H', 'question' => 'Abe Lincoln\'s nickname', 'answer' => 'Honest Abe', 'points' => 100],
            ['letter' => 'L', 'question' => 'What lights the night', 'answer' => 'Lightning', 'points' => 100],
            ['letter' => 'L', 'question' => 'Lips stick cosmetic', 'answer' => 'Lipstick', 'points' => 100],
            ['letter' => 'L', 'question' => 'Crustacean with big claws', 'answer' => 'Lobster', 'points' => 100],
            ['letter' => 'L', 'question' => 'Sin city in Nevada', 'answer' => 'Las Vegas', 'points' => 100],
            ['letter' => 'L', 'question' => 'Green garden lizard', 'answer' => 'Lizard', 'points' => 100],
            ['letter' => 'K', 'question' => 'What you put in a lock', 'answer' => 'Key', 'points' => 100],
            ['letter' => 'L', 'question' => 'Body of water smaller than an ocean', 'answer' => 'Lake', 'points' => 100],

            // Sports Questions (100 questions)
            // Letter A
            ['letter' => 'A', 'question' => 'Boxing legend Muhammad', 'answer' => 'Ali', 'points' => 100],
            ['letter' => 'A', 'question' => 'Track and field events combined', 'answer' => 'Athletics', 'points' => 100],
            ['letter' => 'A', 'question' => 'Houston baseball team', 'answer' => 'Astros', 'points' => 100],
            ['letter' => 'A', 'question' => 'Oakland baseball team', 'answer' => 'Athletics', 'points' => 100],
            ['letter' => 'A', 'question' => 'Atlanta basketball team', 'answer' => 'Atlanta Hawks', 'points' => 100],

            // Letter B
            ['letter' => 'B', 'question' => 'Sport with hoops and a round ball', 'answer' => 'Basketball', 'points' => 100],
            ['letter' => 'B', 'question' => 'America\'s pastime sport', 'answer' => 'Baseball', 'points' => 100],
            ['letter' => 'B', 'question' => 'Tom who won 7 Super Bowls', 'answer' => 'Brady', 'points' => 100],
            ['letter' => 'B', 'question' => 'Boston basketball team', 'answer' => 'Boston Celtics', 'points' => 100],
            ['letter' => 'B', 'question' => 'Sport with gloves and a ring', 'answer' => 'Boxing', 'points' => 100],
            ['letter' => 'B', 'question' => 'Buffalo football team', 'answer' => 'Bills', 'points' => 100],
            ['letter' => 'B', 'question' => 'Kobe who wore number 24', 'answer' => 'Bryant', 'points' => 100],
            ['letter' => 'B', 'question' => 'Baltimore baseball team', 'answer' => 'Baltimore Orioles', 'points' => 100],

            // Letter C
            ['letter' => 'C', 'question' => 'Chicago baseball team with a curse', 'answer' => 'Cubs', 'points' => 100],
            ['letter' => 'C', 'question' => 'Stephen who revolutionized 3-point shooting', 'answer' => 'Curry', 'points' => 100],
            ['letter' => 'C', 'question' => 'Dallas football team', 'answer' => 'Cowboys', 'points' => 100],
            ['letter' => 'C', 'question' => 'Cleveland basketball team', 'answer' => 'Cavaliers', 'points' => 100],
            ['letter' => 'C', 'question' => 'Roberto who broke baseball\'s color barrier in Pittsburgh', 'answer' => 'Clemente', 'points' => 200],
            ['letter' => 'F', 'question' => 'Roger who won Wimbledon rival', 'answer' => 'Federer', 'points' => 200],

            // Letter D
            ['letter' => 'D', 'question' => 'Denver football team', 'answer' => 'Denver Broncos', 'points' => 100],
            ['letter' => 'H', 'question' => 'Miami basketball team with LeBron', 'answer' => 'Heat', 'points' => 200],
            ['letter' => 'B', 'question' => 'Sport with a net and birdie', 'answer' => 'Badminton', 'points' => 100],
            ['letter' => 'P', 'question' => 'Detroit basketball team', 'answer' => 'Pistons', 'points' => 100],
            ['letter' => 'D', 'question' => 'Arizona baseball team', 'answer' => 'Diamondbacks', 'points' => 100],

            // Letter E
            ['letter' => 'E', 'question' => 'Philadelphia football team', 'answer' => 'Eagles', 'points' => 100],
            ['letter' => 'M', 'question' => 'John who won Wimbledon', 'answer' => 'McEnroe', 'points' => 100],

            // Letter F
            ['letter' => 'F', 'question' => 'American sport with touchdowns', 'answer' => 'Football', 'points' => 100],
            ['letter' => 'F', 'question' => 'San Francisco football team', 'answer' => 'Forty-Niners', 'points' => 100],
            ['letter' => 'F', 'question' => 'Roger who has 20 Grand Slams', 'answer' => 'Federer', 'points' => 100],
            ['letter' => 'W', 'question' => 'LeBron\'s Heat teammate Dwyane', 'answer' => 'Wade', 'points' => 200],

            // Letter G
            ['letter' => 'G', 'question' => 'Sport played on a course with clubs', 'answer' => 'Golf', 'points' => 100],
            ['letter' => 'P', 'question' => 'Green Bay football team', 'answer' => 'Packers', 'points' => 100],
            ['letter' => 'G', 'question' => 'Wayne known as The Great One', 'answer' => 'Gretzky', 'points' => 100],
            ['letter' => 'W', 'question' => 'Golden State basketball team', 'answer' => 'Warriors', 'points' => 100],
            ['letter' => 'G', 'question' => 'Sport with floor exercises and balance beam', 'answer' => 'Gymnastics', 'points' => 100],

            // Letter H
            ['letter' => 'H', 'question' => 'Sport played on ice with pucks', 'answer' => 'Hockey', 'points' => 100],
            ['letter' => 'H', 'question' => 'Miami basketball team', 'answer' => 'Heat', 'points' => 100],
            ['letter' => 'R', 'question' => 'Houston basketball team', 'answer' => 'Rockets', 'points' => 100],
            ['letter' => 'H', 'question' => 'Ken Griffey Jr\'s number was 630', 'answer' => 'Home Runs', 'points' => 100],
            ['letter' => 'T', 'question' => 'Houston football team', 'answer' => 'Houston Texans', 'points' => 100],

            // Letter J
            ['letter' => 'J', 'question' => 'Michael who wore 23 for the Bulls', 'answer' => 'Jordan', 'points' => 100],
            ['letter' => 'J', 'question' => 'Derek who captained the Yankees', 'answer' => 'Jeter', 'points' => 100],
            ['letter' => 'J', 'question' => 'Jacksonville football team', 'answer' => 'Jaguars', 'points' => 100],
            ['letter' => 'J', 'question' => 'LeBron who is King', 'answer' => 'James', 'points' => 100],
            ['letter' => 'J', 'question' => 'Magic who played for Lakers', 'answer' => 'Johnson', 'points' => 100],

            // Letter K
            ['letter' => 'C', 'question' => 'Kansas City football team', 'answer' => 'Chiefs', 'points' => 100],
            ['letter' => 'K', 'question' => 'Colin who took a knee', 'answer' => 'Kaepernick', 'points' => 200],
            ['letter' => 'K', 'question' => 'Anna who mastered tennis', 'answer' => 'Kournikova', 'points' => 200],

            // Letter L
            ['letter' => 'L', 'question' => 'Los Angeles basketball team', 'answer' => 'Lakers', 'points' => 100],
            ['letter' => 'L', 'question' => 'Detroit football team', 'answer' => 'Lions', 'points' => 100],
            ['letter' => 'L', 'question' => 'Carl who won 9 Olympic golds in track', 'answer' => 'Lewis', 'points' => 200],
            ['letter' => 'C', 'question' => 'Sport with bats and wickets', 'answer' => 'Cricket', 'points' => 300],

            // Letter M
            ['letter' => 'M', 'question' => 'Sport with 26.2 mile race', 'answer' => 'Marathon', 'points' => 100],
            ['letter' => 'M', 'question' => 'Argentine soccer legend', 'answer' => 'Messi', 'points' => 100],
            ['letter' => 'M', 'question' => 'Peyton who quarterbacked Colts and Broncos', 'answer' => 'Manning', 'points' => 100],
            ['letter' => 'M', 'question' => 'Florida baseball team', 'answer' => 'Marlins', 'points' => 100],
            ['letter' => 'B', 'question' => 'Milwaukee baseball team', 'answer' => 'Brewers', 'points' => 100],
            ['letter' => 'M', 'question' => 'Patrick who quarterbacked Chiefs to Super Bowl', 'answer' => 'Mahomes', 'points' => 100],

            // Letter N
            ['letter' => 'N', 'question' => 'Rafael who dominated clay courts', 'answer' => 'Nadal', 'points' => 100],
            ['letter' => 'N', 'question' => 'Pro football league abbreviation', 'answer' => 'NFL', 'points' => 100],
            ['letter' => 'N', 'question' => 'Pro basketball league abbreviation', 'answer' => 'NBA', 'points' => 100],
            ['letter' => 'N', 'question' => 'Brooklyn basketball team', 'answer' => 'Nets', 'points' => 100],
            ['letter' => 'M', 'question' => 'New York baseball team in Queens', 'answer' => 'Mets', 'points' => 100],

            // Letter O
            ['letter' => 'O', 'question' => 'International sports competition every 4 years', 'answer' => 'Olympics', 'points' => 100],
            ['letter' => 'O', 'question' => 'Shaquille who dominated the paint', 'answer' => 'O\'Neal', 'points' => 100],
            ['letter' => 'O', 'question' => 'Baltimore baseball team', 'answer' => 'Orioles', 'points' => 100],
            ['letter' => 'O', 'question' => 'Jesse who won 4 golds in 1936', 'answer' => 'Owens', 'points' => 200],

            // Letter P
            ['letter' => 'P', 'question' => 'Michael who won 23 Olympic golds', 'answer' => 'Phelps', 'points' => 100],
            ['letter' => 'P', 'question' => 'Pittsburgh baseball team', 'answer' => 'Pirates', 'points' => 100],
            ['letter' => 'S', 'question' => 'Phoenix basketball team', 'answer' => 'Suns', 'points' => 100],
            ['letter' => 'P', 'question' => 'San Diego baseball team', 'answer' => 'Padres', 'points' => 100],
            ['letter' => 'P', 'question' => 'Detroit basketball team', 'answer' => 'Pistons', 'points' => 100],
            ['letter' => 'P', 'question' => 'Green Bay football team', 'answer' => 'Packers', 'points' => 100],

            // Letter R
            ['letter' => 'R', 'question' => 'Colorado baseball team', 'answer' => 'Rockies', 'points' => 100],
            ['letter' => 'R', 'question' => 'Portuguese soccer superstar Cristiano', 'answer' => 'Ronaldo', 'points' => 100],
            ['letter' => 'R', 'question' => 'Washington football team formerly', 'answer' => 'Redskins', 'points' => 100],
            ['letter' => 'B', 'question' => 'Sport with a ball and pins', 'answer' => 'Bowling', 'points' => 200],
            ['letter' => 'R', 'question' => 'Jackie who broke baseball\'s color barrier', 'answer' => 'Robinson', 'points' => 100],
            ['letter' => 'R', 'question' => 'Babe who hit 714 home runs', 'answer' => 'Ruth', 'points' => 100],

            // Letter S
            ['letter' => 'S', 'question' => 'Sport with goals and 11 players per side', 'answer' => 'Soccer', 'points' => 100],
            ['letter' => 'S', 'question' => 'Seattle football team', 'answer' => 'Seahawks', 'points' => 100],
            ['letter' => 'S', 'question' => 'Pittsburgh football team', 'answer' => 'Steelers', 'points' => 100],
            ['letter' => 'S', 'question' => 'San Antonio basketball team', 'answer' => 'Spurs', 'points' => 100],
            ['letter' => 'S', 'question' => 'Sport with a pool and lanes', 'answer' => 'Swimming', 'points' => 100],
            ['letter' => 'S', 'question' => 'Pro football championship game', 'answer' => 'Super Bowl', 'points' => 100],
            ['letter' => 'S', 'question' => 'Chicago baseball team in white', 'answer' => 'Sox', 'points' => 100],

            // Letter T
            ['letter' => 'T', 'question' => 'Sport with rackets and a net', 'answer' => 'Tennis', 'points' => 100],
            ['letter' => 'W', 'question' => 'Tiger who dominated golf', 'answer' => 'Woods', 'points' => 100],
            ['letter' => 'T', 'question' => 'Minnesota basketball team', 'answer' => 'Timberwolves', 'points' => 100],
            ['letter' => 'R', 'question' => 'Toronto basketball team', 'answer' => 'Raptors', 'points' => 100],
            ['letter' => 'T', 'question' => 'Tennessee football team', 'answer' => 'Titans', 'points' => 100],
            ['letter' => 'T', 'question' => 'Mike who bit Holyfield\'s ear', 'answer' => 'Tyson', 'points' => 100],
            ['letter' => 'T', 'question' => 'Competition of three sports', 'answer' => 'Triathlon', 'points' => 200],

            // Letter U
            ['letter' => 'B', 'question' => 'Usain who is the fastest man', 'answer' => 'Bolt', 'points' => 100],
            ['letter' => 'J', 'question' => 'Utah basketball team', 'answer' => 'Jazz', 'points' => 100],

            // Letter V
            ['letter' => 'V', 'question' => 'Sport with a net and 6 players per side', 'answer' => 'Volleyball', 'points' => 100],
            ['letter' => 'V', 'question' => 'Minnesota football team', 'answer' => 'Vikings', 'points' => 100],

            // Letter W
            ['letter' => 'W', 'question' => 'Serena who dominated women\'s tennis', 'answer' => 'Williams', 'points' => 100],
            ['letter' => 'W', 'question' => 'Golden State basketball team', 'answer' => 'Warriors', 'points' => 100],
            ['letter' => 'B', 'question' => 'Sport with pins and a heavy ball', 'answer' => 'Bowling', 'points' => 200],
            ['letter' => 'W', 'question' => 'Tennis Grand Slam in London', 'answer' => 'Wimbledon', 'points' => 100],
            ['letter' => 'W', 'question' => 'Sport with mats and takedowns', 'answer' => 'Wrestling', 'points' => 100],
            ['letter' => 'W', 'question' => 'Tiger\'s last name in golf', 'answer' => 'Woods', 'points' => 100],

            // Letter Y
            ['letter' => 'Y', 'question' => 'New York baseball team in the Bronx', 'answer' => 'Yankees', 'points' => 100],
            ['letter' => 'Y', 'question' => 'Steve who quarterbacked 49ers', 'answer' => 'Young', 'points' => 200],

            // Tough Questions (3 per letter, 300 points each)
            // Letter A
            ['letter' => 'A', 'question' => 'Greek god of the sun who drove a chariot', 'answer' => 'Apollo', 'points' => 300],
            ['letter' => 'A', 'question' => 'Inflammation of the appendix', 'answer' => 'Appendicitis', 'points' => 300],
            ['letter' => 'A', 'question' => 'Study of human societies and cultures', 'answer' => 'Anthropology', 'points' => 300],

            // Letter B
            ['letter' => 'B', 'question' => 'German composer who wrote 9 symphonies while going deaf', 'answer' => 'Beethoven', 'points' => 300],
            ['letter' => 'B', 'question' => 'Type of tree that sheds its bark in patches', 'answer' => 'Birch', 'points' => 300],
            ['letter' => 'B', 'question' => 'French emperor exiled to Elba', 'answer' => 'Bonaparte', 'points' => 300],

            // Letter C
            ['letter' => 'C', 'question' => 'Egyptian queen who ruled with Mark Antony', 'answer' => 'Cleopatra', 'points' => 300],
            ['letter' => 'C', 'question' => 'Explorer who sailed for Spain in 1492', 'answer' => 'Columbus', 'points' => 300],
            ['letter' => 'C', 'question' => 'French impressionist painter of water lilies', 'answer' => 'Claude Monet', 'points' => 300],

            // Letter D
            ['letter' => 'D', 'question' => 'Author of A Tale of Two Cities', 'answer' => 'Dickens', 'points' => 300],
            ['letter' => 'D', 'question' => 'Greek philosopher who searched for an honest man', 'answer' => 'Diogenes', 'points' => 300],
            ['letter' => 'D', 'question' => 'Russian novelist who wrote Crime and Punishment', 'answer' => 'Dostoevsky', 'points' => 300],

            // Letter E
            ['letter' => 'E', 'question' => 'Scientist who developed the theory of relativity', 'answer' => 'Einstein', 'points' => 300],
            ['letter' => 'E', 'question' => 'Greek mathematician who wrote Elements', 'answer' => 'Euclid', 'points' => 300],
            ['letter' => 'E', 'question' => 'Inventor who held over 1,000 patents', 'answer' => 'Edison', 'points' => 300],

            // Letter F
            ['letter' => 'F', 'question' => 'Author of The Great Gatsby', 'answer' => 'Fitzgerald', 'points' => 300],
            ['letter' => 'F', 'question' => 'Scientist who discovered penicillin', 'answer' => 'Fleming', 'points' => 300],
            ['letter' => 'F', 'question' => 'Sigmund who founded psychoanalysis', 'answer' => 'Freud', 'points' => 300],

            // Letter G
            ['letter' => 'G', 'question' => 'Italian astronomer who supported heliocentrism', 'answer' => 'Galileo', 'points' => 300],
            ['letter' => 'G', 'question' => 'Indian leader who used nonviolent resistance', 'answer' => 'Gandhi', 'points' => 300],
            ['letter' => 'G', 'question' => 'German brothers who collected fairy tales', 'answer' => 'Grimm', 'points' => 300],

            // Letter H
            ['letter' => 'H', 'question' => 'Greek poet who wrote the Iliad and Odyssey', 'answer' => 'Homer', 'points' => 300],
            ['letter' => 'H', 'question' => 'Magician who escaped from anything', 'answer' => 'Houdini', 'points' => 300],
            ['letter' => 'H', 'question' => 'Author who wrote A Farewell to Arms', 'answer' => 'Hemingway', 'points' => 300],

            // Letter I
            ['letter' => 'I', 'question' => 'Greek goddess of the rainbow', 'answer' => 'Iris', 'points' => 300],
            ['letter' => 'I', 'question' => 'Spanish Inquisition enforced this institution', 'answer' => 'Inquisition', 'points' => 300],
            ['letter' => 'I', 'question' => 'Flu epidemic of 1918 named after a country', 'answer' => 'Influenza', 'points' => 300],

            // Letter J
            ['letter' => 'J', 'question' => 'Roman god who has two faces', 'answer' => 'Janus', 'points' => 300],
            ['letter' => 'J', 'question' => 'Swiss psychologist who developed archetypes', 'answer' => 'Jung', 'points' => 300],
            ['letter' => 'J', 'question' => 'Third president who wrote the Declaration', 'answer' => 'Jefferson', 'points' => 300],

            // Letter K
            ['letter' => 'K', 'question' => 'Author who wrote The Metamorphosis', 'answer' => 'Kafka', 'points' => 300],
            ['letter' => 'K', 'question' => 'Astronomer who discovered planetary motion laws', 'answer' => 'Kepler', 'points' => 300],
            ['letter' => 'K', 'question' => 'Mongol emperor grandson of Genghis', 'answer' => 'Kublai Khan', 'points' => 300],

            // Letter L
            ['letter' => 'L', 'question' => 'President who delivered the Gettysburg Address', 'answer' => 'Lincoln', 'points' => 300],
            ['letter' => 'L', 'question' => 'Renaissance genius who painted the Mona Lisa', 'answer' => 'Leonardo da Vinci', 'points' => 300],
            ['letter' => 'L', 'question' => 'French chemist who is father of modern chemistry', 'answer' => 'Lavoisier', 'points' => 300],

            // Letter M
            ['letter' => 'M', 'question' => 'Renaissance artist who sculpted David', 'answer' => 'Michelangelo', 'points' => 300],
            ['letter' => 'M', 'question' => 'Explorer who named the Pacific Ocean', 'answer' => 'Magellan', 'points' => 300],
            ['letter' => 'M', 'question' => 'South African leader imprisoned for 27 years', 'answer' => 'Mandela', 'points' => 300],

            // Letter N
            ['letter' => 'N', 'question' => 'Physicist who discovered gravity with an apple', 'answer' => 'Newton', 'points' => 300],
            ['letter' => 'N', 'question' => 'Greek goddess of victory', 'answer' => 'Nike', 'points' => 300],
            ['letter' => 'N', 'question' => 'Nurse who founded modern nursing', 'answer' => 'Nightingale', 'points' => 300],

            // Letter O
            ['letter' => 'O', 'question' => 'Greek hero who took 10 years to get home', 'answer' => 'Odysseus', 'points' => 300],
            ['letter' => 'O', 'question' => 'Father of the atomic bomb project', 'answer' => 'Oppenheimer', 'points' => 300],
            ['letter' => 'O', 'question' => 'Eight-sided polygon shape', 'answer' => 'Octagon', 'points' => 300],

            // Letter P
            ['letter' => 'P', 'question' => 'Greek philosopher who taught Aristotle', 'answer' => 'Plato', 'points' => 300],
            ['letter' => 'P', 'question' => 'Spanish artist who co-founded Cubism', 'answer' => 'Picasso', 'points' => 300],
            ['letter' => 'P', 'question' => 'Scientist who discovered radium', 'answer' => 'Pierre Curie', 'points' => 300],

            // Letter Q
            ['letter' => 'Q', 'question' => 'Fictional character who tilts at windmills', 'answer' => 'Quixote', 'points' => 300],
            ['letter' => 'Q', 'question' => 'Aztec god depicted as a feathered serpent', 'answer' => 'Quetzalcoatl', 'points' => 300],
            ['letter' => 'Q', 'question' => 'Medieval practice of isolating the sick', 'answer' => 'Quarantine', 'points' => 300],

            // Letter R
            ['letter' => 'R', 'question' => 'Italian Renaissance painter of the School of Athens', 'answer' => 'Raphael', 'points' => 300],
            ['letter' => 'R', 'question' => 'President who created the New Deal', 'answer' => 'Roosevelt', 'points' => 300],
            ['letter' => 'R', 'question' => 'Dutch painter who created The Night Watch', 'answer' => 'Rembrandt', 'points' => 300],

            // Letter S
            ['letter' => 'S', 'question' => 'Greek philosopher who drank hemlock', 'answer' => 'Socrates', 'points' => 300],
            ['letter' => 'S', 'question' => 'Bard of Avon who wrote Hamlet', 'answer' => 'Shakespeare', 'points' => 300],
            ['letter' => 'S', 'question' => 'Greek warrior women from mythology', 'answer' => 'Spartans', 'points' => 300],

            // Letter T
            ['letter' => 'T', 'question' => 'Russian author of War and Peace', 'answer' => 'Tolstoy', 'points' => 300],
            ['letter' => 'T', 'question' => 'American author who wrote Walden', 'answer' => 'Thoreau', 'points' => 300],
            ['letter' => 'T', 'question' => 'Egyptian pharaoh whose tomb was found intact', 'answer' => 'Tutankhamun', 'points' => 300],

            // Letter U
            ['letter' => 'U', 'question' => 'Grant who led the Union army', 'answer' => 'Ulysses', 'points' => 300],
            ['letter' => 'U', 'question' => 'Mythical land of perfect society', 'answer' => 'Utopia', 'points' => 300],
            ['letter' => 'U', 'question' => 'Country known as the Soviet Union', 'answer' => 'USSR', 'points' => 300],

            // Letter V
            ['letter' => 'V', 'question' => 'Composer of The Four Seasons', 'answer' => 'Vivaldi', 'points' => 300],
            ['letter' => 'V', 'question' => 'French author who wrote Les Miserables', 'answer' => 'Victor Hugo', 'points' => 300],
            ['letter' => 'V', 'question' => 'Queen who ruled England for 63 years', 'answer' => 'Victoria', 'points' => 300],

            // Letter W
            ['letter' => 'W', 'question' => 'First president of the United States', 'answer' => 'Washington', 'points' => 300],
            ['letter' => 'W', 'question' => 'Irish author who wrote The Picture of Dorian Gray', 'answer' => 'Wilde', 'points' => 300],
            ['letter' => 'W', 'question' => 'Brothers who achieved first powered flight', 'answer' => 'Wright', 'points' => 300],

            // Letter X
            ['letter' => 'X', 'question' => 'Greek word for stranger or foreigner', 'answer' => 'Xenos', 'points' => 300],
            ['letter' => 'X', 'question' => 'Ancient Persian king who invaded Greece', 'answer' => 'Xerxes', 'points' => 300],
            ['letter' => 'X', 'question' => 'Chinese philosophy of filial piety founder', 'answer' => 'Xunzi', 'points' => 300],

            // Letter Y
            ['letter' => 'Y', 'question' => 'Irish poet who wrote The Second Coming', 'answer' => 'Yeats', 'points' => 300],
            ['letter' => 'Y', 'question' => 'River in China that is called the Yellow River', 'answer' => 'Yellow River', 'points' => 300],
            ['letter' => 'Y', 'question' => 'Meditation practice meaning union in Sanskrit', 'answer' => 'Yoga', 'points' => 300],

            // Letter Z
            ['letter' => 'Z', 'question' => 'Greek philosopher who founded Stoicism', 'answer' => 'Zeno', 'points' => 300],
            ['letter' => 'Z', 'question' => 'Babylonian temple tower structure', 'answer' => 'Ziggurat', 'points' => 300],
            ['letter' => 'Z', 'question' => 'Element with atomic number 30', 'answer' => 'Zinc', 'points' => 300],
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
