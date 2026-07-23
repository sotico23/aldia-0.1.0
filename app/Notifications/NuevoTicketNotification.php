<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevoTicketNotification extends Notification implements ShouldQueue
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

    public Ticket $ticket;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function preferenceKey(): string
    {
        return 'nuevo_ticket';
    }

    public function templateSlug(): string
    {
        return 'nuevo_ticket';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'titulo' => $this->ticket->titulo,
            'prioridad' => strtoupper($this->ticket->prioridad),
            'asignado_a' => $this->ticket->asignado_a ?? 'Sin asignar',
            'link' => url('/tickets/'.$this->ticket->id),
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
            ->subject('Nuevo Ticket: '.$this->ticket->titulo)
            ->greeting('Nuevo ticket de soporte')
            ->line('Se ha creado un nuevo ticket.')
            ->line('Título: '.$this->ticket->titulo)
            ->line('Prioridad: '.strtoupper($this->ticket->prioridad))
            ->line('Asignado a: '.($this->ticket->asignado_a ?? 'Sin asignar'))
            ->action('Ver ticket', url('/tickets/'.$this->ticket->id))
            ->line('Revisa los detalles y toma acción.');
    }

    public function toArray($notifiable): array
    {
        return [
            'titulo' => 'Nuevo Ticket: '.$this->ticket->titulo,
            'message' => 'Ticket '.$this->ticket->prioridad.' asignado a '.($this->ticket->asignado_a ?? 'sin asignar'),
            'ticket_id' => $this->ticket->id,
            'tipo' => 'nuevo_ticket',
            'link' => url('/tickets/'.$this->ticket->id),
        ];
    }
}
