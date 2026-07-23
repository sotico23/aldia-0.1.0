<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use App\Models\User;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

class WebhookSettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.web-settings.edit'),
        ];
    }

    public function show(): JsonResponse
    {
        $master = User::role('Master')->whereNull('creator_id')->first();
        $config = $master ? PaymentConfig::where('owner_id', $master->id)->first() : null;

        $baseUrl = url('/webhooks');

        $paypalLogs = WebhookLog::byGateway('paypal')->latest('received_at')->take(5)->get();
        $paypalLast = $paypalLogs->first();

        $mercadopagoLogs = WebhookLog::byGateway('mercadopago')->latest('received_at')->take(5)->get();
        $mercadopagoLast = $mercadopagoLogs->first();

        $lastFailed = WebhookLog::byStatus('failed')->latest('received_at')->first();

        $audit = [
            'total_received' => WebhookLog::count(),
            'total_failed' => WebhookLog::byStatus('failed')->count(),
            'total_duplicates' => WebhookLog::byStatus('duplicate')->count(),
            'last_error' => $lastFailed ? Str::limit(strip_tags($lastFailed->error_message ?? ''), 200) : null,
        ];

        $formatLog = fn ($log) => [
            'id' => $log->id,
            'event_type' => $log->event_type,
            'event_id' => $log->event_id,
            'status' => $log->status,
            'received_at' => $log->received_at->toIso8601String(),
            'error_message' => Str::limit(strip_tags((string) $log->error_message), 200),
        ];

        return response()->json([
            'data' => [
                'paypal' => [
                    'webhook_url' => $baseUrl.'/paypal',
                    'webhook_id' => $config?->paypal_webhook_id ? '••••••••••••••••' : '',
                    'status' => $config?->paypal_active ? 'active' : 'inactive',
                    'last_event' => $paypalLast?->event_type,
                    'last_event_at' => $paypalLast?->received_at?->toIso8601String(),
                    'recent_logs' => $paypalLogs->map($formatLog),
                ],
                'mercadopago' => [
                    'webhook_url' => $baseUrl.'/mercadopago',
                    'webhook_secret' => $config?->mercadopago_webhook_secret ? '••••••••••••••••' : '',
                    'status' => $config?->mercadopago_active ? 'active' : 'inactive',
                    'last_event' => $mercadopagoLast?->event_type,
                    'last_event_at' => $mercadopagoLast?->received_at?->toIso8601String(),
                    'recent_logs' => $mercadopagoLogs->map($formatLog),
                ],
                'audit' => $audit,
            ],
        ]);
    }
}
