<?php

namespace App\Notifications;

use App\Traits\HasNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StockLowNotification extends Notification implements ShouldQueue
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
        public array $productos,
    ) {}

    public function preferenceKey(): string
    {
        return 'stock_low';
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannelsByPreference($notifiable, ['database', 'mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->productos);

        $lines = collect($this->productos)
            ->map(fn ($p) => "- {$p['nombre']}: {$p['cantidad_actual']} / {$p['cantidad_minima']} mín.")
            ->take(10)
            ->toArray();

        $mail = (new MailMessage)
            ->subject("[Stock Bajo] {$count} producto(s) por debajo del mínimo")
            ->greeting('Productos con stock bajo')
            ->line('Los siguientes productos tienen stock por debajo de su mínimo:')
            ->line(implode("\n", $lines));

        if ($count > 10) {
            $mail->line('*y '.($count - 10).' más...*');
        }

        $mail->action('Ver Inventario', url('/inventario?stock_bajo=1'));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Stock bajo',
            'message' => count($this->productos).' producto(s) tienen stock por debajo del mínimo.',
            'productos' => $this->productos,
        ];
    }
}
