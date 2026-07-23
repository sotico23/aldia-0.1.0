<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UptimeAlert extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $table = 'uptime_alerts';

    protected $fillable = [
        'user_id',
        'monitored_site_id',
        'type',
        'email_enabled',
        'webhook_enabled',
        'webhook_url',
        'webhook_secret',
        'is_active',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'webhook_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected $hidden = [
        'webhook_secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monitoredSite(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEmail($query)
    {
        return $query->where('email_enabled', true);
    }

    public function scopeForWebhook($query)
    {
        return $query->where('webhook_enabled', true);
    }
}
