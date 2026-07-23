import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    Building2,
    Calendar,
    ClipboardList,
    CreditCard,
    FileText,
    Gift,
    GraduationCap,
    Globe,
    Lock,
    Megaphone,
    ShoppingCart,
    Truck,
    Users,
    UsersRound,
    Wrench,
} from 'lucide-react';
import { dashboard } from '@/routes';

export type HrefType = NonNullable<InertiaLinkProps['href']>;

export type NavItem = {
    title: string;
    href: HrefType;
    icon?: LucideIcon | null;
    isActive?: boolean;
    group?: string;
    permission?: string | string[];
    items?: NavItem[];
};

export type ExtendedNavItem = NavItem & {
    permission?: string | string[];
    items?: ExtendedNavItem[];
};

const buildRouteUrl = (path: string): HrefType => path;

export const adminNavItems = (): ExtendedNavItem[] => [
    {
        title: 'Administración',
        group: 'SISTEMA',
        href: '#sistema',
        icon: Lock,
        permission: [
            'admin.usuarios.viewAny',
            'admin.roles.viewAny',
            'admin.configuracion.viewAny',
            'admin.web-settings.viewAny',
            'admin.countries.viewAny',
            'admin.reportes.viewAny',
            'sistema.automatizaciones.viewAny',
        ],
        items: [
            {
                title: 'Usuarios y Roles',
                href: buildRouteUrl('/usuarios-roles'),
                permission: ['admin.usuarios.viewAny'],
            },
            {
                title: 'Configuración Web',
                href: buildRouteUrl('/configuracion-web'),
                permission: ['admin.configuracion.viewAny', 'admin.web-settings.viewAny'],
            },
            {
                title: 'Países y Monedas',
                href: buildRouteUrl('/paises'),
                icon: Globe,
                permission: ['admin.countries.viewAny'],
            },
        ],
    },
];

