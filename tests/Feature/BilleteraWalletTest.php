<?php

use App\Models\CommissionRule;
use App\Models\Empleado;
use App\Models\User;
use App\Models\Venta;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $this->master = User::withoutEvents(fn (): User => User::factory()->create(['has_explicit_role' => true]));
    Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web'], ['level' => 0, 'owner_id' => null]);
    $this->master->assignRole('Master');
    foreach (['finanzas.wallets.viewAny', 'finanzas.wallets.create', 'finanzas.wallets.edit'] as $permission) {
        $this->master->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    $this->service = app(WalletService::class);

    $this->cashier = crearCajeroWallet($this->master);
});

/**
 * Crea un cajero operativo (Empleado) con su billetera activa.
 */
function crearCajeroWallet(User $owner, array $attributes = []): User
{
    Role::firstOrCreate(['name' => 'Empleado', 'guard_name' => 'web']);

    $cajero = User::factory()->create([
        'creator_id' => $owner->id,
        'has_explicit_role' => true,
        ...$attributes,
    ]);

    $cajero->syncRoles(['Empleado']);
    $cajero->givePermissionTo(Permission::firstOrCreate(['name' => 'finanzas.wallets.view', 'guard_name' => 'web']));

    Wallet::firstOrCreate([
        'user_id' => $cajero->id,
    ], [
        'balance' => 0,
        'currency' => 'CLP',
        'status' => 'active',
        'allow_overdraft' => false,
    ]);

    return $cajero->fresh();
}

/**
 * Obtiene (o crea) la billetera activa de un cajero.
 */
function crearWalletCajero(User $cajero): Wallet
{
    return Wallet::firstOrCreate([
        'user_id' => $cajero->id,
    ], [
        'balance' => 0,
        'currency' => 'CLP',
        'status' => 'active',
        'allow_overdraft' => false,
    ]);
}

/**
 * Crea una venta pagada del cajero con el total indicado.
 *
 * @param  array<string, mixed>  $attributes
 */
function crearVentaPagada(User $cashier, User $tenant, float $total, array $attributes = []): Venta
{
    return Venta::withoutEvents(fn (): Venta => Venta::factory()->create([
        'owner_id' => $tenant->id,
        'user_id' => $cashier->id,
        'estado' => 'pagada',
        'fecha' => now()->toDateString(),
        'subtotal' => $total,
        'iva' => 0,
        'total' => $total,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        'es_pos' => true,
        ...$attributes,
    ]));
}

test('el master recarga la billetera del cajero y registra el movimiento en el ledger', function () {
    $wallet = crearWalletCajero($this->cashier);

    $transaction = $this->service->rechargeFromMaster($wallet, 50000, $this->master, 'Recarga semanal del cierre');

    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe('credit')
        ->and($transaction->category)->toBe('master_recharge')
        ->and((float) $transaction->amount)->toBe(50000.0)
        ->and((float) $transaction->balance_before)->toBe(0.0)
        ->and((float) $transaction->balance_after)->toBe(50000.0)
        ->and((float) $wallet->fresh()->balance)->toBe(50000.0)
        ->and($transaction->created_by)->toBe($this->master->id);

    $this->assertDatabaseHas('wallet_transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'master_recharge',
        'amount' => 50000,
        'balance_before' => 0,
        'balance_after' => 50000,
        'created_by' => $this->master->id,
    ]);
});

test('solo un master o super admin puede administrar las billeteras', function () {
    $wallet = crearWalletCajero($this->cashier);

    expect($this->service->puedeGestionarBilleteras($this->cashier))->toBeFalse()
        ->and($this->service->puedeGestionarBilleteras($this->master))->toBeTrue();

    expect(fn () => $this->service->rechargeFromMaster($wallet, 1000, $this->cashier, 'No permitido'))
        ->toThrow(AuthorizationException::class);
});

test('la recarga de billetera rechaza montos invalidos', function () {
    $wallet = crearWalletCajero($this->cashier);

    expect(fn () => $this->service->rechargeFromMaster($wallet, 0, $this->master, 'Monto invalido'))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->service->rechargeFromMaster($wallet, -100, $this->master, 'Monto invalido'))
        ->toThrow(ValidationException::class);

    expect((float) $wallet->fresh()->balance)->toBe(0.0);
});

