<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_type_id',
        'category_id',
        'question_text',
        'difficulty',
        'answer_letter',
        'is_fast_money',
        'metadata',
        'created_by',
        'is_active',
        'times_used',
        'times_correct',
        'times_wrong',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_fast_money' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function gameType(): BelongsTo
    {
        return $this->belongsTo(GameType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class)->orderBy('display_order');
    }

    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(SessionQuestion::class);
    }

    /**
     * Increment the times_used counter
     */
    public function incrementUsed(): void
    {
        $this->increment('times_used');
    }

    /**
     * Record a correct answer
     */
    public function recordCorrect(): void
    {
        $this->increment('times_correct');
    }

    /**
     * Record a wrong answer (went to All Play)
     */
    public function recordWrong(): void
    {
        $this->increment('times_wrong');
    }

    /**
     * Get the correct answer percentage
     */
    public function getCorrectPercentageAttribute(): ?float
    {
        $total = $this->times_correct + $this->times_wrong;
        if ($total === 0) {
            return null;
        }
        return round(($this->times_correct / $total) * 100, 1);
    }
}
