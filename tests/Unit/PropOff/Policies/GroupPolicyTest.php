<?php

namespace Tests\Unit\PropOff\Policies;

use App\Models\PropOff\Group;
use App\Models\User;
use App\Policies\PropOff\GroupPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected GroupPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new GroupPolicy();
    }

    /** @test */
    public function any_user_can_view_any_groups()
    {
        $user = User::factory()->create();
        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function admin_can_view_any_group()
    {
        $admin = User::factory()->admin()->create();
        $group = Group::factory()->create();

        $this->assertTrue($this->policy->view($admin, $group));
    }

    /** @test */
    public function captain_can_view_their_group()
    {
        $captain = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($this->policy->view($captain, $group));
    }

    /** @test */
    public function member_can_view_their_group()
    {
        $member = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($member->id, [
            'joined_at' => now(),
            'is_captain' => false,
        ]);

        $this->assertTrue($this->policy->view($member, $group));
    }

    /** @test */
    public function non_member_cannot_view_group()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();

        $this->assertFalse($this->policy->view($user, $group));
    }

    /** @test */
    public function any_authenticated_user_can_create_groups()
    {
        $user = User::factory()->create();
        $this->assertTrue($this->policy->create($user));
    }

    /** @test */
    public function admin_can_update_any_group()
    {
        $admin = User::factory()->admin()->create();
        $group = Group::factory()->create();

        $this->assertTrue($this->policy->update($admin, $group));
    }

    /** @test */
    public function captain_can_update_their_group()
    {
        $captain = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($this->policy->update($captain, $group));
    }

    /** @test */
    public function regular_member_cannot_update_group()
    {
        $member = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($member->id, [
            'joined_at' => now(),
            'is_captain' => false,
        ]);

        $this->assertFalse($this->policy->update($member, $group));
    }

    /** @test */
    public function admin_can_delete_any_group()
    {
        $admin = User::factory()->admin()->create();
        $group = Group::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $group));
    }

    /** @test */
    public function captain_can_delete_their_group()
    {
        $captain = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($this->policy->delete($captain, $group));
    }

    /** @test */
    public function only_admin_can_force_delete_group()
    {
        $admin = User::factory()->admin()->create();
        $captain = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($this->policy->forceDelete($admin, $group));
        $this->assertFalse($this->policy->forceDelete($captain, $group));
    }

    /** @test */
    public function admin_can_add_users_to_any_group()
    {
        $admin = User::factory()->admin()->create();
        $group = Group::factory()->create();

        $this->assertTrue($this->policy->addUser($admin, $group));
    }

    /** @test */
    public function captain_can_add_users_to_their_group()
    {
        $captain = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($this->policy->addUser($captain, $group));
    }

    /** @test */
    public function regular_member_cannot_add_users()
    {
        $member = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($member->id, [
            'joined_at' => now(),
            'is_captain' => false,
        ]);

        $this->assertFalse($this->policy->addUser($member, $group));
    }

    /** @test */
    public function captain_can_remove_users_from_their_group()
    {
        $captain = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($this->policy->removeUser($captain, $group));
    }

    /** @test */
    public function captain_can_manage_questions_in_their_group()
    {
        $captain = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($this->policy->manageQuestions($captain, $group));
    }

    /** @test */
    public function captain_can_grade_their_group()
    {
        $captain = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($this->policy->grade($captain, $group));
    }

    /** @test */
    public function captain_can_manage_members_in_their_group()
    {
        $captain = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($this->policy->manageMembers($captain, $group));
    }
}
