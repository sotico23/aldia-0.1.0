import { Link, usePage } from '@inertiajs/react';
import {
    Users, Truck, Wrench, FileText, CreditCard, UsersRound,
    ClipboardList, ShoppingCart, Calendar, GraduationCap,
    Megaphone, Activity, Gift, Lock, Building2,
} from 'lucide-react';
import { useMemo } from 'react';
import {
    mainNavItems,
    adminNavItems,
    filterNavItems,
    extractTopModules,
} from '@/config/navigation';

const iconMap: Record<string, React.ElementType> = {
    Users, Truck, Wrench, FileText, CreditCard, UsersRound,
    ClipboardList, ShoppingCart, Calendar, GraduationCap,
    Megaphone, Activity, Gift, Lock, Building2,
};

const moduleStyles: Record<string, { gradient: string; border: string; icon: string; shadow: string }> = {
    'Gestión Comercial': {
        gradient: 'from-blue-500/10 via-blue-500/5 to-transparent',
        border: 'border-blue-500/20 hover:border-blue-500/40',
        icon: 'text-blue-500 bg-blue-500/10 group-hover:bg-blue-500/20',
        shadow: 'hover:shadow-blue-500/10',
    },
    'Operaciones e Inventario': {
        gradient: 'from-orange-500/10 via-orange-500/5 to-transparent',
        border: 'border-orange-500/20 hover:border-orange-500/40',
        icon: 'text-orange-500 bg-orange-500/10 group-hover:bg-orange-500/20',
        shadow: 'hover:shadow-orange-500/10',
    },
    'Producción (MRP)': {
        gradient: 'from-yellow-500/10 via-yellow-500/5 to-transparent',
        border: 'border-yellow-500/20 hover:border-yellow-500/40',
        icon: 'text-yellow-500 bg-yellow-500/10 group-hover:bg-yellow-500/20',
        shadow: 'hover:shadow-yellow-500/10',
    },
    Facturación: {
        gradient: 'from-rose-500/10 via-rose-500/5 to-transparent',
        border: 'border-rose-500/20 hover:border-rose-500/40',
        icon: 'text-rose-500 bg-rose-500/10 group-hover:bg-rose-500/20',
        shadow: 'hover:shadow-rose-500/10',
    },
    'Pagos en Línea': {
        gradient: 'from-indigo-500/10 via-indigo-500/5 to-transparent',
        border: 'border-indigo-500/20 hover:border-indigo-500/40',
        icon: 'text-indigo-500 bg-indigo-500/10 group-hover:bg-indigo-500/20',
        shadow: 'hover:shadow-indigo-500/10',
    },
    'Gestión Humana': {
        gradient: 'from-pink-500/10 via-pink-500/5 to-transparent',
        border: 'border-pink-500/20 hover:border-pink-500/40',
        icon: 'text-pink-500 bg-pink-500/10 group-hover:bg-pink-500/20',
        shadow: 'hover:shadow-pink-500/10',
    },
    'Proyectos (PMS)': {
        gradient: 'from-teal-500/10 via-teal-500/5 to-transparent',
        border: 'border-teal-500/20 hover:border-teal-500/40',
        icon: 'text-teal-500 bg-teal-500/10 group-hover:bg-teal-500/20',
        shadow: 'hover:shadow-teal-500/10',
    },
    'Logística y Flota': {
        gradient: 'from-cyan-500/10 via-cyan-500/5 to-transparent',
        border: 'border-cyan-500/20 hover:border-cyan-500/40',
        icon: 'text-cyan-500 bg-cyan-500/10 group-hover:bg-cyan-500/20',
        shadow: 'hover:shadow-cyan-500/10',
    },
    'Punto de Venta (POS)': {
        gradient: 'from-emerald-500/10 via-emerald-500/5 to-transparent',
        border: 'border-emerald-500/20 hover:border-emerald-500/40',
        icon: 'text-emerald-500 bg-emerald-500/10 group-hover:bg-emerald-500/20',
        shadow: 'hover:shadow-emerald-500/10',
    },
    'Citas y Reservas': {
        gradient: 'from-violet-500/10 via-violet-500/5 to-transparent',
        border: 'border-violet-500/20 hover:border-violet-500/40',
        icon: 'text-violet-500 bg-violet-500/10 group-hover:bg-violet-500/20',
        shadow: 'hover:shadow-violet-500/10',
    },
    'Plataforma de Aprendizaje': {
        gradient: 'from-purple-500/10 via-purple-500/5 to-transparent',
        border: 'border-purple-500/20 hover:border-purple-500/40',
        icon: 'text-purple-500 bg-purple-500/10 group-hover:bg-purple-500/20',
        shadow: 'hover:shadow-purple-500/10',
    },
    Marketing: {
        gradient: 'from-fuchsia-500/10 via-fuchsia-500/5 to-transparent',
        border: 'border-fuchsia-500/20 hover:border-fuchsia-500/40',
        icon: 'text-fuchsia-500 bg-fuchsia-500/10 group-hover:bg-fuchsia-500/20',
        shadow: 'hover:shadow-fuchsia-500/10',
    },
    Monitoreo: {
        gradient: 'from-slate-500/10 via-slate-500/5 to-transparent',
        border: 'border-slate-500/20 hover:border-slate-500/40',
        icon: 'text-slate-500 bg-slate-500/10 group-hover:bg-slate-500/20',
        shadow: 'hover:shadow-slate-500/10',
    },
    'Rifas y Sorteos': {
        gradient: 'from-red-500/10 via-red-500/5 to-transparent',
        border: 'border-red-500/20 hover:border-red-500/40',
        icon: 'text-red-500 bg-red-500/10 group-hover:bg-red-500/20',
        shadow: 'hover:shadow-red-500/10',
    },
    Administración: {
        gradient: 'from-gray-500/10 via-gray-500/5 to-transparent',
        border: 'border-gray-500/20 hover:border-gray-500/40',
        icon: 'text-gray-500 bg-gray-500/10 group-hover:bg-gray-500/20',
        shadow: 'hover:shadow-gray-500/10',
    },
};

