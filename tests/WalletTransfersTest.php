<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleRegionalSettings;
use App\Http\Middleware\TrackPageViews;
use App\Models\ClienteFrecuente;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPaymentRequest;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use App\Services\WalletService;
use App\Services\WalletTransferService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('cache.default', 'array');
    DB::purge('sqlite');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('country')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->rememberToken();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->unsignedBigInteger('creator_id')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    foreach (['2026_03_08_214259_create_permission_tables.php', '2026_05_13_233332_add_level_to_roles_table.php', '2026_05_31_210237_add_owner_id_to_roles_table.php', '2026_11_17_204443_create_wallets_table.php', '2026_11_17_204444_create_wallet_transactions_table.php', '2026_11_18_224550_create_clientes_frecuentes_table.php'] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sender = User::withoutEvents(fn (): User => User::factory()->create());
    $this->recipient = User::withoutEvents(fn (): User => User::factory()->create());
    $permission = Permission::firstOrCreate(['name' => 'finanzas.wallets.view', 'guard_name' => 'web']);
    $this->sender->givePermissionTo($permission);
    $this->recipient->givePermissionTo($permission);
    DB::table('wallets')->insert(['user_id' => $this->sender->id, 'balance' => '100.00', 'currency' => 'CLP', 'status' => 'active', 'allow_overdraft' => false]);
    (require database_path('migrations/2026_11_18_205013_add_account_number_and_transfers_to_wallets.php'))->up();
    (require database_path('migrations/2026_11_18_211754_add_share_token_to_wallet_payment_requests.php'))->up();
    $this->senderWallet = Wallet::where('user_id', $this->sender->id)->firstOrFail();
    $this->recipientWallet = Wallet::factory()->create(['user_id' => $this->recipient->id]);
    $this->service = app(WalletTransferService::class);
    $this->withoutMiddleware([HandleInertiaRequests::class, HandleRegionalSettings::class, TrackPageViews::class]);
});

test('existing and new wallets receive unique permanent personal numbers', function () {
    expect($this->senderWallet->account_number)->toMatch('/^[0-9]{16}$/')
        ->not->toBe($this->recipientWallet->account_number);
    $number = $this->senderWallet->account_number;
    $this->senderWallet->update(['balance' => '90.00']);
    expect($this->senderWallet->fresh()->account_number)->toBe($number);
    $this->senderWallet->account_number = '1234567890123456';
    expect(fn () => $this->senderWallet->save())->toThrow(LogicException::class);
});

test('database prevents number changes or assigning a wallet to another user', function (string $field) {
    $value = $field === 'account_number' ? '1234567890123456' : $this->recipient->id;
    expect(fn () => DB::table('wallets')->where('id', $this->senderWallet->id)->update([$field => $value]))->toThrow(QueryException::class);
})->with(['account_number', 'user_id']);

test('transfer moves exact decimals and writes matching debit and credit once', function () {
    $key = (string) Str::uuid();
    $transfer = $this->service->transfer($this->sender, $this->recipientWallet->account_number, '10.01', $key, 'Compra');
    $retry = $this->service->transfer($this->sender, $this->recipientWallet->account_number, '10.01', $key, 'Compra');
    expect($retry->id)->toBe($transfer->id)
        ->and($this->senderWallet->fresh()->balance)->toBe('89.99')
        ->and($this->recipientWallet->fresh()->balance)->toBe('10.01')
        ->and(WalletTransfer::count())->toBe(1)
        ->and(WalletTransaction::where('reference_id', $transfer->id)->where('reference_type', $transfer->getMorphClass())->count())->toBe(2)
        ->and(WalletTransaction::where('type', 'debit')->first()->balance_before)->toBe('100.00')
        ->and(WalletTransaction::where('type', 'credit')->first()->balance_after)->toBe('10.01');
});

