<?php

namespace App\Models\PropOff;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionTemplate extends Model
{
    use HasFactory;

    protected $table = 'propoff_question_templates';

    protected $fillable = [
        'title', 'question_text', 'question_type', 'category', 'default_points',
        'variables', 'default_options', 'is_favorite', 'display_order', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables'       => 'array',
            'default_options' => 'array',
            'is_favorite'     => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function templateAnswers(): HasMany
    {
        return $this->hasMany(QuestionTemplateAnswer::class, 'question_template_id')
            ->orderBy('display_order');
    }

    public function eventQuestions(): HasMany
    {
        return $this->hasMany(EventQuestion::class, 'template_id');
    }

    /** Category column is a comma-separated list. */
    public function getCategoriesAttribute(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->category))));
    }

    public function hasCategory(string $search): bool
    {
        return in_array(
            mb_strtolower(trim($search)),
            array_map('mb_strtolower', $this->categories),
            true,
        );
    }

    public function scopeWithCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', 'like', "%{$category}%");
    }
}
