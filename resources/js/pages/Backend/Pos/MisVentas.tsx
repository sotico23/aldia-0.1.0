import { Head, router } from '@inertiajs/react';
import {
    Ban,
    Banknote,
    CalendarDays,
    CreditCard,
    Eye,
    FileText,
    Loader2,
    Printer,
    ReceiptText,
    RefreshCw,
    TrendingUp,
    Wallet,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    anular as anularVenta,
    index as misVentasIndex,
    show as ventaDetalle,
} from '@/actions/App/Http/Controllers/Backend/PosVentasController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table-pos';
import { Textarea } from '@/components/ui/textarea';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';

type RangoFiltro = 'hoy' | 'ayer' | 'semana' | 'mes' | 'personalizado';

interface VentaRow {
    id: number;
    numero: string;
    folio: string | null;
    documento: string;
    fecha: string;
    hora: string;
    fecha_hora: string;
    cliente: string;
    cliente_rut: string | null;
    tipo_documento: string;
    metodo_pago: string;
    metodo_pago_label: string;
    total: number;
    estado: string;
    empleado: string | null;
    almacen: string | null;
    puede_anular: boolean;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface DesgloseMetodo {
    clave: string;
    etiqueta: string;
    total: number;
    cantidad: number;
    porcentaje: number;
}

interface Metricas {
    dia: { total: number; cantidad: number };
    mes: { total: number; cantidad: number };
    ganancia_cajero: { dia: number; periodo: number };
    ticket_promedio: number;
    metodo_predominante: DesgloseMetodo | null;
    desglose_metodos: DesgloseMetodo[];
}

interface Filtros {
    rango: RangoFiltro;
    fecha_desde: string;
    fecha_hasta: string;
    tipo_documento: string;
    metodo_pago: string;
    cajero_id: number | null;
}

interface Opcion {
    value: string;
    label: string;
}

interface Cajero {
    id: number;
    nombre: string;
}

interface VentaResumen {
    id: number;
    numero: string;
    documento: string;
    total: number;
}

interface ItemDetalle {
    id: number;
    producto: string;
    codigo: string | null;
    cantidad: number;
    precio_unitario: number;
    descuento_unitario: number;
    subtotal: number;
    comision_cajero: number;
    cantidad_retornada: number;
}

interface DetalleVenta {
    id: number;
    numero: string;
    folio: string | null;
    documento: string;
    tipo_documento: string;
    metodo_pago: string;
    metodo_pago_label: string;
    estado: string;
    fecha: string;
    hora: string;
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
    puede_anular: boolean;
}

interface Props {
    ventas: Paginator<VentaRow>;
    metricas: Metricas;
    filtros: Filtros;
    cajeros: Cajero[];
    puede_ver_todos: boolean;
    puede_anular: boolean;
    tipos_documento: Opcion[];
    metodos_pago: Opcion[];
}

const RANGOS: { value: RangoFiltro; label: string }[] = [
    { value: 'hoy', label: 'Hoy' },
    { value: 'ayer', label: 'Ayer' },
    { value: 'semana', label: 'Esta semana' },
    { value: 'mes', label: 'Este mes' },
    { value: 'personalizado', label: 'Personalizado' },
];

const ESTILOS_METODO: Record<string, string> = {
    efectivo:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-400',
    tarjeta:
        'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-400',
    transferencia:
        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-400',
    vale: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-400',
    binance:
        'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-400',
    contactar:
        'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-300',
};

const ESTILOS_TIPO: Record<string, string> = {
    boleta: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-400',
    factura:
        'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-400',
};

function etiquetaTipo(tipo: string): string {
    return tipo === 'factura' ? 'Factura' : 'Boleta';
}

function formatearFechaCorta(fecha: string, locale: string): string {
    if (!fecha) {
        return '';
    }

    return new Date(`${fecha}T12:00:00`).toLocaleDateString(locale, {
        day: '2-digit',
        month: 'short',
    });
}

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
    icono: typeof Banknote;
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
                        <p className="mt-1 text-xs text-muted-foreground">
                            {detalle}
                        </p>
                    </div>
                    <div
                        className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${acento}`}
                    >
                        <Icono className="size-5" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function TicketContenido({
    detalle,
    currencyCode,
}: {
    detalle: DetalleVenta;
    currencyCode: string;
}) {
    return (
        <div className="space-y-5">
            <div className="border-b border-dashed pb-4 text-center">
                <div className="mx-auto mb-2 flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <ReceiptText className="size-5" />
                </div>
                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    {etiquetaTipo(detalle.tipo_documento)}
                </p>
                <p className="font-mono text-lg font-black">
                    {detalle.documento}
                </p>
                <p className="text-xs text-muted-foreground">
                    {detalle.fecha_hora}
                </p>
            </div>

            <div className="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <p className="font-semibold text-muted-foreground">
                        Cliente
                    </p>
                    <p className="font-medium">
                        {detalle.cliente?.nombre ?? 'Cliente genérico'}
                    </p>
                    {detalle.cliente?.rut && (
                        <p className="text-muted-foreground">
                            {detalle.cliente.rut}
                        </p>
                    )}
                </div>
                <div className="text-right">
                    <p className="font-semibold text-muted-foreground">
                        Atendido por
                    </p>
                    <p className="font-medium">
                        {detalle.empleado ?? 'Sin cajero'}
                    </p>
                    {detalle.almacen && (
                        <p className="text-muted-foreground">
                            {detalle.almacen}
                        </p>
                    )}
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border">
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
                                P. Unit.
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                Total
                            </th>
                            <th className="px-3 py-2 text-right font-semibold text-emerald-700 print:hidden">
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
                                    Esta venta no tiene productos registrados.
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
                                                {item.codigo}
                                            </p>
                                        )}
                                        {item.cantidad_retornada > 0 && (
                                            <p className="text-[10px] text-amber-600">
                                                {item.cantidad_retornada}{' '}
                                                envase(s) retornado(s)
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-2 py-2 text-center font-mono">
                                        {item.cantidad}
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
                                    <td className="px-3 py-2 text-right font-semibold text-emerald-700 print:hidden">
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
                        {formatCurrency(detalle.subtotal, currencyCode)}
                    </span>
                </div>
                {detalle.descuento > 0 && (
                    <div className="flex justify-between text-amber-600">
                        <span>Descuento</span>
                        <span>
                            -{formatCurrency(detalle.descuento, currencyCode)}
                        </span>
                    </div>
                )}
                {detalle.cupon && detalle.monto_descuento_cupon > 0 && (
                    <div className="flex justify-between text-amber-600">
                        <span>Cupón {detalle.cupon.codigo}</span>
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
                    <div className="flex justify-between text-blue-600">
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
                    <span>{formatCurrency(detalle.iva, currencyCode)}</span>
                </div>
                <div className="mt-1 flex justify-between border-t pt-2 text-base font-black">
                    <span>Total</span>
                    <span className="text-emerald-600">
                        {formatCurrency(detalle.total, currencyCode)}
                    </span>
                </div>
                <div className="flex justify-between rounded-md bg-emerald-50 px-3 py-2 font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 print:hidden">
                    <span>Comisión del cajero</span>
                    <span>
                        {formatCurrency(
                            detalle.items.reduce(
                                (total, item) => total + item.comision_cajero,
                                0,
                            ),
                            currencyCode,
                        )}
                    </span>
                </div>
            </div>

            <div className="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2 text-xs">
                <span className="text-muted-foreground">Método de pago</span>
                <Badge
                    variant="outline"
                    className={
                        ESTILOS_METODO[detalle.metodo_pago] ??
                        'border-border bg-muted text-foreground'
                    }
                >
                    {detalle.metodo_pago_label}
                </Badge>
            </div>

            {detalle.estado === 'anulada' && (
                <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-center text-xs font-bold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400">
                    VENTA ANULADA
                </p>
            )}
        </div>
    );
}

export default function MisVentas({
    ventas,
    metricas,
    filtros,
    cajeros,
    puede_ver_todos,
    puede_anular,
    tipos_documento,
    metodos_pago,
}: Props) {
    const { currency } = useCountry();
    const { user } = usePermissions();

    const [cargando, setCargando] = useState(false);
    const [detalleOpen, setDetalleOpen] = useState(false);
    const [detalle, setDetalle] = useState<DetalleVenta | null>(null);
    const [detalleCargando, setDetalleCargando] = useState(false);
    const [detalleError, setDetalleError] = useState<string | null>(null);
    const [ventaAAnular, setVentaAAnular] = useState<VentaResumen | null>(null);
    const [motivoAnulacion, setMotivoAnulacion] = useState('');
    const [anulando, setAnulando] = useState(false);

    const aplicarFiltros = (
        next: Record<string, string | number | null | undefined>,
    ) => {
        router.get(
            misVentasIndex.url(),
            {
                rango: filtros.rango,
                fecha_desde: filtros.fecha_desde,
                fecha_hasta: filtros.fecha_hasta,
                tipo_documento: filtros.tipo_documento,
                metodo_pago: filtros.metodo_pago,
                cajero_id:
                    filtros.cajero_id === null
                        ? puede_ver_todos
                            ? 'all'
                            : undefined
                        : filtros.cajero_id,
                ...next,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['ventas', 'metricas', 'filtros'],
                onStart: () => setCargando(true),
                onFinish: () => setCargando(false),
            },
        );
    };

    const aplicarRango = (rango: RangoFiltro) => {
        aplicarFiltros({ rango, fecha_desde: null, fecha_hasta: null });
    };

    const limpiarFiltros = () => {
        router.get(
            misVentasIndex.url(),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['ventas', 'metricas', 'filtros'],
                onStart: () => setCargando(true),
                onFinish: () => setCargando(false),
            },
        );
    };

    const refrescar = () => {
        router.reload({
            only: ['ventas', 'metricas', 'filtros'],
            onStart: () => setCargando(true),
            onFinish: () => setCargando(false),
        });
    };

    const abrirDetalle = async (venta: VentaRow) => {
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

    const abrirAnulacion = (venta: VentaResumen) => {
        setVentaAAnular({
            id: venta.id,
            numero: venta.numero,
            documento: venta.documento,
            total: venta.total,
        });
        setMotivoAnulacion('');
    };

    const confirmarAnulacion = () => {
        if (!ventaAAnular) {
            return;
        }

        setAnulando(true);

        router.post(
            anularVenta.url(ventaAAnular.id),
            { motivo: motivoAnulacion || null },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setVentaAAnular(null);
                    setMotivoAnulacion('');
                    setDetalleOpen(false);
                    setDetalle(null);
                },
                onFinish: () => setAnulando(false),
            },
        );
    };

    const imprimir = () => window.print();

    const totalPeriodo = metricas.desglose_metodos.reduce(
        (acumulado, metodo) => acumulado + metodo.total,
        0,
    );
    const tituloGanancia = puede_ver_todos
        ? filtros.cajero_id === null
            ? 'Ganancia de Cajeros'
            : 'Ganancia del Cajero'
        : 'Mi Ganancia';

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Punto de Venta', href: '/pos' },
                { title: 'Mis Ventas', href: '/pos/mis-ventas' },
            ]}
        >
            <Head title="Mis Ventas y Métricas de Caja" />

            <style>{`
                @media print {
                    body * { visibility: hidden !important; }
                    #ticket-print, #ticket-print * { visibility: visible !important; }
                    #ticket-print {
                        position: absolute;
                        inset: 0;
                        width: 100%;
                        padding: 24px;
                        background: #fff;
                        color: #000;
                    }
                }
            `}</style>

            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-black tracking-tight uppercase italic">
                            Mis Ventas
                        </h1>
                        <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <CalendarDays className="size-4" />
                            Historial y métricas de caja
                            {user?.name && (
                                <span className="hidden sm:inline">
                                    · {user.name}
                                </span>
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={refrescar}
                            disabled={cargando}
                        >
                            {cargando ? (
                                <Loader2 className="mr-2 size-4 animate-spin" />
                            ) : (
                                <RefreshCw className="mr-2 size-4" />
                            )}
                            Refrescar
                        </Button>
                    </div>
                </div>

                <div
                    className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    aria-busy={cargando}
                >
                    <KpiCard
                        titulo="Ventas de hoy"
                        valor={formatCurrency(
                            metricas.dia.total,
                            currency.code,
                        )}
                        detalle={`${metricas.dia.cantidad} documento(s) emitido(s)`}
                        icono={Banknote}
                        acento="bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
                    />
                    <KpiCard
                        titulo="Ventas del mes"
                        valor={formatCurrency(
                            metricas.mes.total,
                            currency.code,
                        )}
                        detalle={`${metricas.mes.cantidad} documento(s) en el mes`}
                        icono={TrendingUp}
                        acento="bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
                    />
                    <KpiCard
                        titulo="Ticket promedio"
                        valor={formatCurrency(
                            metricas.ticket_promedio,
                            currency.code,
                        )}
                        detalle="Promedio por documento hoy"
                        icono={ReceiptText}
                        acento="bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-400"
                    />
                    <KpiCard
                        titulo={`${tituloGanancia} de Hoy`}
                        valor={formatCurrency(
                            metricas.ganancia_cajero.dia,
                            currency.code,
                        )}
                        detalle="Comisiones de documentos vigentes emitidos hoy"
                        icono={Wallet}
                        acento="bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
                    />
                    <KpiCard
                        titulo={`${tituloGanancia} del Período`}
                        valor={formatCurrency(
                            metricas.ganancia_cajero.periodo,
                            currency.code,
                        )}
                        detalle={`${filtros.fecha_desde} a ${filtros.fecha_hasta}`}
                        icono={TrendingUp}
                        acento="bg-teal-50 text-teal-600 dark:bg-teal-950/40 dark:text-teal-400"
                    />
                    <Card className="overflow-hidden border-border/60 shadow-sm">
                        <CardContent className="p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Método predominante
                                    </p>
                                    <p className="mt-2 truncate text-2xl font-black tracking-tight text-foreground">
                                        {metricas.metodo_predominante
                                            ?.etiqueta ?? 'Sin ventas'}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {metricas.metodo_predominante
                                            ? `${metricas.metodo_predominante.porcentaje}% del período`
                                            : 'No hay movimientos en el rango'}
                                    </p>
                                </div>
                                <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                                    <CreditCard className="size-5" />
                                </div>
                            </div>
                            {metricas.desglose_metodos.length > 0 && (
                                <div className="mt-4 space-y-2">
                                    {metricas.desglose_metodos
                                        .slice(0, 3)
                                        .map((metodo) => (
                                            <div key={metodo.clave}>
                                                <div className="flex items-center justify-between text-[11px]">
                                                    <span className="text-muted-foreground">
                                                        {metodo.etiqueta}
                                                    </span>
                                                    <span className="font-semibold">
                                                        {metodo.porcentaje}%
                                                    </span>
                                                </div>
                                                <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full bg-amber-500"
                                                        style={{
                                                            width: `${Math.max(
                                                                metodo.porcentaje,
                                                                2,
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
                </div>

                <Card className="border-border/60 shadow-sm">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-wrap items-center gap-2">
                                {RANGOS.map((rango) => (
                                    <Button
                                        key={rango.value}
                                        type="button"
                                        size="sm"
                                        variant={
                                            filtros.rango === rango.value
                                                ? 'default'
                                                : 'outline'
                                        }
                                        className="h-9 rounded-lg"
                                        onClick={() =>
                                            aplicarRango(rango.value)
                                        }
                                    >
                                        {rango.label}
                                    </Button>
                                ))}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="h-9 text-muted-foreground"
                                    onClick={limpiarFiltros}
                                >
                                    <X className="mr-1.5 size-4" />
                                    Limpiar
                                </Button>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div className="grid gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">
                                        Desde
                                    </Label>
                                    <Input
                                        type="date"
                                        value={filtros.fecha_desde}
                                        onChange={(event) =>
                                            aplicarFiltros({
                                                rango: 'personalizado',
                                                fecha_desde:
                                                    event.target.value || null,
                                            })
                                        }
                                        className="h-9"
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">
                                        Hasta
                                    </Label>
                                    <Input
                                        type="date"
                                        value={filtros.fecha_hasta}
                                        onChange={(event) =>
                                            aplicarFiltros({
                                                rango: 'personalizado',
                                                fecha_hasta:
                                                    event.target.value || null,
                                            })
                                        }
                                        className="h-9"
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">
                                        Tipo de documento
                                    </Label>
                                    <Select
                                        value={filtros.tipo_documento}
                                        onValueChange={(value) =>
                                            aplicarFiltros({
                                                tipo_documento: value,
                                            })
                                        }
                                    >
                                        <SelectTrigger className="h-9 w-full">
                                            <SelectValue placeholder="Todos" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {tipos_documento.map((opcion) => (
                                                <SelectItem
                                                    key={opcion.value}
                                                    value={opcion.value}
                                                >
                                                    {opcion.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">
                                        Método de pago
                                    </Label>
                                    <Select
                                        value={filtros.metodo_pago}
                                        onValueChange={(value) =>
                                            aplicarFiltros({
                                                metodo_pago: value,
                                            })
                                        }
                                    >
                                        <SelectTrigger className="h-9 w-full">
                                            <SelectValue placeholder="Todos" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {metodos_pago.map((opcion) => (
                                                <SelectItem
                                                    key={opcion.value}
                                                    value={opcion.value}
                                                >
                                                    {opcion.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                {puede_ver_todos && (
                                    <div className="grid gap-1.5 sm:col-span-2 xl:col-span-4">
                                        <Label className="text-xs font-medium text-muted-foreground">
                                            Cajero
                                        </Label>
                                        <Select
                                            value={
                                                filtros.cajero_id === null
                                                    ? 'all'
                                                    : String(filtros.cajero_id)
                                            }
                                            onValueChange={(value) =>
                                                aplicarFiltros({
                                                    cajero_id: value,
                                                })
                                            }
                                        >
                                            <SelectTrigger className="h-9 w-full sm:max-w-sm">
                                                <SelectValue placeholder="Todos los cajeros" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Todos los cajeros
                                                </SelectItem>
                                                {cajeros.map((cajero) => (
                                                    <SelectItem
                                                        key={cajero.id}
                                                        value={String(
                                                            cajero.id,
                                                        )}
                                                    >
                                                        {cajero.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-border/60 shadow-sm">
                    <CardHeader className="pb-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-base font-bold">
                                    Transacciones
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    {formatearFechaCorta(
                                        filtros.fecha_desde,
                                        currency.locale,
                                    )}{' '}
                                    —{' '}
                                    {formatearFechaCorta(
                                        filtros.fecha_hasta,
                                        currency.locale,
                                    )}{' '}
                                    · {ventas.total} registro(s)
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Wallet className="size-4" />
                                Total período:{' '}
                                <span className="font-bold text-foreground">
                                    {formatCurrency(
                                        totalPeriodo,
                                        currency.code,
                                    )}
                                </span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="relative">
                            {cargando && (
                                <div className="absolute inset-0 z-10 flex items-center justify-center bg-background/60">
                                    <Loader2 className="size-6 animate-spin text-primary" />
                                </div>
                            )}

                            <Table>
                                <TableHeader className="bg-muted/50">
                                    <TableRow>
                                        <TableHead className="p-3 text-xs font-semibold">
                                            N° Documento / Folio
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold">
                                            Hora
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold">
                                            Cliente
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold">
                                            Tipo
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold">
                                            Método de pago
                                        </TableHead>
                                        <TableHead className="p-3 text-right text-xs font-semibold">
                                            Total
                                        </TableHead>
                                        <TableHead className="p-3 text-center text-xs font-semibold">
                                            Acciones
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {ventas.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-40 text-center"
                                            >
                                                <div className="flex flex-col items-center justify-center gap-2 text-muted-foreground">
                                                    <ReceiptText className="size-10 opacity-30" />
                                                    <p className="text-sm font-medium">
                                                        No hay ventas para los
                                                        filtros seleccionados.
                                                    </p>
                                                    <p className="text-xs">
                                                        Prueba con otro rango de
                                                        fechas o limpia los
                                                        filtros.
                                                    </p>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        ventas.data.map((venta) => (
                                            <TableRow
                                                key={venta.id}
                                                className="cursor-pointer transition-colors hover:bg-muted/40"
                                                onClick={() =>
                                                    abrirDetalle(venta)
                                                }
                                                tabIndex={0}
                                                onKeyDown={(event) => {
                                                    if (
                                                        event.key === 'Enter' ||
                                                        event.key === ' '
                                                    ) {
                                                        event.preventDefault();
                                                        abrirDetalle(venta);
                                                    }
                                                }}
                                            >
                                                <TableCell className="p-3">
                                                    <div className="flex flex-col">
                                                        <span className="font-mono text-xs font-semibold">
                                                            {venta.documento}
                                                        </span>
                                                        <span className="text-[10px] text-muted-foreground">
                                                            {venta.numero}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="p-3">
                                                    <div className="flex flex-col">
                                                        <span className="text-xs font-medium">
                                                            {venta.hora}
                                                        </span>
                                                        <span className="text-[10px] text-muted-foreground">
                                                            {formatearFechaCorta(
                                                                venta.fecha,
                                                                currency.locale,
                                                            )}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="max-w-[220px] p-3">
                                                    <div className="flex flex-col">
                                                        <span className="truncate text-xs font-medium">
                                                            {venta.cliente}
                                                        </span>
                                                        {venta.cliente_rut && (
                                                            <span className="text-[10px] text-muted-foreground">
                                                                {
                                                                    venta.cliente_rut
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="p-3">
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            ESTILOS_TIPO[
                                                                venta
                                                                    .tipo_documento
                                                            ] ??
                                                            'border-border bg-muted text-foreground'
                                                        }
                                                    >
                                                        {etiquetaTipo(
                                                            venta.tipo_documento,
                                                        )}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="p-3">
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
                                                </TableCell>
                                                <TableCell className="p-3 text-right text-sm font-black whitespace-nowrap">
                                                    {formatCurrency(
                                                        venta.total,
                                                        currency.code,
                                                    )}
                                                </TableCell>
                                                <TableCell className="p-3">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={`Ver detalle de ${venta.documento}`}
                                                            onClick={(
                                                                event,
                                                            ) => {
                                                                event.stopPropagation();
                                                                abrirDetalle(
                                                                    venta,
                                                                );
                                                            }}
                                                        >
                                                            <Eye className="size-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={`Reimprimir ${venta.documento}`}
                                                            onClick={(
                                                                event,
                                                            ) => {
                                                                event.stopPropagation();
                                                                abrirDetalle(
                                                                    venta,
                                                                );
                                                            }}
                                                        >
                                                            <Printer className="size-4" />
                                                        </Button>
                                                        {puede_anular &&
                                                            venta.puede_anular && (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-8 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                                                                    aria-label={`Anular ${venta.documento}`}
                                                                    onClick={(
                                                                        event,
                                                                    ) => {
                                                                        event.stopPropagation();
                                                                        abrirAnulacion(
                                                                            venta,
                                                                        );
                                                                    }}
                                                                >
                                                                    <Ban className="size-4" />
                                                                </Button>
                                                            )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination
                            links={ventas.links}
                            meta={{
                                from: ventas.from ?? 0,
                                to: ventas.to ?? 0,
                                total: ventas.total,
                            }}
                        />
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={detalleOpen}
                onOpenChange={(open) => {
                    setDetalleOpen(open);

                    if (!open) {
                        setDetalle(null);
                        setDetalleError(null);
                    }
                }}
            >
                <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <FileText className="size-5" />
                            Detalle del ticket
                        </DialogTitle>
                        <DialogDescription>
                            Revisa los productos y totales de la transacción.
                        </DialogDescription>
                    </DialogHeader>

                    {detalleCargando && (
                        <div className="flex items-center justify-center py-16">
                            <Loader2 className="size-6 animate-spin text-primary" />
                        </div>
                    )}

                    {!detalleCargando && detalleError && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400">
                            {detalleError}
                        </div>
                    )}

                    {!detalleCargando && detalle && (
                        <>
                            <TicketContenido
                                detalle={detalle}
                                currencyCode={currency.code}
                            />
                            <DialogFooter className="flex-col gap-2 sm:flex-row">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="sm:flex-1"
                                    onClick={() => setDetalleOpen(false)}
                                >
                                    Cerrar
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    className="sm:flex-1"
                                    onClick={imprimir}
                                >
                                    <Printer className="mr-2 size-4" />
                                    Reimprimir
                                </Button>
                                {puede_anular && detalle.puede_anular && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        className="sm:flex-1"
                                        onClick={() => abrirAnulacion(detalle)}
                                    >
                                        <Ban className="mr-2 size-4" />
                                        Anular
                                    </Button>
                                )}
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={ventaAAnular !== null}
                onOpenChange={(open) => {
                    if (!open && !anulando) {
                        setVentaAAnular(null);
                        setMotivoAnulacion('');
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Ban className="size-5 text-red-600" />
                            Anular venta
                        </DialogTitle>
                        <DialogDescription>
                            Esta acción marcará la venta como anulada y
                            restaurará el inventario asociado. No se puede
                            deshacer.
                        </DialogDescription>
                    </DialogHeader>

                    {ventaAAnular && (
                        <div className="space-y-4">
                            <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Documento
                                    </span>
                                    <span className="font-mono font-semibold">
                                        {ventaAAnular.documento}
                                    </span>
                                </div>
                                <div className="mt-1 flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Total
                                    </span>
                                    <span className="font-black text-emerald-600">
                                        {formatCurrency(
                                            ventaAAnular.total,
                                            currency.code,
                                        )}
                                    </span>
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="motivo-anulacion">
                                    Motivo (opcional)
                                </Label>
                                <Textarea
                                    id="motivo-anulacion"
                                    value={motivoAnulacion}
                                    maxLength={255}
                                    placeholder="Ej: error de cobro, devolución, duplicado…"
                                    onChange={(event) =>
                                        setMotivoAnulacion(event.target.value)
                                    }
                                />
                            </div>
                        </div>
                    )}

                    <DialogFooter className="flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            className="sm:flex-1"
                            disabled={anulando}
                            onClick={() => {
                                setVentaAAnular(null);
                                setMotivoAnulacion('');
                            }}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            className="sm:flex-1"
                            disabled={anulando}
                            onClick={confirmarAnulacion}
                        >
                            {anulando ? (
                                <Loader2 className="mr-2 size-4 animate-spin" />
                            ) : (
                                <Ban className="mr-2 size-4" />
                            )}
                            Anular venta
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {detalle && (
                <div id="ticket-print" className="hidden print:block">
                    <TicketContenido
                        detalle={detalle}
                        currencyCode={currency.code}
                    />
                </div>
            )}
        </AppLayout>
    );
}
