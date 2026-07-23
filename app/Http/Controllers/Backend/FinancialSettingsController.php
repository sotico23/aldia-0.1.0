<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\WebSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FinancialSettingsController extends Controller implements HasMiddleware
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
                'operation_mode' => $settings->operation_mode ?? 'saas',
                'default_currency' => $settings->default_currency ?? 'PEN',
                'allowed_currencies' => $settings->allowed_currencies ?? ['PEN', 'USD'],
                'default_vat' => (float) ($settings->default_vat ?? 0),
                'auto_tax' => (bool) ($settings->auto_tax ?? false),
                'financial_email' => $settings->financial_email ?? '',
                'billing_email' => $settings->billing_email ?? '',
                'subscriptions_active' => (bool) ($settings->subscriptions_active ?? false),
                'trial_days' => (int) ($settings->trial_days ?? 0),
                'grace_days' => (int) ($settings->grace_days ?? 0),
                'auto_upgrade' => (bool) ($settings->auto_upgrade ?? false),
                'downgrade_allowed' => (bool) ($settings->downgrade_allowed ?? false),
                'cancel_non_payment' => (bool) ($settings->cancel_non_payment ?? false),
                'auto_renewal' => (bool) ($settings->auto_renewal ?? false),
                'invoice_prefix' => $settings->invoice_prefix ?? 'FAC-',
                'invoice_start_number' => (int) ($settings->invoice_start_number ?? 1),
                'auto_invoicing' => (bool) ($settings->auto_invoicing ?? false),
                'auto_send_invoices' => (bool) ($settings->auto_send_invoices ?? false),
                'auto_reminders' => (bool) ($settings->auto_reminders ?? false),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'operation_mode' => 'required|string|in:saas,marketplace,both',
            'default_currency' => 'required|string|max:10',
            'allowed_currencies' => 'nullable|array',
            'allowed_currencies.*' => 'string|max:10',
            'default_vat' => 'nullable|numeric|min:0|max:100',
            'auto_tax' => 'boolean',
            'financial_email' => 'nullable|email|max:255',
            'billing_email' => 'nullable|email|max:255',
            'subscriptions_active' => 'boolean',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'grace_days' => 'nullable|integer|min:0|max:365',
            'auto_upgrade' => 'boolean',
            'downgrade_allowed' => 'boolean',
            'cancel_non_payment' => 'boolean',
            'auto_renewal' => 'boolean',
            'invoice_prefix' => 'nullable|string|max:20',
            'invoice_start_number' => 'nullable|integer|min:1',
            'auto_invoicing' => 'boolean',
            'auto_send_invoices' => 'boolean',
            'auto_reminders' => 'boolean',
        ]);

        $settings = WebSetting::getSettings();
        $settings->update($validated);

        WebSetting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Configuración financiera guardada correctamente.',
        ]);
    }
}
