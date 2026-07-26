<?php

declare(strict_types=1);

use App\Actions\CalculateMetricAction;
use App\DataTransferObjects\MetricResult;
use App\Models\Conductor;
use App\Models\Entrega;
use App\Models\EntregaItem;
use App\Models\GrupoTrabajo;
use App\Models\GrupoTrabajoAsignacion;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\GrupoTrabajoRendimientoService;

// ─── CalculateMetricAction Unit Tests ─────────────────────────────────────────

describe('CalculateMetricAction', function () {
    test('calculates kg correctly for a weight product', function () {
        $producto = Producto::factory()->make([
            'unidad_medida' => 'kg',
            'contenido_por_unidad' => 20.0,
            'peso_base' => 2.0,
        ]);

        $result = app(CalculateMetricAction::class)->execute($producto, 5.0);

        // kg = (5 × 20) + (5 × 2) = 100 + 10 = 110
        expect($result)->toBeInstanceOf(MetricResult::class)
            ->and($result->kg)->toBe(110.0)
            ->and($result->litros)->toBe(0.0)
            ->and($result->subtotal())->toBe(110.0);
    });

    test('calculates litros correctly for a liquid product', function () {
        $producto = Producto::factory()->make([
            'unidad_medida' => 'lt',
            'contenido_por_unidad' => 10.0,
            'peso_base' => 0.5,
        ]);

        $result = app(CalculateMetricAction::class)->execute($producto, 3.0);

        // litros = 3 × 10 = 30  |  kg (tare) = 3 × 0.5 = 1.5
        expect($result->litros)->toBe(30.0)
            ->and($result->kg)->toBe(1.5)
            ->and($result->subtotal())->toBe(1.5); // subtotal = kg when kg > 0
    });

    test('subtotal returns litros when kg is zero', function () {
        $producto = Producto::factory()->make([
            'unidad_medida' => 'litro',
            'contenido_por_unidad' => 5.0,
            'peso_base' => 0.0,
        ]);

        $result = app(CalculateMetricAction::class)->execute($producto, 4.0);

        expect($result->kg)->toBe(0.0)
            ->and($result->litros)->toBe(20.0)
            ->and($result->subtotal())->toBe(20.0); // falls back to litros
    });

    test('returns zero for zero quantity', function () {
        $producto = Producto::factory()->make([
            'unidad_medida' => 'kg',
            'contenido_por_unidad' => 50.0,
            'peso_base' => 1.0,
        ]);

        $result = app(CalculateMetricAction::class)->execute($producto, 0.0);

        expect($result->kg)->toBe(0.0)
            ->and($result->litros)->toBe(0.0);
    });

    test('MetricResult::add merges two results correctly', function () {
        $a = new MetricResult(kg: 10.0, litros: 5.0);
        $b = new MetricResult(kg: 3.0, litros: 2.0);
        $merged = $a->add($b);

        expect($merged->kg)->toBe(13.0)
            ->and($merged->litros)->toBe(7.0);
    });
});

// ─── GrupoTrabajoRendimientoService Integration Tests ─────────────────────────

