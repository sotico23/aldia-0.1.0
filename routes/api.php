<?php

use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Api\InternalAutomationController;
use App\Http\Controllers\Api\InternalAutomationSendController;
use App\Http\Controllers\Api\TenantDataController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Internal\N8nController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    $user = $request->user();

    if (! $user || ! $user->is_active) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    return $user->only([
        'id', 'name', 'email', 'telefono', 'direccion', 'country',
        'business_name', 'business_logo_path', 'business_cover_path',
        'is_active', 'created_at',
    ]);
})->middleware('auth:sanctum');

Route::group(['prefix' => 'v1'], function () {
    Route::post('tracking/update', [TrackingController::class, 'updateLocation'])
        ->middleware(['auth:sanctum', 'active', 'throttle:60,1']);

    Route::post('telegram/check-linking', [TelegramWebhookController::class, 'checkLinking'])
        ->name('api.telegram.check-linking');

    // Endpoints internos para n8n (autenticados con API Key global)
    Route::middleware(['verify-n8n-api-key', 'throttle:60,1'])->group(function () {
        Route::get('automation/config/{businessId}', [InternalAutomationController::class, 'getConfig'])
            ->name('api.internal.automation.config');

        Route::get('automation/reports/{businessId}', [InternalAutomationController::class, 'getReports'])
            ->name('api.internal.automation.reports');

        Route::post('automation/send', [InternalAutomationSendController::class, 'send'])
            ->name('api.internal.automation.send');

        Route::get('automation/health', [SystemHealthController::class, 'health'])
            ->name('api.internal.automation.health');
    });
});

// Internal API for n8n — protected by X-N8N-TOKEN header
Route::prefix('internal')->middleware(['verify-n8n-token', 'throttle:60,1'])->group(function () {
    Route::get('business/{business}/summary', [N8nController::class, 'summary']);
    Route::get('business/{business}/inventory', [N8nController::class, 'inventory']);
    Route::get('business/{business}/sales', [N8nController::class, 'sales']);
    Route::get('business/{business}/appointments', [N8nController::class, 'appointments']);

    // FASE 4: Financial endpoints
    Route::get('business/{business}/cash-flow', [N8nController::class, 'cashFlow']);
    Route::get('business/{business}/accounts-receivable', [N8nController::class, 'accountsReceivable']);
    Route::get('business/{business}/accounts-payable', [N8nController::class, 'accountsPayable']);
    Route::get('business/{business}/expenses', [N8nController::class, 'expenses']);

    // FASE 5: n8n workflow callback
    Route::post('webhook/workflow-complete', [N8nController::class, 'workflowComplete']);

    // FASE 6: Execution history
    Route::get('business/{business}/executions', [N8nController::class, 'executions']);
});

Route::post('canales/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('api.canales.telegram.webhook');
Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');
Route::prefix('tenant')->middleware(['verify-tenant-token', 'active'])->group(function () {
    Route::get('resumen-completo', [TenantDataController::class, 'resumenCompleto'])
        ->name('api.tenant.resumen-completo');
});
