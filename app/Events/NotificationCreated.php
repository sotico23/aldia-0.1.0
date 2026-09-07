<?php
/**
 * Notification Created Event - Triggered when a new notification is sent
 */

namespace App\Events;

use App\Http\Resources\Base\BaseResource;

class NotificationCreated implements ShouldBroadcast
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Create the event
     */
    public function create(): NotificationCreated
    {
        return new self();
    }

    /**
     * Get the event data
     */
    public function getEventData(): array
    {
        return [
            'id' => uniqid(),
            'type' => 'notification.created',
            'title' => $this->notification->title ?? 'Nueva notificación',
            'message' => $this->notification->message ?? '',
            'data' => $this->notification->data,
            'read_at' => $this->notification->read_at,
            'created_at' => $this->notification->created_at,
        ];
    }

    /**
     * Get the channel to subscribe to
     */
    public function getChannel(): string
    {
        return 'notifications.{userId}';
    }
}
