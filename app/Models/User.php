<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\Player;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_friends', 'user_id', 'friend_id')
            ->withPivot('nickname')
            ->withTimestamps();
    }

    public function friendships(): HasMany
    {
        return $this->hasMany(UserFriend::class);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class, 'host_user_id');
    }

    /**
     * Scorekeeper households this user belongs to (with their role).
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Whether this user may attach the given account to a roster player:
     * only accounts they can already see — a linked player in one of their
     * households, or a friend. Never an arbitrary account.
     */
    public function canLinkRosterAccount(int $userId): bool
    {
        return $this->friends()->where('users.id', $userId)->exists()
            || Player::whereIn(
                'household_id',
                $this->households()->pluck('households.id'),
            )
                ->where('user_id', $userId)
                ->exists();
    }

    /**
     * Returns the user's first household; creates a default one if they have
     * none. Called on first authenticated load so the scorekeeper is usable.
     */
    public function ensureDefaultHousehold(): Household
    {
        $existing = $this->households()->first();
        if ($existing) {
            return $existing;
        }

        $household = Household::create([
            'name' => trim($this->first_name) !== ''
                ? "{$this->first_name}'s Household"
                : 'My Household',
            'owner_user_id' => $this->id,
        ]);

        $household->members()->attach($this->id, ['role' => 'owner']);
        $household->ensureRosterPlayer($this);

        return $household;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'guest_token',
        'avatar',
    ];

    /**
     * Global role tier (PropOff): manager > admin > user > guest. Distinct
     * from per-household roles, which live on the household pivot.
     */
    public function isGuest(): bool
    {
        return $this->role === 'guest';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function hasAdminAccess(): bool
    {
        return in_array($this->role, ['admin', 'manager'], true);
    }

    /**
     * Split a single entered name into first/last the same way everywhere
     * (PropOff join flows take one name field).
     */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [
            'first_name' => $parts[0] ?? '',
            'last_name'  => $parts[1] ?? '',
        ];
    }

    // ---- PropOff module -----------------------------------------------

    public function propoffGroups(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\PropOff\Group::class, 'propoff_group_user', 'user_id', 'group_id')
            ->withPivot('joined_at', 'is_captain')
            ->withTimestamps();
    }

    public function captainGroups(): BelongsToMany
    {
        return $this->propoffGroups()->wherePivot('is_captain', true);
    }

    /** Source-app alias for captainGroups(). */
    public function captainOf(): BelongsToMany
    {
        return $this->captainGroups();
    }

    public function isCaptainOf(\App\Models\PropOff\Group|int $group): bool
    {
        $groupId = $group instanceof \App\Models\PropOff\Group ? $group->id : $group;

        return $this->captainGroups()->where('propoff_groups.id', $groupId)->exists();
    }

    public function isCaptain(): bool
    {
        return $this->captainGroups()->exists();
    }

    public function propoffEntries(): HasMany
    {
        return $this->hasMany(\App\Models\PropOff\Entry::class, 'user_id');
    }

    public function canEditEntry(\App\Models\PropOff\Entry $entry): bool
    {
        // Must own the entry, the group must still accept entries, and the
        // event must not be completed (source-app semantics).
        if ($this->id !== $entry->user_id) {
            return false;
        }
        if (! $entry->group->acceptingEntries()) {
            return false;
        }
        if ($entry->event->status === 'completed') {
            return false;
        }

        return true;
    }

    public function createdGroups(): HasMany
    {
        return $this->hasMany(\App\Models\PropOff\Group::class, 'created_by');
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(\App\Models\PropOff\Event::class, 'created_by');
    }

    public function createdQuestionTemplates(): HasMany
    {
        return $this->hasMany(\App\Models\PropOff\QuestionTemplate::class, 'created_by');
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * Get the user's full name.
     */
    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Writing a single name transparently splits it — PropOff flows (and its
     * tests) address users by one name field.
     */
    public function setNameAttribute(string $value): void
    {
        $split = self::splitName($value);
        $this->attributes['first_name'] = $split['first_name'];
        $this->attributes['last_name'] = $split['last_name'];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'guest_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