test('la recarga de una billetera congelada falla sin mover el saldo', function () {
    $wallet = crearWalletCajero($this->cashier);
    $wallet->update(['status' => 'frozen']);

    expect(fn () => $this->service->rechargeFromMaster($wallet, 1000, $this->master, 'Congelada'))
        ->toThrow(RuntimeException::class);

    expect((float) $wallet->fresh()->balance)->toBe(0.0)
        ->and(WalletTransaction::count())->toBe(0);
});

test('la comision por porcentaje se acredita en la billetera del cajero al pagar la venta', function () {
    $regla = CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'percentage',
        'commission_value' => 10,
        'min_sale_amount' => 0,
        'is_active' => true,
    ]);

    $wallet = crearWalletCajero($this->cashier);
    $sale = crearVentaPagada($this->cashier, $this->master, 100000);

    $transaction = $this->service->calculateAndCreditCommission($sale);

    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe('credit')
        ->and($transaction->category)->toBe('sale_commission')
        ->and((float) $transaction->amount)->toBe(10000.0)
        ->and((float) $transaction->balance_before)->toBe(0.0)
        ->and((float) $transaction->balance_after)->toBe(10000.0)
        ->and((float) $wallet->fresh()->balance)->toBe(10000.0)
        ->and($transaction->reference_id)->toBe($sale->id);

    $this->assertDatabaseHas('wallet_transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'sale_commission',
        'amount' => 10000,
        'reference_type' => Venta::class,
        'reference_id' => $sale->id,
        'created_by' => $this->cashier->id,
    ]);
});

test('la comision fija se acredita con el monto definido de la regla', function () {
    CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'fixed',
        'commission_value' => 2500,
        'min_sale_amount' => 0,
        'is_active' => true,
    ]);

    $wallet = crearWalletCajero($this->cashier);
    $sale = crearVentaPagada($this->cashier, $this->master, 100000);

    $transaction = $this->service->calculateAndCreditCommission($sale);

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->amount)->toBe(2500.0)
        ->and((float) $wallet->fresh()->balance)->toBe(2500.0);
});

test('la regla general se sobreescribe por la comision especifica del cajero', function () {
    CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'min_sale_amount' => 0,
        'is_active' => true,
    ]);

    CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => $this->cashier->id,
        'commission_type' => 'percentage',
        'commission_value' => 20,
        'min_sale_amount' => 0,
        'is_active' => true,
    ]);

    $wallet = crearWalletCajero($this->cashier);
    $sale = crearVentaPagada($this->cashier, $this->master, 100000);

    $transaction = $this->service->calculateAndCreditCommission($sale);

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->amount)->toBe(20000.0)
        ->and((float) $wallet->fresh()->balance)->toBe(20000.0);
});

test('la comision no se acredita cuando la venta no supera el monto minimo de la regla', function () {
    CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'percentage',
        'commission_value' => 10,
        'min_sale_amount' => 50000,
        'is_active' => true,
    ]);

    $wallet = crearWalletCajero($this->cashier);
    $sale = crearVentaPagada($this->cashier, $this->master, 30000);

    expect($this->service->calculateAndCreditCommission($sale))->toBeNull()
        ->and((float) $wallet->fresh()->balance)->toBe(0.0)
        ->and(WalletTransaction::count())->toBe(0);
});

test('la comision no se acredita cuando la venta no esta pagada', function () {
    CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'percentage',
        'commission_value' => 10,
        'min_sale_amount' => 0,
        'is_active' => true,
    ]);

    $wallet = crearWalletCajero($this->cashier);
    $sale = Venta::factory()->create([
        'owner_id' => $this->master->id,
        'user_id' => $this->cashier->id,
        'estado' => 'pendiente',
        'fecha' => now()->toDateString(),
        'subtotal' => 100000,
        'iva' => 0,
        'total' => 100000,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        'es_pos' => true,
    ]);

    expect($this->service->calculateAndCreditCommission($sale))->toBeNull()
        ->and(WalletTransaction::count())->toBe(0);
});

test('la comision solo se acredita una vez por venta (idempotencia)', function () {
    CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'percentage',
        'commission_value' => 10,
        'min_sale_amount' => 0,
        'is_active' => true,
    ]);

    $wallet = crearWalletCajero($this->cashier);
    $sale = crearVentaPagada($this->cashier, $this->master, 100000);

    $first = $this->service->calculateAndCreditCommission($sale);
    $second = $this->service->calculateAndCreditCommission($sale);

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and((float) $wallet->fresh()->balance)->toBe(10000.0)
        ->and(WalletTransaction::query()
            ->where('reference_type', Venta::class)
            ->where('reference_id', $sale->id)
            ->where('category', 'sale_commission')
            ->where('type', 'credit')
            ->count())->toBe(1);
});

