<?php

/**
 * Channel Authorization Feature Tests
 *
 * Los canales se registran en `routes/channels.php` durante el arranque de la
 * aplicacion, por lo que no es posible observar el registro con un mock de
 * `Broadcast::channel()`. En su lugar se leen los callbacks realmente
 * registrados en el driver y se invocan con el usuario y el identificador
 * del recurso.
 */

namespace Tests\Feature;

use App\Models\Conversacion;
use App\Models\PublicProfile;
use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class ChannelAuthorizationTest extends TestCase
{
    /**
     * Recupera el callback registrado para un patron de canal.
     */
    protected function channelCallback(string $pattern): callable
    {
        $channels = Broadcast::driver()->getChannels();

        $this->assertTrue(
            $channels->has($pattern),
            "El canal [{$pattern}] no esta registrado en routes/channels.php."
        );

        return $channels->get($pattern);
    }

    public function test_notifications_channel_authorization(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $callback = $this->channelCallback('notifications.{userId}');

        $this->assertTrue((bool) $callback($user, $user->id));
        $this->assertFalse((bool) $callback($otherUser, $user->id));
    }

    public function test_delivery_driver_channel_authorization(): void
    {
        $user = User::factory()->create();
        $repartidor = Repartidor::factory()->create([
            'owner_id' => $user->id,
            'user_id' => $user->id,
        ]);

        $otherRepartidor = Repartidor::factory()->create();

        $callback = $this->channelCallback('delivery.driver.{driverId}');

        $this->assertTrue((bool) $callback($user, $repartidor->id));
        $this->assertFalse((bool) $callback($user, $otherRepartidor->id));
    }

    public function test_delivery_orders_channel_authorization(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $callback = $this->channelCallback('delivery.orders.{orderId}');

        $this->assertTrue((bool) $callback($user, $user->id));
        $this->assertFalse((bool) $callback($otherUser, $user->id));
    }

    public function test_chat_conversation_channel_authorization(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $profile = PublicProfile::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $conversacion = Conversacion::factory()->create([
            'public_profile_id' => $profile->id,
            'comprador_id' => $user->id,
            'vendedor_id' => $otherUser->id,
        ]);

        $callback = $this->channelCallback('chat.conversation.{id}');

        $this->assertTrue((bool) $callback($user, $conversacion->id));
        $this->assertTrue((bool) $callback($otherUser, $conversacion->id));
        $this->assertFalse((bool) $callback(User::factory()->create(), $conversacion->id));
    }
}
