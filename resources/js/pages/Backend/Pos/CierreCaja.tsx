import { Head, router } from '@inertiajs/react';
import {
    Calendar,
    ChevronDown,
    ChevronUp,
    CreditCard,
    Download,
    FileSpreadsheet,
    Lock,
    RefreshCw,
    Search,
    Upload,
    AlertTriangle
} from 'lucide-react';
import React, { Fragment, useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, getLocalDateString } from '@/lib/utils';

interface Arqueo {
    efectivo: number;
    tarjeta: number;
    transferencia: number;
    otros: number;
    total: number;
    cantidad_ventas: number;
    fecha_desde: string;
    fecha_hasta: string;
    detalle: {
        id: number;
        fecha: string;
        hora: string;
        fecha_completa: string;
        tipo: string;
        metodo: string;
        documento: string;
        monto: number;
        items: {
            id: number;
            producto_id: number;
            producto_nombre: string;
            cantidad: number;
            precio_unitario: number;
            subtotal: number;
            cantidad_retornada: number;
        }[];
    }[];
}

export default function CierreCaja({ arqueo }: { arqueo: Arqueo }) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('ventas.pos.viewAny');
    const [fechaDesde, setFechaDesde] = useState(arqueo.fecha_desde);
    const [fechaHasta, setFechaHasta] = useState(arqueo.fecha_hasta);
    const [filtroMetodo, setFiltroMetodo] = useState<string>('todos');
    const [showCierreModal, setShowCierreModal] = useState(false);
    const [isClosing, setIsClosing] = useState(false);
    const [sortConfig, setSortConfig] = useState<{ key: string; direction: 'asc' | 'desc' }>({
        key: 'created_at',
        direction: 'desc',
    });
    const [expandedRows, setExpandedRows] = useState<Record<number, boolean>>({});

    const toggleRow = (id: number) => {
        setExpandedRows((prev) => ({
            ...prev,
            [id]: !prev[id],
        }));
    };

    const requestSort = (key: string) => {
        let direction: 'asc' | 'desc' = 'asc';
        if (sortConfig && sortConfig.key === key && sortConfig.direction === 'asc') {
            direction = 'desc';
        }
        setSortConfig({ key, direction });
    };

    const handleCerrarTurno = () => {
        setIsClosing(true);
        router.post(
            '/pos/cierre/cerrar',
            {
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta,
            },
            {
                onSuccess: () => {
                    setShowCierreModal(false);
                    setIsClosing(false);
                    window.location.reload();
                },
                onError: () => {
                    setIsClosing(false);
                },
            },
        );
    };

    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);

    const handleImportCSV = () => {
        csvInputRef.current?.click();
    };

    const handleImportExcel = () => {
        excelInputRef.current?.click();
    };

    const handleFileChange = (
        e: React.ChangeEvent<HTMLInputElement>,
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        type: 'csv' | 'excel',
    ) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        router.post('/pos/cierre/importar', formData, {
            forceFormData: true,
            onSuccess: () => {
                e.target.value = '';
            },
        });
    };

    const aplicarFiltros = () => {
        router.get(
            '/pos/cierre',
            {
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta,
            },
            { preserveState: false },
        );
    };

    const hoy = () => {
        const today = getLocalDateString();
        setFechaDesde(today);
        setFechaHasta(today);
        router.get(
            '/pos/cierre',
            {
                fecha_desde: today,
                fecha_hasta: today,
            },
            { preserveState: false },
        );
    };

    const ayer = () => {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        const yesterday = getLocalDateString(d);
        setFechaDesde(yesterday);
        setFechaHasta(yesterday);
        router.get(
            '/pos/cierre',
            {
                fecha_desde: yesterday,
                fecha_hasta: yesterday,
            },
            { preserveState: false },
        );
    };

    const estaSemana = () => {
        const d = new Date();
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        const start = new Date(d.getFullYear(), d.getMonth(), diff);
        const startStr = getLocalDateString(start);
        const end = getLocalDateString();
        setFechaDesde(startStr);
        setFechaHasta(end);
        router.get(
            '/pos/cierre',
            {
                fecha_desde: startStr,
                fecha_hasta: end,
            },
            { preserveState: false },
        );
    };

    const esteMes = () => {
        const d = new Date();
        const start = new Date(d.getFullYear(), d.getMonth(), 1);
        const startStr = getLocalDateString(start);
        const endStr = getLocalDateString();
        setFechaDesde(startStr);
        setFechaHasta(endStr);
        router.get(
            '/pos/cierre',
            {
                fecha_desde: startStr,
                fecha_hasta: endStr,
            },
            { preserveState: false },
        );
    };

    const exportarPdf = () => {
        window.open(
            `/pos/cierre/pdf?fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`,
            '_blank',
        );
    };

    const exportarCsv = () => {
        window.open(
            `/pos/cierre/csv?fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`,
            '_blank',
        );
    };

