import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    Flag,
    Loader2,
    Package,
    ReceiptText,
    RefreshCw,
    ShoppingBag,
    ShoppingCart,
    Sparkles,
    Store,
    Target,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { show as ventaDetalle } from '@/actions/App/Http/Controllers/Backend/PosVentasController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCountry } from '@/hooks/use-country';
import AppLayout from '@/layouts/app-layout';
import { cn, formatCurrency } from '@/lib/utils';

interface VentaResumen {
    id: number;
    documento: string;
    fecha: string | null;
    hora: string | null;
    cliente: string;
    tipo_documento: string;
    tipo_documento_label: string;
    metodo_pago: string;
    metodo_pago_label: string;
    total: number;
    estado: string;
}

interface TopProducto {
    id: number;
    nombre: string;
    codigo: string | null;
    cantidad: number;
    total: number;
}

interface Tip {
    titulo: string;
    descripcion: string;
    icono: string;
    tono: string;
}

interface Kpis {
    ventas_hoy: { total: number; documentos: number };
    ventas_mes: { total: number; documentos: number };
    ganancia_hoy: number;
    ganancia_mes: number;
    ticket_promedio: number;
    productos_hoy: { unidades: number; top: string | null };
}

interface ItemDetalle {
    id: number;
    producto: string;
    codigo: string | null;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    comision_cajero: number;
    cantidad_retornada: number;
}

interface DetalleVenta {
    id: number;
    documento: string;
    tipo_documento: string;
    metodo_pago: string;
    metodo_pago_label: string;
    estado: string;
    fecha_hora: string;
    cliente: {
        nombre: string;
        rut: string | null;
        direccion: string | null;
        comuna: string | null;
        ciudad: string | null;
    } | null;
    empleado: string | null;
    almacen: string | null;
    subtotal: number;
    iva: number;
    descuento: number;
    monto_descuento_cupon: number;
    delivery_cost: number;
    total: number;
    cupon: { codigo: string; tipo: string } | null;
    items: ItemDetalle[];
}

interface Props {
    cajero: {
        id: number;
        nombre: string;
        nombre_empleado: string | null;
        cargo: string | null;
        almacen: string | null;
    };
    contexto: {
        fecha: string;
        fecha_larga: string;
        hora: string;
        turno: { nombre: string; rango: string };
    };
    kpis: Kpis;
    top_productos: TopProducto[];
    ultimas_ventas: VentaResumen[];
    stock_critico: number;
    tips: Tip[];
    moneda: string;
}

const ICONOS_TIPS: Record<string, LucideIcon> = {
    sparkles: Sparkles,
    'trending-up': TrendingUp,
    package: Package,
    'alert-triangle': AlertTriangle,
    target: Target,
    flag: Flag,
};

const TONOS_TIPS: Record<
    string,
    { card: string; icono: string; punto: string; etiqueta: string }
> = {
    success: {
        card: 'from-emerald-50 via-white to-white dark:from-emerald-950/40 dark:via-background dark:to-background',
        icono: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
        punto: 'bg-emerald-500',
        etiqueta: 'text-emerald-700 dark:text-emerald-300',
    },
    info: {
        card: 'from-emerald-50 via-white to-white dark:from-emerald-950/40 dark:via-background dark:to-background',
        icono: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
        punto: 'bg-emerald-500',
        etiqueta: 'text-emerald-700 dark:text-emerald-300',
    },
    warning: {
        card: 'from-amber-50 via-white to-white dark:from-amber-950/40 dark:via-background dark:to-background',
        icono: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
        punto: 'bg-amber-500',
        etiqueta: 'text-amber-700 dark:text-amber-300',
    },
    neutral: {
        card: 'from-slate-50 via-white to-white dark:from-slate-900/60 dark:via-background dark:to-background',
        icono: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        punto: 'bg-slate-500',
        etiqueta: 'text-slate-700 dark:text-slate-300',
    },
};

