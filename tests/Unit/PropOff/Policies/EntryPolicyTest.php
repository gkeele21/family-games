<?php

namespace Tests\Unit\PropOff\Policies;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\Group;
use App\Models\User;
use App\Policies\PropOff\EntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected EntryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new EntryPolicy();
    }

    /** @test */
    public function any_user_can_view_any_entries()
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function user_can_view_their_own_entry()
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->view($user, $entry));
    }

    /** @test */
    public function event_creator_can_view_any_entry_for_their_event()
    {
        $creator = User::factory()->create();
        $participant = User::factory()->create();

        $event = Event::factory()->create(['created_by' => $creator->id]);
        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
        ]);

        $this->assertTrue($this->policy->view($creator, $entry));
    }

    /** @test */
    public function admin_can_view_any_entry()
    {
        $admin = User::factory()->admin()->create();
        $participant = User::factory()->create();
        $entry = Entry::factory()->create(['user_id' => $participant->id]);

        $this->assertTrue($this->policy->view($admin, $entry));
    }

    /** @test */
    public function user_cannot_view_others_entry()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $entry = Entry::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->view($user, $entry));
    }

    /** @test */
    public function any_authenticated_user_can_create_entries()
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->create($user));
    }

    /** @test */
    public function user_can_update_their_own_entry_when_event_is_open()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(1),
        ]);
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $this->assertTrue($this->policy->update($user, $entry));
    }

    /** @test */
    public function user_cannot_update_others_entry()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(1),
        ]);
        $entry = Entry::factory()->create([
            'user_id' => $otherUser->id,
            'event_id' => $event->id,
        ]);

        $this->assertFalse($this->policy->update($user, $entry));
    }

    /** @test */
    public function user_cannot_update_entry_after_lock_date()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->subDays(1), // Past lock date
        ]);
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $this->assertFalse($this->policy->update($user, $entry));
    }

    /** @test */
    public function user_can_update_entry_when_no_lock_date()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => null,
        ]);
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $this->assertTrue($this->policy->update($user, $entry));
    }

    /** @test */
    public function user_cannot_update_entry_when_event_is_completed()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'completed',
            'lock_date' => now()->addDays(1),
        ]);
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $this->assertFalse($this->policy->update($user, $entry));
    }

    /** @test */
    public function user_cannot_update_entry_when_event_is_in_progress()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'in_progress',
            'lock_date' => now()->addDays(1),
        ]);
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $this->assertFalse($this->policy->update($user, $entry));
    }

    /** @test */
    public function user_can_update_entry_when_event_is_draft()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'draft',
            'lock_date' => now()->addDays(1),
        ]);
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $this->assertTrue($this->policy->update($user, $entry));
    }

    /** @test */
    public function user_can_delete_their_own_incomplete_entry()
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'is_complete' => false,
        ]);

        $this->assertTrue($this->policy->delete($user, $entry));
    }

    /** @test */
    public function user_cannot_delete_their_completed_entry()
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'is_complete' => true,
        ]);

        $this->assertFalse($this->policy->delete($user, $entry));
    }

    /** @test */
    public function user_cannot_delete_others_entry()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $entry = Entry::factory()->create([
            'user_id' => $otherUser->id,
            'is_complete' => false,
        ]);

        $this->assertFalse($this->policy->delete($user, $entry));
    }

    /** @test */
    public function only_admin_can_restore_entries()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $entry = Entry::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->restore($admin, $entry));
        $this->assertFalse($this->policy->restore($user, $entry));
    }

    /** @test */
    public function only_admin_can_force_delete_entries()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $entry = Entry::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->forceDelete($admin, $entry));
        $this->assertFalse($this->policy->forceDelete($user, $entry));
    }

    /** @test */
    public function update_policy_considers_all_conditions_together()
    {
        $user = User::factory()->create();

        // Valid: owns entry, event open, before lock date
        $validEvent = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(1),
        ]);
        $validEntry = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $validEvent->id,
        ]);
        $this->assertTrue($this->policy->update($user, $validEntry));

        // Invalid: doesn't own entry (even though event is valid)
        $otherUser = User::factory()->create();
        $invalidEntry1 = Entry::factory()->create([
            'user_id' => $otherUser->id,
            'event_id' => $validEvent->id,
        ]);
        $this->assertFalse($this->policy->update($user, $invalidEntry1));

        // Invalid: owns entry but past lock date
        $lockedEvent = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->subDays(1),
        ]);
        $invalidEntry2 = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $lockedEvent->id,
        ]);
        $this->assertFalse($this->policy->update($user, $invalidEntry2));

        // Invalid: owns entry but event is completed
        $completedEvent = Event::factory()->create([
            'status' => 'completed',
            'lock_date' => null,
        ]);
        $invalidEntry3 = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $completedEvent->id,
        ]);
        $this->assertFalse($this->policy->update($user, $invalidEntry3));
    }

    /** @test */
    public function delete_policy_checks_both_ownership_and_completion_status()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Can delete: owns and incomplete
        $entry1 = Entry::factory()->create([
            'user_id' => $user->id,
            'is_complete' => false,
        ]);
        $this->assertTrue($this->policy->delete($user, $entry1));

        // Cannot delete: owns but complete
        $entry2 = Entry::factory()->create([
            'user_id' => $user->id,
            'is_complete' => true,
        ]);
        $this->assertFalse($this->policy->delete($user, $entry2));

        // Cannot delete: incomplete but doesn't own
        $entry3 = Entry::factory()->create([
            'user_id' => $otherUser->id,
            'is_complete' => false,
        ]);
        $this->assertFalse($this->policy->delete($user, $entry3));

        // Cannot delete: complete and doesn't own
        $entry4 = Entry::factory()->create([
            'user_id' => $otherUser->id,
            'is_complete' => true,
        ]);
        $this->assertFalse($this->policy->delete($user, $entry4));
    }
}
