<?php

use App\Events\DeliveryOrderPoolUpdated;
use App\Events\EntregaDisponiblePool;
use App\Http\Controllers\Api\Delivery\DeliveryOrdenController;
use App\Http\Controllers\Backend\EntregaController as WebEntregaController;
use App\Http\Requests\Delivery\CompleteDeliveryRequest;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Services\DeliveryHistoryService;
use App\Services\EntregaPedidoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    Schema::create('conductores', function (Blueprint $table): void {
        $table->id();
        $table->string('nombre');
    });

    Schema::create('pedidos', function (Blueprint $table): void {
        $table->id();
        foreach (['owner_id', 'user_id', 'repartidor_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        $table->string('numero_pedido')->unique();
        foreach (['estado', 'dispatch_mode', 'nombre_cliente', 'telefono_cliente', 'direccion_cliente', 'notas', 'metodo_pago', 'payment_status'] as $column) {
            $table->string($column)->nullable();
        }
        foreach (['subtotal', 'impuesto', 'total', 'costo_envio', 'destino_lat', 'destino_lng', 'distancia_km', 'ganancia_repartidor'] as $column) {
            $table->decimal($column, 12, 7)->nullable();
        }
        foreach (['hora_aceptado', 'hora_recogido', 'hora_entregado', 'pool_visible_at', 'pool_entrada_at', 'fecha_entrega'] as $column) {
            $table->timestamp($column)->nullable();
        }
        $table->boolean('pool_bloqueado')->default(false);
        $table->timestamps();
    });
    (require database_path('migrations/2026_11_15_115131_add_delivery_photo_path_to_pedidos_table.php'))->up();
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
        $table->string('estado')->nullable();
        $table->decimal('comision_pct')->default(80);
        $table->timestamps();
    });
    Schema::create('ventas', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id');
        $table->unsignedBigInteger('user_id');
        $table->string('numero');
        $table->timestamps();
    });

    Route::post('/testing/delivery/{pedido}/delivered', function (CompleteDeliveryRequest $request, Pedido $pedido) {
        $userId = config('testing.delivery_user_id', 99);
        $request->setUserResolver(fn () => User::factory()->make(['id' => $userId]));
        $request->attributes->set('delivery.repartidor', Repartidor::where('user_id', $userId)->first());

        return app(DeliveryOrdenController::class)->delivered($request, $pedido);
    })->middleware('api');
    Route::get('/testing/entregas/{entrega}/evidencia', [WebEntregaController::class, 'evidencePhoto'])
        ->middleware(['api', 'permission:flota.entregas.viewAny']);
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

function deliveryWithPhoto(): Pedido
{
    $entrega = poolEntrega();
    $entrega->save();
    $pedido = app(EntregaPedidoService::class)->sync($entrega);
    Repartidor::create(['owner_id' => 10, 'user_id' => 99, 'estado' => 'ocupado', 'comision_pct' => 80]);
    $pedido->update(['estado' => 'enviado', 'repartidor_id' => 99]);

    return $pedido;
}

function evidencePhoto(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('entrega.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7X8AAAAASUVORK5CYII='));
}

