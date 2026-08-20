<?php

namespace Tests\Feature\Scorekeeper;

use App\Models\Scorekeeper\GameTemplate;
use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\Round;
use App\Models\Scorekeeper\ScoredGame;
use App\Models\User;
use App\Services\Scorekeeper\ScoreGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Several people scoring the same round from their own phones. Each save
 * carries only the cells that device edited, and must leave everyone else's
 * scores alone.
 */
class ConcurrentScoringTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Household $household;
    private ScoredGame $game;
    private Round $round;
    /** @var array<int> */
    private array $competitorIds;

    private function startGame(): void
    {
        $this->owner = User::factory()->create();
        $this->household = Household::create(['name' => 'H', 'owner_user_id' => $this->owner->id]);
        $this->household->members()->attach($this->owner->id, ['role' => 'owner']);

        $players = collect(['Ann', 'Ben', 'Cal'])
            ->map(fn (string $name) => $this->household->players()->create(['name' => $name]));

        $template = GameTemplate::create([
            'name' => 'Panda', 'household_id' => $this->household->id, 'is_system' => false,
            'low_score_wins' => false, 'team_based' => false, 'allow_self_scoring' => true,
            'score_fields' => [
                ['key' => 'yellow', 'label' => 'Yellow', 'counts_toward_total' => true],
                ['key' => 'purple', 'label' => 'Purple', 'counts_toward_total' => true],
                ['key' => 'blue', 'label' => 'Blue', 'counts_toward_total' => true],
            ],
        ]);

        $svc = app(ScoreGameService::class);
        $this->game = $svc->startFromTemplate(
            $this->household,
            $template,
            $players->map(fn ($p) => ['name' => $p->name, 'player_ids' => [$p->id]])->all(),
            $this->owner,
        );
        $this->round = $svc->addRound($this->game);
        $this->competitorIds = $this->game->competitors()
            ->orderBy('display_order')->pluck('id')->all();
    }

    private function save(array $scores): void
    {
        $this->actingAs($this->owner)
            ->patch(route('scorekeeper.games.scores.update', $this->game), [
                'rounds' => [$this->round->id => $scores],
            ])
            ->assertRedirect();
    }

    private function stored(int $competitorId): array
    {
        return $this->round->scores()
            ->where('competitor_id', $competitorId)
            ->first()
            ->values;
    }

    public function test_a_save_does_not_wipe_another_players_scores(): void
    {
        $this->startGame();
        [$ann, $ben] = $this->competitorIds;

        // Ben's phone saves his own row first.
        $this->save([$ben => ['yellow' => 5, 'purple' => 6, 'blue' => 7]]);

        // Ann's phone saves a second later, knowing nothing about Ben's row.
        $this->save([$ann => ['yellow' => 1, 'purple' => 2, 'blue' => 3]]);

        $this->assertSame(['yellow' => 5, 'purple' => 6, 'blue' => 7], $this->stored($ben));
        $this->assertSame(['yellow' => 1, 'purple' => 2, 'blue' => 3], $this->stored($ann));
    }

    public function test_a_save_does_not_wipe_untouched_fields_on_the_same_row(): void
    {
        $this->startGame();
        [$ann] = $this->competitorIds;

        $this->save([$ann => ['yellow' => 4, 'purple' => 9]]);
        // A later save that only carries 'blue' must keep the other two.
        $this->save([$ann => ['blue' => 2]]);

        $this->assertSame(['yellow' => 4, 'purple' => 9, 'blue' => 2], $this->stored($ann));
    }

    public function test_an_explicit_null_clears_just_that_field(): void
    {
        $this->startGame();
        [$ann] = $this->competitorIds;

        $this->save([$ann => ['yellow' => 4, 'purple' => 9, 'blue' => 2]]);
        $this->save([$ann => ['purple' => null]]);

        $this->assertSame(['yellow' => 4, 'blue' => 2], $this->stored($ann));
    }

    public function test_totals_reflect_every_players_saves(): void
    {
        $this->startGame();
        [$ann, $ben, $cal] = $this->competitorIds;

        $this->save([$ann => ['yellow' => 1, 'purple' => 2, 'blue' => 3]]);
        $this->save([$ben => ['yellow' => 5, 'purple' => 6, 'blue' => 7]]);
        $this->save([$cal => ['yellow' => 10]]);

        $totals = app(ScoreGameService::class)->totals($this->game->fresh());

        $this->assertSame(6, $totals[$ann]);
        $this->assertSame(18, $totals[$ben]);
        $this->assertSame(10, $totals[$cal]);
    }
}
