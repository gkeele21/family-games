<?php

namespace Tests\Feature\Scorekeeper;

use App\Models\Scorekeeper\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_household_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('scorekeeper.households.store'), ['name' => 'The Keeles']);

        $household = Household::firstOrFail();
        $response->assertRedirect(route('scorekeeper.households.show', $household));
        $this->assertDatabaseHas('household_user', [
            'household_id' => $household->id,
            'user_id'      => $user->id,
            'role'         => 'owner',
        ]);
    }

    public function test_scorekeeper_home_auto_provisions_a_household(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, $user->households()->count());

        $response = $this->actingAs($user)->get(route('scorekeeper.home'));

        $user->refresh();
        $this->assertSame(1, $user->households()->count());
        $response->assertRedirect(route(
            'scorekeeper.households.games.index',
            $user->households()->first(),
        ));

        // The member is auto-added to the roster so they're selectable.
        $this->assertDatabaseHas('players', [
            'household_id' => $user->households()->first()->id,
            'user_id'      => $user->id,
        ]);
    }

    public function test_scorekeeper_home_returns_to_last_visited_household(): void
    {
        $user = User::factory()->create();
        $this->householdOwnedBy($user);
        $lake = $this->householdOwnedBy($user);

        // Working in a household stamps it as the latest.
        $this->actingAs($user)
            ->get(route('scorekeeper.households.games.index', $lake))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('scorekeeper.home'))
            ->assertRedirect(route('scorekeeper.households.games.index', $lake));
    }

    public function test_scorekeeper_home_falls_back_to_picker_when_nothing_remembered(): void
    {
        $user = User::factory()->create();
        $this->householdOwnedBy($user);
        $this->householdOwnedBy($user);

        $this->actingAs($user)
            ->get(route('scorekeeper.home'))
            ->assertRedirect(route('scorekeeper.households.index'));
    }

    public function test_scorekeeper_home_ignores_a_household_the_user_no_longer_belongs_to(): void
    {
        $user = User::factory()->create();
        $this->householdOwnedBy($user);
        $this->householdOwnedBy($user);
        $other = $this->householdOwnedBy(User::factory()->create());

        $user->forceFill(['last_household_id' => $other->id])->save();

        $this->actingAs($user)
            ->get(route('scorekeeper.home'))
            ->assertRedirect(route('scorekeeper.households.index'));
    }

    public function test_non_member_cannot_view_household(): void
    {
        $household = $this->householdOwnedBy(User::factory()->create());
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('scorekeeper.households.show', $household))
            ->assertForbidden();
    }

    public function test_member_can_view_household(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);

        // The old hub URL now lands on the People tab.
        $this->actingAs($owner)
            ->get(route('scorekeeper.households.show', $household))
            ->assertRedirect(route('scorekeeper.households.people', $household));

        $this->actingAs($owner)
            ->get(route('scorekeeper.households.people', $household))
            ->assertOk();

        // Pre-merge URLs (Players and Sharing tabs) redirect to People.
        $this->actingAs($owner)
            ->get("/scorekeeper/households/{$household->id}/players")
            ->assertRedirect(route('scorekeeper.households.people', $household));

        $this->actingAs($owner)
            ->get("/scorekeeper/households/{$household->id}/sharing")
            ->assertRedirect(route('scorekeeper.households.people', $household));
    }

    public function test_only_owner_can_rename(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $member = User::factory()->create();
        $household->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->patch(route('scorekeeper.households.update', $household), ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('scorekeeper.households.update', $household), ['name' => 'Renamed'])
            ->assertRedirect();

        $this->assertDatabaseHas('households', ['id' => $household->id, 'name' => 'Renamed']);
    }

    public function test_owner_can_delete_household_and_data_cascades(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $player = $household->players()->create(['name' => 'P']);

        $this->actingAs($owner)
            ->delete(route('scorekeeper.households.destroy', $household))
            ->assertRedirect(route('scorekeeper.households.index'));

        $this->assertDatabaseMissing('households', ['id' => $household->id]);
        $this->assertDatabaseMissing('players', ['id' => $player->id]);
    }

    public function test_non_owner_cannot_delete_household(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $member = User::factory()->create();
        $household->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->delete(route('scorekeeper.households.destroy', $household))
            ->assertForbidden();
        $this->assertDatabaseHas('households', ['id' => $household->id]);
    }

    public function test_member_can_leave_but_owner_cannot(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $member = User::factory()->create();
        $household->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->delete(route('scorekeeper.households.leave', $household))
            ->assertRedirect(route('scorekeeper.households.index'));
        $this->assertDatabaseMissing('household_user', [
            'household_id' => $household->id, 'user_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('scorekeeper.households.leave', $household))
            ->assertStatus(422);
    }

    public function test_manage_page_lists_households_with_counts(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $household->players()->create(['name' => 'P1']);
        $household->players()->create(['name' => 'P2']);

        $this->actingAs($owner)
            ->get(route('scorekeeper.households.index'))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Scorekeeper/Households/Index')
                ->has('households', 1)
                ->where('households.0.is_owner', true)
                ->where('households.0.players_count', 2)
                ->where('households.0.members_count', 1));
    }

    private function householdOwnedBy(User $owner): Household
    {
        $household = Household::create(['name' => 'H', 'owner_user_id' => $owner->id]);
        $household->members()->attach($owner->id, ['role' => 'owner']);

        return $household;
    }
}
