<?php

namespace App\Models\Scorekeeper;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundScore extends Model
{
    protected $fillable = ['round_id', 'competitor_id', 'values'];

    protected function casts(): array
    {
        return [
            'values' => 'array',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(ScoredGameCompetitor::class, 'competitor_id');
    }
}
