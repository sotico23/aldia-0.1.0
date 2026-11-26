<?php

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Spatie\Permission\Models\Permission;

test('descarga el PDF de una venta con varios productos', function (string $routeName, string $suffix) {
    $owner = User::factory()->create();
    $owner->givePermissionTo(Permission::firstOrCreate(['name' => 'ventas.ventas.viewAny']));
    $this->actingAs($owner);

    $venta = Venta::factory()->create([
        'owner_id' => $owner->getOwnerId(),
        'user_id' => $owner->id,
        'cliente_id' => Cliente::factory()->create(['owner_id' => $owner->getOwnerId()])->id,
    ]);

    $productos = Producto::factory()->count(2)->create([
        'owner_id' => $owner->getOwnerId(),
        'user_id' => $owner->id,
        'categoria_id' => null,
        'envase_retornable' => false,
        'tipo_envase' => 'caja',
    ]);

    foreach ($productos as $producto) {
        DetalleVenta::factory()->create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
        ]);
    }

    $response = $this->get(route($routeName, $venta))
        ->assertOk()
        ->assertDownload('venta_'.$venta->numero.$suffix.'.pdf')
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF-');
})->with([
    'formal' => ['ventas.download', ''],
    'informal' => ['ventas.download-informal', '_simple'],
]);
