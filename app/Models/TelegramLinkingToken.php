<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TelegramLinkingToken extends Model
{
    use BelongsToOwner;
    use HasFactory;

    /**
     * Generate a sanitized linking token: only letters, numbers and underscores, max 64 chars.
     */
    public static function generateToken(): string
    {
        return Str::random(32);
    }

    protected $fillable = [
        'owner_id',
        'user_id',
        'token',
        'telegram_chat_id',
        'bot_type',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
