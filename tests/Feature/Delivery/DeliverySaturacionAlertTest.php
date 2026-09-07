<?php

use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\DeliverySaturacionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);

    // Primer usuario creado se convierte en Super Admin; se crea un dummy
    // para absorber ese rol y que los demas usuarios queden en niveles bajos.
    User::factory()->create(['email' => 'dummy-setup@test.local']);

    $this->owner = User::factory()->create();

    $this->zona = Zone::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Centro',
        'lat' => -33.4489,
        'lng' => -70.6693,
        'radio_km' => 10,
        'capacidad_max' => 5,
    ]);

    $this->repartidorUser = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);
    $this->repartidorUser->assignRole('Repartidor');

    $this->repartidor = Repartidor::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->repartidorUser->id,
        'estado' => 'disponible',
        'lat' => -33.4489,
        'lng' => -70.6693,
        'radio_km' => 10,
    ]);

    $this->crearPedidoPool = function (array $overrides = []) {
        return Pedido::factory()->create(array_merge([
            'owner_id' => $this->owner->id,
            'user_id' => $this->owner->id,
            'estado' => 'preparando',
            'destino_lat' => -33.452,
            'destino_lng' => -70.665,
        ], $overrides));
    };
});

test('envia una alerta al owner cuando quedan pedidos en pool sin repartidor', function () {
    Notification::fake();

    // Sin zona activa que cubra el pedido, el asignador no puede repartirlo.
    Zone::withoutGlobalScopes()->delete();

    $pedido = ($this->crearPedidoPool)();

    Artisan::call('delivery:auto-assign');

    $pedido->refresh();
    expect($pedido->repartidor_id)->toBeNull();

    Notification::assertSentTo(
        $this->owner,
        DeliverySaturacionNotification::class,
        fn (DeliverySaturacionNotification $notification) => $notification->ownerId === $this->owner->id
            && $notification->pendientes === 1
    );
});

test('la alerta respeta el cooldown y no se repite en corridas seguidas', function () {
    Notification::fake();

    Zone::withoutGlobalScopes()->delete();
    ($this->crearPedidoPool)();

    Artisan::call('delivery:auto-assign');
    Artisan::call('delivery:auto-assign');

    Notification::assertSentTo($this->owner, DeliverySaturacionNotification::class, 1);
});

test('la alerta vuelve a dispararse cuando expira el cooldown', function () {
    Notification::fake();

    Zone::withoutGlobalScopes()->delete();
    ($this->crearPedidoPool)();

    Artisan::call('delivery:auto-assign');

    // Simula el paso del cooldown (30 min): la clave de cache ya no existe.
    Cache::forget("zones.saturacion.alerta.{$this->owner->id}");

    Artisan::call('delivery:auto-assign');

    Notification::assertSentTo($this->owner, DeliverySaturacionNotification::class, 2);
});

test('no notifica al owner de otro tenant sin pedidos en pool', function () {
    Notification::fake();

    Zone::withoutGlobalScopes()->delete();

    $otroOwner = User::factory()->create();
    ($this->crearPedidoPool)(['owner_id' => $otroOwner->id]);

    Artisan::call('delivery:auto-assign');

    Notification::assertNotSentTo($this->owner, DeliverySaturacionNotification::class);
    Notification::assertSentTo($otroOwner, DeliverySaturacionNotification::class);
});

test('no envia alerta cuando todo se asigna correctamente', function () {
    Notification::fake();

    ($this->crearPedidoPool)();

    Artisan::call('delivery:auto-assign');

    Notification::assertNotSentTo($this->owner, DeliverySaturacionNotification::class);
});

test('no envia alerta cuando no hay pedidos en el pool', function () {
    Notification::fake();

    Artisan::call('delivery:auto-assign');

    Notification::assertNotSentTo($this->owner, DeliverySaturacionNotification::class);
});
