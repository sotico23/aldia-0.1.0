<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification
{
    use HasNotificationPreferences, Queueable, SendsViaMailTemplate;

    public int $tries = 3;

    public int $backoff = 60;

    public Subscription $subscription;

    public string $expiredAt;

    public function __construct(Subscription $subscription, string $expiredAt)
    {
        $this->subscription = $subscription;
        $this->expiredAt = $expiredAt;
    }

    public function preferenceKey(): string
    {
        return 'suscripcion_expirada';
    }

    public function templateSlug(): string
    {
        return 'suscripcion_expirada';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'plan_name' => $this->subscription->plan?->nombre ?? 'Plan',
            'expired_at' => $this->expiredAt,
            'link' => url('/suscripcion'),
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
            ->subject('Tu suscripción ha expirado')
            ->greeting('Hola!')
            ->line('Tu suscripción al plan '.$this->subscription->plan?->nombre.' ha expirado el '.$this->expiredAt.'.')
            ->line('Para continuar disfrutando de los beneficios, por favor renueva tu suscripción.')
            ->action('Renovar suscripción', url('/suscripcion'))
            ->line('Gracias por tu preferencia!');
    }

    public function toArray($notifiable): array
    {
        return [
            'titulo' => 'Suscripción expirada',
            'message' => 'Tu suscripción ha expirado el '.$this->expiredAt,
            'subscription_id' => $this->subscription->id,
            'tipo' => 'suscripcion_expirada',
            'link' => url('/suscripcion'),
        ];
    }
}
