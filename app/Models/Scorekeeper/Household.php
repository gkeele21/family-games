<?php

namespace App\Models\Scorekeeper;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'owner_user_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(HouseholdInvite::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /**
     * The user's role in this household (owner/member/guest), or null.
     */
    public function roleOf(User $user): ?string
    {
        return $this->members()
            ->where('users.id', $user->id)
            ->first()?->pivot->role;
    }

    /**
     * Scorers (owner/member) run games. Guests view — and, when a game allows
     * self-scoring, enter their own scores only.
     */
    public function isScorer(User $user): bool
    {
        return in_array($this->roleOf($user), ['owner', 'member'], true);
    }

    /**
     * Ensure a member has a roster player linked to their account, so members
     * appear as selectable players. Idempotent (keyed on user_id).
     */
    public function ensureRosterPlayer(User $user): Player
    {
        return $this->players()->firstOrCreate(
            ['user_id' => $user->id],
            ['name' => $user->name],
        );
    }

    public function scoredGames(): HasMany
    {
        return $this->hasMany(ScoredGame::class);
    }
}
