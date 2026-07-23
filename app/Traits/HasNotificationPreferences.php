<?php

namespace App\Traits;

trait HasNotificationPreferences
{
    abstract public function preferenceKey(): string;

    public function filterChannelsByPreference(object $notifiable, array $channels): array
    {
        return array_values(array_filter($channels, function ($channel) use ($notifiable) {
            return $notifiable->wantsNotificationChannel($this->preferenceKey(), $channel);
        }));
    }
}
