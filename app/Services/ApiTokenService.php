<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Ciclo de vida de los Personal Access Tokens (Sanctum) para clientes
 * no-browser: app Flutter, agentes de IA y cualquier consumidor de la API.
 *
 * - El token se nombra con el nombre del dispositivo para poder revocarlo.
 * - Las abilities se guardan en la columna `abilities` (JSON) del token.
 * - La expiracion por defecto sale de config('sanctum.expiration').
 */
class ApiTokenService
{
    /** Ability minima para consumir las tools de lectura del agente. */
    public const ABILITY_AGENT_READ = 'agent:read';

    /** Ability para las tools de escritura (reservada para una etapa posterior). */
    public const ABILITY_AGENT_WRITE = 'agent:write';

    /** Ability administrativa del tenant. */
    public const ABILITY_AGENT_ADMIN = 'agent:admin';

    public const ABILITY_DELIVERY_READ = 'delivery:read';

    public const ABILITY_DELIVERY_WRITE = 'delivery:write';

    public const ABILITY_CATALOG_READ = 'catalog:read';

    public const ABILITY_REPORTS_READ = 'reports:read';

    public const ABILITY_ADMIN_WRITE = 'admin:write';

    /**
     * Emite un token nuevo para un usuario.
     *
     * @param  array<int, string>  $abilities
     * @return array{token: string, id: int, name: string, abilities: array<int, string>, expires_at: string|null}
     */
    public function issue(
        User $user,
        string $deviceName,
        array $abilities = [self::ABILITY_AGENT_READ],
        ?DateTimeInterface $expiresAt = null
    ): array {
        $deviceName = $this->normalizeDeviceName($deviceName);
        $abilities = $this->normalizeAbilities($abilities);
        $expiresAt = $expiresAt ?? $this->defaultExpiration();

        $newAccessToken = $user->createToken($deviceName, $abilities, $expiresAt);

        return [
            'token' => $newAccessToken->plainTextToken,
            'id' => (int) $newAccessToken->accessToken->getKey(),
            'name' => $deviceName,
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->format('c'),
        ];
    }

    /**
     * Rota un token: revoca el anterior y emite uno nuevo con las mismas
     * abilities (o las indicadas).
     *
     * @param  array<int, string>|null  $abilities
     * @return array{token: string, id: int, name: string, abilities: array<int, string>, expires_at: string|null}
     */
    public function rotate(PersonalAccessToken $current, ?array $abilities = null): array
    {
        $user = $current->tokenable;

        if (! $user instanceof User) {
            throw new \RuntimeException('El token no pertenece a un usuario valido.');
        }

        $abilities = $abilities !== null
            ? $this->normalizeAbilities($abilities)
            : $this->normalizeAbilities($current->abilities ?? []);

        // Un token vivo conserva su vencimiento; uno ya expirado (p. ej. un
        // refresh tardio) recibe una ventana nueva.
        $expiresAt = $current->expires_at !== null && $current->expires_at->isFuture()
            ? $current->expires_at
            : $this->defaultExpiration();

        $new = $this->issue($user, $current->name, $abilities, $expiresAt);

        $current->delete();

        return $new;
    }

    /**
     * Revoca un token concreto.
     */
    public function revoke(PersonalAccessToken $token): void
    {
        $token->delete();
    }

    /**
     * Revoca todos los tokens de un usuario indicados para un dispositivo.
     *
     * @return int numero de tokens revocados
     */
    public function revokeForDevice(User $user, string $deviceName): int
    {
        $deviceName = $this->normalizeDeviceName($deviceName);

        return (int) $user->tokens()
            ->where('name', $deviceName)
            ->delete();
    }

    /**
     * Revoca todos los tokens de un usuario.
     *
     * @return int numero de tokens revocados
     */
    public function revokeAll(User $user): int
    {
        return (int) $user->tokens()->delete();
    }

    /**
     * Abilities activas de un usuario (unION de todos sus tokens vivos).
     *
     * @return array<int, string>
     */
    public function abilitiesOf(User $user): array
    {
        $abilities = $user->tokens()
            ->get()
            ->flatMap(fn (PersonalAccessToken $token) => $token->abilities ?? [])
            ->unique()
            ->values()
            ->all();

        return $this->normalizeAbilities($abilities);
    }

    /**
     * Lista de tokens activos del usuario (sin exponer el hash).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listFor(User $user): Collection
    {
        return $user->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => (int) $token->getKey(),
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'last_used_at' => $token->last_used_at?->format('c'),
                'expires_at' => $token->expires_at?->format('c'),
                'created_at' => $token->created_at?->format('c'),
            ]);
    }

    /**
     * Comprueba si un token cumple una ability (soporta el wildcard `*`).
     */
    public function can(PersonalAccessToken $token, string $ability): bool
    {
        return $token->can($ability);
    }

    /**
     * Expiracion por defecto tomada de config('sanctum.expiration').
     *
     * Sanctum guarda el valor en MINUTOS (lo usa como
     * `now()->subMinutes($expiration)`), asi que se convierte a fecha
     * absoluta para poder guardarla en `expires_at`.
     */
    private function defaultExpiration(): ?DateTimeInterface
    {
        $expiration = config('sanctum.expiration');

        if ($expiration instanceof DateTimeInterface) {
            return $expiration;
        }

        if ($expiration instanceof \DateInterval) {
            return CarbonImmutable::now()->add($expiration);
        }

        if (is_numeric($expiration) && (int) $expiration > 0) {
            return CarbonImmutable::now()->addMinutes((int) $expiration);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    private function normalizeAbilities(array $abilities): array
    {
        $abilities = array_values(array_unique(array_filter(
            array_map(fn ($ability) => trim((string) $ability), $abilities),
            fn (string $ability) => $ability !== ''
        )));

        if (in_array('*', $abilities, true)) {
            return ['*'];
        }

        return $abilities;
    }

    private function normalizeDeviceName(string $deviceName): string
    {
        $deviceName = trim($deviceName);

        if ($deviceName === '') {
            return 'dispositivo-desconocido';
        }

        return mb_substr($deviceName, 0, 120);
    }
}
