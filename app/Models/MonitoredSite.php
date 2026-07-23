<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredSite extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'user_id',
        'name',
        'url',
        'method',
        'expected_status',
        'expected_string',
        'type',
        'check_interval',
        'timeout',
        'response_time_threshold',
        'is_active',
        'ssl_expiry_check',
        'ssl_days_before_expiry',
        'last_check_at',
        'last_status',
        'last_response_time',
        'last_down_at',
        'last_recovered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ssl_expiry_check' => 'boolean',
            'last_check_at' => 'datetime',
            'last_down_at' => 'datetime',
            'last_recovered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(UptimeCheck::class)->orderBy('checked_at', 'desc');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class)->orderBy('started_at', 'desc');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(UptimeAlert::class);
    }

    public function latestCheck(): ?UptimeCheck
    {
        return $this->checks()->latest('checked_at')->first();
    }

    public function getUptimePercentage(int $days = 30): float
    {
        $startDate = now()->subDays($days);
        $totalMinutes = $days * 24 * 60;
        $downMinutes = $this->incidents()
            ->where('started_at', '>=', $startDate)
            ->get()
            ->sum(function ($incident) {
                $end = $incident->resolved_at ?? now();

                return $end->diffInMinutes($incident->started_at);
            });

        if ($totalMinutes === 0) {
            return 100.0;
        }

        return round((($totalMinutes - $downMinutes) / $totalMinutes) * 100, 2);
    }

    public function getAverageResponseTime(int $days = 7): int
    {
        return (int) $this->checks()
            ->where('checked_at', '>=', now()->subDays($days))
            ->where('status', 'up')
            ->avg('response_time_ms') ?? 0;
    }

    public function isDown(): bool
    {
        return $this->last_status === 'down';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNeedsCheck($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_check_at')
                ->orWhere('last_check_at', '<=', now()->subMinutes(5));
        })->where('is_active', true);
    }
}