test('no se puede completar una entrega sin foto ni con un archivo falso', function () {
    $pedido = deliveryWithPhoto();
    $endpoint = '/testing/delivery/'.$pedido->id.'/delivered';
    $this->postJson($endpoint)->assertUnprocessable()->assertJsonValidationErrors('foto_entrega');
    $this->post($endpoint, ['foto_entrega' => UploadedFile::fake()->create('falsa.jpg', 1, 'text/plain')], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('foto_entrega');
    expect($pedido->fresh()->estado)->toBe('enviado');
});

test('guarda la foto privada antes de completar la entrega y actualizar la web', function () {
    Storage::fake('local');
    Event::fake([DeliveryOrderPoolUpdated::class]);
    $pedido = deliveryWithPhoto();
    $this->post('/testing/delivery/'.$pedido->id.'/delivered', ['foto_entrega' => evidencePhoto()], ['Accept' => 'application/json'])
        ->assertSuccessful()->assertJsonPath('pedido.has_delivery_photo', true);

    $pedido->refresh();
    Storage::disk('local')->assertExists($pedido->delivery_photo_path);
    expect($pedido->estado)->toBe('entregado')
        ->and($pedido->entrega->estado)->toBe('entregado');
});

test('un fallo al guardar la foto deja el pedido en reparto', function () {
    $pedido = deliveryWithPhoto();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $this->post('/testing/delivery/'.$pedido->id.'/delivered', ['foto_entrega' => evidencePhoto()], ['Accept' => 'application/json'])
        ->assertServiceUnavailable();
    expect($pedido->fresh()->estado)->toBe('enviado')
        ->and($pedido->fresh()->delivery_photo_path)->toBeNull();
});

test('un repartidor ajeno no puede adjuntar evidencia y completar el pedido', function () {
    Storage::fake('local');
    $pedido = deliveryWithPhoto();
    config()->set('testing.delivery_user_id', 88);
    $this->post('/testing/delivery/'.$pedido->id.'/delivered', ['foto_entrega' => evidencePhoto()], ['Accept' => 'application/json'])
        ->assertForbidden();
    expect($pedido->fresh()->estado)->toBe('enviado');
    Storage::disk('local')->assertDirectoryEmpty('delivery-evidence');
});

test('el registro web expone repartidor horarios y evidencia sin filtrar la ruta privada', function () {
    DB::table('users')->insert(['id' => 99, 'name' => 'Repartidor de prueba']);
    $pedido = deliveryWithPhoto();
    $pedido->update([
        'estado' => 'entregado', 'hora_aceptado' => now()->subMinutes(30),
        'hora_recogido' => now()->subMinutes(20), 'hora_entregado' => now(),
        'delivery_photo_path' => 'delivery-evidence/10/'.$pedido->id.'/foto.jpg',
    ]);
    $other = poolEntrega(20);
    $other->fill(['estado' => 'entregado'])->save();

    $history = app(DeliveryHistoryService::class)->paginateForOwner(10)->toArray();
    expect($history['total'])->toBe(1)
        ->and($history['data'][0]['driver_name'])->toBe('Repartidor de prueba')
        ->and($history['data'][0]['has_photo'])->toBeTrue()
        ->and($history['data'][0]['delivered_at'])->toBe($pedido->hora_entregado->toISOString())
        ->and($history['data'][0])->not->toHaveKey('delivery_photo_path');
});

test('los registros antiguos sin foto ni hora se muestran sin inventar evidencia', function () {
    $entrega = poolEntrega();
    $entrega->fill(['estado' => 'entregado'])->save();
    $record = app(DeliveryHistoryService::class)->paginateForOwner(10)->items()[0];
    expect($record['has_photo'])->toBeFalse()
        ->and($record['driver_name'])->toBeNull()
        ->and($record['delivered_at'])->toBeNull();
});

function historyWebUser(int $ownerId, bool $canView = true): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => $ownerId, 'is_active' => true]);
    $user->shouldReceive('hasRole')->andReturn(false);
    $user->shouldReceive('can')->with('flota.entregas.viewAny')->andReturn($canView);
    $user->shouldReceive('isTrialActive')->andReturn(true);

    return $user;
}

test('la foto privada solo se sirve con permiso y dentro de la empresa', function () {
    Storage::fake('local');
    $pedido = deliveryWithPhoto();
    $path = 'delivery-evidence/10/'.$pedido->id.'/foto.png';
    Storage::disk('local')->put($path, evidencePhoto()->getContent());
    $pedido->update(['delivery_photo_path' => $path]);
    $entrega = $pedido->entrega;

    $this->actingAs(historyWebUser(10))->get('/testing/entregas/'.$entrega->id.'/evidencia')
        ->assertSuccessful()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs(historyWebUser(10, false))->get('/testing/entregas/'.$entrega->id.'/evidencia')->assertForbidden();
    $this->actingAs(historyWebUser(20))->get('/testing/entregas/'.$entrega->id.'/evidencia')->assertNotFound();
});

test('la evidencia inexistente devuelve 404 y la ruta publica exige permiso de entregas', function () {
    $pedido = deliveryWithPhoto();
    $this->actingAs(historyWebUser(10))->get('/testing/entregas/'.$pedido->entrega->id.'/evidencia')->assertNotFound();
    $route = Route::getRoutes()->getByName('entregas.evidencia');
    expect($route->gatherMiddleware())->toContain('permission:flota.entregas.viewAny', 'ownership:entrega');
});
