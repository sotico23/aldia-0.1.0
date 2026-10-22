<?php

/**
 * Tests de la API de autenticacion para clientes no-browser (Ola 1).
 *
 * Cubre `/api/v1/auth/{login,two-factor/verify,refresh,logout,me}`:
 *  - Emision de Personal Access Tokens con abilities de solo lectura.
 *  - Desafio de verificacion en dos pasos (TOTP + codigos de recuperacion).
 *  - Rotacion y revocacion de tokens.
 *  - Aislamiento de tenant en la respuesta.
 */

use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

/**
 * Crea un usuario con 2FA habilitado y devuelve [usuario, secreto, motor].
 *
 * @return array{0: User, 1: string, 2: Google2FA}
 */
function authTestEnableTwoFactor(User $user): array
{
    $engine = app(Google2FA::class);
    $secret = $engine->generateSecretKey();

    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['codigo-recuperacion-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user->fresh(), $secret, $engine];
}

beforeEach(function () {
    // El primer usuario creado absorbe el rol Super Admin; se crea un dummy
    // para que el dueño del tenant no sea un usuario de plataforma.
    User::factory()->create(['email' => 'dummy-auth-setup@test.local']);

    $this->owner = User::factory()->create([
        'name' => 'Dueño Empresa',
        'email' => 'dueno@empresa.cl',
        'password' => Hash::make('secreto123'),
        'business_name' => 'Tenant A',
    ]);

    $this->otherOwner = User::factory()->create([
        'email' => 'otro@empresa.cl',
        'password' => Hash::make('secreto123'),
        'business_name' => 'Tenant B',
    ]);

    $this->tokenService = app(ApiTokenService::class);
});

// ============================================================================
// Login
// ============================================================================

test('el login devuelve un token con el envelope de exito', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
        'device_name' => 'pixel-8',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Sesion iniciada.')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'token',
                'token_type',
                'abilities',
                'expires_at',
                'user' => ['id', 'name', 'email', 'business_name', 'is_active'],
                'tenant' => ['id', 'name', 'business_name'],
            ],
        ]);

    expect($response->json('data.user.id'))->toBe($this->owner->id);
    expect($response->json('data.tenant.id'))->toBe($this->owner->id);
    // Sanctum 4 devuelve el token con el formato "<id>|<token>".
    expect($response->json('data.token'))->toContain(config('sanctum.token_prefix', 'aldia_'));
    expect($response->json('data.token'))->toMatch('/^\d+\|'.config('sanctum.token_prefix', 'aldia_').'/');
});

test('el login es case-insensitive con el email', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'DUENO@EMPRESA.CL',
        'password' => 'secreto123',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('el login no revela si el email esta registrado', function () {
    $emailInexistente = $this->postJson('/api/v1/auth/login', [
        'email' => 'nadie@empresa.cl',
        'password' => 'secreto123',
    ]);

    $emailExistente = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'clave-equivocada',
    ]);

    $emailInexistente->assertStatus(401)->assertJsonPath('status', 'error');
    $emailExistente->assertStatus(401)->assertJsonPath('status', 'error');

    expect($emailInexistente->json('message'))->toBe($emailExistente->json('message'));
});

test('el login valida el formato del email', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'no-es-un-email',
        'password' => 'secreto123',
    ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Datos inválidos.')
        ->assertJsonStructure(['status', 'message', 'errors' => ['email']]);
});

test('el login exige email y contrasena', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonStructure(['status', 'message', 'errors' => ['email', 'password']]);
});

test('el login rechaza una cuenta desactivada', function () {
    $this->owner->forceFill(['is_active' => false])->save();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
    ])
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

test('el login rechaza una cuenta bloqueada', function () {
    $this->owner->forceFill(['banned_at' => now()])->save();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
    ])
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

test('el login no expone el api_token ni la contrasena', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
    ]);

    $body = $response->getContent();

    expect($body)->not->toContain('api_token');
    expect($body)->not->toContain('secreto123');
    expect($body)->not->toContain('password');
});

test('el token emitido solo trae abilities de lectura', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
    ]);

    $abilities = $response->json('data.abilities');

    expect($abilities)->toContain('agent:read');
    expect($abilities)->toContain('catalog:read');
    expect($abilities)->toContain('reports:read');
    expect($abilities)->toContain('delivery:read');
    expect($abilities)->not->toContain('agent:write');
    expect($abilities)->not->toContain('admin:write');
});

