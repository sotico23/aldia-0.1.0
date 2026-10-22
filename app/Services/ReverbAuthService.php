<?php

/**
 * Reverb Auth Service - Handles WebSocket authentication
 */

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class ReverbAuthService
{
    /**
     * Validate WebSocket connection token
     */
    public function validateToken(string $token): ?object
    {
        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return null;
        }

        // Sanctum no expone `expired()`: el vencimiento se lee de `expires_at`.
        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            $accessToken->delete();

            return null;
        }

        return $accessToken->tokenable;
    }

    /**
     * Get user from token
     */
    public function getUserFromToken(string $token): ?object
    {
        $user = $this->validateToken($token);

        if (! $user) {
            return null;
        }

        return $user;
    }

    /**
     * Store connection info
     */
    public function storeConnection(string $userId, string $socketId): void
    {
        $connections = $this->getUserConnections($userId);

        $connections = array_values(array_filter(
            $connections,
            fn (array $connection) => $connection['socket_id'] !== $socketId
        ));

        $connections[] = [
            'user_id' => $userId,
            'socket_id' => $socketId,
            'connected_at' => now()->toISOString(),
        ];

        Cache::put("reverb:connections:{$userId}", $connections, now()->addHours(24));
    }

    /**
     * Remove connection info
     */
    public function removeConnection(string $userId, string $socketId): void
    {
        $connections = array_values(array_filter(
            $this->getUserConnections($userId),
            fn (array $connection) => $connection['socket_id'] !== $socketId
        ));

        Cache::put("reverb:connections:{$userId}", $connections, now()->addHours(24));
    }

    /**
     * Get user connections
     */
    public function getUserConnections(string $userId): array
    {
        return Cache::get("reverb:connections:{$userId}", []);
    }

    /**
     * Check if user is online
     */
    public function isUserOnline(string $userId): bool
    {
        $connections = $this->getUserConnections($userId);

        return count($connections) > 0;
    }
}
