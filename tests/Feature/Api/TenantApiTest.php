<?php

use App\Jobs\SendToN8nJob;
use App\Models\Appointment;
use App\Models\AutomationConfig;
use App\Models\AutomationExecution;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\Compra;
use App\Models\GastoProyecto;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Tesoreria;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = User::factory()->create([
        'business_name' => 'Tienda Test',
        'api_token' => Str::random(60),
    ]);

    $this->master = User::factory()->create([
        'name' => 'Master',
        'creator_id' => null,
    ]);

    $this->invalidToken = Str::random(60);
});

// ─── VerifyTenantToken Middleware ───────────────────────────────

test('tenant endpoint rejects request without token', function () {
    $response = $this->getJson('/api/tenant/resumen-completo');

    $response->assertStatus(401);
    $response->assertJson(['success' => false, 'message' => 'Token de API no proporcionado. Use la cabecera Authorization: Bearer <token>.']);
});

test('tenant endpoint rejects request with invalid token', function () {
    $response = $this->getJson('/api/tenant/resumen-completo', [
        'Authorization' => 'Bearer '.$this->invalidToken,
    ]);

    $response->assertStatus(401);
    $response->assertJson(['success' => false, 'message' => 'Token de API inválido.']);
});

test('tenant endpoint rejects query param token (security hardening)', function () {
    $response = $this->getJson('/api/tenant/resumen-completo?api_token='.$this->tenant->api_token);

    $response->assertStatus(401);
    $response->assertJson(['success' => false, 'message' => 'Token de API no proporcionado. Use la cabecera Authorization: Bearer <token>.']);
});

test('tenant endpoint accepts Bearer token', function () {
    $response = $this->getJson('/api/tenant/resumen-completo', [
        'Authorization' => 'Bearer '.$this->tenant->api_token,
    ]);

    $response->assertOk();
});

// ─── TenantDataController::resumenCompleto ─────────────────────

test('resumenCompleto returns full summary with all KPIs', function () {
    $ownerId = $this->tenant->getOwnerId();

    Cliente::factory()->count(5)->create(['owner_id' => $ownerId]);
    Cliente::factory()->create(['owner_id' => $ownerId, 'created_at' => now()->subMonths(2)]);

    Venta::factory()->count(3)->create([
        'owner_id' => $ownerId,
        'estado' => 'pagada',
        'total' => 100,
    ]);

    $producto1 = Producto::factory()->create([
        'owner_id' => $ownerId,
        'categoria_id' => null,
        'precio_compra' => 50,
    ]);
    Inventario::factory()->create([
        'owner_id' => $ownerId,
        'producto_id' => $producto1->id,
        'cantidad' => 10,
        'cantidad_minima' => 5,
    ]);

    $producto2 = Producto::factory()->create([
        'owner_id' => $ownerId,
        'categoria_id' => null,
        'precio_compra' => 30,
    ]);
    Inventario::factory()->create([
        'owner_id' => $ownerId,
        'producto_id' => $producto2->id,
        'cantidad' => 2,
        'cantidad_minima' => 5,
    ]);

    $user = User::factory()->create(['creator_id' => $this->tenant->id]);
    Appointment::factory()->create([
        'owner_id' => $ownerId,
        'client_id' => $user->id,
        'provider_id' => $user->id,
        'producto_id' => $producto1->id,
        'start_time' => now()->addHour(),
        'status' => 'pendiente',
    ]);

    Tesoreria::factory()->create([
        'owner_id' => $ownerId,
        'tipo' => 'ingreso',
        'monto' => 500,
    ]);
    Tesoreria::factory()->create([
        'owner_id' => $ownerId,
        'tipo' => 'egreso',
        'monto' => 200,
    ]);

    Cobranza::create([
        'owner_id' => $ownerId,
        'cliente_id' => 'Cliente Test',
        'factura_id' => 'FAC-001',
        'metodo_pago' => 'transferencia',
        'estado' => 'pendiente',
        'monto' => 300,
        'fecha_pago' => now(),
    ]);

    Pago::create([
        'owner_id' => $ownerId,
        'proveedor_id' => 'Proveedor Test',
        'metodo_pago' => 'transferencia',
        'factura_id' => 'FAC-002',
        'estado' => 'pendiente',
        'monto' => 150,
        'fecha_pago' => now(),
    ]);

    GastoProyecto::factory()->create([
        'owner_id' => $ownerId,
        'monto' => 80,
    ]);

    Compra::factory()->create([
        'owner_id' => $ownerId,
    ]);

    $response = $this->getJson('/api/tenant/resumen-completo', [
        'Authorization' => 'Bearer '.$this->tenant->api_token,
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'tenant' => ['id', 'name', 'business_name', 'email'],
        'summary' => [
            'sales_today', 'sales_month', 'sales_month_total',
            'inventory_total', 'inventory_low_stock',
            'appointments_today', 'appointments_pending',
            'customers_total', 'customers_new_month',
            'cash_flow_income', 'cash_flow_expense',
            'accounts_receivable', 'accounts_payable',
            'expenses_month', 'purchases_month',
        ],
        'generated_at',
    ]);

    $json = $response->json();
    expect($json['tenant']['business_name'])->toBe('Tienda Test');
    expect($json['summary']['customers_total'])->toBe(6);
    expect($json['summary']['customers_new_month'])->toBe(5);
    expect($json['summary']['sales_month'])->toBe(3);
    expect($json['summary']['sales_month_total'])->toBe(300);
    expect($json['summary']['cash_flow_income'])->toBe(500);
    expect($json['summary']['cash_flow_expense'])->toBe(200);
    expect($json['summary']['accounts_receivable'])->toBe(300);
    expect($json['summary']['accounts_payable'])->toBe(150);
});

