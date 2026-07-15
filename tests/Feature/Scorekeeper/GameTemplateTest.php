<?php

namespace Tests\Feature\Scorekeeper;

use App\Models\GameType;
use App\Models\Scorekeeper\GameTemplate;
use App\Models\Scorekeeper\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GameTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_template_cannot_be_modified(): void
    {
        $user = User::factory()->create();
        $this->householdOwnedBy($user); // membership not required to be blocked, but realistic

        $system = GameTemplate::create(['name' => 'Hearts', 'is_system' => true]);

        $this->actingAs($user)
            ->patch(route('scorekeeper.templates.update', $system), ['name' => 'Hacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('game_templates', ['id' => $system->id, 'name' => 'Hearts']);
    }

    public function test_index_shows_system_and_own_but_not_other_households(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);

        GameTemplate::create(['name' => 'Sys', 'is_system' => true]);
        GameTemplate::create([
            'name' => 'Mine', 'household_id' => $household->id, 'created_by_user_id' => $user->id,
        ]);

        $otherHousehold = Household::create(['name' => 'Other', 'owner_user_id' => $user->id]);
        GameTemplate::create(['name' => 'Theirs', 'household_id' => $otherHousehold->id]);

        $this->actingAs($user)
            ->get(route('scorekeeper.households.templates.index', $household))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scorekeeper/Templates/Index')
                ->has('templates', 2));
    }

    public function test_member_can_create_custom_template_and_new_game(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);

        $this->actingAs($user)
            ->post(route('scorekeeper.households.templates.store', $household), [
                'name'          => 'Family Rummy',
                'new_game_name' => 'Rummy 500',
                'target_score'  => 500,
                'low_score_wins' => false,
                'score_fields'  => [['label' => 'Score', 'counts_toward_total' => true]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('game_templates', [
            'household_id' => $household->id,
            'name'         => 'Family Rummy',
            'is_system'    => false,
        ]);
        // The "add a new game" path created a household-scoped scorekeeper game.
        $this->assertDatabaseHas('game_types', [
            'name'         => 'Rummy 500',
            'kind'         => 'scorekeeper',
            'household_id' => $household->id,
        ]);
    }

    public function test_create_template_referencing_existing_game(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);
        $game = GameType::create([
            'name' => 'Hearts', 'slug' => 'hearts', 'kind' => 'scorekeeper',
        ]);

        $this->actingAs($user)
            ->post(route('scorekeeper.households.templates.store', $household), [
                'name'         => 'Cutthroat Hearts',
                'game_type_id' => $game->id,
                'score_fields' => [['label' => 'Score', 'counts_toward_total' => true]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('game_templates', [
            'name' => 'Cutthroat Hearts', 'game_type_id' => $game->id,
        ]);
    }

    public function test_global_template_promotes_its_game_and_is_visible_to_other_households(): void
    {
        $userA = User::factory()->create();
        $hhA = $this->householdOwnedBy($userA);

        $this->actingAs($userA)
            ->post(route('scorekeeper.households.templates.store', $hhA), [
                'name'          => 'Shared Rummy',
                'new_game_name' => 'Shared Game',
                'is_global'     => true,
                'score_fields'  => [['label' => 'Score', 'counts_toward_total' => true]],
            ])
            ->assertRedirect();

        $template = GameTemplate::where('name', 'Shared Rummy')->firstOrFail();
        $this->assertTrue($template->is_global);
        $game = GameType::where('name', 'Shared Game')->firstOrFail();
        // Promoting the template promoted its household-owned game to global.
        $this->assertTrue($game->is_global);

        // A different household can now see both the game and the template.
        $hhB = $this->householdOwnedBy(User::factory()->create());
        $this->assertTrue(
            GameType::scorekeeper()->visibleTo($hhB->id)->whereKey($game->id)->exists(),
        );
        $this->assertTrue(
            GameTemplate::query()
                ->where(fn ($q) => $q->where('is_system', true)
                    ->orWhere('is_global', true)
                    ->orWhere('household_id', $hhB->id))
                ->whereKey($template->id)->exists(),
        );
    }

    public function test_score_fields_and_team_based_round_trip_with_generated_keys(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);

        $this->actingAs($user)
            ->post(route('scorekeeper.households.templates.store', $household), [
                'name'          => 'Pony Tail',
                'new_game_name' => 'Pony Tail',
                'team_based'    => true,
                'score_fields'  => [
                    ['label' => 'Base', 'counts_toward_total' => false],
                    ['label' => 'Points', 'counts_toward_total' => true],
                ],
            ])
            ->assertRedirect();

        $template = GameTemplate::where('name', 'Pony Tail')->firstOrFail();
        $this->assertTrue($template->team_based);
        $this->assertCount(2, $template->score_fields);
        $this->assertSame('base', $template->score_fields[0]['key']);
        $this->assertSame('points', $template->score_fields[1]['key']);
        $this->assertFalse($template->score_fields[0]['counts_toward_total']);
    }

    public function test_score_fields_can_carry_a_color(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);

        $this->actingAs($user)
            ->post(route('scorekeeper.households.templates.store', $household), [
                'name'          => 'Colorful',
                'new_game_name' => 'Colorful',
                'score_fields'  => [
                    ['label' => 'Red', 'counts_toward_total' => true, 'color' => '#ef4444'],
                    ['label' => 'Blue', 'counts_toward_total' => true, 'color' => null],
                ],
            ])
            ->assertRedirect();

        $template = GameTemplate::where('name', 'Colorful')->firstOrFail();
        $this->assertSame('#ef4444', $template->score_fields[0]['color']);
        $this->assertNull($template->score_fields[1]['color']);
    }

    public function test_template_name_must_be_unique_within_household(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);
        $game = GameType::create(['name' => 'Flip 7', 'slug' => 'flip-7', 'kind' => 'scorekeeper']);
        $payload = [
            'name'         => 'Flip 7',
            'game_type_id' => $game->id,
            'score_fields' => [['label' => 'Score', 'counts_toward_total' => true]],
        ];

        $this->actingAs($user)
            ->post(route('scorekeeper.households.templates.store', $household), $payload)
            ->assertRedirect();

        // Same name again in the same household → rejected.
        $this->actingAs($user)
            ->post(route('scorekeeper.households.templates.store', $household), $payload)
            ->assertSessionHasErrors('name');
        $this->assertSame(1, GameTemplate::where('name', 'Flip 7')->count());

        // A different household may use the same name.
        $otherUser = User::factory()->create();
        $otherHousehold = $this->householdOwnedBy($otherUser);
        $this->actingAs($otherUser)
            ->post(route('scorekeeper.households.templates.store', $otherHousehold), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Updating a template while keeping its own name is fine.
        $template = GameTemplate::where('household_id', $household->id)
            ->where('name', 'Flip 7')->firstOrFail();
        $this->actingAs($user)
            ->patch(route('scorekeeper.templates.update', $template), [
                ...$payload,
                'target_score' => 200,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_template_copy_to_another_household(): void
    {
        $user = User::factory()->create();
        $source = $this->householdOwnedBy($user);
        $target = $this->householdOwnedBy($user); // same user, second household

        // Household-owned game → must be recreated in the target.
        $game = GameType::create([
            'name' => 'Pony Tail', 'slug' => 'pony-tail', 'kind' => 'scorekeeper',
            'household_id' => $source->id,
        ]);
        $template = GameTemplate::create([
            'name' => 'Pony Tail', 'household_id' => $source->id, 'is_system' => false,
            'game_type_id' => $game->id, 'team_based' => true, 'low_score_wins' => false,
            'score_fields' => [
                ['key' => 'base', 'label' => 'Base', 'counts_toward_total' => false],
                ['key' => 'points', 'label' => 'Points', 'counts_toward_total' => true],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('scorekeeper.templates.copy', $template), [
                'target_household_id' => $target->id,
            ])
            ->assertRedirect();

        $copy = GameTemplate::where('household_id', $target->id)->firstOrFail();
        $this->assertSame('Pony Tail', $copy->name);
        $this->assertTrue($copy->team_based);
        $this->assertCount(2, $copy->score_fields);
        // Game recreated within the target household, not shared from source.
        $this->assertNotSame($game->id, $copy->game_type_id);
        $this->assertDatabaseHas('game_types', [
            'id' => $copy->game_type_id, 'household_id' => $target->id, 'name' => 'Pony Tail',
        ]);

        // Copying again suffixes the name.
        $this->actingAs($user)->post(route('scorekeeper.templates.copy', $template), [
            'target_household_id' => $target->id,
        ]);
        $this->assertDatabaseHas('game_templates', [
            'household_id' => $target->id, 'name' => 'Pony Tail (2)',
        ]);
    }

    public function test_template_copy_guards(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);
        $system = GameTemplate::create([
            'name' => 'Sys', 'is_system' => true,
            'score_fields' => [['key' => 'score', 'label' => 'Score', 'counts_toward_total' => true]],
        ]);
        $own = GameTemplate::create([
            'name' => 'Mine', 'household_id' => $household->id, 'is_system' => false,
            'score_fields' => [['key' => 'score', 'label' => 'Score', 'counts_toward_total' => true]],
        ]);

        // System templates can't be copied (already everywhere).
        $this->actingAs($user)
            ->post(route('scorekeeper.templates.copy', $system), [
                'target_household_id' => $household->id,
            ])
            ->assertStatus(422);

        // Target household the user doesn't belong to → 403.
        $stranger = $this->householdOwnedBy(User::factory()->create());
        $this->actingAs($user)
            ->post(route('scorekeeper.templates.copy', $own), [
                'target_household_id' => $stranger->id,
            ])
            ->assertForbidden();
    }

    public function test_template_requires_a_game(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);

        $this->actingAs($user)
            ->post(route('scorekeeper.households.templates.store', $household), [
                'name'         => 'No Game',
                'score_fields' => [['label' => 'Score', 'counts_toward_total' => true]],
            ])
            ->assertSessionHasErrors('game_type_id');

        $this->assertDatabaseMissing('game_templates', ['name' => 'No Game']);
    }

    public function test_template_requires_at_least_one_counting_field(): void
    {
        $user = User::factory()->create();
        $household = $this->householdOwnedBy($user);

        $this->actingAs($user)
            ->post(route('scorekeeper.households.templates.store', $household), [
                'name'         => 'Broken',
                'score_fields' => [['label' => 'Base', 'counts_toward_total' => false]],
            ])
            ->assertSessionHasErrors('score_fields');

        $this->assertDatabaseMissing('game_templates', ['name' => 'Broken']);
    }

    private function householdOwnedBy(User $owner): Household
    {
        $household = Household::create(['name' => 'H', 'owner_user_id' => $owner->id]);
        $household->members()->attach($owner->id, ['role' => 'owner']);

        return $household;
    }
}
