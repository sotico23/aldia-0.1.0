<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationConfig extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'channel',
        'frequency',
        'execution_time',
        'enabled',
        'selected_reports',
        'last_run_at',
        'next_run_at',
        'last_run_status',
        'n8n_webhook_url',
        'n8n_token',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'selected_reports' => 'array',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
