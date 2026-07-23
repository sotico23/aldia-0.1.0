<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backend\StoreAutomationConfigRequest;
use App\Models\AutomationConfig;
use App\Models\ChannelCredential;
use App\Services\N8nService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function __construct(
        protected N8nService $n8nService
    ) {}

    public function index(): Response
    {
        $config = AutomationConfig::where('owner_id', Auth::user()->getOwnerId())->first();

        return Inertia::render('Backend/ChannelCredentials', [
            'automation' => $config ? [
                'channel' => $config->channel,
                'frequency' => $config->frequency,
                'execution_time' => $config->execution_time,
                'enabled' => $config->enabled,
                'selected_reports' => $config->selected_reports ?? [],
                'last_run_at' => $config->last_run_at?->diffForHumans(),
                'next_run_at' => $config->next_run_at?->diffForHumans(),
                'last_run_status' => $config->last_run_status,
            ] : null,
        ]);
    }

    public function store(StoreAutomationConfigRequest $request): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        $validated = $request->validated();

        $config = AutomationConfig::updateOrCreate(
            ['owner_id' => $ownerId],
            [
                'channel' => $validated['channel'],
                'frequency' => $validated['frequency'],
                'execution_time' => $validated['execution_time'],
                'enabled' => $validated['enabled'] ?? false,
                'selected_reports' => $validated['selected_reports'] ?? [],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Configuración guardada correctamente.',
            'automation' => [
                'channel' => $config->channel,
                'frequency' => $config->frequency,
                'execution_time' => $config->execution_time,
                'enabled' => $config->enabled,
                'selected_reports' => $config->selected_reports ?? [],
                'last_run_at' => $config->last_run_at?->diffForHumans(),
                'next_run_at' => $config->next_run_at?->diffForHumans(),
                'last_run_status' => $config->last_run_status,
            ],
        ]);
    }

    public function runTest(): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $config = AutomationConfig::where('owner_id', $ownerId)->first();
        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'No hay configuración de automatización. Guarda la configuración primero.',
            ], 422);
        }

        if (empty($config->selected_reports)) {
            return response()->json([
                'success' => false,
                'message' => 'Selecciona al menos un reporte para ejecutar.',
            ], 422);
        }

        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();
        if (! $credentials) {
            return response()->json([
                'success' => false,
                'message' => 'Conecta Telegram o WhatsApp primero en las credenciales.',
            ], 422);
        }

        if (! $this->n8nService->isAvailable()) {
            $config->update([
                'last_run_at' => now(),
                'last_run_status' => 'error',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook global de n8n no configurado. Contacta al administrador.',
            ], 500);
        }

        $result = $this->n8nService->triggerWorkflow(
            $ownerId,
            $config->channel,
            $config->selected_reports ?? [],
            true
        );

        $config->update([
            'last_run_at' => now(),
            'last_run_status' => $result['success'] ? 'success' : 'error',
        ]);

        if (! $result['success']) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }
}
