<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class NotificationHelper extends ServiceProvider
{
    /**
     * Send a notification safely, catching and logging any exceptions.
     */
    public static function send(object $notifiable, mixed $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (\Throwable $e) {
            Log::error('Notification failed: '.$e->getMessage(), [
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
                'notification_class' => get_class($notification),
                'exception' => class_basename($e),
            ]);
        }
    }
}
