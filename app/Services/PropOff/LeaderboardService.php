<?php

namespace App\Services\PropOff;

use App\Models\PropOff\Event;
use App\Models\PropOff\Group;
use App\Models\PropOff\Leaderboard;
use App\Models\PropOff\Entry;

class LeaderboardService
{
    /**
     * Update leaderboard for a specific entry.
     */
    public function updateLeaderboardForEntry(Entry $entry): void
    {
        $answeredCount = $entry->userAnswers()->count();
        Leaderboard::updateOrCreate(
            [
                'event_id' => $entry->event_id,
                'user_id' => $entry->user_id,
                'group_id' => $entry->group_id,
            ],
            [
                'total_score' => $entry->total_score,
                'possible_points' => $entry->possible_points,
                'percentage' => $entry->percentage,
                'answered_count' => $answeredCount,
                'rank' => 0, // Will be calculated in updateRanks()
            ]
        );
    }

    /**
     * Update ranks for a specific event and group leaderboard.
     * Rank is based ONLY on total points.
     */
    public function updateRanks(int $eventId, ?int $groupId = null): void
    {
        $query = Leaderboard::where('event_id', $eventId);
        if ($groupId) {
            $query->where('group_id', $groupId);
        } else {
            $query->whereNull('group_id');
        }

        // Order by total_score DESC only
        $entries = $query->orderByDesc('total_score')->get();

        $currentRank = 1;
        $previousScore = null;

        foreach ($entries as $index => $entry) {
            // Check if this entry ties with the previous one (same score = same rank)
            if ($previousScore !== null && $previousScore === $entry->total_score) {
                // Same rank as previous (tie)
            } else {
                // New rank = position in list (1-indexed)
                $currentRank = $index + 1;
            }

            $entry->update(['rank' => $currentRank]);
            $previousScore = $entry->total_score;
        }
    }

    /**
     * Recalculate all leaderboards for an event.
     */
    public function recalculateEventLeaderboards(Event $event): void
    {
        // Get all entries with at least one answer
        $entries = $event->entries()
            ->whereHas('userAnswers')
            ->with('userAnswers')
            ->get();

        // Update leaderboard entries
        foreach ($entries as $entry) {
            $this->updateLeaderboardForEntry($entry);
        }

        // Update global leaderboard ranks
        $this->updateRanks($event->id, null);

        // Update ranks for each group
        $groupIds = $event->entries()
            ->whereNotNull('group_id')
            ->distinct()
            ->pluck('group_id');

        foreach ($groupIds as $groupId) {
            $this->updateRanks($event->id, $groupId);
        }

        // Create/update global leaderboard (aggregate across groups)
        $this->createGlobalLeaderboard($event);
    }

