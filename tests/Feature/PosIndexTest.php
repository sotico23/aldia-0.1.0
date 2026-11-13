<?php

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function grantPosViewAny(User $user): void
{
    $user->givePermissionTo(
        Permission::firstOrCreate([
            'name' => 'ventas.pos.viewAny',
            'guard_name' => 'web',
        ]),
    );
}

test('el registro de pos expone todos los productos activos del inventario del tenant', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);
    grantPosViewAny($user);

    $ownerId = $user->id;

    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);

    // Producto con stock en el almacén seleccionado por defecto.
    $conStock = Producto::factory()->create([
        'owner_id' => $ownerId,
        'nombre' => 'Producto con stock',
        'activo' => true,
    ]);
    Inventario::factory()->create([
        'producto_id' => $conStock->id,
        'almacen_id' => $almacen->id,
        'owner_id' => $ownerId,
        'cantidad' => 10,
    ]);

    // Producto sin ningún registro de inventario.
    Producto::factory()->create([
        'owner_id' => $ownerId,
        'nombre' => 'Producto sin inventario',
        'activo' => true,
    ]);

    // Producto con inventario pero cantidad cero.
    $sinCantidad = Producto::factory()->create([
        'owner_id' => $ownerId,
        'nombre' => 'Producto sin cantidad',
        'activo' => true,
    ]);
    Inventario::factory()->create([
        'producto_id' => $sinCantidad->id,
        'almacen_id' => $almacen->id,
        'owner_id' => $ownerId,
        'cantidad' => 0,
    ]);

    // Producto inactivo: no debe aparecer en el catálogo de POS.
    Producto::factory()->create([
        'owner_id' => $ownerId,
        'nombre' => 'Producto inactivo',
        'activo' => false,
    ]);

    $this->actingAs($user)
        ->get(route('pos.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/Pos/Index')
            ->has('productos', 3)
            ->where('productos', function ($productos) {
                $nombres = collect($productos)->pluck('nombre')->sort()->values()->all();

                expect($nombres)->toBe([
                    'Producto con stock',
                    'Producto sin cantidad',
                    'Producto sin inventario',
                ]);
            }));
});
