<?php

namespace Tests\Feature;

use App\Exceptions\StockInsufficientException;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\InventoryMovement;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use App\Traits\ErpSyncTrait;

beforeEach(function () {
    $this->storeOwner = User::factory()->create();
    $this->publicProfile = PublicProfile::factory()->create([
        'user_id' => $this->storeOwner->id,
        'owner_id' => $this->storeOwner->getOwnerId(),
    ]);
    $this->almacen = Almacen::factory()->create([
        'user_id' => $this->storeOwner->id,
        'owner_id' => $this->storeOwner->getOwnerId(),
    ]);
    $this->categoria = Categoria::factory()->create([
        'user_id' => $this->storeOwner->id,
    ]);
    $this->producto = Producto::factory()->create([
        'precio_venta' => 20000,
        'categoria_id' => $this->categoria->id,
    ]);

    // Crear stock inicial en inventario
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 50,
        'cantidad_minima' => 5,
        'owner_id' => $this->storeOwner->getOwnerId(),
    ]);
});

test('syncPedidoToErp decrementa stock del producto en inventarios', function () {
    $pedido = Pedido::factory()->create([
        'user_id' => $this->storeOwner->id,
        'public_profile_id' => $this->publicProfile->id,
    ]);

    PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $this->producto->id,
        'nombre_producto' => $this->producto->nombre,
        'precio_unitario' => $this->producto->precio_venta,
        'cantidad' => 10,
        'subtotal' => 200000,
    ]);

    // Usar el trait en un objeto anónimo para testing
    $syncer = new class
    {
        use ErpSyncTrait;
    };

    $syncer->syncPedidoToErp($pedido);

    // Stock debe haber decrementado: 50 - 10 = 40
    $inventario = Inventario::where('producto_id', $this->producto->id)->first();
    expect((float) $inventario->cantidad)->toBe(40.0);

    // Movimiento de inventario debe existir
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 10,
    ]);

    // Pedido marcado como sincronizado
    $pedido->refresh();
    expect($pedido->erp_synced_at)->not->toBeNull();
});

test('syncPedidoToErp crea movimiento de inventario por cada item', function () {
    $producto2 = Producto::factory()->create([
        'precio_venta' => 35000,
        'categoria_id' => $this->categoria->id,
    ]);

    Inventario::create([
        'producto_id' => $producto2->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 25,
        'cantidad_minima' => 5,
    ]);

    $pedido = Pedido::factory()->create([
        'user_id' => $this->storeOwner->id,
        'public_profile_id' => $this->publicProfile->id,
    ]);

    PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $this->producto->id,
        'nombre_producto' => $this->producto->nombre,
        'precio_unitario' => $this->producto->precio_venta,
        'cantidad' => 5,
        'subtotal' => 100000,
    ]);

    PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto2->id,
        'nombre_producto' => $producto2->nombre,
        'precio_unitario' => $producto2->precio_venta,
        'cantidad' => 8,
        'subtotal' => 280000,
    ]);

    $syncer = new class
    {
        use ErpSyncTrait;
    };

    $syncer->syncPedidoToErp($pedido);

    // Stock decrementado para ambos productos
    expect((float) Inventario::where('producto_id', $this->producto->id)->first()->cantidad)->toBe(45.0);
    expect((float) Inventario::where('producto_id', $producto2->id)->first()->cantidad)->toBe(17.0);

    // Dos movimientos EGRESO creados
    $movimientos = InventoryMovement::where('type', 'EGRESO')
        ->where('product_id', '!=', null)
        ->get();
    expect($movimientos->count())->toBe(2);
});

test('syncPedidoToErp lanza StockInsufficientException cuando stock es insuficiente', function () {
    $pedido = Pedido::factory()->create([
        'user_id' => $this->storeOwner->id,
        'public_profile_id' => $this->publicProfile->id,
    ]);

    PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $this->producto->id,
        'nombre_producto' => $this->producto->nombre,
        'precio_unitario' => $this->producto->precio_venta,
        'cantidad' => 100, // Stock solo tiene 50
        'subtotal' => 2000000,
    ]);

    $syncer = new class
    {
        use ErpSyncTrait;
    };

    $syncer->syncPedidoToErp($pedido);

    // Debería lanzar excepción (Pest captura excepciones automáticamente)
    // Verificar que no se creó ningún registro
    $this->assertDatabaseEmpty('ventas');
    $this->assertDatabaseEmpty('inventory_movements');
    $pedido->refresh();
    expect($pedido->erp_synced_at)->toBeNull();
})->throws(StockInsufficientException::class, 'Stock insuficiente');

