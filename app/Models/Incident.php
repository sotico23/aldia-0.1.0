<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'monitored_site_id',
        'started_at',
        'resolved_at',
        'status',
        'cause',
        'details',
        'notified',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'notified' => 'boolean',
        ];
    }

    public function monitoredSite(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class);
    }

    public function getDurationMinutes(): int
    {
        if (! $this->resolved_at) {
            return now()->diffInMinutes($this->started_at);
        }

        return $this->resolved_at->diffInMinutes($this->started_at);
    }

    public function isOngoing(): bool
    {
        return $this->status === 'down' && $this->resolved_at === null;
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'down')->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }
}
