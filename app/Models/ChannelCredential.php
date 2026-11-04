<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelCredential extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'telegram_bot_token',
        'telegram_bot_username',
        'telegram_bot_id',
        'telegram_chat_id',
        'telegram_linked_at',
        'telegram_mode',
        'telegram_webhook_secret',
        'telegram_webhook_key',
        'bot_type',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'whatsapp_business_id',
        'whatsapp_api_version',
        'whatsapp_mode',
        'whatsapp_wa_id',
        'whatsapp_linked_at',
        'whatsapp_verify_token',
        'whatsapp_app_secret',
        'whatsapp_webhook_key',
        'n8n_base_url',
        'n8n_telegram_proxy_webhook_url',
        'n8n_api_key',
    ];

    protected function casts(): array
    {
        return [
            'telegram_bot_token' => 'encrypted',
            'telegram_webhook_secret' => 'encrypted',
            'whatsapp_access_token' => 'encrypted',
            'whatsapp_verify_token' => 'encrypted',
            'whatsapp_app_secret' => 'encrypted',
            'n8n_api_key' => 'encrypted',
            'telegram_linked_at' => 'datetime',
            'whatsapp_linked_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ChannelLink::class, 'owner_id', 'owner_id');
    }
}
