<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('inventarios index only sums stock of the authenticated owner', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $almacen = Almacen::factory()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->getOwnerId(),
    ]);
    $otherAlmacen = Almacen::factory()->create([
        'user_id' => $otherOwner->id,
        'owner_id' => $otherOwner->getOwnerId(),
    ]);

    $categoria = Categoria::factory()->create([
        'owner_id' => $owner->getOwnerId(),
    ]);

    $producto = Producto::factory()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->getOwnerId(),
        'categoria_id' => $categoria->id,
        'activo' => true,
        'is_service' => false,
    ]);

    // Stock propio del owner
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'owner_id' => $owner->getOwnerId(),
        'cantidad' => 50,
    ]);

    // Stock "fantasma" de otro owner con el mismo producto_id
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $otherAlmacen->id,
        'owner_id' => $otherOwner->getOwnerId(),
        'cantidad' => 999,
    ]);

    $response = $this->actingAs($owner)->get(route('inventarios.index'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('inventarios.data.0.id', $producto->id)
        ->where('inventarios.data.0.total_stock', 50)
    );
});
