<?php

namespace App\Notifications;

use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TempPasswordNotification extends Notification implements ShouldQueue
{
    use HasNotificationPreferences, Queueable, SendsViaMailTemplate;

    public function __construct(
        public string $provider
    ) {}

    public function preferenceKey(): string
    {
        return 'temp_password';
    }

    public function templateSlug(): string
    {
        return 'temp_password';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'provider' => $this->provider,
            'name' => $notifiable->name,
            'link' => url('/forgot-password'),
        ];
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannelsByPreference($notifiable, ['mail']);
    }

    public function toMail($notifiable): MailMessage
    {
        $template = $this->sendViaTemplate($notifiable, $notifiable->getOwnerId());
        if ($template) {
            return $template;
        }

        return (new MailMessage)
            ->subject('Tu cuenta ha sido creada - '.config('app.name'))
            ->greeting('¡Bienvenido a '.config('app.name').'!')
            ->line('Te has registrado exitosamente usando '.$this->provider.'.')
            ->line('')
            ->action('Restablecer tu contraseña', url('/forgot-password'))
            ->line('Para establecer tu contraseña, usa la opción "Olvidé mi contraseña" en la página de inicio de sesión.')
            ->line('¡Gracias por usar nuestra aplicación!');
    }
}
