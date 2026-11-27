<?php

use App\Events\EntregaAsignadaRepartidor;
use App\Models\Entrega;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

test('una venta pendiente se puede asignar a un repartidor sin cambiar su pago', function () {
    $owner = User::factory()->create();
    foreach (['flota.entregas.viewAny', 'flota.entregas.create', 'flota.entregas.edit'] as $permission) {
        $owner->givePermissionTo(Permission::findOrCreate($permission));
    }
    $this->actingAs($owner);

    $venta = Venta::factory()->for($owner, 'user')->create([
        'owner_id' => $owner->getOwnerId(),
        'estado' => 'pendiente',
    ]);
    $repartidor = Repartidor::factory()->create(['owner_id' => $owner->getOwnerId()]);
    Event::fake([EntregaAsignadaRepartidor::class]);

    $this->get(route('entregas.index', ['venta_id' => $venta->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Entregas/Index')
            ->where('venta_inicial_id', $venta->id)
            ->where('ventas.0.id', $venta->id));

    $payload = [
        'venta_id' => $venta->id,
        'repartidor_id' => $repartidor->id,
        'estado_asignacion' => 'asignado',
        'estado' => 'pendiente',
    ];
    $this->post(route('entregas.store'), $payload)->assertRedirect(route('entregas.index'));

    $entrega = Entrega::where('venta_id', $venta->id)->sole();
    expect($entrega->pedido->repartidor_id)->toBe($repartidor->user_id);
    Event::assertDispatched(EntregaAsignadaRepartidor::class);

    $this->post(route('entregas.store'), $payload)->assertSessionHasErrors('venta_id');
    $this->patch(route('entregas.update', $entrega), ['estado' => 'en_ruta'])
        ->assertRedirect(route('entregas.index'));

    expect($venta->fresh()->estado)->toBe('pendiente')
        ->and($entrega->fresh()->pedido->estado)->toBe('enviado');
});

test('el creador cambia de pagada a pendiente desde la ruta de estado', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo(Permission::findOrCreate('ventas.ventas.viewAny'));
    $this->actingAs($owner);
    $venta = Venta::factory()->for($owner, 'user')->create([
        'owner_id' => $owner->getOwnerId(),
        'estado' => 'pagada',
    ]);

    $this->from(route('ventas.index'))
        ->patch(route('ventas.status', $venta), ['estado' => 'pendiente'])
        ->assertRedirect(route('ventas.index'));

    expect($venta->fresh()->estado)->toBe('pendiente');

    $this->patch(route('ventas.status', $venta), ['estado' => 'pagada'])->assertRedirect();
    expect($venta->fresh()->estado)->toBe('pagada');
});

test('ver ventas no autoriza cambiar las ventas de otro usuario', function () {
    $owner = User::factory()->create();
    $empleado = User::factory()->create(['owner_id' => $owner->getOwnerId(), 'creator_id' => $owner->id]);
    $empleado->syncRoles([]);
    $empleado->givePermissionTo(Permission::findOrCreate('ventas.ventas.viewAny'));
    $venta = Venta::factory()->for($owner, 'user')->create([
        'owner_id' => $owner->getOwnerId(),
        'estado' => 'pagada',
    ]);

    $this->actingAs($empleado)
        ->patch(route('ventas.status', $venta), ['estado' => 'pendiente'])
        ->assertForbidden();

    expect($venta->fresh()->estado)->toBe('pagada');
});
