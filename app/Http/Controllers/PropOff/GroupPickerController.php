<?php

namespace App\Http\Controllers\PropOff;

use App\Http\Controllers\Controller;

use App\Models\PropOff\Entry;
use App\Models\PropOff\Leaderboard;
use App\Services\SmartRoutingService;
use Inertia\Inertia;

class GroupPickerController extends Controller
{
    public function __construct(
        protected SmartRoutingService $smartRouting
    ) {}

    /**
     * Display the group picker page.
     */
    public function index()
    {
        $user = auth()->user();
        $activeGroups = $this->smartRouting->getActiveGroups($user);

        // If only one group, redirect directly
        if ($activeGroups->count() === 1) {
            return redirect()->route('propoff.play.hub', ['code' => $activeGroups->first()->code]);
        }

        // If no groups, redirect to home
        if ($activeGroups->count() === 0) {
            return redirect()->route('propoff.groups.index');
        }

        // Format groups for display
        $groups = $activeGroups->map(function ($group) use ($user) {
            // Check if user is captain
            $isCaptain = $user->isCaptainOf($group->id);

            // Get user's entry progress
            $entry = Entry::where('user_id', $user->id)
                ->where('group_id', $group->id)
                ->first();

            $totalQuestions = $group->groupQuestions()->where('is_active', true)->count();
            $answeredCount = $entry ? $entry->userAnswers()->count() : 0;

            // Get user's rank
            $leaderboard = Leaderboard::where('event_id', $group->event_id)
                ->where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->first();

            // Get progress status
            $progressStatus = 'Not started';
            if ($entry) {
                if ($entry->is_complete) {
                    $progressStatus = $leaderboard
                        ? $this->getOrdinal($leaderboard->rank) . ' place'
                        : 'Submitted';
                } else {
                    $progressStatus = $answeredCount . '/' . $totalQuestions . ' answered';
                }
            }

            return [
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
                'is_captain' => $isCaptain,
                'event' => $group->event ? [
                    'id' => $group->event->id,
                    'name' => $group->event->name,
                    'event_date' => $group->event->event_date,
                ] : null,
                'members_count' => $group->users()->count(),
                'my_progress' => [
                    'answered' => $answeredCount,
                    'total' => $totalQuestions,
                    'status' => $progressStatus,
                ],
                'my_rank' => $leaderboard?->rank,
            ];
        });

        return Inertia::render('PropOff/Groups/Selector', [
            'groups' => $groups,
        ]);
    }

    /**
     * Get ordinal suffix for a number.
     */
    protected function getOrdinal(int $n): string
    {
        $s = ['th', 'st', 'nd', 'rd'];
        $v = $n % 100;
        return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }
}
