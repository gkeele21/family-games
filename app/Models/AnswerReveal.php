<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnswerReveal extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_question_id',
        'answer_id',
        'team_id',
        'revealed_at',
        'points_awarded',
    ];

    protected $casts = [
        'revealed_at' => 'datetime',
        'points_awarded' => 'integer',
    ];

    public function sessionQuestion(): BelongsTo
    {
        return $this->belongsTo(SessionQuestion::class);
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
