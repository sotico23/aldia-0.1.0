import { Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    ArrowDownRight,
    DollarSign,
    TrendingUp,
    Zap,
    Wallet,
    Warehouse,
    ShoppingCart,
    Calendar,
    Briefcase,
    PieChart,
    BarChart3,
    Users,
    Shield,
    RefreshCw,
    CheckCircle2,
    XCircle,
    AlertTriangle
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useCountry } from '@/hooks/use-country';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

interface MesData {
    mes: string;
    ingresos: number;
    egresos: number;
    balance: number;
}

interface Proyeccion {
    mes: string;
    total: number;
    es_proyeccion: boolean;
}

interface StatDetail {
    label: string;
    value: string | number;
    subValue: string;
    trend: 'up' | 'down' | 'neutral';
    color: string;
    url?: string;
}

interface TopProducto {
    nombre: string;
    total_cantidad: number;
    total_venta: number;
}

interface ProductoCritico {
    id: number;
    nombre: string;
    codigo: string;
    stock_minimo: number;
    stock_actual: number;
}

interface DashboardProps {
    stats: Record<string, StatDetail>;
    cashFlowData: MesData[];
    proyecciones: Proyeccion[];
    topProductos: TopProducto[];
    siiStats: {
        ambiente: string;
        emisor: {
            rut: string;
            razon_social: string;
        } | null;
        token_activo: boolean;
        folios_disponibles: Array<{
            tipo: number;
            restantes: number;
        }>;
    } | null;
    esSuperAdmin: boolean;
    esAdmin: boolean;
    esEmpleado: boolean;
    esCliente: boolean;
    mensajesSinLeer: number;
    userName: string;
    productosCriticos?: ProductoCritico[];
}

const KPICard = ({
    stat,
    icon: Icon,
}: {
    stat: StatDetail;
    icon: React.ElementType;
}) => {
    const colorClasses: Record<string, string> = {
        indigo: 'bg-indigo-500/20 text-indigo-500',
        amber: 'bg-amber-500/20 text-amber-500',
        emerald: 'bg-emerald-500/20 text-emerald-500',
        blue: 'bg-blue-500/20 text-blue-500',
        rose: 'bg-rose-500/20 text-rose-500',
        orange: 'bg-orange-500/20 text-orange-500',
    };

    return (
        <Link href={stat.url || '#'}>
            <div className="group relative overflow-hidden rounded-3xl border border-white/20 bg-white/10 p-6 shadow-2xl backdrop-blur-xl transition-all hover:scale-[1.02] hover:ring-2 hover:ring-primary/20 dark:border-white/10 dark:bg-zinc-900/40">
                <div className="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-primary/10 blur-3xl" />
                <div className="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl" />
                <div className="relative z-10">
                    <div className="flex items-start justify-between">
                        <div
                            className={`rounded-2xl p-3 ${colorClasses[stat.color] || colorClasses.indigo}`}
                        >
                            <Icon className="h-6 w-6" />
                        </div>
                        {stat.trend !== 'neutral' && (
                            <Badge
                                variant={
                                    stat.trend === 'up'
                                        ? 'default'
                                        : 'destructive'
                                }
                                className="bg-opacity-20 text-[10px] font-bold"
                            >
                                {stat.trend === 'up' ? (
                                    <ArrowUpRight className="mr-1 h-3 w-3" />
                                ) : (
                                    <ArrowDownRight className="mr-1 h-3 w-3" />
                                )}
                                {stat.trend === 'up' ? '+5.2%' : '-2.1%'}
                            </Badge>
                        )}
                    </div>
                    <div className="mt-4">
                        <p className="text-sm font-medium tracking-wider text-muted-foreground uppercase">
                            {stat.label}
                        </p>
                        <h3 className="mt-1 text-2xl font-black tracking-tight">
                            {stat.value}
                        </h3>
                        <p className="mt-1 text-xs text-muted-foreground opacity-70">
                            {stat.subValue}
                        </p>
                    </div>
                </div>
            </div>
        </Link>
    );
};

