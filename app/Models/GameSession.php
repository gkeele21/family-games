<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class GameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_type_id',
        'host_user_id',
        'name',
        'status',
        'settings',
        'invite_code',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->invite_code)) {
                $session->invite_code = strtoupper(Str::random(6));
            }
        });
    }

    public function gameType(): BelongsTo
    {
        return $this->belongsTo(GameType::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class)->orderBy('display_order');
    }

    public function sessionCards(): HasMany
    {
        return $this->hasMany(SessionCard::class)->orderBy('card_number');
    }

    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(SessionQuestion::class)->orderBy('display_order');
    }

    public function gameState(): HasOne
    {
        return $this->hasOne(GameState::class);
    }

    public function sessionPlayers(): HasMany
    {
        return $this->hasMany(SessionPlayer::class);
    }

    public function unassignedPlayers(): HasMany
    {
        return $this->hasMany(SessionPlayer::class)->whereNull('team_id');
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        // First check session settings, then fall back to game type defaults
        $settings = $this->settings ?? [];
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        $defaults = $this->gameType?->default_config ?? [];
        return $defaults[$key] ?? $default;
    }
}
