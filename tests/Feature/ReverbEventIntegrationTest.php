<?php
/**
 * Reverb Event Integration Tests
 */

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Events\DeliveryOrderAssigned;
use App\Events\DeliveryPositionUpdated;
use App\Events\MensajeEnviado;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class ReverbEventIntegrationTest extends TestCase
{
    public function test_notification_created_broadcasts_to_correct_channel(): void
    {
        $event = new class extends NotificationCreated {
            public function __construct()
            {
                parent::__construct();
            }
        };

        Broadcast::shouldReceive('broadcast')
            ->with(\Mockery::on(function ($e) use ($event) {
                return get_class($e) === get_class($event);
            }))
            ->once()
            ->andReturnUsing(function ($e) {
                return $e->getChannel();
            });

        $channel = $event->getChannel();
        
        $this->assertStringContainsString('notifications', $channel);
    }

    public function test_delivery_order_assigned_broadcasts_to_driver_channel(): void
    {
        $event = new class extends DeliveryOrderAssigned {
            public function __construct()
            {
                parent::__construct();
            }
        };

        Broadcast::shouldReceive('broadcast')
            ->with(\Mockery::on(function ($e) use ($event) {
                return get_class($e) === get_class($event);
            }))
            ->once()
            ->andReturnUsing(function ($e) {
                return $e->getChannel();
            });

        $channel = $event->getChannel();
        
        $this->assertStringContainsString('delivery.driver', $channel);
    }

    public function test_delivery_position_updated_broadcasts_to_order_channel(): void
    {
        $event = new class extends DeliveryPositionUpdated {
            public function __construct()
            {
                parent::__construct();
            }
        };

        Broadcast::shouldReceive('broadcast')
            ->with(\Mockery::on(function ($e) use ($event) {
                return get_class($e) === get_class($event);
            }))
            ->once()
            ->andReturnUsing(function ($e) {
                return $e->getChannel();
            });

        $channel = $event->getChannel();
        
        $this->assertStringContainsString('delivery.orders', $channel);
    }

    public function test_mensaje_enviado_broadcasts_to_conversation_channel(): void
    {
        $event = new class extends MensajeEnviado {
            public function __construct()
            {
                parent::__construct();
            }
        };

        Broadcast::shouldReceive('broadcast')
            ->with(\Mockery::on(function ($e) use ($event) {
                return get_class($e) === get_class($event);
            }))
            ->once()
            ->andReturnUsing(function ($e) {
                return $e->getChannel();
            });

        $channel = $event->getChannel();
        
        $this->assertStringContainsString('chat.conversation', $channel);
    }

    public function test_events_have_required_data_fields(): void
    {
        $events = [
            new class extends NotificationCreated {
                public function __construct() { parent::__construct(); }
            },
            new class extends DeliveryOrderAssigned {
                public function __construct() { parent::__construct(); }
            },
            new class extends DeliveryPositionUpdated {
                public function __construct() { parent::__construct(); }
            },
            new class extends MensajeEnviado {
                public function __construct() { parent::__construct(); }
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