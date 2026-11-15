<?php

use App\Events\DeliveryOrderPoolUpdated;
use App\Events\EntregaDisponiblePool;
use App\Http\Controllers\Api\Delivery\DeliveryOrdenController;
use App\Http\Controllers\Api\Delivery\PushDeviceController;
use App\Http\Controllers\Backend\EntregaController as WebEntregaController;
use App\Http\Controllers\Backend\PublicProfileController;
use App\Http\Requests\Delivery\CompleteDeliveryRequest;
use App\Http\Requests\Delivery\StorePushDeviceRequest;
use App\Http\Requests\UpdatePublicProfileRequest;
use App\Jobs\SendDeliveryPush;
use App\Livewire\SystemSettingsPage;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\PublicProfile;
use App\Models\PushDevice;
use App\Models\Repartidor;
use App\Models\User;
use App\Services\DeliveryHistoryService;
use App\Services\DeliveryPushService;
use App\Services\DeliveryStoreService;
use App\Services\EntregaPedidoService;
use App\Support\SystemSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(true);
        $table->timestamp('banned_at')->nullable();
    });
    Schema::create('conductores', function (Blueprint $table): void {
        $table->id();
        $table->string('nombre');
    });
    Schema::create('public_profiles', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->unsignedBigInteger('user_id');
        $table->string('title');
        $table->string('slug')->unique();
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });
    (require database_path('migrations/2026_11_15_154857_add_pickup_location_to_public_profiles_table.php'))->up();
    Schema::create('personal_access_tokens', function (Blueprint $table): void {
        $table->id();
        $table->morphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamps();
    });
    (require database_path('migrations/2026_11_15_131343_create_push_devices_table.php'))->up();
    Schema::create('settings', function (Blueprint $table): void {
        $table->id();
        $table->string('key')->unique();
        $table->text('value')->nullable();
        $table->string('group')->nullable();
        $table->boolean('is_encrypted')->default(false);
        $table->timestamps();
    });
    config()->set('cache.default', 'array');
    Cache::flush();
    Http::preventStrayRequests();

    Schema::create('pedidos', function (Blueprint $table): void {
        $table->id();
        foreach (['owner_id', 'user_id', 'repartidor_id', 'public_profile_id'] as $column) {
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
        $table->string('approval_status')->default('approved');
        $table->string('driver_type')->default('employee');
        $table->boolean('is_online')->default(true);
        $table->decimal('lat', 12, 7)->nullable();
        $table->decimal('lng', 12, 7)->nullable();
        $table->decimal('radio_km')->default(10);
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
    Route::post('/testing/push-device', function (StorePushDeviceRequest $request) {
        $user = User::findOrFail(99);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $user->tokens()->firstOrFail());

        return app(PushDeviceController::class)->store($request);
    })->middleware('api');
});

function enablePushySettings(): void
{
    SystemSettings::save([
        'PUSHY_ENABLED' => ['value' => 'true', 'group' => 'pushy'],
        'PUSHY_SECRET_API_KEY' => ['value' => 'server-test-secret', 'secret' => true, 'group' => 'pushy'],
    ]);
}

function pushDeviceForTest(): PushDevice
{
    DB::table('users')->insertOrIgnore(['id' => 99, 'name' => 'Repartidor', 'is_active' => true]);
    $user = User::findOrFail(99);
    $token = $user->createToken('test-pushy')->accessToken;

    return PushDevice::create(['user_id' => 99, 'personal_access_token_id' => $token->id,
        'token' => 'valid-pushy-device-token', 'token_hash' => hash('sha256', 'valid-pushy-device-token')]);
}

test('la pestaña servidor guarda Pushy cifrado y conserva la clave si se deja vacia', function () {
    Livewire::test(SystemSettingsPage::class)
        ->assertSee('Pushy / Notificaciones de repartos')
        ->set('values.PUSHY_ENABLED', 'true')
        ->set('values.PUSHY_APP_ID', 'public-app-id')
        ->set('secrets.PUSHY_SECRET_API_KEY', 'private-key-from-screen')
        ->call('save')->assertHasNoErrors()
        ->assertSet('secrets.PUSHY_SECRET_API_KEY', '')
        ->assertSet('hasStoredSecret.PUSHY_SECRET_API_KEY', true)
        ->assertDontSee('private-key-from-screen');

    expect(DB::table('settings')->where('key', 'PUSHY_SECRET_API_KEY')->value('value'))->not->toContain('private-key-from-screen');
    Livewire::test(SystemSettingsPage::class)->set('secrets.PUSHY_SECRET_API_KEY', '')->call('save')->assertHasNoErrors();
    expect(SystemSettings::get('PUSHY_SECRET_API_KEY'))->toBe('private-key-from-screen');
    $service = app(DeliveryPushService::class);
    expect($service->publicConfiguration())->toBe(['enabled' => true, 'app_id' => 'public-app-id']);
    SystemSettings::save(['PUSHY_ENABLED' => ['value' => 'false', 'group' => 'pushy']]);
    expect($service->isEnabled())->toBeFalse();
});

