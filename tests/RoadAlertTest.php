<?php

use App\Http\Middleware\CheckDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(TestCase::class);

beforeEach(function () {
    config()->set('cache.default', 'array');
    Cache::flush();
});

function authenticateRoadReporter(): void
{
    test()->withoutMiddleware(CheckDelivery::class);
    Sanctum::actingAs(User::factory()->make(['id' => 777, 'is_active' => true]));
}

test('road alerts require authentication', function () {
    $this->getJson('/api/v1/delivery/road-alerts?lat=-33.45&lng=-70.66')->assertUnauthorized();
    $this->postJson('/api/v1/delivery/road-alerts', [])->assertUnauthorized();
});

test('shares nearby road alerts between drivers and expires them after one hour', function () {
    authenticateRoadReporter();
    $alert = $this->postJson('/api/v1/delivery/road-alerts', [
        'type' => 'police', 'lat' => -33.45, 'lng' => -70.66,
    ])->assertCreated()->assertJsonPath('alert.type', 'police')->json('alert');

    Sanctum::actingAs(User::factory()->make(['id' => 778, 'is_active' => true]));
    $this->getJson('/api/v1/delivery/road-alerts?lat=-33.45&lng=-70.66')
        ->assertOk()->assertJsonPath('alerts.0.id', $alert['id']);
    $this->getJson('/api/v1/delivery/road-alerts?lat=-34.45&lng=-70.66')
        ->assertOk()->assertJsonCount(0, 'alerts');
    $this->travel(61)->minutes();
    $this->getJson('/api/v1/delivery/road-alerts?lat=-33.45&lng=-70.66')
        ->assertOk()->assertJsonCount(0, 'alerts');
});

test('accepts supported road alert types', function (string $type) {
    authenticateRoadReporter();
    $this->postJson('/api/v1/delivery/road-alerts', [
        'type' => $type, 'lat' => -33.45, 'lng' => -70.66,
    ])->assertCreated()->assertJsonPath('alert.type', $type);
})->with(['police', 'obstacle', 'accident', 'traffic', 'roadworks']);

test('validates report type and coordinates', function (array $data, string $field) {
    authenticateRoadReporter();
    $this->postJson('/api/v1/delivery/road-alerts', $data)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['type' => 'unknown', 'lat' => 0, 'lng' => 0], 'type'],
    [['lat' => 0, 'lng' => 0], 'type'],
    [['type' => 'police', 'lat' => 91, 'lng' => 0], 'lat'],
    [['type' => 'obstacle', 'lat' => 0, 'lng' => -181], 'lng'],
]);

test('requires search coordinates', function () {
    authenticateRoadReporter();
    $this->getJson('/api/v1/delivery/road-alerts')->assertUnprocessable()
        ->assertJsonValidationErrors(['lat', 'lng']);
});

test('limits repeated road reports', function () {
    authenticateRoadReporter();
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/delivery/road-alerts', [
            'type' => 'police', 'lat' => 0, 'lng' => 0,
        ])->assertCreated();
    }
    $this->postJson('/api/v1/delivery/road-alerts', [
        'type' => 'police', 'lat' => 0, 'lng' => 0,
    ])->assertTooManyRequests();
});

test('inactive accounts cannot read or publish road alerts', function () {
    Sanctum::actingAs(User::factory()->make(['id' => 779, 'is_active' => false]));
    $this->getJson('/api/v1/delivery/road-alerts?lat=0&lng=0')->assertForbidden();
    $this->postJson('/api/v1/delivery/road-alerts', [
        'type' => 'police', 'lat' => 0, 'lng' => 0,
    ])->assertForbidden();
});
