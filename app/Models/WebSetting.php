<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebSetting extends Model
{
    use HasFactory;

    protected $table = 'web_settings';

    /**
     * Institutional color presets available in the visual theme settings.
     *
     * @var array<string, array{label: string, primary: string, secondary: string, accent: string}>
     */
    public const THEME_PRESETS = [
        'emerald' => [
            'label' => 'Esmeralda',
            'primary' => '#059669',
            'secondary' => '#10B981',
            'accent' => '#34D399',
        ],
        'emerald-deep' => [
            'label' => 'Esmeralda Profunda',
            'primary' => '#047857',
            'secondary' => '#059669',
            'accent' => '#10B981',
        ],
        'graphite' => [
            'label' => 'Grafito Neutro',
            'primary' => '#334155',
            'secondary' => '#64748B',
            'accent' => '#94A3B8',
        ],
        'ocean' => [
            'label' => 'Océano',
            'primary' => '#0284C7',
            'secondary' => '#0EA5E9',
            'accent' => '#7DD3FC',
        ],
        'violet' => [
            'label' => 'Violeta',
            'primary' => '#7C3AED',
            'secondary' => '#8B5CF6',
            'accent' => '#C4B5FD',
        ],
    ];

    protected $fillable = [
        'user_id',
        'app_name',
        'app_logo',
        'app_favicon',
        'app_title',
        'app_description',
        'app_keywords',
        'app_author',
        'theme_preset',
        'primary_color',
        'secondary_color',
        'accent_color',
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
        'telegram_login_bot_name',
        'telegram_login_bot_token',
        'telegram_login_redirect_uri',
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
            'telegram_login_bot_token' => 'encrypted',
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

    /**
     * Resolve the effective institutional palette, falling back to the
     * selected preset when a custom color is missing or invalid.
     *
     * @return array{primary: string, secondary: string, accent: string}
     */
    public function themeColors(): array
    {
        $preset = self::THEME_PRESETS[$this->theme_preset] ?? self::THEME_PRESETS['emerald'];

        return [
            'primary' => $this->normalizeHexColor($this->primary_color) ?? $preset['primary'],
            'secondary' => $this->normalizeHexColor($this->secondary_color) ?? $preset['secondary'],
            'accent' => $this->normalizeHexColor($this->accent_color) ?? $preset['accent'],
        ];
    }

    /**
     * Normalize a persisted hex color, returning null when it is not valid.
     */
    private function normalizeHexColor(?string $color): ?string
    {
        if (! is_string($color)) {
            return null;
        }

        $color = trim($color);

        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) === 1
            ? strtoupper($color)
            : null;
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
