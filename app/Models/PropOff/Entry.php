<?php

namespace App\Models\PropOff;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    use HasFactory;

    protected $table = 'propoff_entries';

    protected $fillable = [
        'event_id', 'user_id', 'group_id', 'total_score', 'possible_points',
        'percentage', 'is_complete', 'submitted_at',
        'submitted_by_captain_id', 'submitted_by_captain_at',
    ];

    protected $attributes = [
        'total_score'     => 0,
        'possible_points' => 0,
        'percentage'      => 0,
        'is_complete'     => false,
    ];

    protected function casts(): array
    {
        return [
            'is_complete'             => 'boolean',
            'submitted_at'            => 'datetime',
            'submitted_by_captain_at' => 'datetime',
            'percentage'              => 'float',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function userAnswers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }

    public function submittedByCaptain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_captain_id');
    }

    public function wasSubmittedByCaptain(): bool
    {
        return $this->submitted_by_captain_id !== null;
    }
}
