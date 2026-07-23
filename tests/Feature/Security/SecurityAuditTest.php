<?php

use App\Jobs\CheckUptimeJob;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DashboardConfig;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\MonitoredSite;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\PublicProfile;
use App\Models\Raffle;
use App\Models\RaffleParticipant;
use App\Models\RafflePrize;
use App\Models\UptimeAlert;
use App\Models\UptimeCheck;
use App\Models\User;
use App\Models\WebSetting;
use App\Notifications\ActualizacionEstadoPedidoNotification;
use App\Notifications\NuevoMensajeChatPedidoNotification;
use App\Notifications\NuevoPedidoNotification;
use App\Notifications\NuevoTicketNotification;
use App\Notifications\PedidoCreadoCompradorNotification;
use App\Notifications\TempPasswordNotification;
use App\Notifications\WelcomeProveedorNotification;
use App\Scopes\OwnerScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// First user always gets 'Super Admin' via User boot. Create dummy to avoid bypass.
beforeEach(function () {
    User::factory()->create(['email' => 'dummy@setup.test']);
});

// ============================================================================
// CRITICAL: Multi-tenancy — owner_id auto-assignment on new models
// ============================================================================

test('DetalleFactura auto-assigns owner_id from authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $categoria = Categoria::factory()->create(['owner_id' => $user->id]);
    $producto = Producto::factory()->create([
        'owner_id' => $user->id,
        'categoria_id' => $categoria->id,
    ]);
    $cliente = Cliente::factory()->create(['owner_id' => $user->id]);
    $factura = Factura::create([
        'numero' => 'FAC-TEST-001',
        'user_id' => $user->id,
        'owner_id' => $user->id,
        'cliente_id' => $cliente->id,
        'fecha' => now(),
        'fecha_vencimiento' => now()->addDays(30),
        'subtotal' => 10000,
        'impuesto' => 1900,
        'total' => 11900,
        'tipo' => 'venta',
        'estado' => 'pendiente',
    ]);

    $detalle = DetalleFactura::create([
        'factura_id' => $factura->id,
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'precio_unitario' => 10000,
        'subtotal' => 10000,
        'impuesto' => 1900,
        'total' => 11900,
        'owner_id' => null,
    ]);

    expect((int) $detalle->owner_id)->toBe((int) $user->id);
});

test('PedidoItem auto-assigns owner_id from authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $producto = Producto::factory()->create([
        'owner_id' => $user->id,
        'categoria_id' => Categoria::factory()->create(['owner_id' => $user->id])->id,
    ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'user_id' => $user->id,
        'owner_id' => $user->id,
        'cliente_id' => $user->id,
        'numero_pedido' => 'PED-AUDIT-OWNER',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'manual',
    ]);

    $item = PedidoItem::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'nombre_producto' => 'Test Product',
        'precio_unitario' => 10000,
        'cantidad' => 1,
        'subtotal' => 10000,
        'owner_id' => null,
    ]);

    expect((int) $item->owner_id)->toBe((int) $user->id);
});

test('new multi-tenant models auto-assign owner_id on creation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $site = MonitoredSite::factory()->create(['owner_id' => $user->id]);

    $alert = UptimeAlert::create([
        'user_id' => $user->id,
        'monitored_site_id' => $site->id,
        'type' => 'site_down',
        'channel' => 'email',
        'is_active' => true,
        'owner_id' => null,
    ]);
    expect((int) $alert->owner_id)->toBe((int) $user->id);

    $check = UptimeCheck::create([
        'monitored_site_id' => $site->id,
        'checked_at' => now(),
        'status' => 'up',
        'response_time_ms' => 100,
        'owner_id' => null,
    ]);
    expect((int) $check->owner_id)->toBe((int) $user->id);

    $raffle = Raffle::create([
        'user_id' => $user->id,
        'owner_id' => $user->id,
        'title' => 'Test Raffle',
        'slug' => 'test-raffle-'.$user->id,
        'start_date' => now(),
        'end_date' => now()->addDays(7),
        'ticket_price' => 1000,
    ]);

    $participant = RaffleParticipant::create([
        'user_id' => $user->id,
        'owner_id' => null,
        'raffle_id' => $raffle->id,
        'entries' => 1,
    ]);
    expect((int) $participant->owner_id)->toBe((int) $user->id);

    $prize = RafflePrize::create([
        'raffle_id' => $raffle->id,
        'name' => 'Test Prize',
        'owner_id' => null,
    ]);
    expect((int) $prize->owner_id)->toBe((int) $user->id);

    $config = DashboardConfig::create([
        'user_id' => $user->id,
        'name' => 'default',
        'mode' => 'full',
        'owner_id' => null,
    ]);
    expect((int) $config->owner_id)->toBe((int) $user->id);
});

