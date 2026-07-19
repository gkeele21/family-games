<?php

namespace App\Models\PropOff;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAnswer extends Model
{
    use HasFactory;

    protected $table = 'propoff_user_answers';

    protected $fillable = [
        'entry_id', 'group_question_id', 'answer_text', 'points_earned', 'is_correct',
    ];

    protected $attributes = [
        'points_earned' => 0,
        'is_correct'    => false,
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function groupQuestion(): BelongsTo
    {
        return $this->belongsTo(GroupQuestion::class);
    }
}
