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
        // COMERCIAL (Gestión Comercial)
        'comercial.categorias' => 'COMERCIAL:Fundamental',
        'comercial.productos' => 'COMERCIAL:Fundamental',
        'comercial.clientes' => 'COMERCIAL:Fundamental',
        'inventario.almacenes' => 'COMERCIAL:Fundamental',
        'comercial.prospectos' => 'COMERCIAL:CRM & Ventas',
        'comercial.oportunidades' => 'COMERCIAL:CRM & Ventas',
        'comercial.cotizaciones' => 'COMERCIAL:CRM & Ventas',
        'comercial.call-center' => 'COMERCIAL:CRM & Ventas',
        'comercial.tickets' => 'COMERCIAL:CRM & Ventas',
        'comercial.campanas' => 'COMERCIAL:CRM & Ventas',
        'ventas.ventas' => 'COMERCIAL:CRM & Ventas',

        // OPERACIONES (Operaciones e Inventario)
        'inventario.inventarios' => 'OPERACIONES:Inventario',
        'inventario.movimientos' => 'OPERACIONES:Inventario',
        'inventario.lotes' => 'OPERACIONES:Inventario',
        'inventario.proveedores' => 'OPERACIONES:Inventario',
        'inventario.compras' => 'OPERACIONES:Inventario',
        'inventario.vacios' => 'OPERACIONES:Inventario',

        // OPERACIONES (Producción MRP)
        'mrp.boms' => 'OPERACIONES:Producción',
        'mrp.produccion' => 'OPERACIONES:Producción',
        'mrp.calidad' => 'OPERACIONES:Producción',
        'mrp.planificacion' => 'OPERACIONES:Producción',

        // OPERACIONES (Monitoreo)
        'uptime.monitores' => 'OPERACIONES:Monitoreo',
        'uptime.alertas' => 'OPERACIONES:Monitoreo',

        // FACTURACIÓN
        'finanzas.facturacion' => 'FACTURACIÓN:Facturación',
        'finanzas.cobranzas' => 'FACTURACIÓN:Cobranzas',
        'finanzas.pagos' => 'FACTURACIÓN:Pagos',
        'finanzas.contabilidad' => 'FACTURACIÓN:Contabilidad',
        'finanzas.impuestos' => 'FACTURACIÓN:Impuestos',

        // FINANZAS (Pagos en Línea)
        'finanzas.tesoreria' => 'FINANZAS:Tesorería',
        'finanzas.sii' => 'FINANZAS:SII (DTE)',
        'admin.finanzas' => 'FINANZAS:Configuración',
        'admin.webpay-config' => 'FINANZAS:Webpay',
        'admin.paypal-config' => 'FINANZAS:PayPal',
        'admin.mercadopago-config' => 'FINANZAS:MercadoPago',
        'admin.transactions' => 'FINANZAS:Movimientos',
        'admin.commissions' => 'FINANZAS:Comisiones',
        'ventas.cupones' => 'FINANZAS:Cupones',

        // RRHH (Gestión Humana)
        'rrhh.empleados' => 'RRHH:Empleados',
        'rrhh.nominas' => 'RRHH:Nómina',
        'rrhh.asistencia' => 'RRHH:Asistencia',
        'rrhh.prestamos' => 'RRHH:Préstamos y Adelantos',
        'rrhh.reclutamiento' => 'RRHH:Reclutamiento',
        'rrhh.evaluaciones' => 'RRHH:Evaluaciones',

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

        // TIENDA (Punto de Venta)
        'ventas.pos' => 'TIENDA:Terminal POS',
        'ventas.variantes' => 'TIENDA:Variantes / SKUs',

        // SERVICIOS (Citas y Reservas)
        'citas.citas' => 'SERVICIOS:Citas',
        'citas.servicios' => 'SERVICIOS:Servicios',

        // EDUCACIÓN (Plataforma de Aprendizaje)
        'lms.cursos' => 'EDUCACIÓN:Cursos',
        'lms.lecciones' => 'EDUCACIÓN:Lecciones',
        'lms.alumnos' => 'EDUCACIÓN:Alumnos',

        // MARKETING
        'admin.mail-templates' => 'MARKETING:Email Marketing',
        'admin.email-config' => 'MARKETING:Config. Correo',
        'rifas.rifas' => 'MARKETING:Rifas y Sorteos',
        'rifas.sorteos' => 'MARKETING:Rifas y Sorteos',

        // SISTEMA (Administración)
        'admin.usuarios' => 'SISTEMA:Usuarios y Roles',
        'admin.roles' => 'SISTEMA:Usuarios y Roles',
        'admin.configuracion' => 'SISTEMA:Configuración Web',
        'admin.web-settings' => 'SISTEMA:Configuración Web',
        'admin.countries' => 'SISTEMA:Países y Monedas',
        'admin.reportes' => 'SISTEMA:Reportes',
        'admin.webhooks' => 'SISTEMA:Automatizaciones',
        'sistema.automatizaciones' => 'SISTEMA:Automatizaciones',
    ];

    /**
     * Orden visual de las Cards en el frontend (debe coincidir con el Sidebar)
     */
    private static array $sidebarCardOrder = [
        'COMERCIAL',
        'OPERACIONES',
        'FACTURACIÓN',
        'FINANZAS',
        'RRHH',
        'PROYECTOS',
        'LOGÍSTICA',
        'TIENDA',
        'SERVICIOS',
        'EDUCACIÓN',
        'MARKETING',
        'SISTEMA',
        'OTROS / PERMISOS TRANSVERSALES',
    ];

    /**
     * Orden de subgrupos dentro de cada módulo (debe coincidir con el Sidebar)
     */
    private static array $subgroupOrder = [
        'COMERCIAL' => ['Fundamental', 'CRM & Ventas'],
        'OPERACIONES' => ['Inventario', 'Producción', 'Monitoreo'],
        'FACTURACIÓN' => ['Facturación', 'Cobranzas', 'Pagos', 'Contabilidad', 'Impuestos'],
        'FINANZAS' => ['Configuración', 'Webpay', 'PayPal', 'MercadoPago', 'Movimientos', 'Comisiones', 'SII (DTE)', 'Tesorería', 'Cupones'],
        'RRHH' => ['Empleados', 'Nómina', 'Asistencia', 'Préstamos y Adelantos', 'Reclutamiento', 'Evaluaciones'],
        'PROYECTOS' => ['Proyectos', 'Hitos y Tareas', 'Timesheets', 'Gastos Proyecto'],
        'LOGÍSTICA' => ['Vehículos', 'Conductores', 'Entregas', 'Cargas Diarias / Rutas', 'Grupos de Trabajo'],
        'TIENDA' => ['Terminal POS', 'Variantes / SKUs'],
        'SERVICIOS' => ['Citas', 'Servicios'],
        'EDUCACIÓN' => ['Cursos', 'Lecciones', 'Alumnos'],
        'MARKETING' => ['Email Marketing', 'Config. Correo', 'Rifas y Sorteos'],
        'SISTEMA' => ['Usuarios y Roles', 'Configuración Web', 'Países y Monedas', 'Reportes', 'Automatizaciones'],
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

        // Formatear el recurso: reemplazar guiones/underscores por espacios y capitalizar
        $formattedResource = ucwords(str_replace(['-', '_'], ' ', $submodulo));

        return "{$friendlyAction} {$formattedResource}";
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

        // Ordenar subgrupos según $subgroupOrder
        foreach ($grouped as $group => $subgroups) {
            $orderedSubgroups = self::$subgroupOrder[$group] ?? [];
            $sortedSubgroups = [];

            foreach ($orderedSubgroups as $subgroupName) {
                if (isset($grouped[$group][$subgroupName])) {
                    $sortedSubgroups[$subgroupName] = $grouped[$group][$subgroupName];
                }
            }

            foreach ($grouped[$group] as $subgroupName => $perms) {
                if (! isset($sortedSubgroups[$subgroupName])) {
                    $sortedSubgroups[$subgroupName] = $perms;
                }
            }

            $grouped[$group] = $sortedSubgroups;

            foreach ($sortedSubgroups as $subgroup => $perms) {
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
     * Devuelve permisos agrupados por Módulo → Subgrupo → Recurso → Permisos
     * Estructura de 3 niveles para el modal de edición de roles
     */
    public static function getGroupedPermissionsByModuleSubgroupResource(): array
    {
        $permissions = Permission::get();

        return self::buildGroupedByModuleSubgroupResource($permissions);
    }

    /**
     * Devuelve permisos agrupados por Módulo → Subgrupo → Recurso filtrados por usuario
     */
    public static function getGroupedPermissionsForUserByModuleSubgroupResource(User $user): array
    {
        $userPermissionIds = $user->getAllPermissions()->pluck('id')->toArray();

        $permissions = Permission::whereIn('id', $userPermissionIds)->get();

        return self::buildGroupedByModuleSubgroupResource($permissions);
    }

    /**
     * Construye la estructura agrupada por Módulo → Subgrupo → Recurso → Permisos
     */
    private static function buildGroupedByModuleSubgroupResource($permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            [$group, $subgroup] = self::getSidebarGroup($permission->name);
            $resource = self::getResourceName($permission->name);

            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            if (! isset($grouped[$group][$subgroup])) {
                $grouped[$group][$subgroup] = [];
            }

            if (! isset($grouped[$group][$subgroup][$resource])) {
                $grouped[$group][$subgroup][$resource] = [];
            }

            $grouped[$group][$subgroup][$resource][] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'friendly_name' => self::getFriendlyName($permission->name),
            ];
        }

        // Ordenar subgrupos según $subgroupOrder y recursos alfabéticamente
        foreach ($grouped as $group => $subgroups) {
            $orderedSubgroups = self::$subgroupOrder[$group] ?? [];
            $sortedSubgroups = [];

            // Primero los subgrupos en el orden definido
            foreach ($orderedSubgroups as $subgroupName) {
                if (isset($grouped[$group][$subgroupName])) {
                    $sortedSubgroups[$subgroupName] = $grouped[$group][$subgroupName];
                }
            }

            // Luego los subgrupos no definidos en el orden (alfabéticamente)
            foreach ($grouped[$group] as $subgroupName => $resources) {
                if (! isset($sortedSubgroups[$subgroupName])) {
                    $sortedSubgroups[$subgroupName] = $resources;
                }
            }

            $grouped[$group] = $sortedSubgroups;

            foreach ($sortedSubgroups as $subgroup => $resources) {
                ksort($grouped[$group][$subgroup]);
                foreach ($resources as $resource => $perms) {
                    usort($grouped[$group][$subgroup][$resource], fn ($a, $b) => strcmp($a['name'], $b['name']));
                }
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
     * Extrae el nombre legible del recurso a partir del nombre técnico del permiso
     * Ej: "comercial.call-center.create" → "Call Center"
     */
    private static function getResourceName(string $permissionName): string
    {
        $parts = explode('.', $permissionName);

        if (count($parts) >= 2) {
            return ucwords(str_replace(['-', '_'], ' ', $parts[1]));
        }

        return 'General';
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
