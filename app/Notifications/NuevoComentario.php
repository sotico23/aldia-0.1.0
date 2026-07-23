<?php

namespace App\Notifications;

use App\Traits\HasNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NuevoComentario extends Notification implements ShouldQueue
{
    use HasNotificationPreferences, Queueable;

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
        public $user,
        public $comentario,
        public $isReply = false
    ) {}

    public function preferenceKey(): string
    {
        return 'comentario';
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannelsByPreference($notifiable, ['database']);
    }

    public function toArray(object $notifiable): array
    {
        $message = "{$this->user->name} ".($this->isReply ? 'respondió a tu comentario.' : 'comentó tu publicación.');

        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_avatar' => $this->user->profile_photo_url,
            'comentario_id' => $this->comentario->id,
            'publicacion_id' => $this->comentario->publicacion_id,
            'tipo' => 'comentario',
            'message' => $message,
            'link' => "/comunidad#publicacion-{$this->comentario->publicacion_id}",
        ];
    }
}
