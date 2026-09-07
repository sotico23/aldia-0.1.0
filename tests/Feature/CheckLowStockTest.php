<?php

use App\Events\LowStock;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Event::fake();
});

test('stock:check-low dispatches LowStock event for products below minimum', function () {
    $user = User::factory()->create();

    $almacen = Almacen::factory()->create(['owner_id' => $user->id]);

    $producto = Producto::factory()->create([
        'owner_id' => $user->id,
        'stock_minimo' => 10,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 3,
        'cantidad_minima' => 10,
        'owner_id' => $user->id,
    ]);

    Artisan::call('stock:check-low');

    Event::assertDispatched(LowStock::class, function ($event) use ($producto, $user) {
        return $event->producto->id === $producto->id
            && $event->stockActual < $event->stockMinimo
            && $event->producto->owner_id === $user->id;
    });
});

test('stock:check-low does not dispatch event when stock is above minimum', function () {
    $user = User::factory()->create();

    $almacen = Almacen::factory()->create(['owner_id' => $user->id]);

    $producto = Producto::factory()->create([
        'owner_id' => $user->id,
        'stock_minimo' => 5,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 20,
        'cantidad_minima' => 5,
        'owner_id' => $user->id,
    ]);

    Artisan::call('stock:check-low');

    Event::assertNotDispatched(LowStock::class);
});

test('stock:check-low dispatches event for each low stock product', function () {
    $user = User::factory()->create();

    $almacen = Almacen::factory()->create(['owner_id' => $user->id]);

    // Product with low stock
    $productoLow = Producto::factory()->create([
        'owner_id' => $user->id,
        'stock_minimo' => 10,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $productoLow->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 3,
        'cantidad_minima' => 10,
        'owner_id' => $user->id,
    ]);

    // Product with normal stock
    $productoNormal = Producto::factory()->create([
        'owner_id' => $user->id,
        'stock_minimo' => 5,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $productoNormal->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 20,
        'cantidad_minima' => 5,
        'owner_id' => $user->id,
    ]);

    Artisan::call('stock:check-low');

    Event::assertDispatchedTimes(LowStock::class, 1);
    Event::assertDispatched(LowStock::class, function ($event) use ($productoLow) {
        return $event->producto->id === $productoLow->id;
    });
});