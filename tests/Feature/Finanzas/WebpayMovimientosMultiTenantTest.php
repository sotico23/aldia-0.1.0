<?php

use App\Models\Pedido;
use App\Models\User;
use App\Models\WebpayTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Permission::firstOrCreate(['name' => 'finanzas.tesoreria.viewAny', 'guard_name' => 'web']);

    $tesoreriaRole = Role::firstOrCreate(['name' => 'Tesoreria', 'guard_name' => 'web']);
    $tesoreriaRole->givePermissionTo('finanzas.tesoreria.viewAny');

    $this->userA = User::factory()->create();
    $this->userA->syncRoles('Tesoreria');

    $this->userB = User::factory()->create();
    $this->userB->syncRoles('Tesoreria');

    // User A's Webpay transactions
    WebpayTransaction::create([
        'owner_id' => $this->userA->id,
        'token' => 'token-a-1',
        'amount' => 10000,
        'status' => 'approved',
        'buy_order' => 'ORD-A-001',
    ]);

    WebpayTransaction::create([
        'owner_id' => $this->userA->id,
        'token' => 'token-a-2',
        'amount' => 20000,
        'status' => 'pending',
        'buy_order' => 'ORD-A-002',
    ]);

    // User B's Webpay transaction
    WebpayTransaction::create([
        'owner_id' => $this->userB->id,
        'token' => 'token-b-1',
        'amount' => 50000,
        'status' => 'approved',
        'buy_order' => 'ORD-B-001',
    ]);

    // User A's Pedido with MercadoPago payment
    Pedido::create([
        'owner_id' => $this->userA->id,
        'user_id' => $this->userA->id,
        'cliente_id' => $this->userA->id,
        'numero_pedido' => 'PED-A-001',
        'estado' => 'confirmado',
        'total' => 30000,
        'subtotal' => 25210,
        'impuesto' => 4790,
        'metodo_pago' => 'mercadopago',
        'payment_id' => 'mp-payment-a-1',
        'payment_status' => 'completed',
        'payment_data' => ['status' => 'approved'],
    ]);
});

test('User A solo ve sus propias transacciones en movimientos', function () {
    $this->actingAs($this->userA);

    $response = $this->get(route('webpay.movimientos'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/Pagos/WebpayMovimientos')
        ->has('transactions.data', 3)
    );
});

test('User B solo ve sus propias transacciones en movimientos', function () {
    $this->actingAs($this->userB);

    $response = $this->get(route('webpay.movimientos'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/Pagos/WebpayMovimientos')
        ->has('transactions.data', 1)
        ->where('transactions.data.0.buy_order', 'ORD-B-001')
    );
});

test('Usuario sin permiso finanzas.tesoreria.viewAny no accede a movimientos', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('webpay.movimientos'))
        ->assertForbidden();
});

test('WebpayTransaction respeta OwnerScope por owner_id', function () {
    $this->actingAs($this->userA);

    $transactions = WebpayTransaction::all();

    expect($transactions)->toHaveCount(2);
    foreach ($transactions as $t) {
        expect($t->owner_id)->toBe($this->userA->id);
    }
});