test('la cancelacion de una venta pagada revierte la comision en la billetera del cajero', function () {
    CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'percentage',
        'commission_value' => 10,
        'min_sale_amount' => 0,
        'is_active' => true,
    ]);

    $wallet = crearWalletCajero($this->cashier);
    $sale = crearVentaPagada($this->cashier, $this->master, 100000);

    $this->service->calculateAndCreditCommission($sale);
    expect((float) $wallet->fresh()->balance)->toBe(10000.0);

    $sale->update(['estado' => 'cancelada']);

    $this->assertDatabaseHas('wallet_transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'debit',
        'category' => 'refund',
        'amount' => 10000,
        'balance_before' => 10000,
        'balance_after' => 0,
        'reference_type' => Venta::class,
        'reference_id' => $sale->id,
    ]);

    expect((float) $wallet->fresh()->balance)->toBe(0.0);
});

test('una venta pagada creada desde el pos acredita la comision automaticamente', function () {
    CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'percentage',
        'commission_value' => 8,
        'min_sale_amount' => 0,
        'is_active' => true,
    ]);

    $wallet = crearWalletCajero($this->cashier);

    $sale = Venta::factory()->create([
        'owner_id' => $this->master->id,
        'user_id' => $this->cashier->id,
        'estado' => 'pagada',
        'fecha' => now()->toDateString(),
        'subtotal' => 50000,
        'iva' => 0,
        'total' => 50000,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        'es_pos' => true,
    ]);

    expect((float) $wallet->fresh()->balance)->toBe(4000.0)
        ->and(WalletTransaction::query()
            ->where('reference_type', Venta::class)
            ->where('reference_id', $sale->id)
            ->where('category', 'sale_commission')
            ->exists())->toBeTrue();
});

test('una venta reembolsada revierte la comision automaticamente', function () {
    CommissionRule::create([
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'fixed',
        'commission_value' => 3000,
        'min_sale_amount' => 0,
        'is_active' => true,
    ]);

    $wallet = crearWalletCajero($this->cashier);

    $sale = Venta::factory()->create([
        'owner_id' => $this->master->id,
        'user_id' => $this->cashier->id,
        'estado' => 'pagada',
        'fecha' => now()->toDateString(),
        'subtotal' => 20000,
        'iva' => 0,
        'total' => 20000,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        'es_pos' => true,
    ]);

    expect((float) $wallet->fresh()->balance)->toBe(3000.0);

    $sale->update(['estado' => 'reembolsada']);

    expect((float) $wallet->fresh()->balance)->toBe(0.0)
        ->and(WalletTransaction::query()
            ->where('reference_type', Venta::class)
            ->where('reference_id', $sale->id)
            ->where('category', 'refund')
            ->where('type', 'debit')
            ->exists())->toBeTrue();
});

