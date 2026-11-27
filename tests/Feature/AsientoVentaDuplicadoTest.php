<?php

use App\Livewire\Ventas\Index;
use App\Models\Asiento;
use App\Models\User;
use App\Models\Venta;
use App\Scopes\OwnerScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function crearAsientoVenta(Venta $venta, int $ownerId, string $descripcion, ?string $numero = null): Asiento
{
    return Asiento::create([
        'owner_id' => $ownerId,
        'fecha' => now(),
        'numero' => $numero ?? 'AS-VNT-'.str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT),
        'descripcion' => $descripcion,
        'tipo' => 'venta',
        'total_debe' => $venta->total,
        'total_haber' => $venta->total,
        'estado' => true,
    ]);
}

test('contabilizar desde ventas una venta ya asentada por el POS no duplica el asiento', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo(Permission::findOrCreate('ventas.ventas.viewAny'));
    $this->actingAs($owner);
    $ownerId = $owner->getOwnerId();

    $venta = Venta::factory()->for($owner, 'user')->create([
        'owner_id' => $ownerId,
        'numero' => 'POS-DUP-1',
        'estado' => 'pendiente',
    ]);
    crearAsientoVenta($venta, $ownerId, "Registro contable Venta POS #{$venta->numero}");

    Livewire::test(Index::class)
        ->call('cambiarEstado', $venta->id, 'pagada')
        ->assertHasNoErrors();

    expect($venta->fresh()->estado)->toBe('pagada')
        ->and(Asiento::withoutGlobalScope(OwnerScope::class)->count())->toBe(1);
});

test('el número de asiento es único aunque otro asiento ocupe el número base', function () {
    $venta = Venta::factory()->create(['numero' => 'VENTA-OTRA']);
    crearAsientoVenta($venta, $venta->owner_id, 'Asiento manual');

    $numeroBase = 'AS-VNT-'.str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT);

    expect(Asiento::numeroParaVenta($venta))->toBe("{$numeroBase}-2");
});
