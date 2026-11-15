<?php

use App\Events\EntregaDisponiblePool;
use App\Http\Controllers\Api\Delivery\DeliveryOrdenController;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Services\EntregaPedidoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');

    Schema::create('pedidos', function (Blueprint $table): void {
        $table->id();
        foreach (['owner_id', 'user_id', 'repartidor_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        $table->string('numero_pedido')->unique();
        foreach (['estado', 'dispatch_mode', 'nombre_cliente', 'telefono_cliente', 'direccion_cliente', 'notas', 'metodo_pago', 'payment_status'] as $column) {
            $table->string($column)->nullable();
        }
        foreach (['subtotal', 'impuesto', 'total', 'costo_envio', 'destino_lat', 'destino_lng', 'distancia_km'] as $column) {
            $table->decimal($column, 12, 7)->nullable();
        }
        foreach (['hora_aceptado', 'hora_recogido', 'hora_entregado', 'pool_visible_at', 'pool_entrada_at'] as $column) {
            $table->timestamp($column)->nullable();
        }
        $table->boolean('pool_bloqueado')->default(false);
        $table->timestamps();
    });
    Schema::create('entregas', function (Blueprint $table): void {
        $table->id();
        foreach (['owner_id', 'venta_id', 'repartidor_id', 'conductor_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        foreach (['cliente', 'telefono', 'direccion', 'notas', 'estado', 'estado_asignacion'] as $column) {
            $table->string($column)->nullable();
        }
        $table->timestamps();
    });
    (require database_path('migrations/2026_11_15_110437_add_pedido_id_to_entregas_table.php'))->up();
    Schema::create('pedido_status_logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('pedido_id');
        foreach (['field', 'from', 'to', 'changed_by', 'gateway'] as $column) {
            $table->string($column)->nullable();
        }
        $table->timestamps();
    });
    foreach (['pedido_items' => 'pedido_id', 'entrega_items' => 'entrega_id', 'conversaciones' => 'pedido_id', 'delivery_configs' => 'owner_id'] as $name => $foreignKey) {
        Schema::create($name, function (Blueprint $table) use ($foreignKey): void {
            $table->id();
            $table->unsignedBigInteger($foreignKey);
        });
    }
    Schema::create('repartidores', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('owner_id');
        $table->timestamps();
    });
    Schema::create('ventas', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id');
        $table->unsignedBigInteger('user_id');
        $table->string('numero');
        $table->timestamps();
    });
});

function poolEntrega(int $ownerId = 10): Entrega
{
    return Entrega::factory()->make([
        'owner_id' => $ownerId, 'venta_id' => null,
        'cliente' => 'Cliente de prueba', 'direccion' => 'Dirección sin coordenadas',
        'repartidor_id' => null, 'conductor_id' => null,
        'estado' => 'pendiente', 'estado_asignacion' => 'libre',
    ])->setRawAttributes([
        'owner_id' => $ownerId, 'cliente' => 'Cliente de prueba',
        'direccion' => 'Dirección sin coordenadas', 'estado' => 'pendiente',
        'estado_asignacion' => 'libre',
    ]);
}

test('sincronizar una entrega crea un solo pedido y publica su identificador real', function () {
    $entrega = poolEntrega();
    $entrega->save();
    $service = app(EntregaPedidoService::class);
    $pedido = $service->sync($entrega);
    $again = $service->sync($entrega);

    expect($again->id)->toBe($pedido->id)
        ->and(Pedido::count())->toBe(1)
        ->and($entrega->fresh()->pedido_id)->toBe($pedido->id)
        ->and($pedido->estado)->toBe('preparando')
        ->and($pedido->pool_bloqueado)->toBeFalse()
        ->and((new EntregaDisponiblePool($entrega->fresh()))->broadcastWith()['pedido_id'])->toBe($pedido->id);
});

test('la API muestra la entrega manual sin GPS solo a su empresa', function () {
    $entrega = poolEntrega();
    $entrega->save();
    $pedido = app(EntregaPedidoService::class)->sync($entrega);
    $controller = app(DeliveryOrdenController::class);
    $unlinked = $pedido->replicate();
    $unlinked->numero_pedido = 'SIN-ENTREGA';
    $unlinked->save();

    foreach ([10 => 1, 20 => 0] as $ownerId => $expected) {
        $request = Request::create('/api/v1/delivery/orders');
        $request->setUserResolver(fn () => new User(['id' => 99]));
        $request->attributes->set('delivery.repartidor', new Repartidor([
            'owner_id' => $ownerId, 'driver_type' => 'employee',
            'is_online' => true, 'estado' => 'disponible', 'approval_status' => 'approved',
            'comision_pct' => 80,
        ]));
        $data = $controller->orders($request)->getData(true);
        expect($data['pool'])->toHaveCount($expected);
        if ($expected) {
            expect($data['pool'][0]['id'])->toBe($pedido->id);
        }
    }
});

test('los cambios del pedido en la APK actualizan la entrega web', function () {
    $entrega = poolEntrega();
    $entrega->save();
    $pedido = app(EntregaPedidoService::class)->sync($entrega);
    $driver = Repartidor::create(['owner_id' => 10, 'user_id' => 99]);

    $pedido->update(['repartidor_id' => 99, 'hora_aceptado' => now()]);
    expect($entrega->fresh()->repartidor_id)->toBe($driver->id)
        ->and($entrega->fresh()->estado_asignacion)->toBe('asignado');
    $pedido->update(['estado' => 'enviado']);
    expect($entrega->fresh()->estado)->toBe('en_ruta');
    $pedido->update(['estado' => 'entregado']);
    expect($entrega->fresh()->estado)->toBe('entregado');
});

test('una entrega reservada a conductor no entra al pool movil', function () {
    $entrega = poolEntrega();
    $entrega->fill(['estado_asignacion' => 'asignado', 'conductor_id' => 7])->save();
    $pedido = app(EntregaPedidoService::class)->sync($entrega);
    expect($pedido->pool_bloqueado)->toBeTrue();
});

test('el comando recupera entregas libres existentes sin duplicarlas y respeta el tenant', function () {
    $entrega = poolEntrega();
    $entrega->save();
    $other = poolEntrega(20);
    $other->save();

    $this->artisan('delivery:sync-entregas', ['--owner' => 10])->assertSuccessful();
    $this->artisan('delivery:sync-entregas', ['--owner' => 10])->assertSuccessful();
    expect(Pedido::count())->toBe(1)
        ->and($entrega->fresh()->pedido_id)->not->toBeNull()
        ->and($other->fresh()->pedido_id)->toBeNull();
});

test('reutiliza el pedido POS existente al liberar su entrega', function () {
    $ventaId = DB::table('ventas')->insertGetId(['owner_id' => 10, 'user_id' => 10, 'numero' => 'BOLETA-1']);
    $entrega = poolEntrega();
    $entrega->save();
    $service = app(EntregaPedidoService::class);
    $pedido = $service->sync($entrega);
    $pedido->update(['numero_pedido' => 'POS-DEL-'.$ventaId]);
    $entrega->forceFill(['pedido_id' => null, 'venta_id' => $ventaId])->saveQuietly();

    expect($service->sync($entrega)->id)->toBe($pedido->id)
        ->and(Pedido::count())->toBe(1);
});
