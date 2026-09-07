<?php
/**
 * Presence Tracker - Tracks user presence in channels
 */

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PresenceTracker
{
    /**
     * Track user joining a channel
     */
    public function trackJoin(string $channel, string $userId, array $userData = []): void
    {
        $key = "presence:{$channel}";
        $presence = Cache::get($key, []);
        
        $presence[$userId] = array_merge($userData, [
            'joined_at' => now()->toISOString(),
            'last_seen' => now()->toISOString(),
        ]);

        Cache::put($key, $presence, now()->addHours(24));
    }

    /**
     * Track user leaving a channel
     */
    public function trackLeave(string $channel, string $userId): void
    {
        $key = "presence:{$channel}";
        $presence = Cache::get($key, []);
        
        unset($presence[$userId]);
        
        Cache::put($key, $presence, now()->addHours(24));
    }

    /**
     * Update user last seen
     */
    public function updateLastSeen(string $channel, string $userId): void
    {
        $key = "presence:{$channel}";
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
        $key = "presence:{$channel}";
        $presence = Cache::get($key, []);
        
        // Filter out stale entries (older than 5 minutes)
        $now = now();
        return array_filter($presence, function ($user) use ($now) {
            $lastSeen = \Carbon\Carbon::parse($user['last_seen']);
            return $lastSeen->diffInMinutes($now) < 5;
        });
    }

    /**
     * Get user presence across all channels
     */
    public function getUserPresence(string $userId): array
    {
        $channels = [
            'chat',
            'notifications',
            'delivery',
        ];

        $userPresence = [];
        
        foreach ($channels as $channel) {
            $presence = $this->getPresence($channel);
            if (isset($presence[$userId])) {
                $userPresence[$channel] = $presence[$userId];
            }
        }

        return $userPresence;
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