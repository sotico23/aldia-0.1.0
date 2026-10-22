<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | La API de Aldia es stateless (Bearer token). Se deja vacio a proposito:
    | no se habilita la autenticacion por cookie/sesion para clientes externos
    | (app Flutter, agentes, n8n). El panel web sigue usando su propia sesion.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Minutos de vida por defecto de los Personal Access Tokens emitidos para
    | clientes no-browser (app Flutter / agentes). SANCTUM_EXPIRATION_MINUTES
    | tiene prioridad; si se deja vacio / en 0, los tokens no expiran.
    |
    | El valor es el numero de MINUTOS que espera Sanctum (no una fecha):
    | Laravel\Sanctum\Guard lo usa como `now()->subMinutes($expiration)`.
    |
    | El refresco se hace explicitamente via POST /api/v1/auth/refresh, que
    | rota el token (revoca el anterior y emite uno nuevo).
    |
    */

    'expiration' => ((int) env('SANCTUM_EXPIRATION_MINUTES', 43200)) > 0
        ? (int) env('SANCTUM_EXPIRATION_MINUTES', 43200)
        : null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Prefijo de los tokens emitidos. Permite detectar tokens filtrados en
    | repositorios publicos (secret scanning de GitHub).
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'aldia_'),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | Solo se usa el guard de sesion de primera parte. Al no activar
    | EnsureFrontendRequestsAreStateful, el flujo por cookie no aplica.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
