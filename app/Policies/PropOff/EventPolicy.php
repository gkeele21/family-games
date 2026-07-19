<?php

namespace App\Policies\PropOff;

use App\Models\PropOff\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admins can create events
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        // User must be the creator or an admin
        return $user->id === $event->created_by || $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        // User must be the creator or an admin
        return $user->id === $event->created_by || $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): bool
    {
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can submit answers to the event.
     */
    public function submit(User $user, Event $event): bool
    {
        // Event must be open for entries
        if ($event->status !== 'open') {
            return false;
        }

        // Event must not be past the lock date
        if ($event->lock_date && now()->isAfter($event->lock_date)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view the event results.
     */
    public function viewResults(User $user, Event $event): bool
    {
        // Results are visible when event is completed or admin at any time
        return $event->status === 'completed' || $user->hasAdminAccess();
    }
}
