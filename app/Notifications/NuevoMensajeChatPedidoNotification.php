<?php

namespace App\Notifications;

use App\Models\Conversacion;
use App\Models\MensajeConversacion;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevoMensajeChatPedidoNotification extends Notification implements ShouldQueue
{
    use HasNotificationPreferences, Queueable, SendsViaMailTemplate;

    public int $tries = 3;

    public int $backoff = 60;

    public function failed(\Throwable $e): void
    {
        \Log::error('Notification failed: '.static::class.': '.$e->getMessage(), [
            'notification_class' => static::class,
            'exception' => $e,
        ]);
    }

    public Conversacion $conversacion;

    public MensajeConversacion $mensaje;

    public function __construct(Conversacion $conversacion, MensajeConversacion $mensaje)
    {
        $this->conversacion = $conversacion;
        $this->mensaje = $mensaje;
    }

    public function preferenceKey(): string
    {
        return 'mensaje_chat';
    }

    public function templateSlug(): string
    {
        return 'mensaje_chat';
    }

    public function templateVariables(object $notifiable): array
    {
        $sender = $this->mensaje->sender;

        return [
            'sender_name' => $sender->name,
            'numero_pedido' => $this->conversacion->pedido->numero_pedido,
            'mensaje' => $this->mensaje->contenido,
            'link' => url('/conversaciones-pedidos/'.$this->conversacion->id.'/chat'),
        ];
    }

    public function via($notifiable): array
    {
        return $this->filterChannelsByPreference($notifiable, ['database', 'mail']);
    }

    public function toMail($notifiable): MailMessage
    {
        $template = $this->sendViaTemplate($notifiable, $notifiable->getOwnerId());
        if ($template) {
            return $template;
        }

        $sender = $this->mensaje->sender;
        $esVendedor = $notifiable->id === $this->conversacion->vendedor_id;

        return (new MailMessage)
            ->subject($esVendedor ? 'Nuevo mensaje de tu cliente' : 'Nuevo mensaje del vendedor')
            ->greeting('Hola!')
            ->line($sender->name.' te ha enviado un mensaje sobre el pedido #'.$this->conversacion->pedido->numero_pedido)
            ->line('"'.(strlen($this->mensaje->contenido) > 100 ? substr($this->mensaje->contenido, 0, 100).'...' : $this->mensaje->contenido).'"')
            ->action('Ver conversación', url('/conversaciones-pedidos/'.$this->conversacion->id.'/chat'))
            ->line('Gracias por usar nuestra plataforma!');
    }

    public function toArray($notifiable): array
    {
        $sender = $this->mensaje->sender;
        $esVendedor = $notifiable->id === $this->conversacion->vendedor_id;

        return [
            'titulo' => $esVendedor ? 'Nuevo mensaje de cliente' : 'Nuevo mensaje del vendedor',
            'message' => $sender->name.': '.(strlen($this->mensaje->contenido) > 100 ? substr($this->mensaje->contenido, 0, 100).'...' : $this->mensaje->contenido),
            'conversacion_id' => $this->conversacion->id,
            'pedido_id' => $this->conversacion->pedido->id,
            'tipo' => 'mensaje_chat_pedido',
            'link' => url('/conversaciones-pedidos/'.$this->conversacion->id.'/chat'),
        ];
    }
}
