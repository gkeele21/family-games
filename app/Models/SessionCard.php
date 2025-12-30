<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'card_number',
        'letter',
        'status',
    ];

    protected $casts = [
        'card_number' => 'integer',
    ];

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(SessionQuestion::class)->orderBy('display_order');
    }
}
