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

    /**
     * Round-start score snapshots (America Says). Captures each team's cumulative
     * score at the moment a round begins — before any of that round's scoring — so
     * a Reset Round restores exactly that, no matter what was revealed, auto-swept,
     * or hand-edited during the round. First write per round wins; later boards of
     * the same round are no-ops. Cleared at game end (see clearRoundScores).
     */
    public function snapshotRoundScoresIfAbsent(int $round, array $teamScores): void
    {
        $all = $this->getStateValue('round_scores', []);
        $key = (string) $round;
        if (array_key_exists($key, $all)) {
            return;
        }
        $all[$key] = $teamScores; // [teamId => score]
        $this->setStateValue('round_scores', $all);
    }

    /** The snapshotted [teamId => score] for a round's start, or null if none. */
    public function roundStartScores(int $round): ?array
    {
        $all = $this->getStateValue('round_scores', []);
        return $all[(string) $round] ?? null;
    }

    /** Drop all round-start snapshots — called at game end to free the row. */
    public function clearRoundScores(): void
    {
        $this->setStateValue('round_scores', null);
    }

    public function getRemainingSeconds(): ?int
    {
        if (!$this->timer_started_at) {
            return $this->timer_duration;
        }

        // Use Unix timestamps for reliable calculation (avoids timezone issues)
        $elapsed = now()->timestamp - $this->timer_started_at->timestamp;
        $remaining = $this->timer_duration - $elapsed;

        // Clamp to the duration: the clock is started ~1s in the future (a grace
        // beat for casting latency), so before it "starts" elapsed is negative and
        // remaining would exceed the duration — don't let that inflate banked time.
        return max(0, min((int) $this->timer_duration, (int) $remaining));
    }
}
