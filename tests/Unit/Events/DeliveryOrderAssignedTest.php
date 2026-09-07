<?php
/**
 * DeliveryOrderAssigned Event Test
 */

namespace Tests\Unit\Events;

use App\Events\DeliveryOrderAssigned;
use Tests\TestCase;

class DeliveryOrderAssignedTest extends TestCase
{
    public function test_delivery_order_assigned_event_has_correct_structure(): void
    {
        $event = new class extends DeliveryOrderAssigned {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $data = $event->getEventData();
        
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('order_id', $data);
        $this->assertArrayHasKey('driver_id', $data);
        $this->assertArrayHasKey('assignment_type', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('created_at', $data);
        
        $this->assertEquals('delivery.order.assigned', $data['type']);
    }

    public function test_delivery_order_assigned_event_channel_format(): void
    {
        $event = new class extends DeliveryOrderAssigned {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $channel = $event->getChannel();
        
        $this->assertStringContainsString('delivery.driver', $channel);
        $this->assertStringContainsString('{driverId}', $channel);
    }
}