const ESTILOS_METODO: Record<string, string> = {
    efectivo:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
    tarjeta:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
    transferencia:
        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300',
    vale: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
    binance:
        'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300',
};

function KpiCard({
    titulo,
    valor,
    detalle,
    icono: Icono,
    acento,
}: {
    titulo: string;
    valor: string;
    detalle: string;
    icono: LucideIcon;
    acento: string;
}) {
    return (
        <Card className="overflow-hidden border-border/60 shadow-sm">
            <CardContent className="p-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                            {titulo}
                        </p>
                        <p className="mt-2 truncate text-2xl font-black tracking-tight text-foreground">
                            {valor}
                        </p>
                        <p className="mt-1 line-clamp-1 text-xs text-muted-foreground">
                            {detalle}
                        </p>
                    </div>
                    <div
                        className={cn(
                            'flex size-11 shrink-0 items-center justify-center rounded-xl',
                            acento,
                        )}
                    >
                        <Icono className="size-5" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function formatearCantidad(cantidad: number): string {
    return cantidad.toLocaleString('es-CL', {
        maximumFractionDigits: 2,
    });
}

export default function CajeroDashboard({
    cajero,
    contexto,
    kpis,
    top_productos,
    ultimas_ventas,
    stock_critico,
    tips,
    moneda,
}: Props) {
    const { currency } = useCountry();
    const currencyCode = currency.code || moneda;

    const [tipActivo, setTipActivo] = useState(0);
    const [refrescando, setRefrescando] = useState(false);
    const [detalleOpen, setDetalleOpen] = useState(false);
    const [detalle, setDetalle] = useState<DetalleVenta | null>(null);
    const [detalleCargando, setDetalleCargando] = useState(false);
    const [detalleError, setDetalleError] = useState<string | null>(null);

    const totalTips = tips.length;
    const tip = tips[tipActivo] ?? tips[0];
    const tono = TONOS_TIPS[tip?.tono ?? 'info'] ?? TONOS_TIPS.info;
    const TipIcono = ICONOS_TIPS[tip?.icono ?? 'sparkles'] ?? Sparkles;

    const maxCantidad = Math.max(
        ...top_productos.map((producto) => producto.cantidad),
        1,
    );

    const refrescar = () => {
        router.reload({
            only: [
                'kpis',
                'top_productos',
                'ultimas_ventas',
                'stock_critico',
                'tips',
                'contexto',
            ],
            onStart: () => setRefrescando(true),
            onFinish: () => setRefrescando(false),
        });
    };

    const moverTip = (direccion: number) => {
        if (totalTips === 0) {
            return;
        }

        setTipActivo((actual) => (actual + direccion + totalTips) % totalTips);
    };

    const abrirDetalle = async (venta: VentaResumen) => {
        setDetalleOpen(true);
        setDetalle(null);
        setDetalleError(null);
        setDetalleCargando(true);

        try {
            const response = await fetch(ventaDetalle.url(venta.id), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('No fue posible cargar el detalle.');
            }

            const data = (await response.json()) as { venta: DetalleVenta };
            setDetalle(data.venta);
        } catch {
            setDetalleError(
                'No se pudo cargar el detalle de la venta. Intenta nuevamente.',
            );
        } finally {
            setDetalleCargando(false);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Mi Caja', href: '/mi-caja' },
            ]}
        >
            <Head title="Mi Caja" />

            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <Store className="size-6" />
                        </div>
                        <div>
                            <p className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                Panel individual de caja
                            </p>
                            <h1 className="mt-1 text-2xl font-black tracking-tight md:text-3xl">
                                Hola, {cajero.nombre}
                            </h1>
                            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                <span className="inline-flex items-center gap-1.5 capitalize">
                                    <CalendarDays className="size-3.5" />
                                    {contexto.fecha_larga}
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <Clock className="size-3.5" />
                                    {contexto.hora}
                                </span>
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-muted/40 px-2 py-0.5 font-semibold text-foreground">
                                    {contexto.turno.nombre} ·{' '}
                                    {contexto.turno.rango}
                                </span>
                                {cajero.almacen && (
                                    <span className="hidden items-center gap-1.5 sm:inline-flex">
                                        <ShoppingBag className="size-3.5" />
                                        {cajero.almacen}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={refrescar}
                            disabled={refrescando}
                        >
                            {refrescando ? (
                                <Loader2 className="animate-spin" />
                            ) : (
                                <RefreshCw />
                            )}
                            Actualizar
                        </Button>
                        <Button asChild size="sm">
                            <Link href="/pos">
                                <ShoppingCart />
                                Abrir POS
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <KpiCard
                        titulo="Ventas de hoy"
                        valor={formatCurrency(
                            kpis.ventas_hoy.total,
                            currencyCode,
                        )}
                        detalle={`${kpis.ventas_hoy.documentos} ${
                            kpis.ventas_hoy.documentos === 1
                                ? 'documento emitido'
                                : 'documentos emitidos'
                        } hoy`}
                        icono={Banknote}
                        acento="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300"
                    />
                    <KpiCard
                        titulo="Ventas del mes"
                        valor={formatCurrency(
                            kpis.ventas_mes.total,
                            currencyCode,
                        )}
                        detalle={`${kpis.ventas_mes.documentos} documentos en el mes en curso`}
                        icono={Wallet}
                        acento="bg-primary/10 text-primary"
                    />
                    <KpiCard
                        titulo="Ticket promedio"
                        valor={formatCurrency(
                            kpis.ticket_promedio,
                            currencyCode,
                        )}
                        detalle="Promedio por transacción de hoy"
                        icono={ReceiptText}
                        acento="bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-300"
                    />
                    <KpiCard
                        titulo="Mi ganancia de hoy"
                        valor={formatCurrency(kpis.ganancia_hoy, currencyCode)}
                        detalle="Comisiones de ventas vigentes emitidas hoy"
                        icono={Banknote}
                        acento="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300"
                    />
                    <KpiCard
                        titulo="Mi ganancia del mes"
                        valor={formatCurrency(kpis.ganancia_mes, currencyCode)}
                        detalle="Comisiones acumuladas en el mes en curso"
                        icono={TrendingUp}
                        acento="bg-teal-100 text-teal-700 dark:bg-teal-900/50 dark:text-teal-300"
                    />
                    <KpiCard
                        titulo="Productos vendidos hoy"
                        valor={`${formatearCantidad(kpis.productos_hoy.unidades)} u.`}
                        detalle={
                            kpis.productos_hoy.top
                                ? `Más vendido del mes: ${kpis.productos_hoy.top}`
                                : 'Aún sin ventas registradas hoy'
                        }
                        icono={Package}
                        acento="bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300"
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <Card className="border-border/60 shadow-sm xl:col-span-2">
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <TrendingUp className="size-4 text-primary" />
                                    Mis productos más vendidos
                                </CardTitle>
                                <CardDescription>
                                    Ranking del mes en curso según tus propias
                                    ventas.
                                </CardDescription>
                            </div>
                            <Badge variant="outline">
                                {top_productos.length} productos
                            </Badge>
                        </CardHeader>
                        <CardContent>
                            {top_productos.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/70 py-10 text-center">
                                    <Package className="size-8 text-muted-foreground/60" />
                                    <p className="text-sm font-semibold">
                                        Aún no hay productos vendidos este mes
                                    </p>
                                    <p className="max-w-sm text-xs text-muted-foreground">
                                        Cuando registres ventas en el POS, aquí
                                        verás tu ranking personal de artículos
                                        con mayor salida.
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {top_productos.map((producto, indice) => (
                                        <div
                                            key={producto.id}
                                            className="space-y-1.5"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-black text-primary">
                                                    {indice + 1}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-semibold">
                                                        {producto.nombre}
                                                    </p>
                                                    {producto.codigo && (
                                                        <p className="text-[10px] text-muted-foreground">
                                                            {producto.codigo}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-bold">
                                                        {formatearCantidad(
                                                            producto.cantidad,
                                                        )}{' '}
                                                        u.
                                                    </p>
                                                    <p className="text-[10px] text-muted-foreground">
                                                        {formatCurrency(
                                                            producto.total,
                                                            currencyCode,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="ml-10 h-1.5 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary/70"
                                                    style={{
                                                        width: `${Math.max(
                                                            (producto.cantidad /
                                                                maxCantidad) *
                                                                100,
                                                            4,
                                                        )}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card
                        className={cn(
                            'border-border/60 bg-gradient-to-br shadow-sm',
                            tono.card,
                        )}
                    >
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Sparkles className="size-4 text-primary" />
                                    Consejos para tu día
                                </CardTitle>
                                <CardDescription>
                                    Recomendaciones según tu operación real.
                                </CardDescription>
                            </div>
                            {stock_critico > 0 && (
                                <Badge
                                    variant="outline"
                                    className="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                                >
                                    <AlertTriangle className="size-3" />
                                    {stock_critico} stock crítico
                                </Badge>
                            )}
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col justify-between gap-4">
                            {tip ? (
                                <div className="space-y-3">
                                    <div
                                        className={cn(
                                            'flex size-11 items-center justify-center rounded-xl',
                                            tono.icono,
                                        )}
                                    >
                                        <TipIcono className="size-5" />
                                    </div>
                                    <div>
                                        <p
                                            className={cn(
                                                'text-[11px] font-bold tracking-wider uppercase',
                                                tono.etiqueta,
                                            )}
                                        >
                                            Consejo {tipActivo + 1} de{' '}
                                            {totalTips}
                                        </p>
                                        <p className="mt-1 text-base font-bold">
                                            {tip.titulo}
                                        </p>
                                        <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                            {tip.descripcion}
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Registra ventas para recibir consejos
                                    personalizados.
                                </p>
                            )}

                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-1.5">
                                    {tips.map((item, indice) => (
                                        <button
                                            key={`${item.titulo}-${indice}`}
                                            type="button"
                                            aria-label={`Ver consejo ${indice + 1}`}
                                            onClick={() => setTipActivo(indice)}
                                            className={cn(
                                                'h-1.5 rounded-full transition-all',
                                                indice === tipActivo
                                                    ? cn('w-5', tono.punto)
                                                    : 'w-1.5 bg-border hover:bg-muted-foreground/40',
                                            )}
                                        />
                                    ))}
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon-sm"
                                        aria-label="Consejo anterior"
                                        onClick={() => moverTip(-1)}
                                    >
                                        <ChevronLeft />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon-sm"
                                        aria-label="Consejo siguiente"
                                        onClick={() => moverTip(1)}
                                    >
                                        <ChevronRight />
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-border/60 shadow-sm">
                    <CardHeader className="flex-row items-center justify-between gap-3">
                        <div>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ReceiptText className="size-4 text-primary" />
                                Ventas recientes
                            </CardTitle>
                            <CardDescription>
                                Últimos movimientos registrados por ti. Haz clic
                                en el ojo para ver el detalle.
                            </CardDescription>
                        </div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/pos/mis-ventas">
                                Ver historial
                                <ArrowRight />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {ultimas_ventas.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/70 py-10 text-center">
                                <ReceiptText className="size-8 text-muted-foreground/60" />
                                <p className="text-sm font-semibold">
                                    Aún no registras ventas
                                </p>
                                <p className="max-w-sm text-xs text-muted-foreground">
                                    Cuando cierres tu primera venta aparecerá
                                    aquí con su detalle y método de pago.
                                </p>
                                <Button asChild size="sm" className="mt-1">
                                    <Link href="/pos">
                                        <ShoppingCart />
                                        Ir al terminal POS
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border/60 text-left text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                            <th className="px-2 py-2">
                                                Documento
                                            </th>
                                            <th className="hidden px-2 py-2 md:table-cell">
                                                Cliente
                                            </th>
                                            <th className="hidden px-2 py-2 sm:table-cell">
                                                Hora
                                            </th>
                                            <th className="hidden px-2 py-2 lg:table-cell">
                                                Pago
                                            </th>
                                            <th className="px-2 py-2 text-right">
                                                Total
                                            </th>
                                            <th className="px-2 py-2 text-right">
                                                <span className="sr-only">
                                                    Acciones
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ultimas_ventas.map((venta) => (
                                            <tr
                                                key={venta.id}
                                                className="border-b border-border/40 last:border-0 hover:bg-muted/30"
                                            >
                                                <td className="px-2 py-3">
                                                    <p className="font-mono text-xs font-bold">
                                                        {venta.documento}
                                                    </p>
                                                    <Badge
                                                        variant="outline"
                                                        className="mt-1 text-[10px]"
                                                    >
                                                        {
                                                            venta.tipo_documento_label
                                                        }
                                                    </Badge>
                                                </td>
                                                <td className="hidden px-2 py-3 md:table-cell">
                                                    <p className="max-w-[180px] truncate text-xs font-medium">
                                                        {venta.cliente}
                                                    </p>
                                                </td>
                                                <td className="hidden px-2 py-3 text-xs text-muted-foreground sm:table-cell">
                                                    {venta.hora ?? '—'}
                                                </td>
                                                <td className="hidden px-2 py-3 lg:table-cell">
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            ESTILOS_METODO[
                                                                venta
                                                                    .metodo_pago
                                                            ] ??
                                                            'border-border bg-muted text-foreground'
                                                        }
                                                    >
                                                        {
                                                            venta.metodo_pago_label
                                                        }
                                                    </Badge>
                                                </td>
                                                <td className="px-2 py-3 text-right text-sm font-bold">
                                                    {formatCurrency(
                                                        venta.total,
                                                        currencyCode,
                                                    )}
                                                </td>
                                                <td className="px-2 py-3 text-right">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label={`Ver detalle de ${venta.documento}`}
                                                        onClick={() =>
                                                            abrirDetalle(venta)
                                                        }
                                                    >
                                                        <Eye />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={detalleOpen} onOpenChange={setDetalleOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader className="border-b border-border/60 px-6 py-5">
                        <DialogTitle className="flex items-center gap-2">
                            <ReceiptText className="size-4 text-primary" />
                            {detalle?.documento ?? 'Detalle de venta'}
                        </DialogTitle>
                        <DialogDescription>
                            {detalle?.fecha_hora ??
                                'Consulta los productos y totales de la venta.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[60vh] overflow-y-auto px-6 py-5">
                        {detalleCargando ? (
                            <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                <Loader2 className="size-8 animate-spin text-primary" />
                                <p className="text-sm text-muted-foreground">
                                    Cargando detalle de la venta…
                                </p>
                            </div>
                        ) : detalleError ? (
                            <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                <AlertTriangle className="size-8 text-amber-500" />
                                <p className="text-sm text-muted-foreground">
                                    {detalleError}
                                </p>
                            </div>
                        ) : detalle ? (
                            <div className="space-y-5">
                                <div className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <p className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                            Cliente
                                        </p>
                                        <p className="font-semibold">
                                            {detalle.cliente?.nombre ??
                                                'Cliente genérico'}
                                        </p>
                                        {detalle.cliente?.rut && (
                                            <p className="text-xs text-muted-foreground">
                                                {detalle.cliente.rut}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                            Método de pago
                                        </p>
                                        <Badge
                                            variant="outline"
                                            className={
                                                ESTILOS_METODO[
                                                    detalle.metodo_pago
                                                ] ??
                                                'border-border bg-muted text-foreground'
                                            }
                                        >
                                            {detalle.metodo_pago_label}
                                        </Badge>
                                    </div>
                                    {detalle.empleado && (
                                        <div>
                                            <p className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                Cajero
                                            </p>
                                            <p className="text-xs font-medium">
                                                {detalle.empleado}
                                            </p>
                                        </div>
                                    )}
                                    {detalle.almacen && (
                                        <div>
                                            <p className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                Almacén
                                            </p>
                                            <p className="text-xs font-medium">
                                                {detalle.almacen}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className="overflow-hidden rounded-lg border border-border/60">
                                    <table className="w-full text-xs">
                                        <thead className="bg-muted/50">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-semibold">
                                                    Producto
                                                </th>
                                                <th className="px-2 py-2 text-center font-semibold">
                                                    Cant.
                                                </th>
                                                <th className="px-2 py-2 text-right font-semibold">
                                                    P. unit.
                                                </th>
                                                <th className="px-3 py-2 text-right font-semibold">
                                                    Total
                                                </th>
                                                <th className="px-3 py-2 text-right font-semibold text-emerald-700">
                                                    Comisión
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {detalle.items.length === 0 ? (
                                                <tr>
                                                    <td
                                                        colSpan={5}
                                                        className="px-3 py-6 text-center text-muted-foreground"
                                                    >
                                                        Esta venta no tiene
                                                        productos registrados.
                                                    </td>
                                                </tr>
                                            ) : (
                                                detalle.items.map((item) => (
                                                    <tr
                                                        key={item.id}
                                                        className="border-t border-border/60"
                                                    >
                                                        <td className="px-3 py-2">
                                                            <p className="font-medium">
                                                                {item.producto}
                                                            </p>
                                                            {item.codigo && (
                                                                <p className="text-[10px] text-muted-foreground">
                                                                    {
                                                                        item.codigo
                                                                    }
                                                                </p>
                                                            )}
                                                        </td>
                                                        <td className="px-2 py-2 text-center font-mono">
                                                            {formatearCantidad(
                                                                item.cantidad,
                                                            )}
                                                        </td>
                                                        <td className="px-2 py-2 text-right font-mono">
                                                            {formatCurrency(
                                                                item.precio_unitario,
                                                                currencyCode,
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-bold">
                                                            {formatCurrency(
                                                                item.subtotal,
                                                                currencyCode,
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-semibold text-emerald-700">
                                                            {formatCurrency(
                                                                item.comision_cajero,
                                                                currencyCode,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="space-y-1.5 text-sm">
                                    <div className="flex justify-between text-muted-foreground">
                                        <span>Subtotal</span>
                                        <span>
                                            {formatCurrency(
                                                detalle.subtotal,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                    {detalle.descuento > 0 && (
                                        <div className="flex justify-between text-amber-600">
                                            <span>Descuento</span>
                                            <span>
                                                -
                                                {formatCurrency(
                                                    detalle.descuento,
                                                    currencyCode,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    {detalle.cupon &&
                                        detalle.monto_descuento_cupon > 0 && (
                                            <div className="flex justify-between text-amber-600">
                                                <span>
                                                    Cupón {detalle.cupon.codigo}
                                                </span>
                                                <span>
                                                    -
                                                    {formatCurrency(
                                                        detalle.monto_descuento_cupon,
                                                        currencyCode,
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                    {detalle.delivery_cost > 0 && (
                                        <div className="flex justify-between text-emerald-600">
                                            <span>Despacho</span>
                                            <span>
                                                {formatCurrency(
                                                    detalle.delivery_cost,
                                                    currencyCode,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-muted-foreground">
                                        <span>IVA</span>
                                        <span>
                                            {formatCurrency(
                                                detalle.iva,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                    <div className="mt-1 flex justify-between border-t border-border/60 pt-2 text-base font-black">
                                        <span>Total</span>
                                        <span className="text-emerald-600">
                                            {formatCurrency(
                                                detalle.total,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex justify-between rounded-md bg-emerald-50 px-3 py-2 font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                        <span>Comisión del cajero</span>
                                        <span>
                                            {formatCurrency(
                                                detalle.items.reduce(
                                                    (total, item) =>
                                                        total +
                                                        item.comision_cajero,
                                                    0,
                                                ),
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ) : null}
                    </div>

                    <DialogFooter className="border-t border-border/60 px-6 py-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDetalleOpen(false)}
                        >
                            Cerrar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
