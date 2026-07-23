<?php

namespace App\Notifications;

use App\Models\Pedido;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActualizacionEstadoPedidoNotification extends Notification implements ShouldQueue
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

    public string $estadoAnterior;

    public string $estadoNuevo;

    public ?string $mensajeAdicional;

    public function __construct(Pedido $pedido, string $estadoAnterior, string $estadoNuevo, ?string $mensajeAdicional = null)
    {
        $this->pedido = $pedido;
        $this->estadoAnterior = $estadoAnterior;
        $this->estadoNuevo = $estadoNuevo;
        $this->mensajeAdicional = $mensajeAdicional;
    }

    public function preferenceKey(): string
    {
        return 'actualizacion_pedido';
    }

    public function templateSlug(): string
    {
        return 'actualizacion_pedido';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'numero_pedido' => $this->pedido->numero_pedido,
            'estado_anterior' => $this->estadoAnterior,
            'estado_nuevo' => $this->estadoNuevo,
            'mensaje' => $this->mensajeAdicional,
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

        $estados = [
            'pendiente' => 'Pendiente',
            'confirmado' => 'Confirmado',
            'preparando' => 'En preparación',
            'enviado' => 'Enviado',
            'entregado' => 'Entregado',
            'cancelado' => 'Cancelado',
        ];

        $mensaje = 'Tu pedido #'.$this->pedido->numero_pedido.' ha cambiado de estado.';

        return (new MailMessage)
            ->subject('Actualización de Pedido #'.$this->pedido->numero_pedido)
            ->greeting('Hola!')
            ->line($mensaje)
            ->line('Estado anterior: '.($estados[$this->estadoAnterior] ?? $this->estadoAnterior))
            ->line('Estado actual: '.($estados[$this->estadoNuevo] ?? $this->estadoNuevo))
            ->when($this->mensajeAdicional, fn ($mail) => $mail->line($this->mensajeAdicional))
            ->action('Ver pedido', url('/pedidos/'.$this->pedido->id))
            ->line('Gracias por tu compra!');
    }

    public function toArray($notifiable): array
    {
        $estados = [
            'pendiente' => 'Pendiente',
            'confirmado' => 'Confirmado',
            'preparando' => 'En preparación',
            'enviado' => 'Enviado',
            'entregado' => 'Entregado',
            'cancelado' => 'Cancelado',
        ];

        return [
            'titulo' => 'Pedido #'.$this->pedido->numero_pedido.' - Estado actualizado',
            'message' => 'Tu pedido ahora está: '.($estados[$this->estadoNuevo] ?? $this->estadoNuevo),
            'pedido_id' => $this->pedido->id,
            'estado' => $this->estadoNuevo,
            'tipo' => 'actualizacion_pedido',
            'link' => url('/pedidos/'.$this->pedido->id),
        ];
    }
}
