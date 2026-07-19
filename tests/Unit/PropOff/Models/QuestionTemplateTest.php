<?php

namespace Tests\Unit\PropOff\Models;

use App\Models\PropOff\QuestionTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_parse_single_category()
    {
        $template = QuestionTemplate::factory()->create([
            'category' => 'football',
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        $categories = $template->categories;

        $this->assertCount(1, $categories);
        $this->assertEquals(['football'], $categories);
    }

    /** @test */
    public function it_can_parse_multiple_categories()
    {
        $template = QuestionTemplate::factory()->create([
            'category' => 'football,nfl,sports',
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        $categories = $template->categories;

        $this->assertCount(3, $categories);
        $this->assertEquals(['football', 'nfl', 'sports'], $categories);
    }

    /** @test */
    public function it_trims_whitespace_from_categories()
    {
        $template = QuestionTemplate::factory()->create([
            'category' => 'football, nfl , sports',
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        $categories = $template->categories;

        $this->assertEquals(['football', 'nfl', 'sports'], $categories);
    }

    /** @test */
    public function it_handles_empty_category()
    {
        $template = QuestionTemplate::factory()->create([
            'category' => '',
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        $categories = $template->categories;

        $this->assertCount(0, $categories);
        $this->assertEquals([], $categories);
    }

    /** @test */
    public function it_returns_empty_array_when_category_is_not_set()
    {
        $template = QuestionTemplate::factory()->create([
            'category' => '',
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        // Manually set to null to test the getter
        $template->category = null;

        $categories = $template->categories;

        $this->assertCount(0, $categories);
        $this->assertEquals([], $categories);
    }

    /** @test */
    public function has_category_returns_true_for_matching_category()
    {
        $template = QuestionTemplate::factory()->create([
            'category' => 'football,nfl,sports',
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        $this->assertTrue($template->hasCategory('football'));
        $this->assertTrue($template->hasCategory('nfl'));
        $this->assertTrue($template->hasCategory('sports'));
    }

    /** @test */
    public function has_category_returns_false_for_non_matching_category()
    {
        $template = QuestionTemplate::factory()->create([
            'category' => 'football,nfl',
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        $this->assertFalse($template->hasCategory('basketball'));
        $this->assertFalse($template->hasCategory('mlb'));
    }

    /** @test */
    public function has_category_is_case_insensitive()
    {
        $template = QuestionTemplate::factory()->create([
            'category' => 'football,NFL,Sports',
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        $this->assertTrue($template->hasCategory('FOOTBALL'));
        $this->assertTrue($template->hasCategory('nfl'));
        $this->assertTrue($template->hasCategory('sports'));
        $this->assertTrue($template->hasCategory('FoOtBaLl'));
    }

    /** @test */
    public function with_category_scope_finds_matching_templates()
    {
        $admin = User::factory()->admin()->create();

        QuestionTemplate::factory()->create([
            'category' => 'football,nfl',
            'created_by' => $admin->id,
        ]);
        QuestionTemplate::factory()->create([
            'category' => 'basketball,nba',
            'created_by' => $admin->id,
        ]);
        QuestionTemplate::factory()->create([
            'category' => 'football,college',
            'created_by' => $admin->id,
        ]);

        $results = QuestionTemplate::withCategory('football')->get();

        $this->assertCount(2, $results);
    }

    /** @test */
    public function with_category_scope_is_case_insensitive_via_like()
    {
        $admin = User::factory()->admin()->create();

        QuestionTemplate::factory()->create([
            'category' => 'Football,NFL',
            'created_by' => $admin->id,
        ]);
        QuestionTemplate::factory()->create([
            'category' => 'FOOTBALL,college',
            'created_by' => $admin->id,
        ]);

        $results = QuestionTemplate::withCategory('football')->get();

        $this->assertCount(2, $results);
    }

    /** @test */
    public function with_category_scope_matches_partial_strings()
    {
        $admin = User::factory()->admin()->create();

        QuestionTemplate::factory()->create([
            'category' => 'football,nfl',
            'created_by' => $admin->id,
        ]);
        QuestionTemplate::factory()->create([
            'category' => 'basketball,nba',
            'created_by' => $admin->id,
        ]);

        // Should match "football" when searching for "ball"
        $results = QuestionTemplate::withCategory('ball')->get();

        $this->assertCount(2, $results); // Matches both football and basketball
    }
}
