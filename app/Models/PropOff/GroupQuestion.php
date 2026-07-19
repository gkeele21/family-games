<?php

namespace App\Models\PropOff;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GroupQuestion extends Model
{
    use HasFactory;

    protected $table = 'propoff_group_questions';

    protected $fillable = [
        'group_id', 'event_question_id', 'question_text', 'question_type',
        'options', 'points', 'display_order', 'is_active', 'is_custom',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_custom' => false,
        'points'    => 1,
    ];

    protected function casts(): array
    {
        return [
            'options'   => 'array',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function eventQuestion(): BelongsTo
    {
        return $this->belongsTo(EventQuestion::class);
    }

    public function userAnswers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }

    public function groupQuestionAnswer(): HasOne
    {
        return $this->hasOne(GroupQuestionAnswer::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeCustom(Builder $q): Builder
    {
        return $q->where('is_custom', true);
    }

    public function scopeStandard(Builder $q): Builder
    {
        return $q->where('is_custom', false);
    }
}
