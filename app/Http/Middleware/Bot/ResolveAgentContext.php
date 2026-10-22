<?php

namespace App\Http\Middleware\Bot;

use App\Models\User;
use App\Scopes\OwnerScope;
use App\Support\AgentContext;
use App\Support\BotContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el contexto del agente para las rutas de API.
 *
 * Acepta dos tipos de credencial:
 *
 *  1. Personal Access Token de Sanctum (formato "id|token") -> preferred.
 *     Es el que emite POST /api/v1/auth/login y consume la app Flutter y
 *     los agentes de IA. Puede expirar, se revoca y lleva abilities.
 *
 *  2. `users.api_token` legacy (texto plano) -> fallback de compatibilidad
 *     con los workflows de n8n ya desplegados. Sin abilities ni expiracion.
 *
 * Reglas de aislamiento (inalterables):
 *  - X-Owner-ID es obligatorio y numerico.
 *  - El tenant debe existir, estar activo y ser raiz de su cadena creator_id.
 *  - Un token de Master/Super Admin NO bypassea el aislamiento: el contexto
 *    se resuelve SIEMPRE al tenant de X-Owner-ID.
 *  - X-User-ID es opcional, pero si viene debe pertenecer al tenant.
 *  - Cross-tenant -> 404 en los controladores (no 403), para no filtrar
 *    la existencia de recursos.
 */
class ResolveAgentContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->jsonError('Token de API no proporcionado. Use la cabecera Authorization: Bearer <token>.', 401);
        }

        $resolution = $this->resolve($request, $token);

        if ($resolution instanceof JsonResponse) {
            return $resolution;
        }

        [$context, $tenant] = $resolution;

        $request->attributes->set('agent_context', $context);
        // Compatibilidad con el Bot API existente (BotContext).
        $request->attributes->set('bot_context', new BotContext($context->ownerId, $context->actingUserId));

        // Activa el global scope OwnerScope (defensa en profundidad).
        // Se hace DESPUES de resolver el tenant para no condicionar las
        // consultas deUser::find() con el scope ya activo.
        Auth::shouldUse('web');
        Auth::setUser($tenant);

        return $next($request);
    }

    /**
     * @return array{0: AgentContext, 1: User}|JsonResponse
     */
    private function resolve(Request $request, string $token): array|JsonResponse
    {
        $pat = $this->findPersonalAccessToken($token);

        if ($pat instanceof JsonResponse) {
            return $pat;
        }

        if ($pat !== null) {
            return $this->resolveFromPersonalAccessToken($request, $pat);
        }

        return $this->resolveFromLegacyApiToken($request, $token);
    }

    /**
     * Busca un Personal Access Token de Sanctum.
     *
     * Devuelve null si no existe y un JsonResponse si la tabla aun no fue
     * migrada (para no romper el despliegue antes de correr la migracion).
     */
    private function findPersonalAccessToken(string $token): PersonalAccessToken|JsonResponse|null
    {
        try {
            return PersonalAccessToken::findToken($token);
        } catch (\Throwable) {
            return $this->jsonError('El almacen de tokens de API no esta disponible.', 503);
        }
    }

    /**
     * @return array{0: AgentContext, 1: User}|JsonResponse
     */
    private function resolveFromPersonalAccessToken(Request $request, PersonalAccessToken $pat): array|JsonResponse
    {
        $user = $pat->tokenable;

        if (! $user instanceof User) {
            return $this->jsonError('Token de API invalido.', 401);
        }

        if (! $user->is_active) {
            return $this->jsonError('Cuenta desactivada. Contacta al administrador.', 403);
        }

        if ($user->banned_at !== null) {
            return $this->jsonError('Cuenta bloqueada. Contacta al administrador.', 403);
        }

        if ($pat->expires_at !== null && $pat->expires_at->isPast()) {
            return $this->jsonError('El token de API expiro. Solicita uno nuevo.', 401);
        }

        $tenantResolution = $this->resolveTenant($request, $user);

        if ($tenantResolution instanceof JsonResponse) {
            return $tenantResolution;
        }

        [$tenant, $actingUserId] = $tenantResolution;

        return [
            new AgentContext(
                ownerId: (int) $tenant->id,
                actingUserId: $actingUserId,
                abilities: $this->abilitiesOf($pat),
                legacyToken: false,
                tokenName: $pat->name,
            ),
            $tenant,
        ];
    }

    /**
     * @return array{0: AgentContext, 1: User}|JsonResponse
     */
    private function resolveFromLegacyApiToken(Request $request, string $token): array|JsonResponse
    {
        $apiUser = User::withoutGlobalScope(OwnerScope::class)
            ->where('api_token', $token)
            ->first();

        if (! $apiUser) {
            return $this->jsonError('Token de API invalido.', 401);
        }

        if (! $apiUser->is_active) {
            return $this->jsonError('Cuenta desactivada. Contacta al administrador.', 403);
        }

        if ($apiUser->banned_at !== null) {
            return $this->jsonError('Cuenta bloqueada. Contacta al administrador.', 403);
        }

        $tenantResolution = $this->resolveTenant($request, $apiUser);

        if ($tenantResolution instanceof JsonResponse) {
            return $tenantResolution;
        }

        [$tenant, $actingUserId] = $tenantResolution;

        return [
            new AgentContext(
                ownerId: (int) $tenant->id,
                actingUserId: $actingUserId,
                abilities: ['*'],
                legacyToken: true,
                tokenName: 'legacy-api-token',
            ),
            $tenant,
        ];
    }

    /**
     * Resuelve el tenant a partir de X-Owner-ID y valida la pertenencia.
     *
     * @return array{0: User, 1: int}|JsonResponse
     */
    private function resolveTenant(Request $request, User $apiUser): array|JsonResponse
    {
        $ownerId = $request->header('X-Owner-ID');

        if ($ownerId === null || ! ctype_digit((string) $ownerId)) {
            return $this->jsonError('Cabecera X-Owner-ID obligatoria y debe ser numerica.', 401);
        }

        $ownerId = (int) $ownerId;

        $isPlatformUser = $apiUser->hasRole('Master') || $apiUser->hasRole('Super Admin');

        if (! $isPlatformUser && $apiUser->getOwnerId() !== $ownerId) {
            return $this->jsonError('El token no pertenece al tenant indicado en X-Owner-ID.', 401);
        }

        $tenant = User::withoutGlobalScope(OwnerScope::class)->find($ownerId);

        if (! $tenant || ! $tenant->is_active || $tenant->getOwnerId() !== $ownerId) {
            return $this->jsonError('Tenant invalido o inactivo en X-Owner-ID.', 401);
        }

        $actingUserId = (int) $tenant->id;

        if ($request->hasHeader('X-User-ID')) {
            $actingUser = User::withoutGlobalScope(OwnerScope::class)
                ->find((int) $request->header('X-User-ID'));

            if (! $actingUser || ! $actingUser->is_active || $actingUser->getOwnerId() !== $ownerId) {
                return $this->jsonError('X-User-ID invalido o no pertenece al tenant.', 401);
            }

            $actingUserId = (int) $actingUser->id;
        }

        return [$tenant, $actingUserId];
    }

    /**
     * @return array<int, string>
     */
    private function abilitiesOf(PersonalAccessToken $pat): array
    {
        $abilities = $pat->abilities ?? [];

        return array_values(array_map(fn ($ability) => (string) $ability, $abilities));
    }

    protected function jsonError(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => [],
        ], $status);
    }
}
