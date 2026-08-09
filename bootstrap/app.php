<?php

use App\Http\Middleware\Bot\ResolveBotTenant;
use App\Http\Middleware\CheckActive;
use App\Http\Middleware\CheckOwnership;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleRegionalSettings;
use App\Http\Middleware\RedirectIfClient;
use App\Http\Middleware\RedirectIfProveedor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackPageViews;
use App\Http\Middleware\VerifyN8nApiKey;
use App\Http\Middleware\VerifyN8nToken;
use App\Http\Middleware\VerifyTenantToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'verify-n8n-api-key' => VerifyN8nApiKey::class,
            'verify-n8n-token' => VerifyN8nToken::class,
            'verify-tenant-token' => VerifyTenantToken::class,
            'bot-api' => ResolveBotTenant::class,
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
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/v1/bot/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Recurso no encontrado.',
                    'errors' => [],
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/v1/bot/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
