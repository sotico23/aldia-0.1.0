<?php

namespace App\Models;

use App\Contracts\HasMessageThread;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Conversation extends Model implements HasMessageThread
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'buyer_id',
        'store_profile_id',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && ! $model->owner_id) {
                $model->owner_id = Auth::user()->getOwnerId();
            }
        });
    }

    public function type(): string
    {
        return 'marketplace';
    }

    public function participants(): array
    {
        $storeOwner = $this->store?->user;

        return array_filter([$this->buyer, $storeOwner]);
    }

    public function otherParticipant(User $user): ?User
    {
        if ($this->buyer_id === $user->id) {
            return $this->store?->user;
        }

        $storeOwner = $this->store?->user;
        if ($storeOwner && $storeOwner->id === $user->id) {
            return $this->buyer;
        }

        return null;
    }

    public function markAsRead(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadCount(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function latestMessageContent(): ?string
    {
        return $this->latestMessage->first()?->body;
    }

    public function lastMessageAt(): mixed
    {
        return $this->latestMessage->first()?->created_at;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(PublicProfile::class, 'store_profile_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latest()->limit(1);
    }
}
