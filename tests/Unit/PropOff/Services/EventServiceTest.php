<?php

namespace Tests\Unit\PropOff\Services;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\Group;
use App\Models\User;
use App\Services\PropOff\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EventService $eventService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventService = new EventService();
    }

    /** @test */
    public function it_checks_if_user_has_joined_event_for_group()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();

        // User hasn't joined yet
        $this->assertFalse($this->eventService->hasUserJoinedEvent($event, $user, $group));

        // Create entry (user joins)
        Entry::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'group_id' => $group->id,
        ]);

        // Now user has joined
        $this->assertTrue($this->eventService->hasUserJoinedEvent($event, $user, $group));
    }

    /** @test */
    public function it_returns_false_when_user_joined_different_group()
    {
        $event = Event::factory()->create();
        $group1 = Group::factory()->create(['event_id' => $event->id]);
        $group2 = Group::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();

        // User joins group1
        Entry::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'group_id' => $group1->id,
        ]);

        // Check if joined group2 (should be false)
        $this->assertFalse($this->eventService->hasUserJoinedEvent($event, $user, $group2));
    }

    /** @test */
    public function it_gets_user_entry_for_event_and_group()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();

        // No entry yet
        $this->assertNull($this->eventService->getUserEntry($event, $user, $group));

        // Create entry
        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'group_id' => $group->id,
        ]);

        // Should return the entry
        $result = $this->eventService->getUserEntry($event, $user, $group);
        $this->assertNotNull($result);
        $this->assertEquals($entry->id, $result->id);
    }

    /** @test */
    public function it_checks_if_event_is_playable_when_open_and_before_lock_date()
    {
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(1), // Tomorrow
        ]);

        $this->assertTrue($this->eventService->isEventPlayable($event));
    }

    /** @test */
    public function it_returns_false_when_event_status_is_not_open()
    {
        $event = Event::factory()->create([
            'status' => 'draft',
            'lock_date' => now()->addDays(1),
        ]);

        $this->assertFalse($this->eventService->isEventPlayable($event));
    }

    /** @test */
    public function it_returns_false_when_event_is_past_lock_date()
    {
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->subDays(1), // Yesterday
        ]);

        $this->assertFalse($this->eventService->isEventPlayable($event));
    }

    /** @test */
    public function it_returns_true_when_event_is_open_and_no_lock_date()
    {
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => null,
        ]);

        $this->assertTrue($this->eventService->isEventPlayable($event));
    }

    /** @test */
    public function it_gets_active_events()
    {
        // Create open events with future lock dates
        $activeEvent1 = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(5),
        ]);

        $activeEvent2 = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(3),
        ]);

        // Create non-active events
        Event::factory()->create([
            'status' => 'completed',
            'lock_date' => now()->subDays(1),
        ]);

        Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->subDays(1), // Past lock date
        ]);

        $activeEvents = $this->eventService->getActiveEvents();

        // Should only return the 2 active events
        $this->assertCount(2, $activeEvents);

        // Should be ordered by lock_date ascending (soonest first)
        $this->assertEquals($activeEvent2->id, $activeEvents[0]->id);
        $this->assertEquals($activeEvent1->id, $activeEvents[1]->id);
    }

    /** @test */
    public function it_limits_active_events_results()
    {
        // Create 5 active events
        for ($i = 1; $i <= 5; $i++) {
            Event::factory()->create([
                'status' => 'open',
                'lock_date' => now()->addDays($i),
            ]);
        }

        $activeEvents = $this->eventService->getActiveEvents(3);

        $this->assertCount(3, $activeEvents);
    }

    /** @test */
    public function it_gets_completed_events()
    {
        // Create completed events
        $completed1 = Event::factory()->create([
            'status' => 'completed',
            'event_date' => now()->subDays(5),
        ]);

        $completed2 = Event::factory()->create([
            'status' => 'completed',
            'event_date' => now()->subDays(10),
        ]);

        // Create non-completed event
        Event::factory()->create([
            'status' => 'open',
            'event_date' => now()->addDays(1),
        ]);

        $completedEvents = $this->eventService->getCompletedEvents();

        // Should only return completed events
        $this->assertCount(2, $completedEvents);

        // Should be ordered by event_date descending (most recent first)
        $this->assertEquals($completed1->id, $completedEvents[0]->id);
        $this->assertEquals($completed2->id, $completedEvents[1]->id);
    }

    /** @test */
    public function it_limits_completed_events_results()
    {
        // Create 5 completed events
        for ($i = 1; $i <= 5; $i++) {
            Event::factory()->create([
                'status' => 'completed',
                'event_date' => now()->subDays($i),
            ]);
        }

        $completedEvents = $this->eventService->getCompletedEvents(3);

        $this->assertCount(3, $completedEvents);
    }

    /** @test */
    public function it_calculates_possible_points_for_event()
    {
        $event = Event::factory()->create();

        // Create questions with different point values
        EventQuestion::factory()->create([
            'event_id' => $event->id,
            'points' => 10,
        ]);

        EventQuestion::factory()->create([
            'event_id' => $event->id,
            'points' => 15,
        ]);

        EventQuestion::factory()->create([
            'event_id' => $event->id,
            'points' => 20,
        ]);

        $totalPoints = $this->eventService->calculatePossiblePoints($event);

        $this->assertEquals(45, $totalPoints); // 10 + 15 + 20
    }

    /** @test */
    public function it_returns_zero_when_event_has_no_questions()
    {
        $event = Event::factory()->create();

        $totalPoints = $this->eventService->calculatePossiblePoints($event);

        $this->assertEquals(0, $totalPoints);
    }

    /** @test */
    public function it_gets_available_events_for_user()
    {
        $user = User::factory()->create();

        // Available: open and before lock date
        $available1 = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(5),
        ]);

        // Available: open with no lock date
        $available2 = Event::factory()->create([
            'status' => 'open',
            'lock_date' => null,
        ]);

        // Not available: completed
        Event::factory()->create([
            'status' => 'completed',
            'lock_date' => now()->addDays(1),
        ]);

        // Not available: past lock date
        Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->subDays(1),
        ]);

        $availableEvents = $this->eventService->getAvailableEventsForUser($user, 50);

        $this->assertEquals(2, $availableEvents->count());
    }

    /** @test */
    public function it_searches_events_by_name()
    {
        Event::factory()->create(['name' => 'Super Bowl 2024']);
        Event::factory()->create(['name' => 'World Cup Finals']);
        Event::factory()->create(['name' => 'NBA Finals']);

        $results = $this->eventService->searchEvents('Bowl', 50);

        $this->assertEquals(1, $results->count());
        $this->assertStringContainsString('Bowl', $results->first()->name);
    }

    /** @test */
    public function it_searches_events_by_name_partial_match()
    {
        Event::factory()->create([
            'name' => 'Football Championship 2024',
        ]);

        Event::factory()->create([
            'name' => 'Basketball Finals',
        ]);

        Event::factory()->create([
            'name' => 'College Football Bowl',
        ]);

        $results = $this->eventService->searchEvents('Football', 50);

        $this->assertEquals(2, $results->count());
    }

    /** @test */
    public function it_searches_events_by_description()
    {
        Event::factory()->create([
            'name' => 'Event 1',
            'description' => 'This is about championship games',
        ]);

        Event::factory()->create([
            'name' => 'Event 2',
            'description' => 'Regular season game',
        ]);

        $results = $this->eventService->searchEvents('championship', 50);

        $this->assertEquals(1, $results->count());
        $this->assertStringContainsString('championship', $results->first()->description);
    }

    /** @test */
    public function search_is_case_insensitive()
    {
        Event::factory()->create(['name' => 'Super Bowl 2024']);

        $results = $this->eventService->searchEvents('super bowl', 50);

        $this->assertEquals(1, $results->count());
    }

    /** @test */
    public function it_filters_events_by_status()
    {
        Event::factory()->create(['status' => 'open']);
        Event::factory()->create(['status' => 'open']);
        Event::factory()->create(['status' => 'completed']);
        Event::factory()->create(['status' => 'draft']);

        $openEvents = $this->eventService->filterEventsByStatus('open', 50);
        $this->assertEquals(2, $openEvents->count());

        $completedEvents = $this->eventService->filterEventsByStatus('completed', 50);
        $this->assertEquals(1, $completedEvents->count());

        $draftEvents = $this->eventService->filterEventsByStatus('draft', 50);
        $this->assertEquals(1, $draftEvents->count());
    }

    /** @test */
    public function filtered_events_are_ordered_by_event_date_descending()
    {
        $event1 = Event::factory()->create([
            'status' => 'completed',
            'event_date' => now()->subDays(5),
        ]);

        $event2 = Event::factory()->create([
            'status' => 'completed',
            'event_date' => now()->subDays(10),
        ]);

        $event3 = Event::factory()->create([
            'status' => 'completed',
            'event_date' => now()->subDays(2),
        ]);

        $results = $this->eventService->filterEventsByStatus('completed', 50);

        // Most recent first
        $this->assertEquals($event3->id, $results->first()->id);
        $this->assertEquals($event2->id, $results->last()->id);
    }

    /** @test */
    public function it_includes_question_count_in_active_events()
    {
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(1),
        ]);

        EventQuestion::factory()->count(5)->create(['event_id' => $event->id]);

        $activeEvents = $this->eventService->getActiveEvents();

        $this->assertEquals(5, $activeEvents->first()->questions_count);
    }

    /** @test */
    public function it_includes_creator_in_results()
    {
        $creator = User::factory()->create(['name' => 'Event Creator']);
        $event = Event::factory()->create([
            'status' => 'open',
            'lock_date' => now()->addDays(1),
            'created_by' => $creator->id,
        ]);

        $activeEvents = $this->eventService->getActiveEvents();

        $this->assertNotNull($activeEvents->first()->creator);
        $this->assertEquals('Event Creator', $activeEvents->first()->creator->name);
    }
}
