<?php

namespace Tests\Feature\Scorekeeper;

use App\Mail\HouseholdInvitation;
use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\HouseholdInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class HouseholdInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_sends_email_and_flashes_success(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('scorekeeper.households.invites.store', $household), [
                'email' => 'Bert@Example.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(
            HouseholdInvitation::class,
            fn (HouseholdInvitation $mail) => $mail->hasTo('bert@example.com'),
        );
        $this->assertDatabaseHas('household_invites', ['email' => 'bert@example.com']);
    }

    public function test_failed_invite_email_returns_error_and_removes_invite(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new TransportException('connection refused'));

        $this->actingAs($owner)
            ->post(route('scorekeeper.households.invites.store', $household), [
                'email' => 'bert@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('household_invites', ['email' => 'bert@example.com']);
    }

    public function test_matching_email_can_accept_invite(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $invitee = User::factory()->create(['email' => 'join@example.com']);
        $invite = $this->inviteTo($household, $owner, 'join@example.com');

        $this->actingAs($invitee)
            ->post(route('scorekeeper.invites.accept', $invite->token))
            ->assertRedirect(route('scorekeeper.households.show', $household->id));

        $this->assertDatabaseHas('household_user', [
            'household_id' => $household->id,
            'user_id'      => $invitee->id,
            'role'         => 'member',
        ]);
        // Accepting also puts the new member on the roster.
        $this->assertDatabaseHas('players', [
            'household_id' => $household->id,
            'user_id'      => $invitee->id,
        ]);
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_invite_rejected_for_different_email(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $other = User::factory()->create(['email' => 'someone-else@example.com']);
        $invite = $this->inviteTo($household, $owner, 'invited@example.com');

        $this->actingAs($other)
            ->post(route('scorekeeper.invites.accept', $invite->token))
            ->assertForbidden();

        $this->assertDatabaseMissing('household_user', [
            'household_id' => $household->id,
            'user_id'      => $other->id,
        ]);
    }

    public function test_player_invite_links_account_to_existing_player(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $player = $household->players()->create(['name' => 'Bert']);

        // Send targeting the player.
        $this->actingAs($owner)
            ->post(route('scorekeeper.households.invites.store', $household), [
                'email'     => 'bert@example.com',
                'player_id' => $player->id,
            ])
            ->assertRedirect();

        $invite = HouseholdInvite::firstOrFail();
        $this->assertSame($player->id, $invite->player_id);
        $this->assertSame('guest', $invite->role);

        // Accept as a new account with the invited email.
        $bert = User::factory()->create(['email' => 'bert@example.com']);
        $playerCountBefore = $household->players()->count();

        $this->actingAs($bert)
            ->post(route('scorekeeper.invites.accept', $invite->token))
            ->assertRedirect();

        $player->refresh();
        $this->assertSame($bert->id, $player->user_id);
        $this->assertFalse($player->is_guest);
        // Linked to the existing player — no duplicate roster entry created.
        $this->assertSame($playerCountBefore, $household->players()->count());
        $this->assertDatabaseHas('household_user', [
            'household_id' => $household->id, 'user_id' => $bert->id, 'role' => 'guest',
        ]);
    }

    public function test_player_invite_rejected_for_already_linked_player(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $linked = User::factory()->create();
        $player = $household->players()->create(['name' => 'Linked', 'user_id' => $linked->id]);

        $this->actingAs($owner)
            ->post(route('scorekeeper.households.invites.store', $household), [
                'email'     => 'someone@example.com',
                'player_id' => $player->id,
            ])
            ->assertStatus(422);
    }

    public function test_player_invite_falls_back_when_player_deleted(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $player = $household->players()->create(['name' => 'Gone']);

        $this->actingAs($owner)->post(
            route('scorekeeper.households.invites.store', $household),
            ['email' => 'gone@example.com', 'player_id' => $player->id],
        );
        $invite = HouseholdInvite::firstOrFail();
        $player->delete(); // player removed before accepting → plain invite

        $user = User::factory()->create(['email' => 'gone@example.com']);
        $this->actingAs($user)
            ->post(route('scorekeeper.invites.accept', $invite->token))
            ->assertRedirect();

        // Fallback path creates a roster player for the new member.
        $this->assertDatabaseHas('players', [
            'household_id' => $household->id, 'user_id' => $user->id,
        ]);
    }

    public function test_public_preview_is_visible_without_auth(): void
    {
        $owner = User::factory()->create();
        $household = $this->householdOwnedBy($owner);
        $invite = $this->inviteTo($household, $owner, 'guest@example.com');

        $this->get(route('scorekeeper.invites.show', $invite->token))->assertOk();
    }

    private function householdOwnedBy(User $owner): Household
    {
        $household = Household::create(['name' => 'H', 'owner_user_id' => $owner->id]);
        $household->members()->attach($owner->id, ['role' => 'owner']);

        return $household;
    }

    private function inviteTo(Household $household, User $inviter, string $email): HouseholdInvite
    {
        return HouseholdInvite::create([
            'household_id'       => $household->id,
            'email'              => $email,
            'invited_by_user_id' => $inviter->id,
            'role'               => 'member',
            'token'              => Str::random(48),
            'expires_at'         => now()->addDays(14),
        ]);
    }
}
