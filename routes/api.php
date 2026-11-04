<?php

use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Bot\ClienteBotController;
use App\Http\Controllers\Api\Bot\OpenApiController;
use App\Http\Controllers\Api\Bot\ResourceBotController;
use App\Http\Controllers\Api\Bot\TransversalBotController;
use App\Http\Controllers\Api\Bot\VentaBotController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\Delivery\DeliveryOrdenController;
use App\Http\Controllers\Api\Delivery\ZoneController;
use App\Http\Controllers\Api\InternalAutomationController;
use App\Http\Controllers\Api\InternalAutomationSendController;
use App\Http\Controllers\Api\TenantDataController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Backend\GlobalSearchController;
use App\Http\Controllers\Backend\SiiController;
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
})->middleware(['auth:sanctum', 'throttle:api']);

Route::group(['prefix' => 'v1'], function () {
    Route::prefix('channels')->group(function () {
        Route::post('resolve', [ChannelController::class, 'resolve'])
            ->middleware(['verify-n8n-api-key:global', 'throttle:60,1'])
            ->name('api.v1.channels.resolve');

        Route::post('reply', [ChannelController::class, 'reply'])
            ->middleware(['verify-tenant-token', 'throttle:60,1'])
            ->name('api.v1.channels.reply');
    });

    Route::post('tracking/update', [TrackingController::class, 'updateLocation'])
        ->middleware(['auth:sanctum', 'active', 'throttle:60,1']);

    Route::post('telegram/check-linking', [TelegramWebhookController::class, 'checkLinking'])
        ->middleware(['verify-n8n-token', 'throttle:60,1'])
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

// -----------------------------------------------------------------------------
// API de autenticacion para clientes no-browser (app Flutter, agentes).
// -----------------------------------------------------------------------------
//
// No crea sesion web: entrega Personal Access Tokens de Sanctum con
// abilities explicitas. El login exige `throttle:login` (5/min por email|ip)
// y el grupo aplica `throttle:api-auth` (10/min).

Route::prefix('v1/auth')->middleware(['throttle:api-auth'])->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('api.v1.auth.login');

    Route::post('two-factor/verify', [AuthController::class, 'twoFactorVerify'])
        ->middleware('throttle:two-factor-api')
        ->name('api.v1.auth.two-factor.verify');

    Route::post('refresh', [AuthController::class, 'refresh'])
        ->name('api.v1.auth.refresh');

    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware('api-token')
        ->name('api.v1.auth.logout');

    Route::get('me', [AuthController::class, 'me'])
        ->middleware('api-token')
        ->name('api.v1.auth.me');
});

// OpenAPI contract for n8n "Tools via OpenAPI" import.
// Debe registrarse ANTES del comodín `v1/bot/{resource}` para que no lo capture.
Route::get('v1/bot/openapi', [OpenApiController::class, 'index'])->middleware('throttle:api');

// Bot API for AI agents (n8n / agentes de IA) — strict multi-tenant isolation.
// `agent-api` acepta el Personal Access Token de Sanctum (app Flutter, agentes)
// y, por compatibilidad, el `users.api_token` legacy que ya usan los workflows
// de n8n desplegados.
Route::prefix('v1/bot')->middleware(['agent-api', 'ability:agent:read', 'throttle:agent-read'])->group(function () {
    // Transversales (Ola 2)
    Route::get('contexto', [TransversalBotController::class, 'contexto']);
    Route::get('resumen', [TransversalBotController::class, 'resumen']);
    Route::get('reportes/{tipo}', [TransversalBotController::class, 'reportes']);
    Route::get('buscar', [TransversalBotController::class, 'buscar']);
    Route::get('dashboard/resumen', [TransversalBotController::class, 'dashboardResumen']);

    // Existentes (mantener compatibilidad)
    Route::get('clientes', [ClienteBotController::class, 'index']);
    Route::get('clientes/{cliente}', [ClienteBotController::class, 'show']);
    Route::get('ventas', [VentaBotController::class, 'index']);
    Route::get('ventas/{venta}', [VentaBotController::class, 'show']);

    // Recursos genéricos read-only (Ola 2)
    Route::get('{resource}', [ResourceBotController::class, 'index']);
    Route::get('{resource}/{id}', [ResourceBotController::class, 'show']);
});

