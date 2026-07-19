<?php

namespace App\Services\PropOff;

use App\Models\User;

class SmartRoutingService
{
    /**
     * Get the appropriate redirect URL for a user based on their active groups.
     *
     * Logic:
     * - 0 active groups: Go to homepage (join a group prompt)
     * - 1 active group: Go directly to that group's Play Hub
     * - Multiple groups: Go to group chooser page
     */
    public function getRedirectForUser(User $user): string
    {
        $activeGroups = $this->getActiveGroups($user);
        $count = $activeGroups->count();

        if ($count === 0) {
            // No active groups - go to join page
            return route('propoff.groups.index');
        }

        if ($count === 1) {
            return route('propoff.play.hub', ['code' => $activeGroups->first()->code]);
        }

        return route('propoff.selector');
    }

    /**
     * Get active groups for a user.
     * Active = event is not completed and not locked more than 7 days ago.
     */
    public function getActiveGroups(User $user)
    {
        return $user->propoffGroups()
            ->with('event')
            ->whereHas('event', function ($query) {
                $query->whereIn('status', ['draft', 'open', 'locked'])
                    ->orWhere('event_date', '>=', now()->subDays(7));
            })
            ->get();
    }
}
