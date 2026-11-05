<?php

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calcula el margen absoluto y porcentual sobre el precio de venta', function () {
    $producto = Producto::factory()->create([
        'categoria_id' => null,
        'precio_compra' => 140,
        'precio_venta' => 200,
    ]);

    expect($producto->margen_absoluto)->toBe(60.0)
        ->and($producto->margen_ganancia)->toBe(30.0);
});

test('el margen porcentual es cero cuando el precio de venta es cero', function () {
    $producto = Producto::factory()->create([
        'categoria_id' => null,
        'precio_compra' => 100,
        'precio_venta' => 0,
    ]);

    expect($producto->margen_absoluto)->toBe(-100.0)
        ->and($producto->margen_ganancia)->toBe(0.0);
});
