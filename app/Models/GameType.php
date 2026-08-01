<?php

namespace App\Models;

use App\Models\Scorekeeper\Household;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'display_order',
        'description',
        'default_config',
        'kind',
        'household_id',
        'is_global',
    ];

    protected $casts = [
        'default_config' => 'array',
        'is_global'      => 'boolean',
    ];

    /** Owning household for a custom scorekeeper game; null for system/global. */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('kind', 'online')->orderBy('display_order')->orderBy('id');
    }

    public function scopeScorekeeper(Builder $query): Builder
    {
        return $query->where('kind', 'scorekeeper');
    }

    /**
     * System/global games (null household), games shared globally, or the
     * given household's own games.
     */
    public function scopeVisibleTo(Builder $query, int $householdId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('household_id')
            ->orWhere('is_global', true)
            ->orWhere('household_id', $householdId));
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }
}
