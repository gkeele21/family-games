<?php

namespace Tests\Unit\PropOff\Models;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_an_event()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        $this->assertInstanceOf(Event::class, $group->event);
        $this->assertEquals($event->id, $group->event->id);
    }

    /** @test */
    public function it_belongs_to_a_creator()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $group->creator);
        $this->assertEquals($user->id, $group->creator->id);
    }

    /** @test */
    public function it_has_many_members()
    {
        $group = Group::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $group->users()->attach($user1->id, ['joined_at' => now()]);
        $group->users()->attach($user2->id, ['joined_at' => now()]);

        $this->assertEquals(2, $group->users()->count());
        $this->assertTrue($group->users->contains($user1));
        $this->assertTrue($group->users->contains($user2));
    }

    /** @test */
    public function it_can_have_captains()
    {
        $group = Group::factory()->create();
        $captain = User::factory()->create();
        $member = User::factory()->create();

        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);
        $group->users()->attach($member->id, [
            'joined_at' => now(),
            'is_captain' => false,
        ]);

        $this->assertEquals(1, $group->captains()->count());
        $this->assertTrue($group->captains->contains($captain));
        $this->assertFalse($group->captains->contains($member));
    }

    /** @test */
    public function is_captain_method_works_correctly()
    {
        $group = Group::factory()->create();
        $captain = User::factory()->create();
        $member = User::factory()->create();

        $group->users()->attach($captain->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);
        $group->users()->attach($member->id, [
            'joined_at' => now(),
            'is_captain' => false,
        ]);

        $this->assertTrue($group->isCaptain($captain));
        $this->assertTrue($group->isCaptain($captain->id));
        $this->assertFalse($group->isCaptain($member));
        $this->assertFalse($group->isCaptain($member->id));
    }

    /** @test */
    public function add_captain_makes_user_a_captain()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();

        $group->addCaptain($user);

        $this->assertTrue($group->isCaptain($user));
        $this->assertTrue($group->users->contains($user));
    }

    /** @test */
    public function add_captain_promotes_existing_member()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();

        // First add as regular member
        $group->users()->attach($user->id, [
            'joined_at' => now(),
            'is_captain' => false,
        ]);

        $this->assertFalse($group->isCaptain($user));

        // Now promote to captain
        $group->addCaptain($user);
        $group->refresh();

        $this->assertTrue($group->isCaptain($user));
    }

    /** @test */
    public function remove_captain_demotes_captain_to_member()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();

        $group->users()->attach($user->id, [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        $this->assertTrue($group->isCaptain($user));

        $group->removeCaptain($user);
        $group->refresh();

        $this->assertFalse($group->isCaptain($user));
        $this->assertTrue($group->users->contains($user)); // Still a member
    }

    /** @test */
    public function uses_captain_grading_returns_true_when_grading_source_is_captain()
    {
        $group = Group::factory()->create(['grading_source' => 'captain']);
        $this->assertTrue($group->usesCaptainGrading());
        $this->assertFalse($group->usesAdminGrading());
    }

    /** @test */
    public function uses_admin_grading_returns_true_when_grading_source_is_admin()
    {
        $group = Group::factory()->create(['grading_source' => 'admin']);
        $this->assertTrue($group->usesAdminGrading());
        $this->assertFalse($group->usesCaptainGrading());
    }

    /** @test */
    public function accepting_entries_returns_false_when_group_cutoff_passed()
    {
        $group = Group::factory()->create([
            'entry_cutoff' => now()->subDay(),
        ]);

        $this->assertFalse($group->acceptingEntries());
    }

    /** @test */
    public function accepting_entries_returns_true_when_group_cutoff_not_reached()
    {
        $group = Group::factory()->create([
            'entry_cutoff' => now()->addDay(),
        ]);

        $this->assertTrue($group->acceptingEntries());
    }

    /** @test */
    public function accepting_entries_falls_back_to_event_lock_date()
    {
        $event = Event::factory()->create([
            'lock_date' => now()->addDay(),
        ]);
        $group = Group::factory()->create([
            'event_id' => $event->id,
            'entry_cutoff' => null,
        ]);

        $this->assertTrue($group->acceptingEntries());

        // Update event lock date to past
        $event->update(['lock_date' => now()->subDay()]);
        $group->refresh();

        $this->assertFalse($group->acceptingEntries());
    }

    /** @test */
    public function accepting_entries_returns_true_when_no_cutoffs_set()
    {
        $event = Event::factory()->create(['lock_date' => null]);
        $group = Group::factory()->create([
            'event_id' => $event->id,
            'entry_cutoff' => null,
        ]);

        $this->assertTrue($group->acceptingEntries());
    }

    /** @test */
    public function it_has_many_entries()
    {
        $group = Group::factory()->create();
        $entry = Entry::factory()->create(['group_id' => $group->id]);

        $this->assertTrue($group->entries->contains($entry));
    }

    /** @test */
    public function it_has_many_group_questions()
    {
        $group = Group::factory()->create();
        $question = GroupQuestion::factory()->create(['group_id' => $group->id]);

        $this->assertTrue($group->groupQuestions->contains($question));
    }
}
