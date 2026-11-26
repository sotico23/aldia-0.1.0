<?php

use App\Models\PublicProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows active stores to visitors without sharing private account data', function () {
    $owner = User::factory()->create();
    $store = PublicProfile::factory()->for($owner)->create(['owner_id' => $owner->getOwnerId()]);
    PublicProfile::factory()->for($owner)->create([
        'owner_id' => $owner->getOwnerId(),
        'is_active' => false,
    ]);

    $this->withoutVite()
        ->get('/tiendas')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/index')
            ->where('auth.user', null)
            ->has('profiles.data', 1)
            ->where('profiles.data.0.slug', $store->slug)
            ->where('profiles.data.0.user.name', $owner->name)
            ->missing('profiles.data.0.user.email')
            ->missing('profiles.data.0.email'));
});

it('redirects the previous store directory URL to the public directory', function () {
    $this->get('/tienda')
        ->assertStatus(301)
        ->assertRedirect('/tiendas');
});
