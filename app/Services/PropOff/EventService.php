<?php

namespace App\Services\PropOff;

use App\Models\PropOff\Event;
use App\Models\PropOff\Group;
use App\Models\PropOff\Entry;
use App\Models\User;

class EventService
{
    /**
     * Check if a user has joined an event for a specific group.
     */
    public function hasUserJoinedEvent(Event $event, User $user, Group $group): bool
    {
        return Entry::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->exists();
    }

    /**
     * Get user's entry for an event and group.
     */
    public function getUserEntry(Event $event, User $user, Group $group): ?Entry
    {
        return Entry::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->first();
    }

    /**
     * Check if an event is playable (open and before lock date).
     */
    public function isEventPlayable(Event $event): bool
    {
        if ($event->status !== 'open') {
            return false;
        }

        if ($event->lock_date && now()->isAfter($event->lock_date)) {
            return false;
        }

        return true;
    }

    /**
     * Get active events for a user.
     */
    public function getActiveEvents(int $limit = 10)
    {
        return Event::where('status', 'open')
            ->where('lock_date', '>', now())
            ->orderBy('lock_date', 'asc')
            ->with('creator')
            ->withCount('questions')
            ->limit($limit)
            ->get();
    }

    /**
     * Get completed events.
     */
    public function getCompletedEvents(int $limit = 10)
    {
        return Event::where('status', 'completed')
            ->orderBy('event_date', 'desc')
            ->with('creator')
            ->withCount(['questions', 'entries'])
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate total possible points for an event.
     */
    public function calculatePossiblePoints(Event $event): int
    {
        return $event->questions()->sum('points');
    }

    /**
     * Get events user can participate in (open and before lock).
     */
    public function getAvailableEventsForUser(User $user, int $perPage = 15)
    {
        return Event::where('status', 'open')
            ->where(function ($query) {
                $query->whereNull('lock_date')
                    ->orWhere('lock_date', '>', now());
            })
            ->with('creator')
            ->withCount('questions')
            ->orderBy('event_date', 'asc')
            ->paginate($perPage);
    }

    /**
     * Search events by name or description.
     */
    public function searchEvents(string $query, int $perPage = 15)
    {
        return Event::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->with('creator')
            ->withCount(['questions', 'entries'])
            ->orderBy('event_date', 'desc')
            ->paginate($perPage);
    }

    /**
     * Filter events by status.
     */
    public function filterEventsByStatus(string $status, int $perPage = 15)
    {
        return Event::where('status', $status)
            ->with('creator')
            ->withCount(['questions', 'entries'])
            ->orderBy('event_date', 'desc')
            ->paginate($perPage);
    }
}
