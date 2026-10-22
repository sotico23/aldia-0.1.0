<?php

/**
 * Pest Test Configuration
 */

use App\Events\DeliveryOrderAssigned;
use App\Events\DeliveryPositionUpdated;
use App\Events\MensajeEnviado;
use App\Events\NotificationCreated;
use App\Models\Conversacion;
use App\Models\PublicProfile;
use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Pest 4 exige `pest()->extend(...)->in(...)` para que una clase base se
| aplique a los tests de un directorio. La forma antigua `uses(TestCase::class)`
| queda anclada al propio archivo tests/Pest.php y NO se propaga, por lo que
| los tests se quedaban sin instancia de Laravel y fallaban con
| "A facade root has not been set.".
|
| `RefreshDatabase` se aplica globalmente: con SQLite :memory: el esquema se
| migra una sola vez por proceso y cada test corre dentro de una transaccion
| que se revierte al terminar. Asi el suite es rapido y cada test parte de un
| estado limpio. Los datos base de referencia (paises y roles) los crea
| `Tests\TestCase::prepareDatabase()`.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

uses()->group('realtime')->in('Feature', 'Unit');

/**
 * Dataset for channel authorization tests
 *
 * Cada entrada contiene el patron registrado en routes/channels.php, el
 * prefijo esperado y un closure que arma el contexto (usuario + id) para
 * invocar el callback real del canal.
 */
dataset('channels', [
    'notifications' => [
        'notifications.{userId}',
        'notifications',
        function (): array {
            $user = User::factory()->create();

            return [$user, $user->id];
        },
    ],
    'delivery_driver' => [
        'delivery.driver.{driverId}',
        'delivery.driver',
        function (): array {
            $user = User::factory()->create();
            $repartidor = Repartidor::factory()->create([
                'owner_id' => $user->id,
                'user_id' => $user->id,
            ]);

            return [$user, $repartidor->id];
        },
    ],
    'delivery_orders' => [
        'delivery.orders.{orderId}',
        'delivery.orders',
        function (): array {
            $user = User::factory()->create();

            return [$user, $user->id];
        },
    ],
    'chat_conversation' => [
        'chat.conversation.{id}',
        'chat.conversation',
        function (): array {
            $user = User::factory()->create();
            $profile = PublicProfile::factory()->create();
            $conversacion = Conversacion::factory()->create([
                'public_profile_id' => $profile->id,
                'comprador_id' => $user->id,
            ]);

            return [$user, $conversacion->id];
        },
    ],
]);

/**
 * Dataset for event types
 */
dataset('events', [
    'notification_created' => [NotificationCreated::class, 'notifications'],
    'delivery_order_assigned' => [DeliveryOrderAssigned::class, 'delivery.driver'],
    'delivery_position_updated' => [DeliveryPositionUpdated::class, 'delivery.orders'],
    'mensaje_enviado' => [MensajeEnviado::class, 'chat.conversation'],
]);
