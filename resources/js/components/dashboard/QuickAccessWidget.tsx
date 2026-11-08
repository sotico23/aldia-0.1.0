import { Link, usePage } from '@inertiajs/react';
import {
    Settings,
    User,
    Bell,
    Users,
    Store,
    Package,
    ShoppingCart,
    Award,
    Bot,
    ShieldCheck,
    FileText,
    Building2,
    ClipboardList,
    CreditCard,
    GraduationCap,
    Truck,
    UsersRound,
    Wrench,
    Banknote,
    Clock,
    CalendarCheck,
    Briefcase,
    Tags,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Switch } from '@/components/ui/switch';
import { canAny } from '@/config/navigation';

interface Shortcut {
    id: string;
    title: string;
    href: string;
    icon: any;
    color: string;
    bg: string;
    hoverBorder: string;
    permission?: string | string[];
    group: string;
}

const ALL_SHORTCUTS: Shortcut[] = [
    // Left Sidebar Items
    {
        id: 'categorias',
        title: 'Categorías',
        href: '/categorias',
        icon: Tags,
        color: 'text-emerald-600',
        bg: 'bg-emerald-50',
        hoverBorder: 'hover:border-emerald-200',
        permission: 'comercial.categorias.viewAny',
        group: 'Comercial',
    },
    {
        id: 'productos',
        title: 'Inventario',
        href: '/productos',
        icon: Package,
        color: 'text-purple-600',
        bg: 'bg-purple-50',
        hoverBorder: 'hover:border-purple-200',
        permission: 'comercial.productos.viewAny',
        group: 'Comercial',
    },
    {
        id: 'clientes',
        title: 'Clientes',
        href: '/clientes',
        icon: Users,
        color: 'text-emerald-600',
        bg: 'bg-emerald-50',
        hoverBorder: 'hover:border-emerald-200',
        permission: 'comercial.clientes.viewAny',
        group: 'Comercial',
    },
    {
        id: 'ventas',
        title: 'Ventas (SFA)',
        href: '/ventas',
        icon: Banknote,
        color: 'text-emerald-600',
        bg: 'bg-emerald-50',
        hoverBorder: 'hover:border-emerald-200',
        permission: 'ventas.ventas.viewAny',
        group: 'Comercial',
    },
    {
        id: 'cotizaciones',
        title: 'Cotizaciones',
        href: '/cotizaciones',
        icon: FileText,
        color: 'text-slate-600',
        bg: 'bg-slate-50',
        hoverBorder: 'hover:border-slate-200',
        permission: 'comercial.cotizaciones.viewAny',
        group: 'Comercial',
    },
    {
        id: 'inventarios',
        title: 'Almacenes',
        href: '/inventarios',
        icon: Building2,
        color: 'text-emerald-600',
        bg: 'bg-emerald-50',
        hoverBorder: 'hover:border-emerald-200',
        permission: 'inventario.inventarios.viewAny',
        group: 'Operaciones',
    },
    {
        id: 'compras',
        title: 'Órdenes de Compra',
        href: '/compras',
        icon: ShoppingCart,
        color: 'text-emerald-600',
        bg: 'bg-emerald-50',
        hoverBorder: 'hover:border-emerald-200',
        permission: 'inventario.compras.viewAny',
        group: 'Operaciones',
    },
    {
        id: 'produccion',
        title: 'Producción',
        href: '/ordenes-produccion',
        icon: Wrench,
        color: 'text-amber-600',
        bg: 'bg-amber-50',
        hoverBorder: 'hover:border-amber-200',
        permission: 'mrp.produccion.viewAny',
        group: 'Operaciones',
    },
    {
        id: 'facturacion',
        title: 'Facturación',
        href: '/facturacion',
        icon: FileText,
        color: 'text-rose-600',
        bg: 'bg-rose-50',
        hoverBorder: 'hover:border-rose-200',
        permission: 'finanzas.facturacion.viewAny',
        group: 'Finanzas',
    },
    {
        id: 'cobranzas',
        title: 'Cobranzas',
        href: '/cobranzas',
        icon: CreditCard,
        color: 'text-orange-600',
        bg: 'bg-orange-50',
        hoverBorder: 'hover:border-orange-200',
        permission: 'finanzas.cobranzas.viewAny',
        group: 'Finanzas',
    },
    {
        id: 'empleados',
        title: 'Empleados',
        href: '/empleados',
        icon: UsersRound,
        color: 'text-pink-600',
        bg: 'bg-pink-50',
        hoverBorder: 'hover:border-pink-200',
        permission: 'rrhh.empleados.viewAny',
        group: 'RRHH',
    },
    {
        id: 'asistencia',
        title: 'Asistencia',
        href: '/asistencia',
        icon: Clock,
        color: 'text-emerald-600',
        bg: 'bg-emerald-50',
        hoverBorder: 'hover:border-emerald-200',
        permission: 'rrhh.asistencia.viewAny',
        group: 'RRHH',
    },
    {
        id: 'proyectos',
        title: 'Proyectos',
        href: '/proyectos',
        icon: ClipboardList,
        color: 'text-teal-600',
        bg: 'bg-teal-50',
        hoverBorder: 'hover:border-teal-200',
        permission: 'proyectos.proyectos.viewAny',
        group: 'Proyectos',
    },
    {
        id: 'entregas',
        title: 'Logística',
        href: '/entregas',
        icon: Truck,
        color: 'text-zinc-600',
        bg: 'bg-zinc-50',
        hoverBorder: 'hover:border-zinc-200',
        permission: 'flota.entregas.viewAny',
        group: 'Logística',
    },
    {
        id: 'pos',
        title: 'Punto de Venta',
        href: '/pos',
        icon: Store,
        color: 'text-fuchsia-600',
        bg: 'bg-fuchsia-50',
        hoverBorder: 'hover:border-fuchsia-200',
        permission: 'ventas.pos.viewAny',
        group: 'Tienda',
    },
    {
        id: 'citas',
        title: 'Agendar Cita',
        href: '/appointments',
        icon: CalendarCheck,
        color: 'text-rose-600',
        bg: 'bg-rose-50',
        hoverBorder: 'hover:border-rose-200',
        permission: 'citas.citas.viewAny',
        group: 'Servicios',
    },
    {
        id: 'cursos',
        title: 'Cursos',
        href: '/cursos',
        icon: GraduationCap,
        color: 'text-violet-600',
        bg: 'bg-violet-50',
        hoverBorder: 'hover:border-violet-200',
        permission: 'lms.cursos.viewAny',
        group: 'Educación',
    },

    // Right Sidebar Items
    {
        id: 'perfil',
        title: 'Mi Perfil',
        href: '/profile',
        icon: User,
        color: 'text-slate-700',
        bg: 'bg-slate-100',
        hoverBorder: 'hover:border-slate-300',
        group: 'Ajustes Personales',
    },
    {
        id: 'notificaciones_config',
        title: 'Notificaciones',
        href: '/settings/notifications',
        icon: Bell,
        color: 'text-amber-500',
        bg: 'bg-amber-50',
        hoverBorder: 'hover:border-amber-200',
        group: 'Ajustes Personales',
    },
    {
        id: 'sii',
        title: 'SII (DTE)',
        href: '/sii',
        icon: ShieldCheck,
        color: 'text-emerald-700',
        bg: 'bg-emerald-50',
        hoverBorder: 'hover:border-emerald-300',
        permission: 'finanzas.sii.viewAny',
        group: 'SII',
    },
    {
        id: 'marketplace',
        title: 'Marketplace',
        href: '/tienda',
        icon: Store,
        color: 'text-orange-500',
        bg: 'bg-orange-50',
        hoverBorder: 'hover:border-orange-200',
        group: 'Social',
    },
    {
        id: 'mis_pedidos',
        title: 'Mis Pedidos',
        href: '/mis-pedidos',
        icon: Package,
        color: 'text-emerald-500',
        bg: 'bg-emerald-50',
        hoverBorder: 'hover:border-emerald-200',
        group: 'Social',
    },
    {
        id: 'mis_ventas',
        title: 'Mis Ventas',
        href: '/pedidos-recibidos',
        icon: ShoppingCart,
        color: 'text-emerald-500',
        bg: 'bg-emerald-50',
        hoverBorder: 'hover:border-emerald-200',
        permission: 'comercial.oportunidades.viewAny',
        group: 'Social',
    },
    {
        id: 'recomienda',
        title: 'Recompensas',
        href: '/afiliados/recomendar',
        icon: Award,
        color: 'text-yellow-600',
        bg: 'bg-yellow-50',
        hoverBorder: 'hover:border-yellow-200',
        group: 'Social',
    },
    {
        id: 'automatiza',
        title: 'Automatizaciones',
        href: '/canales',
        icon: Bot,
        color: 'text-purple-600',
        bg: 'bg-purple-50',
        hoverBorder: 'hover:border-purple-200',
        permission: 'sistema.automatizaciones.viewAny',
        group: 'Sistema',
    },
];