export const mainNavItems: ExtendedNavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard.url(),
        icon: Building2,
    },
    {
        title: 'Gestión Comercial',
        group: 'COMERCIAL',
        href: '#comercial',
        icon: Users,
        permission: 'comercial.*',
        items: [
            {
                title: 'Fundamental',
                href: '#',
                items: [
                    { title: 'Categorías', href: buildRouteUrl('/categorias'), permission: 'comercial.categorias.viewAny' },
                    { title: 'Productos', href: buildRouteUrl('/productos'), permission: 'comercial.productos.viewAny' },
                    { title: 'Clientes', href: buildRouteUrl('/clientes'), permission: 'comercial.clientes.viewAny' },
                    { title: 'Almacenes (WMS)', href: buildRouteUrl('/almacenes'), permission: 'inventario.almacenes.viewAny' },

                ],
            },
            {
                title: 'CRM & Ventas',
                href: '#',
                items: [
                    { title: 'Leads & Pipeline', href: buildRouteUrl('/prospectos'), permission: 'comercial.prospectos.viewAny' },
                    { title: 'Oportunidades', href: buildRouteUrl('/oportunidades'), permission: 'comercial.oportunidades.viewAny' },
                    { title: 'Cotizaciones', href: buildRouteUrl('/cotizaciones'), permission: 'comercial.cotizaciones.viewAny' },
                    { title: 'Ventas (SFA)', href: buildRouteUrl('/ventas'), permission: 'ventas.ventas.viewAny' },
                    { title: 'Campañas', href: buildRouteUrl('/campanas'), permission: 'comercial.campanas.viewAny' },
                    { title: 'Call Center', href: buildRouteUrl('/call-center'), permission: 'comercial.call-center.viewAny' },
                    { title: 'Tickets Soporte', href: buildRouteUrl('/tickets'), permission: 'comercial.tickets.viewAny' },
                ],
            },
        ],
    },
    {
        title: 'Operaciones e Inventario',
        group: 'OPERACIONES',
        href: '#operaciones',
        icon: Truck,
        permission: 'inventario.*',
        items: [
            { title: 'Inventario', href: buildRouteUrl('/inventarios'), permission: 'inventario.inventarios.viewAny' },
            { title: 'Movimientos', href: buildRouteUrl('/movimientos'), permission: 'inventario.movimientos.viewAny' },
            { title: 'Lotes y Series', href: buildRouteUrl('/lotes'), permission: 'inventario.lotes.viewAny' },
            { title: 'Proveedores', href: buildRouteUrl('/proveedors'), permission: 'inventario.proveedores.viewAny' },
            { title: 'Órdenes de Compra', href: buildRouteUrl('/compras'), permission: 'inventario.compras.viewAny' },
            { title: 'Vacíos', href: buildRouteUrl('/vacios'), permission: 'inventario.vacios.viewAny' },
        ],
    },
    {
        title: 'Producción (MRP)',
        group: 'OPERACIONES',
        href: '#mrp',
        icon: Wrench,
        permission: 'mrp.*',
        items: [
            { title: 'BOM (Materiales)', href: buildRouteUrl('/boms'), permission: 'mrp.boms.viewAny' },
            { title: 'Órdenes Producción', href: buildRouteUrl('/ordenes-produccion'), permission: 'mrp.produccion.viewAny' },
            { title: 'Control Calidad', href: buildRouteUrl('/calidad'), permission: 'mrp.calidad.viewAny' },
            { title: 'Planificación', href: buildRouteUrl('/planificacion'), permission: 'mrp.planificacion.viewAny' },
        ],
    },
    {
        title: 'Facturación',
        group: 'FACTURACIÓN',
        href: '#facturacion',
        icon: FileText,
        permission: 'finanzas.*',
        items: [
            { title: 'Facturación (AR)', href: buildRouteUrl('/facturacion'), permission: 'finanzas.facturacion.viewAny' },
            { title: 'Cobranzas', href: buildRouteUrl('/cobranzas'), permission: 'finanzas.cobranzas.viewAny' },
            { title: 'Pagos (AP)', href: buildRouteUrl('/pagos'), permission: 'finanzas.pagos.viewAny' },
            { title: 'Contabilidad (GL)', href: buildRouteUrl('/contabilidad'), permission: 'finanzas.contabilidad.viewAny' },
            { title: 'Impuestos', href: buildRouteUrl('/impuestos'), permission: 'finanzas.impuestos.viewAny' },
        ],
    },
    {
        title: 'Pagos en Línea',
        group: 'FINANZAS',
        href: '#pagos-online',
        icon: CreditCard,
        permission: 'finanzas.*',
        items: [
            { title: 'Configuración Webpay', href: buildRouteUrl('/webpay/config'), permission: 'admin.configuracion.viewAny' },
            { title: 'Configuración PayPal', href: buildRouteUrl('/paypal/config'), permission: 'admin.configuracion.viewAny' },
            { title: 'Configuración MercadoPago', href: buildRouteUrl('/mercadopago/config'), permission: 'admin.configuracion.viewAny' },
            { title: 'Movimientos', href: buildRouteUrl('/webpay/movimientos'), permission: 'finanzas.tesoreria.viewAny' },
            { title: 'Pago Plataforma', href: buildRouteUrl('/pagos/plataforma'), permission: 'admin.configuracion.viewAny' },
            { title: 'Cupones de Descuento', href: buildRouteUrl('/cupones'), permission: 'ventas.cupones.viewAny' },
        ],
    },
    {
        title: 'Gestión Humana',
        group: 'RRHH',
        href: '#rrhh',
        icon: UsersRound,
        permission: 'rrhh.*',
        items: [
            { title: 'Empleados', href: buildRouteUrl('/empleados'), permission: 'rrhh.empleados.viewAny' },
            { title: 'Nómina', href: buildRouteUrl('/nominas'), permission: 'rrhh.nominas.viewAny' },
            { title: 'Asistencia', href: buildRouteUrl('/asistencia'), permission: 'rrhh.asistencia.viewAny' },
            { title: 'Préstamos y Adelantos', href: buildRouteUrl('/prestamos'), permission: 'rrhh.prestamos.viewAny' },
            { title: 'Cuotas Pendientes', href: buildRouteUrl('/prestamos/cuotas-pendientes'), permission: 'rrhh.prestamos.viewAny' },
            { title: 'Reclutamiento', href: buildRouteUrl('/reclutamiento'), permission: 'rrhh.reclutamiento.viewAny' },
            { title: 'Evaluaciones', href: buildRouteUrl('/evaluaciones'), permission: 'rrhh.evaluaciones.viewAny' },
        ],
    },
    {
        title: 'Proyectos (PMS)',
        group: 'PROYECTOS',
        href: '#pms',
        icon: ClipboardList,
        permission: 'proyectos.*',
        items: [
            { title: 'Proyectos', href: buildRouteUrl('/proyectos'), permission: 'proyectos.proyectos.viewAny' },
            { title: 'Hitos y Tareas', href: buildRouteUrl('/hitos'), permission: 'proyectos.hitos.viewAny' },
            { title: 'Timesheets', href: buildRouteUrl('/timesheets'), permission: 'proyectos.timesheets.viewAny' },
            { title: 'Gastos Proyecto', href: buildRouteUrl('/gastos-proyecto'), permission: 'proyectos.gastos.viewAny' },
        ],
    },
    {
        title: 'Logística y Flota',
        group: 'LOGÍSTICA',
        href: '#logistica',
        icon: Truck,
        permission: 'flota.*',
        items: [
            { title: 'Vehículos', href: buildRouteUrl('/vehiculos'), permission: 'flota.vehiculos.viewAny' },
            { title: 'Conductores', href: buildRouteUrl('/conductores'), permission: 'flota.conductores.viewAny' },
            { title: 'Entregas', href: buildRouteUrl('/entregas'), permission: 'flota.entregas.viewAny' },
            { title: 'Cargas Diarias / Rutas', href: buildRouteUrl('/cargas-diarias'), permission: 'flota.cargas.viewAny' },
            { title: 'Grupos de Trabajo', href: buildRouteUrl('/grupos-trabajo'), permission: 'flota.grupos-trabajo.viewAny' },
        ],
    },
    {
        title: 'Punto de Venta (POS)',
        group: 'TIENDA',
        href: '#tienda',
        icon: ShoppingCart,
        permission: 'ventas.pos.*',
        items: [
            { title: 'Terminal POS', href: buildRouteUrl('/pos'), permission: 'ventas.pos.viewAny' },
            { title: 'Cierre de Caja', href: buildRouteUrl('/pos/cierre'), permission: 'ventas.pos.viewAny' },
            { title: 'Facturación POS', href: buildRouteUrl('/pos/facturacion'), permission: 'ventas.pos.viewAny' },
            { title: 'Reportes Ventas', href: buildRouteUrl('/pos/reportes'), permission: 'ventas.pos.viewAny' },
            { title: 'Mensajes Marketplace', href: buildRouteUrl('/chat'), permission: 'comercial.oportunidades.viewAny' },
            { title: 'Variantes / SKUs', href: buildRouteUrl('/pos/variantes'), permission: 'ventas.variantes.viewAny' },
        ],
    },
    {
        title: 'Citas y Reservas',
        group: 'SERVICIOS',
        href: '#reservas',
        icon: Calendar,
        permission: 'citas.*',
        items: [
            { title: 'Dashboard', href: buildRouteUrl('/appointments/dashboard'), permission: 'citas.citas.viewAny' },
            { title: 'Calendario', href: buildRouteUrl('/appointments/calendar'), permission: 'citas.citas.viewAny' },
            { title: 'Mis Citas', href: buildRouteUrl('/appointments'), permission: 'citas.citas.viewAny' },
            { title: 'Servicios', href: buildRouteUrl('/services'), permission: 'citas.servicios.viewAny' },
        ],
    },
    {
        title: 'Plataforma de Aprendizaje',
        group: 'EDUCACIÓN',
        href: '#lms',
        icon: GraduationCap,
        permission: 'lms.*',
        items: [
            { title: 'Catálogo de Cursos', href: buildRouteUrl('/cursos'), permission: 'lms.cursos.viewAny' },
            { title: 'Mis Cursos (Instructor)', href: buildRouteUrl('/instructor/cursos'), permission: 'lms.cursos.create' },
            { title: 'Cursos Inscritos', href: buildRouteUrl('/alumno/cursos'), permission: 'lms.alumnos.viewAny' },
            { title: 'Progreso y Notas', href: buildRouteUrl('/alumno/progreso'), permission: 'lms.alumnos.viewAny' },
        ],
    },
    {
        title: 'Marketing',
        group: 'MARKETING',
        href: '#marketing',
        icon: Megaphone,
        permission: 'admin.*',
        items: [
            { title: 'Campañas', href: buildRouteUrl('/campanas'), permission: 'comercial.campanas.viewAny' },
            { title: 'Email Marketing', href: buildRouteUrl('/mail-templates'), permission: 'admin.mail-templates.viewAny' },
            { title: 'Config. Correo', href: buildRouteUrl('/marketing/email-config'), permission: 'admin.email-config.viewAny' },
        ],
    },
    {
        title: 'Monitoreo',
        group: 'OPERACIONES',
        href: '#uptime',
        icon: Activity,
        permission: 'uptime.*',
        items: [
            { title: 'Monitores', href: buildRouteUrl('/uptime'), permission: 'uptime.monitores.viewAny' },
            { title: 'Alertas', href: buildRouteUrl('/uptime/alerts'), permission: 'uptime.alertas.viewAny' },
        ],
    },
    {
        title: 'Rifas y Sorteos',
        group: 'MARKETING',
        href: '#raffles',
        icon: Gift,
        permission: 'rifas.*',
        items: [
            { title: 'Gestionar Rifas', href: buildRouteUrl('/raffles'), permission: 'rifas.rifas.viewAny' },
            { title: 'Sorteos', href: buildRouteUrl('/raffles/draws'), permission: 'rifas.rifas.viewAny' },
        ],
    },
];

