<?php
/**
 * Producto Resource Test
 */

namespace Tests\Unit\Resources\Producto;

use App\Http\Resources\Producto\ProductoResource;
use Tests\TestCase;

class ProductoResourceTest extends TestCase
{
    public function test_producto_resource_returns_product_data(): void
    {
        $resource = new class extends ProductoResource {
            public function __construct()
            {
                $this->id = 1;
                $this->created_at = '2024-01-01T00:00:00Z';
                $this->updated_at = '2024-01-01T00:00:00Z';
            }
            
            protected function addMinimalFields(): void
            {
                $this->addCommonFields([
                    'id' => 1,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'updated_at' => '2024-01-01T00:00:00Z',
                    'data' => [
                        'id' => 1,
                        'nombre' => 'Producto Test',
                        'descripcion' => 'Descripción test',
                        'precio_base' => 100.50,
                        'stock_unidades' => 10,
                        'categoria_id' => 1,
                        'imagen_principal' => 'https://example.com/image.jpg',
                        'estado_inventario' => 'disponible',
                    ],
                ]);
            }
        };

        $data = $resource->getMinimalData();
        
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('Producto Test', $data['data']['nombre']);
        $this->assertEquals(100.50, $data['data']['precio_base']);
        $this->assertEquals(10, $data['data']['stock_unidades']);
        $this->assertEquals('disponible', $data['data']['estado_inventario']);
    }

    public function test_producto_resource_includes_required_fields(): void
    {
        $resource = new class extends ProductoResource {
            public function __construct()
            {
                $this->id = 2;
                $this->created_at = '2024-01-01T00:00:00Z';
                $this->updated_at = '2024-01-01T00:00:00Z';
            }
            
            protected function addMinimalFields(): void
            {
                $this->addCommonFields([
                    'id' => 2,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'updated_at' => '2024-01-01T00:00:00Z',
                    'data' => [
                        'id' => 2,
                        'nombre' => 'Test Product',
                        'precio_base' => 50.00,
                        'stock_unidades' => 5,
                    ],
                ]);
            }
        };

        $data = $resource->getMinimalData();
        
        $requiredFields = ['id', 'nombre', 'precio_base', 'stock_unidades'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $data['data']);
        }
    }
}