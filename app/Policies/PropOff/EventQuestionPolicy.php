<?php

namespace App\Policies\PropOff;

use App\Models\PropOff\EventQuestion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventQuestionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view event questions
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EventQuestion $eventQuestion): bool
    {
        // All authenticated users can view event questions
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admins can create event questions
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EventQuestion $eventQuestion): bool
    {
        // Only admins can update event questions
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EventQuestion $eventQuestion): bool
    {
        // Only admins can delete event questions
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EventQuestion $eventQuestion): bool
    {
        // Only admins can restore event questions
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EventQuestion $eventQuestion): bool
    {
        // Only admins can permanently delete event questions
        return $user->hasAdminAccess();
    }
}