export function canAny(permission: string | string[], userPermissions: string[] | undefined): boolean {
    if (!permission) return true;
    if (!userPermissions) return false;

    const perms = Array.isArray(permission) ? permission : [permission];
    return perms.some((p) => {
        if (p.endsWith('.*')) {
            const prefix = p.slice(0, -1);
            return userPermissions.some((up) => up.startsWith(prefix));
        }
        return userPermissions.includes(p);
    });
}

function filterNavItemsRecursive(items: ExtendedNavItem[], userPermissions: string[] | undefined, hasWildcard: boolean): NavItem[] {
    return items
        .filter((item) => {
            if (hasWildcard) return true;
            if (!item.permission) return true;
            return canAny(item.permission, userPermissions);
        })
        .map((item) => {
            if (!item.items) return item;

            const filteredSubItems = filterNavItemsRecursive(item.items as ExtendedNavItem[], userPermissions, hasWildcard);

            return { ...item, items: filteredSubItems };
        })
        .filter((item) => {
            if (item.items && item.items.length === 0) {
                return false;
            }
            return true;
        }) as NavItem[];
}

export function filterNavItems(items: ExtendedNavItem[], userPermissions: string[] | undefined): NavItem[] {
    const hasWildcard = userPermissions?.length === 1 && userPermissions[0] === '*';
    return filterNavItemsRecursive(items, userPermissions, hasWildcard);
}

