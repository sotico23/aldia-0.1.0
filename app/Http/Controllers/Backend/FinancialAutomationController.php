<?php

namespace App\Http\Controllers\Backend;

use App\Enums\FinancialEvent;
use App\Http\Controllers\Controller;
use App\Models\WebSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class FinancialAutomationController extends Controller implements HasMiddleware
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
            'data' => $settings->financial_automations ?? FinancialEvent::defaults(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'events' => 'required|array',
            'events.*.event' => ['required', 'string', Rule::in(FinancialEvent::values())],
            'events.*.label' => 'required|string',
            'events.*.n8n' => 'boolean',
            'events.*.telegram' => 'boolean',
            'events.*.whatsapp' => 'boolean',
            'events.*.email' => 'boolean',
        ]);

        $settings = WebSetting::getSettings();
        $settings->financial_automations = $validated['events'];
        $settings->save();

        WebSetting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Automatizaciones financieras guardadas correctamente.',
        ]);
    }
}
