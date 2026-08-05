<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'base_url',
        'webhook_url',
        'telegram_proxy_url',
        'api_key',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'whatsapp_business_id',
        'whatsapp_api_version',
        'is_active',
        'last_check_at',
        'last_check_status',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'whatsapp_access_token' => 'encrypted',
            'is_active' => 'boolean',
            'last_check_at' => 'datetime',
        ];
    }

    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }
}
