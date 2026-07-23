<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AutomationExecution extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'uuid',
        'workflow',
        'status',
        'triggered_by',
        'payload',
        'output',
        'error_message',
        'execution_time_ms',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'payload' => 'array',
            'output' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByWorkflow($query, string $workflow)
    {
        return $query->where('workflow', $workflow);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
