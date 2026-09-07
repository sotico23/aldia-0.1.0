<?php

namespace App\Notifications;

use App\Models\Inventario;
use App\Models\Producto;
use App\Traits\HasNotificationPreferences;
use App\Traits\SendsViaMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use HasNotificationPreferences, Queueable, SendsViaMailTemplate;

    public int $tries = 3;

    public int $backoff = 60;

    public Producto $producto;

    public Inventario $inventario;

    public int $stockActual;

    public int $stockMinimo;

    public function __construct(Producto $producto, Inventario $inventario, int $stockActual, int $stockMinimo)
    {
        $this->producto = $producto;
        $this->inventario = $inventario;
        $this->stockActual = $stockActual;
        $this->stockMinimo = $stockMinimo;
    }

    public function preferenceKey(): string
    {
        return 'stock_bajo';
    }

    public function templateSlug(): string
    {
        return 'stock_bajo';
    }

    public function templateVariables(object $notifiable): array
    {
        return [
            'producto_nombre' => $this->producto->nombre,
            'stock_actual' => $this->stockActual,
            'stock_minimo' => $this->stockMinimo,
            'almacen' => $this->inventario->almacen?->nombre ?? 'N/A',
            'link' => url('/inventario/'.$this->inventario->id),
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
            ->subject('Alerta: Stock bajo - '.$this->producto->nombre)
            ->greeting('Hola!')
            ->line('El producto "'.$this->producto->nombre.'" ha alcanzado un nivel de stock bajo.')
            ->line('Stock actual: '.$this->stockActual)
            ->line('Stock mínimo configurado: '.$this->stockMinimo)
            ->line('Almacén: '.($this->inventario->almacen?->nombre ?? 'N/A'))
            ->action('Ver inventario', url('/inventario/'.$this->inventario->id))
            ->line('Por favor, considera reabastecer este producto.');
    }

    public function toArray($notifiable): array
    {
        return [
            'titulo' => 'Stock bajo: '.$this->producto->nombre,
            'message' => 'Stock actual: '.$this->stockActual.' (mínimo: '.$this->stockMinimo.')',
            'producto_id' => $this->producto->id,
            'inventario_id' => $this->inventario->id,
            'tipo' => 'stock_bajo',
            'link' => url('/inventario/'.$this->inventario->id),
        ];
    }
}
