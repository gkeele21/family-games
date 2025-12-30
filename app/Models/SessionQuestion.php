<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'session_card_id',
        'question_id',
        'display_order',
        'status',
        'controlling_team_id',
        'controlling_team_ids',
        'control_status',
        'points_available',
        'played_at',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'points_available' => 'integer',
        'played_at' => 'datetime',
        'controlling_team_ids' => 'array',
    ];

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function sessionCard(): BelongsTo
    {
        return $this->belongsTo(SessionCard::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function controllingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'controlling_team_id');
    }

    public function answerReveals(): HasMany
    {
        return $this->hasMany(AnswerReveal::class);
    }

    public function revealedAnswerIds(): array
    {
        return $this->answerReveals()->pluck('answer_id')->toArray();
    }

    /**
     * Get the IDs of all controlling teams (supports both single and multiple).
     */
    public function getControllingTeamIdsArray(): array
    {
        // If we have multiple controlling teams, use that
        if (!empty($this->controlling_team_ids)) {
            return $this->controlling_team_ids;
        }

        // Fall back to single controlling team
        if ($this->controlling_team_id) {
            return [$this->controlling_team_id];
        }

        return [];
    }

    /**
     * Check if a specific team has control.
     */
    public function teamHasControl(int $teamId): bool
    {
        return in_array($teamId, $this->getControllingTeamIdsArray());
    }

    /**
     * Set the controlling teams (can be one or multiple).
     */
    public function setControllingTeams(array $teamIds): void
    {
        if (count($teamIds) === 1) {
            // Single team - use the original column for backward compatibility
            $this->controlling_team_id = $teamIds[0];
            $this->controlling_team_ids = null;
        } else {
            // Multiple teams
            $this->controlling_team_id = null;
            $this->controlling_team_ids = $teamIds;
        }
        $this->save();
    }
}
