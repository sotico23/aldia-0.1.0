<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('VerifyTenantToken middleware rejects query string api_token', function () {
    $user = User::factory()->create([
        'api_token' => 'test-api-token-123',
    ]);

    // Try with query string - should fail (401)
    $response = $this->getJson('/api/tenant/resumen-completo?api_token=test-api-token-123');
    $response->assertStatus(401);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Token de API no proporcionado. Use la cabecera Authorization: Bearer <token>.');
});

test('VerifyTenantToken middleware accepts Bearer token in Authorization header', function () {
    $user = User::factory()->create([
        'api_token' => 'test-api-token-123',
    ]);

    $response = $this->getJson('/api/tenant/resumen-completo', [
        'Authorization' => 'Bearer test-api-token-123',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
});

test('VerifyTenantToken middleware rejects invalid token', function () {
    $response = $this->getJson('/api/tenant/resumen-completo', [
        'Authorization' => 'Bearer invalid-token',
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Token de API inválido.');
});

test('VerifyTenantToken middleware rejects inactive user', function () {
    $user = User::factory()->create([
        'api_token' => 'test-api-token-123',
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/tenant/resumen-completo', [
        'Authorization' => 'Bearer test-api-token-123',
    ]);

    $response->assertStatus(403);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Cuenta desactivada. Contacta al administrador.');
});

test('VerifyTenantToken middleware rejects missing token', function () {
    $response = $this->getJson('/api/tenant/resumen-completo');

    $response->assertStatus(401);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Token de API no proporcionado. Use la cabecera Authorization: Bearer <token>.');
});
