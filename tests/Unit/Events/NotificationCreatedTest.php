<?php
/**
 * NotificationCreated Event Test
 */

namespace Tests\Unit\Events;

use App\Events\NotificationCreated;
use Tests\TestCase;

class NotificationCreatedTest extends TestCase
{
    public function test_notification_created_event_has_correct_structure(): void
    {
        $event = new class extends NotificationCreated {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $data = $event->getEventData();
        
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('read_at', $data);
        $this->assertArrayHasKey('created_at', $data);
        
        $this->assertEquals('notification.created', $data['type']);
    }

    public function test_notification_created_event_channel_format(): void
    {
        $event = new class extends NotificationCreated {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $channel = $event->getChannel();
        
        $this->assertStringContainsString('notifications', $channel);
        $this->assertStringContainsString('{userId}', $channel);
    }
}