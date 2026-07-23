<?php

namespace App\Helpers;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionHelper
{
    /**
     * Mapeo de Módulos Técnicos a Grupos de Sidebar (Cards)
     *
     * Formato: 'prefijo.tecnico' => 'Grupo Sidebar:Subgrupo'
     * Si no hay subgrupo, solo 'Grupo Sidebar'
     */
    private static array $sidebarModuleMap = [
        // SISTEMA (Administración)
        'admin.usuarios' => 'SISTEMA:Usuarios y Roles',
        'admin.roles' => 'SISTEMA:Usuarios y Roles',
        'admin.configuracion' => 'SISTEMA:Configuración Web',
        'admin.web-settings' => 'SISTEMA:Configuración Web',
        'admin.countries' => 'SISTEMA:Países y Monedas',
        'admin.reportes' => 'SISTEMA:Reportes',
        'admin.mail-templates' => 'SISTEMA:Email Marketing',
        'admin.email-config' => 'SISTEMA:Config. Correo',
        'admin.webhooks' => 'SISTEMA:Automatizaciones',
        'admin.finanzas' => 'SISTEMA:Automatizaciones',
        'admin.webpay-config' => 'SISTEMA:Automatizaciones',
        'admin.paypal-config' => 'SISTEMA:Automatizaciones',
        'admin.mercadopago-config' => 'SISTEMA:Automatizaciones',
        'admin.transactions' => 'SISTEMA:Automatizaciones',
        'admin.commissions' => 'SISTEMA:Automatizaciones',
        'sistema.automatizaciones' => 'SISTEMA:Automatizaciones',

        // COMERCIAL (Gestión Comercial)
        'comercial.categorias' => 'COMERCIAL:Fundamental',
        'comercial.productos' => 'COMERCIAL:Fundamental',
        'comercial.clientes' => 'COMERCIAL:Fundamental',
        'comercial.prospectos' => 'COMERCIAL:CRM & Ventas',
        'comercial.oportunidades' => 'COMERCIAL:CRM & Ventas',
        'comercial.cotizaciones' => 'COMERCIAL:CRM & Ventas',
        'comercial.campanas' => 'COMERCIAL:CRM & Ventas',
        'comercial.call-center' => 'COMERCIAL:CRM & Ventas',
        'comercial.tickets' => 'COMERCIAL:CRM & Ventas',

        // OPERACIONES (Operaciones e Inventario)
        'inventario.inventarios' => 'OPERACIONES:Inventario',
        'inventario.almacenes' => 'OPERACIONES:Almacenes',
        'inventario.movimientos' => 'OPERACIONES:Movimientos',
        'inventario.lotes' => 'OPERACIONES:Lotes y Series',
        'inventario.proveedores' => 'OPERACIONES:Proveedores',
        'inventario.compras' => 'OPERACIONES:Órdenes de Compra',
        'inventario.vacios' => 'OPERACIONES:Vacíos',

        // MRP (Producción)
        'mrp.boms' => 'MRP:BOM (Materiales)',
        'mrp.produccion' => 'MRP:Órdenes Producción',
        'mrp.calidad' => 'MRP:Control Calidad',
        'mrp.planificacion' => 'MRP:Planificación',

        // FINANZAS Y FACTURACIÓN (Facturación)
        'finanzas.facturacion' => 'FINANZAS Y FACTURACIÓN:Facturación (AR)',
        'finanzas.cobranzas' => 'FINANZAS Y FACTURACIÓN:Cobranzas',
        'finanzas.pagos' => 'FINANZAS Y FACTURACIÓN:Pagos (AP)',
        'finanzas.contabilidad' => 'FINANZAS Y FACTURACIÓN:Contabilidad (GL)',
        'finanzas.impuestos' => 'FINANZAS Y FACTURACIÓN:Impuestos',
        'finanzas.tesoreria' => 'FINANZAS Y FACTURACIÓN:Tesorería',

        // PAGOS EN LÍNEA
        'finanzas.webpay-config' => 'PAGOS EN LÍNEA:Webpay',
        'finanzas.paypal-config' => 'PAGOS EN LÍNEA:PayPal',
        'finanzas.mercadopago-config' => 'PAGOS EN LÍNEA:MercadoPago',
        'finanzas.webpay-movimientos' => 'PAGOS EN LÍNEA:Movimientos',
        'finanzas.plataforma-pago' => 'PAGOS EN LÍNEA:Pago Plataforma',
        'ventas.cupones' => 'PAGOS EN LÍNEA:Cupones',

        // GESTIÓN HUMANA
        'rrhh.empleados' => 'GESTIÓN HUMANA:Empleados',
        'rrhh.nominas' => 'GESTIÓN HUMANA:Nómina',
        'rrhh.asistencia' => 'GESTIÓN HUMANA:Asistencia',
        'rrhh.prestamos' => 'GESTIÓN HUMANA:Préstamos y Adelantos',
        'rrhh.reclutamiento' => 'GESTIÓN HUMANA:Reclutamiento',
        'rrhh.evaluaciones' => 'GESTIÓN HUMANA:Evaluaciones',

        // PROYECTOS (PMS)
        'proyectos.proyectos' => 'PROYECTOS:Proyectos',
        'proyectos.hitos' => 'PROYECTOS:Hitos y Tareas',
        'proyectos.timesheets' => 'PROYECTOS:Timesheets',
        'proyectos.gastos' => 'PROYECTOS:Gastos Proyecto',

        // LOGÍSTICA
        'flota.vehiculos' => 'LOGÍSTICA:Vehículos',
        'flota.conductores' => 'LOGÍSTICA:Conductores',
        'flota.entregas' => 'LOGÍSTICA:Entregas',
        'flota.cargas' => 'LOGÍSTICA:Cargas Diarias / Rutas',
        'flota.grupos-trabajo' => 'LOGÍSTICA:Grupos de Trabajo',

        // PUNTO DE VENTA (POS)
        'ventas.pos' => 'PUNTO DE VENTA (POS):Terminal POS',
        'ventas.variantes' => 'PUNTO DE VENTA (POS):Variantes / SKUs',

        // CITAS Y RESERVAS
        'citas.citas' => 'CITAS Y RESERVAS:Citas',
        'citas.servicios' => 'CITAS Y RESERVAS:Servicios',

        // PLATAFORMA DE APRENDIZAJE (LMS)
        'lms.cursos' => 'PLATAFORMA DE APRENDIZAJE:Cursos',
        'lms.lecciones' => 'PLATAFORMA DE APRENDIZAJE:Lecciones',
        'lms.alumnos' => 'PLATAFORMA DE APRENDIZAJE:Alumnos',

        // MARKETING (unificado: Campañas + Rifas + Email)
        'comercial.campanas' => 'MARKETING:Campañas',
        'rifas.rifas' => 'MARKETING:Rifas y Sorteos',
        'rifas.sorteos' => 'MARKETING:Rifas y Sorteos',
        'admin.mail-templates' => 'MARKETING:Email Marketing',
        'admin.email-config' => 'MARKETING:Config. Correo',

        // MONITOREO
        'uptime.monitores' => 'MONITOREO:Monitores',
        'uptime.alertas' => 'MONITOREO:Alertas',

        // GESTIÓN SII (DTE) - desde sidebar derecho
        'finanzas.sii' => 'GESTIÓN SII (DTE):Configuración SII',

        // MARKETPLACE - desde sidebar derecho
        'comercial.oportunidades' => 'MARKETPLACE:Mis Ventas',
        'ventas.cupones' => 'MARKETPLACE:Cupones',

        // RECOMPENSAS - desde sidebar derecho
        // No hay permisos técnicos específicos, usar general

        // AUTOMATIZACIONES - desde sidebar derecho
        'sistema.automatizaciones' => 'AUTOMATIZACIONES:Canales',
    ];

