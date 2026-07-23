<?php

namespace App\Notifications;

use App\Models\Pedido;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevoPedidoNotification extends Notification implements ShouldQueue
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
        return 'nuevo_pedido';
    }

    public function templateSlug(): string
    {
        return 'nuevo_pedido';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'numero_pedido' => $this->pedido->numero_pedido,
            'nombre_cliente' => $this->pedido->nombre_cliente,
            'total' => '$'.number_format($this->pedido->total, 0, ',', '.'),
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
            ->subject('Nuevo Pedido #'.$this->pedido->numero_pedido)
            ->greeting('¡Nueva compra!')
            ->line('Has recibido un nuevo pedido.')
            ->line('Pedido #'.$this->pedido->numero_pedido)
            ->line('Cliente: '.$this->pedido->nombre_cliente)
            ->line('Total: $'.number_format($this->pedido->total, 0, ',', '.'))
            ->action('Ver pedido', url('/pedidos/'.$this->pedido->id))
            ->line('Gracias por usar nuestra plataforma!');
    }

    public function toArray($notifiable): array
    {
        return [
            'titulo' => 'Nuevo Pedido #'.$this->pedido->numero_pedido,
            'message' => 'Tienes una nueva compra de '.$this->pedido->nombre_cliente.' por $'.number_format($this->pedido->total, 0, ',', '.'),
            'pedido_id' => $this->pedido->id,
            'tipo' => 'nuevo_pedido',
            'link' => url('/pedidos/'.$this->pedido->id),
        ];
    }
}
