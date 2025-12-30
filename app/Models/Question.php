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
}
