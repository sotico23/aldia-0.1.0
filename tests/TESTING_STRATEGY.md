# Real-time Architecture Testing Strategy

## Test Structure

```
tests/
├── Unit/
│   ├── Resources/
│   │   ├── BaseResourceTest.php
│   │   ├── ProductoResourceTest.php
│   │   └── NotificationResourceTest.php
│   └── Events/
│       ├── NotificationCreatedTest.php
│       ├── DeliveryOrderAssignedTest.php
│       ├── DeliveryPositionUpdatedTest.php
│       └── MensajeEnviadoTest.php
├── Feature/
│   ├── ChannelAuthorizationTest.php
│   ├── ReverbAuthServiceTest.php
│   ├── ReverbRateLimiterTest.php
│   ├── PresenceTrackerTest.php
│   ├── ReverbEventIntegrationTest.php
│   └── RealtimePestTest.php
├── Pest.php
├── phpunit.realtime.xml
└── run-realtime-tests.sh
```

## Test Categories

### 1. Unit Tests (`tests/Unit/`)
- **Resource Tests**: Verify resource data transformation, field inclusion, and serialization
- **Event Tests**: Verify event structure, channel naming, and data payloads

### 2. Feature Tests (`tests/Feature/`)
- **Channel Authorization**: Test channel access policies for all 4 real-time channels
- **Auth Service**: Test token validation, connection tracking, and user online status
- **Rate Limiter**: Test rate limit configuration, enforcement, and cleanup
- **Presence Tracker**: Test join/leave tracking, last seen updates, and cross-channel presence
- **Event Integration**: Test event broadcasting to correct channels
- **Pest Tests**: Modern syntax tests using Pest framework

## Running Tests

### All Real-time Tests
```bash
./tests/run-realtime-tests.sh
```

### Specific Test Suites
```bash
# Unit tests only
php artisan test --testsuite="Realtime Unit" --compact

# Feature tests only
php artisan test --testsuite="Realtime Feature" --compact

# Pest tests (realtime group)
php artisan test --filter=realtime --compact
```

### With Coverage
```bash
./tests/run-realtime-tests.sh --coverage
```

## Test Data & Factories

The tests use Laravel factories for:
- `User` model
- `Conversacion` model

Required factories should be created in:
```php
// database/factories/UserFactory.php
// database/factories/ConversacionFactory.php
```

## Key Test Scenarios

### Channel Authorization
- ✅ Notifications channel: User can only access their own notifications
- ✅ Delivery driver channel: Only assigned driver can access
- ✅ Delivery orders channel: Only order owner can access
- ✅ Chat conversation: Only participants can access

### Rate Limiting
- ✅ Notifications: 60 requests/minute
- ✅ Delivery: 120 requests/minute
- ✅ Chat: 30 requests/minute
- ✅ Presence: 10 requests/minute

### Presence Tracking
- ✅ User join/leave events
- ✅ Last seen timestamp updates
- ✅ Stale entry cleanup (5 min TTL)
- ✅ Cross-channel presence queries

### Event Broadcasting
- ✅ NotificationCreated → notifications.{userId}
- ✅ DeliveryOrderAssigned → delivery.driver.{driverId}
- ✅ DeliveryPositionUpdated → delivery.orders.{orderId}
- ✅ MensajeEnviado → chat.conversation.{conversationId}

## Continuous Integration

Add to `.github/workflows/realtime-tests.yml`:
```yaml
name: Real-time Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --prefer-dist --no-progress
      - run: ./tests/run-realtime-tests.sh
```

## Test Coverage Goals

- Unit tests: 90%+ coverage for resources and events
- Feature tests: 80%+ coverage for services and channels
- Integration tests: 70%+ coverage for event broadcasting