const CashFlowChart = ({ data }: { data: MesData[] }) => {
    if (!data.length)
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Sin datos
            </div>
        );

    const max = Math.max(
        ...data.map((d) => d.ingresos),
        ...data.map((d) => d.egresos),
        1,
    );
    const height = 200;
    const width = 600;

    const getPath = (key: 'ingresos' | 'egresos') => {
        if (data.length < 2) return '';
        return data
            .map((d, i) => {
                const x = (i / (data.length - 1)) * width;
                const y = height - (d[key] / max) * height;
                return `${x},${y}`;
            })
            .join(' ');
    };

    return (
        <div className="relative h-[240px] w-full pt-4">
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="h-full w-full overflow-visible"
                preserveAspectRatio="none"
            >
                <defs>
                    <linearGradient
                        id="gradIngresos"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stopColor="rgb(99, 102, 241)"
                            stopOpacity="0.3"
                        />
                        <stop
                            offset="100%"
                            stopColor="rgb(99, 102, 241)"
                            stopOpacity="0"
                        />
                    </linearGradient>
                    <linearGradient
                        id="gradEgresos"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stopColor="rgb(244, 63, 94)"
                            stopOpacity="0.3"
                        />
                        <stop
                            offset="100%"
                            stopColor="rgb(244, 63, 94)"
                            stopOpacity="0"
                        />
                    </linearGradient>
                </defs>
                <path
                    d={`M 0 ${height} L ${getPath('ingresos')} L ${width} ${height} Z`}
                    fill="url(#gradIngresos)"
                />
                <path
                    d={`M 0 ${height} L ${getPath('egresos')} L ${width} ${height} Z`}
                    fill="url(#gradEgresos)"
                />
                <path
                    d={`M ${getPath('ingresos')}`}
                    fill="none"
                    stroke="rgb(99, 102, 241)"
                    strokeWidth="3"
                    strokeLinecap="round"
                />
                <path
                    d={`M ${getPath('egresos')}`}
                    fill="none"
                    stroke="rgb(244, 63, 94)"
                    strokeWidth="3"
                    strokeLinecap="round"
                />
            </svg>
            <div className="mt-2 flex justify-between text-[10px] font-bold text-muted-foreground uppercase opacity-60">
                {data.map((d, i) => (
                    <span key={i}>{d.mes}</span>
                ))}
            </div>
        </div>
    );
};

