<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\GameType;
use App\Models\Question;
use Illuminate\Database\Seeder;

class AmericaSaysQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $americaSays = GameType::where('slug', 'america-says')->first();

        if (!$americaSays) {
            $this->command->error('America Says game type not found. Run GameTypeSeeder first.');
            return;
        }

        $questions = [
            // Question 1
            [
                'question' => 'Name something you find at a beach',
                'answers' => ['Sand', 'Water', 'Waves', 'Shells', 'Sunscreen', 'Towels', 'Umbrellas'],
            ],
            // Question 2
            [
                'question' => 'Name a popular pizza topping',
                'answers' => ['Pepperoni', 'Cheese', 'Sausage', 'Mushrooms', 'Onions', 'Peppers', 'Olives'],
            ],
            // Question 3
            [
                'question' => 'Name something you do before going to bed',
                'answers' => ['Brush Teeth', 'Shower', 'Read', 'Watch TV', 'Set Alarm', 'Pray', 'Lock Doors'],
            ],
            // Question 4
            [
                'question' => 'Name a reason people are late to work',
                'answers' => ['Traffic', 'Overslept', 'Car Trouble', 'Kids', 'Weather', 'Alarm Failed', 'Got Lost'],
            ],
            // Question 5
            [
                'question' => 'Name something found in a kitchen',
                'answers' => ['Stove', 'Refrigerator', 'Sink', 'Microwave', 'Dishes', 'Table', 'Knives'],
            ],
            // Question 6
            [
                'question' => 'Name a popular breakfast food',
                'answers' => ['Eggs', 'Bacon', 'Cereal', 'Pancakes', 'Toast', 'Waffles', 'Oatmeal'],
            ],
            // Question 7
            [
                'question' => 'Name something you bring on a camping trip',
                'answers' => ['Tent', 'Sleeping Bag', 'Flashlight', 'Food', 'Matches', 'Cooler', 'Bug Spray'],
            ],
            // Question 8
            [
                'question' => 'Name a popular Halloween costume',
                'answers' => ['Witch', 'Ghost', 'Vampire', 'Zombie', 'Superhero', 'Princess', 'Pirate'],
            ],
            // Question 9
            [
                'question' => 'Name something you find in a classroom',
                'answers' => ['Desks', 'Chalkboard', 'Books', 'Teacher', 'Students', 'Pencils', 'Clock'],
            ],
            // Question 10
            [
                'question' => 'Name a popular ice cream flavor',
                'answers' => ['Vanilla', 'Chocolate', 'Strawberry', 'Mint Chip', 'Cookie Dough', 'Rocky Road', 'Cookies & Cream'],
            ],
            // Question 11
            [
                'question' => 'Name something people collect',
                'answers' => ['Stamps', 'Coins', 'Cards', 'Art', 'Antiques', 'Dolls', 'Records'],
            ],
            // Question 12
            [
                'question' => 'Name a popular pet',
                'answers' => ['Dog', 'Cat', 'Fish', 'Bird', 'Hamster', 'Rabbit', 'Turtle'],
            ],
            // Question 13
            [
                'question' => 'Name something you do on a first date',
                'answers' => ['Dinner', 'Movie', 'Talk', 'Drinks', 'Walk', 'Coffee', 'Bowling'],
            ],
            // Question 14
            [
                'question' => 'Name a holiday people travel for',
                'answers' => ['Thanksgiving', 'Christmas', 'Easter', 'Fourth of July', 'New Years', 'Memorial Day', 'Labor Day'],
            ],
            // Question 15
            [
                'question' => 'Name something found in a hospital',
                'answers' => ['Doctors', 'Nurses', 'Beds', 'Patients', 'Medicine', 'IV', 'Wheelchairs'],
            ],
            // Question 16
            [
                'question' => 'Name a popular fast food restaurant',
                'answers' => ['McDonalds', 'Burger King', 'Wendys', 'Taco Bell', 'Chick-fil-A', 'Subway', 'KFC'],
            ],
            // Question 17
            [
                'question' => 'Name something you do at a birthday party',
                'answers' => ['Eat Cake', 'Open Gifts', 'Sing', 'Play Games', 'Dance', 'Blow Candles', 'Take Photos'],
            ],
            // Question 18
            [
                'question' => 'Name a popular sport',
                'answers' => ['Football', 'Basketball', 'Baseball', 'Soccer', 'Tennis', 'Golf', 'Hockey'],
            ],
            // Question 19
            [
                'question' => 'Name something you find at a gas station',
                'answers' => ['Gas Pumps', 'Snacks', 'Drinks', 'Restrooms', 'Air Pump', 'Car Wash', 'Lottery Tickets'],
            ],
            // Question 20
            [
                'question' => 'Name a reason to call in sick',
                'answers' => ['Flu', 'Cold', 'Headache', 'Stomach Bug', 'Fever', 'Doctor Appointment', 'Family Emergency'],
            ],
            // Question 21
            [
                'question' => 'Name something you see at a circus',
                'answers' => ['Clowns', 'Elephants', 'Acrobats', 'Lions', 'Trapeze', 'Ringmaster', 'Tightrope'],
            ],
            // Question 22
            [
                'question' => 'Name a popular condiment',
                'answers' => ['Ketchup', 'Mustard', 'Mayo', 'Hot Sauce', 'BBQ Sauce', 'Ranch', 'Relish'],
            ],
            // Question 23
            [
                'question' => 'Name something found in a purse',
                'answers' => ['Wallet', 'Keys', 'Phone', 'Lipstick', 'Gum', 'Tissues', 'Mints'],
            ],
            // Question 24
            [
                'question' => 'Name a popular TV show genre',
                'answers' => ['Comedy', 'Drama', 'Reality', 'Crime', 'Sci-Fi', 'News', 'Sports'],
            ],
            // Question 25
            [
                'question' => 'Name something you do on a rainy day',
                'answers' => ['Watch TV', 'Read', 'Sleep', 'Play Games', 'Clean', 'Cook', 'Movies'],
            ],
            // Question 26
            [
                'question' => 'Name a popular fruit',
                'answers' => ['Apple', 'Banana', 'Orange', 'Strawberry', 'Grapes', 'Watermelon', 'Mango'],
            ],
            // Question 27
            [
                'question' => 'Name something found in a gym',
                'answers' => ['Treadmill', 'Weights', 'Mirrors', 'Mats', 'Bikes', 'Lockers', 'Benches'],
            ],
            // Question 28
            [
                'question' => 'Name a popular car color',
                'answers' => ['Black', 'White', 'Silver', 'Red', 'Blue', 'Gray', 'Green'],
            ],
            // Question 29
            [
                'question' => 'Name something you find at a wedding',
                'answers' => ['Cake', 'Bride', 'Groom', 'Flowers', 'Music', 'Dancing', 'Rings'],
            ],
            // Question 30
            [
                'question' => 'Name a popular coffee drink',
                'answers' => ['Latte', 'Cappuccino', 'Espresso', 'Americano', 'Mocha', 'Iced Coffee', 'Black Coffee'],
            ],
            // Question 31
            [
                'question' => 'Name something people forget to do',
                'answers' => ['Lock Door', 'Turn Off Lights', 'Pay Bills', 'Call Back', 'Take Medicine', 'Feed Pet', 'Appointments'],
            ],
            // Question 32
            [
                'question' => 'Name a popular vegetable',
                'answers' => ['Carrot', 'Broccoli', 'Corn', 'Potato', 'Tomato', 'Lettuce', 'Onion'],
            ],
            // Question 33
            [
                'question' => 'Name something found at a park',
                'answers' => ['Playground', 'Trees', 'Benches', 'Grass', 'Pond', 'Picnic Tables', 'Walking Path'],
            ],
            // Question 34
            [
                'question' => 'Name a reason to celebrate',
                'answers' => ['Birthday', 'Wedding', 'Graduation', 'New Job', 'Baby', 'Promotion', 'Anniversary'],
            ],
            // Question 35
            [
                'question' => 'Name something you do in the morning',
                'answers' => ['Shower', 'Brush Teeth', 'Eat Breakfast', 'Get Dressed', 'Coffee', 'Check Phone', 'Exercise'],
            ],
            // Question 36
            [
                'question' => 'Name a popular sandwich',
                'answers' => ['BLT', 'Grilled Cheese', 'PB&J', 'Ham & Cheese', 'Turkey', 'Club', 'Tuna'],
            ],
            // Question 37
            [
                'question' => 'Name something you find in a hotel room',
                'answers' => ['Bed', 'TV', 'Bathroom', 'Towels', 'Bible', 'Mini Fridge', 'Safe'],
            ],
            // Question 38
            [
                'question' => 'Name a popular music genre',
                'answers' => ['Pop', 'Rock', 'Country', 'Hip Hop', 'R&B', 'Jazz', 'Classical'],
            ],
            // Question 39
            [
                'question' => 'Name something you do on New Years Eve',
                'answers' => ['Party', 'Drink', 'Countdown', 'Kiss', 'Watch Ball Drop', 'Fireworks', 'Make Resolutions'],
            ],
            // Question 40
            [
                'question' => 'Name a popular dessert',
                'answers' => ['Cake', 'Ice Cream', 'Pie', 'Cookies', 'Brownies', 'Cheesecake', 'Pudding'],
            ],
            // Question 41
            [
                'question' => 'Name something found in a bathroom',
                'answers' => ['Toilet', 'Sink', 'Shower', 'Towels', 'Mirror', 'Soap', 'Toothbrush'],
            ],
            // Question 42
            [
                'question' => 'Name a popular board game',
                'answers' => ['Monopoly', 'Scrabble', 'Chess', 'Clue', 'Life', 'Sorry', 'Checkers'],
            ],
            // Question 43
            [
                'question' => 'Name something you take on vacation',
                'answers' => ['Clothes', 'Camera', 'Sunscreen', 'Phone', 'Passport', 'Sunglasses', 'Money'],
            ],
            // Question 44
            [
                'question' => 'Name a popular soft drink',
                'answers' => ['Coke', 'Pepsi', 'Sprite', 'Dr Pepper', 'Mountain Dew', 'Fanta', '7-Up'],
            ],
            // Question 45
            [
                'question' => 'Name something found in an office',
                'answers' => ['Desk', 'Computer', 'Chair', 'Phone', 'Printer', 'Files', 'Stapler'],
            ],
            // Question 46
            [
                'question' => 'Name a popular movie snack',
                'answers' => ['Popcorn', 'Candy', 'Soda', 'Nachos', 'Hot Dog', 'Pretzels', 'Ice Cream'],
            ],
            // Question 47
            [
                'question' => 'Name something you do at the gym',
                'answers' => ['Lift Weights', 'Run', 'Stretch', 'Cardio', 'Swim', 'Yoga', 'Bike'],
            ],
            // Question 48
            [
                'question' => 'Name a popular superhero',
                'answers' => ['Batman', 'Superman', 'Spider-Man', 'Wonder Woman', 'Iron Man', 'Captain America', 'Hulk'],
            ],
            // Question 49
            [
                'question' => 'Name something you find at a zoo',
                'answers' => ['Animals', 'Lions', 'Monkeys', 'Elephants', 'Gift Shop', 'Food', 'Cages'],
            ],
            // Question 50
            [
                'question' => 'Name a popular chip flavor',
                'answers' => ['Regular', 'BBQ', 'Sour Cream & Onion', 'Salt & Vinegar', 'Cheddar', 'Ranch', 'Jalapeno'],
            ],
            // Question 51
            [
                'question' => 'Name something you find in a wallet',
                'answers' => ['Cash', 'Credit Cards', 'ID', 'Photos', 'Receipts', 'Business Cards', 'Gift Cards'],
            ],
            // Question 52
            [
                'question' => 'Name a popular pizza chain',
                'answers' => ['Dominos', 'Pizza Hut', 'Papa Johns', 'Little Caesars', 'Papa Murphys', 'Marcos', 'Round Table'],
            ],
            // Question 53
            [
                'question' => 'Name something kids play with',
                'answers' => ['Toys', 'Video Games', 'Dolls', 'Blocks', 'Balls', 'Bikes', 'Stuffed Animals'],
            ],
            // Question 54
            [
                'question' => 'Name a popular streaming service',
                'answers' => ['Netflix', 'Hulu', 'Disney+', 'Amazon Prime', 'HBO Max', 'Apple TV', 'YouTube'],
            ],
            // Question 55
            [
                'question' => 'Name something you do on a snow day',
                'answers' => ['Build Snowman', 'Sled', 'Stay Inside', 'Hot Cocoa', 'Watch TV', 'Sleep In', 'Snowball Fight'],
            ],
            // Question 56
            [
                'question' => 'Name a popular candy bar',
                'answers' => ['Snickers', 'Kit Kat', 'Reeses', 'Twix', 'Milky Way', 'Hersheys', 'Three Musketeers'],
            ],
            // Question 57
            [
                'question' => 'Name something you find at a barbecue',
                'answers' => ['Burgers', 'Hot Dogs', 'Grill', 'Corn', 'Coleslaw', 'Ribs', 'Baked Beans'],
            ],
            // Question 58
            [
                'question' => 'Name a popular social media platform',
                'answers' => ['Facebook', 'Instagram', 'Twitter', 'TikTok', 'Snapchat', 'LinkedIn', 'YouTube'],
            ],
            // Question 59
            [
                'question' => 'Name something found in a toolbox',
                'answers' => ['Hammer', 'Screwdriver', 'Wrench', 'Nails', 'Pliers', 'Tape Measure', 'Drill'],
            ],
            // Question 60
            [
                'question' => 'Name a popular breakfast cereal',
                'answers' => ['Cheerios', 'Frosted Flakes', 'Froot Loops', 'Lucky Charms', 'Corn Flakes', 'Cinnamon Toast Crunch', 'Honey Nut Cheerios'],
            ],
            // Question 61
            [
                'question' => 'Name something you do at a pool',
                'answers' => ['Swim', 'Dive', 'Sunbathe', 'Float', 'Splash', 'Play Marco Polo', 'Relax'],
            ],
            // Question 62
            [
                'question' => 'Name a popular type of cookie',
                'answers' => ['Chocolate Chip', 'Oatmeal Raisin', 'Peanut Butter', 'Sugar', 'Oreo', 'Snickerdoodle', 'Macadamia Nut'],
            ],
            // Question 63
            [
                'question' => 'Name something you find in a garage',
                'answers' => ['Car', 'Tools', 'Bikes', 'Lawn Mower', 'Storage Boxes', 'Workbench', 'Sports Equipment'],
            ],
            // Question 64
            [
                'question' => 'Name a popular cocktail',
                'answers' => ['Margarita', 'Martini', 'Mojito', 'Pina Colada', 'Daiquiri', 'Cosmopolitan', 'Long Island'],
            ],
            // Question 65
            [
                'question' => 'Name something you do on a road trip',
                'answers' => ['Listen to Music', 'Eat Snacks', 'Sleep', 'Play Games', 'Stop for Gas', 'Take Photos', 'Talk'],
            ],
            // Question 66
            [
                'question' => 'Name a popular flower',
                'answers' => ['Rose', 'Tulip', 'Sunflower', 'Daisy', 'Lily', 'Orchid', 'Carnation'],
            ],
            // Question 67
            [
                'question' => 'Name something found on a menu',
                'answers' => ['Prices', 'Appetizers', 'Entrees', 'Desserts', 'Drinks', 'Salads', 'Specials'],
            ],
            // Question 68
            [
                'question' => 'Name a popular shoe brand',
                'answers' => ['Nike', 'Adidas', 'Converse', 'Vans', 'Reebok', 'New Balance', 'Puma'],
            ],
            // Question 69
            [
                'question' => 'Name something you do at a concert',
                'answers' => ['Dance', 'Sing Along', 'Cheer', 'Take Photos', 'Drink', 'Stand', 'Wave Hands'],
            ],
            // Question 70
            [
                'question' => 'Name a popular cheese',
                'answers' => ['Cheddar', 'Mozzarella', 'American', 'Swiss', 'Parmesan', 'Brie', 'Pepper Jack'],
            ],
            // Question 71
            [
                'question' => 'Name something found at a carnival',
                'answers' => ['Rides', 'Games', 'Cotton Candy', 'Funnel Cake', 'Prizes', 'Ferris Wheel', 'Clowns'],
            ],
            // Question 72
            [
                'question' => 'Name a popular type of pasta',
                'answers' => ['Spaghetti', 'Penne', 'Fettuccine', 'Macaroni', 'Lasagna', 'Linguine', 'Rigatoni'],
            ],
            // Question 73
            [
                'question' => 'Name something you find in a school cafeteria',
                'answers' => ['Tables', 'Food', 'Trays', 'Milk', 'Lunch Ladies', 'Lines', 'Trash Cans'],
            ],
            // Question 74
            [
                'question' => 'Name a popular holiday movie',
                'answers' => ['Home Alone', 'Elf', 'A Christmas Story', 'Its a Wonderful Life', 'The Grinch', 'Miracle on 34th St', 'Christmas Vacation'],
            ],
            // Question 75
            [
                'question' => 'Name something you do when stuck in traffic',
                'answers' => ['Listen to Music', 'Talk on Phone', 'Honk', 'Sing', 'Text', 'Eat', 'Get Frustrated'],
            ],
            // Question 76
            [
                'question' => 'Name a popular breakfast drink',
                'answers' => ['Coffee', 'Orange Juice', 'Milk', 'Tea', 'Smoothie', 'Water', 'Apple Juice'],
            ],
            // Question 77
            [
                'question' => 'Name something found on a playground',
                'answers' => ['Swings', 'Slide', 'Monkey Bars', 'Sandbox', 'See-Saw', 'Jungle Gym', 'Bench'],
            ],
            // Question 78
            [
                'question' => 'Name a popular TV channel',
                'answers' => ['ESPN', 'HBO', 'CNN', 'Fox', 'NBC', 'ABC', 'CBS'],
            ],
            // Question 79
            [
                'question' => 'Name something you find in a medicine cabinet',
                'answers' => ['Aspirin', 'Band-Aids', 'Toothpaste', 'Prescription Pills', 'Cough Syrup', 'Vitamins', 'Mouthwash'],
            ],
            // Question 80
            [
                'question' => 'Name a popular Mexican food',
                'answers' => ['Tacos', 'Burritos', 'Nachos', 'Quesadilla', 'Enchiladas', 'Guacamole', 'Tamales'],
            ],
            // Question 81
            [
                'question' => 'Name something you do when you cant sleep',
                'answers' => ['Watch TV', 'Read', 'Count Sheep', 'Drink Milk', 'Scroll Phone', 'Toss and Turn', 'Take Medicine'],
            ],
            // Question 82
            [
                'question' => 'Name a popular type of donut',
                'answers' => ['Glazed', 'Chocolate', 'Jelly', 'Boston Cream', 'Sprinkle', 'Powdered', 'Maple Bar'],
            ],
            // Question 83
            [
                'question' => 'Name something found at a farmers market',
                'answers' => ['Vegetables', 'Fruits', 'Flowers', 'Honey', 'Eggs', 'Bread', 'Cheese'],
            ],
            // Question 84
            [
                'question' => 'Name a popular video game console',
                'answers' => ['PlayStation', 'Xbox', 'Nintendo Switch', 'Wii', 'GameCube', 'Sega', 'Atari'],
            ],
            // Question 85
            [
                'question' => 'Name something you clean in your house',
                'answers' => ['Floors', 'Bathroom', 'Kitchen', 'Windows', 'Dishes', 'Laundry', 'Bedroom'],
            ],
            // Question 86
            [
                'question' => 'Name a popular type of bread',
                'answers' => ['White', 'Wheat', 'Sourdough', 'Rye', 'French', 'Italian', 'Multigrain'],
            ],
            // Question 87
            [
                'question' => 'Name something you find at a laundromat',
                'answers' => ['Washers', 'Dryers', 'Detergent', 'Folding Tables', 'Chairs', 'Change Machine', 'Lint'],
            ],
            // Question 88
            [
                'question' => 'Name a popular animated movie',
                'answers' => ['Frozen', 'Toy Story', 'Finding Nemo', 'The Lion King', 'Shrek', 'Moana', 'Coco'],
            ],
            // Question 89
            [
                'question' => 'Name something you do on a lazy Sunday',
                'answers' => ['Sleep In', 'Watch TV', 'Eat Brunch', 'Read', 'Nap', 'Relax', 'Go to Church'],
            ],
            // Question 90
            [
                'question' => 'Name a popular type of tea',
                'answers' => ['Green Tea', 'Black Tea', 'Chamomile', 'Earl Grey', 'Peppermint', 'Oolong', 'Herbal'],
            ],
            // Question 91
            [
                'question' => 'Name something found at a basketball game',
                'answers' => ['Players', 'Ball', 'Hoop', 'Fans', 'Referees', 'Scoreboard', 'Cheerleaders'],
            ],
            // Question 92
            [
                'question' => 'Name a popular soup',
                'answers' => ['Chicken Noodle', 'Tomato', 'Clam Chowder', 'Vegetable', 'French Onion', 'Minestrone', 'Cream of Mushroom'],
            ],
            // Question 93
            [
                'question' => 'Name something you find at an airport',
                'answers' => ['Planes', 'Gates', 'Security', 'Luggage', 'Restaurants', 'Shops', 'Passengers'],
            ],
            // Question 94
            [
                'question' => 'Name a popular phone app',
                'answers' => ['Facebook', 'Instagram', 'TikTok', 'YouTube', 'Snapchat', 'Twitter', 'Gmail'],
            ],
            // Question 95
            [
                'question' => 'Name something you do at a picnic',
                'answers' => ['Eat', 'Play Games', 'Sit on Blanket', 'Talk', 'Relax', 'Drink', 'Watch Kids'],
            ],
            // Question 96
            [
                'question' => 'Name a popular salad dressing',
                'answers' => ['Ranch', 'Italian', 'Caesar', 'Blue Cheese', 'Thousand Island', 'Vinaigrette', 'French'],
            ],
            // Question 97
            [
                'question' => 'Name something found on a desk',
                'answers' => ['Computer', 'Pens', 'Paper', 'Lamp', 'Phone', 'Stapler', 'Calendar'],
            ],
            // Question 98
            [
                'question' => 'Name a popular amusement park',
                'answers' => ['Disney World', 'Disneyland', 'Universal Studios', 'Six Flags', 'Cedar Point', 'SeaWorld', 'Busch Gardens'],
            ],
            // Question 99
            [
                'question' => 'Name something you do on a date night',
                'answers' => ['Dinner', 'Movie', 'Drinks', 'Dancing', 'Walk', 'Concert', 'Stay Home'],
            ],
            // Question 100
            [
                'question' => 'Name a popular type of nut',
                'answers' => ['Peanut', 'Almond', 'Cashew', 'Walnut', 'Pistachio', 'Pecan', 'Macadamia'],
            ],
        ];

        foreach ($questions as $index => $q) {
            $question = Question::create([
                'game_type_id' => $americaSays->id,
                'question_text' => $q['question'],
                'difficulty' => 'medium',
                'is_active' => true,
            ]);

            // Create answers with points (100 points each, ranked by display order)
            foreach ($q['answers'] as $answerIndex => $answerText) {
                Answer::create([
                    'question_id' => $question->id,
                    'answer_text' => $answerText,
                    'points' => 100,
                    'display_order' => $answerIndex + 1,
                ]);
            }
        }

        $this->command->info('Created ' . count($questions) . ' America Says questions with ' . (count($questions) * 7) . ' answers.');
    }
}