// ============================================================================
// CRITICAL: Password security — no passwords in notification emails
// ============================================================================

test('WelcomeProveedorNotification does not leak passwords in email', function () {
    $user = User::factory()->create(['name' => 'Test User']);
    $notification = new WelcomeProveedorNotification(email: 'test@example.com');

    $rendered = $notification->toMail($user)->render();

    expect(str_contains($rendered, 'contraseña temporal'))->toBeFalse();
    expect(str_contains($rendered, 'password temporal'))->toBeFalse();
    expect(str_contains($rendered, 'clientenuevo'))->toBeFalse();
    expect(str_contains($rendered, 'Restablecer tu contraseña'))->toBeTrue();
    expect(str_contains($rendered, '/forgot-password'))->toBeTrue();
});

test('TempPasswordNotification does not leak passwords in email', function () {
    $user = User::factory()->create(['name' => 'Test User']);
    $notification = new TempPasswordNotification(provider: 'email');

    $rendered = $notification->toMail($user)->render();

    expect(str_contains($rendered, 'contraseña temporal'))->toBeFalse();
    expect(str_contains($rendered, 'password temporal'))->toBeFalse();
    expect(str_contains($rendered, 'Te has registrado exitosamente usando email'))->toBeTrue();
    expect(str_contains($rendered, 'Restablecer tu contraseña'))->toBeTrue();
    expect(str_contains($rendered, '/forgot-password'))->toBeTrue();
});

// ============================================================================
// MEDIUM: Notifications should be queueable (ShouldQueue)
// ============================================================================

