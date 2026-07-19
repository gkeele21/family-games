<?php

namespace App\Models\PropOff;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupQuestionAnswer extends Model
{
    use HasFactory;

    protected $table = 'propoff_group_question_answers';

    protected $fillable = [
        'group_id', 'group_question_id', 'correct_answer', 'points_awarded', 'is_void',
    ];

    protected function casts(): array
    {
        return ['is_void' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function groupQuestion(): BelongsTo
    {
        return $this->belongsTo(GroupQuestion::class);
    }
}
