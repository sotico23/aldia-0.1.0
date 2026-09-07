<?php
/**
 * DeliveryPositionUpdated Event Test
 */

namespace Tests\Unit\Events;

use App\Events\DeliveryPositionUpdated;
use Tests\TestCase;

class DeliveryPositionUpdatedTest extends TestCase
{
    public function test_delivery_position_updated_event_has_correct_structure(): void
    {
        $event = new class extends DeliveryPositionUpdated {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $data = $event->getEventData();
        
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('position_id', $data);
        $this->assertArrayHasKey('order_id', $data);
        $this->assertArrayHasKey('driver_id', $data);
        $this->assertArrayHasKey('latitude', $data);
        $this->assertArrayHasKey('longitude', $data);
        $this->assertArrayHasKey('distance_km', $data);
        $this->assertArrayHasKey('estado_actual', $data);
        $this->assertArrayHasKey('created_at', $data);
        
        $this->assertEquals('delivery.position.updated', $data['type']);
    }

    public function test_delivery_position_updated_event_channel_format(): void
    {
        $event = new class extends DeliveryPositionUpdated {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $channel = $event->getChannel();
        
        $this->assertStringContainsString('delivery.orders', $channel);
        $this->assertStringContainsString('{orderId}', $channel);
    }
}