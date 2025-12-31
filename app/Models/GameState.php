<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameState extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'current_question_id',
        'current_card_id',
        'active_team_id',
        'round_number',
        'timer_started_at',
        'timer_duration',
        'state_data',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'timer_duration' => 'integer',
        'timer_started_at' => 'datetime',
        'state_data' => 'array',
    ];

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(SessionQuestion::class, 'current_question_id');
    }

    public function currentCard(): BelongsTo
    {
        return $this->belongsTo(SessionCard::class, 'current_card_id');
    }

    public function activeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'active_team_id');
    }

    public function getStateValue(string $key, mixed $default = null): mixed
    {
        return $this->state_data[$key] ?? $default;
    }

    public function setStateValue(string $key, mixed $value): void
    {
        $data = $this->state_data ?? [];
        $data[$key] = $value;
        $this->state_data = $data;
        $this->save();
    }

    public function getRemainingSeconds(): ?int
    {
        if (!$this->timer_started_at) {
            return $this->timer_duration;
        }

        // Use Unix timestamps for reliable calculation (avoids timezone issues)
        $elapsed = now()->timestamp - $this->timer_started_at->timestamp;
        $remaining = $this->timer_duration - $elapsed;

        return max(0, (int) $remaining);
    }
}