test('un push usa la configuracion de la pantalla y no se repite aunque se ejecute de nuevo', function () {
    $pedido = deliveryWithPhoto();
    $pedido->update(['estado' => 'preparando']);
    $device = pushDeviceForTest();
    enablePushySettings();
    Http::fake(['api.pushy.me/*' => Http::response(['success' => true], 200)]);
    $job = new SendDeliveryPush($device->id, 99, $pedido->id, 'assigned', $pedido->assignmentAlertKey());
    $job->handle(app(DeliveryPushService::class));
    $job->handle(app(DeliveryPushService::class));
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'api_key=server-test-secret')
        && $request['data']['type'] === 'assigned' && $request['data']['user_id'] === 99);
    expect(DB::table('push_devices')->value('token'))->not->toBe('valid-pushy-device-token')
        ->and($device->toArray())->not->toHaveKey('token');
});

test('el cierre de sesion elimina el dispositivo y evita envios pendientes', function () {
    $pedido = deliveryWithPhoto();
    $pedido->update(['estado' => 'preparando']);
    $device = pushDeviceForTest();
    enablePushySettings();
    $device->accessToken->delete();
    expect(PushDevice::find($device->id))->toBeNull();
    (new SendDeliveryPush($device->id, 99, $pedido->id, 'assigned', $pedido->assignmentAlertKey()))->handle(app(DeliveryPushService::class));
    Http::assertNothingSent();
});

test('el pool no notifica pedidos reservados fuera de la empresa ni repartidores offline', function () {
    $entrega = poolEntrega();
    $entrega->save();
    $pedido = app(EntregaPedidoService::class)->sync($entrega);
    $device = pushDeviceForTest();
    $driver = Repartidor::create(['user_id' => 99, 'owner_id' => 20, 'estado' => 'disponible', 'approval_status' => 'approved', 'driver_type' => 'employee', 'is_online' => true]);
    $service = app(DeliveryPushService::class);
    expect($service->canReceive($pedido, $driver, 'pool'))->toBeFalse();
    $driver->update(['owner_id' => 10, 'is_online' => false]);
    expect($service->canReceive($pedido, $driver->fresh(), 'pool'))->toBeFalse();
    $driver->update(['is_online' => true]);
    expect($service->canReceive($pedido, $driver->fresh(), 'pool'))->toBeTrue();
});

test('los tokens push invalidos se retiran y los fallos transitorios permiten reintento', function () {
    $pedido = deliveryWithPhoto();
    $pedido->update(['estado' => 'preparando']);
    $device = pushDeviceForTest();
    enablePushySettings();
    Http::fake(['api.pushy.me/*' => Http::sequence()->push([], 503)->push(['success' => true], 200)->push(['code' => 'NO_RECIPIENTS'], 400)]);
    $job = new SendDeliveryPush($device->id, 99, $pedido->id, 'assigned', $pedido->assignmentAlertKey());
    expect(fn () => $job->handle(app(DeliveryPushService::class)))->toThrow(RuntimeException::class);
    $job->handle(app(DeliveryPushService::class));
    Http::assertSentCount(2);
    Cache::flush();
    $job->handle(app(DeliveryPushService::class));
    expect($device->fresh())->toBeNull();
});

test('registrar el dispositivo ignora el usuario del cuerpo y requiere autenticacion real', function () {
    $device = pushDeviceForTest();
    $this->postJson('/testing/push-device', ['token' => 'new-device-token-123456', 'user_id' => 20])->assertSuccessful();
    expect(PushDevice::where('token_hash', hash('sha256', 'new-device-token-123456'))->first()->user_id)->toBe(99);
    $this->postJson('/api/v1/delivery/push-device', ['token' => 'new-device-token-123456'])->assertUnauthorized();
});

