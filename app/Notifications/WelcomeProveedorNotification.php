<?php

namespace App\Notifications;

use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeProveedorNotification extends Notification implements ShouldQueue
{
    use HasNotificationPreferences, Queueable, SendsViaMailTemplate;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $email,
    ) {}

    public function failed(\Throwable $e): void
    {
        \Log::error('Notification failed: '.static::class.': '.$e->getMessage(), [
            'notification_class' => static::class,
            'exception' => $e,
        ]);
    }

    public function preferenceKey(): string
    {
        return 'welcome_proveedor';
    }

    public function templateSlug(): string
    {
        return 'welcome_proveedor';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'email' => $this->email,
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
            ->subject('Acceso al Portal de Proveedores - '.config('app.name'))
            ->greeting('¡Hola '.$notifiable->name.'!')
            ->line('Se ha creado tu acceso al Portal de Proveedores de '.config('app.name').'.')
            ->line('**Email: '.$this->email.'**')
            ->line('')
            ->action('Restablecer tu contraseña', url('/forgot-password'))
            ->line('Para establecer tu contraseña, haz clic en "Olvidé mi contraseña" en la página de inicio de sesión.')
            ->line('¡Gracias por trabajar con nosotros!');
    }
}
