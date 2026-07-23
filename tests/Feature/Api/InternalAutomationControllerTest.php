<?php

use App\Models\AutomationConfig;
use App\Models\AutomationExecution;
use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\User;
use App\Services\AutomationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createN8nApiKey(): void
{
    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'webhook_url' => 'https://n8n.example.com/webhook/test',
        'api_key' => 'valid-n8n-api-key',
        'is_active' => true,
    ]);
}

function apiAuthHeaders(): array
{
    return ['X-API-Key' => 'valid-n8n-api-key'];
}

test('getConfig returns business data with config and credentials', function () {
    createN8nApiKey();

    $user = User::factory()->create();

    ChannelCredential::create([
        'owner_id' => $user->id,
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    AutomationConfig::create([
        'owner_id' => $user->id,
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['ventas', 'inventario'],
    ]);

    $response = $this->getJson("/api/v1/automation/config/{$user->id}", apiAuthHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
        'config' => [
            'channel' => 'telegram',
            'frequency' => 'daily',
            'execution_time' => '08:00',
            'enabled' => true,
            'selected_reports' => ['ventas', 'inventario'],
        ],
        'credentials' => [
            'telegram_bot_username' => 'test_bot',
        ],
    ]);
});

test('getConfig returns null config when business has no automation config', function () {
    createN8nApiKey();

    $user = User::factory()->create();

    $response = $this->getJson("/api/v1/automation/config/{$user->id}", apiAuthHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'config' => null,
        'credentials' => null,
    ]);
});

test('getConfig returns 404 for non-existent business', function () {
    createN8nApiKey();

    $response = $this->getJson('/api/v1/automation/config/99999', apiAuthHeaders());

    $response->assertNotFound();
    $response->assertJson([
        'success' => false,
        'message' => 'Negocio no encontrado.',
    ]);
});

test('getReports returns response structure', function () {
    createN8nApiKey();

    $user = User::factory()->create();

    $this->mock(AutomationReportService::class, function ($mock) use ($user) {
        $mock->shouldReceive('generate')
            ->once()
            ->with($user->id, ['resumen_ejecutivo'])
            ->andReturn([
                'resumen_ejecutivo' => ['total_ventas' => 5, 'total_gastos' => 3],
            ]);
    });

    $response = $this->getJson("/api/v1/automation/reports/{$user->id}?reports[]=resumen_ejecutivo", apiAuthHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $user->id,
        'reports' => [
            'resumen_ejecutivo' => ['total_ventas' => 5, 'total_gastos' => 3],
        ],
    ]);
});

test('internal endpoints return 401 without API key', function () {
    $response = $this->getJson('/api/v1/automation/config/1');

    $response->assertUnauthorized();
    $response->assertJson([
        'success' => false,
        'message' => 'API Key no proporcionada.',
    ]);
});

test('internal endpoints return 401 with invalid API key', function () {
    createN8nApiKey();

    $response = $this->getJson('/api/v1/automation/config/1', [
        'X-API-Key' => 'invalid-key',
    ]);

    $response->assertUnauthorized();
    $response->assertJson([
        'success' => false,
        'message' => 'API Key inválida.',
    ]);
});

test('send endpoint returns 422 when no credentials configured', function () {
    createN8nApiKey();

    $user = User::factory()->create();
    $execution = AutomationExecution::create([
        'owner_id' => $user->id,
        'workflow' => 'automation',
        'status' => 'processing',
        'triggered_by' => 'scheduler',
        'executed_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/automation/send', [
        'execution_id' => $execution->id,
        'business_id' => $user->id,
        'channel' => 'telegram',
        'message' => 'Test message',
    ], apiAuthHeaders());

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No channel credentials configured for this business.',
    ]);
});

test('send endpoint returns 422 with invalid payload', function () {
    createN8nApiKey();

    $response = $this->postJson('/api/v1/automation/send', [], apiAuthHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['execution_id', 'business_id', 'channel', 'message']);
});

test('send endpoint returns 422 when execution does not exist', function () {
    createN8nApiKey();

    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/automation/send', [
        'execution_id' => 99999,
        'business_id' => $user->id,
        'channel' => 'telegram',
        'message' => 'Test message',
    ], apiAuthHeaders());

    $response->assertStatus(422);
});

test('send endpoint returns 401 without API key', function () {
    $response = $this->postJson('/api/v1/automation/send', [
        'execution_id' => 1,
        'business_id' => 1,
        'channel' => 'telegram',
        'message' => 'Test',
    ]);

    $response->assertUnauthorized();
});

test('health endpoint returns system status', function () {
    createN8nApiKey();

    $response = $this->getJson('/api/v1/automation/health', apiAuthHeaders());

    $response->assertOk();
    $response->assertJsonStructure([
        'status',
        'timestamp',
        'metrics' => [
            'pending_jobs',
            'failed_jobs',
            'today_executions',
            'failed_executions',
            'average_execution_time_ms',
            'success_rate_percent',
            'stale_reserved_jobs',
        ],
        'issues',
    ]);
    $response->assertJson([
        'metrics' => [
            'pending_jobs' => 0,
            'failed_jobs' => 0,
        ],
    ]);
});

test('health endpoint returns 401 without API key', function () {
    $response = $this->getJson('/api/v1/automation/health');

    $response->assertUnauthorized();
});
