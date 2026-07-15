<?php

namespace App\Models\Scorekeeper;

use App\Models\GameType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id',
        'name',
        'game_type_id',
        'target_score',
        'low_score_wins',
        'max_rounds',
        'score_fields',
        'team_based',
        'allow_self_scoring',
        'is_global',
        'is_system',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'low_score_wins' => 'boolean',
            'is_system'      => 'boolean',
            'is_global'      => 'boolean',
            'team_based'     => 'boolean',
            'allow_self_scoring' => 'boolean',
            'score_fields'   => 'array',
        ];
    }

    public function gameType(): BelongsTo
    {
        return $this->belongsTo(GameType::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