test('el token emitido queda registrado con el nombre del dispositivo', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
        'device_name' => 'pixel-8',
    ]);

    $pat = PersonalAccessToken::where('tokenable_id', $this->owner->id)->first();

    expect($pat)->not->toBeNull();
    expect($pat->name)->toBe('pixel-8');
    expect($pat->abilities)->toContain('agent:read');
    expect($response->json('data.expires_at'))->not->toBeNull();
});

// ============================================================================
// Verificacion en dos pasos
// ============================================================================

test('el login con 2FA devuelve un desafio y no un token', function () {
    [$user] = authTestEnableTwoFactor($this->owner);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
        'device_name' => 'pixel-8',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('data.two_factor_required', true)
        ->assertJsonStructure(['status', 'message', 'data' => ['two_factor_required', 'challenge_id', 'expires_in']]);

    expect($response->json('data.token'))->toBeNull();
    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(0);
});

test('la verificacion 2FA con el codigo correcto entrega el token', function () {
    [$user, $secret, $engine] = authTestEnableTwoFactor($this->owner);

    $challengeId = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
        'device_name' => 'pixel-8',
    ])->json('data.challenge_id');

    $response = $this->postJson('/api/v1/auth/two-factor/verify', [
        'challenge_id' => $challengeId,
        'code' => $engine->getCurrentOtp($secret),
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.user.id', $user->id);

    expect($response->json('data.token'))->not->toBeEmpty();
    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(1);
});

test('la verificacion 2FA con codigo incorrecto responde 422 y permite reintentar', function () {
    [$user, $secret, $engine] = authTestEnableTwoFactor($this->owner);

    $challengeId = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
    ])->json('data.challenge_id');

    $this->postJson('/api/v1/auth/two-factor/verify', [
        'challenge_id' => $challengeId,
        'code' => '000000',
    ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonStructure(['status', 'message', 'errors' => ['code']]);

    // El desafio sigue vivo: un codigo correcto despues funciona.
    $this->postJson('/api/v1/auth/two-factor/verify', [
        'challenge_id' => $challengeId,
        'code' => $engine->getCurrentOtp($secret),
    ])->assertOk();
});

test('la verificacion 2FA con un desafio desconocido responde 401', function () {
    $this->postJson('/api/v1/auth/two-factor/verify', [
        'challenge_id' => 'desafio-que-no-existe',
        'code' => '123456',
    ])
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('la verificacion 2FA acepta un codigo de recuperacion', function () {
    [$user] = authTestEnableTwoFactor($this->owner);

    $challengeId = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
    ])->json('data.challenge_id');

    $this->postJson('/api/v1/auth/two-factor/verify', [
        'challenge_id' => $challengeId,
        'code' => 'codigo-recuperacion-1',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('el desafio 2FA es de un solo uso', function () {
    [$user, $secret, $engine] = authTestEnableTwoFactor($this->owner);

    $challengeId = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
    ])->json('data.challenge_id');

    $this->postJson('/api/v1/auth/two-factor/verify', [
        'challenge_id' => $challengeId,
        'code' => $engine->getCurrentOtp($secret),
    ])->assertOk();

    $this->postJson('/api/v1/auth/two-factor/verify', [
        'challenge_id' => $challengeId,
        'code' => $engine->getCurrentOtp($secret),
    ])->assertStatus(401);
});

test('la verificacion 2FA exige el desafio y el codigo', function () {
    $this->postJson('/api/v1/auth/two-factor/verify', [])
        ->assertStatus(422)
        ->assertJsonStructure(['status', 'message', 'errors' => ['challenge_id', 'code']]);
});

// ============================================================================
// Refresh
// ============================================================================

test('el refresh rota el token y revoca el anterior', function () {
    $issued = $this->tokenService->issue($this->owner, 'pixel-8', ['agent:read']);

    $response = $this->postJson('/api/v1/auth/refresh', ['token' => $issued['token']]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.abilities', ['agent:read']);

    $nuevo = $response->json('data.token');

    expect($nuevo)->not->toBe($issued['token']);
    expect(PersonalAccessToken::find($issued['id']))->toBeNull();

    // El token nuevo funciona y el viejo ya no.
    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$nuevo])
        ->assertOk();

    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$issued['token']])
        ->assertStatus(401);
});

