<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marca la request como belonging al grupo de middleware `api`.
 *
 * Permite distinguir las rutas reales de `routes/api.php` de las rutas web
 * que, por compatibilidad con clientes externos, cuelgan bajo el prefijo
 * `/api/...` (por ejemplo `api/v1/tenant-credentials/autocomplete`).
 *
 * Las rutas web deben redirigir a /login cuando no hay sesion; las rutas de
 * API deben responder con el envelope JSON `{status:"error", ...}`.
 */
class MarkApiRequest
{
    public const ATTRIBUTE = 'api_request';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, true);

        return $next($request);
    }
}
