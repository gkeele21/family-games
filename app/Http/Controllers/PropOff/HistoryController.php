<?php

namespace App\Http\Controllers\PropOff;

use App\Http\Controllers\Controller;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Leaderboard;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HistoryController extends Controller
{
    /**
     * Display the history page.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $year = $request->query('year');

        // Get completed entries for the user
        $query = Entry::with(['event', 'group'])
            ->where('user_id', $user->id)
            ->where('is_complete', true)
            ->whereHas('event', function ($q) use ($year) {
                if ($year) {
                    $q->whereYear('event_date', $year);
                }
            })
            ->orderBy('submitted_at', 'desc');

        $entries = $query->paginate(20);

        // Get all years with entries for filter
        $years = Entry::where('user_id', $user->id)
            ->where('is_complete', true)
            ->with('event:id,event_date')
            ->get()
            ->pluck('event.event_date')
            ->filter()
            ->map(fn ($date) => $date->year)
            ->unique()
            ->sortDesc()
            ->values();

        // Calculate stats
        $stats = $this->calculateStats($user);

        // Format entries with leaderboard data
        $formattedEntries = $entries->through(function ($entry) use ($user) {
            $leaderboard = Leaderboard::where('event_id', $entry->event_id)
                ->where('group_id', $entry->group_id)
                ->where('user_id', $user->id)
                ->first();

            $totalPlayers = Leaderboard::where('event_id', $entry->event_id)
                ->where('group_id', $entry->group_id)
                ->count();

            return [
                'id' => $entry->id,
                'event_name' => $entry->event->name,
                'group_name' => $entry->group->name,
                'event_date' => $entry->event->event_date,
                'player_count' => $totalPlayers,
                'rank' => $leaderboard?->rank,
                'score' => $entry->total_score,
                'group_code' => $entry->group->code,
            ];
        });

        return Inertia::render('PropOff/History', [
            'stats' => $stats,
            'years' => $years,
            'entries' => $formattedEntries,
            'currentYear' => $year ? (int) $year : null,
        ]);
    }

    /**
     * Calculate aggregate stats for the user.
     */
    protected function calculateStats($user): array
    {
        // Get all completed entries
        $completedEntries = Entry::where('user_id', $user->id)
            ->where('is_complete', true)
            ->get();

        $eventsPlayed = $completedEntries->count();

        // Get podium finishes
        $leaderboards = Leaderboard::where('user_id', $user->id)->get();

        $first = $leaderboards->where('rank', 1)->count();
        $second = $leaderboards->where('rank', 2)->count();
        $third = $leaderboards->where('rank', 3)->count();

        // Calculate average from first
        $avgFromFirst = 0;
        if ($leaderboards->count() > 0) {
            $totalDiff = 0;
            $count = 0;

            foreach ($leaderboards as $entry) {
                $firstPlace = Leaderboard::where('event_id', $entry->event_id)
                    ->where('group_id', $entry->group_id)
                    ->where('rank', 1)
                    ->first();

                if ($firstPlace) {
                    $totalDiff += $entry->total_score - $firstPlace->total_score;
                    $count++;
                }
            }

            $avgFromFirst = $count > 0 ? round($totalDiff / $count, 1) : 0;
        }

        // Calculate average rank
        $avgRank = $leaderboards->count() > 0
            ? round($leaderboards->avg('rank'), 1)
            : 0;

        return [
            'events_played' => $eventsPlayed,
            'podium_finishes' => [
                'first' => $first,
                'second' => $second,
                'third' => $third,
            ],
            'avg_from_first' => $avgFromFirst,
            'avg_rank' => $avgRank,
        ];
    }
}