    /**
     * Create global leaderboard by aggregating group performances.
     */
    protected function createGlobalLeaderboard(Event $event): void
    {
        // Get all users who have answered at least one question
        $userIds = $event->entries()
            ->whereHas('userAnswers')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            // Get all entries for this user across all groups
            $userEntries = $event->entries()
                ->where('user_id', $userId)
                ->whereHas('userAnswers')
                ->get();

            // Aggregate scores
            $totalScore = $userEntries->sum('total_score');
            $possiblePoints = $userEntries->sum('possible_points');
            $answeredCount = $userEntries->sum(function ($entry) {
                return $entry->userAnswers()->count();
            });

            $percentage = $possiblePoints > 0
                ? round(($totalScore / $possiblePoints) * 100, 2)
                : 0;

            // Create or update global leaderboard entry
            Leaderboard::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'user_id' => $userId,
                    'group_id' => null, // Global leaderboard
                ],
                [
                    'total_score' => $totalScore,
                    'possible_points' => $possiblePoints,
                    'percentage' => $percentage,
                    'answered_count' => $answeredCount,
                    'rank' => 0, // Will be updated by updateRanks()
                ]
            );
        }

        // Update global ranks
        $this->updateRanks($event->id, null);
    }

    /**
     * Get leaderboard for a specific event and group.
     */
    public function getLeaderboard(Event $event, ?Group $group = null, int $limit = 50)
    {
        $query = Leaderboard::with('user')
            ->where('event_id', $event->id);

        if ($group) {
            $query->where('group_id', $group->id);
        } else {
            $query->whereNull('group_id');
        }

        return $query->orderBy('rank')
            ->limit($limit)
            ->get();
    }

    /**
     * Get user's rank in a specific leaderboard.
     */
    public function getUserRank(Event $event, int $userId, ?int $groupId = null): ?int
    {
        $leaderboard = Leaderboard::where('event_id', $event->id)
            ->where('user_id', $userId);

        if ($groupId) {
            $leaderboard->where('group_id', $groupId);
        } else {
            $leaderboard->whereNull('group_id');
        }

        $entry = $leaderboard->first();

        return $entry?->rank;
    }

    /**
     * Get top performers for an event.
     */
    public function getTopPerformers(Event $event, int $limit = 10, ?int $groupId = null)
    {
        $query = Leaderboard::with('user')
            ->where('event_id', $event->id);

        if ($groupId) {
            $query->where('group_id', $groupId);
        } else {
            $query->whereNull('group_id');
        }

        return $query->orderBy('rank')
            ->limit($limit)
            ->get();
    }

    /**
     * Get leaderboard statistics for an event.
     */
    public function getLeaderboardStats(Event $event, ?int $groupId = null): array
    {
        $query = Leaderboard::where('event_id', $event->id);
        if ($groupId) {
            $query->where('group_id', $groupId);
        } else {
            $query->whereNull('group_id');
        }

        $entries = $query->get();

        return [
            'total_participants' => $entries->count(),
            'average_score' => $entries->avg('percentage') ?? 0,
            'highest_score' => $entries->max('percentage') ?? 0,
            'lowest_score' => $entries->min('percentage') ?? 0,
            'median_score' => $this->calculateMedian($entries->pluck('percentage')->toArray()),
        ];
    }

    /**
     * Calculate median value.
     */
    protected function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Update leaderboard for a specific event and group.
     * Uses bulk operations for efficiency.
     */
    public function updateLeaderboard(Event $event, Group $group): void
    {
        // Get all entries with answer counts in a single query
        $entries = $event->entries()
            ->where('group_id', $group->id)
            ->withCount('userAnswers')
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        // Bulk upsert leaderboard entries
        $leaderboardData = $entries->map(function ($entry) {
            return [
                'event_id' => $entry->event_id,
                'user_id' => $entry->user_id,
                'group_id' => $entry->group_id,
                'total_score' => $entry->total_score,
                'possible_points' => $entry->possible_points,
                'percentage' => $entry->percentage,
                'answered_count' => $entry->user_answers_count,
                'rank' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        Leaderboard::upsert(
            $leaderboardData,
            ['event_id', 'user_id', 'group_id'],
            ['total_score', 'possible_points', 'percentage', 'answered_count', 'rank', 'updated_at']
        );

        // Update ranks for this group
        $this->updateRanksBulk($event->id, $group->id);
    }

    /**
     * Update ranks using a more efficient approach.
     */
    public function updateRanksBulk(int $eventId, int $groupId): void
    {
        // Get all leaderboard entries ordered by score
        $entries = Leaderboard::where('event_id', $eventId)
            ->where('group_id', $groupId)
            ->orderByDesc('total_score')
            ->get(['id', 'total_score']);

        if ($entries->isEmpty()) {
            return;
        }

        // Calculate ranks in memory
        $updates = [];
        $currentRank = 1;
        $previousScore = null;

        foreach ($entries as $index => $entry) {
            if ($previousScore !== null && $previousScore === $entry->total_score) {
                // Tie - same rank
            } else {
                $currentRank = $index + 1;
            }
            $updates[$entry->id] = $currentRank;
            $previousScore = $entry->total_score;
        }

        // Bulk update ranks using CASE statement
        if (!empty($updates)) {
            $cases = [];
            $ids = [];
            foreach ($updates as $id => $rank) {
                $cases[] = "WHEN id = {$id} THEN {$rank}";
                $ids[] = $id;
            }
            $caseSql = implode(' ', $cases);
            $idsSql = implode(',', $ids);

            \DB::statement("UPDATE propoff_leaderboards SET `rank` = CASE {$caseSql} END WHERE id IN ({$idsSql})");
        }
    }
}
