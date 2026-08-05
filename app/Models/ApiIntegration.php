<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiIntegration extends Model
{
    use BelongsToOwner, HasFactory;

    public const CATEGORY_PAYMENT = 'payment';

    public const CATEGORY_BOTS = 'bots';

    public const CATEGORY_ECOMMERCE = 'ecommerce';

    /**
     * @var array<string, string>
     */
    public const PROVIDERS = [
        'webpay' => self::CATEGORY_PAYMENT,
        'mercadopago' => self::CATEGORY_PAYMENT,
        'paypal' => self::CATEGORY_PAYMENT,
        'n8n' => self::CATEGORY_BOTS,
        'telegram' => self::CATEGORY_BOTS,
        'whatsapp' => self::CATEGORY_BOTS,
        'facebook_meta' => self::CATEGORY_BOTS,
        'shopify' => self::CATEGORY_ECOMMERCE,
        'woocommerce' => self::CATEGORY_ECOMMERCE,
        'mercado_libre' => self::CATEGORY_ECOMMERCE,
    ];

    protected $fillable = [
        'owner_id',
        'provider',
        'credentials',
        'environment',
        'is_active',
        'last_tested_at',
        'last_tested_status',
        'last_tested_message',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): string
    {
        return self::PROVIDERS[$this->provider] ?? self::CATEGORY_ECOMMERCE;
    }
}
