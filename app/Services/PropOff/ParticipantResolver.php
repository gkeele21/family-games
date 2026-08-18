<?php

namespace App\Services\PropOff;

use App\Models\PropOff\Group;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One place that decides whether someone joining a PropOff group is a person we
 * already know or a genuinely new one.
 *
 * Before this existed, four routes created users independently — the invitation
 * flow, the code-join flow, the captain's add-guest, and captain group creation
 * — and only two of them looked for an existing person first. The invitation
 * flow, which carried 140 of the joins for Super Bowl LX, created a fresh row
 * every single time. That is where most of the 18 duplicated names came from:
 * the same human re-registering after losing their magic link.
 *
 * Deliberately NOT silent de-duplication. Matching on name alone would merge two
 * different real people who share one — "Megan" already appears three times in
 * the existing data. A name match is a *candidate*; the caller shows it back to
 * the person and lets them confirm or reject it.
 */
class ParticipantResolver
{
    /**
     * A name match inside this group — the person may be returning. Returns a
     * candidate to confirm, never something to silently reuse.
     */
    public function findCandidateInGroup(string $name, Group $group): ?User
    {
        $split = User::splitName($name);

        return $group->users()
            ->where('first_name', $split['first_name'])
            ->where('last_name', $split['last_name'])
            ->first();
    }

    /**
     * An email match is a definite identity — emails are unique on users, and
     * someone typing their own address is asserting who they are. Safe to reuse
     * without confirmation.
     */
    public function findByEmail(?string $email): ?User
    {
        if (! $email) {
            return null;
        }

        return User::where('email', $email)->first();
    }

    /**
     * Attach to the group, tolerating an already-present membership so a
     * double-submitted join never throws or duplicates the pivot row.
     */
    public function attachTo(User $user, Group $group, bool $isCaptain = false): void
    {
        if ($group->users()->where('users.id', $user->id)->exists()) {
            return;
        }

        $group->users()->attach($user->id, [
            'joined_at'  => now(),
            'is_captain' => $isCaptain,
        ]);
    }

    /**
     * Create a new guest identity. A guest_token is issued only when there's no
     * password, since it's the magic-link credential for people who can't log
     * in any other way.
     */
    public function createGuest(string $name, ?string $email = null, ?string $password = null): User
    {
        return User::create([
            ...User::splitName($name),
            'email'       => $email,
            'password'    => $password ? Hash::make($password) : null,
            'role'        => 'guest',
            'guest_token' => $password ? null : Str::random(32),
        ]);
    }

    /**
     * The summary shown on the "is this you?" step: enough for someone to
     * recognise their own entry without exposing anyone else's answers.
     */
    public function candidateSummary(User $user, Group $group): array
    {
        $entry = $group->entries()->where('user_id', $user->id)->first();

        return [
            'name'     => $user->name,
            'answered' => $entry ? $entry->userAnswers()->count() : 0,
            'total'    => $group->groupQuestions()->where('is_active', true)->count(),
            'user_id'  => $user->id,
        ];
    }
}
