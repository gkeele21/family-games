<?php

namespace Tests\Feature\PropOff;

use App\Models\PropOff\CaptainInvitation;
use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestion;
use App\Models\PropOff\GroupQuestionAnswer;
use App\Models\PropOff\UserAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PropOffFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_middleware_blocks_regular_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('propoff.admin.events.index'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('propoff.admin.events.index'))
            ->assertOk();
    }

    public function test_full_flow_captain_invite_to_leaderboard(): void
    {
        $admin = $this->admin();
        $event = Event::create([
            'name' => 'Super Bowl', 'category' => 'sports',
            'event_date' => now()->addDays(7), 'status' => 'open',
            'created_by' => $admin->id,
        ]);
        $event->eventQuestions()->create([
            'question_text' => 'Who wins the coin toss?',
            'question_type' => 'multiple_choice',
            'options' => [['label' => 'Home', 'points' => 0], ['label' => 'Away', 'points' => 0]],
            'points' => 2, 'display_order' => 1,
        ]);
        $invite = CaptainInvitation::create([
            'event_id' => $event->id,
            'token' => CaptainInvitation::generateToken(),
            'created_by' => $admin->id,
        ]);

        // 1. A logged-out person becomes a passwordless guest CAPTAIN.
        $this->post(route('propoff.captain.groups.store', [$event, $invite->token]), [
            'name'           => 'Keele Party',
            'grading_source' => 'captain',
            'captain_name'   => 'Grant Keele',
        ])->assertRedirect(); // Inertia::location → plain 302 outside Inertia

        $captain = User::where('first_name', 'Grant')->where('role', 'guest')->firstOrFail();
        $this->assertNotNull($captain->guest_token);
        $group = Group::where('name', 'Keele Party')->firstOrFail();
        $this->assertTrue($group->isCaptain($captain));
        // Event questions fanned out into the group.
        $this->assertSame(1, $group->groupQuestions()->count());
        // Auto-created member invitation link.
        $this->assertSame(1, $group->invitations()->count());

        // 2. Magic-link login works for the captain later.
        auth()->logout();
        $this->get(route('propoff.guest.login', $captain->guest_token))
            ->assertRedirect();
        $this->assertAuthenticatedAs($captain);

        // 3. An anonymous member joins by name via the play flow.
        auth()->logout();
        $this->flushSession();
        $this->post(route('propoff.play.join.process', $group->code), [
            'name' => 'Bert Keele',
        ])->assertRedirect();
        $bert = User::where('first_name', 'Bert')->firstOrFail();
        $this->assertTrue($group->users()->whereKey($bert->id)->exists());

        // 4. Member answers the question (viewing the game creates the entry).
        $gq = $group->groupQuestions()->first();
        $this->actingAs($bert)->get(route('propoff.play.game', $group->code));
        $this->actingAs($bert)
            ->post(route('propoff.play.save', $group->code), [
                'answers' => [
                    ['group_question_id' => $gq->id, 'answer_text' => 'Away'],
                ],
            ]);
        $entry = $group->entries()->where('user_id', $bert->id)->first();
        $this->assertNotNull($entry, 'entry created via play flow');

        // 5. Captain grades after lock; leaderboard ranks Bert.
        $group->update(['entry_cutoff' => now()->subMinute()]);
        $this->actingAs($captain)
            ->post(route('propoff.groups.grading.setAnswer', [$group, $gq]), [
                'correct_answer' => 'Away',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('propoff_group_question_answers', [
            'group_id' => $group->id, 'group_question_id' => $gq->id,
        ]);

        $row = \App\Models\PropOff\Leaderboard::where('group_id', $group->id)
            ->where('user_id', $bert->id)->first();
        $this->assertNotNull($row, 'leaderboard row exists');
        $this->assertSame(1, $row->rank);
        $this->assertGreaterThanOrEqual(2, $row->total_score);
    }

    public function test_guest_cookie_silently_logs_guest_back_in(): void
    {
        $guest = User::create([
            'first_name' => 'Cookie', 'last_name' => 'Guest',
            'role' => 'guest', 'guest_token' => Str::random(32),
        ]);

        $this->withCookie('keeler_guest', $guest->guest_token)
            ->get('/')
            ->assertOk();
        $this->assertAuthenticatedAs($guest);
    }

    public function test_guest_upgrade_sets_password_and_flips_role(): void
    {
        $guest = User::create([
            'first_name' => 'Up', 'last_name' => 'Grade',
            'email' => 'upgrade@example.com',
            'role' => 'guest', 'guest_token' => Str::random(32),
        ]);

        $this->actingAs($guest)
            ->post(route('password.set'), [
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect();

        $guest->refresh();
        $this->assertNotNull($guest->password);
        $this->assertNull($guest->guest_token);
        $this->assertSame('user', $guest->role);
    }

    public function test_history_page_lists_years_for_completed_entries(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_date' => '2026-02-08']);
        $group = Group::factory()->create(['event_id' => $event->id]);
        Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user->id,
            'is_complete' => true,
        ]);

        $response = $this->actingAs($user)->get(route('propoff.history'));

        $response->assertOk();
        $this->assertContains(2026, $response->viewData('page')['props']['years']);
    }

    public function test_locked_game_page_shows_answer_distribution(): void
    {
        $event = Event::factory()->create(['lock_date' => now()->addDay()]);
        $group = Group::factory()->create([
            'event_id' => $event->id,
            'code' => 'DIST01',
            'entry_cutoff' => now()->subHour(), // locked -> distribution runs
        ]);
        $question = GroupQuestion::factory()->create([
            'group_id' => $group->id,
            'question_type' => 'text',
            'is_active' => true,
        ]);

        $player = User::factory()->create(['first_name' => 'Pat', 'last_name' => 'Picks']);
        $group->users()->attach($player->id, ['joined_at' => now()]);
        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $player->id,
            'is_complete' => true,
        ]);
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $question->id,
            'answer_text' => 'Chiefs',
            'points_earned' => 0,
            'is_correct' => false,
        ]);

        $this->actingAs($player)
            ->get(route('propoff.play.game', ['code' => 'DIST01']))
            ->assertOk();
    }
}
