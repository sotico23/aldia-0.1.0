<?php

namespace App\Notifications;

use App\Models\Mensaje;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevoMensajeInternoNotification extends Notification implements ShouldQueue
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

    public Mensaje $mensaje;

    public function __construct(Mensaje $mensaje)
    {
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
            'mensaje' => $this->mensaje->contenido,
            'link' => url('/mensajes'),
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

        return (new MailMessage)
            ->subject('Nuevo mensaje de '.$sender->name)
            ->greeting('Hola!')
            ->line($sender->name.' te ha enviado un mensaje:')
            ->line('"'.(strlen($this->mensaje->contenido) > 100 ? substr($this->mensaje->contenido, 0, 100).'...' : $this->mensaje->contenido).'"')
            ->action('Ver mensajes', url('/mensajes'))
            ->line('Gracias por usar nuestra plataforma!');
    }

    public function toArray($notifiable): array
    {
        $sender = $this->mensaje->sender;

        return [
            'user_id' => $sender->id,
            'user_name' => $sender->name,
            'user_avatar' => $sender->profile_photo_path,
            'titulo' => 'Nuevo mensaje de '.$sender->name,
            'message' => $sender->name.': '.(strlen($this->mensaje->contenido) > 100 ? substr($this->mensaje->contenido, 0, 100).'...' : $this->mensaje->contenido),
            'tipo' => 'mensaje_chat',
            'link' => url('/mensajes'),
        ];
    }
}
