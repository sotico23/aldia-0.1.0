<?php
/**
 * Pest Test Configuration
 */

uses()->group('realtime');

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->artisan('migrate:fresh --seed');
});

afterEach(function () {
    // Cleanup
});

/**
 * Dataset for channel authorization tests
 */
dataset('channels', [
    'notifications' => ['notifications.1', 'user', 1],
    'delivery_driver' => ['delivery.driver.1', 'driver', 1],
    'delivery_orders' => ['delivery.orders.1', 'order', 1],
    'chat_conversation' => ['chat.conversation.1', 'conversation', 1],
]);

/**
 * Dataset for event types
 */
dataset('events', [
    'notification_created' => [\App\Events\NotificationCreated::class, 'notifications'],
    'delivery_order_assigned' => [\App\Events\DeliveryOrderAssigned::class, 'delivery.driver'],
    'delivery_position_updated' => [\App\Events\DeliveryPositionUpdated::class, 'delivery.orders'],
    'mensaje_enviado' => [\App\Events\MensajeEnviado::class, 'chat.conversation'],
]);