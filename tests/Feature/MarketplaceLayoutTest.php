<?php

use App\Models\PublicProfile;
use App\Models\User;
use Illuminate\Support\HtmlString;

test('el layout publico muestra ambos laterales para invitados', function () {
    $this->blade('<x-app-layout>{{ $content }}</x-app-layout>', [
        'content' => new HtmlString('<p>Contenido de la tienda</p>'),
    ])
        ->assertSee('Navegación principal')
        ->assertSee('Mi cuenta')
        ->assertSee('Contenido de la tienda')
        ->assertSee('Ingresa para ver tu perfil y tu tienda.')
        ->assertDontSee('Cerrar sesión');
});

test('el lateral muestra la cuenta autenticada y su propia tienda', function () {
    $visitor = User::factory()->create(['name' => 'Visitante autenticado']);
    $profile = PublicProfile::factory()->create([
        'user_id' => $visitor->id,
        'owner_id' => $visitor->id,
        'slug' => 'tienda-del-visitante',
    ]);

    $this->actingAs($visitor)
        ->blade('<x-app-layout>Otra tienda</x-app-layout>')
        ->assertSee($visitor->name)
        ->assertSee($visitor->email)
        ->assertSee(route('marketplace.show', $profile->slug))
        ->assertSee('Cerrar sesión')
        ->assertDontSee('Ingresa para ver tu perfil y tu tienda.');
});
