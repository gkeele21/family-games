<?php

namespace App\Models\PropOff;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CaptainInvitation extends Model
{
    use HasFactory;

    protected $table = 'propoff_captain_invitations';

    protected $fillable = [
        'event_id', 'token', 'max_uses', 'times_used', 'expires_at',
        'is_active', 'created_by',
    ];

    protected $attributes = [
        'is_active'  => true,
        'times_used' => 0,
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active'  => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateToken(): string
    {
        return Str::random(32);
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }
        if ($this->max_uses !== null && $this->times_used >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function canBeUsed(): bool
    {
        return $this->isValid();
    }

    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }

    public function getUrl(): string
    {
        return route('propoff.captain.join', $this->token);
    }

    public function getUrlAttribute(): string
    {
        return $this->getUrl();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeValid(Builder $q): Builder
    {
        return $q->active()
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($w) => $w->whereNull('max_uses')->orWhereColumn('times_used', '<', 'max_uses'));
    }
}
