<?php

namespace Tests\Feature\Scorekeeper;

use App\Models\Scorekeeper\GameTemplate;
use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\ScoredGame;
use App\Models\User;
use App\Services\Scorekeeper\ScoreGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_a_player_to_an_individual_game(): void
    {
        [$user, $household, $players] = $this->gameContext(4, false);
        $game = $this->start($household, false, [
            ['name' => $players[0]->name, 'player_ids' => [$players[0]->id]],
            ['name' => $players[1]->name, 'player_ids' => [$players[1]->id]],
        ], $user);

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.store', $game), [
                'player_id' => $players[2]->id,
            ])
            ->assertRedirect();

        $this->assertSame(3, $game->competitors()->count());
        $this->assertDatabaseHas('competitor_player', ['player_id' => $players[2]->id]);
    }

    public function test_cannot_add_a_player_already_in_the_game(): void
    {
        [$user, $household, $players] = $this->gameContext(3, false);
        $game = $this->start($household, false, [
            ['name' => $players[0]->name, 'player_ids' => [$players[0]->id]],
            ['name' => $players[1]->name, 'player_ids' => [$players[1]->id]],
        ], $user);

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.store', $game), [
                'player_id' => $players[0]->id,
            ])
            ->assertStatus(422);
    }

    public function test_removing_a_competitor_is_blocked_below_two(): void
    {
        [$user, $household, $players] = $this->gameContext(3, false);
        $game = $this->start($household, false, [
            ['name' => $players[0]->name, 'player_ids' => [$players[0]->id]],
            ['name' => $players[1]->name, 'player_ids' => [$players[1]->id]],
        ], $user);
        $first = $game->competitors()->orderBy('display_order')->first();

        $this->actingAs($user)
            ->delete(route('scorekeeper.games.competitors.destroy', [$game, $first]))
            ->assertStatus(422);
        $this->assertSame(2, $game->competitors()->count());
    }

    public function test_can_add_a_team_and_a_member(): void
    {
        [$user, $household, $players] = $this->gameContext(6, true);
        $game = $this->start($household, true, [
            ['name' => 'Reds', 'player_ids' => [$players[0]->id, $players[1]->id]],
            ['name' => 'Blues', 'player_ids' => [$players[2]->id, $players[3]->id]],
        ], $user);

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.store', $game), ['name' => 'Greens'])
            ->assertRedirect();
        $greens = $game->competitors()->where('name', 'Greens')->firstOrFail();

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.members.add', [$game, $greens]), [
                'player_id' => $players[4]->id,
            ])
            ->assertRedirect();

        $this->assertSame(3, $game->competitors()->count());
        $this->assertDatabaseHas('competitor_player', [
            'competitor_id' => $greens->id, 'player_id' => $players[4]->id,
        ]);
    }

    public function test_removing_the_last_member_of_a_team_is_blocked(): void
    {
        [$user, $household, $players] = $this->gameContext(3, true);
        $game = $this->start($household, true, [
            ['name' => 'Reds', 'player_ids' => [$players[0]->id]],
            ['name' => 'Blues', 'player_ids' => [$players[1]->id]],
        ], $user);
        $reds = $game->competitors()->where('name', 'Reds')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('scorekeeper.games.competitors.members.remove', [$game, $reds, $players[0]->id]))
            ->assertStatus(422);
        $this->assertSame(1, $reds->players()->count());
    }

    public function test_cannot_edit_a_completed_game(): void
    {
        [$user, $household, $players] = $this->gameContext(3, false);
        $game = $this->start($household, false, [
            ['name' => $players[0]->name, 'player_ids' => [$players[0]->id]],
            ['name' => $players[1]->name, 'player_ids' => [$players[1]->id]],
        ], $user);
        $game->update(['is_complete' => true]);

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.store', $game), [
                'player_id' => $players[2]->id,
            ])
            ->assertForbidden();
    }

    public function test_can_add_a_new_guest_player_not_on_the_roster(): void
    {
        [$user, $household, $players] = $this->gameContext(2, false);
        $game = $this->start($household, false, [
            ['name' => $players[0]->name, 'player_ids' => [$players[0]->id]],
            ['name' => $players[1]->name, 'player_ids' => [$players[1]->id]],
        ], $user);

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.store', $game), [
                'new_player_name' => 'Guest Gary',
            ])
            ->assertRedirect();

        $this->assertSame(3, $game->competitors()->count());
        // Created as a guest (kept off the roster).
        $this->assertDatabaseHas('players', [
            'household_id' => $household->id, 'name' => 'Guest Gary', 'is_guest' => true,
        ]);
        $this->assertSame(2, $household->players()->where('is_guest', false)->count());
    }

    public function test_new_player_can_be_added_to_the_household_roster(): void
    {
        [$user, $household, $players] = $this->gameContext(2, false);
        $game = $this->start($household, false, [
            ['name' => $players[0]->name, 'player_ids' => [$players[0]->id]],
            ['name' => $players[1]->name, 'player_ids' => [$players[1]->id]],
        ], $user);

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.store', $game), [
                'new_player_name'  => 'Nina',
                'add_to_household' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('players', [
            'household_id' => $household->id, 'name' => 'Nina', 'is_guest' => false,
        ]);
        $this->assertSame(3, $household->players()->where('is_guest', false)->count());
    }

    public function test_can_add_a_guest_to_a_team(): void
    {
        [$user, $household, $players] = $this->gameContext(2, true);
        $game = $this->start($household, true, [
            ['name' => 'Reds', 'player_ids' => [$players[0]->id]],
            ['name' => 'Blues', 'player_ids' => [$players[1]->id]],
        ], $user);
        $reds = $game->competitors()->where('name', 'Reds')->firstOrFail();

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.members.add', [$game, $reds]), [
                'new_player_name' => 'Guest Grace',
            ])
            ->assertRedirect();

        $this->assertSame(2, $reds->players()->count());
        $this->assertDatabaseHas('players', [
            'household_id' => $household->id, 'name' => 'Guest Grace', 'is_guest' => true,
        ]);
    }

    public function test_can_reorder_competitors(): void
    {
        [$user, $household, $players] = $this->gameContext(3, false);
        $game = $this->start($household, false, [
            ['name' => $players[0]->name, 'player_ids' => [$players[0]->id]],
            ['name' => $players[1]->name, 'player_ids' => [$players[1]->id]],
            ['name' => $players[2]->name, 'player_ids' => [$players[2]->id]],
        ], $user);

        $ordered = $game->competitors()->orderBy('display_order')->pluck('id')->all();
        $reversed = array_reverse($ordered);

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.reorder', $game), [
                'competitor_ids' => $reversed,
            ])
            ->assertRedirect();

        $this->assertSame(
            $reversed,
            $game->competitors()->orderBy('display_order')->pluck('id')->all(),
        );
    }

    /**
     * @return array{0: User, 1: Household, 2: \Illuminate\Support\Collection}
     */
    public function test_mid_game_suggestion_add_carries_the_account_link(): void
    {
        [$user, $household, $players] = $this->gameContext(2, false);
        $game = $this->start($household, false, [
            ['name' => $players[0]->name, 'player_ids' => [$players[0]->id]],
            ['name' => $players[1]->name, 'player_ids' => [$players[1]->id]],
        ], $user);

        // "Aunt Jo" is a linked player in the user's other household.
        $linked = User::factory()->create();
        $lake = Household::create(['name' => 'Lake', 'owner_user_id' => $user->id]);
        $lake->members()->attach($user->id, ['role' => 'owner']);
        $lake->players()->create(['name' => 'Aunt Jo', 'user_id' => $linked->id]);

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.store', $game), [
                'new_player_name'  => 'Aunt Jo',
                'user_id'          => $linked->id,
                'add_to_household' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('players', [
            'household_id' => $household->id,
            'name'         => 'Aunt Jo',
            'user_id'      => $linked->id,
            'is_guest'     => false,
        ]);
        $this->assertSame(3, $game->competitors()->count());
    }

    public function test_mid_game_add_cannot_link_an_arbitrary_account(): void
    {
        [$user, $household, $players] = $this->gameContext(2, false);
        $game = $this->start($household, false, [
            ['name' => $players[0]->name, 'player_ids' => [$players[0]->id]],
            ['name' => $players[1]->name, 'player_ids' => [$players[1]->id]],
        ], $user);

        $stranger = User::factory()->create();

        $this->actingAs($user)
            ->post(route('scorekeeper.games.competitors.store', $game), [
                'new_player_name' => 'Stranger',
                'user_id'         => $stranger->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('players', [
            'household_id' => $household->id,
            'user_id'      => $stranger->id,
        ]);
    }

    private function gameContext(int $playerCount, bool $teamBased): array
    {
        $user = User::factory()->create();
        $household = Household::create(['name' => 'H', 'owner_user_id' => $user->id]);
        $household->members()->attach($user->id, ['role' => 'owner']);
        $players = collect(range(1, $playerCount))->map(
            fn ($i) => $household->players()->create(['name' => "P$i"]),
        );

        return [$user, $household, $players];
    }

    private function start(Household $household, bool $teamBased, array $competitors, User $user): ScoredGame
    {
        $template = GameTemplate::create([
            'name' => 'T', 'household_id' => $household->id, 'is_system' => false,
            'low_score_wins' => false, 'team_based' => $teamBased,
            'score_fields' => [['key' => 'score', 'label' => 'Score', 'counts_toward_total' => true]],
        ]);

        return app(ScoreGameService::class)
            ->startFromTemplate($household, $template, $competitors, $user);
    }
}
