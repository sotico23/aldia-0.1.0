<?php

use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('muestra un ticket térmico imprimible con los productos de la venta', function () {
    $owner = User::factory()->create(['name' => 'Comercio Prueba']);
    $owner->givePermissionTo(Permission::findOrCreate('ventas.ventas.viewAny'));
    $this->actingAs($owner);
    $ownerId = $owner->getOwnerId();

    $venta = Venta::factory()->for($owner, 'user')->create([
        'owner_id' => $ownerId,
        'numero' => 'TICKET-80MM',
        'estado' => 'pagada',
    ]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'nombre' => 'Producto Ticket',
    ]);
    $venta->detalleVentas()->create([
        'producto_id' => $producto->id,
        'cantidad' => 2,
        'precio_unitario' => 1500,
        'subtotal' => 3000,
    ]);

    $this->get(route('ventas.ticket', $venta))
        ->assertOk()
        ->assertSee('TICKET-80MM')
        ->assertSee('Producto Ticket')
        ->assertSee('Imprimir ticket')
        ->assertSee('Gracias por su compra');
});
