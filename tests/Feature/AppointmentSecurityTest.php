<?php

use App\Models\Appointment;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed permissions needed for appointment routes
    Permission::firstOrCreate(['name' => 'citas.citas.viewAny']);
    Permission::firstOrCreate(['name' => 'citas.citas.create']);
    Permission::firstOrCreate(['name' => 'citas.citas.edit']);

    $this->owner = User::factory()->create([
        'business_name' => 'Negocio Test',
    ]);

    $this->provider = User::factory()->create([
        'creator_id' => $this->owner->id,
    ]);

    $this->client = User::factory()->create([
        'creator_id' => $this->owner->id,
    ]);

    $this->product = Producto::factory()->create([
        'owner_id' => $this->owner->id,
        'is_service' => true,
        'duracion' => 60,
        'precio_venta' => 25000,
        'categoria_id' => null,
    ]);

    $this->profile = PublicProfile::create([
        'user_id' => $this->owner->id,
        'owner_id' => $this->owner->getOwnerId(),
        'slug' => 'negocio-test',
        'title' => 'Negocio Test',
    ]);
});

// ─── Double-Booking Prevention ──────────────────────────────────

describe('Double-Booking Prevention', function () {

    test('appointment service detects scheduling conflicts', function () {
        $service = app(AppointmentService::class);

        // Create an existing appointment from 10:00 to 11:00
        Appointment::factory()->create([
            'provider_id' => $this->provider->id,
            'owner_id' => $this->owner->id,
            'producto_id' => $this->product->id,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'confirmada',
        ]);

        // Overlapping: 10:30 - 11:30 should conflict
        expect($service->hasConflict(
            $this->provider->id,
            now()->addDay()->setTime(10, 30),
            now()->addDay()->setTime(11, 30),
        ))->toBeTrue();

        // Non-overlapping: 11:00 - 12:00 should NOT conflict
        expect($service->hasConflict(
            $this->provider->id,
            now()->addDay()->setTime(11, 0),
            now()->addDay()->setTime(12, 0),
        ))->toBeFalse();
    });

    test('appointment service excludes cancelled appointments from conflict check', function () {
        $service = app(AppointmentService::class);

        // Create a cancelled appointment
        Appointment::factory()->create([
            'provider_id' => $this->provider->id,
            'owner_id' => $this->owner->id,
            'producto_id' => $this->product->id,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'cancelada',
        ]);

        // Same time slot should NOT conflict because the existing one is cancelled
        expect($service->hasConflict(
            $this->provider->id,
            now()->addDay()->setTime(10, 0),
            now()->addDay()->setTime(11, 0),
        ))->toBeFalse();
    });

    test('appointment service excludes a specific appointment on update', function () {
        $service = app(AppointmentService::class);

        $existing = Appointment::factory()->create([
            'provider_id' => $this->provider->id,
            'owner_id' => $this->owner->id,
            'producto_id' => $this->product->id,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'confirmada',
        ]);

        // Checking the same appointment with exclusion should NOT conflict
        expect($service->hasConflict(
            $this->provider->id,
            now()->addDay()->setTime(10, 0),
            now()->addDay()->setTime(11, 0),
            $existing->id,
        ))->toBeFalse();

        // A different overlapping appointment SHOULD conflict
        $other = Appointment::factory()->create([
            'provider_id' => $this->provider->id,
            'owner_id' => $this->owner->id,
            'producto_id' => $this->product->id,
            'start_time' => now()->addDay()->setTime(10, 30),
            'end_time' => now()->addDay()->setTime(11, 30),
            'status' => 'pendiente',
        ]);

        expect($service->hasConflict(
            $this->provider->id,
            now()->addDay()->setTime(10, 0),
            now()->addDay()->setTime(11, 0),
            $other->id,
        ))->toBeTrue();
    });

    test('admin store endpoint rejects overlapping appointment via slot unavailable exception', function () {
        // Create an existing appointment
        Appointment::factory()->create([
            'provider_id' => $this->provider->id,
            'owner_id' => $this->owner->id,
            'producto_id' => $this->product->id,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'confirmada',
        ]);

        // Grant permission and act as owner
        $this->owner->syncPermissions(['citas.citas.viewAny', 'citas.citas.create']);

        $this->actingAs($this->owner);

        $this->post('/appointments', [
            'client_id' => $this->client->id,
            'producto_id' => $this->product->id,
            'provider_id' => $this->provider->id,
            'start_time' => now()->addDay()->setTime(10, 30)->toDateTimeString(),
            'end_time' => now()->addDay()->setTime(11, 30)->toDateTimeString(),
        ])->assertSessionHasErrors('error');
    });

    test('booking store rejects overlapping appointment via slot unavailable exception', function () {
        // Create an existing appointment for the provider
        Appointment::factory()->create([
            'provider_id' => $this->owner->id,
            'owner_id' => $this->owner->id,
            'producto_id' => $this->product->id,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'confirmada',
        ]);

        $this->post("/booking/{$this->profile->slug}", [
            'service_id' => $this->product->id,
            'start_time' => now()->addDay()->setTime(10, 30)->toDateTimeString(),
            'client_name' => 'Juan Perez',
            'client_email' => 'juan@test.com',
            'payment_method' => 'message',
        ])->assertSessionHasErrors('error');
    });
});

