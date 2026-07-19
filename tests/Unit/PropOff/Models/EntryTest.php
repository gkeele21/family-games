<?php

namespace Tests\Unit\PropOff\Models;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\Group;
use App\Models\User;
use App\Models\PropOff\UserAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_an_event()
    {
        $event = Event::factory()->create();
        $entry = Entry::factory()->create(['event_id' => $event->id]);

        $this->assertInstanceOf(Event::class, $entry->event);
        $this->assertEquals($event->id, $entry->event->id);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $entry->user);
        $this->assertEquals($user->id, $entry->user->id);
    }

    /** @test */
    public function it_belongs_to_a_group()
    {
        $group = Group::factory()->create();
        $entry = Entry::factory()->create(['group_id' => $group->id]);

        $this->assertInstanceOf(Group::class, $entry->group);
        $this->assertEquals($group->id, $entry->group->id);
    }

    /** @test */
    public function it_has_many_user_answers()
    {
        $entry = Entry::factory()->create();
        $answer = UserAnswer::factory()->create(['entry_id' => $entry->id]);

        $this->assertTrue($entry->userAnswers->contains($answer));
    }

    /** @test */
    public function it_can_be_submitted_by_a_captain()
    {
        $captain = User::factory()->create();
        $entry = Entry::factory()->create([
            'submitted_by_captain_id' => $captain->id,
            'submitted_by_captain_at' => now(),
        ]);

        $this->assertInstanceOf(User::class, $entry->submittedByCaptain);
        $this->assertEquals($captain->id, $entry->submittedByCaptain->id);
        $this->assertTrue($entry->wasSubmittedByCaptain());
    }

    /** @test */
    public function was_submitted_by_captain_returns_false_when_not_submitted_by_captain()
    {
        $entry = Entry::factory()->create([
            'submitted_by_captain_id' => null,
        ]);

        $this->assertFalse($entry->wasSubmittedByCaptain());
    }

    /** @test */
    public function it_casts_is_complete_to_boolean()
    {
        $entry = Entry::factory()->create(['is_complete' => true]);
        $this->assertTrue($entry->is_complete);
        $this->assertIsBool($entry->is_complete);
    }

    /** @test */
    public function it_casts_dates_correctly()
    {
        $submittedAt = now();
        $entry = Entry::factory()->create([
            'submitted_at' => $submittedAt,
            'submitted_by_captain_at' => $submittedAt,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $entry->submitted_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $entry->submitted_by_captain_at);
    }

    /** @test */
    public function it_casts_percentage_to_float()
    {
        $entry = Entry::factory()->create(['percentage' => 85.5]);
        $this->assertIsFloat($entry->percentage);
        $this->assertEquals(85.5, $entry->percentage);
    }
}
