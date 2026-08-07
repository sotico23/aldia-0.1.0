import { Head } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    BarChart3,
    TrendingUp,
    Package,
    Users,
    DollarSign,
    ShoppingCart,
    AlertTriangle,
    FileText,
    Target,
    HeadphonesIcon,
    ArrowUpRight,
    ArrowDownRight
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reportes BI', href: '/reportes' },
];

// ── Types ────────────────────────────────────────────────────────────────────
interface Kpis {
    ventas_mes: number; ventas_cambio: number;
    clientes_total: number; clientes_nuevos: number;
    compras_mes: number;
    productos_total: number; stock_bajo: number;
    cotizaciones_mes: number; cotizaciones_total: number;
    prospectos_mes: number; oportunidades: number;
    tickets_abiertos: number;
}
interface MesSales { mes: string; total: number; cantidad: number; }
interface TopItem { nombre: string; unidades?: number; ingreso?: number; total?: number; pedidos?: number; }
interface EstadoItem { estado: string; cantidad: number; total: number; }
interface StockItem { producto: string; cantidad: number; minimo: number; deficit: number; }
interface PipelineItem { etapa: string; cantidad: number; valor: number; }

interface Props {
    kpis: Kpis;
    ventas_por_mes: MesSales[];
    top_productos: TopItem[];
    top_clientes: TopItem[];
    ventas_por_estado: EstadoItem[];
    stock_bajo: StockItem[];
    pipeline: PipelineItem[];
}

// ── Helpers ──────────────────────────────────────────────────────────────────
const fmt = (n: number, locale: string = 'es-CL', currencyCode: string = 'CLP') =>
    new Intl.NumberFormat(locale, { style: 'currency', currency: currencyCode, maximumFractionDigits: 0 }).format(n);

const fmtNum = (n: number, locale: string = 'es-CL') =>
    new Intl.NumberFormat(locale).format(n);

const shortMonth = (ym: string, locale: string = 'es-CL') => {
    const [y, m] = ym.split('-');
    return new Date(+y, +m - 1).toLocaleString(locale, { month: 'short' });
};

// ── Mini SVG bar chart ────────────────────────────────────────────────────────
function BarChart({ data, fmtFn, fmtNumFn, shortMonthFn }: { data: MesSales[]; fmtFn: (n: number) => string; fmtNumFn: (n: number) => string; shortMonthFn: (ym: string) => string }) {
    if (!data.length) return <div className="flex h-48 items-center justify-center text-muted-foreground text-sm">Sin datos</div>;
    const max = Math.max(...data.map(d => d.total), 1);
    return (
        <div className="flex h-48 items-end gap-1 pt-4 pb-6 relative">
            {data.map((d, i) => {
                const h = Math.max(4, (d.total / max) * 148);
                return (
                    <div key={i} className="group flex flex-1 flex-col items-center gap-1 relative">
                        {/* tooltip */}
                        <div className="absolute bottom-full mb-1 hidden group-hover:flex flex-col items-center z-10">
                            <div className="bg-foreground text-background text-[10px] rounded px-2 py-1 whitespace-nowrap shadow-lg">
                                {shortMonthFn(d.mes)}: {fmtFn(d.total)}<br />
                                <span className="opacity-70">{fmtNumFn(d.cantidad)} ventas</span>
                            </div>
                        </div>
                        <div
                            className="w-full rounded-t-sm bg-primary/80 hover:bg-primary transition-colors cursor-pointer"
                            style={{ height: `${h}px` }}
                        />
                        <span className="text-[9px] text-muted-foreground absolute -bottom-4">{shortMonthFn(d.mes)}</span>
                    </div>
                );
            })}
        </div>
    );
}