// ─── Cancellation Policy ───────────────────────────────────────

describe('Cancellation Policy', function () {

    test('cancellation is denied when less than 24 hours before start', function () {
        $service = app(AppointmentService::class);

        $appointment = Appointment::factory()->create([
            'provider_id' => $this->provider->id,
            'owner_id' => $this->owner->id,
            'producto_id' => $this->product->id,
            'start_time' => now()->addHours(12), // 12 hours from now
            'end_time' => now()->addHours(13),
            'status' => 'confirmada',
        ]);

        expect($service->canCancel($appointment))->toBeFalse();
    });

    test('cancellation is allowed when more than 24 hours before start', function () {
        $service = app(AppointmentService::class);

        $appointment = Appointment::factory()->create([
            'provider_id' => $this->provider->id,
            'owner_id' => $this->owner->id,
            'producto_id' => $this->product->id,
            'start_time' => now()->addDays(3), // 3 days from now
            'end_time' => now()->addDays(3)->addHour(),
            'status' => 'confirmada',
        ]);

        expect($service->canCancel($appointment))->toBeTrue();
    });

    test('already cancelled appointment cannot be cancelled again', function () {
        $service = app(AppointmentService::class);

        $appointment = Appointment::factory()->create([
            'provider_id' => $this->provider->id,
            'owner_id' => $this->owner->id,
            'producto_id' => $this->product->id,
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHour(),
            'status' => 'cancelada',
        ]);

        expect($service->canCancel($appointment))->toBeFalse();
    });
});

// ─── Multi-Tenant Booking Injection ─────────────────────────────

describe('Multi-Tenant Booking Protection', function () {

    test('booking store rejects service_id from different tenant', function () {
        // Create another owner's service
        $otherOwner = User::factory()->create(['business_name' => 'Otro Negocio']);
        $otherProduct = Producto::factory()->create([
            'owner_id' => $otherOwner->id,
            'is_service' => true,
            'duracion' => 60,
            'precio_venta' => 30000,
            'categoria_id' => null,
        ]);

        $response = $this->post("/booking/{$this->profile->slug}", [
            'service_id' => $otherProduct->id,
            'start_time' => now()->addDay()->setTime(14, 0)->toDateTimeString(),
            'client_name' => 'Hacker',
            'client_email' => 'hacker@test.com',
            'payment_method' => 'message',
        ]);

        $response->assertStatus(403);
    });

    test('booking store allows service_id from correct tenant', function () {
        $response = $this->post("/booking/{$this->profile->slug}", [
            'service_id' => $this->product->id,
            'start_time' => now()->addDay()->setTime(14, 0)->toDateTimeString(),
            'client_name' => 'Cliente Legitimo',
            'client_email' => 'legitimo@test.com',
            'payment_method' => 'message',
        ]);

        // Should not be 403
        $response->assertStatus(302);
    });
});
