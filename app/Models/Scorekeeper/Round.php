<?php

namespace App\Models\Scorekeeper;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    use HasFactory;

    protected $fillable = ['scored_game_id', 'round_number'];

    public function scoredGame(): BelongsTo
    {
        return $this->belongsTo(ScoredGame::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(RoundScore::class);
    }
}
