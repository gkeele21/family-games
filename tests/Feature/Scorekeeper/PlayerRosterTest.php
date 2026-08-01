<?php

namespace Tests\Feature\Scorekeeper;

use App\Models\Scorekeeper\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlayerRosterTest extends TestCase
{
    use RefreshDatabase;

    private function householdOwnedBy(User $owner, string $name = 'Home'): Household
    {
        $household = Household::create([
            'name'          => $name,
            'owner_user_id' => $owner->id,
        ]);
        $household->members()->attach($owner->id, ['role' => 'owner']);
        $household->ensureRosterPlayer($owner);

        return $household;
    }

    public function test_people_page_suggests_players_from_other_households(): void
    {
        $owner = User::factory()->create();
        $home = $this->householdOwnedBy($owner, 'Home');
        $lake = $this->householdOwnedBy($owner, 'Lake House');
        $lake->players()->create(['name' => 'Grandma']);

        $this->actingAs($owner)
            ->get(route('scorekeeper.households.people', $home))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scorekeeper/Households/People')
                ->where('suggestions.0.name', 'Grandma')
                ->where('suggestions.0.source', 'Lake House'));
    }

    public function test_suggestions_exclude_names_already_on_this_roster(): void
    {
        $owner = User::factory()->create();
        $home = $this->householdOwnedBy($owner, 'Home');
        $home->players()->create(['name' => 'Grandma']);
        $lake = $this->householdOwnedBy($owner, 'Lake House');
        $lake->players()->create(['name' => 'Grandma']);

        $this->actingAs($owner)
            ->get(route('scorekeeper.households.people', $home))
            ->assertInertia(fn (Assert $page) => $page
                ->where('suggestions', []));
    }

    public function test_suggestions_include_friends(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create(['first_name' => 'Fred', 'last_name' => 'Friend']);
        $owner->friends()->attach($friend->id);
        $home = $this->householdOwnedBy($owner, 'Home');

        $this->actingAs($owner)
            ->get(route('scorekeeper.households.people', $home))
            ->assertInertia(fn (Assert $page) => $page
                ->where('suggestions.0.name', 'Fred Friend')
                ->where('suggestions.0.user_id', $friend->id)
                ->where('suggestions.0.source', 'Friend'));
    }

    public function test_adding_a_suggested_player_carries_the_account_link(): void
    {
        $owner = User::factory()->create();
        $linked = User::factory()->create();
        $home = $this->householdOwnedBy($owner, 'Home');
        $lake = $this->householdOwnedBy($owner, 'Lake House');
        $lake->players()->create(['name' => 'Aunt Jo', 'user_id' => $linked->id]);

        $this->actingAs($owner)
            ->post(route('scorekeeper.households.players.store', $home), [
                'name'    => 'Aunt Jo',
                'user_id' => $linked->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('players', [
            'household_id' => $home->id,
            'name'         => 'Aunt Jo',
            'user_id'      => $linked->id,
        ]);
    }

    public function test_cannot_link_an_arbitrary_account(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $home = $this->householdOwnedBy($owner, 'Home');

        $this->actingAs($owner)
            ->post(route('scorekeeper.households.players.store', $home), [
                'name'    => 'Stranger',
                'user_id' => $stranger->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('players', [
            'household_id' => $home->id,
            'user_id'      => $stranger->id,
        ]);
    }

    public function test_cannot_link_the_same_account_twice_in_a_household(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $owner->friends()->attach($friend->id);
        $home = $this->householdOwnedBy($owner, 'Home');
        $home->players()->create(['name' => 'Fred', 'user_id' => $friend->id]);

        $this->actingAs($owner)
            ->post(route('scorekeeper.households.players.store', $home), [
                'name'    => 'Fred Again',
                'user_id' => $friend->id,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(
            1,
            $home->players()->where('user_id', $friend->id)->count(),
        );
    }

    public function test_plain_name_add_still_works(): void
    {
        $owner = User::factory()->create();
        $home = $this->householdOwnedBy($owner, 'Home');

        $this->actingAs($owner)
            ->post(route('scorekeeper.households.players.store', $home), [
                'name' => 'Grandma',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('players', [
            'household_id' => $home->id,
            'name'         => 'Grandma',
            'user_id'      => null,
        ]);
    }
}
