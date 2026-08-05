<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebSetting extends Model
{
    use HasFactory;

    protected $table = 'web_settings';

    protected $fillable = [
        'user_id',
        'app_name',
        'app_logo',
        'app_favicon',
        'app_title',
        'app_description',
        'app_keywords',
        'app_author',
        'timezone',
        'locale',
        'currency',
        'currency_symbol',
        'maintenance_mode',
        'operation_mode',
        'default_currency',
        'allowed_currencies',
        'default_vat',
        'auto_tax',
        'financial_email',
        'billing_email',
        'subscriptions_active',
        'trial_days',
        'grace_days',
        'auto_upgrade',
        'downgrade_allowed',
        'cancel_non_payment',
        'auto_renewal',
        'invoice_prefix',
        'invoice_start_number',
        'auto_invoicing',
        'auto_send_invoices',
        'auto_reminders',
        'marketplace_commission_type',
        'marketplace_commission_rate',
        'marketplace_fixed_amount',
        'min_commission',
        'max_commission',
        'min_withdrawal_amount',
        'split_payment_active',
        'split_payment_gateway',
        'auto_hold_commission',
        'fund_release_period',
        'refund_policy',
        'partial_refunds_allowed',
        'financial_automations',
        'hero_titulo',
        'hero_subtitulo',
        'hero_boton_principal',
        'hero_boton_secundario',
        'hero_badge',
        'caracteristicas',
        'planes',
        'cta_titulo',
        'cta_descripcion',
        'cta_boton',
        'nav_quienes_somos_visible',
        'nav_quienes_somos_label',
        'nav_quienes_somos_content',
        'nav_quienes_somos_image',
        'nav_quienes_somos_subtitle',
        'nav_feedback_visible',
        'nav_feedback_label',
        'nav_feedback_content',
        'nav_feedback_image',
        'nav_feedback_subtitle',
        'nav_fundacion_visible',
        'nav_fundacion_label',
        'nav_fundacion_content',
        'nav_fundacion_image',
        'nav_fundacion_subtitle',
        'nav_extra',
        'nav_app_brand_visible',
        'google_client_id',
        'google_client_secret',
        'google_redirect_uri',
        'facebook_client_id',
        'facebook_client_secret',
        'facebook_redirect_uri',
        'global_telegram_bot_username',
        'global_telegram_bot_token',
        'whatsapp_webhook_url',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'whatsapp_business_id',
        'whatsapp_api_version',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
            'nav_quienes_somos_visible' => 'boolean',
            'nav_feedback_visible' => 'boolean',
            'nav_fundacion_visible' => 'boolean',
            'nav_app_brand_visible' => 'boolean',
            'caracteristicas' => 'array',
            'planes' => 'array',
            'nav_extra' => 'array',
            'google_client_secret' => 'encrypted',
            'facebook_client_secret' => 'encrypted',
            'operation_mode' => 'string',
            'default_currency' => 'string',
            'allowed_currencies' => 'array',
            'default_vat' => 'decimal:2',
            'auto_tax' => 'boolean',
            'subscriptions_active' => 'boolean',
            'trial_days' => 'integer',
            'grace_days' => 'integer',
            'auto_upgrade' => 'boolean',
            'downgrade_allowed' => 'boolean',
            'cancel_non_payment' => 'boolean',
            'auto_renewal' => 'boolean',
            'auto_invoicing' => 'boolean',
            'auto_send_invoices' => 'boolean',
            'auto_reminders' => 'boolean',
            'marketplace_commission_rate' => 'decimal:2',
            'marketplace_fixed_amount' => 'decimal:2',
            'min_commission' => 'decimal:2',
            'max_commission' => 'decimal:2',
            'min_withdrawal_amount' => 'decimal:2',
            'split_payment_active' => 'boolean',
            'auto_hold_commission' => 'boolean',
            'partial_refunds_allowed' => 'boolean',
            'financial_automations' => 'array',
        ];
    }

    public static function getSettings(bool $forPublicPage = false): self
    {
        $cacheKey = 'web_settings:latest';

        return cache()->rememberForever($cacheKey, function () {
            $settings = self::orderBy('id', 'desc')->first();

            if ($settings) {
                return $settings;
            }

            return self::create([
                'user_id' => null,
                'app_name' => 'GrowERP',
                'app_title' => 'GrowERP - Tu ERP todo-en-uno',
            ]);
        });
    }

    public static function clearCache(): void
    {
        cache()->forget('web_settings:latest');
        cache()->forget('web_settings:shared');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updated(function (self $settings) {
            self::clearCache();
        });

        static::created(function (self $settings) {
            self::clearCache();
        });
    }
}
