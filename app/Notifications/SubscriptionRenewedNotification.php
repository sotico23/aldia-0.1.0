<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewedNotification extends Notification
{
    use HasNotificationPreferences, Queueable, SendsViaMailTemplate;

    public int $tries = 3;

    public int $backoff = 60;

    public Subscription $subscription;

    public string $previousEndsAt;

    public string $newEndsAt;

    public function __construct(Subscription $subscription, string $previousEndsAt, string $newEndsAt)
    {
        $this->subscription = $subscription;
        $this->previousEndsAt = $previousEndsAt;
        $this->newEndsAt = $newEndsAt;
    }

    public function preferenceKey(): string
    {
        return 'suscripcion_renovada';
    }

    public function templateSlug(): string
    {
        return 'suscripcion_renovada';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'plan_name' => $this->subscription->plan?->nombre ?? 'Plan',
            'previous_ends_at' => $this->previousEndsAt,
            'new_ends_at' => $this->newEndsAt,
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
            ->subject('Tu suscripción ha sido renovada')
            ->greeting('Hola!')
            ->line('Tu suscripción al plan '.$this->subscription->plan?->nombre.' ha sido renovada exitosamente.')
            ->line('Nueva fecha de vencimiento: '.$this->newEndsAt)
            ->action('Ver suscripción', url('/suscripcion'))
            ->line('Gracias por continuar con nosotros!');
    }

    public function toArray($notifiable): array
    {
        return [
            'titulo' => 'Suscripción renovada',
            'message' => 'Tu suscripción ha sido renovada hasta el '.$this->newEndsAt,
            'subscription_id' => $this->subscription->id,
            'tipo' => 'suscripcion_renovada',
            'link' => url('/suscripcion'),
        ];
    }
}
