<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use HasNotificationPreferences, Queueable, SendsViaMailTemplate;

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
        public Transaction $transaction
    ) {}

    public function preferenceKey(): string
    {
        return 'pago_recibido';
    }

    public function templateSlug(): string
    {
        return 'pago_recibido';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'monto' => '$'.number_format((float) $this->transaction->amount, 0, ',', '.'),
            'buy_order' => $this->transaction->metadata['buy_order'] ?? $this->transaction->gateway_transaction_id,
            'link' => url('/pagos'),
        ];
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannelsByPreference($notifiable, ['database', 'mail']);
    }

    public function toMail($notifiable): MailMessage
    {
        $template = $this->sendViaTemplate($notifiable, $notifiable->getOwnerId());
        if ($template) {
            return $template;
        }

        $buyOrder = $this->transaction->metadata['buy_order'] ?? $this->transaction->gateway_transaction_id;

        return (new MailMessage)
            ->subject('Pago recibido - '.config('app.name'))
            ->greeting('¡Pago confirmado!')
            ->line('Se ha recibido un pago por $'.number_format((float) $this->transaction->amount, 0, ',', '.'))
            ->line('Orden de compra: '.$buyOrder)
            ->action('Ver detalle', url('/pagos'))
            ->line('Gracias por usar nuestros servicios.');
    }

    public function toArray(object $notifiable): array
    {
        $buyOrder = $this->transaction->metadata['buy_order'] ?? $this->transaction->gateway_transaction_id;

        return [
            'titulo' => 'Pago recibido',
            'message' => 'Pago por $'.number_format((float) $this->transaction->amount, 0, ',', '.').' confirmado ('.$buyOrder.').',
            'monto' => $this->transaction->amount,
            'buy_order' => $buyOrder,
            'tipo' => 'pago_recibido',
            'link' => url('/pagos'),
        ];
    }
}
