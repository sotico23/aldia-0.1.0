<?php

use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('el pdf de venta tolera ventas sin cliente asociado', function (string $view) {
    $user = User::factory()->create();
    $this->actingAs($user);

    $venta = Venta::factory()->create([
        'cliente_id' => null,
        'user_id' => $user->id,
        'owner_id' => $user->getOwnerId(),
    ]);

    $this->view($view, [
        'venta' => $venta,
        'logo' => null,
        'cspNonce' => 'test-nonce',
    ])
        ->assertSee('Cliente no especificado')
        ->assertSee('N/A');
})->with([
    'pdf formal' => ['pdf.venta'],
    'pdf informal' => ['pdf.venta-informal'],
]);