describe('GrupoTrabajoRendimientoService', function () {
    beforeEach(function (): void {
        $this->owner = User::factory()->create();
        $this->service = app(GrupoTrabajoRendimientoService::class);
    });

    /**
     * KEY TEST: Real dispatched weight must come from EntregaItem, NOT DetalleVenta.
     *
     * Setup:
     *   - DetalleVenta has subtotal_metrica = 1000 kg  (projection / commercial).
     *   - EntregaItem   has subtotal_metrica = 250 kg  (actual delivered).
     *
     * Expectation: the service returns 250 kg, not 1000 kg.
     */
    test('metrics come from EntregaItem, NOT DetalleVenta', function () {
        $grupo = GrupoTrabajo::factory()->create([
            'owner_id' => $this->owner->id,
            'estado' => 'activo',
        ]);

        $conductor = Conductor::factory()->create(['owner_id' => $this->owner->id]);
        $grupo->conductores()->attach($conductor->id);

        // Venta (commercial projection)
        $venta = Venta::factory()->create([
            'owner_id' => $this->owner->id,
            'estado' => 'pagada',
            'total' => 500_000,
            'fecha' => now()->toDateString(),
        ]);

        // Simulate a DetalleVenta with a large metric (projection artifact)
        DB::table('detalle_ventas')->insert([
            'venta_id' => $venta->id,
            'producto_id' => Producto::factory()->create(['owner_id' => $this->owner->id, 'categoria_id' => null])->id,
            'cantidad' => 10,
            'precio_unitario' => 50_000,
            'subtotal' => 500_000,
            'subtotal_metrica' => 1_000.00, // ← this should NOT be used for real metrics
            'owner_id' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Entrega (real execution)
        $entrega = Entrega::factory()->create([
            'owner_id' => $this->owner->id,
            'venta_id' => $venta->id,
            'conductor_id' => $conductor->id,
            'estado' => 'entregado',
        ]);

        // EntregaItem with actual delivered weight
        DB::table('entrega_items')->insert([
            'entrega_id' => $entrega->id,
            'producto_id' => Producto::factory()->create(['owner_id' => $this->owner->id, 'categoria_id' => null])->id,
            'cantidad_pedida' => 5,
            'cantidad_entregada' => 5,
            'unidad_medida' => 'kg',
            'subtotal_metrica' => 250.00, // ← SSOT for real dispatched weight
            'unidades_totales' => 5,
            'owner_id' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->calcularMetricasGrupo(
            $grupo,
            $this->owner->id,
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        );

        // Must be 250 (from EntregaItem), NOT 1000 (from DetalleVenta)
        expect($result['kg'])->toBe(250.0)
            ->and($result['l'])->toBe(0.0)
            ->and($result['cantidad'])->toBe(1);
    });

    test('returns zeros when group has no deliveries', function () {
        $grupo = GrupoTrabajo::factory()->create([
            'owner_id' => $this->owner->id,
            'estado' => 'activo',
        ]);

        $result = $this->service->calcularMetricasGrupo(
            $grupo,
            $this->owner->id,
        );

        expect($result['kg'])->toBe(0.0)
            ->and($result['l'])->toBe(0.0)
            ->and($result['monto'])->toBe(0.0)
            ->and($result['cantidad'])->toBe(0);
    });

    test('inactive groups are excluded from calcularPorGrupos', function () {
        GrupoTrabajo::factory()->create([
            'owner_id' => $this->owner->id,
            'estado' => 'inactivo',
        ]);

        $result = $this->service->calcularPorGrupos($this->owner->id);

        expect($result['porGrupo'])->toBeEmpty();
    });

    test('calcularCumplimientoAsignacion returns compliance data from EntregaItem', function () {
        $grupo = GrupoTrabajo::factory()->create([
            'owner_id' => $this->owner->id,
            'estado' => 'activo',
        ]);

        $conductor = Conductor::factory()->create(['owner_id' => $this->owner->id]);
        $grupo->conductores()->attach($conductor->id);

        $inicio = now()->startOfMonth()->toDateString();
        $fin = now()->endOfMonth()->toDateString();

        $asignacion = GrupoTrabajoAsignacion::factory()->create([
            'owner_id' => $this->owner->id,
            'grupo_trabajo_id' => $grupo->id,
            'user_id' => $this->owner->id,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'meta_kg' => 500.0,
            'meta_l' => 0.0,
            'estado' => 'activa',
        ]);

        $venta = Venta::factory()->create([
            'owner_id' => $this->owner->id,
            'estado' => 'pagada',
            'fecha' => now()->toDateString(),
        ]);

        $entrega = Entrega::factory()->create([
            'owner_id' => $this->owner->id,
            'venta_id' => $venta->id,
            'conductor_id' => $conductor->id,
        ]);

        DB::table('entrega_items')->insert([
            'entrega_id' => $entrega->id,
            'producto_id' => Producto::factory()->create(['owner_id' => $this->owner->id, 'categoria_id' => null])->id,
            'cantidad_pedida' => 10,
            'cantidad_entregada' => 10,
            'unidad_medida' => 'kg',
            'subtotal_metrica' => 300.0,
            'unidades_totales' => 10,
            'owner_id' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $compliance = $this->service->calcularCumplimientoAsignacion($asignacion);

        expect($compliance['peso_entregado_kg'])->toBe(300.0)
            ->and($compliance['meta_kg'])->toBe(500.0)
            ->and($compliance['porcentaje_cumplimiento_kg'])->toBe(60.0)  // 300/500 × 100
            ->and($compliance['cumple_meta_kg'])->toBeFalse();
    });
});
