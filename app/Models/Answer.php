<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'answer_text',
        'points',
        'display_order',
        'times_revealed',
    ];

    protected $casts = [
        'points' => 'integer',
        'display_order' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function reveals(): HasMany
    {
        return $this->hasMany(AnswerReveal::class);
    }

    /**
     * Record that this answer was revealed/guessed
     */
    public function recordReveal(): void
    {
        $this->increment('times_revealed');
    }

    /**
     * Get the reveal percentage (how often this answer is guessed out of all times the question was used)
     */
    public function getRevealPercentageAttribute(): ?float
    {
        $questionTimesUsed = $this->question->times_used;
        if ($questionTimesUsed === 0) {
            return null;
        }
        return round(($this->times_revealed / $questionTimesUsed) * 100, 1);
    }
}
