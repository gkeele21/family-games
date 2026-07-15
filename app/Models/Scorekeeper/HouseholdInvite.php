<?php

namespace App\Models\Scorekeeper;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdInvite extends Model
{
    protected $fillable = [
        'household_id',
        'email',
        'invited_by_user_id',
        'role',
        'player_id',
        'token',
        'expires_at',
        'accepted_at',
        'accepted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /** Roster player this invite links an account to (optional). */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