// ─── SendToN8nJob ──────────────────────────────────────────────

test('SendToN8nJob sends payload to tenant webhook URL', function () {
    Config::set('services.n8n.webhook_url', null);

    Http::fake();

    AutomationConfig::create([
        'owner_id' => $this->tenant->id,
        'channel' => 'telegram',
        'frequency' => 'daily',
        'enabled' => true,
        'n8n_webhook_url' => 'https://n8n.example.com/tenant-webhook',
    ]);

    SendToN8nJob::dispatch([
        'business_id' => $this->tenant->id,
        'event' => 'test',
    ], $this->tenant->id, 'test-workflow');

    SendToN8nJob::dispatchSync([
        'business_id' => $this->tenant->id,
        'event' => 'test',
    ], $this->tenant->id, 'test-workflow');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://n8n.example.com/tenant-webhook'
            && $request['business_id'] === $this->tenant->id
            && $request['event'] === 'test';
    });
});

test('SendToN8nJob falls back to global webhook URL when tenant has none', function () {
    Config::set('services.n8n.webhook_url', 'https://n8n-global.example.com/hook');

    Http::fake();

    // No AutomationConfig for this tenant

    SendToN8nJob::dispatchSync(['key' => 'value'], $this->tenant->id, 'fallback-test');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://n8n-global.example.com/hook';
    });
});

test('SendToN8nJob logs execution on success', function () {
    Config::set('services.n8n.webhook_url', 'https://n8n.example.com/hook');

    Http::fake([
        'n8n.example.com/*' => Http::response(['ok' => true], 200),
    ]);

    SendToN8nJob::dispatchSync(['msg' => 'hello'], $this->tenant->id, 'report-workflow');

    $execution = AutomationExecution::where('owner_id', $this->tenant->id)->first();

    expect($execution)->not->toBeNull();
    expect($execution->workflow)->toBe('report-workflow');
    expect($execution->status)->toBe('success');
    expect($execution->triggered_by)->toBe('job');
    expect($execution->payload)->toBe(['msg' => 'hello']);
});

test('SendToN8nJob logs execution on failure', function () {
    Config::set('services.n8n.webhook_url', 'https://n8n.example.com/hook');

    Http::fake([
        'n8n.example.com/*' => Http::response('Server Error', 500),
    ]);

    SendToN8nJob::dispatchSync(['msg' => 'fail'], $this->tenant->id, 'failing-workflow');

    $execution = AutomationExecution::where('owner_id', $this->tenant->id)->first();

    expect($execution)->not->toBeNull();
    expect($execution->workflow)->toBe('failing-workflow');
    expect($execution->status)->toBe('failed');
    expect($execution->error_message)->toBe('Server Error');
});

test('SendToN8nJob skips when no webhook URL is configured', function () {
    Config::set('services.n8n.webhook_url', null);

    Http::fake();

    SendToN8nJob::dispatchSync(['msg' => 'no-url'], $this->tenant->id, 'no-url-test');

    Http::assertNothingSent();
});
