<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UptimeCheck extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $fillable = [
        'monitored_site_id',
        'checked_at',
        'status',
        'response_time_ms',
        'http_status_code',
        'response_body',
        'error_message',
        'dns_time_ms',
        'connect_time_ms',
        'ssl_expiry_days',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function monitoredSite(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('checked_at', '>=', now()->subDays($days));
    }

    public function scopeFailed($query)
    {
        return $query->where('status', '!=', 'up');
    }
}