// ── Horizontal bar ────────────────────────────────────────────────────────────
function HBar({ label, value, max, color, sub, fmtFn, fmtNumFn }: { label: string; value: number; max: number; color: string; sub?: string; fmtFn?: (n: number) => string; fmtNumFn?: (n: number) => string }) {
    const pct = max > 0 ? (value / max) * 100 : 0;
    return (
        <div className="space-y-1">
            <div className="flex justify-between text-xs">
                <span className="font-medium truncate max-w-[60%]">{label}</span>
                <span className="text-muted-foreground">{sub ?? (fmtNumFn ? fmtNumFn(value) : fmtNum(value))}</span>
            </div>
            <div className="h-2 w-full rounded-full bg-muted">
                <div className={`h-2 rounded-full ${color} transition-all duration-500`} style={{ width: `${Math.max(2, pct)}%` }} />
            </div>
        </div>
    );
}

// ── KPI Card ──────────────────────────────────────────────────────────────────
function KPI({ title, value, sub, icon: Icon, color, trend, warn }: {
    title: string; value: string; sub: string;
    icon: React.ElementType; color: string; trend?: number; warn?: boolean;
}) {
    return (
        <Card className="border-none shadow-md ring-1 ring-border/40 hover:shadow-lg transition-all bg-card/60">
            <CardContent className="p-5">
                <div className="flex items-start justify-between">
                    <div className="space-y-1 min-w-0">
                        <p className="text-[11px] font-bold uppercase tracking-widest text-muted-foreground">{title}</p>
                        <p className="text-2xl font-black tracking-tight">{value}</p>
                        <div className="flex items-center gap-1 text-[11px]">
                            {trend !== undefined && (
                                trend >= 0
                                    ? <ArrowUpRight className="h-3 w-3 text-emerald-500" />
                                    : <ArrowDownRight className="h-3 w-3 text-red-500" />
                            )}
                            <span className={`${warn ? 'text-amber-600' : 'text-muted-foreground'}`}>{sub}</span>
                        </div>
                    </div>
                    <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ${color}`}>
                        <Icon className="h-5 w-5" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

// ── Badge pills for estado ────────────────────────────────────────────────────
const estadoColor: Record<string, string> = {
    pagada: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    pendiente: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
    anulada: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
    enviada: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    default: 'bg-muted text-muted-foreground',
};

// ── Main component ────────────────────────────────────────────────────────────
export default function Index({ kpis, ventas_por_mes, top_productos, top_clientes, ventas_por_estado, stock_bajo, pipeline }: Props) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('admin.reportes.viewAny');
    const maxProd = Math.max(...top_productos.map(p => p.unidades ?? 0), 1);
    const maxClient = Math.max(...top_clientes.map(c => c.total ?? 0), 1);
    const maxPipe = Math.max(...pipeline.map(p => p.valor), 1);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const fmtLocal = (n: number) => fmt(n, currency.locale, currency.code);
    const fmtNumLocal = (n: number) => fmtNum(n, currency.locale);
    const shortMonthLocal = (ym: string) => shortMonth(ym, currency.locale);

    if (!canAccess) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <>
            <Head title="Reportes BI" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-6 p-6 max-w-[1600px] mx-auto">
                    {/* Header */}
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight">Reportes y Análisis (BI)</h1>
                        <p className="text-muted-foreground">Métricas clave del negocio en tiempo real</p>
                    </div>

                    {/* ── KPI Grid ── */}
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <KPI
                            title="Ventas del Mes"
                            value={fmtLocal(kpis.ventas_mes)}
                            sub={`${kpis.ventas_cambio >= 0 ? '+' : ''}${kpis.ventas_cambio}% vs mes ant.`}
                            icon={DollarSign}
                            color="bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400"
                            trend={kpis.ventas_cambio}
                        />
                        <KPI
                            title="Clientes Activos"
                            value={fmtNumLocal(kpis.clientes_total)}
                            sub={`+${kpis.clientes_nuevos} nuevos este mes`}
                            icon={Users}
                            color="bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400"
                        />
                        <KPI
                            title="Compras del Mes"
                            value={fmtLocal(kpis.compras_mes)}
                            sub={`${kpis.productos_total} productos activos`}
                            icon={ShoppingCart}
                            color="bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-400"
                        />
                        <KPI
                            title="Stock Bajo"
                            value={fmtNumLocal(kpis.stock_bajo)}
                            sub="productos bajo mínimo"
                            icon={AlertTriangle}
                            color="bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400"
                            warn={kpis.stock_bajo > 0}
                        />
                        <KPI
                            title="Cotizaciones (Mes)"
                            value={fmtNumLocal(kpis.cotizaciones_mes)}
                            sub={`${fmtNumLocal(kpis.cotizaciones_total)} en total`}
                            icon={FileText}
                            color="bg-sky-100 text-sky-600 dark:bg-sky-900/40 dark:text-sky-400"
                        />
                        <KPI
                            title="Leads (Mes)"
                            value={fmtNumLocal(kpis.prospectos_mes)}
                            sub="nuevos prospectos"
                            icon={Target}
                            color="bg-pink-100 text-pink-600 dark:bg-pink-900/40 dark:text-pink-400"
                        />
                        <KPI
                            title="Oportunidades"
                            value={fmtNumLocal(kpis.oportunidades)}
                            sub="activas en pipeline"
                            icon={TrendingUp}
                            color="bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400"
                        />
                        <KPI
                            title="Tickets Abiertos"
                            value={fmtNumLocal(kpis.tickets_abiertos)}
                            sub="soporte pendiente"
                            icon={HeadphonesIcon}
                            color="bg-orange-100 text-orange-600 dark:bg-orange-900/40 dark:text-orange-400"
                            warn={kpis.tickets_abiertos > 5}
                        />
                    </div>

                    {/* ── Main grid ── */}
                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* Sales trend chart */}
                        <Card className="lg:col-span-2 border-none shadow-md ring-1 ring-border/40 bg-card/60">
                            <CardHeader className="pb-2">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            <BarChart3 className="h-4 w-4 text-primary" /> Ventas Mensuales
                                        </CardTitle>
                                        <CardDescription>Últimos 12 meses</CardDescription>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm font-bold">{fmtLocal(kpis.ventas_mes)}</p>
                                        <p className={`text-xs flex items-center gap-0.5 justify-end ${kpis.ventas_cambio >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                            {kpis.ventas_cambio >= 0 ? <ArrowUpRight className="h-3 w-3" /> : <ArrowDownRight className="h-3 w-3" />}
                                            {kpis.ventas_cambio}%
                                        </p>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="px-6 pb-6">
                                <BarChart data={ventas_por_mes} fmtFn={fmtLocal} fmtNumFn={fmtNumLocal} shortMonthFn={shortMonthLocal} />
                            </CardContent>
                        </Card>

                        {/* Ventas por estado */}
                        <Card className="border-none shadow-md ring-1 ring-border/40 bg-card/60">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Estado de Ventas</CardTitle>
                                <CardDescription>Distribución por estado</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {ventas_por_estado.length === 0 && (
                                    <p className="text-sm text-muted-foreground">Sin datos</p>
                                )}
                                {ventas_por_estado.map((e) => (
                                    <div key={e.estado} className="flex items-center justify-between text-sm">
                                        <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold capitalize ${estadoColor[e.estado] ?? estadoColor.default}`}>
                                            {e.estado}
                                        </span>
                                        <div className="text-right">
                                            <p className="font-semibold">{fmtLocal(e.total)}</p>
                                            <p className="text-[11px] text-muted-foreground">{fmtNumLocal(e.cantidad)} pedidos</p>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    {/* ── Second row ── */}
                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* Top productos */}
                        <Card className="border-none shadow-md ring-1 ring-border/40 bg-card/60">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Package className="h-4 w-4 text-primary" /> Top 5 Productos
                                </CardTitle>
                                <CardDescription>Por unidades vendidas</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {top_productos.length === 0 && <p className="text-sm text-muted-foreground">Sin datos</p>}
                                {top_productos.map((p, i) => (
                                    <HBar
                                        key={i}
                                        label={p.nombre}
                                        value={p.unidades ?? 0}
                                        max={maxProd}
                                        color="bg-primary"
                                        sub={`${fmtNumLocal(p.unidades ?? 0)} uds`}
                                    />
                                ))}
                            </CardContent>
                        </Card>

                        {/* Top clientes */}
                        <Card className="border-none shadow-md ring-1 ring-border/40 bg-card/60">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Users className="h-4 w-4 text-blue-500" /> Top 5 Clientes
                                </CardTitle>
                                <CardDescription>Por ingresos generados</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {top_clientes.length === 0 && <p className="text-sm text-muted-foreground">Sin datos</p>}
                                {top_clientes.map((c, i) => (
                                    <HBar
                                        key={i}
                                        label={c.nombre}
                                        value={c.total ?? 0}
                                        max={maxClient}
                                        color="bg-blue-500"
                                        sub={fmtLocal(c.total ?? 0)}
                                    />
                                ))}
                            </CardContent>
                        </Card>

                        {/* Pipeline CRM */}
                        <Card className="border-none shadow-md ring-1 ring-border/40 bg-card/60">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Target className="h-4 w-4 text-pink-500" /> Pipeline CRM
                                </CardTitle>
                                <CardDescription>Por etapa de oportunidades</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {pipeline.length === 0 && <p className="text-sm text-muted-foreground">Sin datos</p>}
                                {pipeline.map((p, i) => (
                                    <HBar
                                        key={i}
                                        label={p.etapa}
                                        value={p.valor}
                                        max={maxPipe}
                                        color="bg-pink-500"
                                        sub={`${fmtNumLocal(p.cantidad)} oport. · ${fmtLocal(p.valor)}`}
                                    />
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    {/* ── Stock bajo ── */}
                    {stock_bajo.length > 0 && (
                        <Card className="border-none shadow-md ring-1 ring-amber-500/30 bg-amber-50/50 dark:bg-amber-900/10">
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base text-amber-700 dark:text-amber-400">
                                            <AlertTriangle className="h-4 w-4" /> Alertas de Stock
                                        </CardTitle>
                                        <CardDescription>Productos por debajo del mínimo</CardDescription>
                                    </div>
                                    <div className="flex items-center gap-1 rounded-lg border p-1">
                                        <Button
                                            variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                            size="sm"
                                            onClick={() => setViewMode('table')}
                                        >
                                            <List className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                            size="sm"
                                            onClick={() => setViewMode('cards')}
                                        >
                                            <LayoutGrid className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {viewMode === 'table' ? (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-xs font-semibold text-muted-foreground uppercase">
                                                    <th className="pb-2 text-left">Producto</th>
                                                    <th className="pb-2 text-right">Stock</th>
                                                    <th className="pb-2 text-right">Mínimo</th>
                                                    <th className="pb-2 text-right">Déficit</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border/50">
                                                {stock_bajo.map((s, i) => (
                                                    <tr key={i}>
                                                        <td className="py-2 font-medium">{s.producto}</td>
                                                        <td className="py-2 text-right text-red-600 font-bold">{fmtNumLocal(s.cantidad)}</td>
                                                        <td className="py-2 text-right text-muted-foreground">{fmtNumLocal(s.minimo)}</td>
                                                        <td className="py-2 text-right">
                                                            <span className="rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-xs font-bold dark:bg-red-900/40 dark:text-red-400">
                                                                -{fmtNumLocal(s.deficit)}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        {stock_bajo.map((s, i) => (
                                            <Card key={i}>
                                                <CardContent className="p-4">
                                                    <div className="mb-3 flex items-start justify-between">
                                                        <h3 className="font-semibold">{s.producto}</h3>
                                                        <span className="rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-xs font-bold dark:bg-red-900/40 dark:text-red-400">
                                                             -{fmtNumLocal(s.deficit)}
                                                         </span>
                                                     </div>
                                                     <div className="space-y-1 text-sm">
                                                         <div className="flex justify-between">
                                                             <span className="text-muted-foreground">Stock</span>
                                                             <span className="font-bold text-red-600">{fmtNumLocal(s.cantidad)}</span>
                                                         </div>
                                                         <div className="flex justify-between">
                                                             <span className="text-muted-foreground">Mínimo</span>
                                                             <span>{fmtNumLocal(s.minimo)}</span>
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </AppLayout>
        </>
    );
}
