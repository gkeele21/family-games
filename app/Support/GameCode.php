<?php

namespace App\Support;

use App\Models\GameSession;

/**
 * Generates human-friendly game codes.
 *
 * Codes are common, easy-to-spell 6-letter words so hosts can read them off a
 * TV and players can type them without squinting at ambiguous characters. The
 * list is intentionally wholesome and free of easily-misread words.
 */
class GameCode
{
    /**
     * Curated 6-letter words. Kept simple, common, and family-friendly.
     */
    public const WORDS = [
        'ANCHOR', 'ANIMAL', 'ANSWER', 'AUTUMN', 'BANANA', 'BASKET', 'BEACON',
        'BEAVER', 'BISHOP', 'BOTTLE', 'BRANCH', 'BREEZE', 'BRIDGE', 'BRIGHT',
        'BUBBLE', 'BUCKET', 'BUNDLE', 'BUTTON', 'CACTUS', 'CAMERA', 'CANDLE',
        'CANYON', 'CARROT', 'CASTLE', 'CELERY', 'CHERRY', 'CIRCLE', 'CIRCUS',
        'CLOVER', 'COFFEE', 'COMEDY', 'COOKIE', 'COPPER', 'CORNER', 'COTTON',
        'COUGAR', 'CRAYON', 'DAHLIA', 'DAMSON', 'DANGER', 'DAZZLE', 'DESERT',
        'DINGHY', 'DINNER', 'DONKEY', 'DRAGON', 'DREAMY', 'ENGINE', 'FALCON',
        'FIDDLE', 'FINGER', 'FIZZLE', 'FLOWER', 'FOREST', 'FRIEND', 'FROSTY',
        'GARDEN', 'GARLIC', 'GERBIL', 'GIGGLE', 'GINGER', 'GLIDER', 'GLOOMY',
        'GOBLIN', 'GOLDEN', 'GRAPES', 'GRAVEL', 'GROOVY', 'GUITAR', 'HAMMER',
        'HANGAR', 'HARBOR', 'HELMET', 'HERMIT', 'HICCUP', 'HOCKEY', 'HONEST',
        'HORNET', 'HUDDLE', 'HUNTER', 'ICICLE', 'IGLOOS', 'ISLAND', 'JACKET',
        'JAGUAR', 'JIGSAW', 'JOCKEY', 'JOSTLE', 'JUMBLE', 'JUNGLE', 'JUNIOR',
        'KETTLE', 'KITTEN', 'KOALAS', 'LADDER', 'LAPTOP', 'LAUNCH', 'LEMONS',
        'LIZARD', 'LOCKET', 'LUMBER', 'MAGNET', 'MAMMAL', 'MAPLES', 'MARBLE',
        'MARKER', 'MEADOW', 'MELODY', 'MINNOW', 'MIRROR', 'MITTEN', 'MONKEY',
        'MUFFIN', 'MYSTIC', 'NATURE', 'NECTAR', 'NEEDLE', 'NICKEL', 'NOODLE',
        'NUGGET', 'OCELOT', 'ORANGE', 'ORCHID', 'OSPREY', 'OTTERS', 'OYSTER',
        'PADDLE', 'PALACE', 'PANDAS', 'PARROT', 'PEACHY', 'PEANUT', 'PEBBLE',
        'PENCIL', 'PEPPER', 'PICNIC', 'PIGEON', 'PILLOW', 'PIRATE', 'PLANET',
        'POCKET', 'POLLEN', 'PONIES', 'POTATO', 'PRETTY', 'PUDDLE', 'PUZZLE',
        'QUARTZ', 'QUIVER', 'RABBIT', 'RADISH', 'RANGER', 'RIBBON', 'RIPPLE',
        'ROCKET', 'ROOKIE', 'RUBBER', 'RUFFLE', 'SADDLE', 'SALMON', 'SANDAL',
        'SAUCER', 'SEASON', 'SESAME', 'SHOVEL', 'SHRIMP', 'SILVER', 'SIMPLE',
        'SLEIGH', 'SMOOTH', 'SNAPPY', 'SOCCER', 'SPIDER', 'SPRING', 'SPROUT',
        'SQUARE', 'SQUASH', 'STABLE', 'STREAM', 'SUGARS', 'SUMMER', 'SUNSET',
        'SWIVEL', 'TABLES', 'TACKLE', 'TALCUM', 'TEAPOT', 'TEMPLE', 'THRONE',
        'TICKLE', 'TIGERS', 'TIMBER', 'TINSEL', 'TOFFEE', 'TOMATO', 'TOPHAT',
        'TOUCAN', 'TRAVEL', 'TRIPOD', 'TROPHY', 'TUNNEL', 'TURBAN', 'TURNIP',
        'TURTLE', 'UMPIRE', 'VALLEY', 'VELVET', 'VIOLET', 'VOYAGE', 'WAFFLE',
        'WALNUT', 'WALRUS', 'WANDER', 'WEASEL', 'WHALES', 'WIGGLE', 'WILLOW',
        'WINDOW', 'WINTER', 'WIZARD', 'WONDER', 'YELLOW', 'YOGURT', 'ZEBRAS',
        'ZENITH', 'ZIGZAG', 'ZINNIA', 'ZIPPER',
    ];

    /**
     * Generate a unique, human-friendly game code.
     *
     * Uniqueness is enforced only against games that are still joinable
     * (anything but "completed"), so friendly words can be recycled once a
     * game wraps up rather than being permanently consumed.
     */
    public static function generate(): string
    {
        $words = self::validWords();

        $active = GameSession::where('status', '!=', 'completed')
            ->pluck('invite_code')
            ->all();

        $available = array_values(array_diff($words, $active));

        // Every friendly word is currently in an active game (extremely
        // unlikely) — fall back to a word plus a digit to stay unique.
        if (empty($available)) {
            do {
                $code = substr($words[array_rand($words)], 0, 5).random_int(0, 9);
            } while (in_array($code, $active, true));

            return $code;
        }

        return $available[array_rand($available)];
    }

    /**
     * Exactly-6-letter, uppercase words from the curated list.
     *
     * Guards against stray whitespace or wrong-length entries slipping into
     * WORDS so a bad list entry can never produce an invalid code.
     */
    private static function validWords(): array
    {
        return array_values(array_filter(
            array_map(fn (string $w) => strtoupper(trim($w)), self::WORDS),
            fn (string $w) => strlen($w) === 6 && ctype_alpha($w),
        ));
    }
}
