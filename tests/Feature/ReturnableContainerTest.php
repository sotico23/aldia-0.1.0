<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\VentaController;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnableContainerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->almacen = Almacen::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'nombre' => 'Almacén Principal',
        ]);

        // Producto retornable (ej: botella de agua)
        $this->productoRetornable = Producto::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'nombre' => 'Botella de Agua 500ml',
            'codigo' => 'AGUA-500',
            'precio_venta' => 1000,
            'envase_retornable' => true,
            'envase_producto_id' => null, // Se asignará después
        ]);

        // Producto envase (la botella vacía)
        $this->productoEnvase = Producto::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'nombre' => 'Botella Vacía 500ml',
            'codigo' => 'BOTELLA-VACIA',
            'precio_venta' => 0, // El envase no se vende por separado
            'envase_retornable' => false,
        ]);

        // Asociar el producto con su envase
        $this->productoRetornable->update([
            'envase_producto_id' => $this->productoEnvase->id,
        ]);

        // Crear inventario inicial
        Inventario::create([
            'owner_id' => $this->user->getOwnerId(),
            'producto_id' => $this->productoRetornable->id,
            'almacen_id' => $this->almacen->id,
            'cantidad' => 10, // 10 botellas llenas
            'cantidad_minima' => 2,
        ]);

        Inventario::create([
            'owner_id' => $this->user->getOwnerId(),
            'producto_id' => $this->productoEnvase->id,
            'almacen_id' => $this->almacen->id,
            'cantidad' => 5, // 5 botellas vacías disponibles
            'cantidad_minima' => 1,
        ]);

        $this->cliente = Cliente::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'email' => 'cliente@test.com',
        ]);
    }

    /** @test */
    public function it_decrements_both_product_and_container_inventory_when_selling_returnable_product_without_return()
    {
        // Crear venta de 2 botellas de agua sin retorno de envases
        $venta = Venta::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'cliente_id' => $this->cliente->id,
            'almacen_id' => $this->almacen->id,
            'estado' => 'pagada',
            'total' => 2000, // 2 * 1000
        ]);

        // Agregar detalle de venta
        $venta->detalleVentas()->create([
            'producto_id' => $this->productoRetornable->id,
            'cantidad' => 2,
            'precio_unitario' => 1000,
            'subtotal' => 2000,
            'envase_retornado' => false, // No se retornan envases
        ]);

        // Procesar el pago (esto desencadena la lógica de inventario)
        $venta->fresh(); // Recargar para asegurar que tiene los detalles
        app(VentaController::class)->procesarPago($venta);

        // Verificar inventario del producto (debería ser 10 - 2 = 8)
        $productoInventario = Inventario::where('producto_id', $this->productoRetornable->id)
            ->where('almacen_id', $this->almacen->id)
            ->first();

        $this->assertEquals(8, $productoInventario->cantidad);

        // Verificar inventario del envase (debería ser 5 - 2 = 3)
        $envaseInventario = Inventario::where('producto_id', $this->productoEnvase->id)
            ->where('almacen_id', $this->almacen->id)
            ->first();

        $this->assertEquals(3, $envaseInventario->cantidad);
    }

    /** @test */
    public function it_increments_container_inventory_when_customer_returns_containers()
    {
        // Crear venta de 2 botellas de agua con retorno de 1 envase
        $venta = Venta::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'cliente_id' => $this->cliente->id,
            'almacen_id' => $this->almacen->id,
            'estado' => 'pagada',
            'total' => 2000, // 2 * 1000
        ]);

        // Agregar detalle de venta
        $venta->detalleVentas()->create([
            'producto_id' => $this->productoRetornable->id,
            'cantidad' => 2,
            'precio_unitario' => 1000,
            'subtotal' => 2000,
            'envase_retornado' => true, // Se retorna 1 envase (pero el campo indica que se están retornando envases en general)
            // Nota: En la implementación actual, el campo envase_retornado indica si se están retornando envases,
            // pero no especifica cuántos. Para simplificar, asumimos que si es true, se retorna la misma cantidad que se compró.
        ]);

        // Procesar el pago
        $venta->fresh();
        app(VentaController::class)->procesarPago($venta);

        // Verificar inventario del producto (debería ser 10 - 2 = 8)
        $productoInventario = Inventario::where('producto_id', $this->productoRetornable->id)
            ->where('almacen_id', $this->almacen->id)
            ->first();

        $this->assertEquals(8, $productoInventario->cantidad);

        // Verificar inventario del envase:
        // Inicialmente: 5
        // Se entregan 2 envases con el producto: 5 - 2 = 3
        // Se retorna 1 envase: 3 + 1 = 4
        // Resultado esperado: 4
        $envaseInventario = Inventario::where('producto_id', $this->productoEnvase->id)
            ->where('almacen_id', $this->almacen->id)
            ->first();

        $this->assertEquals(4, $envaseInventario->cantidad);
    }
}
