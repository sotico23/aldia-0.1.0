<?php

namespace App\Services\Auth;

use App\Helpers\PermissionHelper;
use App\Models\User;
use App\Scopes\OwnerScope;
use App\Services\ApiTokenService;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Logica de negocio del inicio de sesion para clientes no-browser
 * (app Flutter, agentes de IA, integraciones).
 *
 * A diferencia del login web (Fortify + sesion), aqui no se crea sesion:
 * se emite un Personal Access Token de Sanctum con abilities explicitas.
 */
class ApiAuthService
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    /**
     * Busca un usuario por email sin aplicar scopes de tenant.
     *
     * La comparacion es case-insensitive para que el comportamiento sea
     * identico en MySQL (collation ci) y en SQLite (case-sensitive) durante
     * los tests.
     */
    public function findByEmail(string $email): ?User
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        return User::withoutGlobalScope(OwnerScope::class)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    /**
     * Valida las credenciales de un usuario.
     *
     * Devuelve null tanto si el usuario no existe como si la contraseña no
     * coincide: la API nunca debe revelar si un email esta registrado.
     */
    public function verifyCredentials(string $email, string $password): ?User
    {
        $user = $this->findByEmail($email);

        if ($user === null) {
            return null;
        }

        if (! is_string($user->password) || $user->password === '') {
            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Indica si la cuenta puede operar contra la API.
     *
     * @return array{0: bool, 1: string|null} [ok, mensaje de rechazo]
     */
    public function accountStatus(User $user): array
    {
        if ($user->banned_at !== null) {
            return [false, 'Tu cuenta esta bloqueada. Contacta al administrador.'];
        }

        if (! $user->is_active) {
            return [false, 'Tu cuenta esta desactivada. Contacta al administrador.'];
        }

        return [true, null];
    }

    /**
     * Resuelve el tenant (empresa) al que pertenece el usuario.
     *
     * El tenant es la raiz de la cadena `creator_id`: para el dueño es el
     * mismo usuario y para sus empleados es el dueño de la empresa.
     */
    public function resolveTenant(User $user): ?User
    {
        $tenantId = $user->getOwnerId();

        if ($tenantId === (int) $user->id) {
            return $user;
        }

        return User::withoutGlobalScope(OwnerScope::class)->find($tenantId);
    }

    /**
     * Payload publico del usuario (nunca incluye api_token ni secretos).
     *
     * @return array<string, mixed>
     */
    public function userPayload(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'telefono' => $user->telefono,
            'direccion' => $user->direccion,
            'country' => $user->country,
            'business_name' => $user->business_name,
            'business_logo_path' => $user->business_logo_path,
            'business_cover_path' => $user->business_cover_path,
            'is_active' => (bool) $user->is_active,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'created_at' => $user->created_at?->format('c'),
        ];
    }

    /**
     * Payload del tenant (empresa) del usuario.
     *
     * @return array<string, mixed>|null
     */
    public function tenantPayload(User $user): ?array
    {
        $tenant = $this->resolveTenant($user);

        if ($tenant === null) {
            return null;
        }

        return [
            'id' => (int) $tenant->id,
            'name' => $tenant->name,
            'business_name' => $tenant->business_name,
        ];
    }

    /**
     * Estado de la prueba gratuita, para que la app muestre el aviso
     * correspondiente sin tener que calcularlo por su cuenta.
     *
     * @return array<string, mixed>
     */
    public function trialPayload(User $user): array
    {
        return [
            'is_active' => $user->isTrialActive(),
            'is_expired' => $user->isTrialExpired(),
            'days_remaining' => $user->trialDaysRemaining(),
            'ends_at' => $user->trial_ends_at?->format('c'),
        ];
    }

    /**
     * Modulos del ERP a los que el usuario tiene acceso.
     *
     * @return array<int, string>
     */
    public function modulesFor(User $user): array
    {
        return PermissionHelper::getSidebarGroupsForUser($user);
    }

    /**
     * Tools del agente que el token actual puede invocar.
     *
     * El registro completo de tools llega en la Ola 2; aqui solo se listan
     * las que ya existen en la Tool API (`/api/v1/bot/*`).
     *
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    public function toolsFor(array $abilities): array
    {
        if (in_array('*', $abilities, true)) {
            return array_values(self::AVAILABLE_TOOLS);
        }

        $tools = [];

        foreach (self::AVAILABLE_TOOLS as $tool => $requiredAbility) {
            if (in_array($requiredAbility, $abilities, true)) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * Contexto completo de la sesion actual (endpoint `/api/v1/auth/me`).
     *
     * @param  array<int, string>  $abilities
     * @return array<string, mixed>
     */
    public function sessionContext(User $user, array $abilities, ?PersonalAccessToken $token = null): array
    {
        return [
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($user),
            'abilities' => $abilities,
            'modules' => $this->modulesFor($user),
            'tools_disponibles' => $this->toolsFor($abilities),
            'trial' => $this->trialPayload($user),
            'token' => [
                'name' => $token?->name,
                'expires_at' => $token?->expires_at?->format('c'),
                'last_used_at' => $token?->last_used_at?->format('c'),
                'created_at' => $token?->created_at?->format('c'),
            ],
        ];
    }

    /**
     * Tools de la Tool API disponibles hoy, con la ability que las habilita.
     *
     * @var array<string, string>
     */
    private const AVAILABLE_TOOLS = [
        'bot.clientes.list' => ApiTokenService::ABILITY_AGENT_READ,
        'bot.clientes.show' => ApiTokenService::ABILITY_AGENT_READ,
        'bot.ventas.list' => ApiTokenService::ABILITY_AGENT_READ,
        'bot.ventas.show' => ApiTokenService::ABILITY_AGENT_READ,
    ];
}
