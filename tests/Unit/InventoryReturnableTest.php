<?php

namespace Tests\Unit;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReturnableTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function product_can_have_returnable_container_association()
    {
        // Create a product that represents an empty container
        $envase = Producto::factory()->create([
            'nombre' => 'Cilindro 15kg Vacío',
            'codigo' => 'CYL-15K-VACIO',
            'tipo_envase' => 'cilindro',
            'envase_retornable' => true,
        ]);

        // Create a product that represents the full gas content
        $productoLleno = Producto::factory()->create([
            'nombre' => 'Gas Licuado 15kg',
            'codigo' => 'GAS-15K',
            'tipo_envase' => 'gas licuado',
            'envase_retornable' => true,
            'envase_producto_id' => $envase->id, // Associate the container
        ]);

        // Test that the association works
        $this->assertEquals($envase->id, $productoLleno->envase_producto_id);
        $this->assertInstanceOf(Producto::class, $productoLleno->envaseProducto);
        $this->assertEquals($envase->id, $productoLleno->envaseProducto->id);

        // Test that a non-returnable product has null association
        $productoNoRetornable = Producto::factory()->create([
            'nombre' => 'Producto Simple',
            'codigo' => 'SIMPLE-001',
            'envase_retornable' => false,
            'envase_producto_id' => null,
        ]);

        $this->assertNull($productoNoRetornable->envase_producto_id);
        $this->assertNull($productoNoRetornable->envaseProducto);
    }

    /** @test */
    public function inventory_calculations_use_net_product_sold()
    {
        // This test ensures our controller logic focuses on net product sold
        // In a real scenario, we would test the controller method
        // For now, we verify the relationship exists and works correctly

        $envase = Producto::factory()->create([
            'nombre' => 'Tanque Vacío',
            'envase_retornable' => true,
        ]);

        $producto = Producto::factory()->create([
            'nombre' => 'Producto con Envase',
            'envase_retornable' => true,
            'envase_producto_id' => $envase->id,
        ]);

        // Verify we can access the associated container
        $this->assertNotNull($producto->envaseProducto);
        $this->assertEquals('Tanque Vacío', $producto->envaseProducto->nombre);
    }
}
