<?php

namespace App\Notifications;

use App\Traits\HasNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NuevaReaccion extends Notification implements ShouldQueue
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
        public $reactable,
        public $type // 'like' or 'heart'
    ) {}

    public function preferenceKey(): string
    {
        return 'reaccion';
    }

    public function via(object $notifiable): array
    {
        return $this->filterChannelsByPreference($notifiable, ['database']);
    }

    public function toArray(object $notifiable): array
    {
        $modelName = class_basename($this->reactable);

        $publicacionId = $modelName === 'Publicacion' ? $this->reactable->id : $this->reactable->publicacion_id;

        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_avatar' => $this->user->profile_photo_url,
            'type' => $this->type,
            'tipo' => 'reaccion',
            'reactable_id' => $this->reactable->id,
            'reactable_type' => $modelName,
            'message' => "{$this->user->name} le dio ".($this->type === 'like' ? 'me gusta' : 'un corazón').' a tu '.($modelName === 'Publicacion' ? 'publicación' : 'comentario').'.',
            'link' => "/comunidad#publicacion-{$publicacionId}",
        ];
    }
}
