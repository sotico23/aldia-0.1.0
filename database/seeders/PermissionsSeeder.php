<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /** @var list<string> */
    private const ACTIONS = ['viewAny', 'view', 'create', 'edit', 'delete', 'import', 'export'];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $modules = $this->getModuleDefinitions();

        // Generate all granular permissions
        $allPermissions = [];

        foreach ($modules as $module => $submodules) {
            foreach ($submodules as $submodule) {
                foreach (self::ACTIONS as $action) {
                    $allPermissions[] = "{$module}.{$submodule}.{$action}";
                }
            }
        }

        // Legacy / special permissions (backward compat + marketplace)
        $specialPermissions = [
            'ver dashboard',
            'acceso completo', // @deprecated — no references in code, kept for safety
            'hacer pedidos',
            'ver sus pedidos',
            'compras.viewOwn',
            'perfil.edit',
            'documentos.upload',
            // General-purpose permissions for community and tasks features
            'general.comunidad.create',
            'general.comunidad.edit',
            'general.comunidad.delete',
            'general.tareas.create',
            'general.tareas.edit',
            'general.tareas.delete',
            'admin.roles.custom',
            // Delivery - zona de repartidores
            'delivery.repartos.accept',
            'delivery.repartos.pickup',
            'delivery.repartos.deliver',
            // Public Profile / Tiendas management
            'admin.public-profiles.toggle-status',
            'admin.public-profiles.toggle-official',
        ];

        $allPermissions = array_merge($allPermissions, $specialPermissions);

        // Create all permissions
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // --- ROLE CREATION WITH LEVELS ---

        // 0. Master (Level 0) — full control
        $roleMaster = Role::firstOrCreate(['name' => 'Master'], ['owner_id' => null]);
        $roleMaster->level = 0;
        $roleMaster->save();
        $roleMaster->syncPermissions(Permission::all());

        // 1. Super Admin (Level 1) — full control via Gate::before
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'Super Admin'], ['owner_id' => null]);
        $roleSuperAdmin->level = 1;
        $roleSuperAdmin->save();
        $roleSuperAdmin->syncPermissions(Permission::all());

        // 2. Administrador (Level 2) — everything except admin-sensitive resources
        $roleAdmin = Role::firstOrCreate(['name' => 'Administrador'], ['owner_id' => null]);
        $roleAdmin->level = 2;
        $roleAdmin->save();

        $adminExcluded = [
            'admin.roles.create',
            'admin.roles.edit',
            'admin.roles.delete',
            'admin.web-settings.create',
            'admin.web-settings.edit',
            'admin.web-settings.delete',
            'admin.mail-templates.create',
            'admin.mail-templates.edit',
            'admin.mail-templates.delete',
            'admin.email-config.create',
            'admin.email-config.edit',
            'admin.email-config.delete',
        ];
        $roleAdmin->syncPermissions(
            Permission::whereNotIn('name', $adminExcluded)->get()
        );

        // Grant custom role management separately (not for system roles)
        $roleAdmin->givePermissionTo('admin.roles.custom');

        // 3. Empleado (Level 3) — base dashboard + assigned modules
        $roleEmpleado = Role::firstOrCreate(['name' => 'Empleado'], ['owner_id' => null]);
        $roleEmpleado->level = 3;
        $roleEmpleado->save();

        $empleadoPermissions = [
            'ver dashboard',
            // Comercial — view & create
            'comercial.prospectos.viewAny',
            'comercial.prospectos.view',
            'comercial.prospectos.create',
            'comercial.prospectos.edit',
            'comercial.oportunidades.viewAny',
            'comercial.oportunidades.view',
            'comercial.oportunidades.create',
            'comercial.oportunidades.edit',
            'comercial.clientes.viewAny',
            'comercial.clientes.view',
            'comercial.clientes.create',
            'comercial.clientes.edit',
            'comercial.productos.viewAny',
            'comercial.productos.view',
            'comercial.categorias.viewAny',
            'comercial.categorias.view',
            'comercial.categorias.create',
            'comercial.categorias.edit',
            'comercial.cotizaciones.viewAny',
            'comercial.cotizaciones.view',
            'comercial.cotizaciones.create',
            'comercial.cotizaciones.edit',
            'comercial.tickets.viewAny',
            'comercial.tickets.view',
            'comercial.tickets.create',
            // Inventario — view only
            'inventario.inventarios.viewAny',
            'inventario.inventarios.view',
            'inventario.almacenes.viewAny',
            'inventario.almacenes.view',
            'inventario.vacios.viewAny',
            'inventario.vacios.view',
            // Flota — view + entregas
            'flota.vehiculos.viewAny',
            'flota.vehiculos.view',
            'flota.conductores.viewAny',
            'flota.conductores.view',
            'flota.entregas.viewAny',
            'flota.entregas.view',
            'flota.entregas.create',
            'flota.entregas.edit',
            'flota.cargas.viewAny',
            'flota.cargas.view',
            'flota.grupos-trabajo.viewAny',
            'flota.grupos-trabajo.view',
            // Ventas (SFA)
            'ventas.ventas.viewAny',
            'ventas.ventas.view',
            // POS
            'ventas.pos.viewAny',
            'ventas.pos.view',
            'ventas.pos.create',
            'ventas.variantes.viewAny',
            'ventas.variantes.view',
            // Cupones
            'ventas.cupones.viewAny',
            'ventas.cupones.view',
            'ventas.cupones.create',
            'ventas.cupones.edit',
        ];
        $roleEmpleado->syncPermissions($empleadoPermissions);

        // 4. Cliente (Level 3) — marketplace only
        $roleCliente = Role::firstOrCreate(['name' => 'Cliente'], ['owner_id' => null]);
        $roleCliente->level = 3;
        $roleCliente->save();

        $clientePermissions = [
            'hacer pedidos',
            'ver sus pedidos',
            'comercial.productos.viewAny',
            'comercial.productos.view',
            'lms.cursos.viewAny',
            'lms.cursos.view',
            'lms.alumnos.viewAny',
            'lms.alumnos.view',
            'citas.citas.viewAny',
            'citas.citas.create',
        ];
        $roleCliente->syncPermissions($clientePermissions);

        // 5. Proveedor (Level 3) — supplier portal
        $roleProveedor = Role::firstOrCreate(['name' => 'Proveedor'], ['owner_id' => null]);
        $roleProveedor->level = 3;
        $roleProveedor->owner_id = null;
        $roleProveedor->save();

        $proveedorPermissions = [
            'compras.viewOwn',
            'perfil.edit',
            'documentos.upload',
        ];
        $roleProveedor->syncPermissions($proveedorPermissions);

        // 6. Usuario (Level 3) — trial / read-only after trial
        $roleUsuario = Role::firstOrCreate(['name' => 'Usuario'], ['owner_id' => null]);
        $roleUsuario->level = 3;
        $roleUsuario->save();

        $usuarioPermissions = Permission::where('name', 'like', '%.viewAny')
            ->orWhere('name', 'like', '%.view')
            ->orWhereIn('name', ['ver dashboard', 'ver sus pedidos', 'hacer pedidos'])
            ->pluck('name')
            ->toArray();

        $usuarioPermissions[] = 'comercial.categorias.create';
        $usuarioPermissions[] = 'comercial.categorias.edit';
        $roleUsuario->syncPermissions($usuarioPermissions);

        // 7. Repartidor (Level 3) — zona de reparto B2C
        $roleRepartidor = Role::firstOrCreate(['name' => 'Repartidor'], ['owner_id' => null]);
        $roleRepartidor->level = 3;
        $roleRepartidor->owner_id = null;
        $roleRepartidor->save();

        $repartidorPermissions = [
            'delivery.repartos.viewAny',
            'delivery.repartos.view',
            'delivery.repartos.accept',
            'delivery.repartos.pickup',
            'delivery.repartos.deliver',
        ];
        $roleRepartidor->syncPermissions($repartidorPermissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Define all modules and their submodules.
     *
     * @return array<string, list<string>>
     */
    private function getModuleDefinitions(): array
    {
        return [
            'comercial' => [
                'prospectos',
                'oportunidades',
                'clientes',
                'productos',
                'categorias',
                'cotizaciones',
                'campanas',
                'tickets',
                'call-center',
            ],
            'inventario' => [
                'proveedores',
                'compras',
                'inventarios',
                'almacenes',
                'lotes',
                'movimientos',
                'vacios',
            ],
            'mrp' => [
                'boms',
                'produccion',
                'calidad',
                'planificacion',
            ],
            'finanzas' => [
                'facturacion',
                'cobranzas',
                'pagos',
                'tesoreria',
                'contabilidad',
                'impuestos',
                'sii',
            ],
            'admin' => [
                'configuracion',
                'usuarios',
                'roles',
                'reportes',
                'web-settings',
                'mail-templates',
                'email-config',
                'finanzas',
                'webpay-config',
                'paypal-config',
                'mercadopago-config',
                'transactions',
                'commissions',
                'webhooks',
                'countries',
            ],
            'rrhh' => [
                'empleados',
                'nominas',
                'asistencia',
                'prestamos',
                'reclutamiento',
                'evaluaciones',
            ],
            'proyectos' => [
                'proyectos',
                'hitos',
                'timesheets',
                'gastos',
            ],
            'flota' => [
                'vehiculos',
                'conductores',
                'entregas',
                'cargas',
                'grupos-trabajo',
            ],
            'delivery' => [
                'repartos',
                'repartidores',
                'config',
            ],
            'ventas' => [
                'ventas',
                'pos',
                'variantes',
                'cupones',
            ],
            'lms' => [
                'cursos',
                'lecciones',
                'alumnos',
            ],
            'rifas' => [
                'rifas',
                'sorteos',
            ],
            'uptime' => [
                'monitores',
                'alertas',
            ],
            'sistema' => [
                'automatizaciones',
                'integraciones',
            ],
            'citas' => [
                'citas',
                'servicios',
            ],
        ];
    }
}
