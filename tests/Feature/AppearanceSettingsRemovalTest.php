<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('appearance settings route is no longer registered', function () {
    expect(Route::has('appearance.edit'))->toBeFalse();
});

test('appearance settings page is not accessible', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/appearance')
        ->assertNotFound();
});

test('institutional emerald primary color is configured', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--brand-primary: #059669')
        ->toContain('--primary: var(--brand-primary)')
        ->not->toContain('--primary: oklch(0.205 0 0)');
});
