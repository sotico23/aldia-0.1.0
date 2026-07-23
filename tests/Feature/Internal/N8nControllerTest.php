<?php

use App\Models\Appointment;
use App\Models\AutomationExecution;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\GastoProyecto;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Tesoreria;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.n8n.token', 'test-n8n-token-123');
});

function n8nHeaders(): array
{
    return ['X-N8N-TOKEN' => 'test-n8n-token-123'];
}

test('summary returns executive summary for business', function () {
    $business = User::factory()->create(['business_name' => 'Mi Empresa']);
    Cliente::factory()->count(3)->create(['owner_id' => $business->id]);
    Cliente::factory()->count(2)->create([
        'owner_id' => $business->id,
        'created_at' => now()->subMonths(2),
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/summary", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business' => [
            'id' => $business->id,
            'name' => 'Mi Empresa',
        ],
        'summary' => [
            'sales_today' => 0,
            'sales_month' => 0,
            'inventory_total' => 0,
            'inventory_low_stock' => 0,
            'appointments_today' => 0,
            'appointments_pending' => 0,
            'customers_total' => 5,
            'customers_new_month' => 3,
        ],
    ]);
    $response->assertJsonStructure(['summary', 'business', 'generated_at']);
});

test('summary returns 404 for non-existent business', function () {
    $response = $this->getJson('/api/internal/business/99999/summary', n8nHeaders());

    $response->assertNotFound();
    $response->assertJson([
        'success' => false,
        'message' => 'Business not found',
    ]);
});

test('inventory returns inventory items for business', function () {
    $business = User::factory()->create();
    $categoria = Categoria::factory()->create(['owner_id' => $business->id]);
    $producto = Producto::factory()->create([
        'owner_id' => $business->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Producto Test',
    ]);
    Inventario::factory()->create([
        'owner_id' => $business->id,
        'producto_id' => $producto->id,
        'cantidad' => 50,
        'cantidad_minima' => 10,
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/inventory", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
        'items' => [
            [
                'product_id' => $producto->id,
                'product_name' => 'Producto Test',
                'quantity' => 50,
                'min_stock' => 10,
                'is_low_stock' => false,
            ],
        ],
    ]);
});

test('inventory marks low stock correctly', function () {
    $business = User::factory()->create();
    $categoria = Categoria::factory()->create(['owner_id' => $business->id]);
    $productoLow = Producto::factory()->create([
        'owner_id' => $business->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Stock Bajo',
    ]);
    Inventario::factory()->create([
        'owner_id' => $business->id,
        'producto_id' => $productoLow->id,
        'cantidad' => 5,
        'cantidad_minima' => 10,
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/inventory", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'items' => [
            ['product_name' => 'Stock Bajo', 'is_low_stock' => true],
        ],
    ]);
});

test('sales returns recent sales for business', function () {
    $business = User::factory()->create();
    $cliente = Cliente::factory()->create([
        'owner_id' => $business->id,
        'nombre' => 'Juan Perez',
    ]);
    Venta::factory()->create([
        'owner_id' => $business->id,
        'cliente_id' => $cliente->id,
        'user_id' => $business->id,
        'total' => 15000,
        'estado' => 'pagada',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/sales", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
    ]);
    $response->assertJsonCount(1, 'sales');
    $response->assertJsonPath('sales.0.customer', 'Juan Perez');
    $response->assertJsonPath('sales.0.total', 15000);
});