test('syncPedidoToErp no modifica erp_synced_at cuando falla por stock', function () {
    $pedido = Pedido::factory()->create([
        'user_id' => $this->storeOwner->id,
        'public_profile_id' => $this->publicProfile->id,
        'erp_synced_at' => null,
    ]);

    PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $this->producto->id,
        'nombre_producto' => $this->producto->nombre,
        'precio_unitario' => $this->producto->precio_venta,
        'cantidad' => 999,
        'subtotal' => 19980000,
    ]);

    $syncer = new class
    {
        use ErpSyncTrait;
    };

    try {
        $syncer->syncPedidoToErp($pedido);
    } catch (StockInsufficientException $e) {
        // Esperado
    }

    $pedido->refresh();
    expect($pedido->erp_synced_at)->toBeNull();
    $this->assertDatabaseEmpty('ventas');
    $this->assertDatabaseEmpty('inventory_movements');
});

test('syncPedidoToErp es atomico: rollback completo si falla un item', function () {
    $producto2 = Producto::factory()->create([
        'precio_venta' => 50000,
        'categoria_id' => $this->categoria->id,
    ]);

    // producto2 no tiene stock
    Inventario::create([
        'producto_id' => $producto2->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 0,
        'cantidad_minima' => 1,
    ]);

    $pedido = Pedido::factory()->create([
        'user_id' => $this->storeOwner->id,
        'public_profile_id' => $this->publicProfile->id,
    ]);

    PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $this->producto->id,
        'nombre_producto' => $this->producto->nombre,
        'precio_unitario' => $this->producto->precio_venta,
        'cantidad' => 5,
        'subtotal' => 100000,
    ]);

    PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto2->id,
        'nombre_producto' => $producto2->nombre,
        'precio_unitario' => $producto2->precio_venta,
        'cantidad' => 10,
        'subtotal' => 500000,
    ]);

    $syncer = new class
    {
        use ErpSyncTrait;
    };

    try {
        $syncer->syncPedidoToErp($pedido);
    } catch (StockInsufficientException $e) {
        // Esperado
    }

    // Rollback completo: nada se creó
    $this->assertDatabaseEmpty('ventas');
    $this->assertDatabaseEmpty('detalle_ventas');
    $this->assertDatabaseEmpty('inventory_movements');
    $this->assertDatabaseEmpty('tesoreria');
    $this->assertDatabaseEmpty('asientos');

    // Stock no fue tocado
    expect((float) Inventario::where('producto_id', $this->producto->id)->first()->cantidad)->toBe(50.0);

    $pedido->refresh();
    expect($pedido->erp_synced_at)->toBeNull();
});

test('syncPedidoToErp no se ejecuta si ya esta sincronizado', function () {
    $pedido = Pedido::factory()->create([
        'user_id' => $this->storeOwner->id,
        'public_profile_id' => $this->publicProfile->id,
        'erp_synced_at' => now(),
    ]);

    $syncer = new class
    {
        use ErpSyncTrait;
    };

    $syncer->syncPedidoToErp($pedido);

    // No debe crear nada adicional
    $this->assertDatabaseEmpty('ventas');
    $this->assertDatabaseEmpty('inventory_movements');
});

test('syncPedidoToErp no se ejecuta si no tiene publicProfile', function () {
    $pedido = Pedido::factory()->create([
        'user_id' => $this->storeOwner->id,
        'public_profile_id' => null,
    ]);

    PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $this->producto->id,
        'nombre_producto' => $this->producto->nombre,
        'precio_unitario' => $this->producto->precio_venta,
        'cantidad' => 5,
        'subtotal' => 100000,
    ]);

    $syncer = new class
    {
        use ErpSyncTrait;
    };

    $syncer->syncPedidoToErp($pedido);

    $this->assertDatabaseEmpty('ventas');
    $this->assertDatabaseEmpty('inventory_movements');
});
