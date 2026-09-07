<?php
/**
 * Notification Resource Test
 */

namespace Tests\Unit\Resources\Notification;

use App\Http\Resources\Notification\NotificationResource;
use Tests\TestCase;

class NotificationResourceTest extends TestCase
{
    public function test_notification_resource_returns_notification_data(): void
    {
        $resource = new class extends NotificationResource {
            public function __construct()
            {
                $this->id = 1;
                $this->created_at = '2024-01-01T00:00:00Z';
                $this->updated_at = '2024-01-01T00:00:00Z';
            }
            
            protected function addMinimalFields(): void
            {
                $this->addCommonFields([
                    'id' => 1,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'updated_at' => '2024-01-01T00:00:00Z',
                    'data' => [
                        'id' => 1,
                        'tipo' => 'order_assigned',
                        'titulo' => 'Nueva orden asignada',
                        'contenido' => 'Se te ha asignado la orden #123',
                        'leido' => false,
                    ],
                ]);
            }
        };

        $data = $resource->getMinimalData();
        
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('order_assigned', $data['data']['tipo']);
        $this->assertEquals('Nueva orden asignada', $data['data']['titulo']);
        $this->assertFalse($data['data']['leido']);
    }

    public function test_notification_resource_marks_as_read(): void
    {
        $resource = new class extends NotificationResource {
            public function __construct()
            {
                $this->id = 2;
                $this->created_at = '2024-01-01T00:00:00Z';
                $this->updated_at = '2024-01-01T00:00:00Z';
            }
            
            protected function addMinimalFields(): void
            {
                $this->addCommonFields([
                    'id' => 2,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'updated_at' => '2024-01-01T00:00:00Z',
                    'data' => [
                        'id' => 2,
                        'tipo' => 'system',
                        'titulo' => 'Sistema',
                        'contenido' => 'Mensaje del sistema',
                        'leido' => true,
                    ],
                ]);
            }
        };

        $data = $resource->getMinimalData();
        
        $this->assertTrue($data['data']['leido']);
    }
}