test('la cola push se dispara despues del commit y no desde una transaccion revertida', function () {
    $pedido = deliveryWithPhoto();
    $pedido->update(['estado' => 'preparando']);
    pushDeviceForTest();
    enablePushySettings();
    Bus::fake([SendDeliveryPush::class]);
    DB::beginTransaction();
    $pedido->update(['hora_aceptado' => now()]);
    Bus::assertNotDispatched(SendDeliveryPush::class);
    DB::rollBack();
    Bus::assertNotDispatched(SendDeliveryPush::class);
    DB::transaction(fn () => $pedido->fresh()->update(['hora_aceptado' => now()->addMinute()]));
    Bus::assertDispatched(SendDeliveryPush::class, fn ($job) => $job->userId === 99 && $job->type === 'assigned');
});

test('no permite activar Pushy sin una clave configurada', function () {
    Livewire::test(SystemSettingsPage::class)->set('values.PUSHY_ENABLED', 'true')
        ->call('save')->assertHasErrors('secrets.PUSHY_SECRET_API_KEY');
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

test('las claves de alerta son estables al consultar y cambian para una nueva asignacion', function () {
    $entrega = poolEntrega();
    $entrega->save();
    $pedido = app(EntregaPedidoService::class)->sync($entrega);
    $poolKey = $pedido->poolAlertKey();
    $pedido->update(['nombre_cliente' => 'Nombre actualizado']);
    expect($pedido->fresh()->poolAlertKey())->toBe($poolKey);
    $assignmentKey = $pedido->assignmentAlertKey();
    $pedido->update(['repartidor_id' => 99, 'hora_aceptado' => now()]);
    expect($pedido->assignmentAlertKey())->not->toBe($assignmentKey)
        ->and($pedido->assignmentAlertKey())->not->toBe($poolKey);
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
    $historyRoute = Route::getRoutes()->getByName('entregas.registro');
    expect($historyRoute->getActionMethod())->toBe('deliveryHistory')
        ->and($historyRoute->gatherMiddleware())->toContain('permission:flota.entregas.viewAny');
});

test('el mapa publica solo tiendas activas ubicadas y respeta el alcance del repartidor', function () {
    DB::table('users')->insert([
        ['id' => 10, 'name' => 'Comercio local', 'is_active' => true],
        ['id' => 20, 'name' => 'Otro comercio', 'is_active' => true],
        ['id' => 30, 'name' => 'Comercio suspendido', 'is_active' => false],
    ]);
    foreach ([
        [1, 10, true, -33.45, -70.66], [2, 20, true, -33.451, -70.661],
        [3, 10, false, -33.45, -70.66], [4, 10, true, null, null],
        [5, 30, true, -33.45, -70.66], [6, 10, true, -40, -73],
    ] as [$id, $owner, $active, $lat, $lng]) {
        DB::table('public_profiles')->insert(['id' => $id, 'owner_id' => $owner, 'user_id' => $owner,
            'slug' => "store-{$id}", 'title' => "Comercio {$id}", 'is_active' => $active,
            'pickup_address' => 'Calle 123', 'pickup_lat' => $lat, 'pickup_lng' => $lng]);
    }
    $driver = new Repartidor(['owner_id' => 10, 'approval_status' => 'approved',
        'driver_type' => 'employee', 'lat' => -33.45, 'lng' => -70.66, 'radio_km' => 5]);
    $service = app(DeliveryStoreService::class);
    expect(array_column($service->stores($driver), 'id'))->toBe([1]);
    $driver->driver_type = 'freelancer';
    expect(array_column($service->stores($driver), 'id'))->toBe([1, 2]);
    $driver->approval_status = 'pending';
    expect($service->stores($driver))->toBe([]);
    $this->getJson('/api/v1/delivery/stores')->assertUnauthorized();
});

test('el pedido utiliza su comercio explicito y nunca el perfil del comprador o de otro tenant', function () {
    DB::table('public_profiles')->insert([
        ['id' => 1, 'owner_id' => 10, 'user_id' => 10, 'slug' => 'principal', 'title' => 'Principal', 'is_active' => true],
        ['id' => 2, 'owner_id' => 10, 'user_id' => 11, 'slug' => 'sucursal', 'title' => 'Sucursal', 'is_active' => true],
        ['id' => 3, 'owner_id' => 20, 'user_id' => 20, 'slug' => 'otro', 'title' => 'Otro', 'is_active' => true],
    ]);
    $service = app(DeliveryStoreService::class);
    $order = new Pedido(['owner_id' => 10, 'user_id' => 20, 'public_profile_id' => 2]);
    expect($service->pickup($order)['title'])->toBe('Sucursal');
    $order = new Pedido(['owner_id' => 10, 'user_id' => 20]);
    expect($service->pickup($order)['title'])->toBe('Principal');
    $order = new Pedido(['owner_id' => 10, 'public_profile_id' => 3]);
    expect($service->pickup($order))->toBeNull();
});

test('la API conserva el comercio y las coordenadas del cliente antes y despues de retirar', function () {
    DB::table('public_profiles')->insert(['id' => 1, 'owner_id' => 10, 'user_id' => 10,
        'slug' => 'retiro', 'title' => 'Comercio de retiro', 'is_active' => true,
        'pickup_address' => 'Comercio 123', 'pickup_lat' => -33.45, 'pickup_lng' => -70.66]);
    $entrega = poolEntrega();
    $entrega->save();
    $pedido = app(EntregaPedidoService::class)->sync($entrega);
    $pedido->update(['repartidor_id' => 99, 'hora_aceptado' => now(), 'destino_lat' => -33.46, 'destino_lng' => -70.67]);
    $request = Request::create('/api/v1/delivery/orders');
    $request->setUserResolver(fn () => (new User)->forceFill(['id' => 99]));
    $request->attributes->set('delivery.repartidor', new Repartidor(['owner_id' => 10, 'approval_status' => 'approved', 'comision_pct' => 80]));
    $controller = app(DeliveryOrdenController::class);
    $before = $controller->orders($request)->getData(true)['pedidos_activos'][0];
    expect($before['pickup_store']['title'])->toBe('Comercio de retiro')
        ->and($before['pickup_store']['lat'])->toBe(-33.45)->and($before['hora_recogido'])->toBeNull();
    Event::fake([DeliveryOrderPoolUpdated::class]);
    $controller->pickup($request, $pedido);
    $after = $controller->orders($request)->getData(true)['pedidos_activos'][0];
    expect($after['estado'])->toBe('enviado')->and($after['hora_recogido'])->not->toBeNull()
        ->and($after['pickup_store'])->toBe($before['pickup_store'])->and((float) $after['destino_lat'])->toBe(-33.46);
});

test('la ubicacion de retiro valida el par de coordenadas y requiere direccion', function () {
    $request = new UpdatePublicProfileRequest;
    $request->setUserResolver(fn () => new User(['id' => 10]));
    $rules = $request->rules();
    unset($rules['slug']);
    $validator = validator(['title' => 'Comercio', 'pickup_lat' => 91, 'pickup_lng' => -181], $rules);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain('pickup_lat', 'pickup_lng', 'pickup_address');
    expect(validator(['title' => 'Comercio', 'pickup_address' => 'Local 123', 'pickup_lat' => -33.45], $rules)->fails())->toBeTrue();
    expect(validator(['title' => 'Comercio', 'pickup_address' => 'Local 123', 'pickup_lat' => -33.45, 'pickup_lng' => -70.66], $rules)->passes())->toBeTrue();
});

test('guardar la configuracion publica persiste el punto de retiro de la propia tienda', function () {
    DB::table('users')->insert(['id' => 10, 'name' => 'Comerciante', 'is_active' => true]);
    DB::table('public_profiles')->insert(['id' => 1, 'owner_id' => 10, 'user_id' => 10,
        'title' => 'Comercio', 'slug' => 'comercio', 'is_active' => true]);
    $request = UpdatePublicProfileRequest::create('/settings/public-profile', 'PATCH', [
        'title' => 'Comercio', 'slug' => 'comercio', 'is_active' => true,
        'pickup_address' => 'Comercio 123', 'pickup_lat' => '-33.45', 'pickup_lng' => '-70.66',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => User::findOrFail(10));
    $request->validateResolved();
    app(PublicProfileController::class)->update($request);
    $store = PublicProfile::findOrFail(1);
    expect($store->pickup_address)->toBe('Comercio 123')->and($store->pickup_lat)->toBe(-33.45)
        ->and($store->pickup_lng)->toBe(-70.66)->and($store->user_id)->toBe(10);
});
