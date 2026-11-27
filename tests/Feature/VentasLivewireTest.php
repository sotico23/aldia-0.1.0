<?php

use App\Events\EntregaAsignadaRepartidor;
use App\Livewire\Ventas\Index;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

test('ventas se abre como una página Livewire y acepta navegación desde Inertia', function () {
    $this->withoutVite();
    $owner = User::factory()->create();
    $owner->givePermissionTo(Permission::findOrCreate('ventas.ventas.viewAny'));

    $this->actingAs($owner)->get(route('ventas.index'))
        ->assertOk()
        ->assertSeeLivewire(Index::class)
        ->assertSee('livewire.csp', false);

    $this->get(route('ventas.index'), ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('ventas.index'));
});

test('filtra y cambia el pago en el componente sin redirigir', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo(Permission::findOrCreate('ventas.ventas.viewAny'));
    $this->actingAs($owner);
    $venta = Venta::factory()->for($owner, 'user')->create([
        'owner_id' => $owner->getOwnerId(),
        'numero' => 'LOCAL-PAGADA',
        'estado' => 'pagada',
    ]);
    $otra = Venta::factory()->for($owner, 'user')->create([
        'owner_id' => $owner->getOwnerId(),
        'numero' => 'LOCAL-PENDIENTE',
        'estado' => 'pendiente',
    ]);
    Venta::factory()->create(['numero' => 'OTRO-NEGOCIO']);

    Livewire::test(Index::class)
        ->assertSee($venta->numero)
        ->assertDontSee('OTRO-NEGOCIO')
        ->set('busqueda', $venta->numero)
        ->assertDontSee($otra->numero)
        ->set('vista', 'tarjetas')
        ->call('cambiarEstado', $venta->id, 'pendiente')
        ->assertHasNoErrors()
        ->assertSet('mensaje', 'Estado actualizado.')
        ->assertNoRedirect();

    expect($venta->fresh()->estado)->toBe('pendiente');
});

test('crea, modifica y elimina una venta desde las acciones Livewire', function () {
    $owner = User::factory()->create();
    foreach (['ventas.ventas.viewAny', 'ventas.ventas.create'] as $permiso) {
        $owner->givePermissionTo(Permission::findOrCreate($permiso));
    }
    $this->actingAs($owner);
    $ownerId = $owner->getOwnerId();
    $cliente = Cliente::factory()->create(['owner_id' => $ownerId, 'activo' => true]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId, 'activo' => true]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'activo' => true,
        'envase_retornable' => false,
        'precio_venta' => 1500,
    ]);

    $pagina = Livewire::test(Index::class)
        ->call('nueva')
        ->set('form.numero_factura', 'LIVEWIRE-NUEVA')
        ->set('form.tipo_documento', 'cotizacion')
        ->set('form.cliente_id', (string) $cliente->id)
        ->set('form.almacen_ids', [(string) $almacen->id])
        ->set('form.productos.0.producto_id', (string) $producto->id)
        ->assertSet('form.productos.0.precio_unitario', $producto->precio_venta)
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSet('mensaje', 'Venta creada.')
        ->assertSet('formOpen', false)
        ->assertNoRedirect()
        ->assertSee('LIVEWIRE-NUEVA');

    $venta = Venta::where('numero', 'LIVEWIRE-NUEVA')->sole();

    $pagina->call('ver', $venta->id)
        ->assertSee($producto->nombre)
        ->call('cerrarDetalle')
        ->call('editar', $venta->id)
        ->set('form.notas', 'Actualizada con Livewire')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSet('mensaje', 'Venta actualizada.')
        ->assertNoRedirect();

    expect($venta->fresh()->notas)->toBe('Actualizada con Livewire');

    $pagina->call('eliminar', $venta->id)->assertSet('mensaje', 'Venta eliminada.')->assertNoRedirect();
    $this->assertModelMissing($venta);
});

test('una acción Livewire rechaza ventas de otra empresa', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo(Permission::findOrCreate('ventas.ventas.viewAny'));
    $ajena = Venta::factory()->create(['estado' => 'pagada']);

    $this->actingAs($owner);
    $pagina = Livewire::test(Index::class);
    expect(fn () => $pagina->call('cambiarEstado', $ajena->id, 'pendiente'))
        ->toThrow(ModelNotFoundException::class);
    expect($ajena->fresh()->estado)->toBe('pagada');
});

test('envía al repartidor una venta pendiente sin salir del listado', function () {
    $owner = User::factory()->create();
    foreach (['ventas.ventas.viewAny', 'flota.entregas.viewAny', 'flota.entregas.create'] as $permiso) {
        $owner->givePermissionTo(Permission::findOrCreate($permiso));
    }
    $venta = Venta::factory()->for($owner, 'user')->create([
        'owner_id' => $owner->getOwnerId(),
        'estado' => 'pendiente',
        'delivery_address' => 'Avenida Central 123',
    ]);
    $repartidor = Repartidor::factory()->create([
        'owner_id' => $owner->getOwnerId(),
        'driver_type' => Repartidor::TYPE_FREELANCER,
    ]);
    Event::fake([EntregaAsignadaRepartidor::class]);
    $this->actingAs($owner);

    Livewire::test(Index::class)
        ->call('prepararEntrega', $venta->id)
        ->assertSet('envio.direccion', 'Avenida Central 123')
        ->set('envio.repartidor_id', (string) $repartidor->id)
        ->call('enviarEntrega')
        ->assertHasNoErrors()
        ->assertSet('mensaje', 'Venta enviada a reparto.')
        ->assertSet('ventaEntrega', null)
        ->assertNoRedirect();

    expect($venta->fresh()->estado)->toBe('pendiente')
        ->and($venta->entrega()->sole()->pedido->repartidor_id)->toBe($repartidor->user_id);
});

test('importa un CSV y actualiza el listado sin redirigir', function () {
    $owner = User::factory()->create();
    foreach (['ventas.ventas.viewAny', 'ventas.ventas.import'] as $permiso) {
        $owner->givePermissionTo(Permission::findOrCreate($permiso));
    }
    $ownerId = $owner->getOwnerId();
    Cliente::factory()->create(['owner_id' => $ownerId, 'email' => 'cliente@example.test']);
    Producto::factory()->create(['owner_id' => $ownerId, 'nombre' => 'Producto CSV']);
    $this->actingAs($owner);

    $archivo = UploadedFile::fake()->createWithContent('ventas.csv', implode("\n", [
        'Numero;Fecha;Cliente_Email;Estado;Item_Descripcion;Item_Cantidad;Item_Precio;Subtotal;IVA;Total;Notas',
        'LIVEWIRE-CSV;2026-11-26;cliente@example.test;pendiente;Producto CSV;1;1000;1000;190;1190;Importada',
    ]));

    Livewire::test(Index::class)
        ->set('archivo', $archivo)
        ->call('importar')
        ->assertHasNoErrors()
        ->assertSet('archivo', null)
        ->assertSee('LIVEWIRE-CSV')
        ->assertNoRedirect();
});
