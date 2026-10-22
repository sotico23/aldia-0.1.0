<?php

/**
 * Reverb Event Integration Tests
 */

namespace Tests\Feature;

use App\Events\DeliveryOrderAssigned;
use App\Events\DeliveryPositionUpdated;
use App\Events\MensajeEnviado;
use App\Events\NotificationCreated;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class ReverbEventIntegrationTest extends TestCase
{
    /**
     * Simula la difusion del evento y devuelve el canal anunciado.
     */
    protected function broadcastAndGetChannel(object $event): string
    {
        Broadcast::shouldReceive('broadcast')
            ->once()
            ->andReturnUsing(fn ($e) => $e->getChannel());

        return Broadcast::broadcast($event);
    }

    public function test_notification_created_broadcasts_to_correct_channel(): void
    {
        $event = new class extends NotificationCreated
        {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $channel = $this->broadcastAndGetChannel($event);

        $this->assertStringContainsString('notifications', $channel);
    }

    public function test_delivery_order_assigned_broadcasts_to_driver_channel(): void
    {
        $event = new class extends DeliveryOrderAssigned
        {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $channel = $this->broadcastAndGetChannel($event);

        $this->assertStringContainsString('delivery.driver', $channel);
    }

    public function test_delivery_position_updated_broadcasts_to_order_channel(): void
    {
        $event = new class extends DeliveryPositionUpdated
        {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $channel = $this->broadcastAndGetChannel($event);

        $this->assertStringContainsString('delivery.orders', $channel);
    }

    public function test_mensaje_enviado_broadcasts_to_conversation_channel(): void
    {
        $event = new class extends MensajeEnviado
        {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $channel = $this->broadcastAndGetChannel($event);

        $this->assertStringContainsString('chat.conversation', $channel);
    }

    public function test_events_have_required_data_fields(): void
    {
        $events = [
            new class extends NotificationCreated
            {
                public function __construct()
                {
                    parent::__construct();
                }
            },
            new class extends DeliveryOrderAssigned
            {
                public function __construct()
                {
                    parent::__construct();
                }
            },
            new class extends DeliveryPositionUpdated
            {
                public function __construct()
                {
                    parent::__construct();
                }
            },
            new class extends MensajeEnviado
            {
                public function __construct()
                {
                    parent::__construct();
                }
            },
        ];

        foreach ($events as $event) {
            $data = $event->getEventData();

            $this->assertArrayHasKey('id', $data);
            $this->assertArrayHasKey('type', $data);
            $this->assertArrayHasKey('created_at', $data);

            // Each event type should have its specific fields
            $this->assertNotEmpty($data['type']);
        }
    }
}
