<?php

namespace Tests\Feature\PropOff\Admin;

use App\Models\PropOff\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_create_event_without_category()
    {
        $admin = User::factory()->admin()->create();

        $eventData = [
            'name' => 'Test Event',
            'description' => 'Test Description',
            'event_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'status' => 'draft',
            'lock_date' => now()->addDays(6)->format('Y-m-d\TH:i'),
        ];

        $response = $this->actingAs($admin)->post(
            route('propoff.admin.events.store'),
            $eventData
        );

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Event created successfully!');

        $this->assertDatabaseHas('propoff_events', [
            'name' => 'Test Event',
            'description' => 'Test Description',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
    }

    /** @test */
    public function admin_can_update_event_without_category()
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $updateData = [
            'name' => 'Updated Event Name',
            'description' => 'Updated Description',
            'event_date' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'status' => 'open',
            'lock_date' => now()->addDays(9)->format('Y-m-d\TH:i'),
        ];

        $response = $this->actingAs($admin)->put(
            route('propoff.admin.events.update', $event),
            $updateData
        );

        $response->assertRedirect(route('propoff.admin.events.show', $event));
        $response->assertSessionHas('success', 'Event updated successfully!');

        $this->assertDatabaseHas('propoff_events', [
            'id' => $event->id,
            'name' => 'Updated Event Name',
            'description' => 'Updated Description',
            'status' => 'open',
        ]);
    }

    /** @test */
    public function non_admin_cannot_create_event()
    {
        $user = User::factory()->create();

        $eventData = [
            'name' => 'Test Event',
            'description' => 'Test Description',
            'event_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'status' => 'draft',
        ];

        $response = $this->actingAs($user)->post(
            route('propoff.admin.events.store'),
            $eventData
        );

        $response->assertStatus(403);
    }

    /** @test */
    public function event_creation_requires_name()
    {
        $admin = User::factory()->admin()->create();

        $eventData = [
            'description' => 'Test Description',
            'event_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'status' => 'draft',
        ];

        $response = $this->actingAs($admin)->post(
            route('propoff.admin.events.store'),
            $eventData
        );

        $response->assertSessionHasErrors('name');
    }

    /** @test */
    public function event_creation_requires_valid_event_date()
    {
        $admin = User::factory()->admin()->create();

        $eventData = [
            'name' => 'Test Event',
            'description' => 'Test Description',
            'event_date' => now()->subDays(1)->format('Y-m-d\TH:i'), // Past date
            'status' => 'draft',
        ];

        $response = $this->actingAs($admin)->post(
            route('propoff.admin.events.store'),
            $eventData
        );

        $response->assertSessionHasErrors('event_date');
    }

    /** @test */
    public function event_creation_requires_valid_status()
    {
        $admin = User::factory()->admin()->create();

        $eventData = [
            'name' => 'Test Event',
            'description' => 'Test Description',
            'event_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'status' => 'invalid_status',
        ];

        $response = $this->actingAs($admin)->post(
            route('propoff.admin.events.store'),
            $eventData
        );

        $response->assertSessionHasErrors('status');
    }

    /** @test */
    public function admin_can_search_events_by_name()
    {
        $admin = User::factory()->admin()->create();

        Event::factory()->create([
            'name' => 'Football Championship',
            'created_by' => $admin->id,
        ]);

        Event::factory()->create([
            'name' => 'Basketball Finals',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(
            route('propoff.admin.events.index', ['search' => 'Football'])
        );

        $response->assertStatus(200);
        // The search should find the Football event
        $events = $response->viewData('page')['props']['events']['data'];
        $this->assertCount(1, $events);
        $this->assertEquals('Football Championship', $events[0]['name']);
    }

    /** @test */
    public function admin_can_search_events_by_description()
    {
        $admin = User::factory()->admin()->create();

        Event::factory()->create([
            'name' => 'Event One',
            'description' => 'This is about football',
            'created_by' => $admin->id,
        ]);

        Event::factory()->create([
            'name' => 'Event Two',
            'description' => 'This is about basketball',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(
            route('propoff.admin.events.index', ['search' => 'football'])
        );

        $response->assertStatus(200);
        // The search should find the event with "football" in description
        $events = $response->viewData('page')['props']['events']['data'];
        $this->assertCount(1, $events);
        $this->assertStringContainsString('football', $events[0]['description']);
    }

    /** @test */
    public function admin_can_filter_events_by_status()
    {
        $admin = User::factory()->admin()->create();

        Event::factory()->create(['status' => 'open', 'created_by' => $admin->id]);
        Event::factory()->create(['status' => 'open', 'created_by' => $admin->id]);
        Event::factory()->create(['status' => 'completed', 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(
            route('propoff.admin.events.index', ['status' => 'open'])
        );

        $response->assertStatus(200);
        $events = $response->viewData('page')['props']['events']['data'];
        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertEquals('open', $event['status']);
        }
    }

    /** @test */
    public function event_creation_allows_optional_description()
    {
        $admin = User::factory()->admin()->create();

        $eventData = [
            'name' => 'Test Event',
            'event_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'status' => 'draft',
        ];

        $response = $this->actingAs($admin)->post(
            route('propoff.admin.events.store'),
            $eventData
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('propoff_events', [
            'name' => 'Test Event',
            'description' => null,
        ]);
    }

    /** @test */
    public function event_creation_allows_optional_lock_date()
    {
        $admin = User::factory()->admin()->create();

        $eventData = [
            'name' => 'Test Event',
            'description' => 'Test Description',
            'event_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'status' => 'draft',
        ];

        $response = $this->actingAs($admin)->post(
            route('propoff.admin.events.store'),
            $eventData
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('propoff_events', [
            'name' => 'Test Event',
            'lock_date' => null,
        ]);
    }

    /** @test */
    public function admin_can_delete_event()
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)->delete(
            route('propoff.admin.events.destroy', $event)
        );

        $response->assertRedirect(route('propoff.admin.events.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('propoff_events', ['id' => $event->id]);
    }

    /** @test */
    public function non_admin_cannot_delete_others_event()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($user)->delete(
            route('propoff.admin.events.destroy', $event)
        );

        $response->assertStatus(403);
        $this->assertDatabaseHas('propoff_events', ['id' => $event->id]);
    }
}