test('transfer rejects invalid amounts without modifying balances', function (string $amount) {
    expect(fn () => $this->service->transfer($this->sender, $this->recipientWallet->account_number, $amount, (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($this->senderWallet->fresh()->balance)->toBe('100.00')->and(WalletTransaction::count())->toBe(0);
})->with(['0', '-1', '0.001', '100.01', '99999999999', 'abc']);

test('transfers reject frozen wallets currency mismatch and self payment', function (string $case) {
    $account = $this->recipientWallet->account_number;
    match ($case) {
        'sender_frozen' => $this->senderWallet->update(['status' => 'frozen']),
        'recipient_frozen' => $this->recipientWallet->update(['status' => 'frozen']),
        'currency' => $this->recipientWallet->update(['currency' => 'USD']),
        'self' => $account = $this->senderWallet->account_number,
        'missing' => $account = '1234567890123456',
    };
    expect(fn () => $this->service->transfer($this->sender, $account, '10', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(WalletTransaction::count())->toBe(0);
})->with(['sender_frozen', 'recipient_frozen', 'currency', 'self', 'missing']);

test('reusing a transfer key with changed data is rejected', function () {
    $key = (string) Str::uuid();
    $this->service->transfer($this->sender, $this->recipientWallet->account_number, '10', $key);
    expect(fn () => $this->service->transfer($this->sender, $this->recipientWallet->account_number, '11', $key))->toThrow(ValidationException::class);
    expect(WalletTransfer::count())->toBe(1);
});

test('both ledger entries and balances roll back if either credit fails', function () {
    DB::unprepared("CREATE TRIGGER fail_wallet_credit BEFORE INSERT ON wallet_transactions WHEN NEW.type = 'credit' BEGIN SELECT RAISE(ABORT, 'Simulated ledger failure'); END");
    expect(fn () => $this->service->transfer($this->sender, $this->recipientWallet->account_number, '10', (string) Str::uuid()))->toThrow(QueryException::class);
    expect($this->senderWallet->fresh()->balance)->toBe('100.00')
        ->and($this->recipientWallet->fresh()->balance)->toBe('0.00')
        ->and(WalletTransaction::count())->toBe(0)->and(WalletTransfer::count())->toBe(0);
});

test('requesting payment does not debit money and payer acceptance is idempotent', function () {
    $key = (string) Str::uuid();
    $request = $this->service->requestPayment($this->recipient, $this->senderWallet->account_number, '12.34', $key, 'Pedido');
    $retry = $this->service->requestPayment($this->recipient, $this->senderWallet->account_number, '12.34', $key, 'Pedido');
    expect($retry->id)->toBe($request->id)->and(WalletPaymentRequest::count())->toBe(1)->and(WalletTransaction::count())->toBe(0);
    $this->service->resolvePayment($this->sender, $request, 'accept');
    $this->service->resolvePayment($this->sender, $request, 'accept');
    expect($request->fresh()->status)->toBe('paid')->and($request->fresh()->transfer_id)->not->toBeNull()
        ->and($this->senderWallet->fresh()->balance)->toBe('87.66')->and($this->recipientWallet->fresh()->balance)->toBe('12.34')
        ->and(WalletTransfer::count())->toBe(1)->and(WalletTransaction::count())->toBe(2);
});

test('payment checks the current payer balance when accepted and preserves pending on failure', function () {
    $request = $this->service->requestPayment($this->recipient, $this->senderWallet->account_number, '90', (string) Str::uuid());
    $this->senderWallet->update(['balance' => '20.00']);
    expect(fn () => $this->service->resolvePayment($this->sender, $request, 'accept'))->toThrow(ValidationException::class);
    expect($request->fresh()->status)->toBe('pending')->and(WalletTransaction::count())->toBe(0);
});

test('only payer accepts or rejects and only requester cancels even for master', function () {
    $request = $this->service->requestPayment($this->recipient, $this->senderWallet->account_number, '10', (string) Str::uuid());
    expect(fn () => $this->service->resolvePayment($this->recipient, $request, 'accept'))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->resolvePayment($this->sender, $request, 'cancel'))->toThrow(AuthorizationException::class);
    $master = User::withoutEvents(fn (): User => User::factory()->create());
    $master->assignRole(Role::create(['name' => 'Master', 'guard_name' => 'web', 'level' => 0]));
    expect(fn () => $this->service->resolvePayment($master, $request, 'accept'))->toThrow(AuthorizationException::class);
    $this->service->resolvePayment($this->recipient, $request, 'cancel');
    expect($request->fresh()->status)->toBe('cancelled')->and(WalletTransaction::count())->toBe(0);
    expect(fn () => $this->service->resolvePayment($this->sender, $request, 'accept'))->toThrow(ValidationException::class);
});

test('rejected payment cannot be paid later', function () {
    $request = $this->service->requestPayment($this->recipient, $this->senderWallet->account_number, '10', (string) Str::uuid());
    $this->service->resolvePayment($this->sender, $request, 'reject');
    expect($request->fresh()->status)->toBe('rejected');
    expect(fn () => $this->service->resolvePayment($this->sender, $request, 'accept'))->toThrow(ValidationException::class);
    expect(WalletTransaction::count())->toBe(0);
});

test('recipient lookup returns identity without exposing another users balance or email', function () {
    $this->actingAs($this->sender)->getJson(route('billetera.destinatario', ['account_number' => $this->recipientWallet->account_number]))
        ->assertOk()->assertExactJson(['account_number' => $this->recipientWallet->account_number, 'name' => $this->recipient->name, 'currency' => 'CLP']);
});

test('http transfer always debits authenticated users wallet and validates permission', function () {
    $payload = ['account_number' => $this->recipientWallet->account_number, 'amount' => '3.33', 'request_key' => (string) Str::uuid(), 'sender_wallet_id' => $this->recipientWallet->id];
    $this->actingAs($this->sender)->post(route('billetera.transferencias.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    expect($this->senderWallet->fresh()->balance)->toBe('96.67');
    $this->sender->revokePermissionTo('finanzas.wallets.view');
    $this->actingAs($this->sender)->postJson(route('billetera.transferencias.store'), $payload)->assertForbidden();
});

test('http requests require payer consent and do not let outsiders resolve them', function () {
    $this->actingAs($this->recipient)->post(route('billetera.solicitudes.store'), ['account_number' => $this->senderWallet->account_number, 'amount' => '4', 'request_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
    $request = WalletPaymentRequest::firstOrFail();
    $this->actingAs($this->recipient)->postJson(route('billetera.solicitudes.resolve', $request), ['decision' => 'accept'])->assertForbidden();
    $this->actingAs($this->sender)->post(route('billetera.solicitudes.resolve', $request), ['decision' => 'accept'])->assertRedirect()->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe('paid');
});

test('wallet page exposes only personal payment requests and its permanent number', function () {
    $request = $this->service->requestPayment($this->recipient, $this->senderWallet->account_number, '4', (string) Str::uuid(), 'Visible personal');
    $outsiders = User::withoutEvents(fn () => User::factory()->count(2)->create());
    WalletPaymentRequest::factory()->create([
        'requester_wallet_id' => Wallet::factory()->create(['user_id' => $outsiders[0]->id])->id,
        'payer_wallet_id' => Wallet::factory()->create(['user_id' => $outsiders[1]->id])->id,
    ]);
    $this->actingAs($this->sender)->withHeader('X-Inertia', 'true')->get(route('cajero.billetera'))
        ->assertOk()->assertJsonPath('props.wallet.account_number', $this->senderWallet->account_number)
        ->assertJsonCount(1, 'props.payment_requests.data')->assertJsonPath('props.payment_requests.data.0.id', $request->id)
        ->assertJsonPath('props.payment_requests.data.0.incoming', true);
});

test('cashier can create update and delete own frequent clients', function () {
    $this->actingAs($this->sender)->post(route('billetera.frecuentes.store'), [
        'nombre' => '  Cliente Uno  ',
        'account_number' => $this->recipientWallet->account_number,
        'nota' => '  Mayorista  ',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $cliente = ClienteFrecuente::firstOrFail();
    expect((int) $cliente->wallet_id)->toBe($this->senderWallet->id)
        ->and($cliente->nombre)->toBe('Cliente Uno')
        ->and($cliente->nota)->toBe('Mayorista');

    $this->actingAs($this->sender)->put(route('billetera.frecuentes.update', $cliente), [
        'nombre' => 'Cliente Actualizado',
        'account_number' => $this->recipientWallet->account_number,
        'nota' => '',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($cliente->fresh()->nombre)->toBe('Cliente Actualizado')
        ->and($cliente->fresh()->nota)->toBeNull();

    $this->actingAs($this->sender)->delete(route('billetera.frecuentes.destroy', $cliente))
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(ClienteFrecuente::count())->toBe(0);
});

test('frequent clients are scoped to the wallet and validate the destination', function () {
    $cliente = ClienteFrecuente::create([
        'wallet_id' => $this->senderWallet->id,
        'nombre' => 'Cliente del cajero',
        'account_number' => $this->recipientWallet->account_number,
    ]);
    $third = User::withoutEvents(fn (): User => User::factory()->create());
    $thirdWallet = Wallet::factory()->create(['user_id' => $third->id]);

    $this->actingAs($this->recipient)->put(route('billetera.frecuentes.update', $cliente), [
        'nombre' => 'Intruso',
        'account_number' => $thirdWallet->account_number,
    ])->assertForbidden();
    $this->actingAs($this->recipient)->delete(route('billetera.frecuentes.destroy', $cliente))->assertForbidden();

    expect($cliente->fresh()->nombre)->toBe('Cliente del cajero');

    $this->actingAs($this->sender)->postJson(route('billetera.frecuentes.store'), [
        'nombre' => 'Mi propia billetera',
        'account_number' => $this->senderWallet->account_number,
    ])->assertUnprocessable()->assertJsonValidationErrors('account_number');

    $this->actingAs($this->sender)->postJson(route('billetera.frecuentes.store'), [
        'nombre' => 'Inexistente',
        'account_number' => '1234567890123456',
    ])->assertUnprocessable()->assertJsonValidationErrors('account_number');

    $this->actingAs($this->sender)->postJson(route('billetera.frecuentes.store'), [
        'nombre' => 'Duplicado',
        'account_number' => $this->recipientWallet->account_number,
    ])->assertUnprocessable()->assertJsonValidationErrors('account_number');

    $this->actingAs($this->sender)->postJson(route('billetera.frecuentes.store'), [
        'nombre' => 'Formato inválido',
        'account_number' => '123',
    ])->assertUnprocessable()->assertJsonValidationErrors('account_number');

    expect(ClienteFrecuente::count())->toBe(1);
});

test('wallet page lists only the authenticated wallet frequent clients', function () {
    ClienteFrecuente::create([
        'wallet_id' => $this->senderWallet->id,
        'nombre' => 'Cliente propio',
        'account_number' => $this->recipientWallet->account_number,
        'nota' => 'Preferente',
    ]);
    ClienteFrecuente::create([
        'wallet_id' => $this->recipientWallet->id,
        'nombre' => 'Cliente ajeno',
        'account_number' => $this->senderWallet->account_number,
    ]);

    $this->actingAs($this->sender)->withHeader('X-Inertia', 'true')->get(route('cajero.billetera'))
        ->assertOk()
        ->assertJsonCount(1, 'props.clientes_frecuentes')
        ->assertJsonPath('props.clientes_frecuentes.0.nombre', 'Cliente propio')
        ->assertJsonPath('props.clientes_frecuentes.0.account_number', $this->recipientWallet->account_number)
        ->assertJsonPath('props.clientes_frecuentes.0.nota', 'Preferente');
});

test('master can recharge any active user including one without a wallet', function () {
    $master = User::withoutEvents(fn (): User => User::factory()->create());
    $master->assignRole(Role::create(['name' => 'Master', 'guard_name' => 'web', 'level' => 0]));
    $newUser = User::withoutEvents(fn (): User => User::factory()->create());
    $this->actingAs($master)->post(route('billeteras.recargas.cajero', $newUser), ['amount' => '500', 'note' => 'Saldo inicial'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $wallet = app(WalletService::class)->walletFor($newUser);
    expect($wallet->account_number)->toMatch('/^[0-9]{16}$/')->and($wallet->balance)->toBe('500.00');
    $this->actingAs($this->sender)->postJson(route('billeteras.recargas.cajero', $newUser), ['amount' => '50'])->assertForbidden();
});

test('master recharge selector includes active tenants without a wallet', function () {
    (require database_path('migrations/2026_11_17_204446_create_commission_rules_table.php'))->up();
    $master = User::withoutEvents(fn (): User => User::factory()->create());
    $master->assignRole(Role::create(['name' => 'Master', 'guard_name' => 'web', 'level' => 0]));
    $newUser = User::withoutEvents(fn (): User => User::factory()->create());
    $this->actingAs($master)->withHeader('X-Inertia', 'true')->get(route('billeteras.index'))->assertOk()
        ->assertJsonFragment(['user_id' => $newUser->id, 'wallet_id' => null, 'name' => $newUser->name, 'email' => $newUser->email, 'balance' => 0, 'currency' => 'CLP', 'status' => null]);
});

test('payment refuses to change its currency even if both wallets changed currency', function () {
    $request = $this->service->requestPayment($this->recipient, $this->senderWallet->account_number, '10', (string) Str::uuid());
    $this->senderWallet->update(['currency' => 'USD']);
    $this->recipientWallet->update(['currency' => 'USD']);
    expect(fn () => $this->service->resolvePayment($this->sender, $request, 'accept'))->toThrow(ValidationException::class);
    expect($request->fresh()->status)->toBe('pending')->and(WalletTransaction::count())->toBe(0);
});

test('wallet forms stay responsive and require confirmation before transfers and payments', function () {
    $chrome = collect(['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium'])->first(fn (string $path): bool => is_file($path));
    if (! $chrome || ! is_file(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Requires Chrome and npm run build.');
    }
    $payment = $this->service->requestPayment($this->recipient, null, '15.25', (string) Str::uuid(), 'Pedido para comprobar el QR');
    $response = $this->actingAs($this->sender)->withHeader('X-Inertia', 'true')->get(route('billetera.enlaces.show', $payment->share_token))->assertOk();
    $fixture = ['payment' => $response->json('props.payment'), 'wallet' => $response->json('props.wallet')];
    $process = new Process(['node', base_path('tests/wallet-browser.mjs')], base_path(), ['CHROME_BINARY' => $chrome, 'WALLET_LINK_FIXTURE' => json_encode($fixture, JSON_THROW_ON_ERROR)]);
    $process->setTimeout(150);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    $results = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($results)->toHaveCount(10);
    foreach ($results as $result) {
        expect($result['failures'])->toBeEmpty(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
});

test('creating a shareable payment link returns an opaque token without reserving money', function () {
    $key = (string) Str::uuid();
    $this->actingAs($this->recipient)->post(route('billetera.enlaces.store'), ['amount' => '15.25', 'note' => 'Cobro compartido', 'request_key' => $key])
        ->assertRedirect()->assertSessionHasNoErrors();
    $payment = WalletPaymentRequest::firstOrFail();
    expect($payment->share_token)->toHaveLength(64)->and($payment->payer_wallet_id)->toBeNull()
        ->and($payment->status)->toBe('pending')->and(WalletTransaction::count())->toBe(0);
    $this->actingAs($this->recipient)->post(route('billetera.enlaces.store'), ['amount' => '15.25', 'note' => 'Cobro compartido', 'request_key' => $key])
        ->assertRedirect(route('billetera.enlaces.show', $payment->share_token));
    expect(WalletPaymentRequest::count())->toBe(1);
});

test('opening link or QR never debits money and QR encodes the actual shared URL locally', function () {
    $payment = $this->service->requestPayment($this->recipient, null, '15.25', (string) Str::uuid());
    $url = route('billetera.enlaces.show', $payment->share_token);
    $response = $this->actingAs($this->sender)->withHeader('X-Inertia', 'true')->get($url)->assertOk()
        ->assertJsonPath('props.payment.url', $url)->assertJsonPath('props.payment.can_pay', true)
        ->assertJsonPath('props.payment.recipient', $this->recipient->name);
    $qr = $response->json('props.payment.qr_code');
    expect($qr)->toStartWith('data:image/svg+xml;base64,');
    $svg = base64_decode(substr($qr, strlen('data:image/svg+xml;base64,')), true);
    expect($svg)->toContain('<svg')->toContain('<path')->not->toContain('<script');
    expect(WalletTransaction::count())->toBe(0)->and($payment->fresh()->payer_wallet_id)->toBeNull();
});

test('shareable link requires login and an unknown token cannot be guessed by numeric ID', function () {
    $payment = $this->service->requestPayment($this->recipient, null, '15', (string) Str::uuid());
    $this->get(route('billetera.enlaces.show', $payment->share_token))->assertRedirect(route('login'));
    $this->actingAs($this->sender)->get(route('billetera.enlaces.show', 'not-a-valid-token'))->assertNotFound();
    $this->actingAs($this->sender)->get(route('billetera.enlaces.show', $payment->id))->assertNotFound();
});

test('link is paid once by the confirmed payer and replay cannot charge twice', function () {
    $payment = $this->service->requestPayment($this->recipient, null, '15.25', (string) Str::uuid());
    $url = route('billetera.enlaces.pay', $payment->share_token);
    $this->actingAs($this->sender)->post($url, ['amount' => '0.01', 'requester_wallet_id' => $this->senderWallet->id])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($this->sender)->post($url)->assertRedirect()->assertSessionHasNoErrors();
    expect($payment->fresh()->status)->toBe('paid')->and((int) $payment->fresh()->payer_wallet_id)->toBe($this->senderWallet->id)
        ->and($this->senderWallet->fresh()->balance)->toBe('84.75')->and($this->recipientWallet->fresh()->balance)->toBe('15.25')
        ->and(WalletTransfer::count())->toBe(1)->and(WalletTransaction::count())->toBe(2);
    $outsider = User::withoutEvents(fn (): User => User::factory()->create());
    $outsider->givePermissionTo('finanzas.wallets.view');
    expect(fn () => $this->service->payLink($outsider, $payment))->toThrow(AuthorizationException::class);
});

test('insufficient funds cannot claim a link and another user can still pay it', function () {
    $payment = $this->service->requestPayment($this->sender, null, '5', (string) Str::uuid());
    expect(fn () => $this->service->payLink($this->recipient, $payment))->toThrow(ValidationException::class);
    expect($payment->fresh()->payer_wallet_id)->toBeNull()->and($payment->fresh()->status)->toBe('pending')->and(WalletTransaction::count())->toBe(0);
});

test('numeric request route cannot claim an unassigned link and owner cannot pay themselves', function () {
    $payment = $this->service->requestPayment($this->recipient, null, '5', (string) Str::uuid());
    $this->actingAs($this->sender)->postJson(route('billetera.solicitudes.resolve', $payment), ['decision' => 'accept'])->assertForbidden();
    expect(fn () => $this->service->payLink($this->recipient, $payment))->toThrow(ValidationException::class);
    expect(WalletTransaction::count())->toBe(0)->and($payment->fresh()->payer_wallet_id)->toBeNull();
});

test('cancelled payment link cannot collect money', function () {
    $payment = $this->service->requestPayment($this->recipient, null, '5', (string) Str::uuid());
    $this->service->resolvePayment($this->recipient, $payment, 'cancel');
    expect(fn () => $this->service->payLink($this->sender, $payment))->toThrow(AuthorizationException::class);
    expect(WalletTransaction::count())->toBe(0);
});

test('sharing a targeted request still restricts payment and details to its selected payer', function () {
    $payment = $this->service->requestPayment($this->recipient, $this->senderWallet->account_number, '5', (string) Str::uuid());
    $outsider = User::withoutEvents(fn (): User => User::factory()->create());
    $outsider->givePermissionTo('finanzas.wallets.view');
    $this->actingAs($outsider)->get(route('billetera.enlaces.show', $payment->share_token))->assertForbidden();
    $this->actingAs($outsider)->postJson(route('billetera.enlaces.pay', $payment->share_token))->assertForbidden();
    $this->actingAs($this->sender)->withHeader('X-Inertia', 'true')->get(route('billetera.enlaces.show', $payment->share_token))->assertOk();
});
