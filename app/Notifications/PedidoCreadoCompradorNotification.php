<?php

namespace App\Notifications;

use App\Models\Pedido;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PedidoCreadoCompradorNotification extends Notification implements ShouldQueue
{
    use HasNotificationPreferences, Queueable, SendsViaMailTemplate;

    public int $tries = 5;

    public int $backoff = 120;

    public function failed(\Throwable $e): void
    {
        \Log::error('Notification failed: '.static::class.': '.$e->getMessage(), [
            'notification_class' => static::class,
            'exception' => $e,
        ]);
    }

    public Pedido $pedido;

    public function __construct(Pedido $pedido)
    {
        $this->pedido = $pedido;
    }

    public function preferenceKey(): string
    {
        return 'pedido_creado';
    }

    public function templateSlug(): string
    {
        return 'pedido_creado';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'numero_pedido' => $this->pedido->numero_pedido,
            'nombre_cliente' => $this->pedido->nombre_cliente,
            'estado' => $this->pedido->estado,
            'link' => url('/pedidos/'.$this->pedido->id),
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

        return (new MailMessage)
            ->subject('Tu pedido #'.$this->pedido->numero_pedido)
            ->greeting('¡Hola '.$this->pedido->nombre_cliente.'!')
            ->line('Tu pedido ha sido creado con éxito.')
            ->line('Estado actual: '.$this->pedido->estado)
            ->action('Ver detalle del pedido', url('/pedidos/'.$this->pedido->id))
            ->line('Gracias por comprar con nosotros.');
    }

    public function toArray($notifiable): array
    {
        return [
            'titulo' => 'Pedido Creado #'.$this->pedido->numero_pedido,
            'message' => 'Tu pedido ha sido registrado y está '.$this->pedido->estado.'.',
            'pedido_id' => $this->pedido->id,
            'tipo' => 'actualizacion_pedido',
            'link' => url('/pedidos/'.$this->pedido->id),
        ];
    }
}
