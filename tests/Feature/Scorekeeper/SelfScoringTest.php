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

class SelfScoringTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $guest;
    private Household $household;
    private ScoredGame $game;
    private Round $round;
    private int $selfCompetitorId;
    private int $otherCompetitorId;

    private function startGame(bool $allowSelfScoring): void
    {
        $this->owner = User::factory()->create();
        $this->household = Household::create(['name' => 'H', 'owner_user_id' => $this->owner->id]);
        $this->household->members()->attach($this->owner->id, ['role' => 'owner']);

        // A guest account linked to a roster player (the player-invite result).
        $this->guest = User::factory()->create();
        $this->household->members()->attach($this->guest->id, ['role' => 'guest']);
        $self = $this->household->players()->create(['name' => 'Bert', 'user_id' => $this->guest->id]);
        $other = $this->household->players()->create(['name' => 'Tara']);

        $template = GameTemplate::create([
            'name' => 'T', 'household_id' => $this->household->id, 'is_system' => false,
            'low_score_wins' => false, 'team_based' => false,
            'allow_self_scoring' => $allowSelfScoring,
            'score_fields' => [['key' => 'score', 'label' => 'Score', 'counts_toward_total' => true]],
        ]);

        $svc = app(ScoreGameService::class);
        $this->game = $svc->startFromTemplate($this->household, $template, [
            ['name' => 'Bert', 'player_ids' => [$self->id]],
            ['name' => 'Tara', 'player_ids' => [$other->id]],
        ], $this->owner);
        $this->round = $svc->addRound($this->game);

        $competitors = $this->game->competitors()->orderBy('display_order')->get();
        $this->selfCompetitorId = $competitors[0]->id;
        $this->otherCompetitorId = $competitors[1]->id;
    }

    public function test_flag_snapshots_from_template_onto_game(): void
    {
        $this->startGame(true);
        $this->assertTrue($this->game->fresh()->allow_self_scoring);
    }

    public function test_guest_can_enter_own_scores_when_enabled(): void
    {
        $this->startGame(true);

        $this->actingAs($this->guest)
            ->patch(route('scorekeeper.games.rounds.update', [$this->game, $this->round]), [
                'scores' => [$this->selfCompetitorId => ['score' => 42]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('round_scores', [
            'round_id' => $this->round->id, 'competitor_id' => $this->selfCompetitorId,
        ]);
    }

    public function test_guest_cannot_enter_someone_elses_scores(): void
    {
        $this->startGame(true);

        $this->actingAs($this->guest)
            ->patch(route('scorekeeper.games.rounds.update', [$this->game, $this->round]), [
                'scores' => [$this->otherCompetitorId => ['score' => 99]],
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_score_when_template_disallows(): void
    {
        $this->startGame(false);

        $this->actingAs($this->guest)
            ->patch(route('scorekeeper.games.rounds.update', [$this->game, $this->round]), [
                'scores' => [$this->selfCompetitorId => ['score' => 42]],
            ])
            ->assertForbidden();
    }

    public function test_guest_bulk_save_limited_to_own_scores(): void
    {
        $this->startGame(true);

        $this->actingAs($this->guest)
            ->patch(route('scorekeeper.games.scores.update', $this->game), [
                'rounds' => [
                    $this->round->id => [$this->selfCompetitorId => ['score' => 42]],
                ],
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('round_scores', [
            'round_id' => $this->round->id, 'competitor_id' => $this->selfCompetitorId,
        ]);

        $this->actingAs($this->guest)
            ->patch(route('scorekeeper.games.scores.update', $this->game), [
                'rounds' => [
                    $this->round->id => [$this->otherCompetitorId => ['score' => 99]],
                ],
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_run_the_game(): void
    {
        $this->startGame(true);

        $this->actingAs($this->guest)
            ->post(route('scorekeeper.games.rounds.add', $this->game))
            ->assertForbidden();
        $this->actingAs($this->guest)
            ->post(route('scorekeeper.games.complete', $this->game))
            ->assertForbidden();
        $this->actingAs($this->guest)
            ->delete(route('scorekeeper.games.destroy', $this->game))
            ->assertForbidden();
        $this->actingAs($this->guest)
            ->post(route('scorekeeper.games.competitors.store', $this->game), [
                'new_player_name' => 'Intruder',
            ])
            ->assertForbidden();
    }

    public function test_owner_still_scores_everyone(): void
    {
        $this->startGame(true);

        $this->actingAs($this->owner)
            ->patch(route('scorekeeper.games.rounds.update', [$this->game, $this->round]), [
                'scores' => [
                    $this->selfCompetitorId  => ['score' => 10],
                    $this->otherCompetitorId => ['score' => 20],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('round_scores', [
            'round_id' => $this->round->id, 'competitor_id' => $this->otherCompetitorId,
        ]);
    }
}
