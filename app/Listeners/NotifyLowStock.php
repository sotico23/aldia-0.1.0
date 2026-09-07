<?php

namespace App\Listeners;

use App\Events\LowStock;
use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyLowStock implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 60;

    public function failed(\Throwable $e): void
    {
        \Log::error('LowStock notification failed: '.$e->getMessage(), [
            'event' => LowStock::class,
            'exception' => $e,
        ]);
    }

    public function handle(LowStock $event): void
    {
        // Notify the business owner
        $owner = User::find($event->producto->owner_id);

        if ($owner) {
            NotificationHelper::send($owner, new LowStockNotification(
                $event->producto,
                $event->inventario,
                $event->stockActual,
                $event->stockMinimo
            ));
        }
    }
}
