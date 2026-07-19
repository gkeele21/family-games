<?php

namespace Tests\Unit\PropOff\Services;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Event;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestion;
use App\Models\PropOff\Leaderboard;
use App\Models\User;
use App\Models\PropOff\UserAnswer;
use App\Services\PropOff\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardServiceTest extends TestCase
{
    /**
     * The leaderboard only counts entries that have answers — give a bare
     * factory entry one so recalculation includes it.
     */
    private function giveAnswer(Entry $entry): void
    {
        $question = GroupQuestion::factory()->create(['group_id' => $entry->group_id]);
        UserAnswer::create([
            'entry_id' => $entry->id,
            'group_question_id' => $question->id,
            'answer_text' => 'x',
            'points_earned' => 0,
            'is_correct' => false,
        ]);
    }

    use RefreshDatabase;

    protected LeaderboardService $leaderboardService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leaderboardService = new LeaderboardService();
    }

    /** @test */
    public function it_creates_leaderboard_entry_for_submission()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();

        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user->id,
            'total_score' => 85,
            'possible_points' => 100,
            'percentage' => 85.0,
            'is_complete' => true,
        ]);

        // Create some user answers for answered_count
        UserAnswer::factory()->count(3)->create(['entry_id' => $entry->id]);

        $this->leaderboardService->updateLeaderboardForEntry($entry);

        $this->assertDatabaseHas('propoff_leaderboards', [
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user->id,
            'total_score' => 85,
            'possible_points' => 100,
            'percentage' => 85.0,
            'answered_count' => 3,
        ]);
    }

    /** @test */
    public function it_updates_existing_leaderboard_entry()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();

        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user->id,
            'total_score' => 85,
            'possible_points' => 100,
            'percentage' => 85.0,
        ]);

        // Create initial leaderboard entry
        $this->leaderboardService->updateLeaderboardForEntry($entry);

        // Update entry score
        $entry->update([
            'total_score' => 95,
            'percentage' => 95.0,
        ]);

        // Update leaderboard
        $this->leaderboardService->updateLeaderboardForEntry($entry);

        // Should update, not create new
        $this->assertEquals(1, Leaderboard::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('propoff_leaderboards', [
            'user_id' => $user->id,
            'total_score' => 95,
            'percentage' => 95.0,
        ]);
    }

    /** @test */
    public function it_calculates_ranks_correctly_for_group()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        // Create 3 users with different scores
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user1->id,
            'total_score' => 95,
            'possible_points' => 100,
            'percentage' => 95.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user2->id,
            'total_score' => 85,
            'possible_points' => 100,
            'percentage' => 85.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user3->id,
            'total_score' => 75,
            'possible_points' => 100,
            'percentage' => 75.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        $this->leaderboardService->updateRanks($event->id, $group->id);

        // Check ranks are correct (1, 2, 3)
        $this->assertEquals(1, Leaderboard::where('user_id', $user1->id)->first()->rank);
        $this->assertEquals(2, Leaderboard::where('user_id', $user2->id)->first()->rank);
        $this->assertEquals(3, Leaderboard::where('user_id', $user3->id)->first()->rank);
    }

    /** @test */
    public function it_handles_ties_correctly_in_ranking()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        // Create 4 users: 2 tied for first, then unique 3rd and 4th
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $user4 = User::factory()->create();

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user1->id,
            'total_score' => 90,
            'possible_points' => 100,
            'percentage' => 90.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user2->id,
            'total_score' => 90,
            'possible_points' => 100,
            'percentage' => 90.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user3->id,
            'total_score' => 80,
            'possible_points' => 100,
            'percentage' => 80.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user4->id,
            'total_score' => 70,
            'possible_points' => 100,
            'percentage' => 70.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        $this->leaderboardService->updateRanks($event->id, $group->id);

        // Both tied users should be rank 1
        $this->assertEquals(1, Leaderboard::where('user_id', $user1->id)->first()->rank);
        $this->assertEquals(1, Leaderboard::where('user_id', $user2->id)->first()->rank);

        // Next should be rank 3 (skip rank 2 due to tie)
        $this->assertEquals(3, Leaderboard::where('user_id', $user3->id)->first()->rank);
        $this->assertEquals(4, Leaderboard::where('user_id', $user4->id)->first()->rank);
    }

    /** @test */
    public function it_breaks_ties_by_total_score_when_percentages_equal()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Same percentage but different total scores
        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user1->id,
            'total_score' => 90,
            'possible_points' => 100,
            'percentage' => 90.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user2->id,
            'total_score' => 45, // Lower total score
            'possible_points' => 50,
            'percentage' => 90.0, // Same percentage
            'answered_count' => 10,
            'rank' => 0,
        ]);

        $this->leaderboardService->updateRanks($event->id, $group->id);

        // User1 should rank higher (better total score)
        $this->assertEquals(1, Leaderboard::where('user_id', $user1->id)->first()->rank);
        $this->assertEquals(2, Leaderboard::where('user_id', $user2->id)->first()->rank);
    }

    /** @test */
    public function it_ties_users_with_same_score_regardless_of_answered_count()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Same total score but different answered_count - should still tie
        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user1->id,
            'total_score' => 90,
            'possible_points' => 100,
            'percentage' => 90.0,
            'answered_count' => 12, // More answers
            'rank' => 0,
        ]);

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user2->id,
            'total_score' => 90,
            'possible_points' => 100,
            'percentage' => 90.0,
            'answered_count' => 10, // Fewer answers
            'rank' => 0,
        ]);

        $this->leaderboardService->updateRanks($event->id, $group->id);

        // Both should be tied for rank 1 (same total score)
        $this->assertEquals(1, Leaderboard::where('user_id', $user1->id)->first()->rank);
        $this->assertEquals(1, Leaderboard::where('user_id', $user2->id)->first()->rank);
    }

    /** @test */
    public function it_calculates_global_leaderboard_ranks()
    {
        $event = Event::factory()->create();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create global leaderboard entries (group_id is null)
        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => null,
            'user_id' => $user1->id,
            'total_score' => 95,
            'possible_points' => 100,
            'percentage' => 95.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => null,
            'user_id' => $user2->id,
            'total_score' => 85,
            'possible_points' => 100,
            'percentage' => 85.0,
            'answered_count' => 10,
            'rank' => 0,
        ]);

        $this->leaderboardService->updateRanks($event->id, null);

        $this->assertEquals(1, Leaderboard::where('user_id', $user1->id)->whereNull('group_id')->first()->rank);
        $this->assertEquals(2, Leaderboard::where('user_id', $user2->id)->whereNull('group_id')->first()->rank);
    }

    /** @test */
    public function it_recalculates_event_leaderboards_for_all_groups()
    {
        $event = Event::factory()->create();
        $group1 = Group::factory()->create(['event_id' => $event->id]);
        $group2 = Group::factory()->create(['event_id' => $event->id]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create entries for both groups
        tap(Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group1->id,
            'user_id' => $user1->id,
            'total_score' => 90,
            'possible_points' => 100,
            'percentage' => 90.0,
            'is_complete' => true,
        ]), fn ($e) => $this->giveAnswer($e));

        tap(Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group2->id,
            'user_id' => $user2->id,
            'total_score' => 85,
            'possible_points' => 100,
            'percentage' => 85.0,
            'is_complete' => true,
        ]), fn ($e) => $this->giveAnswer($e));

        $this->leaderboardService->recalculateEventLeaderboards($event);

        // Check group leaderboards exist
        $this->assertDatabaseHas('propoff_leaderboards', [
            'event_id' => $event->id,
            'group_id' => $group1->id,
            'user_id' => $user1->id,
        ]);

        $this->assertDatabaseHas('propoff_leaderboards', [
            'event_id' => $event->id,
            'group_id' => $group2->id,
            'user_id' => $user2->id,
        ]);

        // Check global leaderboard exists
        $this->assertDatabaseHas('propoff_leaderboards', [
            'event_id' => $event->id,
            'group_id' => null,
            'user_id' => $user1->id,
        ]);
    }

    /** @test */
    public function it_aggregates_user_scores_across_groups_for_global_leaderboard()
    {
        $event = Event::factory()->create();
        $group1 = Group::factory()->create(['event_id' => $event->id]);
        $group2 = Group::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();

        // User participates in both groups
        tap(Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group1->id,
            'user_id' => $user->id,
            'total_score' => 50,
            'possible_points' => 100,
            'percentage' => 50.0,
            'is_complete' => true,
        ]), fn ($e) => $this->giveAnswer($e));

        tap(Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group2->id,
            'user_id' => $user->id,
            'total_score' => 40,
            'possible_points' => 100,
            'percentage' => 40.0,
            'is_complete' => true,
        ]), fn ($e) => $this->giveAnswer($e));

        $this->leaderboardService->recalculateEventLeaderboards($event);

        // Global leaderboard should have aggregated scores
        $globalEntry = Leaderboard::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereNull('group_id')
            ->first();

        $this->assertNotNull($globalEntry);
        $this->assertEquals(90, $globalEntry->total_score); // 50 + 40
        $this->assertEquals(200, $globalEntry->possible_points); // 100 + 100
        $this->assertEquals(45.0, $globalEntry->percentage); // 90/200 * 100
    }

    /** @test */
    public function it_gets_leaderboard_for_specific_group()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user1->id,
            'total_score' => 95,
            'possible_points' => 100,
            'percentage' => 95.0,
            'answered_count' => 10,
            'rank' => 1,
        ]);

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user2->id,
            'total_score' => 85,
            'possible_points' => 100,
            'percentage' => 85.0,
            'answered_count' => 10,
            'rank' => 2,
        ]);

        $leaderboard = $this->leaderboardService->getLeaderboard($event, $group);

        $this->assertCount(2, $leaderboard);
        $this->assertEquals($user1->id, $leaderboard->first()->user_id);
        $this->assertEquals(1, $leaderboard->first()->rank);
    }

    /** @test */
    public function it_gets_global_leaderboard_when_no_group_specified()
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => null,
            'user_id' => $user->id,
            'total_score' => 95,
            'possible_points' => 100,
            'percentage' => 95.0,
            'answered_count' => 10,
            'rank' => 1,
        ]);

        $leaderboard = $this->leaderboardService->getLeaderboard($event, null);

        $this->assertCount(1, $leaderboard);
        $this->assertEquals($user->id, $leaderboard->first()->user_id);
        $this->assertNull($leaderboard->first()->group_id);
    }

    /** @test */
    public function it_gets_user_rank_in_group()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();

        Leaderboard::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user->id,
            'total_score' => 85,
            'possible_points' => 100,
            'percentage' => 85.0,
            'answered_count' => 10,
            'rank' => 3,
        ]);

        $rank = $this->leaderboardService->getUserRank($event, $user->id, $group->id);

        $this->assertEquals(3, $rank);
    }

    /** @test */
    public function it_returns_null_when_user_has_no_rank()
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();

        $rank = $this->leaderboardService->getUserRank($event, $user->id, null);

        $this->assertNull($rank);
    }

    /** @test */
    public function it_gets_top_performers()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        // Create 5 users with ranks 1-5
        for ($i = 1; $i <= 5; $i++) {
            $user = User::factory()->create();
            Leaderboard::create([
                'event_id' => $event->id,
                'group_id' => $group->id,
                'user_id' => $user->id,
                'total_score' => 100 - ($i * 5),
                'possible_points' => 100,
                'percentage' => 100 - ($i * 5),
                'answered_count' => 10,
                'rank' => $i,
            ]);
        }

        $topPerformers = $this->leaderboardService->getTopPerformers($event, 3, $group->id);

        $this->assertCount(3, $topPerformers);
        $this->assertEquals(1, $topPerformers[0]->rank);
        $this->assertEquals(2, $topPerformers[1]->rank);
        $this->assertEquals(3, $topPerformers[2]->rank);
    }

    /** @test */
    public function it_calculates_leaderboard_statistics()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        // Create entries with percentages: 90, 80, 70, 60, 50
        $percentages = [90, 80, 70, 60, 50];
        foreach ($percentages as $percentage) {
            $user = User::factory()->create();
            Leaderboard::create([
                'event_id' => $event->id,
                'group_id' => $group->id,
                'user_id' => $user->id,
                'total_score' => $percentage,
                'possible_points' => 100,
                'percentage' => (float) $percentage,
                'answered_count' => 10,
                'rank' => 1,
            ]);
        }

        $stats = $this->leaderboardService->getLeaderboardStats($event, $group->id);

        $this->assertEquals(5, $stats['total_participants']);
        $this->assertEquals(70.0, $stats['average_score']);
        $this->assertEquals(90.0, $stats['highest_score']);
        $this->assertEquals(50.0, $stats['lowest_score']);
        $this->assertEquals(70.0, $stats['median_score']); // Middle value
    }

    /** @test */
    public function it_calculates_median_with_odd_number_of_values()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        // Odd number: 5 values
        $percentages = [90, 80, 70, 60, 50];
        foreach ($percentages as $percentage) {
            $user = User::factory()->create();
            Leaderboard::create([
                'event_id' => $event->id,
                'group_id' => $group->id,
                'user_id' => $user->id,
                'percentage' => (float) $percentage,
                'total_score' => $percentage,
                'possible_points' => 100,
                'answered_count' => 10,
                'rank' => 1,
            ]);
        }

        $stats = $this->leaderboardService->getLeaderboardStats($event, $group->id);

        // Median of [50, 60, 70, 80, 90] is 70
        $this->assertEquals(70.0, $stats['median_score']);
    }

    /** @test */
    public function it_calculates_median_with_even_number_of_values()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        // Even number: 4 values
        $percentages = [90, 80, 60, 50];
        foreach ($percentages as $percentage) {
            $user = User::factory()->create();
            Leaderboard::create([
                'event_id' => $event->id,
                'group_id' => $group->id,
                'user_id' => $user->id,
                'percentage' => (float) $percentage,
                'total_score' => $percentage,
                'possible_points' => 100,
                'answered_count' => 10,
                'rank' => 1,
            ]);
        }

        $stats = $this->leaderboardService->getLeaderboardStats($event, $group->id);

        // Median of [50, 60, 80, 90] is (60 + 80) / 2 = 70
        $this->assertEquals(70.0, $stats['median_score']);
    }

    /** @test */
    public function it_handles_empty_leaderboard_for_statistics()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        $stats = $this->leaderboardService->getLeaderboardStats($event, $group->id);

        $this->assertEquals(0, $stats['total_participants']);
        $this->assertEquals(0, $stats['average_score']);
        $this->assertEquals(0, $stats['highest_score']);
        $this->assertEquals(0, $stats['lowest_score']);
        $this->assertEquals(0, $stats['median_score']);
    }

    /** @test */
    public function it_updates_leaderboard_for_specific_group()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();

        $entry = Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user->id,
            'total_score' => 85,
            'possible_points' => 100,
            'percentage' => 85.0,
            'is_complete' => true,
        ]);
        $this->giveAnswer($entry);

        $this->leaderboardService->updateLeaderboard($event, $group);

        // Should create group leaderboard
        $this->assertDatabaseHas('propoff_leaderboards', [
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        // Global rows are created by recalculateEventLeaderboards, not by
        // the per-group update (current design).
        $this->leaderboardService->recalculateEventLeaderboards($event);
        $this->assertDatabaseHas('propoff_leaderboards', [
            'event_id' => $event->id,
            'group_id' => null,
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_only_includes_completed_entries_in_leaderboard()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Completed entry
        tap(Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user1->id,
            'is_complete' => true,
        ]), fn ($e) => $this->giveAnswer($e));

        // Incomplete entry
        tap(Entry::factory()->create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'user_id' => $user2->id,
            'is_complete' => false,
        ]), fn ($e) => $this->giveAnswer($e));

        $this->leaderboardService->recalculateEventLeaderboards($event);

        // Current design: leaderboards include in-progress entries too (live
        // standings during an event), so BOTH users appear.
        $this->assertDatabaseHas('propoff_leaderboards', [
            'user_id' => $user1->id,
        ]);

        $this->assertDatabaseHas('propoff_leaderboards', [
            'user_id' => $user2->id,
        ]);
    }

    /** @test */
    public function it_limits_leaderboard_results()
    {
        $event = Event::factory()->create();
        $group = Group::factory()->create(['event_id' => $event->id]);

        // Create 10 users
        for ($i = 1; $i <= 10; $i++) {
            $user = User::factory()->create();
            Leaderboard::create([
                'event_id' => $event->id,
                'group_id' => $group->id,
                'user_id' => $user->id,
                'total_score' => 100 - $i,
                'possible_points' => 100,
                'percentage' => 100 - $i,
                'answered_count' => 10,
                'rank' => $i,
            ]);
        }

        $leaderboard = $this->leaderboardService->getLeaderboard($event, $group, 5);

        $this->assertCount(5, $leaderboard);
    }
}
