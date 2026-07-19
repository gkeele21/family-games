<?php

namespace Tests\Unit\PropOff\Policies;

use App\Models\PropOff\EventQuestion;
use App\Models\User;
use App\Policies\PropOff\EventQuestionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventQuestionPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected EventQuestionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new EventQuestionPolicy();
    }

    /** @test */
    public function any_authenticated_user_can_view_any_event_questions()
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function any_authenticated_user_can_view_an_event_question()
    {
        $user = User::factory()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $this->assertTrue($this->policy->view($user, $eventQuestion));
    }

    /** @test */
    public function admin_can_view_event_questions()
    {
        $admin = User::factory()->admin()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $this->assertTrue($this->policy->view($admin, $eventQuestion));
        $this->assertTrue($this->policy->viewAny($admin));
    }

    /** @test */
    public function only_admin_can_create_event_questions()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->assertTrue($this->policy->create($admin));
        $this->assertFalse($this->policy->create($user));
    }

    /** @test */
    public function only_admin_can_update_event_questions()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $this->assertTrue($this->policy->update($admin, $eventQuestion));
        $this->assertFalse($this->policy->update($user, $eventQuestion));
    }

    /** @test */
    public function only_admin_can_delete_event_questions()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $eventQuestion));
        $this->assertFalse($this->policy->delete($user, $eventQuestion));
    }

    /** @test */
    public function only_admin_can_restore_event_questions()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $this->assertTrue($this->policy->restore($admin, $eventQuestion));
        $this->assertFalse($this->policy->restore($user, $eventQuestion));
    }

    /** @test */
    public function only_admin_can_force_delete_event_questions()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $this->assertTrue($this->policy->forceDelete($admin, $eventQuestion));
        $this->assertFalse($this->policy->forceDelete($user, $eventQuestion));
    }

    /** @test */
    public function admin_has_full_access_to_all_actions()
    {
        $admin = User::factory()->admin()->create();
        $eventQuestion = EventQuestion::factory()->create();

        // Admin can do everything
        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $eventQuestion));
        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $eventQuestion));
        $this->assertTrue($this->policy->delete($admin, $eventQuestion));
        $this->assertTrue($this->policy->restore($admin, $eventQuestion));
        $this->assertTrue($this->policy->forceDelete($admin, $eventQuestion));
    }

    /** @test */
    public function regular_user_can_only_view()
    {
        $user = User::factory()->create();
        $eventQuestion = EventQuestion::factory()->create();

        // User can view
        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $eventQuestion));

        // User cannot do anything else
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $eventQuestion));
        $this->assertFalse($this->policy->delete($user, $eventQuestion));
        $this->assertFalse($this->policy->restore($user, $eventQuestion));
        $this->assertFalse($this->policy->forceDelete($user, $eventQuestion));
    }

    /** @test */
    public function guest_user_can_view_event_questions()
    {
        $guest = User::factory()->guest()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $this->assertTrue($this->policy->viewAny($guest));
        $this->assertTrue($this->policy->view($guest, $eventQuestion));
    }

    /** @test */
    public function guest_user_cannot_modify_event_questions()
    {
        $guest = User::factory()->guest()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $this->assertFalse($this->policy->create($guest));
        $this->assertFalse($this->policy->update($guest, $eventQuestion));
        $this->assertFalse($this->policy->delete($guest, $eventQuestion));
        $this->assertFalse($this->policy->restore($guest, $eventQuestion));
        $this->assertFalse($this->policy->forceDelete($guest, $eventQuestion));
    }

    /** @test */
    public function all_destructive_actions_require_admin()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $guest = User::factory()->guest()->create();
        $eventQuestion = EventQuestion::factory()->create();

        $destructiveActions = [
            'create',
            'update',
            'delete',
            'restore',
            'forceDelete',
        ];

        foreach ($destructiveActions as $action) {
            // Admin can perform action
            if ($action === 'create') {
                $this->assertTrue($this->policy->$action($admin));
            } else {
                $this->assertTrue($this->policy->$action($admin, $eventQuestion));
            }

            // Regular user cannot
            if ($action === 'create') {
                $this->assertFalse($this->policy->$action($user));
            } else {
                $this->assertFalse($this->policy->$action($user, $eventQuestion));
            }

            // Guest cannot
            if ($action === 'create') {
                $this->assertFalse($this->policy->$action($guest));
            } else {
                $this->assertFalse($this->policy->$action($guest, $eventQuestion));
            }
        }
    }
}
