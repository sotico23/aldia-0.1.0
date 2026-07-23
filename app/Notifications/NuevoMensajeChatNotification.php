<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevoMensajeChatNotification extends Notification implements ShouldQueue
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

    public Conversation $conversation;

    public Message $message;

    public function __construct(Conversation $conversation, Message $message)
    {
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
        $sender = $this->message->sender;

        return [
            'sender_name' => $sender->name,
            'mensaje' => $this->message->body,
            'link' => url('/chat/'.$this->conversation->id),
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

        $sender = $this->message->sender;
        $store = $this->conversation->store;

        return (new MailMessage)
            ->subject('Nuevo mensaje en tu conversación')
            ->greeting('Hola!')
            ->line($sender->name.' te ha enviado un mensaje sobre '.($store->title ?? 'tu conversación'))
            ->line('"'.(strlen($this->message->body) > 100 ? substr($this->message->body, 0, 100).'...' : $this->message->body).'"')
            ->action('Ver conversación', url('/chat/'.$this->conversation->id))
            ->line('Gracias por usar nuestra plataforma!');
    }

    public function toArray($notifiable): array
    {
        $sender = $this->message->sender;
        $store = $this->conversation->store;
        $esVendedor = $notifiable->id === $store?->user_id;

        return [
            'titulo' => $esVendedor ? 'Nuevo mensaje de cliente' : 'Nuevo mensaje del vendedor',
            'message' => $sender->name.': '.(strlen($this->message->body) > 100 ? substr($this->message->body, 0, 100).'...' : $this->message->body),
            'conversation_id' => $this->conversation->id,
            'tipo' => 'mensaje_chat',
            'link' => url('/chat/'.$this->conversation->id),
        ];
    }
}
