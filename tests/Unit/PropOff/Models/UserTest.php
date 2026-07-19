<?php

namespace Tests\Unit\PropOff\Models;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\Group;
use App\Models\PropOff\QuestionTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_fillable_attributes()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('user', $user->role);
    }

    /** @test */
    public function it_hides_password_and_remember_token()
    {
        $user = User::factory()->create();
        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    /** @test */
    public function is_guest_returns_true_for_guest_users()
    {
        $guest = User::factory()->guest()->create();
        $this->assertTrue($guest->isGuest());

        $user = User::factory()->create(['role' => 'user']);
        $this->assertFalse($user->isGuest());
    }

    /** @test */
    public function has_admin_access_returns_true_for_admin_and_manager_users()
    {
        $admin = User::factory()->admin()->create();
        $this->assertTrue($admin->hasAdminAccess());

        $manager = User::factory()->create(['role' => 'manager']);
        $this->assertTrue($manager->hasAdminAccess());

        $user = User::factory()->create(['role' => 'user']);
        $this->assertFalse($user->hasAdminAccess());
    }

    /** @test */
    public function it_belongs_to_many_groups()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();

        $user->propoffGroups()->attach($group->id, [
            'joined_at' => now(),
            'is_captain' => false,
        ]);

        $this->assertTrue($user->propoffGroups->contains($group));
        $this->assertEquals(1, $user->propoffGroups()->count());
    }

    /** @test */
    public function it_can_be_captain_of_groups()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();

        $user->propoffGroups()->attach($group->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($user->captainOf->contains($group));
        $this->assertTrue($user->isCaptainOf($group));
        $this->assertTrue($user->isCaptain());
    }

    /** @test */
    public function is_captain_of_works_with_group_model_and_id()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();

        $user->propoffGroups()->attach($group->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        // Test with Group model
        $this->assertTrue($user->isCaptainOf($group));

        // Test with group ID
        $this->assertTrue($user->isCaptainOf($group->id));
    }

    /** @test */
    public function is_captain_returns_false_when_not_captain_of_any_group()
    {
        $user = User::factory()->create();
        $this->assertFalse($user->isCaptain());
    }

    /** @test */
    public function it_has_many_created_groups()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($user->createdGroups->contains($group));
    }

    /** @test */
    public function it_has_many_created_events()
    {
        $user = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($user->createdEvents->contains($event));
    }

    /** @test */
    public function it_has_many_created_question_templates()
    {
        $user = User::factory()->admin()->create();
        $template = QuestionTemplate::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($user->createdQuestionTemplates->contains($template));
    }

    /** @test */
    public function it_has_many_entries()
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->propoffEntries->contains($entry));
    }

    /** @test */
    public function can_edit_entry_returns_false_if_user_does_not_own_entry()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $entry = Entry::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($user->canEditEntry($entry));
    }

    /** @test */
    public function can_edit_entry_returns_false_if_group_is_not_accepting_entries()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['entry_cutoff' => now()->subDay()]);
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'group_id' => $group->id,
        ]);

        $this->assertFalse($user->canEditEntry($entry));
    }

    /** @test */
    public function can_edit_entry_returns_false_if_event_is_completed()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['status' => 'completed']);
        $group = Group::factory()->create(['event_id' => $event->id]);
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'group_id' => $group->id,
        ]);

        $this->assertFalse($user->canEditEntry($entry));
    }

    /** @test */
    public function can_edit_entry_returns_true_when_all_conditions_are_met()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['status' => 'open']);
        $group = Group::factory()->create([
            'event_id' => $event->id,
            'entry_cutoff' => now()->addDay(),
        ]);
        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'group_id' => $group->id,
        ]);

        $this->assertTrue($user->canEditEntry($entry));
    }
}
