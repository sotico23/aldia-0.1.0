<?php

use App\Models\Cupon;
use App\Models\User;
use Spatie\Permission\Models\Permission;

// ============================================================================
// Helper: asignar permisos de cupones a un usuario
// ============================================================================
function giveCuponPermissions(User $user, array $actions = ['viewAny', 'view', 'create', 'edit', 'delete']): void
{
    $permissions = array_map(fn ($a) => Permission::firstOrCreate(['name' => "ventas.cupones.{$a}"]), $actions);
    $user->givePermissionTo($permissions);
}

// ============================================================================
// Helpers: crear usuarios sin rol automático
// ============================================================================

function createUserWithoutRole(): User
{
    $user = User::factory()->create();
    $user->syncRoles([]);

    return $user;
}

// ============================================================================
// CRUD Básico
// ============================================================================

test('lista cupones requiere autenticacion', function () {
    $this->get(route('ventas.cupones.index'))->assertRedirect(route('login'));
});

test('lista cupones requiere permiso', function () {
    $user = createUserWithoutRole();

    $this->actingAs($user)
        ->get(route('ventas.cupones.index'))
        ->assertForbidden();
});

test('lista cupones con permiso', function () {
    $user = createUserWithoutRole();
    giveCuponPermissions($user, ['viewAny']);
    Cupon::factory()->count(3)->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('ventas.cupones.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/Cupones/Index')
            ->has('cupones.data', 3)
        );
});

test('crear cupon requiere permiso create', function () {
    $user = createUserWithoutRole();
    giveCuponPermissions($user, ['viewAny']);

    $this->actingAs($user)
        ->post(route('ventas.cupones.store'), [
            'codigo' => 'TEST-001',
            'tipo' => 'porcentaje',
            'valor' => 10,
        ])
        ->assertForbidden();
});

test('puede crear cupon con permiso', function () {
    $user = createUserWithoutRole();
    giveCuponPermissions($user);

    $this->actingAs($user)
        ->post(route('ventas.cupones.store'), [
            'codigo' => 'WELCOME10',
            'tipo' => 'porcentaje',
            'valor' => 10,
            'descripcion' => 'Descuento de bienvenida',
            'max_usos' => 100,
            'usos_por_cliente' => 1,
            'compra_minima' => 5000,
            'activa' => true,
        ])
        ->assertRedirect(route('ventas.cupones.index'));

    $this->assertDatabaseHas('cupones', [
        'codigo' => 'WELCOME10',
        'owner_id' => $user->id,
        'user_id' => $user->id,
    ]);
});

test('crear cupon con codigo duplicado falla', function () {
    $user = User::factory()->create();
    giveCuponPermissions($user);
    Cupon::factory()->create(['codigo' => 'UNICO', 'owner_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('ventas.cupones.store'), [
            'codigo' => 'UNICO',
            'tipo' => 'porcentaje',
            'valor' => 10,
        ])
        ->assertSessionHasErrors('codigo');
});

test('puede actualizar cupon', function () {
    $user = User::factory()->create();
    giveCuponPermissions($user);
    $cupon = Cupon::factory()->create([
        'owner_id' => $user->id,
        'valor' => 10,
    ]);

    $this->actingAs($user)
        ->put(route('ventas.cupones.update', $cupon), [
            'valor' => 20,
        ])
        ->assertRedirect(route('ventas.cupones.index'));

    expect($cupon->fresh()->valor)->toEqual(20.0);
});

test('puede eliminar cupon', function () {
    $user = User::factory()->create();
    giveCuponPermissions($user);
    $cupon = Cupon::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('ventas.cupones.destroy', $cupon))
        ->assertRedirect(route('ventas.cupones.index'));

    expect(Cupon::find($cupon->id))->toBeNull();
});

