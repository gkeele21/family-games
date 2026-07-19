<?php

namespace Tests\Unit\PropOff\Policies;

use App\Models\PropOff\Event;
use App\Models\User;
use App\Policies\PropOff\EventPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected EventPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new EventPolicy();
    }

    /** @test */
    public function any_user_can_view_any_events()
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function any_user_can_view_an_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $this->assertTrue($this->policy->view($user, $event));
    }

    /** @test */
    public function only_admin_can_create_events()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->assertTrue($this->policy->create($admin));
        $this->assertFalse($this->policy->create($user));
    }

    /** @test */
    public function event_creator_can_update_their_event()
    {
        $creator = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $creator->id]);

        $this->assertTrue($this->policy->update($creator, $event));
    }

    /** @test */
    public function admin_can_update_any_event()
    {
        $admin = User::factory()->admin()->create();
        $creator = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $creator->id]);

        $this->assertTrue($this->policy->update($admin, $event));
    }

    /** @test */
    public function regular_user_cannot_update_others_event()
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $creator->id]);

        $this->assertFalse($this->policy->update($user, $event));
    }

    /** @test */
    public function event_creator_can_delete_their_event()
    {
        $creator = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $creator->id]);

        $this->assertTrue($this->policy->delete($creator, $event));
    }

    /** @test */
    public function admin_can_delete_any_event()
    {
        $admin = User::factory()->admin()->create();
        $creator = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $creator->id]);

        $this->assertTrue($this->policy->delete($admin, $event));
    }

    /** @test */
    public function regular_user_cannot_delete_others_event()
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $creator->id]);

        $this->assertFalse($this->policy->delete($user, $event));
    }

    /** @test */
    public function only_admin_can_restore_events()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $this->assertTrue($this->policy->restore($admin, $event));
        $this->assertFalse($this->policy->restore($user, $event));
    }

    /** @test */
    public function only_admin_can_force_delete_events()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $this->assertTrue($this->policy->forceDelete($admin, $event));
        $this->assertFalse($this->policy->forceDelete($user, $event));
    }

    /** @test */
    public function user_can_submit_to_open_event_before_lock_date()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(1),
        ]);

        $this->assertTrue($this->policy->submit($user, $event));
    }

    /** @test */
    public function user_cannot_submit_to_non_open_event()
    {
        $user = User::factory()->create();

        $draftEvent = Event::factory()->create([
            'status' => 'draft',
            'lock_date' => now()->addDays(1),
        ]);
        $this->assertFalse($this->policy->submit($user, $draftEvent));

        $completedEvent = Event::factory()->create([
            'status' => 'completed',
            'lock_date' => now()->addDays(1),
        ]);
        $this->assertFalse($this->policy->submit($user, $completedEvent));

        $inProgressEvent = Event::factory()->create([
            'status' => 'in_progress',
            'lock_date' => now()->addDays(1),
        ]);
        $this->assertFalse($this->policy->submit($user, $inProgressEvent));
    }

    /** @test */
    public function user_cannot_submit_after_lock_date()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->subDays(1), // Past lock date
        ]);

        $this->assertFalse($this->policy->submit($user, $event));
    }

    /** @test */
    public function user_can_submit_to_open_event_with_no_lock_date()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => null,
        ]);

        $this->assertTrue($this->policy->submit($user, $event));
    }

    /** @test */
    public function submit_policy_checks_both_status_and_lock_date()
    {
        $user = User::factory()->create();

        // Valid: open + before lock date
        $validEvent = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(1),
        ]);
        $this->assertTrue($this->policy->submit($user, $validEvent));

        // Invalid: open but past lock date
        $lockedEvent = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->subDays(1),
        ]);
        $this->assertFalse($this->policy->submit($user, $lockedEvent));

        // Invalid: before lock date but not open
        $draftEvent = Event::factory()->create([
            'status' => 'draft',
            'lock_date' => now()->addDays(1),
        ]);
        $this->assertFalse($this->policy->submit($user, $draftEvent));

        // Invalid: not open and past lock date
        $completedEvent = Event::factory()->create([
            'status' => 'completed',
            'lock_date' => now()->subDays(1),
        ]);
        $this->assertFalse($this->policy->submit($user, $completedEvent));
    }

    /** @test */
    public function user_can_view_results_of_completed_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['status' => 'completed']);

        $this->assertTrue($this->policy->viewResults($user, $event));
    }

    /** @test */
    public function user_cannot_view_results_of_non_completed_event()
    {
        $user = User::factory()->create();

        $openEvent = Event::factory()->create(['status' => 'open']);
        $this->assertFalse($this->policy->viewResults($user, $openEvent));

        $draftEvent = Event::factory()->create(['status' => 'draft']);
        $this->assertFalse($this->policy->viewResults($user, $draftEvent));

        $inProgressEvent = Event::factory()->create(['status' => 'in_progress']);
        $this->assertFalse($this->policy->viewResults($user, $inProgressEvent));
    }

    /** @test */
    public function admin_can_view_results_at_any_time()
    {
        $admin = User::factory()->admin()->create();

        // Admin can view results even for open events
        $openEvent = Event::factory()->create(['status' => 'open']);
        $this->assertTrue($this->policy->viewResults($admin, $openEvent));

        // Admin can view results for draft events
        $draftEvent = Event::factory()->create(['status' => 'draft']);
        $this->assertTrue($this->policy->viewResults($admin, $draftEvent));

        // Admin can view results for in-progress events
        $inProgressEvent = Event::factory()->create(['status' => 'in_progress']);
        $this->assertTrue($this->policy->viewResults($admin, $inProgressEvent));

        // Admin can view results for completed events
        $completedEvent = Event::factory()->create(['status' => 'completed']);
        $this->assertTrue($this->policy->viewResults($admin, $completedEvent));
    }

    /** @test */
    public function view_results_handles_all_status_combinations()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $statuses = ['draft', 'open', 'in_progress', 'locked', 'completed'];

        foreach ($statuses as $status) {
            $event = Event::factory()->create(['status' => $status]);

            // Admin can always view
            $this->assertTrue($this->policy->viewResults($admin, $event));

            // Regular user can only view if completed
            if ($status === 'completed') {
                $this->assertTrue($this->policy->viewResults($user, $event));
            } else {
                $this->assertFalse($this->policy->viewResults($user, $event));
            }
        }
    }

    /** @test */
    public function creator_and_admin_both_have_full_access()
    {
        $creator = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $creator->id]);

        // Both can update
        $this->assertTrue($this->policy->update($creator, $event));
        $this->assertTrue($this->policy->update($admin, $event));

        // Both can delete
        $this->assertTrue($this->policy->delete($creator, $event));
        $this->assertTrue($this->policy->delete($admin, $event));
    }
}
