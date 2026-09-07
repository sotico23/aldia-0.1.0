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
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createBusinessWithN8nKey(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'n8n_api_key' => 'test-n8n-'.Str::random(16),
    ], $attributes));
}

function n8nHeaders(User $business): array
{
    return ['X-N8N-TOKEN' => $business->n8n_api_key];
}

test('summary returns executive summary for business', function () {
    $business = createBusinessWithN8nKey(['business_name' => 'Mi Empresa']);
    Cliente::factory()->count(3)->create(['owner_id' => $business->id]);
    Cliente::factory()->count(2)->create([
        'owner_id' => $business->id,
        'created_at' => now()->subMonths(2),
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/summary", n8nHeaders($business));

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
    $business = createBusinessWithN8nKey();

    $response = $this->getJson('/api/internal/business/99999/summary', n8nHeaders($business));

    $response->assertNotFound();
    $response->assertJson([
        'success' => false,
        'message' => 'Negocio no encontrado o sin API key configurada.',
    ]);
});

test('inventory returns inventory items for business', function () {
    $business = createBusinessWithN8nKey();
    $categoria = Categoria::factory()->create(['owner_id' => $business->id]);
    $producto = Producto::factory()->create([
        'owner_id' => $business->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Producto Test',
    ]);

    Inventario::factory()->create([
        'owner_id' => $business->id,
        'producto_id' => $producto->id,
        'stock_actual' => 100,
        'stock_minimo' => 10,
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/inventory", n8nHeaders($business));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
    ]);
    $response->assertJsonCount(1, 'items');
    $response->assertJsonPath('items.0.product_name', 'Producto Test');
});

test('inventory marks low stock correctly', function () {
    $business = createBusinessWithN8nKey();
    $categoria = Categoria::factory()->create(['owner_id' => $business->id]);
    $productoNormal = Producto::factory()->create([
        'owner_id' => $business->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Normal',
    ]);
    $productoBajo = Producto::factory()->create([
        'owner_id' => $business->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Stock Bajo',
    ]);

    Inventario::factory()->create([
        'owner_id' => $business->id,
        'producto_id' => $productoNormal->id,
        'stock_actual' => 50,
        'stock_minimo' => 10,
    ]);
    Inventario::factory()->create([
        'owner_id' => $business->id,
        'producto_id' => $productoBajo->id,
        'stock_actual' => 5,
        'stock_minimo' => 10,
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/inventory", n8nHeaders($business));

    $response->assertOk();
    $lowStockItem = collect($response->json('items'))->firstWhere('product_name', 'Stock Bajo');
    $this->assertTrue($lowStockItem['is_low_stock']);
});

test('sales returns recent sales for business', function () {
    $business = createBusinessWithN8nKey();
    $cliente = Cliente::factory()->create(['owner_id' => $business->id, 'nombre' => 'Cliente Test']);

    Venta::factory()->count(3)->create([
        'owner_id' => $business->id,
        'cliente_id' => $cliente->id,
        'total' => 10000,
        'estado' => 'completado',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/sales", n8nHeaders($business));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
    ]);
    $response->assertJsonCount(3, 'sales');
});

test('sales respects limit parameter', function () {
    $business = createBusinessWithN8nKey();
    $cliente = Cliente::factory()->create(['owner_id' => $business->id]);

    Venta::factory()->count(5)->create([
        'owner_id' => $business->id,
        'cliente_id' => $cliente->id,
        'total' => 10000,
        'estado' => 'completado',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/sales?limit=2", n8nHeaders($business));

    $response->assertOk();
    $response->assertJsonCount(2, 'sales');
});

test('appointments returns appointments for business', function () {
    $business = createBusinessWithN8nKey();
    $categoria = Categoria::factory()->create(['owner_id' => $business->id, 'servicio_tipo' => 'servicio']);
    $servicio = Producto::factory()->create([
        'owner_id' => $business->id,
        'categoria_id' => $categoria->id,
        'es_servicio' => true,
        'duracion_minutos' => 30,
        'nombre' => 'Corte de pelo',
    ]);
    $cliente = Cliente::factory()->create(['owner_id' => $business->id]);

    Appointment::factory()->count(2)->create([
        'owner_id' => $business->id,
        'cliente_id' => $cliente->id,
        'servicio_id' => $servicio->id,
        'fecha_inicio' => now()->addDay(),
        'estado' => 'confirmada',
    ]);
    Appointment::factory()->create([
        'owner_id' => $business->id,
        'cliente_id' => $cliente->id,
        'servicio_id' => $servicio->id,
        'fecha_inicio' => now()->addDay(),
        'estado' => 'cancelada',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/appointments", n8nHeaders($business));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
    ]);
    $response->assertJsonCount(3, 'appointments');
});

test('appointments filters by status', function () {
    $business = createBusinessWithN8nKey();
    $categoria = Categoria::factory()->create(['owner_id' => $business->id, 'servicio_tipo' => 'servicio']);
    $servicio = Producto::factory()->create([
        'owner_id' => $business->id,
        'categoria_id' => $categoria->id,
        'es_servicio' => true,
        'duracion_minutos' => 30,
    ]);
    $cliente = Cliente::factory()->create(['owner_id' => $business->id]);

    Appointment::factory()->create([
        'owner_id' => $business->id,
        'cliente_id' => $cliente->id,
        'servicio_id' => $servicio->id,
        'fecha_inicio' => now()->addDay(),
        'estado' => 'confirmada',
    ]);
    Appointment::factory()->create([
        'owner_id' => $business->id,
        'cliente_id' => $cliente->id,
        'servicio_id' => $servicio->id,
        'fecha_inicio' => now()->addDay(),
        'estado' => 'pendiente',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/appointments?status=confirmada", n8nHeaders($business));

    $response->assertOk();
    $response->assertJsonCount(1, 'appointments');
    $response->assertJsonPath('appointments.0.status', 'confirmada');
});

test('endpoints return 401 with wrong token', function () {
    $business = createBusinessWithN8nKey();

    $response = $this->getJson('/api/internal/business/1/summary', [
        'X-N8N-TOKEN' => 'wrong-token',
    ]);

    $response->assertUnauthorized();
    $response->assertJson([
        'success' => false,
        'message' => 'Token de n8n inválido para este negocio.',
    ]);
});

test('business data isolation — cannot see other business data', function () {
    $businessA = createBusinessWithN8nKey();
    $businessB = createBusinessWithN8nKey();

    Cliente::factory()->create(['owner_id' => $businessA->id, 'nombre' => 'Cliente de A']);
    Cliente::factory()->create(['owner_id' => $businessB->id, 'nombre' => 'Cliente de B']);

    $response = $this->getJson("/api/internal/business/{$businessA->id}/summary", n8nHeaders($businessA));

    $response->assertOk();
    $response->assertJsonPath('summary.customers_total', 1);
});

// ──────────────────────────────────────────────
// FASE 4: Financial endpoints
// ──────────────────────────────────────────────

test('cash-flow returns treasury items for business', function () {
    $business = createBusinessWithN8nKey();

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

    $response = $this->getJson("/api/internal/business/{$business->id}/cash-flow", n8nHeaders($business));

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
    $business = createBusinessWithN8nKey();

    Tesoreria::factory()->create(['owner_id' => $business->id, 'tipo' => 'ingreso', 'monto' => 50000]);
    Tesoreria::factory()->create(['owner_id' => $business->id, 'tipo' => 'egreso', 'monto' => 30000]);

    $response = $this->getJson("/api/internal/business/{$business->id}/cash-flow?tipo=ingreso", n8nHeaders($business));

    $response->assertOk();
    $response->assertJsonCount(1, 'items');
    $response->assertJsonPath('items.0.type', 'ingreso');
});

test('accounts-receivable returns cobranza items', function () {
    $business = createBusinessWithN8nKey();

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

    $response = $this->getJson("/api/internal/business/{$business->id}/accounts-receivable", n8nHeaders($business));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
        'summary' => ['total_pending' => 150000],
    ]);
    $response->assertJsonCount(2, 'items');
});

test('accounts-payable returns pago items', function () {
    $business = createBusinessWithN8nKey();

    Pago::create([
        'owner_id' => $business->id,
        'monto' => 80000,
        'estado' => 'pendiente',
        'metodo_pago' => 'transferencia',
        'referencia' => 'PAG-001',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/accounts-payable", n8nHeaders($business));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
        'summary' => ['total_pending' => 80000],
    ]);
    $response->assertJsonCount(1, 'items');
});

test('expenses returns combined project and treasury expenses', function () {
    $business = createBusinessWithN8nKey();

    GastoProyecto::factory()->create([
        'owner_id' => $business->id,
        'monto' => 30000,
        'tipo' => 'materiales',
        'estado' => 'aprobado',
    ]);

    Tesoreria::factory()->create([
        'owner_id' => $business->id,
        'tipo' => 'egreso',
        'monto' => 20000,
        'categoria' => 'gastos_operacionales',
        'estado' => 'confirmado',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/expenses", n8nHeaders($business));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
        'summary' => ['total_expenses' => 50000],
    ]);
    $response->assertJsonCount(2, 'items');
});

// ──────────────────────────────────────────────
// FASE 5: n8n workflow callback
// ──────────────────────────────────────────────

test('workflow-complete webhook stores execution and returns success', function () {
    $business = createBusinessWithN8nKey();

    $response = $this->postJson('/api/internal/webhook/workflow-complete', [
        'business_id' => $business->id,
        'workflow' => 'reporte-semanal',
        'status' => 'success',
        'output' => ['report_url' => 'https://n8n.example.com/report/123'],
        'execution_time_ms' => 3500,
    ], n8nHeaders($business));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'message' => 'Ejecución registrada correctamente.',
    ]);

    $this->assertDatabaseHas('automation_executions', [
        'owner_id' => $business->id,
        'workflow' => 'reporte-semanal',
        'status' => 'success',
    ]);
});

test('workflow-complete stores error status', function () {
    $business = createBusinessWithN8nKey();

    $response = $this->postJson('/api/internal/webhook/workflow-complete', [
        'business_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'error',
        'error_message' => 'Timeout al conectar con API externa',
        'execution_time_ms' => 30000,
    ], n8nHeaders($business));

    $response->assertOk();

    $this->assertDatabaseHas('automation_executions', [
        'owner_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'error',
        'error_message' => 'Timeout al conectar con API externa',
    ]);
});

test('workflow-complete validates required fields', function () {
    $business = createBusinessWithN8nKey();

    $response = $this->postJson('/api/internal/webhook/workflow-complete', [], n8nHeaders($business));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['business_id', 'workflow', 'status']);
});

test('workflow-complete validates business exists', function () {
    $business = createBusinessWithN8nKey();

    $response = $this->postJson('/api/internal/webhook/workflow-complete', [
        'business_id' => 99999,
        'workflow' => 'test',
        'status' => 'success',
    ], n8nHeaders($business));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['business_id']);
});

// ──────────────────────────────────────────────
// FASE 6: Execution history
// ──────────────────────────────────────────────

test('executions returns execution history for business', function () {
    $business = createBusinessWithN8nKey();

    AutomationExecution::factory()->count(3)->create([
        'owner_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'success',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/executions", n8nHeaders($business));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'business_id' => $business->id,
    ]);
    $response->assertJsonCount(3, 'executions');
});

test('executions filters by workflow and status', function () {
    $business = createBusinessWithN8nKey();

    AutomationExecution::factory()->create([
        'owner_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'success',
    ]);
    AutomationExecution::factory()->create([
        'owner_id' => $business->id,
        'workflow' => 'reporte-semanal',
        'status' => 'error',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/executions?workflow=reporte-diario&status=success", n8nHeaders($business));

    $response->assertOk();
    $response->assertJsonCount(1, 'executions');
    $response->assertJsonPath('executions.0.status', 'success');
});

test('executions respects limit parameter', function () {
    $business = createBusinessWithN8nKey();

    AutomationExecution::factory()->count(5)->create([
        'owner_id' => $business->id,
        'workflow' => 'reporte-diario',
        'status' => 'success',
    ]);

    $response = $this->getJson("/api/internal/business/{$business->id}/executions?limit=3", n8nHeaders($business));

    $response->assertOk();
    $response->assertJsonCount(3, 'executions');
});