test('puede toggle activa/inactiva', function () {
    $user = User::factory()->create();
    giveCuponPermissions($user);
    $cupon = Cupon::factory()->create([
        'owner_id' => $user->id,
        'activa' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('ventas.cupones.toggle', $cupon))
        ->assertRedirect(route('ventas.cupones.index'));

    expect($cupon->fresh()->activa)->toBeTrue();
});

// ============================================================================
// Validación del cupón (método de modelo)
// ============================================================================

test('cupon activo y vigente es valido', function () {
    $cupon = Cupon::factory()->active()->create([
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
    ]);

    expect($cupon->validar())->toBeTrue();
});

test('cupon inactivo no es valido', function () {
    $cupon = Cupon::factory()->inactive()->create();

    expect($cupon->validar())->toBeFalse();
});

test('cupon expirado no es valido', function () {
    $cupon = Cupon::factory()->expirado()->active()->create();

    expect($cupon->validar())->toBeFalse();
});

test('cupon sin usos restantes no es valido', function () {
    $cupon = Cupon::factory()->sinUsos()->active()->create();

    expect($cupon->validar())->toBeFalse();
});

test('cupon con compra minima no valida monto menor', function () {
    $cupon = Cupon::factory()->active()->create([
        'compra_minima' => 10000,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'max_usos' => 0,
    ]);

    expect($cupon->validar(5000))->toBeFalse();
    expect($cupon->validar(15000))->toBeTrue();
});

// ============================================================================
// Cálculo de descuento
// ============================================================================

test('calcular descuento porcentaje', function () {
    $cupon = Cupon::factory()->porcentaje()->create(['valor' => 25]);

    expect($cupon->calcularDescuento(20000))->toEqual(5000.0);
});

test('calcular descuento precio fijo', function () {
    $cupon = Cupon::factory()->precioFijo()->create(['valor' => 3000]);

    expect($cupon->calcularDescuento(10000))->toEqual(3000.0);
});

test('calcular descuento precio fijo no supera el monto', function () {
    $cupon = Cupon::factory()->precioFijo()->create(['valor' => 15000]);

    expect($cupon->calcularDescuento(10000))->toEqual(10000.0);
});

test('calcular descuento envio gratis es 0', function () {
    $cupon = Cupon::factory()->create(['tipo' => 'envio_gratis', 'valor' => 0]);

    expect($cupon->calcularDescuento(20000))->toEqual(0.0);
});

// ============================================================================
// Canje (incrementar usos)
// ============================================================================

test('canjear cupon valido incrementa usos', function () {
    $cupon = Cupon::factory()->active()->create([
        'usos_actuales' => 0,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'max_usos' => 10,
        'compra_minima' => null,
    ]);

    $result = $cupon->canjear();

    expect($result)->toBeTrue();
    expect($cupon->fresh()->usos_actuales)->toEqual(1);
});

test('canjear cupon invalido no incrementa usos', function () {
    $cupon = Cupon::factory()->sinUsos()->active()->create(['usos_actuales' => 5]);

    $result = $cupon->canjear();

    expect($result)->toBeFalse();
    expect($cupon->fresh()->usos_actuales)->toEqual(5);
});

// ============================================================================
// Endpoint público de validación
// ============================================================================

test('validar cupon endpoint — cupon valido', function () {
    $user = User::factory()->create();
    Cupon::factory()->active()->create([
        'codigo' => 'VERANO',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'compra_minima' => null,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'max_usos' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('cupones.validar'), [
            'codigo' => 'VERANO',
            'monto' => 20000,
        ])
        ->assertSuccessful()
        ->assertJson([
            'valido' => true,
            'codigo' => 'VERANO',
        ]);
});

test('validar cupon endpoint — cupon invalido por monto minimo', function () {
    $user = User::factory()->create();
    Cupon::factory()->active()->create([
        'codigo' => 'MINIMO',
        'compra_minima' => 50000,
    ]);

    $this->actingAs($user)
        ->post(route('cupones.validar'), [
            'codigo' => 'MINIMO',
            'monto' => 10000,
        ])
        ->assertSuccessful()
        ->assertJson(['valido' => false]);
});

test('validar cupon endpoint — cupon no encontrado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('cupones.validar'), [
            'codigo' => 'NOEXISTE',
            'monto' => 10000,
        ])
        ->assertSuccessful()
        ->assertJson(['valido' => false, 'mensaje' => 'Cupón no encontrado.']);
});

test('validar cupon endpoint requiere autenticacion', function () {
    $this->post(route('cupones.validar'), [
        'codigo' => 'TEST',
        'monto' => 10000,
    ])->assertRedirect(route('login'));
});

// ============================================================================
// Renderizado de plantilla
// ============================================================================

test('renderizar plantilla reemplaza variables', function () {
    $cupon = Cupon::factory()->create([
        'plantilla_html' => '<p>Código: {{codigo}} - Valor: {{valor}} - Tipo: {{tipo}}</p>',
    ]);

    $html = $cupon->renderizarPlantilla([
        'codigo' => 'HOLA',
        'valor' => '15%',
        'tipo' => 'Porcentaje',
    ]);

    expect($html)->toBe('<p>Código: HOLA - Valor: 15% - Tipo: Porcentaje</p>');
});

test('renderizar plantilla sin variables deja los placeholders intactos', function () {
    $cupon = Cupon::factory()->create(['plantilla_html' => '<p>{{codigo}} - {{noexiste}}</p>']);

    $html = $cupon->renderizarPlantilla(['codigo' => 'TEST']);

    expect($html)->toContain('TEST');
    expect($html)->toContain('{{noexiste}}');
});

// ============================================================================
// Multi-tenancy (OwnerScope)
// ============================================================================

test('usuario solo ve sus propios cupones', function () {
    $ownerA = createUserWithoutRole();
    $ownerB = createUserWithoutRole();
    giveCuponPermissions($ownerA, ['viewAny']);
    giveCuponPermissions($ownerB, ['viewAny']);

    Cupon::factory()->create(['codigo' => 'A-001', 'owner_id' => $ownerA->id]);
    Cupon::factory()->create(['codigo' => 'B-001', 'owner_id' => $ownerB->id]);

    $this->actingAs($ownerA)
        ->get(route('ventas.cupones.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/Cupones/Index')
            ->has('cupones.data', 1)
            ->where('cupones.data.0.codigo', 'A-001')
        );
});

// ============================================================================
// Vista previa del cupón
// ============================================================================

test('preview endpoint devuelve html', function () {
    $user = createUserWithoutRole();
    giveCuponPermissions($user);
    $cupon = Cupon::factory()->create([
        'owner_id' => $user->id,
        'plantilla_html' => '<h1>{{codigo}}</h1>',
        'variables_ejemplo' => ['codigo' => 'PREVIEW'],
    ]);

    $this->actingAs($user)
        ->get(route('ventas.cupones.preview', $cupon))
        ->assertSuccessful()
        ->assertJson(['html' => '<h1>PREVIEW</h1>']);
});