    /**
     * Orden visual de las Cards en el frontend
     */
    private static array $sidebarCardOrder = [
        'SISTEMA',
        'COMERCIAL',
        'OPERACIONES',
        'MRP',
        'FINANZAS Y FACTURACIÓN',
        'PAGOS EN LÍNEA',
        'GESTIÓN HUMANA',
        'PROYECTOS',
        'LOGÍSTICA',
        'PUNTO DE VENTA (POS)',
        'CITAS Y RESERVAS',
        'PLATAFORMA DE APRENDIZAJE',
        'MARKETING',
        'MONITOREO',
        'GESTIÓN SII (DTE)',
        'MARKETPLACE',
        'RECOMPENSAS',
        'AUTOMATIZACIONES',
        'OTROS / PERMISOS TRANSVERSALES',
    ];

    /**
     * Mapeo de Acciones del CRUD a nombres amigables
     */
    public static array $actionMap = [
        'viewAny' => 'Ver Listado',
        'view' => 'Ver Detalle',
        'create' => 'Crear / Agregar',
        'edit' => 'Editar / Modificar',
        'delete' => 'Eliminar',
        'import' => 'Carga Masiva (Importar)',
        'export' => 'Descargar Reportes (Exportar)',
    ];

    /**
     * Genera un nombre amigable a partir del nombre técnico (ej: comercial.clientes.create)
     */
    public static function getFriendlyName(string $permissionName): string
    {
        $parts = explode('.', $permissionName);

        // Nombres legacy (ej: "ver dashboard") o mal formados
        if (count($parts) !== 3) {
            return ucfirst($permissionName);
        }

        [$modulo, $submodulo, $accion] = $parts;

        $friendlyAction = self::$actionMap[$accion] ?? ucfirst($accion);

        return "{$friendlyAction}";
    }

