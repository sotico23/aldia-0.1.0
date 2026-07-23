import { Link, usePage } from '@inertiajs/react';
import {
    Settings, User, Palette, Bell, Users, Store, Package,
    ShoppingCart, Award, Bot, ShieldCheck, FileText,
    Building2, ClipboardList, CreditCard, GraduationCap,
    Truck, UsersRound, Wrench, Banknote, Clock, CalendarCheck,
    Briefcase, Tags
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
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
    { id: 'categorias', title: 'Categorías', href: '/categorias', icon: Tags, color: 'text-blue-600', bg: 'bg-blue-50', hoverBorder: 'hover:border-blue-200', permission: 'comercial.categorias.viewAny', group: 'Comercial' },
    { id: 'productos', title: 'Inventario', href: '/productos', icon: Package, color: 'text-purple-600', bg: 'bg-purple-50', hoverBorder: 'hover:border-purple-200', permission: 'comercial.productos.viewAny', group: 'Comercial' },
    { id: 'clientes', title: 'Clientes', href: '/clientes', icon: Users, color: 'text-emerald-600', bg: 'bg-emerald-50', hoverBorder: 'hover:border-emerald-200', permission: 'comercial.clientes.viewAny', group: 'Comercial' },
    { id: 'ventas', title: 'Ventas (SFA)', href: '/ventas', icon: Banknote, color: 'text-emerald-600', bg: 'bg-emerald-50', hoverBorder: 'hover:border-emerald-200', permission: 'ventas.ventas.viewAny', group: 'Comercial' },
    { id: 'cotizaciones', title: 'Cotizaciones', href: '/cotizaciones', icon: FileText, color: 'text-slate-600', bg: 'bg-slate-50', hoverBorder: 'hover:border-slate-200', permission: 'comercial.cotizaciones.viewAny', group: 'Comercial' },
    { id: 'inventarios', title: 'Almacenes', href: '/inventarios', icon: Building2, color: 'text-indigo-600', bg: 'bg-indigo-50', hoverBorder: 'hover:border-indigo-200', permission: 'inventario.inventarios.viewAny', group: 'Operaciones' },
    { id: 'compras', title: 'Órdenes de Compra', href: '/compras', icon: ShoppingCart, color: 'text-cyan-600', bg: 'bg-cyan-50', hoverBorder: 'hover:border-cyan-200', permission: 'inventario.compras.viewAny', group: 'Operaciones' },
    { id: 'produccion', title: 'Producción', href: '/ordenes-produccion', icon: Wrench, color: 'text-amber-600', bg: 'bg-amber-50', hoverBorder: 'hover:border-amber-200', permission: 'mrp.produccion.viewAny', group: 'Operaciones' },
    { id: 'facturacion', title: 'Facturación', href: '/facturacion', icon: FileText, color: 'text-rose-600', bg: 'bg-rose-50', hoverBorder: 'hover:border-rose-200', permission: 'finanzas.facturacion.viewAny', group: 'Finanzas' },
    { id: 'cobranzas', title: 'Cobranzas', href: '/cobranzas', icon: CreditCard, color: 'text-orange-600', bg: 'bg-orange-50', hoverBorder: 'hover:border-orange-200', permission: 'finanzas.cobranzas.viewAny', group: 'Finanzas' },
    { id: 'empleados', title: 'Empleados', href: '/empleados', icon: UsersRound, color: 'text-pink-600', bg: 'bg-pink-50', hoverBorder: 'hover:border-pink-200', permission: 'rrhh.empleados.viewAny', group: 'RRHH' },
    { id: 'asistencia', title: 'Asistencia', href: '/asistencia', icon: Clock, color: 'text-blue-600', bg: 'bg-blue-50', hoverBorder: 'hover:border-blue-200', permission: 'rrhh.asistencia.viewAny', group: 'RRHH' },
    { id: 'proyectos', title: 'Proyectos', href: '/proyectos', icon: ClipboardList, color: 'text-teal-600', bg: 'bg-teal-50', hoverBorder: 'hover:border-teal-200', permission: 'proyectos.proyectos.viewAny', group: 'Proyectos' },
    { id: 'entregas', title: 'Logística', href: '/entregas', icon: Truck, color: 'text-zinc-600', bg: 'bg-zinc-50', hoverBorder: 'hover:border-zinc-200', permission: 'flota.entregas.viewAny', group: 'Logística' },
    { id: 'pos', title: 'Punto de Venta', href: '/pos', icon: Store, color: 'text-fuchsia-600', bg: 'bg-fuchsia-50', hoverBorder: 'hover:border-fuchsia-200', permission: 'ventas.pos.viewAny', group: 'Tienda' },
    { id: 'citas', title: 'Agendar Cita', href: '/appointments', icon: CalendarCheck, color: 'text-rose-600', bg: 'bg-rose-50', hoverBorder: 'hover:border-rose-200', permission: 'citas.citas.viewAny', group: 'Servicios' },
    { id: 'cursos', title: 'Cursos', href: '/cursos', icon: GraduationCap, color: 'text-violet-600', bg: 'bg-violet-50', hoverBorder: 'hover:border-violet-200', permission: 'lms.cursos.viewAny', group: 'Educación' },
    
    // Right Sidebar Items
    { id: 'perfil', title: 'Mi Perfil', href: '/profile', icon: User, color: 'text-slate-700', bg: 'bg-slate-100', hoverBorder: 'hover:border-slate-300', group: 'Ajustes Personales' },
    { id: 'apariencia', title: 'Apariencia', href: '/appearance', icon: Palette, color: 'text-indigo-500', bg: 'bg-indigo-50', hoverBorder: 'hover:border-indigo-200', group: 'Ajustes Personales' },
    { id: 'notificaciones_config', title: 'Notificaciones', href: '/settings/notifications', icon: Bell, color: 'text-amber-500', bg: 'bg-amber-50', hoverBorder: 'hover:border-amber-200', group: 'Ajustes Personales' },
    { id: 'sii', title: 'SII (DTE)', href: '/sii', icon: ShieldCheck, color: 'text-blue-700', bg: 'bg-blue-50', hoverBorder: 'hover:border-blue-300', permission: 'finanzas.sii.viewAny', group: 'SII' },
    { id: 'marketplace', title: 'Marketplace', href: '/tienda', icon: Store, color: 'text-orange-500', bg: 'bg-orange-50', hoverBorder: 'hover:border-orange-200', group: 'Social' },
    { id: 'mis_pedidos', title: 'Mis Pedidos', href: '/mis-pedidos', icon: Package, color: 'text-emerald-500', bg: 'bg-emerald-50', hoverBorder: 'hover:border-emerald-200', group: 'Social' },
    { id: 'mis_ventas', title: 'Mis Ventas', href: '/pedidos-recibidos', icon: ShoppingCart, color: 'text-blue-500', bg: 'bg-blue-50', hoverBorder: 'hover:border-blue-200', permission: 'comercial.oportunidades.viewAny', group: 'Social' },
    { id: 'recomienda', title: 'Recompensas', href: '/afiliados/recomendar', icon: Award, color: 'text-yellow-600', bg: 'bg-yellow-50', hoverBorder: 'hover:border-yellow-200', group: 'Social' },
    { id: 'automatiza', title: 'Automatizaciones', href: '/canales', icon: Bot, color: 'text-purple-600', bg: 'bg-purple-50', hoverBorder: 'hover:border-purple-200', permission: 'sistema.automatizaciones.viewAny', group: 'Sistema' },
];

