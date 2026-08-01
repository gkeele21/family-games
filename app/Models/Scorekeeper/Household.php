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

    /**
     * Add-player suggestions: people from the requesting user's OTHER
     * households, then their friends — never a system-wide user search.
     * Deduped against this household's roster (by linked account, else name).
     */
    public function playerSuggestionsFor(User $user): array
    {
        $rosterNames = $this->players()->pluck('name')
            ->map(fn ($n) => mb_strtolower($n));
        $rosterUserIds = $this->players()->whereNotNull('user_id')->pluck('user_id');

        $suggestions = [];
        $seen = [];
        $add = function (string $name, ?int $userId, string $source) use (
            &$suggestions, &$seen, $rosterNames, $rosterUserIds
        ) {
            $key = $userId ? "u{$userId}" : 'n' . mb_strtolower($name);
            if (isset($seen[$key]) || isset($seen['n' . mb_strtolower($name)])) {
                return;
            }
            if ($rosterNames->contains(mb_strtolower($name))) {
                return;
            }
            if ($userId && $rosterUserIds->contains($userId)) {
                return;
            }
            $seen[$key] = true;
            $suggestions[] = ['name' => $name, 'user_id' => $userId, 'source' => $source];
        };

        Player::query()
            ->whereIn('household_id', $user->households()->pluck('households.id'))
            ->where('household_id', '!=', $this->id)
            ->where('is_guest', false)
            ->with('household:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'user_id', 'household_id'])
            ->each(fn ($p) => $add($p->name, $p->user_id, $p->household->name));

        $user->friends()->orderBy('first_name')->get()
            ->each(fn ($f) => $add($f->name, $f->id, 'Friend'));

        return $suggestions;
    }
}