// Escrituras del Bot API: requieren `agent:write`. El token legacy de n8n
// tiene el wildcard `*` y conserva el acceso completo.
Route::prefix('v1/bot')->middleware(['agent-api', 'ability:agent:write', 'throttle:agent-write'])->group(function () {
    Route::post('clientes', [ClienteBotController::class, 'store']);
    Route::put('clientes/{cliente}', [ClienteBotController::class, 'update']);
    Route::delete('clientes/{cliente}', [ClienteBotController::class, 'destroy']);

    Route::post('ventas', [VentaBotController::class, 'store']);
    Route::put('ventas/{venta}', [VentaBotController::class, 'update']);
    Route::delete('ventas/{venta}', [VentaBotController::class, 'destroy']);
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

// Zona de repartidores — API movil (PWA) / api-first
Route::prefix('v1/delivery')
    ->middleware(['auth:sanctum', 'delivery', 'throttle:delivery'])
    ->group(function () {
        Route::post('location', [DeliveryOrdenController::class, 'location'])
            ->middleware('throttle:60,1');

        Route::get('me', [DeliveryOrdenController::class, 'me']);
        Route::post('availability', [DeliveryOrdenController::class, 'availability']);
        Route::get('orders', [DeliveryOrdenController::class, 'orders']);

        Route::post('orders/{pedido}/accept', [DeliveryOrdenController::class, 'accept']);
        Route::post('orders/{pedido}/pickup', [DeliveryOrdenController::class, 'pickup']);
        Route::post('orders/{pedido}/delivered', [DeliveryOrdenController::class, 'delivered']);
        Route::post('orders/{pedido}/reject', [DeliveryOrdenController::class, 'reject']);
    });

// Zonas de reparto — administración y analítica por tenant
Route::prefix('v1/zones')
    ->middleware(['auth:sanctum', 'active', 'throttle:60,1'])
    ->group(function () {
        Route::get('/', [ZoneController::class, 'index']);
        Route::post('/', [ZoneController::class, 'store']);
        Route::get('resumen', [ZoneController::class, 'resumen']);
        Route::get('geojson', [ZoneController::class, 'geojson']);
        Route::post('pool/{pedido}/asignar', [ZoneController::class, 'asignarPool']);
        Route::put('{zone}', [ZoneController::class, 'update']);
        Route::delete('{zone}', [ZoneController::class, 'destroy']);
    });

Route::post('canales/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('api.canales.telegram.webhook');
Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');
Route::prefix('tenant')->middleware(['verify-tenant-token', 'active', 'throttle:60,1'])->group(function () {
    Route::get('resumen-completo', [TenantDataController::class, 'resumenCompleto'])
        ->name('api.tenant.resumen-completo');
});

// -----------------------------------------------------------------------------
// Utilidades para agentes y clientes no-browser
// -----------------------------------------------------------------------------
//
// OJO: las rutas se registran bajo el prefijo `v1/` a proposito. Laravel
// indexa las rutas por metodo+URI, asi que registrar `api/global-search`
// aqui SOBREESCRIBIRIA la ruta homonima del panel web (routes/web.php) y
// esta quedaria anulada. El prefijo `v1` evita la colision.

// Búsqueda global cross-módulo. La versión del panel web sigue en
// routes/web.php (sesión); esta es la variante para tokens de API.
Route::get('v1/global-search', [GlobalSearchController::class, 'search'])
    ->middleware(['agent-api', 'ability:agent:read', 'throttle:search'])
    ->name('api.global-search.token');

// Validación / consulta de RUT (utilidad pública, no expone datos del tenant).
Route::get('v1/sii/consultar/{rut}', [SiiController::class, 'validarRut'])
    ->middleware('throttle:api')
    ->name('api.sii.consultar.token');
Route::post('v1/sii/validar-rut', [SiiController::class, 'validarRut'])
    ->middleware('throttle:api')
    ->name('api.sii.validar-rut.token');
