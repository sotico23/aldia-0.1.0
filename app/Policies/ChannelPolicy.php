<?php
/**
 * Channel Policy - Handles channel authorization
 */

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChannelPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can join notifications channel
     */
    public function joinNotifications(User $user, int $userId): bool
    {
        return $user->id === $userId;
    }

    /**
     * Determine if user can join driver channel
     */
    public function joinDriverChannel(User $user, int $driverId): bool
    {
        return $user->repartidor_id === $driverId;
    }

    /**
     * Determine if user can join order channel
     */
    public function joinOrderChannel(User $user, int $orderId): bool
    {
        return $user->id === $orderId;
    }

    /**
     * Determine if user can join conversation channel
     */
    public function joinConversationChannel(User $user, int $conversationId): bool
    {
        $conversacion = \App\Models\Conversacion::find($conversationId);
        
        if (! $conversacion) {
            return false;
        }

        return $user->id === $conversacion->comprador_id
            || $user->id === $conversacion->vendedor_id;
    }

    /**
     * Determine if user can join presence channel
     */
    public function joinPresenceChannel(User $user, int $channelId): bool
    {
        return $this->joinConversationChannel($user, $channelId);
    }
}