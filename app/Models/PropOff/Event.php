<?php

namespace App\Models\PropOff;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $table = 'propoff_events';

    protected $fillable = [
        'name', 'description', 'category', 'event_type', 'event_date',
        'status', 'lock_date', 'created_by',
    ];

    protected $appends = ['is_locked'];

    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'lock_date'  => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function eventQuestions(): HasMany
    {
        return $this->hasMany(EventQuestion::class)->orderBy('display_order');
    }

    public function questions(): HasMany
    {
        return $this->eventQuestions();
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function leaderboards(): HasMany
    {
        return $this->hasMany(Leaderboard::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class);
    }

    public function eventAnswers(): HasMany
    {
        return $this->hasMany(EventAnswer::class);
    }

    public function captainInvitations(): HasMany
    {
        return $this->hasMany(CaptainInvitation::class);
    }

    public function acceptingEntries(): bool
    {
        return ! $this->lock_date || now()->lt($this->lock_date);
    }

    public function getIsLockedAttribute(): bool
    {
        return ! $this->acceptingEntries();
    }
}