test('el refresh acepta el token en la cabecera Authorization', function () {
    $issued = $this->tokenService->issue($this->owner, 'pixel-8', ['agent:read']);

    $this->postJson('/api/v1/auth/refresh', [], ['Authorization' => 'Bearer '.$issued['token']])
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('el refresh con un token invalido responde 401', function () {
    $this->postJson('/api/v1/auth/refresh', ['token' => 'token-que-no-existe'])
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('el refresh con un token expirado responde 401', function () {
    $issued = $this->tokenService->issue(
        $this->owner,
        'pixel-8',
        ['agent:read'],
        now()->subMinute()
    );

    $this->postJson('/api/v1/auth/refresh', ['token' => $issued['token']])
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('el refresh de un usuario desactivado responde 403', function () {
    $issued = $this->tokenService->issue($this->owner, 'pixel-8', ['agent:read']);

    $this->owner->forceFill(['is_active' => false])->save();

    $this->postJson('/api/v1/auth/refresh', ['token' => $issued['token']])
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

// ============================================================================
// Logout
// ============================================================================

test('el logout revoca el token actual', function () {
    $issued = $this->tokenService->issue($this->owner, 'pixel-8', ['agent:read']);

    $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$issued['token']])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.revoked_tokens', 1);

    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$issued['token']])
        ->assertStatus(401);
});

test('el logout con all_devices revoca todos los tokens del usuario', function () {
    $issued = $this->tokenService->issue($this->owner, 'pixel-8', ['agent:read']);
    $otro = $this->tokenService->issue($this->owner, 'tablet', ['agent:read']);

    $this->postJson(
        '/api/v1/auth/logout',
        ['all_devices' => true],
        ['Authorization' => 'Bearer '.$issued['token']]
    )
        ->assertOk()
        ->assertJsonPath('data.revoked_tokens', 2);

    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$otro['token']])
        ->assertStatus(401);
});

test('el logout con device_name revoca solo los tokens de ese dispositivo', function () {
    $issued = $this->tokenService->issue($this->owner, 'pixel-8', ['agent:read']);
    $otro = $this->tokenService->issue($this->owner, 'tablet', ['agent:read']);

    $this->postJson(
        '/api/v1/auth/logout',
        ['device_name' => 'pixel-8'],
        ['Authorization' => 'Bearer '.$issued['token']]
    )
        ->assertOk()
        ->assertJsonPath('data.revoked_tokens', 1);

    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$issued['token']])
        ->assertStatus(401);

    // El token del otro dispositivo sigue vigente.
    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$otro['token']])
        ->assertOk();
});

test('el logout exige un token valido', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

// ============================================================================
// Me
// ============================================================================

test('me devuelve el contexto de la sesion', function () {
    $issued = $this->tokenService->issue($this->owner, 'pixel-8', ['agent:read', 'reports:read']);

    $response = $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$issued['token']]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.user.id', $this->owner->id)
        ->assertJsonPath('data.tenant.id', $this->owner->id)
        ->assertJsonPath('data.token.name', 'pixel-8')
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'roles'],
                'tenant' => ['id', 'name', 'business_name'],
                'abilities',
                'modules',
                'tools_disponibles',
                'trial' => ['is_active', 'is_expired', 'days_remaining'],
                'token' => ['name', 'expires_at'],
            ],
        ]);

    expect($response->json('data.abilities'))->toBe(['agent:read', 'reports:read']);
    expect($response->json('data.tools_disponibles'))->toContain('bot.clientes.list');
});

test('me de un empleado devuelve el tenant del dueno', function () {
    $empleado = User::factory()->create(['creator_id' => $this->owner->id]);

    $issued = $this->tokenService->issue($empleado, 'pixel-8', ['agent:read']);

    $response = $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$issued['token']]);

    $response->assertOk()
        ->assertJsonPath('data.user.id', $empleado->id)
        ->assertJsonPath('data.tenant.id', $this->owner->id);
});

test('me sin token responde 401', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

// ============================================================================
// Integracion con la Tool API
// ============================================================================

test('el token emitido por el login sirve para la Tool API', function () {
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
    ])->json('data.token');

    $this->getJson('/api/v1/bot/clientes', [
        'Authorization' => 'Bearer '.$token,
        'X-Owner-ID' => (string) $this->owner->id,
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('el token emitido por el login NO puede escribir en la Tool API', function () {
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'dueno@empresa.cl',
        'password' => 'secreto123',
    ])->json('data.token');

    $this->postJson('/api/v1/bot/clientes', [
        'nombre' => 'Cliente nuevo',
        'email' => 'nuevo@correo.cl',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Owner-ID' => (string) $this->owner->id,
    ])
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

test('un token de otro tenant no opera sobre el tenant indicado', function () {
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'otro@empresa.cl',
        'password' => 'secreto123',
    ])->json('data.token');

    $this->getJson('/api/v1/bot/clientes', [
        'Authorization' => 'Bearer '.$token,
        'X-Owner-ID' => (string) $this->owner->id,
    ])
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});
