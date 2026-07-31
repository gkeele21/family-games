<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\GameType;
use App\Models\Question;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Starter categories for tagging questions, plus a first-pass categorization
     * of the seeded America Says bank. Anything not explicitly mapped falls to
     * "General". Keyed by question text so it re-applies cleanly after a reseed.
     */
    public function run(): void
    {
        $byGame = [
            'america-says' => [
                'Food & Drink',
                'Entertainment',
                'Sports',
                'Around the House',
                'Holidays',
                'General',
            ],
            'oodles' => [
                'Easy Letters',
                'Tricky Letters',
                'General',
            ],
        ];

        foreach ($byGame as $slug => $names) {
            $gameType = GameType::where('slug', $slug)->first();
            if (!$gameType) {
                continue;
            }
            foreach ($names as $name) {
                Category::firstOrCreate(['game_type_id' => $gameType->id, 'name' => $name]);
            }
        }

        $this->categorizeAmericaSays();
    }

    protected function categorizeAmericaSays(): void
    {
        $gameType = GameType::where('slug', 'america-says')->first();
        if (!$gameType) {
            return;
        }

        $map = [
            'Food & Drink' => [
                'Name a popular pizza topping',
                'Name a popular breakfast food',
                'Name a popular ice cream flavor',
                'Name a popular fast food restaurant',
                'Name a popular condiment',
                'Name a popular fruit',
                'Name a popular coffee drink',
                'Name a popular vegetable',
                'Name a popular sandwich',
                'Name a popular dessert',
                'Name a popular soft drink',
                'Name a popular movie snack',
                'Name a popular chip flavor',
                'Name a popular pizza chain',
                'Name a popular candy bar',
                'Name something you find at a barbecue',
                'Name a popular breakfast cereal',
                'Name a popular type of cookie',
                'Name a popular cocktail',
                'Name something found on a menu',
                'Name a popular cheese',
                'Name a popular type of pasta',
                'Name something you find in a school cafeteria',
                'Name a popular breakfast drink',
                'Name a popular Mexican food',
                'Name a popular type of donut',
                'Name something found at a farmers market',
                'Name a popular type of bread',
                'Name a popular type of tea',
                'Name a popular soup',
                'Name a popular salad dressing',
                'Name a popular type of nut',
            ],
            'Entertainment' => [
                'Name something you see at a circus',
                'Name a popular TV show genre',
                'Name a popular music genre',
                'Name a popular board game',
                'Name a popular superhero',
                'Name a popular streaming service',
                'Name a popular social media platform',
                'Name something you do at a concert',
                'Name something found at a carnival',
                'Name a popular holiday movie',
                'Name a popular TV channel',
                'Name a popular video game console',
                'Name a popular animated movie',
                'Name a popular phone app',
                'Name a popular amusement park',
            ],
            'Sports' => [
                'Name a popular sport',
                'Name something found in a gym',
                'Name something you do at the gym',
                'Name something found at a basketball game',
            ],
            'Around the House' => [
                'Name something found in a kitchen',
                'Name something found in a bathroom',
                'Name something found in a toolbox',
                'Name something you find in a garage',
                'Name something you find in a medicine cabinet',
                'Name something you clean in your house',
                'Name something you find at a laundromat',
            ],
            'Holidays' => [
                'Name a popular Halloween costume',
                'Name a holiday people travel for',
                'Name something you do on New Years Eve',
            ],
        ];

        $catIds = Category::where('game_type_id', $gameType->id)->pluck('id', 'name');
        $generalId = $catIds['General'] ?? null;

        $textToCat = [];
        foreach ($map as $catName => $texts) {
            $cid = $catIds[$catName] ?? null;
            if (!$cid) {
                continue;
            }
            foreach ($texts as $t) {
                $textToCat[$t] = $cid;
            }
        }

        Question::where('game_type_id', $gameType->id)->get()->each(function (Question $q) use ($textToCat, $generalId) {
            $cid = $textToCat[$q->question_text] ?? $generalId;
            if ($cid && $q->category_id !== $cid) {
                $q->update(['category_id' => $cid]);
            }
        });
    }
}
