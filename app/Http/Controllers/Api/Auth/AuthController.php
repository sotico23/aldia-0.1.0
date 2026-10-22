<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RefreshRequest;
use App\Http\Requests\Api\Auth\TwoFactorVerifyRequest;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\Auth\ApiAuthService;
use App\Services\Auth\TwoFactorChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * API de autenticacion para clientes no-browser (app Flutter, agentes).
 *
 * No crea sesion web: emite Personal Access Tokens de Sanctum con abilities
 * explicitas. Todas las respuestas usan el envelope de la API:
 *   { "status": "success", "message": "...", "data": {...} }
 *   { "status": "error",   "message": "...", "errors": {...} }
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly ApiAuthService $auth,
        private readonly ApiTokenService $tokens,
        private readonly TwoFactorChallengeService $challenges,
    ) {}

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $deviceName = $request->deviceName();

        $user = $this->auth->verifyCredentials($email, (string) $request->input('password'));

        if ($user === null) {
            $this->logAttempt(null, $email, $deviceName, $request, false, 'credenciales');

            return $this->error('Credenciales invalidas.', 401);
        }

        [$allowed, $reason] = $this->auth->accountStatus($user);

        if (! $allowed) {
            $this->logAttempt($user, $email, $deviceName, $request, false, 'cuenta');

            return $this->error((string) $reason, 403);
        }

        // Verificacion en dos pasos: se devuelve un desafio opaco en lugar del
        // token. El cliente debe llamar a /api/v1/auth/two-factor/verify.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $challengeId = $this->challenges->create($user, $deviceName, $request->ip());

            $this->logAttempt($user, $email, $deviceName, $request, true, '2fa-requerido');

            return response()->json([
                'status' => 'error',
                'message' => 'Codigo de verificacion en dos pasos requerido.',
                'errors' => [],
                'data' => [
                    'two_factor_required' => true,
                    'challenge_id' => $challengeId,
                    'expires_in' => TwoFactorChallengeService::TTL_SECONDS,
                ],
            ], 422);
        }

        $this->logAttempt($user, $email, $deviceName, $request, true, 'login');

        return $this->respondWithSession($user, $deviceName, 'Sesion iniciada.');
    }

    /**
     * POST /api/v1/auth/two-factor/verify
     */
    public function twoFactorVerify(TwoFactorVerifyRequest $request): JsonResponse
    {
        $challengeId = $request->challengeId();
        $challenge = $this->challenges->find($challengeId);

        if ($challenge === null) {
            return $this->error('El desafio de verificacion expiro. Inicia sesion nuevamente.', 401);
        }

        $user = User::find($challenge['user_id'] ?? 0);

        if (! $user instanceof User) {
            $this->challenges->forget($challengeId);

            return $this->error('El desafio de verificacion expiro. Inicia sesion nuevamente.', 401);
        }

        [$allowed, $reason] = $this->auth->accountStatus($user);

        if (! $allowed) {
            $this->challenges->forget($challengeId);

            return $this->error((string) $reason, 403);
        }

        $code = $request->code();

        if (! $this->verifyTwoFactorCode($user, $code)) {
            $this->logAttempt($user, (string) $user->email, (string) ($challenge['device_name'] ?? ''), $request, false, '2fa');

            return response()->json([
                'status' => 'error',
                'message' => 'El codigo de verificacion no es valido.',
                'errors' => [
                    'code' => ['El codigo de verificacion no es valido.'],
                ],
            ], 422);
        }

        // Un unico uso por desafio.
        $this->challenges->forget($challengeId);

        $this->logAttempt($user, (string) $user->email, (string) ($challenge['device_name'] ?? ''), $request, true, '2fa');

        return $this->respondWithSession(
            $user,
            (string) ($challenge['device_name'] ?? ''),
            'Sesion iniciada.'
        );
    }

    /**
     * POST /api/v1/auth/refresh
     *
     * Rota el token: emite uno nuevo con las mismas abilities y revoca el
     * utilizado.
     */
    public function refresh(RefreshRequest $request): JsonResponse
    {
        $plainToken = $request->plainToken();

        if ($plainToken === '') {
            return $this->error('Token de API invalido o expirado.', 401);
        }

        $token = PersonalAccessToken::findToken($plainToken);

        if (! $token instanceof PersonalAccessToken) {
            return $this->error('Token de API invalido o expirado.', 401);
        }

        if ($this->tokens->isExpired($token)) {
            $token->delete();

            return $this->error('El token expiro. Inicia sesion nuevamente.', 401);
        }

        $user = $token->tokenable;

        if (! $user instanceof User) {
            $token->delete();

            return $this->error('Token de API invalido o expirado.', 401);
        }

        [$allowed, $reason] = $this->auth->accountStatus($user);

        if (! $allowed) {
            return $this->error((string) $reason, 403);
        }

        $rotated = $this->tokens->rotate($token);

        return $this->respondWithSession($user, (string) $rotated['name'], 'Token renovado.', $rotated);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->attributes->get('api_token');
        $revoked = 0;

        if ($request->boolean('all_devices') && $user instanceof User) {
            // Cierre de sesion en todos los dispositivos del usuario.
            $revoked = $this->tokens->revokeAll($user);
        } elseif ($user instanceof User && $request->filled('device_name')) {
            // Cierre de sesion en un dispositivo concreto.
            $revoked = $this->tokens->revokeForDevice($user, (string) $request->input('device_name'));
        } elseif ($token instanceof PersonalAccessToken) {
            $this->tokens->revoke($token);
            $revoked = 1;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sesion cerrada.',
            'data' => [
                'revoked_tokens' => $revoked,
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->error('Unauthenticated.', 401);
        }

        $token = $request->attributes->get('api_token');
        $abilities = $token instanceof PersonalAccessToken
            ? ($token->abilities ?? [])
            : $this->tokens->defaultAbilitiesFor($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Sesion activa.',
            'data' => $this->auth->sessionContext($user, $abilities, $token),
        ]);
    }

    /**
     * Emite el token y arma la respuesta de sesion.
     *
     * @param  array<string, mixed>|null  $issued
     */
    private function respondWithSession(User $user, string $deviceName, string $message, ?array $issued = null): JsonResponse
    {
        $issued ??= $this->tokens->issue(
            $user,
            $deviceName,
            $this->tokens->defaultAbilitiesFor($user)
        );

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'token' => $issued['token'],
                'token_type' => 'Bearer',
                'abilities' => $issued['abilities'],
                'expires_at' => $issued['expires_at'],
                'user' => $this->auth->userPayload($user),
                'tenant' => $this->auth->tenantPayload($user),
                'trial' => $this->auth->trialPayload($user),
            ],
        ]);
    }

    /**
     * Verifica un codigo TOTP o un codigo de recuperacion.
     */
    private function verifyTwoFactorCode(User $user, string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        // Los codigos de recuperacion no son numericos.
        if (! preg_match('/^\d{6}$/', $code) && $this->consumeRecoveryCode($user, $code)) {
            return true;
        }

        if (empty($user->two_factor_secret)) {
            return false;
        }

        try {
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

            return (bool) app(TwoFactorAuthenticationProvider::class)->verify($secret, $code);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Consume un codigo de recuperacion (si es valido).
     */
    private function consumeRecoveryCode(User $user, string $code): bool
    {
        try {
            $recoveryCodes = $user->recoveryCodes() ?? [];
        } catch (\Throwable) {
            return false;
        }

        if (! in_array($code, $recoveryCodes, true)) {
            return false;
        }

        $user->replaceRecoveryCode($code);

        return true;
    }

    /**
     * Auditoria de accesos a la API (nunca se registra la contrasena).
     */
    private function logAttempt(
        ?User $user,
        string $email,
        string $deviceName,
        Request $request,
        bool $success,
        string $stage
    ): void {
        Log::info('api.auth.attempt', [
            'user_id' => $user?->id,
            'email' => $user?->email ?? $email,
            'ip' => $request->ip(),
            'device_name' => $deviceName,
            'stage' => $stage,
            'success' => $success,
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function error(string $message, int $status = 422, array $errors = []): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