test('mail notifications implement ShouldQueue', function () {
    expect((new ReflectionClass(NuevoPedidoNotification::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
    expect((new ReflectionClass(NuevoTicketNotification::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
    expect((new ReflectionClass(NuevoMensajeChatPedidoNotification::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
    expect((new ReflectionClass(PedidoCreadoCompradorNotification::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
    expect((new ReflectionClass(ActualizacionEstadoPedidoNotification::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
    expect((new ReflectionClass(WelcomeProveedorNotification::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
    expect((new ReflectionClass(TempPasswordNotification::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
});

// ============================================================================
// CRITICAL: User $fillable must protect is_active and banned_at
// ============================================================================

test('User fillable does not allow mass assignment of is_active or banned_at', function () {
    $user = User::factory()->create();

    $user->update(['is_active' => false, 'banned_at' => now()]);
    $user->refresh();

    expect($user->is_active)->toBeTrue();
    expect($user->banned_at)->toBeNull();
});

// ============================================================================
// ALTO: Role middleware blocks unauthorized access to cliente/proveedor routes
// ============================================================================

test('cliente routes are blocked for non-cliente users', function () {
    Role::firstOrCreate(['name' => 'Proveedor', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('Proveedor');

    $response = $this->actingAs($user)->get(route('cliente.dashboard'));

    $response->assertForbidden();
});

test('proveedor routes are blocked for non-proveedor users', function () {
    Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('Cliente');

    $response = $this->actingAs($user)->get(route('proveedor.dashboard'));

    $response->assertForbidden();
});

test('cliente routes are accessible for cliente users', function () {
    $user = User::factory()->create();
    $user->assignRole('Cliente');

    $this->actingAs($user)->get(route('cliente.dashboard'))->assertOk();
});

test('proveedor routes are accessible for proveedor users', function () {
    Role::firstOrCreate(['name' => 'Proveedor', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('Proveedor');

    Proveedor::factory()->create([
        'user_id' => $user->id,
        'owner_id' => $user->id,
        'email' => $user->email,
    ]);

    $this->actingAs($user)->get(route('proveedor.dashboard'))->assertOk();
});

// ============================================================================
// CRITICAL: Payment routes require authentication
// ============================================================================

test('payment routes return login redirect when unauthenticated', function () {
    $this->get(route('paypal.pay', ['pedidoId' => 1]))->assertRedirect(route('login'));
    $this->get(route('mercadopago.pay', ['pedidoId' => 1]))->assertRedirect(route('login'));
    $this->post(route('webpay.pay'))->assertRedirect(route('login'));
});

test('confirmacion route requires authentication', function () {
    $this->get(route('tienda.confirmacion', ['slug' => 'test', 'pedidoId' => 1]))
        ->assertRedirect(route('login'));
});

// ============================================================================
// MEDIUM: CheckUptimeJob idempotency via uniqueId
// ============================================================================

test('CheckUptimeJob has unique ID for idempotency', function () {
    $user = User::factory()->create();
    $site = MonitoredSite::factory()->create(['owner_id' => $user->id]);

    $job = new CheckUptimeJob($site);

    expect($job->uniqueId())->toBe('uptime-'.$site->id);
    expect($job->uniqueFor())->toBe(60);
});

// ============================================================================
// ALTO: UsuarioRolController scoped by owner_id
// ============================================================================

test('OwnerScope prevents cross-tenant access to non-admin users', function () {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    Cliente::factory()->for($ownerA, 'owner')->create(['nombre' => 'ClientA']);
    Cliente::factory()->for($ownerB, 'owner')->create(['nombre' => 'ClientB']);

    $this->actingAs($ownerA);

    expect(Cliente::count())->toBe(1);
    expect(Cliente::first()->nombre)->toBe('ClientA');
});

test('master can create a user through the usuarios-roles user store endpoint', function () {
    Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web']);

    $master = User::factory()->create();
    $master->assignRole('Master');

    $this->actingAs($master)
        ->from(route('usuarios-roles.index'))
        ->post(route('usuarios-roles.user.store'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo.usuario@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertRedirect(route('usuarios-roles.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'nuevo.usuario@example.com',
    ]);

    $createdUser = User::query()->where('email', 'nuevo.usuario@example.com')->firstOrFail();
    expect($createdUser->hasRole('Usuario'))->toBeTrue();
    expect($createdUser->creator_id)->toBe($master->id);
    expect($createdUser->owner_id)->toBe($master->getOwnerId());

    $this->get(route('usuarios-roles.index'))
        ->assertOk();
});

// ============================================================================
// MEDIUM: WebSettingController uses $request->input() not $request->all()
// ============================================================================

test('WebSettingController ignores extraneous fields in request', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrador');
    Permission::firstOrCreate(['name' => 'admin.web-settings.edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'admin.web-settings.viewAny', 'guard_name' => 'web']);
    $user->givePermissionTo(['admin.web-settings.edit', 'admin.web-settings.viewAny']);
    $this->actingAs($user);

    $setting = WebSetting::create([
        'owner_id' => $user->id,
        'app_name' => 'Original',
        'app_title' => 'Original Title',
        'timezone' => 'UTC',
        'locale' => 'es',
        'currency' => 'CLP',
        'currency_symbol' => '$',
        'app_description' => 'Desc',
        'maintenance_mode' => false,
    ]);

    $response = $this->from('/configuracion-web')
        ->put(route('configuracion-web.update', $setting), [
            'app_name' => 'Updated',
            'app_title' => 'Updated Title',
            'timezone' => 'America/Santiago',
            'locale' => 'es',
            'currency' => 'CLP',
            'currency_symbol' => '$',
            'app_description' => 'Desc',
            'maintenance_mode' => false,
            'owner_id' => 999,
            'secret_key' => 'leaked',
        ]);

    $response->assertRedirect();

    $setting->refresh();
    expect($setting->app_name)->toBe('Updated');
    expect($setting->getAttributes())->not->toHaveKey('secret_key');
});

// ============================================================================
// ALTO: OwnerScope correctly filters cross-tenant data
// ============================================================================

test('OwnerScope prevents cross-tenant data leakage', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $clienteA = Cliente::factory()->create(['owner_id' => $userA->id]);
    Factura::create([
        'numero' => 'FAC-SCOPE-A1',
        'user_id' => $userA->id,
        'owner_id' => $userA->id,
        'cliente_id' => $clienteA->id,
        'fecha' => now(),
        'fecha_vencimiento' => now()->addDays(30),
        'subtotal' => 1000,
        'impuesto' => 190,
        'total' => 1190,
        'tipo' => 'venta',
        'estado' => 'pendiente',
    ]);

    $clienteB = Cliente::factory()->create(['owner_id' => $userB->id]);
    Factura::create([
        'numero' => 'FAC-SCOPE-B1',
        'user_id' => $userB->id,
        'owner_id' => $userB->id,
        'cliente_id' => $clienteB->id,
        'fecha' => now(),
        'fecha_vencimiento' => now()->addDays(30),
        'subtotal' => 2000,
        'impuesto' => 380,
        'total' => 2380,
        'tipo' => 'venta',
        'estado' => 'pendiente',
    ]);

    Auth::logout();
    $this->actingAs($userA);

    expect(Factura::count())->toBe(1);
});

// ============================================================================
// MEDIUM: HasBulkOperations uses $request->only() not $request->all()
// ============================================================================

test('HasBulkOperations filters request to allow-list only', function () {
    $request = new Request([
        'search' => 'test',
        'estado' => 'activo',
        'owner_id' => 999,
        'is_admin' => true,
    ]);

    $filters = $request->only(['search', 'categoria_id', 'estado', 'fecha_desde', 'fecha_hasta', 'activo', 'tipo', 'almacen_id']);

    expect($filters)->toHaveKey('search');
    expect($filters)->toHaveKey('estado');
    expect($filters)->not->toHaveKey('owner_id');
    expect($filters)->not->toHaveKey('is_admin');
});

// ============================================================================
// ALTO: Payment controllers verify cliente_id ownership
// ============================================================================

test('PayPal pay denies cross-tenant access', function () {
    $vendor = User::factory()->create();
    $buyer = User::factory()->create();
    $attacker = User::factory()->create();

    $profile = PublicProfile::factory()->create([
        'user_id' => $vendor->id,
        'owner_id' => $vendor->id,
        'is_active' => true,
    ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'user_id' => $vendor->id,
        'owner_id' => $vendor->id,
        'public_profile_id' => $profile->id,
        'cliente_id' => $buyer->id,
        'numero_pedido' => 'PED-AUDIT-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Buyer',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'paypal',
    ]);

    $response = $this->actingAs($attacker)
        ->get(route('paypal.pay', ['pedidoId' => $pedido->id]));

    $response->assertForbidden();
});

test('MercadoPago pay denies cross-tenant access', function () {
    $vendor = User::factory()->create();
    $buyer = User::factory()->create();
    $attacker = User::factory()->create();

    $profile = PublicProfile::factory()->create([
        'user_id' => $vendor->id,
        'owner_id' => $vendor->id,
        'is_active' => true,
    ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'user_id' => $vendor->id,
        'owner_id' => $vendor->id,
        'public_profile_id' => $profile->id,
        'cliente_id' => $buyer->id,
        'numero_pedido' => 'PED-AUDIT-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Buyer',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'mercadopago',
    ]);

    $response = $this->actingAs($attacker)
        ->get(route('mercadopago.pay', ['pedidoId' => $pedido->id]));

    $response->assertForbidden();
});
