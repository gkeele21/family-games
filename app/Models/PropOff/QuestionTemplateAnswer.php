<?php

namespace App\Models\PropOff;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTemplateAnswer extends Model
{
    protected $table = 'propoff_question_template_answers';

    protected $fillable = ['question_template_id', 'answer_text', 'display_order'];

    public function questionTemplate(): BelongsTo
    {
        return $this->belongsTo(QuestionTemplate::class, 'question_template_id');
    }
}
