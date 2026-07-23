# Arquitectura Multi-Tenant

## Resumen

Este sistema utiliza una arquitectura **single database, shared tables, row-level tenant isolation**. No existe un modelo `Business` o `Company` separado — el modelo `User` (`users` table) actúa como la entidad raíz del tenant.

Una "empresa" o "negocio" está representada por un usuario raíz (`creator_id = null`) que posee datos de negocio almacenados directamente en la tabla `users`.

---

## Jerarquía de Usuarios

```
Master (level 0, creator_id = null)
  │
  ├── Super Admin (level 1, creator_id = Master.id)
  │     │
  │     ├── Administrador (level 2, creator_id = Super Admin.id)
  │     │     │
  │     │     ├── Empleado (level 3, creator_id = Administrador.id)
  │     │     ├── Cliente (level 3, creator_id = Administrador.id)
  │     │     ├── Proveedor (level 3, creator_id = Administrador.id)
  │     │     └── Usuario (level 3, creator_id = Administrador.id)
  │     │
  │     └── Otros Administradores...
  │
  └── Otros Super Admins...
```

- **Nivel 0 (Master)**: Dueño de la plataforma. Bypass total de permisos (`Gate::before`). Ve todos los datos.
- **Nivel 1 (Super Admin)**: Administrador global. Bypass total de permisos.
- **Nivel 2 (Administrador)**: Admin de negocio. Acceso completo excepto recursos sensibles del sistema.
- **Nivel 3 (Empleado, Cliente, Proveedor, Usuario)**: Usuarios finales. Acceso limitado según su rol.

---

## Propiedad de Datos: `owner_id`

Cada fila de datos perteneciente a un tenant tiene una columna `owner_id` que referencia `users.id`.

### `getOwnerId()` — Traversia Recursiva

El método `User::getOwnerId()` recorre recursivamente `creator_id` hacia arriba hasta encontrar el usuario raíz (`creator_id = null`):

```php
public function getOwnerId(): int
{
    if ($this->creator_id) {
        $creator = self::find($this->creator_id);
        return $creator ? $creator->getOwnerId() : $this->creator_id;
    }
    return $this->id;
}
```

### `BelongsToOwner` Trait

Trait aplicado a ~97 modelos que:
1. **Asigna automáticamente** `owner_id` al crear registros.
2. **Filtra globalmente** usando `OwnerScope`.

```php
trait BelongsToOwner
{
    protected static function bootBelongsToOwner(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && ! $model->owner_id) {
                $model->owner_id = Auth::user()->getOwnerId();
            }
        });
        static::addGlobalScope(new OwnerScope);
    }
}
```

### `OwnerScope` — Global Scope

```php
public function apply(Builder $builder, Model $model): void
{
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
            return; // Bypass para super usuarios
        }
        $builder->where($model->qualifyColumn('owner_id'), $user->getOwnerId());
    }
}
```

---

## Flujo de Aislamiento de Datos

```
Request → Middleware (auth) → Controller
                               │
                  ┌────────────┴────────────┐
                  │                         │
            Usa OwnerScope            Usa withoutGlobalScope
            (OwnerScope)              (OwnerScope::class)
                  │                         │
        Solo datos del tenant         Datos cross-tenant
        (automático)                  (marketplace, pagos)
```

### Bypass Intencional

Algunos controladores usan `withoutGlobalScope(OwnerScope::class)` para operaciones cross-tenant:

| Controlador | Motivo |
|---|---|
| `MarketplaceController` | Listar tiendas públicas |
| `PedidoController` | Clientes comprando en diferentes tiendas |
| `PayPalController` / `MercadoPagoController` | Procesar pagos entre tenants |
| `ErpSyncTrait` | Sincronización ERP entre tenant del vendedor |
| `ChatController` | Chat marketplace cross-tenant |

---

## Permisos y Roles

### Framework: Spatie Laravel Permission

- Modelo `Role` personalizado (`App\Models\Role`) con columna `level` para jerarquía.
- Permisos granulares con formato: `{module}.{submodule}.{action}`.
- Acciones: `viewAny`, `view`, `create`, `edit`, `delete`, `import`, `export`.

### Gate::before — Bypass Global

```php
Gate::before(function ($user, $ability) {
    if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
        return true;
    }
});
```

### Middleware de Permisos

- `CheckPermission` — Verifica permisos con soporte OR (pipe `|`).
- `CheckRole` — Verifica roles específicos.
- `CheckOwnership` — Verifica que el modelo pertenezca al tenant del usuario.
- `CheckActive` — Verifica que el usuario no esté suspendido.

### Permisos Financieros Granulares (Nuevos)

