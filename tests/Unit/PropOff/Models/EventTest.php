<?php

namespace Tests\Unit\PropOff\Models;

use App\Models\PropOff\Event;
use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\Group;
use App\Models\PropOff\QuestionTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_a_creator()
    {
        $user = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $event->creator);
        $this->assertEquals($user->id, $event->creator->id);
    }

    /** @test */
    public function it_has_many_event_questions()
    {
        $event = Event::factory()->create();
        $question1 = EventQuestion::factory()->create([
            'event_id' => $event->id,
            'display_order' => 1,
        ]);
        $question2 = EventQuestion::factory()->create([
            'event_id' => $event->id,
            'display_order' => 2,
        ]);

        $this->assertEquals(2, $event->eventQuestions()->count());
        $this->assertTrue($event->eventQuestions->contains($question1));
        $this->assertTrue($event->eventQuestions->contains($question2));
    }

    /** @test */
    public function event_questions_are_ordered_by_display_order()
    {
        $event = Event::factory()->create();
        $question2 = EventQuestion::factory()->create([
            'event_id' => $event->id,
            'display_order' => 2,
        ]);
        $question1 = EventQuestion::factory()->create([
            'event_id' => $event->id,
            'display_order' => 1,
        ]);

        $questions = $event->eventQuestions;
        $this->assertEquals(1, $questions->first()->display_order);
        $this->assertEquals(2, $questions->last()->display_order);
    }

    /** @test */
    public function questions_is_alias_for_event_questions()
    {
        $event = Event::factory()->create();
        $question = EventQuestion::factory()->create(['event_id' => $event->id]);

        $this->assertEquals($event->eventQuestions()->count(), $event->questions()->count());
    }

    /** @test */
    public function it_has_many_groups()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        $this->assertTrue($event->groups->contains($group));
    }

    /** @test */
    public function it_casts_dates_correctly()
    {
        $eventDate = now()->addWeek();
        $lockDate = now()->addDays(6);

        $event = Event::factory()->create([
            'event_date' => $eventDate,
            'lock_date' => $lockDate,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $event->event_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $event->lock_date);
    }


    /** @test */
    public function it_does_not_have_available_templates_method()
    {
        $event = Event::factory()->create();

        $this->assertFalse(method_exists($event, 'availableTemplates'));
    }
}
