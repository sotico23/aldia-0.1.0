<?php

namespace App\Models;

use App\Scopes\OwnerScope;
use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentConfig extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'commerce_code', 'api_key', 'environment', 'is_active',
        'paypal_client_id', 'paypal_client_secret', 'paypal_mode', 'paypal_active',
        'paypal_webhook_id',
        'mercadopago_public_key', 'mercadopago_access_token', 'mercadopago_mode', 'mercadopago_active',
        'mercadopago_webhook_secret',
        'use_platform_config',
        'commission_rate', 'commission_type',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'paypal_client_secret' => 'encrypted',
            'paypal_webhook_id' => 'encrypted',
            'mercadopago_access_token' => 'encrypted',
            'mercadopago_webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
            'paypal_active' => 'boolean',
            'mercadopago_active' => 'boolean',
            'use_platform_config' => 'boolean',
            'commission_rate' => 'decimal:2',
        ];
    }

    public function hasAnyActiveMethod(): bool
    {
        return $this->is_active
            || $this->paypal_active
            || $this->mercadopago_active;
    }

    public static function resolveForOwner(int $userId): ?self
    {
        $user = User::withoutGlobalScope(OwnerScope::class)->find($userId);

        if (! $user) {
            return null;
        }

        $ownerId = $user->getOwnerId();

        $config = self::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->first();

        // Tenant has own config with active methods and doesn't want platform config
        if ($config && $config->hasAnyActiveMethod() && ! $config->use_platform_config) {
            return $config;
        }

        // Tenant explicitly wants to use platform (master) config
        if ($config && $config->use_platform_config) {
            $master = User::withoutGlobalScope(OwnerScope::class)
                ->role('Master')
                ->first();
            if ($master) {
                return self::withoutGlobalScope(OwnerScope::class)
                    ->where('owner_id', $master->id)
                    ->first();
            }
        }

        return null;
    }
}
