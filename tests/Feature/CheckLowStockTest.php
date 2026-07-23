<?php

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Notifications\StockLowNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
});

test('stock:check-low sends notification when products are below minimum', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'inventario.inventarios.viewAny']));

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

    Notification::assertSentTo(
        $user,
        StockLowNotification::class,
        function (StockLowNotification $notification) use ($producto) {
            return count($notification->productos) === 1
                && $notification->productos[0]['producto_id'] === $producto->id;
        }
    );
});

test('stock:check-low does not notify when stock is above minimum', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'inventario.inventarios.viewAny']));

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

    Notification::assertNothingSent();
});

test('stock:check-low only notifies users with inventory permission', function () {
    $userWithPerm = User::factory()->create();
    $userWithPerm->givePermissionTo(Permission::firstOrCreate(['name' => 'inventario.inventarios.viewAny']));

    $userWithoutPerm = User::factory()->create();

    $almacen = Almacen::factory()->create(['owner_id' => $userWithPerm->id]);

    $producto = Producto::factory()->create([
        'owner_id' => $userWithPerm->id,
        'stock_minimo' => 5,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 1,
        'cantidad_minima' => 5,
        'owner_id' => $userWithPerm->id,
    ]);

    Artisan::call('stock:check-low');

    Notification::assertSentTo($userWithPerm, StockLowNotification::class);
    Notification::assertNotSentTo($userWithoutPerm, StockLowNotification::class);
});
