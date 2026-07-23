<?php

namespace App\Notifications;

use App\Models\Cupon;
use App\Traits\HasNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CuponLimiteNotificacion extends Notification implements ShouldQueue
{
    use Dispatchable, HasNotificationPreferences, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function failed(\Throwable $e): void
    {
        \Log::error('Notification failed: '.static::class.': '.$e->getMessage(), [
            'notification_class' => static::class,
            'exception' => $e,
        ]);
    }

    public function __construct(
        public Cupon $cupon,
    ) {}

    public function preferenceKey(): string
    {
        return 'cupon_limite';
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannelsByPreference($notifiable, ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $porcentaje = $this->cupon->max_usos > 0
            ? round(($this->cupon->usos_actuales / $this->cupon->max_usos) * 100)
            : 0;

        return (new MailMessage)
            ->subject("Alerta: Cupón {$this->cupon->codigo} cerca del límite")
            ->line("El cupón **{$this->cupon->codigo}** ha alcanzado el {$porcentaje}% de su límite de usos.")
            ->line("Usos actuales: {$this->cupon->usos_actuales} / {$this->cupon->max_usos}")
            ->action('Ver Cupón', url('/backend/cupones/'.$this->cupon->id))
            ->line('Considere crear un nuevo cupón o ajustar el límite.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'cupon_id' => $this->cupon->id,
            'codigo' => $this->cupon->codigo,
            'usos_actuales' => $this->cupon->usos_actuales,
            'max_usos' => $this->cupon->max_usos,
            'mensaje' => "Cupón {$this->cupon->codigo} ha alcanzado el ".round(($this->cupon->usos_actuales / $this->cupon->max_usos) * 100).'% de su límite.',
        ];
    }
}