test('el master recarga una billetera desde el panel y registra la transaccion', function () {
    $wallet = crearWalletCajero($this->cashier);

    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->actingAs($this->master)
        ->post(route('billeteras.recargas', $wallet), [
            'amount' => 25000,
            'note' => 'Recarga manual del cierre',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('wallet_transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'master_recharge',
        'amount' => 25000,
        'created_by' => $this->master->id,
    ]);

    expect((float) $wallet->fresh()->balance)->toBe(25000.0);
});

test('el master puede ver el panel de billeteras', function () {
    $wallet = crearWalletCajero($this->cashier);
    $this->service->rechargeFromMaster($wallet, 5000, $this->master, 'Recarga de prueba');

    $this->actingAs($this->master)
        ->get(route('billeteras.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backend/Wallets/Index')
            ->has('wallets', 1)
            ->has('commission_rules')
            ->where('transacciones.meta.total', 1)
            ->where('transacciones.data.0.wallet.user.name', $this->cashier->name)
            ->missing('transacciones.data.0.wallet.user.password')
            ->missing('transacciones.data.0.wallet.user.remember_token'));
});

test('un cajero solo puede ver su propia billetera y no el panel master', function () {
    $wallet = crearWalletCajero($this->cashier);
    $this->service->rechargeFromMaster($wallet, 30000, $this->master, 'Recarga inicial');

    $this->actingAs($this->cashier)
        ->get(route('cajero.billetera'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backend/Pos/CajeroWallet')
            ->where('wallet.id', $wallet->id)
            ->where('wallet.balance', 30000));

    $this->actingAs($this->cashier)
        ->get(route('billeteras.index'))
        ->assertForbidden();
});

test('la billetera del cajero se crea automaticamente al abrir su panel', function () {
    $this->cashier->wallet()->delete();

    $this->actingAs($this->cashier)
        ->get(route('cajero.billetera'))
        ->assertOk();

    expect(Wallet::query()->where('user_id', $this->cashier->id)->exists())->toBeTrue();
});

test('el panel del cajero filtra el historial por rango de fechas', function () {
    $wallet = crearWalletCajero($this->cashier);

    $this->service->rechargeFromMaster($wallet, 10000, $this->master, 'Recarga de ayer');
    WalletTransaction::query()->latest('id')->first()->forceFill(['created_at' => now()->subDays(5)])->save();

    $this->service->rechargeFromMaster($wallet, 20000, $this->master, 'Recarga de hoy');

    $this->actingAs($this->cashier)
        ->get(route('cajero.billetera', [
            'desde' => now()->subDay()->toDateString(),
            'hasta' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('transacciones.data', 1)
            ->where('transacciones.data.0.description', 'Recarga de hoy'));
});

test('el master crea y actualiza reglas de comision desde el panel', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->actingAs($this->master)
        ->post(route('billeteras.commission-rules.store'), [
            'commission_type' => 'percentage',
            'commission_value' => 12,
            'min_sale_amount' => 5000,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('commission_rules', [
        'tenant_id' => $this->master->id,
        'user_id' => null,
        'commission_type' => 'percentage',
        'commission_value' => 12,
        'min_sale_amount' => 5000,
        'is_active' => true,
    ]);

    $this->actingAs($this->master)
        ->post(route('billeteras.commission-rules.store'), [
            'commission_type' => 'fixed',
            'commission_value' => 1500,
            'min_sale_amount' => 0,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect(CommissionRule::query()->where('tenant_id', $this->master->id)->whereNull('user_id')->count())->toBe(1)
        ->and((float) CommissionRule::query()->where('tenant_id', $this->master->id)->first()->commission_value)->toBe(1500.0);
});

test('el master crea una regla especifica para un cajero', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->actingAs($this->master)
        ->post(route('billeteras.commission-rules.store'), [
            'user_id' => $this->cashier->id,
            'commission_type' => 'percentage',
            'commission_value' => 25,
            'min_sale_amount' => 0,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('commission_rules', [
        'tenant_id' => $this->master->id,
        'user_id' => $this->cashier->id,
        'commission_value' => 25,
    ]);
});

test('un administrador de comercio solo ve las billeteras de su propio tenant', function () {
    Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web'], ['level' => 2, 'owner_id' => null]);

    $admin = User::withoutEvents(fn (): User => User::factory()->create(['has_explicit_role' => true]));
    $admin->syncRoles(['Administrador']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'finanzas.wallets.viewAny', 'guard_name' => 'web']));

    $propio = crearCajeroWallet($this->master);
    $propio->forceFill(['owner_id' => $admin->id, 'creator_id' => $admin->id])->save();

    $ajeno = crearCajeroWallet($this->master);
    $ajeno->forceFill(['owner_id' => $this->master->id, 'creator_id' => $this->master->id])->save();

    $this->actingAs($admin)
        ->get(route('billeteras.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backend/Wallets/Index')
            ->has('wallets', 1)
            ->where('wallets.0.user_id', $propio->id)
            ->where('tenant_id', $admin->id));
});

test('el modal de recarga lista cajeros aunque todavia no tengan billetera', function () {
    $cajero = User::withoutEvents(fn (): User => User::factory()->create([
        'creator_id' => $this->master->id,
        'owner_id' => $this->master->id,
        'has_explicit_role' => true,
    ]));

    Empleado::factory()->create([
        'user_id' => $cajero->id,
        'owner_id' => $this->master->id,
        'creator_id' => $this->master->id,
    ]);

    expect(Wallet::query()->where('user_id', $cajero->id)->exists())->toBeFalse();

    $this->actingAs($this->master)
        ->get(route('billeteras.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backend/Wallets/Index')
            ->where('cajeros', fn ($cajeros) => collect($cajeros)->contains(
                fn (array $opcion) => $opcion['user_id'] === $cajero->id
                    && $opcion['wallet_id'] === null
                    && $opcion['name'] === $cajero->name
            )));
});

test('el master recarga a un cajero sin billetera y la billetera se crea automaticamente', function () {
    $cajero = User::withoutEvents(fn (): User => User::factory()->create([
        'creator_id' => $this->master->id,
        'owner_id' => $this->master->id,
        'has_explicit_role' => true,
    ]));

    Empleado::factory()->create([
        'user_id' => $cajero->id,
        'owner_id' => $this->master->id,
        'creator_id' => $this->master->id,
    ]);

    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->actingAs($this->master)
        ->post(route('billeteras.recargas.cajero', $cajero), [
            'amount' => 18000,
            'note' => 'Primera recarga del cajero',
        ])
        ->assertRedirect();

    $wallet = Wallet::query()->where('user_id', $cajero->id)->first();

    expect($wallet)->not->toBeNull()
        ->and((float) $wallet->balance)->toBe(18000.0);

    $this->assertDatabaseHas('wallet_transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'category' => 'master_recharge',
        'amount' => 18000,
        'created_by' => $this->master->id,
    ]);
});

test('el seeder de permisos registra billeteras y las asigna a los roles base', function () {
    $this->seed(PermissionsSeeder::class);

    foreach (['viewAny', 'view', 'create', 'edit', 'delete', 'import', 'export'] as $action) {
        expect(Permission::where('name', "finanzas.wallets.{$action}")->exists())
            ->toBeTrue("Missing permission finanzas.wallets.{$action}");
    }

    expect(Role::where('name', 'Empleado')->first()->hasPermissionTo('finanzas.wallets.view'))->toBeTrue()
        ->and(Role::where('name', 'Administrador')->first()->hasPermissionTo('finanzas.wallets.viewAny'))->toBeTrue()
        ->and(Role::where('name', 'Master')->first()->hasPermissionTo('finanzas.wallets.create'))->toBeTrue();
});

test('la configuracion web devuelve la regla general de comision por defecto', function () {
    $this->actingAs($this->master)
        ->getJson(route('configuracion-web.wallet-settings'))
        ->assertOk()
        ->assertJsonPath('data.exists', false)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.commission_type', 'percentage')
        ->assertJsonPath('data.commission_value', 0)
        ->assertJsonPath('data.min_sale_amount', 0);
});

test('el master configura la comision general de billeteras desde configuracion web', function () {
    $this->actingAs($this->master)
        ->putJson(route('configuracion-web.wallet-settings.update'), [
            'commission_type' => 'percentage',
            'commission_value' => 12.5,
            'min_sale_amount' => 1000,
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.exists', true)
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('commission_rules', [
        'tenant_id' => $this->master->getOwnerId(),
        'user_id' => null,
        'commission_type' => 'percentage',
        'commission_value' => 12.5,
        'min_sale_amount' => 1000,
        'is_active' => true,
    ]);

    $this->actingAs($this->master)
        ->getJson(route('configuracion-web.wallet-settings'))
        ->assertOk()
        ->assertJsonPath('data.commission_type', 'percentage')
        ->assertJsonPath('data.commission_value', 12.5)
        ->assertJsonPath('data.min_sale_amount', 1000)
        ->assertJsonPath('data.is_active', true);
});

test('guardar la configuracion general reutiliza la misma regla del tenant', function () {
    foreach ([10, 20] as $porcentaje) {
        $this->actingAs($this->master)
            ->putJson(route('configuracion-web.wallet-settings.update'), [
                'commission_type' => 'percentage',
                'commission_value' => $porcentaje,
                'min_sale_amount' => 0,
                'is_active' => true,
            ])
            ->assertOk();
    }

    $reglas = CommissionRule::query()
        ->where('tenant_id', $this->master->getOwnerId())
        ->whereNull('user_id')
        ->get();

    expect($reglas)->toHaveCount(1)
        ->and((float) $reglas->first()->commission_value)->toBe(20.0);
});

test('la configuracion de billeteras exige permiso de edicion de configuracion web', function () {
    Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web'], ['level' => 2, 'owner_id' => null]);

    $admin = User::withoutEvents(fn (): User => User::factory()->create(['has_explicit_role' => true]));
    $admin->syncRoles(['Administrador']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.web-settings.viewAny', 'guard_name' => 'web']));
    Permission::firstOrCreate(['name' => 'admin.web-settings.edit', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->putJson(route('configuracion-web.wallet-settings.update'), [
            'commission_type' => 'fixed',
            'commission_value' => 500,
            'min_sale_amount' => 0,
            'is_active' => true,
        ])
        ->assertForbidden();

    expect(CommissionRule::query()->whereNull('user_id')->count())->toBe(0);
});