    /**
     * Obtiene el grupo y subgrupo de sidebar para un permiso dado
     */
    private static function getSidebarGroup(string $permissionName): array
    {
        $parts = explode('.', $permissionName);
        $prefix = count($parts) >= 2 ? "{$parts[0]}.{$parts[1]}" : $permissionName;

        // Buscar coincidencia exacta del prefijo
        if (isset(self::$sidebarModuleMap[$prefix])) {
            $mapped = self::$sidebarModuleMap[$prefix];
            if (str_contains($mapped, ':')) {
                [$group, $subgroup] = explode(':', $mapped, 2);

                return [$group, $subgroup];
            }

            return [$mapped, 'General'];
        }

        // Buscar coincidencia solo por módulo (primer parte)
        $modulo = $parts[0] ?? '';
        foreach (self::$sidebarModuleMap as $key => $value) {
            if (str_starts_with($key, $modulo.'.')) {
                if (str_contains($value, ':')) {
                    [$group, $subgroup] = explode(':', $value, 2);

                    return [$group, $subgroup];
                }

                return [$value, 'General'];
            }
        }

        // Fallback: permisos legacy/huérfanos
        return ['OTROS / PERMISOS TRANSVERSALES', 'Otros'];
    }

    /**
     * Devuelve toda la estructura de permisos agrupada por Cards de Sidebar
     */
    public static function getGroupedPermissionsBySidebar(): array
    {
        $permissions = Permission::get();

        return self::buildGroupedBySidebar($permissions);
    }

    /**
     * Devuelve permisos agrupados por Sidebar filtrados por lo que el usuario puede asignar
     */
    public static function getGroupedPermissionsForUserBySidebar(User $user): array
    {
        $userPermissionIds = $user->getAllPermissions()->pluck('id')->toArray();

        $permissions = Permission::whereIn('id', $userPermissionIds)->get();

        return self::buildGroupedBySidebar($permissions);
    }

    /**
     * Construye la estructura agrupada por Cards de Sidebar
     */
    private static function buildGroupedBySidebar($permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            [$group, $subgroup] = self::getSidebarGroup($permission->name);

            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            if (! isset($grouped[$group][$subgroup])) {
                $grouped[$group][$subgroup] = [];
            }

            $grouped[$group][$subgroup][] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'friendly_name' => self::getFriendlyName($permission->name),
            ];
        }

        // Ordenar subgrupos dentro de cada grupo alfabéticamente
        foreach ($grouped as $group => $subgroups) {
            ksort($grouped[$group]);
            foreach ($subgroups as $subgroup => $perms) {
                usort($grouped[$group][$subgroup], fn ($a, $b) => strcmp($a['name'], $b['name']));
            }
        }

        // Ordenar grupos según $sidebarCardOrder
        uksort($grouped, function ($a, $b) {
            $posA = array_search($a, self::$sidebarCardOrder);
            $posB = array_search($b, self::$sidebarCardOrder);
            if ($posA === false && $posB === false) {
                return strcmp($a, $b);
            }
            if ($posA === false) {
                return 1;
            }
            if ($posB === false) {
                return -1;
            }

            return $posA - $posB;
        });

        return $grouped;
    }

    /**
     * Mantener compatibilidad: métodos legacy que usan la estructura antigua
     */
    public static function getGroupedPermissions(): array
    {
        return self::getGroupedPermissionsBySidebar();
    }

    public static function getGroupedPermissionsForUser(User $user): array
    {
        return self::getGroupedPermissionsForUserBySidebar($user);
    }

    public static function getGroupedPermissionsFiltered(array $allowedIds): array
    {
        $permissions = Permission::whereIn('id', $allowedIds)->get();

        return self::buildGroupedBySidebar($permissions);
    }
}