// Reemplazar iconos que falten o fallen
const FallbackIcon = ({ className }: { className?: string }) => (
    <Briefcase className={className} />
);

export default function QuickAccessWidget() {
    const { auth } = usePage().props as any;
    const userPermissions = useMemo(
        () => auth.user.permissions || [],
        [auth.user.permissions],
    );

    // Filter shortcuts by user permissions
    const availableShortcuts = useMemo(() => {
        return ALL_SHORTCUTS.filter((shortcut) => {
            if (!shortcut.permission) return true;
            return canAny(shortcut.permission, userPermissions);
        });
    }, [userPermissions]);

    const defaultSelection = ['ventas', 'asistencia', 'productos', 'citas'];

    const [selectedIds, setSelectedIds] = useState<string[]>(() => {
        const saved = localStorage.getItem(`quick_access_${auth.user.id}`);
        if (saved) {
            try {
                return JSON.parse(saved);
            } catch {
                return defaultSelection;
            }
        }
        return defaultSelection;
    });
    const [isLoaded] = useState(true);

    const toggleShortcut = (id: string) => {
        const newSelection = selectedIds.includes(id)
            ? selectedIds.filter((x) => x !== id)
            : [...selectedIds, id];

        setSelectedIds(newSelection);
        localStorage.setItem(
            `quick_access_${auth.user.id}`,
            JSON.stringify(newSelection),
        );
    };

    if (!isLoaded) return null;

    const visibleShortcuts = availableShortcuts.filter((s) =>
        selectedIds.includes(s.id),
    );

    // Agrupar los disponibles para el selector
    const groupedAvailable = availableShortcuts.reduce(
        (acc, curr) => {
            if (!acc[curr.group]) acc[curr.group] = [];
            acc[curr.group].push(curr);
            return acc;
        },
        {} as Record<string, Shortcut[]>,
    );

    return (
        <div className="relative flex h-full flex-col">
            <div className="absolute -top-12 right-2 z-10">
                <Dialog>
                    <DialogTrigger asChild>
                        <button className="group rounded-full border border-slate-100 bg-white p-2 text-slate-500 shadow-sm transition-all hover:text-slate-800 hover:shadow">
                            <Settings className="h-4 w-4 transition-transform duration-500 group-hover:rotate-90" />
                        </button>
                    </DialogTrigger>
                    <DialogContent className="flex max-h-[90dvh] w-[95vw] max-w-2xl flex-col overflow-hidden p-4 sm:p-6">
                        <DialogHeader className="shrink-0">
                            <DialogTitle className="text-lg font-black text-slate-800 sm:text-xl">
                                Personalizar Accesos Rápidos
                            </DialogTitle>
                            <p className="text-xs text-slate-500 sm:text-sm">
                                Selecciona los módulos que deseas ver en tu
                                panel de inicio rápido.
                            </p>
                        </DialogHeader>
                        <ScrollArea className="-mx-2 min-h-0 flex-1 overflow-y-auto px-2 sm:-mx-4 sm:px-4">
                            {Object.entries(groupedAvailable).map(
                                ([group, items]) => (
                                    <div key={group} className="mb-4 sm:mb-6">
                                        <h3 className="mb-2 border-b border-slate-100 pb-1 text-[10px] font-bold tracking-widest text-slate-400 uppercase sm:mb-3 sm:text-xs">
                                            {group}
                                        </h3>
                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                                            {items.map((item) => {
                                                const Icon =
                                                    item.icon || FallbackIcon;
                                                return (
                                                    <label
                                                        key={item.id}
                                                        className={`flex cursor-pointer items-center justify-between rounded-lg border p-3 transition-colors ${selectedIds.includes(item.id) ? 'border-primary/50 bg-primary/5' : 'border-slate-100 hover:bg-slate-50'}`}
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <div
                                                                className={`rounded-lg p-2 ${item.bg}`}
                                                            >
                                                                <Icon
                                                                    className={`h-4 w-4 ${item.color}`}
                                                                />
                                                            </div>
                                                            <span className="text-sm font-semibold text-slate-700">
                                                                {item.title}
                                                            </span>
                                                        </div>
                                                        <Switch
                                                            checked={selectedIds.includes(
                                                                item.id,
                                                            )}
                                                            onCheckedChange={() =>
                                                                toggleShortcut(
                                                                    item.id,
                                                                )
                                                            }
                                                        />
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ),
                            )}
                        </ScrollArea>
                    </DialogContent>
                </Dialog>
            </div>

            {visibleShortcuts.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50">
                    <Settings className="mb-2 h-8 w-8 text-slate-300" />
                    <p className="text-sm font-medium text-slate-500">
                        No has seleccionado ningún acceso rápido.
                    </p>
                    <p className="text-xs text-slate-400">
                        Usa el ícono de engranaje para configurar.
                    </p>
                </div>
            ) : (
                <div className="lg:grid-cols-auto-fit min-[250px] grid flex-1 grid-cols-2 gap-4 md:grid-cols-4">
                    {visibleShortcuts.map((shortcut) => {
                        const Icon = shortcut.icon || FallbackIcon;
                        return (
                            <Link
                                key={shortcut.id}
                                href={shortcut.href}
                                className="block h-full min-h-[120px]"
                            >
                                <Card
                                    className={`group h-full cursor-pointer border border-slate-100 bg-white/90 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.05)] backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] ${shortcut.hoverBorder}`}
                                >
                                    <CardContent className="relative flex h-full flex-col items-center justify-center gap-3 overflow-hidden p-5 text-center">
                                        <div className="pointer-events-none absolute -top-4 -right-4 opacity-[0.02] transition-opacity duration-500 group-hover:opacity-[0.05]">
                                            <Icon className="h-24 w-24 -rotate-12 transform transition-transform duration-700 ease-out group-hover:rotate-0" />
                                        </div>
                                        <div
                                            className={`rounded-2xl p-4 ${shortcut.bg} relative z-10 shadow-sm transition-transform duration-300 group-hover:scale-110`}
                                        >
                                            <Icon
                                                className={`h-8 w-8 ${shortcut.color}`}
                                            />
                                        </div>
                                        <h3 className="relative z-10 text-sm font-bold text-slate-700 group-hover:text-slate-900">
                                            {shortcut.title}
                                        </h3>
                                    </CardContent>
                                </Card>
                            </Link>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
