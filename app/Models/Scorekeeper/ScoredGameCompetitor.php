<?php

namespace App\Models\Scorekeeper;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoredGameCompetitor extends Model
{
    use HasFactory;

    protected $table = 'scored_game_competitors';

    protected $fillable = ['scored_game_id', 'name', 'display_order'];

    public function scoredGame(): BelongsTo
    {
        return $this->belongsTo(ScoredGame::class);
    }

    /**
     * Roster players making up this competitor — one for an individual,
     * many for a team.
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(
            Player::class,
            'competitor_player',
            'competitor_id',
            'player_id',
        );
    }

    public function roundScores(): HasMany
    {
        return $this->hasMany(RoundScore::class, 'competitor_id');
    }
}
