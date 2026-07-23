<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomationConfig;
use App\Models\ChannelCredential;
use App\Models\User;
use App\Services\AutomationReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalAutomationController extends Controller
{
    public function __construct(
        protected AutomationReportService $reportService
    ) {}

    public function getConfig(int $businessId): JsonResponse
    {
        $config = AutomationConfig::where('owner_id', $businessId)->first();
        $user = User::find($businessId);
        $credentials = ChannelCredential::where('owner_id', $businessId)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'business' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'business_name' => $user->business_name ?? $user->name,
            ],
            'config' => $config ? [
                'channel' => $config->channel,
                'frequency' => $config->frequency,
                'execution_time' => $config->execution_time,
                'enabled' => $config->enabled,
                'selected_reports' => $config->selected_reports ?? [],
            ] : null,
            'credentials' => $credentials ? [
                'telegram_bot_username' => $credentials->telegram_bot_username,
                'whatsapp_phone_number_id' => $credentials->whatsapp_phone_number_id,
                'whatsapp_business_id' => $credentials->whatsapp_business_id,
            ] : null,
        ]);
    }

    public function getReports(int $businessId, Request $request): JsonResponse
    {
        $request->merge(['reports' => $request->input('reports', [])]);

        $request->validate([
            'reports' => 'nullable|array',
            'reports.*' => 'string',
        ]);

        return response()->json([
            'success' => true,
            'business_id' => $businessId,
            'reports' => $this->reportService->generate($businessId, $request->input('reports', [])),
        ]);
    }
}
