<?php

namespace App\Policies\PropOff;

use App\Models\PropOff\Entry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EntryPolicy
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
    public function view(User $user, Entry $entry): bool
    {
        // User can view their own entries, or event creator can view all entries for their event
        return $user->id === $entry->user_id
            || $user->id === $entry->event->created_by
            || $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Entry $entry): bool
    {
        // User must own the entry (can edit even if submitted by captain per requirements)
        if ($user->id !== $entry->user_id) {
            return false;
        }

        // Check if event is still before lock date
        if ($entry->event->lock_date && now()->isAfter($entry->event->lock_date)) {
            return false;
        }

        // Check if event is not completed or in progress
        if (in_array($entry->event->status, ['completed', 'in_progress'])) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Entry $entry): bool
    {
        // User can only delete their own incomplete entries
        return $user->id === $entry->user_id && !$entry->is_complete;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Entry $entry): bool
    {
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Entry $entry): bool
    {
        return $user->hasAdminAccess();
    }
}
