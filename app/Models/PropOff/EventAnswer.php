<?php

namespace App\Models\PropOff;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAnswer extends Model
{
    protected $table = 'propoff_event_answers';

    protected $fillable = [
        'event_id', 'event_question_id', 'correct_answer', 'display_order',
        'is_void', 'set_at', 'set_by',
    ];

    protected function casts(): array
    {
        return [
            'is_void' => 'boolean',
            'set_at'  => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventQuestion(): BelongsTo
    {
        return $this->belongsTo(EventQuestion::class);
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
