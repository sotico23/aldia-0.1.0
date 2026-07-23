<?php

namespace App\Notifications;

use App\Models\WebSetting;
use App\Traits\HasNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrialExpiryNotification extends Notification implements ShouldQueue
{
    use Dispatchable, HasNotificationPreferences, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 120;

    public function failed(\Throwable $e): void
    {
        \Log::error('Notification failed: '.static::class.': '.$e->getMessage(), [
            'notification_class' => static::class,
            'exception' => $e,
        ]);
    }

    public function __construct(
        public int $daysRemaining,
    ) {}

    public function preferenceKey(): string
    {
        return 'trial_expiry';
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannelsByPreference($notifiable, ['database', 'mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planUrl = route('planes.index');
        $trialDays = (int) (WebSetting::getSettings()->trial_days ?? 15);

        if ($this->daysRemaining <= 0) {
            return (new MailMessage)
                ->subject('Tu período de prueba ha finalizado')
                ->greeting('Hola '.$notifiable->name)
                ->line("Tu período de prueba de {$trialDays} días ha finalizado.")
                ->line('Ya no podrás crear, editar ni eliminar registros, pero podrás seguir consultando tu información.')
                ->line('Para seguir usando todas las funciones, elige un plan de suscripción.')
                ->action('Ver planes disponibles', $planUrl)
                ->line('Si tienes preguntas, contacta a nuestro equipo de soporte.')
                ->salutation('Saludos, el equipo de '.config('app.name'));
        }

        return (new MailMessage)
            ->subject("Tu prueba termina en {$this->daysRemaining} días")
            ->greeting('Hola '.$notifiable->name)
            ->line("Tu período de prueba termina en {$this->daysRemaining} días.")
            ->line('Te recomendamos elegir un plan antes de que finalice para no perder acceso a las funciones.')
            ->action('Ver planes', $planUrl)
            ->salutation('Saludos, el equipo de '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        $trialDays = (int) (WebSetting::getSettings()->trial_days ?? 15);

        if ($this->daysRemaining <= 0) {
            return [
                'titulo' => 'Período de prueba finalizado',
                'message' => 'Tu período de prueba ha finalizado. Actualiza tu plan para seguir editando.',
                'tipo' => 'trial_expiry',
                'link' => route('planes.index'),
                'days_remaining' => $this->daysRemaining,
                'trial_days' => $trialDays,
            ];
        }

        return [
            'titulo' => "Tu prueba termina en {$this->daysRemaining} días",
            'message' => "Tu período de prueba termina en {$this->daysRemaining} días. Elige un plan para continuar.",
            'tipo' => 'trial_warning',
            'link' => route('planes.index'),
            'days_remaining' => $this->daysRemaining,
            'trial_days' => $trialDays,
        ];
    }
}
