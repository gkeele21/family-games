<?php

namespace App\Models\PropOff;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leaderboard extends Model
{
    use HasFactory;

    protected $table = 'propoff_leaderboards';

    protected $fillable = [
        'event_id', 'group_id', 'user_id', 'rank', 'total_score',
        'possible_points', 'percentage', 'answered_count',
    ];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:2'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