// Reemplazar iconos que falten o fallen
const FallbackIcon = ({ className }: { className?: string }) => <Briefcase className={className} />;

export default function QuickAccessWidget() {
    const { auth } = usePage().props as any;
    const userPermissions = useMemo(() => auth.user.permissions || [], [auth.user.permissions]);
    
    // Filter shortcuts by user permissions
    const availableShortcuts = useMemo(() => {
        return ALL_SHORTCUTS.filter(shortcut => {
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
            ? selectedIds.filter(x => x !== id)
            : [...selectedIds, id];
        
        setSelectedIds(newSelection);
        localStorage.setItem(`quick_access_${auth.user.id}`, JSON.stringify(newSelection));
    };

    if (!isLoaded) return null;

    const visibleShortcuts = availableShortcuts.filter(s => selectedIds.includes(s.id));

    // Agrupar los disponibles para el selector
    const groupedAvailable = availableShortcuts.reduce((acc, curr) => {
        if (!acc[curr.group]) acc[curr.group] = [];
        acc[curr.group].push(curr);
        return acc;
    }, {} as Record<string, Shortcut[]>);

    return (
        <div className="flex flex-col h-full relative">
            <div className="absolute -top-12 right-2 z-10">
                <Dialog>
                    <DialogTrigger asChild>
                        <button className="p-2 bg-white rounded-full shadow-sm hover:shadow border border-slate-100 text-slate-500 hover:text-slate-800 transition-all group">
                            <Settings className="w-4 h-4 group-hover:rotate-90 transition-transform duration-500" />
                        </button>
                    </DialogTrigger>
                    <DialogContent className="w-[95vw] max-w-2xl max-h-[90dvh] flex flex-col overflow-hidden p-4 sm:p-6">
                        <DialogHeader className="shrink-0">
                            <DialogTitle className="text-lg sm:text-xl font-black text-slate-800">
                                Personalizar Accesos Rápidos
                            </DialogTitle>
                            <p className="text-xs sm:text-sm text-slate-500">Selecciona los módulos que deseas ver en tu panel de inicio rápido.</p>
                        </DialogHeader>
                        <ScrollArea className="flex-1 -mx-2 sm:-mx-4 px-2 sm:px-4 min-h-0 overflow-y-auto">
                            {Object.entries(groupedAvailable).map(([group, items]) => (
                                <div key={group} className="mb-4 sm:mb-6">
                                    <h3 className="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 sm:mb-3 border-b border-slate-100 pb-1">{group}</h3>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                                        {items.map(item => {
                                            const Icon = item.icon || FallbackIcon;
                                            return (
                                                <label key={item.id} className={`flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-colors ${selectedIds.includes(item.id) ? 'border-primary/50 bg-primary/5' : 'border-slate-100 hover:bg-slate-50'}`}>
                                                    <div className="flex items-center gap-3">
                                                        <div className={`p-2 rounded-lg ${item.bg}`}>
                                                            <Icon className={`w-4 h-4 ${item.color}`} />
                                                        </div>
                                                        <span className="text-sm font-semibold text-slate-700">{item.title}</span>
                                                    </div>
                                                    <Switch 
                                                        checked={selectedIds.includes(item.id)} 
                                                        onCheckedChange={() => toggleShortcut(item.id)} 
                                                    />
                                                </label>
                                            )
                                        })}
                                    </div>
                                </div>
                            ))}
                        </ScrollArea>
                    </DialogContent>
                </Dialog>
            </div>

            {visibleShortcuts.length === 0 ? (
                <div className="flex-1 flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50/50">
                    <Settings className="w-8 h-8 text-slate-300 mb-2" />
                    <p className="text-sm text-slate-500 font-medium">No has seleccionado ningún acceso rápido.</p>
                    <p className="text-xs text-slate-400">Usa el ícono de engranaje para configurar.</p>
                </div>
            ) : (
                <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-auto-fit min-[250px] gap-4 flex-1">
                    {visibleShortcuts.map((shortcut) => {
                        const Icon = shortcut.icon || FallbackIcon;
                        return (
                            <Link key={shortcut.id} href={shortcut.href} className="block min-h-[120px] h-full">
                                <Card className={`h-full border border-slate-100 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.05)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 bg-white/90 backdrop-blur-sm group cursor-pointer ${shortcut.hoverBorder}`}>
                                    <CardContent className="p-5 flex flex-col items-center justify-center gap-3 text-center h-full relative overflow-hidden">
                                        <div className="absolute -right-4 -top-4 opacity-[0.02] group-hover:opacity-[0.05] transition-opacity duration-500 pointer-events-none">
                                            <Icon className="w-24 h-24 transform -rotate-12 group-hover:rotate-0 transition-transform duration-700 ease-out" />
                                        </div>
                                        <div className={`p-4 rounded-2xl ${shortcut.bg} shadow-sm group-hover:scale-110 transition-transform duration-300 relative z-10`}>
                                            <Icon className={`h-8 w-8 ${shortcut.color}`} />
                                        </div>
                                        <h3 className="font-bold text-slate-700 group-hover:text-slate-900 relative z-10 text-sm">{shortcut.title}</h3>
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
