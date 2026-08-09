<?php

namespace App\Services;

use App\Exceptions\TelegramAuthException;
use Illuminate\Http\Request;

class TelegramAuthService
{
    public function __construct(private readonly ?string $botToken = null) {}

    /**
     * Verify a Telegram Login Widget payload following the official Telegram
     * specification: build the "data check string" from every field (excluding
     * hash), sign it with HMAC-SHA256 using the bot token, and compare it in
     * constant time. The auth_date freshness check prevents replay attacks.
     *
     * @return array{id: string, name: ?string, username: ?string, avatar: ?string, email: ?string, auth_date: int}
     *
     * @throws TelegramAuthException
     */
    public function verify(Request $request): array
    {
        $data = $request->query();
        $hash = $data['hash'] ?? null;

        if (! is_string($hash) || strlen($hash) !== 64) {
            throw new TelegramAuthException('El hash de Telegram falta o es inválido.');
        }

        if (empty($data['id']) || ! ctype_digit((string) $data['id'])) {
            throw new TelegramAuthException('El id de Telegram es inválido.');
        }

        $authDate = (int) ($data['auth_date'] ?? 0);

        if ($authDate <= 0) {
            throw new TelegramAuthException('El auth_date de Telegram es inválido.');
        }

        $fields = collect($data)
            ->only(['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'email'])
            ->reject(fn (mixed $value): bool => $value === null);

        $dataCheckString = $fields
            ->map(fn (mixed $value, string $key): string => "{$key}={$value}")
            ->sort()
            ->values()
            ->join("\n");

        $botToken = $this->botToken ?? (string) config('services.telegram.client_secret');

        if ($botToken === '') {
            throw new TelegramAuthException('El token del bot de Telegram no está configurado.');
        }

        $secretKey = hash('sha256', $botToken, true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($computedHash, $hash)) {
            throw new TelegramAuthException('La firma HMAC de Telegram no coincide.');
        }

        $maxAge = (int) config('services.telegram.login_max_age', 300);

        if ($authDate < time() - $maxAge) {
            throw new TelegramAuthException('La sesión de Telegram ha expirado.');
        }

        return [
            'id' => (string) $data['id'],
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: null,
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'avatar' => isset($data['photo_url']) ? (string) $data['photo_url'] : null,
            'email' => isset($data['email']) ? (string) $data['email'] : null,
            'auth_date' => $authDate,
        ];
    }
}
