<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailConfigLog extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'mail_config_id',
        'owner_id',
        'status',
        'test_email',
        'error_message',
        'response_time',
    ];

    public function mailConfig(): BelongsTo
    {
        return $this->belongsTo(MailConfig::class);
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'success' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'timeout' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    public function getStatusIcon(): string
    {
        return match ($this->status) {
            'success' => '✅',
            'failed' => '❌',
            'timeout' => '⏱️',
            default => '❓',
        };
    }

    public function getFormattedTime(): string
    {
        if (! $this->response_time) {
            return '-';
        }

        return "{$this->response_time}ms";
    }
}
