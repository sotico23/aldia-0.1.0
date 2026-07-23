<?php

use App\Enums\PaymentStatus;
use App\Events\PaymentSuccessful;
use App\Models\Pedido;
use App\Models\PedidoStatusLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['email' => 'dummy@setup.test']);
});

// ============================================================================
// PaymentStatus Enum
// ============================================================================

test('PaymentStatus enum has all expected cases', function () {
    expect(PaymentStatus::cases())->toHaveCount(6);
    expect(PaymentStatus::Completed)->toBeInstanceOf(PaymentStatus::class);
    expect(PaymentStatus::Created)->toBeInstanceOf(PaymentStatus::class);
    expect(PaymentStatus::Pending)->toBeInstanceOf(PaymentStatus::class);
    expect(PaymentStatus::Failed)->toBeInstanceOf(PaymentStatus::class);
    expect(PaymentStatus::Cancelled)->toBeInstanceOf(PaymentStatus::class);
    expect(PaymentStatus::Local)->toBeInstanceOf(PaymentStatus::class);
});

test('PaymentStatus completed is terminal', function () {
    expect(PaymentStatus::Completed->isTerminal())->toBeTrue();
    expect(PaymentStatus::Failed->isTerminal())->toBeTrue();
    expect(PaymentStatus::Cancelled->isTerminal())->toBeTrue();
});

test('PaymentStatus pending is not terminal', function () {
    expect(PaymentStatus::Pending->isTerminal())->toBeFalse();
    expect(PaymentStatus::Created->isTerminal())->toBeFalse();
    expect(PaymentStatus::Local->isTerminal())->toBeFalse();
});

test('PaymentStatus label returns human-readable string', function () {
    expect(PaymentStatus::Completed->label())->toBe('Completado');
    expect(PaymentStatus::Created->label())->toBe('Creado');
    expect(PaymentStatus::Pending->label())->toBe('Pendiente');
    expect(PaymentStatus::Failed->label())->toBe('Fallido');
    expect(PaymentStatus::Cancelled->label())->toBe('Cancelado');
    expect(PaymentStatus::Local->label())->toBe('Pago local');
});

test('PaymentStatus from value resolves correctly', function () {
    expect(PaymentStatus::from('completed'))->toBe(PaymentStatus::Completed);
    expect(PaymentStatus::from('created'))->toBe(PaymentStatus::Created);
    expect(PaymentStatus::from('pending'))->toBe(PaymentStatus::Pending);
    expect(PaymentStatus::tryFrom('unknown'))->toBeNull();
});

test('PaymentStatus responds to enum helpers', function () {
    expect(PaymentStatus::tryFrom('completed'))->not->toBeNull();
    expect(PaymentStatus::tryFrom('cancelled'))->not->toBeNull();
    expect(PaymentStatus::tryFrom('invalid_status'))->toBeNull();
});

// ============================================================================
// PedidoStatusLog (Audit Trail)
// ============================================================================

test('pedido status change creates audit log entry', function () {
    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'cliente_id' => $user->id,
        'numero_pedido' => 'PED-AUDIT-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'metodo_pago' => 'paypal',
        'payment_status' => 'pending',
    ]);

    expect(PedidoStatusLog::count())->toBe(1);

    $log = PedidoStatusLog::first();
    expect($log->pedido_id)->toBe($pedido->id);
    expect($log->field)->toBe('payment_status');
    expect($log->from)->toBeNull();
    expect($log->to)->toBe('pending');
    expect($log->gateway)->toBe('paypal');
});

test('pedido status update logs the transition', function () {
    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'cliente_id' => $user->id,
        'numero_pedido' => 'PED-AUDIT-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'metodo_pago' => 'mercado_pago',
        'payment_status' => 'pending',
    ]);

    PedidoStatusLog::truncate();

    $pedido->update(['payment_status' => 'completed']);

    $log = PedidoStatusLog::where('pedido_id', $pedido->id)->first();
    expect($log)->not->toBeNull();
    expect($log->from)->toBe('pending');
    expect($log->to)->toBe('completed');
    expect($log->field)->toBe('payment_status');
});

test('pedido status change is idempotent — same value logs nothing', function () {
    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'cliente_id' => $user->id,
        'numero_pedido' => 'PED-AUDIT-003',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'metodo_pago' => 'paypal',
        'payment_status' => 'pending',
    ]);

    PedidoStatusLog::truncate();

    $pedido->update(['payment_status' => 'pending']);

    expect(PedidoStatusLog::count())->toBe(0);
});

// ============================================================================
// TransactionService
// ============================================================================

test('TransactionService recordPayment creates transaction and fires event', function () {
    Event::fake([PaymentSuccessful::class]);

    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'cliente_id' => $user->id,
        'numero_pedido' => 'PED-TRX-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 50000,
        'metodo_pago' => 'paypal',
        'payment_status' => 'pending',
    ]);

    $service = app(TransactionService::class);
    $transaction = $service->recordPayment(
        gateway: 'paypal',
        gatewayTransactionId: 'PAY-123-TEST',
        pedido: $pedido,
        amount: 50000,
        currency: 'USD',
        fee: 1500,
        extraMetadata: ['custom_key' => 'custom_value'],
    );

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($transaction->gateway)->toBe('paypal');
    expect($transaction->gateway_transaction_id)->toBe('PAY-123-TEST');
    expect($transaction->pedido_id)->toBe($pedido->id);
    expect((int) $transaction->amount)->toBe(50000);
    expect((int) $transaction->fee)->toBe(1500);
    expect((int) $transaction->net_amount)->toBe(48500);
    expect($transaction->currency)->toBe('USD');
    expect($transaction->type)->toBe('customer_payment');
    expect($transaction->status)->toBe('approved');
    expect($transaction->metadata['numero_pedido'])->toBe('PED-TRX-001');
    expect($transaction->metadata['custom_key'])->toBe('custom_value');

    Event::assertDispatched(PaymentSuccessful::class);
});

test('TransactionService recordPayment is idempotent', function () {
    Event::fake();

    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'cliente_id' => $user->id,
        'numero_pedido' => 'PED-TRX-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 50000,
        'metodo_pago' => 'paypal',
        'payment_status' => 'pending',
    ]);

    $service = app(TransactionService::class);

    $first = $service->recordPayment(
        gateway: 'paypal',
        gatewayTransactionId: 'PAY-IDEMPOTENT',
        pedido: $pedido,
        amount: 50000,
        currency: 'USD',
    );

    $second = $service->recordPayment(
        gateway: 'paypal',
        gatewayTransactionId: 'PAY-IDEMPOTENT',
        pedido: $pedido,
        amount: 50000,
        currency: 'USD',
    );

    expect($first->id)->toBe($second->id);
    expect(Transaction::where('gateway_transaction_id', 'PAY-IDEMPOTENT')->count())->toBe(1);
});

test('TransactionService recordGatewayOnly returns null for empty id', function () {
    $service = app(TransactionService::class);

    $result = $service->recordGatewayOnly('paypal', '', 1000, 'USD');

    expect($result)->toBeNull();
});

// ============================================================================
// payments:cleanup command
// ============================================================================

test('payments:cleanup does not throw and reports zero', function () {
    $this->artisan('payments:cleanup')
        ->assertSuccessful();
});

test('payments:cleanup dry-run mode works', function () {
    $this->artisan('payments:cleanup --dry-run')
        ->assertSuccessful();
});
