<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scorekeeper\Household;
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
        'first_name',
        'last_name',
        'email',
        'password',
    ];

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
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