test('sales respects limit parameter', function () {
    $business = User::factory()->create();
    $cliente = Cliente::factory()->create(['owner_id' => $business->id]);

    Venta::factory()->count(5)->create([
        'owner_id' => $business->id,
        'cliente_id' => $cliente->id,
        'user_id' => $business->id,
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/sales?limit=2", n8nHeaders());

    $response->assertOk();
    $response->assertJsonCount(2, 'sales');
});

test('appointments returns appointments for business', function () {
    $business = User::factory()->create();
    $client = User::factory()->create();
    $categoria = Categoria::factory()->create(['owner_id' => $business->id]);
    $producto = Producto::factory()->create([
        'owner_id' => $business->id,
        'categoria_id' => $categoria->id,
        'is_service' => true,
    ]);

    Appointment::factory()->create([
        'owner_id' => $business->id,
        'client_id' => $client->id,
        'provider_id' => $business->id,
        'producto_id' => $producto->id,
        'start_time' => now()->addHour(),
        'end_time' => now()->addHours(2),
        'status' => 'pendiente',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/appointments", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
    ]);
    $response->assertJsonCount(1, 'appointments');
    $response->assertJsonPath('appointments.0.status', 'pendiente');
});

test('appointments filters by status', function () {
    $business = User::factory()->create();
    $client = User::factory()->create();
    $categoria = Categoria::factory()->create(['owner_id' => $business->id]);
    $producto = Producto::factory()->create([
        'owner_id' => $business->id,
        'categoria_id' => $categoria->id,
        'is_service' => true,
    ]);

    Appointment::factory()->create([
        'owner_id' => $business->id,
        'client_id' => $client->id,
        'provider_id' => $business->id,
        'producto_id' => $producto->id,
        'start_time' => now()->addHour(),
        'status' => 'pendiente',
    ]);

    Appointment::factory()->create([
        'owner_id' => $business->id,
        'client_id' => $client->id,
        'provider_id' => $business->id,
        'producto_id' => $producto->id,
        'start_time' => now()->addDays(2),
        'status' => 'confirmada',
    ]);

    $response = $this->getJson(
        "/api/internal/business/{$business->id}/appointments?status=confirmada",
        n8nHeaders()
    );

    $response->assertOk();
    $response->assertJsonCount(1, 'appointments');
    $response->assertJsonPath('appointments.0.status', 'confirmada');
});

test('endpoints return 401 without valid token', function () {
    $response = $this->getJson('/api/internal/business/1/summary');

    $response->assertUnauthorized();
    $response->assertJson([
        'success' => false,
        'message' => 'Token de n8n no proporcionado.',
    ]);
});

test('endpoints return 401 with wrong token', function () {
    $response = $this->getJson('/api/internal/business/1/summary', [
        'X-N8N-TOKEN' => 'wrong-token',
    ]);

    $response->assertUnauthorized();
    $response->assertJson([
        'success' => false,
        'message' => 'Token de n8n inválido.',
    ]);
});

test('business data isolation — cannot see other business data', function () {
    $businessA = User::factory()->create();
    $businessB = User::factory()->create();

    Cliente::factory()->create(['owner_id' => $businessA->id, 'nombre' => 'Cliente de A']);
    Cliente::factory()->create(['owner_id' => $businessB->id, 'nombre' => 'Cliente de B']);

    $response = $this->getJson("/api/internal/business/{$businessA->id}/summary", n8nHeaders());

    $response->assertOk();
    $response->assertJsonPath('summary.customers_total', 1);
});

// ──────────────────────────────────────────────
// FASE 4: Financial endpoints
// ──────────────────────────────────────────────

test('cash-flow returns treasury items for business', function () {
    $business = User::factory()->create();

    Tesoreria::factory()->create([
        'owner_id' => $business->id,
        'tipo' => 'ingreso',
        'monto' => 100000,
        'categoria' => 'ventas',
        'estado' => 'confirmado',
    ]);

    Tesoreria::factory()->create([
        'owner_id' => $business->id,
        'tipo' => 'egreso',
        'monto' => 50000,
        'categoria' => 'servicios',
        'estado' => 'confirmado',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/cash-flow", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
        'summary' => [
            'total_income' => 100000,
            'total_expense' => 50000,
        ],
    ]);
    $response->assertJsonCount(2, 'items');
});

test('cash-flow filters by type', function () {
    $business = User::factory()->create();

    Tesoreria::factory()->create(['owner_id' => $business->id, 'tipo' => 'ingreso', 'monto' => 50000]);
    Tesoreria::factory()->create(['owner_id' => $business->id, 'tipo' => 'egreso', 'monto' => 30000]);

    $response = $this->getJson("/api/internal/business/{$business->id}/cash-flow?tipo=ingreso", n8nHeaders());

    $response->assertOk();
    $response->assertJsonCount(1, 'items');
    $response->assertJsonPath('items.0.type', 'ingreso');
});

test('accounts-receivable returns cobranza items', function () {
    $business = User::factory()->create();

    Cobranza::create([
        'owner_id' => $business->id,
        'monto' => 150000,
        'estado' => 'pendiente',
        'metodo_pago' => 'transferencia',
        'referencia' => 'COB-001',
    ]);

    Cobranza::create([
        'owner_id' => $business->id,
        'monto' => 250000,
        'estado' => 'completado',
        'metodo_pago' => 'efectivo',
        'referencia' => 'COB-002',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/accounts-receivable", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
        'summary' => ['total_pending' => 150000],
    ]);
    $response->assertJsonCount(2, 'items');
});

test('accounts-payable returns pago items', function () {
    $business = User::factory()->create();

    Pago::create([
        'owner_id' => $business->id,
        'monto' => 80000,
        'estado' => 'pendiente',
        'metodo_pago' => 'transferencia',
        'referencia' => 'PAG-001',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/accounts-payable", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
        'summary' => ['total_pending' => 80000],
    ]);
    $response->assertJsonCount(1, 'items');
});

test('expenses returns combined project and treasury expenses', function () {
    $business = User::factory()->create();

    GastoProyecto::create([
        'owner_id' => $business->id,
        'categoria' => 'materiales',
        'descripcion' => 'Compra de materiales',
        'monto' => 200000,
        'fecha' => now()->toDateString(),
    ]);

    Tesoreria::create([
        'owner_id' => $business->id,
        'tipo' => 'egreso',
        'monto' => 75000,
        'categoria' => 'servicios',
        'descripcion' => 'Pago servicios',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/expenses", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
        'summary' => [
            'total_project_expenses' => 200000,
            'total_treasury_expenses' => 75000,
            'total_expenses' => 275000,
        ],
    ]);
});

// ──────────────────────────────────────────────
// FASE 5: Webhook callback
// ──────────────────────────────────────────────

test('workflow-complete webhook stores execution and returns success', function () {
    $business = User::factory()->create();

    $response = $this->postJson('/api/internal/webhook/workflow-complete', [
        'business_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'success',
        'triggered_by' => 'schedule',
        'output' => ['report_url' => 'https://n8n.example.com/report/123'],
        'execution_time_ms' => 3500,
    ], n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'message' => 'Ejecución registrada correctamente.',
    ]);

    $this->assertDatabaseHas('automation_executions', [
        'owner_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'success',
        'triggered_by' => 'schedule',
    ]);
});

test('workflow-complete stores error status', function () {
    $business = User::factory()->create();

    $response = $this->postJson('/api/internal/webhook/workflow-complete', [
        'business_id' => $business->id,
        'workflow' => 'reporte-semanal',
        'status' => 'error',
        'error_message' => 'Timeout al conectar con API externa',
        'execution_time_ms' => 30000,
    ], n8nHeaders());

    $response->assertOk();

    $this->assertDatabaseHas('automation_executions', [
        'owner_id' => $business->id,
        'workflow' => 'reporte-semanal',
        'status' => 'error',
        'error_message' => 'Timeout al conectar con API externa',
    ]);
});

test('workflow-complete validates required fields', function () {
    $response = $this->postJson('/api/internal/webhook/workflow-complete', [], n8nHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['business_id', 'workflow', 'status']);
});

test('workflow-complete validates business exists', function () {
    $response = $this->postJson('/api/internal/webhook/workflow-complete', [
        'business_id' => 99999,
        'workflow' => 'test',
        'status' => 'success',
    ], n8nHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['business_id']);
});

// ──────────────────────────────────────────────
// FASE 6: Execution history
// ──────────────────────────────────────────────

test('executions returns execution history for business', function () {
    $business = User::factory()->create();

    AutomationExecution::create([
        'owner_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'success',
        'triggered_by' => 'schedule',
        'executed_at' => now(),
    ]);

    AutomationExecution::create([
        'owner_id' => $business->id,
        'workflow' => 'reporte-semanal',
        'status' => 'error',
        'triggered_by' => 'manual',
        'error_message' => 'Conexión fallida',
        'executed_at' => now()->subHour(),
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/executions", n8nHeaders());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
    ]);
    $response->assertJsonCount(2, 'executions');
});

test('executions filters by workflow and status', function () {
    $business = User::factory()->create();

    AutomationExecution::create([
        'owner_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'success',
        'executed_at' => now(),
    ]);

    AutomationExecution::create([
        'owner_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'error',
        'executed_at' => now(),
    ]);

    $response = $this->getJson(
        "/api/internal/business/{$business->id}/executions?workflow=reporte-diario&status=success",
        n8nHeaders()
    );

    $response->assertOk();
    $response->assertJsonCount(1, 'executions');
    $response->assertJsonPath('executions.0.status', 'success');
});

test('executions respects limit parameter', function () {
    $business = User::factory()->create();

    foreach (range(1, 5) as $i) {
        AutomationExecution::create([
            'owner_id' => $business->id,
            'workflow' => 'test-'.$i,
            'status' => 'success',
            'executed_at' => now(),
        ]);
    }

    $response = $this->getJson(
        "/api/internal/business/{$business->id}/executions?limit=3",
        n8nHeaders()
    );

    $response->assertOk();
    $response->assertJsonCount(3, 'executions');
});
