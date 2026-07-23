<?php

namespace App\Notifications;

use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewCommunicationMessage extends Notification implements ShouldQueue
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

    public string $type;

    public mixed $conversation;

    public mixed $message;

    public function __construct(string $type, mixed $conversation, mixed $message)
    {
        $this->type = $type;
        $this->conversation = $conversation;
        $this->message = $message;
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
        $sender = $this->resolveSender();

        return [
            'sender_name' => $sender?->name ?? 'Alguien',
            'numero_pedido' => $this->resolveOrderNumber(),
            'mensaje' => $this->resolveContent(),
            'link' => $this->resolveLink(),
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

        $sender = $this->resolveSender();
        $content = $this->resolveContent();
        $orderNumber = $this->resolveOrderNumber();
        $subject = match ($this->type) {
            'order' => $this->isReceiverSeller($notifiable)
                ? 'Nuevo mensaje de tu cliente'
                : 'Nuevo mensaje del vendedor',
            'marketplace' => 'Nuevo mensaje en tu tienda',
            'internal' => 'Nuevo mensaje interno',
            default => 'Nuevo mensaje',
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hola!')
            ->line(($sender?->name ?? 'Alguien').' te ha enviado un mensaje'
                .($orderNumber ? " sobre el pedido #{$orderNumber}" : ''))
            ->line('"'.(strlen($content) > 100 ? substr($content, 0, 100).'...' : $content).'"')
            ->action('Ver conversación', $this->resolveLink())
            ->line('Gracias por usar nuestra plataforma!');
    }

    public function toArray($notifiable): array
    {
        $sender = $this->resolveSender();
        $content = $this->resolveContent();
        $orderNumber = $this->resolveOrderNumber();

        $title = match ($this->type) {
            'order' => $this->isReceiverSeller($notifiable)
                ? 'Nuevo mensaje de cliente'
                : 'Nuevo mensaje del vendedor',
            'marketplace' => 'Nuevo mensaje en tu tienda',
            'internal' => 'Nuevo mensaje interno',
            default => 'Nuevo mensaje',
        };

        return [
            'titulo' => $title,
            'message' => ($sender?->name ?? 'Alguien').': '
                .(strlen($content) > 100 ? substr($content, 0, 100).'...' : $content),
            'tipo' => "mensaje_{$this->type}",
            'link' => $this->resolveLink(),
            'pedido_id' => $this->resolvePedidoId(),
        ];
    }

    private function resolveSender(): mixed
    {
        return match ($this->type) {
            'order' => $this->message->sender,
            'marketplace' => $this->message->sender,
            'internal' => $this->message->sender,
            default => null,
        };
    }

    private function resolveContent(): string
    {
        return match ($this->type) {
            'order' => $this->message->contenido ?? '',
            'marketplace' => $this->message->body ?? '',
            'internal' => $this->message->contenido ?? '',
            default => '',
        };
    }

    private function resolveOrderNumber(): ?string
    {
        return match ($this->type) {
            'order' => $this->conversation->pedido?->numero_pedido,
            default => null,
        };
    }

    private function resolveLink(): string
    {
        return match ($this->type) {
            'order' => url('/conversaciones-pedidos/'.$this->conversation->id.'/chat'),
            'marketplace' => url('/chat/'.$this->conversation->id),
            'internal' => url('/mensajes'),
            default => url('/'),
        };
    }

    private function resolvePedidoId(): ?int
    {
        return match ($this->type) {
            'order' => $this->conversation->pedido?->id,
            default => null,
        };
    }

    private function isReceiverSeller(object $notifiable): bool
    {
        return match ($this->type) {
            'order' => $notifiable->id === $this->conversation->vendedor_id,
            default => false,
        };
    }
}
