<?php

/**
 * Presence Tracker - Tracks user presence in channels
 */

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PresenceTracker
{
    /**
     * Track user joining a channel
     */
    public function trackJoin(string $channel, string $userId, array $userData = []): void
    {
        $key = $this->channelKey($channel);
        $presence = Cache::get($key, []);

        $presence[$userId] = array_merge($userData, [
            'joined_at' => now()->toISOString(),
            'last_seen' => now()->toISOString(),
        ]);

        Cache::put($key, $presence, now()->addHours(24));

        $this->registerChannel($channel);
    }

    /**
     * Track user leaving a channel
     */
    public function trackLeave(string $channel, string $userId): void
    {
        $key = $this->channelKey($channel);
        $presence = Cache::get($key, []);

        unset($presence[$userId]);

        Cache::put($key, $presence, now()->addHours(24));
    }

    /**
     * Update user last seen
     */
    public function updateLastSeen(string $channel, string $userId): void
    {
        $key = $this->channelKey($channel);
        $presence = Cache::get($key, []);

        if (isset($presence[$userId])) {
            $presence[$userId]['last_seen'] = now()->toISOString();
            Cache::put($key, $presence, now()->addHours(24));
        }
    }

    /**
     * Get channel presence
     */
    public function getPresence(string $channel): array
    {
        $key = $this->channelKey($channel);
        $presence = Cache::get($key, []);

        // Filter out stale entries (older than 5 minutes)
        $now = now();

        return array_filter($presence, function ($user) use ($now) {
            $lastSeen = Carbon::parse($user['last_seen']);

            return $lastSeen->diffInMinutes($now) < 5;
        });
    }

    /**
     * Get user presence across all channels
     *
     * La presencia se agrega por categoria (el prefijo del canal: `chat`,
     * `notifications`, `delivery`, ...), de modo que un usuario conectado a
     * `chat.conversation.1` aparece bajo la clave `chat`.
     */
    public function getUserPresence(string $userId): array
    {
        $userPresence = [];

        foreach (Cache::get($this->channelsKey(), []) as $channel) {
            $presence = $this->getPresence($channel);

            if (! isset($presence[$userId])) {
                continue;
            }

            $category = explode('.', $channel)[0];

            $userPresence[$category] = $presence[$userId];
        }

        return $userPresence;
    }

    /**
     * Clave de cache donde vive la presencia de un canal.
     */
    protected function channelKey(string $channel): string
    {
        return "presence:{$channel}";
    }

    /**
     * Clave de cache con el registro de canales con presencia.
     */
    protected function channelsKey(): string
    {
        return 'presence:channels';
    }

    /**
     * Registra un canal para que `getUserPresence` pueda recorrerlo.
     */
    protected function registerChannel(string $channel): void
    {
        $channels = Cache::get($this->channelsKey(), []);

        if (! in_array($channel, $channels, true)) {
            $channels[] = $channel;
            Cache::put($this->channelsKey(), $channels, now()->addHours(24));
        }
    }

    /**
     * Check if user is present in channel
     */
    public function isUserPresent(string $channel, string $userId): bool
    {
        $presence = $this->getPresence($channel);

        return isset($presence[$userId]);
    }
}