const defaultStyle = {
    gradient: 'from-primary/10 via-primary/5 to-transparent',
    border: 'border-border/50 hover:border-primary/30',
    icon: 'text-primary bg-primary/10 group-hover:bg-primary/20',
    shadow: 'hover:shadow-primary/10',
};

export default function AccesoRapidoWidget() {
    const { auth } = usePage().props as {
        auth: { user: { permissions?: string[] } };
    };

    const modules = useMemo(() => {
        const rawAllItems = [...mainNavItems, ...adminNavItems()];
        const filteredItems = filterNavItems(rawAllItems, auth.user.permissions);
        return extractTopModules(filteredItems);
    }, [auth.user.permissions]);

    if (modules.length === 0) return null;

    return (
        <div className="p-4">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">
                {modules.map(mod => {
                    const Icon = mod.icon ?? iconMap[mod.title] ?? null;
                    const s = moduleStyles[mod.title] || defaultStyle;

                    return (
                        <Link
                            key={mod.title}
                            href={mod.href}
                            className={`group relative flex flex-col items-center gap-3 overflow-hidden rounded-xl border p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg active:scale-[0.98] ${s.border} ${s.shadow}`}
                        >
                            <div className={`pointer-events-none absolute inset-0 bg-gradient-to-b ${s.gradient} opacity-0 transition-opacity duration-300 group-hover:opacity-100`} />
                            <div className={`relative z-10 flex flex-col items-center gap-3`}>
                                {Icon && (
                                    <div className={`rounded-xl p-3 transition-all duration-300 ${s.icon}`}>
                                        <Icon className="h-7 w-7" />
                                    </div>
                                )}
                                <span className="text-center text-[11px] font-semibold leading-tight text-muted-foreground group-hover:text-foreground transition-colors duration-300">
                                    {mod.title}
                                </span>
                            </div>
                            <div className={`absolute bottom-0 left-0 right-0 h-0.5 scale-x-0 transition-transform duration-300 group-hover:scale-x-100 ${s.icon.replace('text-', 'bg-').replace(' group-hover:', '')}`} />
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
