<?php

namespace App\Models\PropOff;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventQuestion extends Model
{
    use HasFactory;

    protected $table = 'propoff_event_questions';

    protected $fillable = [
        'event_id', 'template_id', 'question_text', 'question_type',
        'options', 'points', 'display_order',
    ];

    protected $appends = ['type', 'order_number'];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuestionTemplate::class, 'template_id');
    }

    public function groupQuestions(): HasMany
    {
        return $this->hasMany(GroupQuestion::class);
    }

    public function eventAnswer(): HasOne
    {
        return $this->hasOne(EventAnswer::class);
    }

    public function eventAnswers(): HasMany
    {
        return $this->hasMany(EventAnswer::class)->orderBy('display_order');
    }

    public function getTypeAttribute(): string
    {
        return $this->question_type;
    }

    public function getOrderNumberAttribute(): int
    {
        return (int) $this->display_order;
    }
}
