<?php

namespace Tests\Feature\Scorekeeper;

use App\Models\GameType;
use App\Models\Scorekeeper\GameTemplate;
use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\HouseholdInvite;
use App\Models\Scorekeeper\ScoredGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GameListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_games_index_puts_unfinished_and_own_games_first(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);
        $me = $household->players()->where('user_id', $user->id)->first()
            ?? $household->players()->create(['name' => 'Me', 'user_id' => $user->id]);
        $alice = $household->players()->create(['name' => 'Alice']);
        $bob = $household->players()->create(['name' => 'Bob']);

        $gameType = GameType::create(['name' => 'Rummy 500', 'slug' => 'rummy-500', 'kind' => 'scorekeeper']);
        $template = GameTemplate::create([
            'name' => 'Rummy', 'household_id' => $household->id, 'target_score' => 500,
            'low_score_wins' => false, 'is_system' => false, 'team_based' => false,
            'game_type_id' => $gameType->id,
            'score_fields' => [['key' => 'score', 'label' => 'Score', 'counts_toward_total' => true]],
        ]);

        // Game without me (created first → older), game with me, and a completed one.
        $this->actingAs($user)->post(route('scorekeeper.households.games.store', $household), [
            'game_template_id' => $template->id, 'player_ids' => [$alice->id, $bob->id],
        ])->assertRedirect();
        $notMine = ScoredGame::latest('id')->first();

        $this->actingAs($user)->post(route('scorekeeper.households.games.store', $household), [
            'game_template_id' => $template->id, 'player_ids' => [$me->id, $alice->id],
        ])->assertRedirect();
        $mine = ScoredGame::latest('id')->first();

        $this->actingAs($user)->post(route('scorekeeper.households.games.store', $household), [
            'game_template_id' => $template->id, 'player_ids' => [$alice->id, $bob->id],
        ])->assertRedirect();
        $done = ScoredGame::latest('id')->first();
        $done->update(['is_complete' => true, 'ended_at' => now()]);

        HouseholdInvite::create([
            'household_id' => $household->id, 'email' => $user->email,
            'invited_by_user_id' => $user->id, 'role' => 'member',
            'token' => 'tok123', 'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('scorekeeper.households.games.index', $household))
            ->assertInertia(fn (Assert $p) => $p
                ->where('games.0.id', $mine->id)
                ->where('games.0.is_mine', true)
                ->where('games.1.id', $notMine->id)
                ->where('games.1.is_mine', false)
                ->where('games.2.id', $done->id)
                ->where('games.2.is_complete', true));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('activeGames.0.id', $mine->id)
                ->where('activeGames.0.is_mine', true)
                ->where('activeGames.1.is_mine', false)
                // Invite is to a household they already belong to → filtered out.
                ->where('pendingInvites', [])
                ->etc());
    }

    public function test_pending_invite_to_another_household_surfaces(): void
    {
        $owner = User::factory()->create();
        $other = $this->householdOwnedBy($owner);
        $invitee = User::factory()->create(['email' => 'guest@example.com']);

        HouseholdInvite::create([
            'household_id' => $other->id, 'email' => 'GUEST@example.com',
            'invited_by_user_id' => $owner->id, 'role' => 'guest',
            'token' => 'tok999', 'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($invitee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('pendingInvites.0.token', 'tok999')
                ->where('pendingInvites.0.household_name', 'H')
                ->where('pendingInvites.0.role', 'guest')
                ->etc());
    }

    private function householdOwnedBy(User $owner): Household
    {
        $household = Household::create(['name' => 'H', 'owner_user_id' => $owner->id]);
        $household->members()->attach($owner->id, ['role' => 'owner']);

        return $household;
    }
}
