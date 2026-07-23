<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'plan_id',
        'gateway',
        'gateway_subscription_id',
        'status',
        'price',
        'starts_at',
        'expires_at',
        'cancelled_at',
        'trial_ends_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active']);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->expires_at && $this->expires_at->isPast());
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['trial', 'active']);
    }

    public function recordHistory(string $action, ?array $data = null): void
    {
        $this->history()->create([
            'action' => $action,
            'data' => $data,
        ]);
    }
}
