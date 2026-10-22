<?php

use App\Http\Middleware\Api\CheckApiOwnership;
use App\Http\Middleware\Api\CheckApiPermission;
use App\Http\Middleware\Api\CheckApiRole;
use App\Http\Middleware\Api\RequireAbility;
use App\Http\Middleware\Api\ResolveApiToken;
use App\Http\Middleware\Bot\ResolveAgentContext;
use App\Http\Middleware\Bot\ResolveBotTenant;
use App\Http\Middleware\CheckActive;
use App\Http\Middleware\CheckDelivery;
use App\Http\Middleware\CheckOwnership;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleRegionalSettings;
use App\Http\Middleware\MarkApiRequest;
use App\Http\Middleware\RedirectIfClient;
use App\Http\Middleware\RedirectIfProveedor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackPageViews;
use App\Http\Middleware\VerifyN8nApiKey;
use App\Http\Middleware\VerifyN8nToken;
use App\Http\Middleware\VerifyTenantToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'canales/telegram/webhook',
            // El widget de Telegram Login envía su payload firmado (HMAC) por
            // POST al callback; la firma reemplaza la protección CSRF.
            'auth/telegram/callback',
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'ownership' => CheckOwnership::class,
            'active' => CheckActive::class,
            'delivery' => CheckDelivery::class,
            'verify-n8n-api-key' => VerifyN8nApiKey::class,
            'verify-n8n-token' => VerifyN8nToken::class,
            'verify-tenant-token' => VerifyTenantToken::class,
            'bot-api' => ResolveBotTenant::class,
            // API para agentes de IA y clientes no-browser (app Flutter).
            // Resuelve el tenant (X-Owner-ID) y activa el global scope
            // OwnerScope mediante Auth::shouldUse().
            'agent-api' => ResolveAgentContext::class,
            'ability' => RequireAbility::class,
            // Variantes JSON-aware de los middleware web (el web redirige a
            // /login y devuelve HTML a un cliente movil).
            'api-role' => CheckApiRole::class,
            'api-permission' => CheckApiPermission::class,
            'api-ownership' => CheckApiOwnership::class,
            // Resuelve el Personal Access Token de Sanctum de forma explicita
            // (no delega en el guard) para poder trabajar con sus abilities
            // y revocar exactamente el token usado.
            'api-token' => ResolveApiToken::class,
        ]);

        // El grupo `api` solo traia SubstituteBindings. NO se agrega
        // `throttle:api` de forma global: casi todas las rutas de api ya
        // tienen su propio limitador (bot, delivery, 60,1, search...) y el
        // limitador `api` (60/min) bajaria el techo del Bot API (120/min).
        // El limitador `api` se aplica por ruta donde haga falta
        // (ver routes/api.php).
        //
        // `MarkApiRequest` marca la request como API para que los errores de
        // autenticacion/autorizacion se devuelvan en JSON. No se puede usar
        // el prefijo `/api` porque en `routes/web.php` hay rutas web bajo
        // `/api/...` que deben redirigir a /login.
        $middleware->api(prepend: [
            MarkApiRequest::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleRegionalSettings::class,
            HandleInertiaRequests::class,
            TrackPageViews::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
            CheckActive::class,
            RedirectIfClient::class,
            RedirectIfProveedor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Rutas con contrato JSON propio (Bot API + API de agente + Auth API).
        $jsonApiRoutes = ['api/v1/bot/*', 'api/v1/agent/*', 'api/v1/auth/*'];

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($jsonApiRoutes) {
            if ($request->is($jsonApiRoutes)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Recurso no encontrado.',
                    'errors' => [],
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($jsonApiRoutes) {
            if ($request->is($jsonApiRoutes)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Un cliente no-browser nunca debe recibir HTML ni un redirect a
        // /login: se responde siempre con el envelope JSON de la API.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->attributes->get(MarkApiRequest::ATTRIBUTE) === true) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                    'errors' => [],
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->attributes->get(MarkApiRequest::ATTRIBUTE) === true) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Forbidden.',
                    'errors' => [],
                ], 403);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->attributes->get(MarkApiRequest::ATTRIBUTE) === true) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Forbidden.',
                    'errors' => [],
                ], 403);
            }
        });
    })->create();
