<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\InventoryClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cierre de inventario guarda y muestra el stock de cierre', function () {
    $user = User::factory()->create();
    $almacen = Almacen::factory()->create([
        'user_id' => $user->id,
        'owner_id' => $user->getOwnerId(),
    ]);

    $this->actingAs($user)
        ->post(route('inventario-cierre.store'), [
            'almacen_id' => $almacen->id,
            'type' => 'BODEGA',
            'total_products' => 10,
            'opening_stock' => 500,
            'closing_stock' => 450,
            'expected_stock' => 460,
            'difference' => -10,
            'observations' => 'Conteo físico',
            'marcar_auditado' => false,
        ])
        ->assertRedirect(route('inventario-cierre.index'));

    $cierre = InventoryClosure::where('owner_id', $user->getOwnerId())->first();

    expect($cierre)->not->toBeNull();
    expect((float) $cierre->closing_stock)->toBe(450.0);
    expect((float) $cierre->opening_stock)->toBe(500.0);

    $this->actingAs($user)
        ->get(route('inventario-cierre.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cierres.data.0.closing_stock', '450.000')
            ->where('cierres.data.0.opening_stock', '500.000'));
});
