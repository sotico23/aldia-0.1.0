<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Desafios de verificacion en dos pasos (TOTP) para el login de la API.
 *
 * El desafio es un identificador opaco que viaja en el body de la respuesta
 * del login. Se guarda en la cache con una vida corta para que el codigo
 * real nunca salga del servidor y para poder invalidarlo al consumed.
 */
class TwoFactorChallengeService
{
    /** Vida del desafio en segundos (5 minutos). */
    public const TTL_SECONDS = 300;

    /**
     * Crea un desafio y devuelve su identificador opaco.
     */
    public function create(User $user, string $deviceName = '', ?string $ip = null): string
    {
        $challengeId = Str::random(64);

        Cache::put($this->key($challengeId), [
            'user_id' => (int) $user->id,
            'device_name' => $deviceName,
            'ip' => $ip,
            'created_at' => now()->toIso8601String(),
        ], self::TTL_SECONDS);

        return $challengeId;
    }

    /**
     * Recupera un desafio sin consumirlo (permite reintentar el codigo).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $challengeId): ?array
    {
        if (trim($challengeId) === '') {
            return null;
        }

        $payload = Cache::get($this->key($challengeId));

        return is_array($payload) ? $payload : null;
    }

    /**
     * Consume el desafio: lo devuelve y lo elimina de la cache.
     *
     * @return array<string, mixed>|null
     */
    public function consume(string $challengeId): ?array
    {
        $payload = $this->find($challengeId);

        if ($payload !== null) {
            $this->forget($challengeId);
        }

        return $payload;
    }

    /**
     * Elimina un desafio de la cache.
     */
    public function forget(string $challengeId): void
    {
        if (trim($challengeId) === '') {
            return;
        }

        Cache::forget($this->key($challengeId));
    }

    private function key(string $challengeId): string
    {
        return 'api.auth.2fa.'.$challengeId;
    }
}
