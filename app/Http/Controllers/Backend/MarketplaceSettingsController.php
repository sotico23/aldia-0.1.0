<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\WebSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MarketplaceSettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.web-settings.edit'),
        ];
    }

    public function show(): JsonResponse
    {
        $settings = WebSetting::getSettings();

        return response()->json([
            'data' => [
                'commission_type' => $settings->marketplace_commission_type ?? 'percentage',
                'commission_rate' => (float) ($settings->marketplace_commission_rate ?? 0),
                'fixed_amount' => (float) ($settings->marketplace_fixed_amount ?? 0),
                'min_commission' => $settings->min_commission ? (float) $settings->min_commission : null,
                'max_commission' => $settings->max_commission ? (float) $settings->max_commission : null,
                'min_withdrawal_amount' => (float) ($settings->min_withdrawal_amount ?? 0),
                'split_payment_active' => (bool) ($settings->split_payment_active ?? false),
                'split_payment_gateway' => $settings->split_payment_gateway ?? 'mercadopago',
                'auto_hold_commission' => (bool) ($settings->auto_hold_commission ?? true),
                'fund_release_period' => $settings->fund_release_period ?? 'immediate',
                'refund_policy' => $settings->refund_policy ?? 'platform_absorbs',
                'partial_refunds_allowed' => (bool) ($settings->partial_refunds_allowed ?? true),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commission_type' => 'required|string|in:percentage,fixed,hybrid',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'min_commission' => 'nullable|numeric|min:0',
            'max_commission' => 'nullable|numeric|min:0',
            'min_withdrawal_amount' => 'nullable|numeric|min:0',
            'split_payment_active' => 'boolean',
            'split_payment_gateway' => 'nullable|string|in:paypal,mercadopago',
            'auto_hold_commission' => 'boolean',
            'fund_release_period' => 'required|string|in:immediate,24_hours,7_days,15_days,30_days',
            'refund_policy' => 'required|string|in:platform_absorbs,business_absorbs',
            'partial_refunds_allowed' => 'boolean',
        ]);

        $settings = WebSetting::getSettings();

        $settings->update([
            'marketplace_commission_type' => $validated['commission_type'],
            'marketplace_commission_rate' => $validated['commission_rate'] ?? 0,
            'marketplace_fixed_amount' => $validated['fixed_amount'] ?? 0,
            'min_commission' => $validated['min_commission'] ?? null,
            'max_commission' => $validated['max_commission'] ?? null,
            'min_withdrawal_amount' => $validated['min_withdrawal_amount'] ?? 0,
            'split_payment_active' => $validated['split_payment_active'] ?? false,
            'split_payment_gateway' => $validated['split_payment_gateway'] ?? 'mercadopago',
            'auto_hold_commission' => $validated['auto_hold_commission'] ?? true,
            'fund_release_period' => $validated['fund_release_period'],
            'refund_policy' => $validated['refund_policy'],
            'partial_refunds_allowed' => $validated['partial_refunds_allowed'] ?? true,
        ]);

        WebSetting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Configuración de Marketplace guardada correctamente.',
        ]);
    }
}
