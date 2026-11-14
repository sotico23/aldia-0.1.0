<?php

use App\Http\Controllers\Backend\DashboardController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('productos', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('owner_id');
        $table->decimal('precio_compra')->nullable();
    });
    Schema::create('inventarios', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('owner_id');
        $table->unsignedBigInteger('producto_id');
        $table->decimal('cantidad')->nullable();
    });
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => 60]);
    $user->shouldReceive('hasRole')->andReturn(false);
    $user->shouldReceive('getOwnerId')->andReturn(60);
    $this->actingAs($user);
});

afterEach(function () {
    Schema::dropIfExists('inventarios');
    Schema::dropIfExists('productos');
});

test('el dashboard de un usuario nuevo calcula cero sin inventario', function () {
    $method = new ReflectionMethod(DashboardController::class, 'inventoryStockValue');

    expect($method->invoke(new DashboardController, 60, false))->toBe(0.0);
});

test('el valor de inventario distingue owner_id en ambas tablas y respeta al propietario', function () {
    DB::table('productos')->insert([
        ['id' => 1, 'owner_id' => 60, 'precio_compra' => 100],
        ['id' => 2, 'owner_id' => 61, 'precio_compra' => 900],
        ['id' => 3, 'owner_id' => 60, 'precio_compra' => null],
    ]);
    DB::table('inventarios')->insert([
        ['owner_id' => 60, 'producto_id' => 1, 'cantidad' => 2],
        ['owner_id' => 61, 'producto_id' => 1, 'cantidad' => 500],
        ['owner_id' => 60, 'producto_id' => 2, 'cantidad' => 400],
        ['owner_id' => 60, 'producto_id' => 3, 'cantidad' => null],
    ]);
    $method = new ReflectionMethod(DashboardController::class, 'inventoryStockValue');

    expect($method->invoke(new DashboardController, 60, false))->toBe(200.0);
});
