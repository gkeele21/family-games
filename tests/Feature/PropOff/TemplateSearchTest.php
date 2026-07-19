<?php

namespace Tests\Feature\PropOff;

use App\Models\PropOff\Event;
use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\QuestionTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateSearchTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_search_templates_by_category()
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        QuestionTemplate::factory()->create([
            'category' => 'football,nfl',
            'title' => 'Football Question',
            'created_by' => $admin->id,
        ]);
        QuestionTemplate::factory()->create([
            'category' => 'basketball,nba',
            'title' => 'Basketball Question',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson(
            route('propoff.admin.events.event-questions.searchTemplates', ['event' => $event, 'category' => 'football'])
        );

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'templates');
        $response->assertJsonPath('templates.0.title', 'Football Question');
        $response->assertJsonPath('search_term', 'football');
    }

    /** @test */
    public function search_excludes_already_imported_templates()
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $template1 = QuestionTemplate::factory()->create([
            'category' => 'football',
            'title' => 'Template 1',
            'created_by' => $admin->id,
        ]);
        $template2 = QuestionTemplate::factory()->create([
            'category' => 'football',
            'title' => 'Template 2',
            'created_by' => $admin->id,
        ]);

        // Import template1 to the event
        EventQuestion::factory()->create([
            'event_id' => $event->id,
            'template_id' => $template1->id,
        ]);

        $response = $this->actingAs($admin)->getJson(
            route('propoff.admin.events.event-questions.searchTemplates', ['event' => $event, 'category' => 'football'])
        );

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'templates');
        $response->assertJsonPath('templates.0.id', $template2->id);
        $response->assertJsonPath('templates.0.title', 'Template 2');
    }

    /** @test */
    public function search_returns_empty_for_non_matching_category()
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        QuestionTemplate::factory()->create([
            'category' => 'football',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson(
            route('propoff.admin.events.event-questions.searchTemplates', ['event' => $event, 'category' => 'baseball'])
        );

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'templates');
        $response->assertJsonPath('total', 0);
    }

    /** @test */
    public function non_admin_cannot_search_templates()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $response = $this->actingAs($user)->getJson(
            route('propoff.admin.events.event-questions.searchTemplates', ['event' => $event, 'category' => 'football'])
        );

        $response->assertStatus(403);
    }

    /** @test */
    public function search_requires_category_parameter()
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)->getJson(
            route('propoff.admin.events.event-questions.searchTemplates', $event)
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('category');
    }

    /** @test */
    public function search_matches_multiple_categories_in_comma_separated_list()
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        QuestionTemplate::factory()->create([
            'category' => 'football,nfl,sports',
            'title' => 'NFL Template',
            'created_by' => $admin->id,
        ]);
        QuestionTemplate::factory()->create([
            'category' => 'basketball,nba',
            'title' => 'NBA Template',
            'created_by' => $admin->id,
        ]);

        // Search for 'nfl' should find the template with 'football,nfl,sports'
        $response = $this->actingAs($admin)->getJson(
            route('propoff.admin.events.event-questions.searchTemplates', ['event' => $event, 'category' => 'nfl'])
        );

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'templates');
        $response->assertJsonPath('templates.0.title', 'NFL Template');
    }

    /** @test */
    public function search_returns_templates_ordered_by_display_order()
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        QuestionTemplate::factory()->create([
            'category' => 'football',
            'title' => 'Template B',
            'display_order' => 2,
            'created_by' => $admin->id,
        ]);
        QuestionTemplate::factory()->create([
            'category' => 'football',
            'title' => 'Template A',
            'display_order' => 1,
            'created_by' => $admin->id,
        ]);
        QuestionTemplate::factory()->create([
            'category' => 'football',
            'title' => 'Template C',
            'display_order' => 3,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson(
            route('propoff.admin.events.event-questions.searchTemplates', ['event' => $event, 'category' => 'football'])
        );

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'templates');
        $response->assertJsonPath('templates.0.title', 'Template A');
        $response->assertJsonPath('templates.1.title', 'Template B');
        $response->assertJsonPath('templates.2.title', 'Template C');
    }

    /** @test */
    public function search_trims_whitespace_from_category()
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        QuestionTemplate::factory()->create([
            'category' => 'football',
            'title' => 'Football Template',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson(
            route('propoff.admin.events.event-questions.searchTemplates', ['event' => $event, 'category' => '  football  '])
        );

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'templates');
        $response->assertJsonPath('search_term', 'football');
    }
}