const detalleFiltrado = (() => {
        let filtered =
            filtroMetodo === 'todos'
                ? arqueo.detalle
                : arqueo.detalle.filter(
                      (d) => d.metodo.toLowerCase() === filtroMetodo,
                  );

        if (filtroMetodo === 'otros') {
            filtered = arqueo.detalle.filter((d) =>
                ['vale', 'visa_transbank', 'binance', 'contactar'].includes(
                    d.metodo.toLowerCase(),
                ),
            );
        }

        // Apply sorting
        if (sortConfig) {
            filtered = [...filtered].sort((a, b) => {
                let aVal: any = a[sortConfig.key as keyof typeof a];
                let bVal: any = b[sortConfig.key as keyof typeof b];

                // Handle date/time sorting
                if (sortConfig.key === 'fecha' || sortConfig.key === 'hora') {
                    aVal = new Date(`${a.fecha}T${a.hora}`).getTime();
                    bVal = new Date(`${b.fecha}T${b.hora}`).getTime();
                }

                if (aVal < bVal) {
                    return sortConfig.direction === 'asc' ? -1 : 1;
                }
                if (aVal > bVal) {
                    return sortConfig.direction === 'asc' ? 1 : -1;
                }
                return 0;
            });
        }

        return filtered;
    })();

    const esMismoDia = fechaDesde === fechaHasta;
    const fechaLabel = esMismoDia
        ? new Date(fechaDesde + 'T12:00:00').toLocaleDateString(currency.locale, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : `${new Date(fechaDesde + 'T12:00:00').toLocaleDateString(currency.locale, { day: 'numeric', month: 'short' })} — ${new Date(fechaHasta + 'T12:00:00').toLocaleDateString(currency.locale, { day: 'numeric', month: 'short', year: 'numeric' })}`;

    if (!canAccess) {
        return (
            <AppLayout
                breadcrumbs={[
                    { title: 'Dashboard', href: '/dashboard' },
                    { title: 'Caja POS', href: '/pos' },
                ]}
            >
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Caja POS', href: '/pos' },
                { title: 'Cierre de Caja', href: '/pos/cierre' },
            ]}
        >
            <Head title="Cierre de Caja (Arqueo)" />

            <div className="space-y-5 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-black tracking-tight uppercase italic">
                            Arqueo de Caja
                        </h1>
                        <p className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                            <Calendar className="h-4 w-4" />
                            {fechaLabel}— {arqueo.cantidad_ventas} transacciones
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.location.reload()}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" /> Refrescar
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9 gap-2 rounded-xl border-muted-foreground/10 font-bold"
                                >
                                    <Download className="h-4 w-4 text-primary" />
                                    <span>Herramientas</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem onClick={exportarPdf}>
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar PDF
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={exportarCsv}>
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar Excel/CSV
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={handleImportCSV}>
                                    <Upload className="mr-2 h-4 w-4" />
                                    Importar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={handleImportExcel}>
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Importar Excel
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Button
                            size="sm"
                            className="bg-gradient-to-r from-red-600 to-red-700 px-6 font-bold text-white shadow-lg shadow-red-600/30 hover:from-red-700 hover:to-red-800"
                            onClick={() => setShowCierreModal(true)}
                        >
                            <Lock className="mr-2 h-4 w-4" /> CERRAR TURNO
                        </Button>
                    </div>
                </div>

                {/* Filtros de Fecha */}
                <Card className="shadow-sm">
                    <CardContent className="pt-4 pb-4">
                        <div className="flex flex-col gap-4 md:flex-row md:items-end">
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={hoy}
                                    className={
                                        esMismoDia &&
                                        fechaDesde ===
                                            getLocalDateString()
                                            ? 'bg-primary text-primary-foreground'
                                            : ''
                                    }
                                >
                                    Hoy
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={ayer}
                                >
                                    Ayer
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={estaSemana}
                                >
                                    Esta Semana
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={esteMes}
                                >
                                    Este Mes
                                </Button>
                            </div>
                            <div className="flex flex-1 items-end gap-3">
                                <div className="grid gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">
                                        Desde
                                    </Label>
                                    <Input
                                        type="date"
                                        value={fechaDesde}
                                        onChange={(e) =>
                                            setFechaDesde(e.target.value)
                                        }
                                        className="h-9 w-full sm:w-[160px]"
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">
                                        Hasta
                                    </Label>
                                    <Input
                                        type="date"
                                        value={fechaHasta}
                                        onChange={(e) =>
                                            setFechaHasta(e.target.value)
                                        }
                                        className="h-9 w-full sm:w-[160px]"
                                    />
                                </div>
                                <Button size="sm" onClick={aplicarFiltros}>
                                    <Search className="mr-2 h-4 w-4" />{' '}
                                    Consultar
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Totales / Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-l-4 border-l-emerald-500 shadow-sm">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                EFECTIVO EN CAJA
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-emerald-600">
                                {formatCurrency(arqueo.efectivo)}
                            </div>
                            <p className="mt-1 text-[10px] text-muted-foreground">
                                Sencillo acumulado en período
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-blue-500 shadow-sm">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                TARJETAS (VOUCHERS)
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-blue-600">
                                {formatCurrency(arqueo.tarjeta)}
                            </div>
                            <p className="mt-1 text-[10px] text-muted-foreground">
                                Transbank / Mercado Pago
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-purple-500 shadow-sm">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                TRANSFERENCIAS
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-purple-600">
                                {formatCurrency(arqueo.transferencia)}
                            </div>
                            <p className="mt-1 text-[10px] text-muted-foreground">
                                Bancarias directas
                            </p>
                        </CardContent>
                    </Card>
                    {arqueo.otros > 0 && (
                        <Card className="border-l-4 border-l-orange-500 shadow-sm">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                    OTROS
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-black text-orange-600">
                                    {formatCurrency(arqueo.otros)}
                                </div>
                                <p className="mt-1 text-[10px] text-muted-foreground">
                                    Vale / Webpay / Binance / Contactar
                                </p>
                            </CardContent>
                        </Card>
                    )}
                    <Card className="border-none bg-slate-900 text-white shadow-md">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-slate-400 uppercase">
                                TOTAL ARQUEO
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-white">
                                {formatCurrency(arqueo.total)}
                            </div>
                            <p className="mt-1 text-[10px] text-slate-400">
                                {arqueo.cantidad_ventas} transacciones
                                realizadas
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Tabla de Detalle */}
                <Card className="border-none bg-background shadow-sm">
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="text-lg font-bold">
                                    Resumen de Transacciones
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Movimientos registrados en el período
                                    seleccionado.
                                </CardDescription>
                            </div>
                            <Select
                                value={filtroMetodo}
                                onValueChange={setFiltroMetodo}
                            >
                                <SelectTrigger className="h-8 w-full text-xs sm:w-[170px]">
                                    <SelectValue placeholder="Filtrar método" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="todos">
                                        Todos los métodos
                                    </SelectItem>
                                    <SelectItem value="efectivo">
                                        Efectivo
                                    </SelectItem>
                                    <SelectItem value="tarjeta">
                                        Tarjeta
                                    </SelectItem>
                                    <SelectItem value="transferencia">
                                        Transferencia
                                    </SelectItem>
                                    <SelectItem value="vale">
                                        Vales
                                    </SelectItem>
                                    <SelectItem value="visa_transbank">
                                        Visa Transbank
                                    </SelectItem>
                                    <SelectItem value="binance">
                                        Binance
                                    </SelectItem>
                                    <SelectItem value="contactar">
                                        Contactar con Administración
                                    </SelectItem>
                                    <SelectItem value="otros">
                                        Otros (Vale / Visa / Binance / Contactar)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-hidden rounded-lg border">
                            <Table>
                                <TableHeader className="bg-muted/50">
                                    <TableRow>
                                        <TableHead className="p-3 text-xs font-semibold text-slate-600 select-none cursor-pointer hover:bg-slate-100/80 transition-colors" onClick={() => requestSort('fecha')}>
                                            <span className="flex items-center gap-1">
                                                Fecha
                                                {sortConfig.key === 'fecha' && (
                                                    <span className="ml-1">
                                                        {sortConfig.direction === 'asc' ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                                                    </span>
                                                )}
                                            </span>
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold text-slate-600 select-none cursor-pointer hover:bg-slate-100/80 transition-colors" onClick={() => requestSort('hora')}>
                                            <span className="flex items-center gap-1">
                                                Hora
                                                {sortConfig.key === 'hora' && (
                                                    <span className="ml-1">
                                                        {sortConfig.direction === 'asc' ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                                                    </span>
                                                )}
                                            </span>
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold text-slate-600 select-none cursor-pointer hover:bg-slate-100/80 transition-colors" onClick={() => requestSort('tipo')}>
                                            <span className="flex items-center gap-1">
                                                Concepto
                                                {sortConfig.key === 'tipo' && (
                                                    <span className="ml-1">
                                                        {sortConfig.direction === 'asc' ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                                                    </span>
                                                )}
                                            </span>
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold text-slate-600">
                                            <span className="flex items-center gap-1">
                                                Detalle Productos
                                            </span>
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold text-slate-600 select-none cursor-pointer hover:bg-slate-100/80 transition-colors" onClick={() => requestSort('metodo')}>
                                            <span className="flex items-center gap-1">
                                                Método
                                                {sortConfig.key === 'metodo' && (
                                                    <span className="ml-1">
                                                        {sortConfig.direction === 'asc' ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                                                    </span>
                                                )}
                                            </span>
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold text-slate-600 select-none cursor-pointer hover:bg-slate-100/80 transition-colors" onClick={() => requestSort('documento')}>
                                            <span className="flex items-center gap-1">
                                                Referencia
                                                {sortConfig.key === 'documento' && (
                                                    <span className="ml-1">
                                                        {sortConfig.direction === 'asc' ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                                                    </span>
                                                )}
                                            </span>
                                        </TableHead>
                                        <TableHead className="text-right p-3 text-xs font-semibold text-slate-600 select-none cursor-pointer hover:bg-slate-100/80 transition-colors" onClick={() => requestSort('monto')}>
                                            <span className="flex items-center justify-end gap-1">
                                                Monto
                                                {sortConfig.key === 'monto' && (
                                                    <span className="ml-1">
                                                        {sortConfig.direction === 'asc' ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                                                    </span>
                                                )}
                                            </span>
                                        </TableHead>
                                        <TableHead className="p-3 text-xs font-semibold text-slate-600 select-none cursor-pointer hover:bg-slate-100/80 transition-colors text-center" onClick={() => {}}>
                                            <span className="sr-only">Detalle</span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {detalleFiltrado.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                                No hay ventas registradas en el período seleccionado.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        detalleFiltrado.map((item, i) => (
                                            <Fragment key={item.id || i}>
                                                <TableRow
                                                    className="hover:bg-muted/5 cursor-pointer"
                                                    onClick={() => toggleRow(item.id)}
                                                >
                                                    <TableCell className="p-3 text-xs">
                                                        {item.fecha}
                                                    </TableCell>
                                                    <TableCell className="p-3 text-xs">
                                                        {item.hora}
                                                    </TableCell>
                                                    <TableCell className="p-3 text-xs font-medium">
                                                        {item.tipo}
                                                    </TableCell>
                                                    <TableCell className="p-3 text-xs text-slate-500 max-w-xs">
                                                        {item.items && item.items.length > 0 ? (
                                                            <div className="flex flex-wrap gap-1">
                                                                {item.items.map((prod, idx) => (
                                                                    <span
                                                                        key={idx}
                                                                        className="inline-flex items-center bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded text-[10px] font-medium"
                                                                    >
                                                                        {prod.cantidad}x {prod.producto_nombre}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        ) : (
                                                            <span className="text-slate-400 italic">
                                                                Sin productos
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="p-3 text-xs">
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                                                item.metodo === 'Efectivo'
                                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                                    : item.metodo === 'Tarjeta'
                                                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                                                        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
                                                            }`}
                                                        >
                                                            {item.metodo}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="p-3 font-mono text-xs">
                                                        {item.documento}
                                                    </TableCell>
                                                    <TableCell className="p-3 text-right font-black text-emerald-600">
                                                        {formatCurrency(item.monto)}
                                                    </TableCell>
                                                    <TableCell className="p-3 text-center">
                                                        <ChevronDown
                                                            className={`mx-auto h-4 w-4 transition-transform ${
                                                                expandedRows[item.id] ? 'rotate-180' : ''
                                                            }`}
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                                {expandedRows[item.id] && (
                                                    <TableRow>
                                                        <TableCell colSpan={7} className="px-0 py-0">
                                                            <div className="bg-slate-50/50 p-4 border border-slate-100 rounded-lg border-t-0">
                                                                <div className="flex flex-col gap-3">
                                                                    <div className="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                                                        <Calendar className="h-3 w-3" />
                                                                        <span>{item.fecha_completa}</span>
                                                                    </div>
                                                                    <div className="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                                                        <CreditCard className="h-3 w-3" />
                                                                        <span className="capitalize">{item.metodo.toLowerCase()}</span>
                                                                    </div>
                                                                    <div className="border-t border-slate-200 pt-3">
                                                                        <div className="overflow-x-auto">
                                                                            <table className="w-full text-xs">
                                                                                <thead>
                                                                                    <tr className="border-b border-slate-200">
                                                                                        <th className="text-left py-1.5 font-medium text-slate-500 uppercase tracking-wider">Producto</th>
                                                                                        <th className="text-center py-1.5 font-medium text-slate-500 uppercase tracking-wider">Cant.</th>
                                                                                        <th className="text-right py-1.5 font-medium text-slate-500 uppercase tracking-wider">P. Unit.</th>
                                                                                        <th className="text-right py-1.5 font-medium text-slate-500 uppercase tracking-wider">Total</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    {item.items.map((productItem, pi) => (
                                                                                        <tr
                                                                                            key={productItem.id || pi}
                                                                                            className="border-b border-slate-100 last:border-0 hover:bg-slate-50/50"
                                                                                        >
                                                                                            <td className="py-1.5 font-medium text-slate-700 dark:text-slate-300">{productItem.producto_nombre}</td>
                                                                                            <td className="text-center py-1.5 font-mono text-slate-600 dark:text-slate-400">{productItem.cantidad}</td>
                                                                                            <td className="text-right py-1.5 font-mono text-slate-600 dark:text-slate-400">{formatCurrency(productItem.precio_unitario)}</td>
                                                                                            <td className="text-right py-1.5 font-bold text-slate-800 dark:text-slate-200">{formatCurrency(productItem.subtotal)}</td>
                                                                                        </tr>
                                                                                    ))}
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                )}
                                            </Fragment>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        {detalleFiltrado.length > 0 && (
                            <div className="mt-3 flex justify-end pr-2">
                                <div className="text-sm font-bold text-muted-foreground">
                                    Total filtrado:{' '}
                                    <span className="ml-2 text-lg text-foreground">
                                        {formatCurrency(
                                            detalleFiltrado.reduce(
                                                (acc, d) => acc + d.monto,
                                                0,
                                            ),
                                        )}
                                    </span>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <input
                ref={csvInputRef}
                type="file"
                accept=".csv"
                className="hidden"
                onChange={(e) => handleFileChange(e, 'csv')}
            />
            <input
                ref={excelInputRef}
                type="file"
                accept=".xlsx,.xls"
                className="hidden"
                onChange={(e) => handleFileChange(e, 'excel')}
            />

            <Dialog open={showCierreModal} onOpenChange={setShowCierreModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader className="px-6 pt-6">
                        <DialogTitle className="flex items-center gap-2 text-red-600">
                            <AlertTriangle className="h-5 w-5" />
                            Cerrar Turno
                        </DialogTitle>
                        <DialogDescription>
                            ¿Estás seguro de que deseas cerrar el turno? Esta
                            acción finalizará la sesión actual y no podrá
                            deshacerse.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 rounded-lg bg-muted/50 p-4 mx-6">
                        <div className="flex justify-between">
                            <span className="text-sm text-muted-foreground">
                                Período:
                            </span>
                            <span className="text-sm font-medium">
                                {fechaLabel}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-muted-foreground">
                                Transacciones:
                            </span>
                            <span className="text-sm font-medium">
                                {arqueo.cantidad_ventas}
                            </span>
                        </div>
                        <div className="flex justify-between border-t pt-2">
                            <span className="text-sm text-muted-foreground">
                                Total:
                            </span>
                            <span className="text-lg font-bold text-emerald-600">
                                {formatCurrency(arqueo.total)}
                            </span>
                        </div>
                    </div>
                    <DialogFooter className="px-6 pb-6">
                        <Button
                            variant="outline"
                            onClick={() => setShowCierreModal(false)}
                            disabled={isClosing}
                        >
                            Cancelar
                        </Button>
                        <Button
                            className="bg-gradient-to-r from-red-600 to-red-700 font-bold text-white"
                            onClick={handleCerrarTurno}
                            disabled={isClosing}
                        >
                            {isClosing ? 'Cerrando...' : 'Confirmar Cierre'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
