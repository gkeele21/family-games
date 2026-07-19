<?php

namespace App\Policies\PropOff;

use App\Models\PropOff\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GroupPolicy
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
    public function view(User $user, Group $group): bool
    {
        // Admin, captain, or member can view
        return $user->hasAdminAccess()
            || $user->isCaptainOf($group->id)
            || $group->members->contains($user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create groups
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Group $group): bool
    {
        // Admin or captain can update
        return $user->hasAdminAccess() || $user->isCaptainOf($group->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool
    {
        // Admin or captain can delete
        return $user->hasAdminAccess() || $user->isCaptainOf($group->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Group $group): bool
    {
        // Admin or captain can restore
        return $user->hasAdminAccess() || $user->isCaptainOf($group->id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Group $group): bool
    {
        // Only admin can force delete
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can add users to the group.
     */
    public function addUser(User $user, Group $group): bool
    {
        // Admin or captain can add users
        return $user->hasAdminAccess() || $user->isCaptainOf($group->id);
    }

    /**
     * Determine whether the user can remove users from the group.
     */
    public function removeUser(User $user, Group $group): bool
    {
        // Admin or captain can remove users
        return $user->hasAdminAccess() || $user->isCaptainOf($group->id);
    }

    /**
     * Determine whether the user can manage questions in the group.
     */
    public function manageQuestions(User $user, Group $group): bool
    {
        // Admin or captain can manage questions
        return $user->hasAdminAccess() || $user->isCaptainOf($group->id);
    }

    /**
     * Determine whether the user can grade (set answers) for the group.
     */
    public function grade(User $user, Group $group): bool
    {
        // Admin or captain can grade
        return $user->hasAdminAccess() || $user->isCaptainOf($group->id);
    }

    /**
     * Determine whether the user can manage members (promote/demote captains).
     */
    public function manageMembers(User $user, Group $group): bool
    {
        // Admin or captain can manage members
        return $user->hasAdminAccess() || $user->isCaptainOf($group->id);
    }
}