export interface ModuleWithLinks {
    module: string;
    icon: LucideIcon | null;
    links: { title: string; href: HrefType }[];
}

export interface TopModuleItem {
    title: string;
    href: string;
    icon: LucideIcon | null;
    color?: string;
}

export function extractTopModules(items: NavItem[]): TopModuleItem[] {
    const result: TopModuleItem[] = [];

    function isStringHref(href: HrefType): href is string {
        return typeof href === 'string';
    }

    function findFirstChildUrl(nodes: NavItem[]): string | null {
        for (const node of nodes) {
            if (isStringHref(node.href) && node.href !== '#' && node.href.startsWith('/')) {
                return node.href;
            }
            if (node.items) {
                const found = findFirstChildUrl(node.items);
                if (found) return found;
            }
        }
        return null;
    }

    for (const item of items) {
        if (item.title === 'Dashboard') continue;

        if (item.items) {
            const url = findFirstChildUrl(item.items);
            if (url) {
                result.push({ title: item.title, href: url, icon: item.icon || null });
            }
        } else if (isStringHref(item.href) && item.href.startsWith('/')) {
            result.push({ title: item.title, href: item.href, icon: item.icon || null });
        }
    }

    return result;
}

export function extractModules(items: NavItem[]): ModuleWithLinks[] {
    const modules: ModuleWithLinks[] = [];

    function isStringHref(href: HrefType): href is string {
        return typeof href === 'string';
    }

    function collectLeafLinks(nodes: NavItem[]): { title: string; href: HrefType }[] {
        const result: { title: string; href: HrefType }[] = [];

        for (const node of nodes) {
            if (isStringHref(node.href) && node.href !== '#' && node.href.startsWith('/')) {
                result.push({ title: node.title, href: node.href });
            }
            if (node.items) {
                result.push(...collectLeafLinks(node.items));
            }
        }

        return result;
    }

    for (const item of items) {
        if (item.title === 'Dashboard') {
            continue;
        } else if (item.items) {
            const links = collectLeafLinks(item.items);
            if (links.length > 0) {
                modules.push({
                    module: item.title,
                    icon: item.icon || null,
                    links,
                });
            }
        } else if (isStringHref(item.href) && item.href.startsWith('/')) {
            modules.push({
                module: item.title,
                icon: item.icon || null,
                links: [{ title: item.title, href: item.href }],
            });
        }
    }

    return modules;
}
