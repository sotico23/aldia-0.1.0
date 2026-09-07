<?php
/**
 * MensajeEnviado Event Test
 */

namespace Tests\Unit\Events;

use App\Events\MensajeEnviado;
use Tests\TestCase;

class MensajeEnviadoTest extends TestCase
{
    public function test_mensaje_enviado_event_has_correct_structure(): void
    {
        $event = new class extends MensajeEnviado {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $data = $event->getEventData();
        
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('conversacion_id', $data);
        $this->assertArrayHasKey('leader_id', $data);
        $this->assertArrayHasKey('mensaje_id', $data);
        $this->assertArrayHasKey('texto', $data);
        $this->assertArrayHasKey('tipo_mensaje', $data);
        $this->assertArrayHasKey('leido_por_usuario', $data);
        $this->assertArrayHasKey('created_at', $data);
        
        $this->assertEquals('chat.message.sent', $data['type']);
    }

    public function test_mensaje_enviado_event_channel_format(): void
    {
        $event = new class extends MensajeEnviado {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $channel = $event->getChannel();
        
        $this->assertStringContainsString('chat.conversation', $channel);
        $this->assertStringContainsString('{conversationId}', $channel);
    }
}