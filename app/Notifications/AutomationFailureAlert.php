<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class AutomationFailureAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $workflow,
        public int $consecutiveFailures,
        public string $lastUuid,
        public ?string $lastError = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['log'];

        if (config('services.slack.notifications.bot_user_oauth_token')) {
            $channels[] = 'slack';
        }

        if (config('mail.mailer') !== 'log') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[ALERTA] Automatización fallida: {$this->workflow}")
            ->error()
            ->line("El workflow **{$this->workflow}** ha fallado {$this->consecutiveFailures} veces consecutivas.")
            ->line("Último error: {$this->lastError}")
            ->line("UUID de ejecución: {$this->lastUuid}")
            ->action('Ver Historial', url('/automatizaciones/historial'));
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->error()
            ->content('🚨 *Fallo crítico en automatización*')
            ->attachment(function ($attachment) {
                $attachment
                    ->title("Workflow: {$this->workflow}")
                    ->fields([
                        'Fallos consecutivos' => (string) $this->consecutiveFailures,
                        'Último error' => $this->lastError ?? 'N/A',
                        'UUID' => $this->lastUuid,
                    ])
                    ->timestamp(now());
            });
    }

    public function toLog(object $notifiable): void
    {
        logger()->critical('Automation consecutive failure alert', [
            'workflow' => $this->workflow,
            'consecutive_failures' => $this->consecutiveFailures,
            'last_uuid' => $this->lastUuid,
            'last_error' => $this->lastError,
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'workflow' => $this->workflow,
            'consecutive_failures' => $this->consecutiveFailures,
            'last_uuid' => $this->lastUuid,
            'last_error' => $this->lastError,
        ];
    }
}