const TopProductsChart = ({ data }: { data: TopProducto[] }) => {
    if (!data.length)
        return (
            <div className="flex h-40 items-center justify-center text-muted-foreground italic">
                Sin datos
            </div>
        );

    const total = data.reduce((acc, curr) => acc + Number(curr.total_venta), 0);
    const colors = ['#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#f43f5e'];

    return (
        <div className="flex items-center gap-4">
            <div className="relative h-32 w-32 shrink-0">
                <svg viewBox="0 0 32 32" className="h-full w-full -rotate-90">
                    {data.map((p, i) => {
                        const percent = (Number(p.total_venta) / total) * 100;
                        const strokeDasharray = `${percent} ${100 - percent}`;
                        const cumulativePercent = data.slice(0, i).reduce((sum, item) => sum + (Number(item.total_venta) / total) * 100, 0);
                        const strokeDashoffset = -cumulativePercent;
                        return (
                            <circle
                                key={i}
                                cx="16"
                                cy="16"
                                r="15.915"
                                fill="transparent"
                                stroke={colors[i % colors.length]}
                                strokeWidth="4"
                                strokeDasharray={strokeDasharray}
                                strokeDashoffset={strokeDashoffset}
                            />
                        );
                    })}
                </svg>
                <div className="absolute inset-0 flex items-center justify-center">
                    <PieChart className="h-5 w-5 text-primary" />
                </div>
            </div>
            <div className="flex-1 space-y-2 overflow-hidden">
                {data.slice(0, 5).map((p, i) => (
                    <div
                        key={i}
                        className="flex items-center justify-between text-xs"
                    >
                        <div className="flex items-center gap-2 truncate">
                            <div
                                className="h-2 w-2 rounded-full"
                                style={{
                                    backgroundColor: colors[i % colors.length],
                                }}
                            />
                            <span className="truncate font-medium">
                                {p.nombre}
                            </span>
                        </div>
                        <span className="text-[10px] font-bold opacity-60">
                            {((Number(p.total_venta) / total) * 100).toFixed(0)}
                            %
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default function Dashboard({
    stats,
    cashFlowData,
    proyecciones,
    topProductos,
    userName,
    mensajesSinLeer,
    siiStats,
    esAdmin,
    esSuperAdmin,
    productosCriticos,
}: DashboardProps) {
    const [refreshing, setRefreshing] = useState(false);
    const { code: countryCode, currency } = useCountry();

    const handleRefreshToken = () => {
        setRefreshing(true);
        router.post(
            '/sii/token/refrescar',
            {},
            {
                onSuccess: () => {
                    toast.success('Token del SII actualizado');
                    setRefreshing(false);
                },
                onError: () => {
                    toast.error('Error al actualizar token');
                    setRefreshing(false);
                },
                onFinish: () => setRefreshing(false),
            },
        );
    };

    const getTipoDteLabel = (tipo: number) => {
        const labels: Record<number, string> = {
            33: 'Factura Electrónica',
            34: 'Factura Exenta',
            61: 'Nota de Crédito',
            56: 'Nota de Débito',
            52: 'Guía de Despacho',
        };
        return labels[tipo] || `DTE ${tipo}`;
    };
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight">
                            Hola,{' '}
                            <span className="text-primary">
                                {userName.split(' ')[0]}
                            </span>
                        </h1>
                        <p className="mt-1 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                             <Calendar className="h-4 w-4" />
                             {new Date().toLocaleDateString(currency.locale, {
                                 weekday: 'long',
                                 day: 'numeric',
                                 month: 'long',
                                 year: 'numeric',
                             })}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href="/mensajes" className="relative">
                            <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-muted transition-colors hover:bg-primary">
                                <Zap className="h-5 w-5" />
                            </div>
                            {mensajesSinLeer > 0 && (
                                <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white">
                                    {mensajesSinLeer}
                                </span>
                            )}
                        </Link>
                    </div>
                </div>

                {/* KPIs Grid */}
                <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {stats.ventas && (
                        <KPICard stat={stats.ventas} icon={DollarSign} />
                    )}
                    {stats.utilidad && (
                        <KPICard stat={stats.utilidad} icon={TrendingUp} />
                    )}
                    {stats.cartera && (
                        <KPICard stat={stats.cartera} icon={Wallet} />
                    )}
                    {stats.pagables && (
                        <KPICard stat={stats.pagables} icon={ShoppingCart} />
                    )}
                </div>

                {/* Low Stock Alerts */}
                {productosCriticos && productosCriticos.length > 0 && (
                    <div className="mb-8 overflow-hidden rounded-3xl border border-rose-500/20 bg-rose-500/5 p-6 backdrop-blur-xl transition-all">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-500/20 text-rose-500">
                                <AlertTriangle className="h-6 w-6" />
                            </div>
                            <div className="flex-1">
                                <h2 className="text-xl font-black tracking-tight text-rose-600">
                                    Alertas de Stock Bajo
                                </h2>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Tienes {productosCriticos.length} productos
                                    con existencias por debajo del límite
                                    mínimo.
                                </p>
                            </div>
                            <Link href="/inventarios">
                                <button className="rounded-xl bg-rose-500 px-6 py-2 text-sm font-black text-white shadow-lg shadow-rose-500/20 transition-all hover:scale-105 active:scale-95">
                                    Gestionar Inventario
                                </button>
                            </Link>
                        </div>

                        <div className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {productosCriticos.map((p) => (
                                <div
                                    key={p.id}
                                    className="flex items-center justify-between rounded-2xl border border-rose-500/10 bg-white/5 p-4 transition-all hover:bg-white/10"
                                >
                                    <div className="overflow-hidden">
                                        <p className="truncate text-sm font-black text-rose-900 dark:text-rose-100">
                                            {p.nombre}
                                        </p>
                                        <p className="text-[10px] font-bold text-muted-foreground uppercase">
                                            SKU: {p.codigo}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-lg font-black text-rose-600">
                                            {Math.floor(p.stock_actual)}
                                        </p>
                                        <p className="text-[10px] font-bold text-muted-foreground">
                                            Mín: {p.stock_minimo}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Charts Row */}
                <div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Cash Flow */}
                    <div className="lg:col-span-2">
                        <Card className="h-full border-none bg-card/50 shadow-xl backdrop-blur-sm">
                            <CardHeader className="flex flex-row items-center gap-2 pb-2">
                                <BarChart3 className="h-5 w-5 text-primary" />
                                <CardTitle className="text-lg font-black">
                                    Flujo de Caja
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <CashFlowChart data={cashFlowData || []} />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Top Products */}
                    <div>
                        <Card className="h-full border-none bg-card/50 shadow-xl backdrop-blur-sm">
                            <CardHeader className="flex flex-row items-center gap-2 pb-2">
                                <PieChart className="h-5 w-5 text-primary" />
                                <CardTitle className="text-lg font-black">
                                    Top Productos
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <TopProductsChart data={topProductos || []} />
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Quick Actions & Proyecciones */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Quick Links */}
                    <Card className="border-none bg-card/50 shadow-xl backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center gap-2">
                            <Briefcase className="h-5 w-5 text-primary" />
                            <CardTitle className="text-lg font-black">
                                Acciones Rápidas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-3">
                                <Link
                                    href="/facturacion"
                                    className="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-accent"
                                >
                                    <div className="rounded-lg bg-primary/10 p-2">
                                        <DollarSign className="h-4 w-4 text-primary" />
                                    </div>
                                    <div>
                                        <p className="font-medium">
                                            Nueva Factura
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Documentos
                                        </p>
                                    </div>
                                </Link>
                                <Link
                                    href="/ventas"
                                    className="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-accent"
                                >
                                    <div className="rounded-lg bg-indigo-500/10 p-2">
                                        <Activity className="h-4 w-4 text-indigo-500" />
                                    </div>
                                    <div>
                                        <p className="font-medium">
                                            Punto de Venta
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            POS
                                        </p>
                                    </div>
                                </Link>
                                <Link
                                    href="/productos"
                                    className="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-accent"
                                >
                                    <div className="rounded-lg bg-orange-500/10 p-2">
                                        <Warehouse className="h-4 w-4 text-orange-500" />
                                    </div>
                                    <div>
                                        <p className="font-medium">
                                            Inventario
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Stock
                                        </p>
                                    </div>
                                </Link>
                                <Link
                                    href="/clientes"
                                    className="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-accent"
                                >
                                    <div className="rounded-lg bg-blue-500/10 p-2">
                                        <Users className="h-4 w-4 text-blue-500" />
                                    </div>
                                    <div>
                                        <p className="font-medium">Clientes</p>
                                        <p className="text-xs text-muted-foreground">
                                            Cartera
                                        </p>
                                    </div>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Proyecciones */}
                    <Card className="border-none bg-card/50 shadow-xl backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center gap-2">
                            <Zap className="h-5 w-5 text-amber-500" />
                            <CardTitle className="text-lg font-black">
                                Proyección
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {(proyecciones || [])
                                    .slice(0, 4)
                                    .map((p, i) => (
                                        <div
                                            key={i}
                                            className="flex items-center justify-between"
                                        >
                                            <span className="text-sm font-medium text-muted-foreground uppercase">
                                                {p.mes}
                                            </span>
                                            <span className="font-black">
                                                 $
                                                 {new Intl.NumberFormat(
                                                     currency.locale,
                                                 ).format(p.total)}
                                            </span>
                                        </div>
                                    ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* SII Operational Status */}
                {(esAdmin || esSuperAdmin) && siiStats && (
                    <div className="mt-8">
                        <Card className="border-none bg-zinc-900/10 shadow-2xl backdrop-blur-2xl transition-all hover:ring-2 hover:ring-primary/10 dark:bg-zinc-900/60">
                            <CardHeader className="flex flex-row items-center justify-between pb-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-2xl bg-primary/20 p-3">
                                        <Shield className="h-6 w-6 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-xl font-black">
                                            Estado Operacional SII
                                        </CardTitle>
                                        <div className="mt-1 flex items-center gap-2">
                                            <Badge
                                                variant={
                                                    siiStats.ambiente ===
                                                    'produccion'
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                                className="text-[10px] font-bold tracking-tighter uppercase"
                                            >
                                                {siiStats.ambiente}
                                            </Badge>
                                            <div className="flex items-center gap-1 text-[10px] font-bold text-muted-foreground uppercase">
                                                {siiStats.token_activo ? (
                                                    <span className="flex items-center gap-1 text-emerald-500">
                                                        <CheckCircle2 className="h-3 w-3" />{' '}
                                                        Token Activo
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center gap-1 text-rose-500">
                                                        <XCircle className="h-3 w-3" />{' '}
                                                        Sin Token
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {siiStats?.emisor && (
                                    <button
                                        onClick={handleRefreshToken}
                                        disabled={refreshing}
                                        className="flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-foreground shadow-lg transition-all hover:scale-105 active:scale-95 disabled:opacity-50"
                                    >
                                        <RefreshCw
                                            className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`}
                                        />
                                        {refreshing
                                            ? 'Solicitando...'
                                            : 'Renovar Token'}
                                    </button>
                                )}
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                    <div className="space-y-4">
                                        <h4 className="text-xs font-black tracking-widest text-muted-foreground uppercase">
                                            Información Emisor
                                        </h4>
                                        {siiStats.emisor ? (
                                            <div className="rounded-2xl border border-white/5 bg-white/5 p-4">
                                                <p className="truncate text-lg font-bold">
                                                    {
                                                        siiStats.emisor
                                                            .razon_social
                                                    }
                                                </p>
                                                <p className="mt-1 text-sm font-medium text-primary">
                                                    {siiStats.emisor.rut}
                                                </p>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground italic">
                                                Emisor no configurado
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-4 md:col-span-2">
                                        <h4 className="text-xs font-black tracking-widest text-muted-foreground uppercase">
                                            Folios Disponibles (CAF)
                                        </h4>
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            {siiStats.folios_disponibles
                                                .length > 0 ? (
                                                siiStats.folios_disponibles.map(
                                                    (caf, idx) => (
                                                        <div
                                                            key={idx}
                                                            className="flex items-center justify-between rounded-2xl border border-white/5 bg-white/5 p-4"
                                                        >
                                                            <div>
                                                                <p className="text-[10px] font-black text-muted-foreground uppercase">
                                                                    {getTipoDteLabel(
                                                                        caf.tipo,
                                                                    )}
                                                                </p>
                                                                <p className="text-lg font-black">
                                                                    {
                                                                        caf.restantes
                                                                    }
                                                                </p>
                                                            </div>
                                                            <div
                                                                className={`h-2 w-2 rounded-full ${caf.restantes > 50 ? 'bg-emerald-500' : 'bg-rose-500'}`}
                                                            />
                                                        </div>
                                                    ),
                                                )
                                            ) : (
                                                <p className="text-sm text-muted-foreground italic">
                                                    No hay folios autorizados
                                                    cargados
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
