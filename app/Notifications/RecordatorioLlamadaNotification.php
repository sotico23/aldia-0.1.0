<?php

namespace App\Notifications;

use App\Models\ProgramacionCallCenter;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecordatorioLlamadaNotification extends Notification implements ShouldQueue
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

    public ProgramacionCallCenter $programacion;

    public function __construct(ProgramacionCallCenter $programacion)
    {
        $this->programacion = $programacion;
    }

    public function preferenceKey(): string
    {
        return 'recordatorio_llamada';
    }

    public function templateSlug(): string
    {
        return 'recordatorio_llamada';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'titulo' => $this->programacion->titulo,
            'fecha' => $this->programacion->fecha_programada->format('d/m/Y H:i'),
            'telefono' => $this->programacion->numero_telefono ?? 'Sin número',
            'descripcion' => $this->programacion->descripcion ?? 'Sin descripción',
            'link' => url('/call-center'),
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
            ->subject('Recordatorio: '.$this->programacion->titulo)
            ->greeting('Recordatorio de llamada programada')
            ->line('Tienes una llamada programada:')
            ->line('Título: '.$this->programacion->titulo)
            ->line('Fecha: '.$this->programacion->fecha_programada->format('d/m/Y H:i'))
            ->line('Teléfono: '.($this->programacion->numero_telefono ?? 'Sin número'))
            ->line('Descripción: '.($this->programacion->descripcion ?? 'Sin descripción'))
            ->action('Ir al Call Center', url('/call-center'))
            ->line('No olvides realizar la llamada a tiempo.');
    }

    public function toArray($notifiable): array
    {
        return [
            'titulo' => 'Recordatorio: '.$this->programacion->titulo,
            'message' => 'Llamada programada para '.$this->programacion->fecha_programada->format('d/m/Y H:i'),
            'programacion_id' => $this->programacion->id,
            'tipo' => 'recordatorio_llamada',
            'link' => url('/call-center'),
        ];
    }
}
