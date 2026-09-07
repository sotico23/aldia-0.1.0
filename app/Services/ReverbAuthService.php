<?php
/**
 * Reverb Auth Service - Handles WebSocket authentication
 */

namespace App\Services;

use Illuminate\Support\Facades\Auth;
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

        if ($accessToken->expired()) {
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
        Cache::put("reverb:connection:{$userId}:{$socketId}", [
            'user_id' => $userId,
            'socket_id' => $socketId,
            'connected_at' => now()->toISOString(),
        ], now()->addHours(24));
    }

    /**
     * Remove connection info
     */
    public function removeConnection(string $userId, string $socketId): void
    {
        Cache::forget("reverb:connection:{$userId}:{$socketId}");
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