| Permiso | Descripción |
|---|---|
| `admin.finanzas.viewAny` | Panel general de finanzas |
| `admin.webpay-config.viewAny` / `.edit` | Configuración Webpay |
| `admin.paypal-config.viewAny` / `.edit` | Configuración PayPal |
| `admin.mercadopago-config.viewAny` / `.edit` | Configuración MercadoPago |
| `admin.transactions.viewAny` | Ver movimientos/transacciones |
| `admin.commissions.viewAny` / `.edit` | Comisiones |
| `admin.webhooks.viewAny` | Webhooks |

---

## Modelos Clave para Multi-Tenant

### User (users) — Entidad Raíz

| Campo | Propósito |
|---|---|
| `creator_id` | Jerarquía: quién creó este usuario |
| `business_name` | Nombre del negocio |
| `business_logo_path` | Logo del negocio |
| `primary_color` / `secondary_color` | Colores de marca |
| `razon_social` / `giro` / `rut` | Datos fiscales chilenos |
| `trial_ends_at` | Fin del período de prueba |
| `is_active` / `banned_at` | Estado de la cuenta |

### PaymentConfig (payment_configs) — Configuración de Pagos

| Campo | Propósito |
|---|---|
| `owner_id` | Tenant propietario |
| `commerce_code` / `api_key` | Credenciales Webpay |
| `paypal_client_id` / `paypal_client_secret` | Credenciales PayPal |
| `mercadopago_access_token` | Token MercadoPago |
| `use_platform_config` | Usar config del Master como fallback |
| `commission_rate` / `commission_type` | Configuración de comisión |
| `paypal_webhook_id` | ID para verificación de webhooks PayPal |
| `mercadopago_webhook_secret` | Secreto para verificación webhooks MP |

### Transaction (transactions) — Tabla Unificada (Nueva)

| Campo | Propósito |
|---|---|
| `uuid` | Identificador único |
| `business_id` | Tenant |
| `user_id` | Usuario que realizó el pago |
| `gateway` | webpay / paypal / mercadopago |
| `type` | subscription_payment / customer_payment / refund / etc. |
| `amount` / `fee` / `net_amount` | Desglose financiero |

### Commission (commissions) — Comisiones (Nueva)

| Campo | Propósito |
|---|---|
| `business_id` | Tenant |
| `transaction_id` | Transacción relacionada |
| `commission_type` | percentage / fixed / hybrid |
| `commission_rate` | Porcentaje o monto fijo |
| `commission_amount` | Monto calculado |

---

## Buenas Prácticas

### 1. Siempre usar `BelongsToOwner` trait

Para cualquier nuevo modelo que contenga datos de negocio:

```php
class NuevoModelo extends Model
{
    use BelongsToOwner;
    // ...
}
```

### 2. Usar `withoutGlobalScope` con precaución

Solo cuando sea estrictamente necesario (operaciones cross-tenant). Documentar el motivo.

### 3. Verificar ownership manualmente

Cuando se usa `withoutGlobalScope`, agregar verificación manual:

```php
$model = Modelo::withoutGlobalScope(OwnerScope::class)->findOrFail($id);
if ($model->owner_id !== auth()->user()->getOwnerId()) {
    abort(403);
}
```

### 4. Nuevos permisos

Siempre agregar nuevos sub-módulos en `PermissionsSeeder::getModuleDefinitions()`.

### 5. Middleware de ruta

Usar middleware combinado para rutas admin:

```php
Route::middleware(['permission:admin.nuevo-modulo.viewAny|admin.configuracion.viewAny'])
```

### 6. Datos del usuario como negocio

El `User` raíz contiene datos del negocio. Para acceder:

```php
$business = User::find($ownerId); // where creator_id = null
$businessName = $business->business_name;
```

---

## Diagrama de Relaciones (ERD)

```
users (Tenant Root)
  │
  ├── creator_id → self (jerarquía)
  │
  ├── payment_configs (owner_id)
  │     └── Configuración de pasarelas + comisiones
  │
  ├── transactions (business_id)
  │     ├── gateway, type, amount, fee, net_amount
  │     └── commissions (transaction_id)
  │           └── commission_type, rate, amount
  │
  ├── payment_sessions (business_id)
  │     └── Sesiones de pago persistentes (reemplaza Session)
  │
  ├── webpay_transactions (owner_id)
  │     └── Transacciones Webpay legacy
  │
  ├── pedidos (owner_id / business_id)
  │     └── Órdenes del marketplace
  │
  └── + 90+ modelos con BelongsToOwner
```

---

## Consideraciones de Seguridad

1. **Nunca exponer `owner_id` en URLs o APIs públicas.**
2. **Siempre validar ownership en operaciones de escritura.**
3. **Master/Super Admin bypass debe ser monitorizado.**
4. **Webhooks externos verifican firma antes de procesar.**
5. **Los webhooks NO usan OwnerScope — verifican origen por firma.**
6. **Las transacciones huérfanas se detectan con `php artisan payments:audit`.**
