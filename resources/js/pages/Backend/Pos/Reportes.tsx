import { router, Head } from '@inertiajs/react';
import {
    ArrowUpRight,
    ArrowDownRight,
    TrendingUp,
    Package,
    DollarSign,
    Download,
    Upload,
    FileSpreadsheet,
    UserCheck,
    Calendar,
} from 'lucide-react';
import { useRef } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';

interface RankingItem {
    nombre: string;
    total_vendidos: number;
    total_ingresos: number;
    total_ganancia?: number;
}

interface Almacen {
    id: number;
    nombre: string;
}

interface Cajero {
    id: number;
    nombre: string;
    apellido: string;
}

interface VentaCajero {
    empleado_id: number | null;
    cajero_nombre: string;
    total_ventas: number;
    monto_total: number;
    ganancia_total: number;
    margen: number;
}

interface DesgloseProducto {
    empleado_id: number | null;
    cajero_nombre: string;
    producto_id: number;
    producto_nombre: string;
    cantidad: number;
    ingresos: number;
    ganancia: number;
    margen: number;
}

interface TotalesReporte {
    monto_total: number;
    ganancia_total: number;
    total_ventas: number;
}

export default function Reportes({
    ranking,
    utilidad,
    totalMes,
    almacenes = [],
    almacenId = null,
    cajeros = [],
    empleadoId = null,
    ventasPorCajero = [],
    desgloseProductos = [],
    totales = { monto_total: 0, ganancia_total: 0, total_ventas: 0 },
    fechaDesde = '',
    fechaHasta = '',
}: {
    ranking: RankingItem[];
    utilidad: number;
    totalMes: number;
    almacenes?: Almacen[];
    almacenId?: string | null;
    cajeros?: Cajero[];
    empleadoId?: string | null;
    ventasPorCajero?: VentaCajero[];
    desgloseProductos?: DesgloseProducto[];
    totales?: TotalesReporte;
    fechaDesde?: string;
    fechaHasta?: string;
}) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('ventas.pos.viewAny');
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

        router.post('/pos/reportes/importar', formData, {
            forceFormData: true,
            onSuccess: () => {
                e.target.value = '';
            },
        });
    };

    const applyFilters = (next: Record<string, string | null | undefined>) => {
        router.get(
            '/pos/reportes',
            {
                almacen_id: almacenId || null,
                empleado_id: empleadoId || null,
                fecha_desde: fechaDesde || null,
                fecha_hasta: fechaHasta || null,
                ...next,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleAlmacenChange = (id: string) => {
        applyFilters({ almacen_id: id === 'all' ? null : id });
    };

    const handleCajeroChange = (id: string) => {
        applyFilters({ empleado_id: id === 'all' ? null : id });
    };

    const handleFechaDesdeChange = (value: string) => {
        applyFilters({ fecha_desde: value || null });
    };

    const handleFechaHastaChange = (value: string) => {
        applyFilters({ fecha_hasta: value || null });
    };

    const buildExportUrl = (format: string) => {
        const params = new URLSearchParams({ format });

        if (almacenId) {
            params.set('almacen_id', almacenId);
        }

        if (empleadoId) {
            params.set('empleado_id', empleadoId);
        }

        if (fechaDesde) {
            params.set('fecha_desde', fechaDesde);
        }

        if (fechaHasta) {
            params.set('fecha_hasta', fechaHasta);
        }

        return `/pos/reportes/exportar?${params.toString()}`;
    };

    // Simular stock bajo para la demo de "huesos" o slow moving si no tenemos data real de slow moving
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const huesos = ranking.length > 0 ? ranking.slice(-2) : [];

    if (!canAccess) {
        return (
            <AppLayout
                breadcrumbs={[
                    { title: 'Dashboard', href: '/dashboard' },
                    { title: 'Caja POS', href: '/pos' },
                ]}
            >
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">
                        No tienes permiso para acceder a esta página.
                    </p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Caja POS', href: '/pos' },
                { title: 'Reportes y Rankings', href: '/pos/reportes' },
            ]}
        >
            <Head title="Reportes POS" />

            <div className="space-y-4 p-4">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            INTELIGENCIA DE NEGOCIO (POS)
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Análisis de rentabilidad real y rotación de
                            inventarios
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="w-48">
                            <Select
                                value={almacenId || 'all'}
                                onValueChange={handleAlmacenChange}
                            >
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="Todos los Almacenes" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos los Almacenes
                                    </SelectItem>
                                    {almacenes.map((a) => (
                                        <SelectItem
                                            key={a.id}
                                            value={String(a.id)}
                                        >
                                            {a.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="w-52">
                            <Select
                                value={empleadoId || 'all'}
                                onValueChange={handleCajeroChange}
                            >
                                <SelectTrigger className="h-9">
                                    <UserCheck className="mr-2 h-4 w-4 text-muted-foreground" />
                                    <SelectValue placeholder="Todos los Cajeros" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos los Cajeros
                                    </SelectItem>
                                    {cajeros.map((cajero) => (
                                        <SelectItem
                                            key={cajero.id}
                                            value={String(cajero.id)}
                                        >
                                            {cajero.nombre} {cajero.apellido}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex items-center gap-2 rounded-xl border bg-background px-2 py-1">
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                            <Input
                                type="date"
                                value={fechaDesde}
                                onChange={(e) =>
                                    handleFechaDesdeChange(e.target.value)
                                }
                                className="h-7 w-32 border-0 p-0 text-xs"
                            />
                            <span className="text-xs text-muted-foreground">
                                a
                            </span>
                            <Input
                                type="date"
                                value={fechaHasta}
                                onChange={(e) =>
                                    handleFechaHastaChange(e.target.value)
                                }
                                className="h-7 w-32 border-0 p-0 text-xs"
                            />
                        </div>

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
                                <DropdownMenuItem onClick={handleImportCSV}>
                                    <Upload className="mr-2 h-4 w-4" />
                                    Importar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={handleImportExcel}>
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Importar Excel
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() =>
                                        window.open(
                                            buildExportUrl('csv'),
                                            '_blank',
                                        )
                                    }
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() =>
                                        window.open(
                                            buildExportUrl('excel'),
                                            '_blank',
                                        )
                                    }
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar Excel
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card className="border-emerald-100 bg-emerald-50">
                        <CardHeader className="pb-1">
                            <CardTitle className="flex items-center justify-between text-[10px] font-bold text-emerald-700 uppercase">
                                Utilidad Bruta Real
                                <TrendingUp className="h-3 w-3" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-emerald-900">
                                {formatCurrency(utilidad)}
                            </div>
                            <p className="mt-1 text-[10px] font-medium text-emerald-700">
                                Margen tras descontar costos
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-none bg-slate-900">
                        <CardHeader className="pb-1">
                            <CardTitle className="flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase">
                                Venta Total (Mes)
                                <DollarSign className="h-3 w-3 text-slate-500" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-white">
                                {formatCurrency(totalMes)}
                            </div>
                            <p className="mt-1 flex items-center text-[10px] font-medium text-slate-400">
                                <ArrowUpRight className="mr-1 h-3 w-3 text-green-400" />{' '}
                                +5.4% vs Mes Ant.
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-blue-100 bg-blue-50">
                        <CardHeader className="pb-1">
                            <CardTitle className="flex items-center justify-between text-[10px] font-bold text-blue-700 uppercase">
                                Tickets Emitidos
                                <Package className="h-3 w-3" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-blue-900">
                                {ranking.length > 0 ? 'Real Data' : '0'} Unid.
                            </div>
                            <p className="mt-1 text-[10px] font-medium text-blue-700">
                                Volumen de despacho POS
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-amber-100 bg-amber-50">
                        <CardHeader className="pb-1">
                            <CardTitle className="flex items-center justify-between text-[10px] font-bold text-amber-700 uppercase">
                                Transacciones e-Comm
                                <Package className="h-3 w-3" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-amber-900">
                                0 Nuevas
                            </div>
                            <p className="mt-1 animate-pulse text-[10px] font-bold font-medium tracking-wider text-amber-700 italic">
                                esperando nuevas ventas
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-primary/10 bg-primary/5">
                    <CardContent className="flex flex-wrap items-center justify-between gap-4 p-4">
                        <div>
                            <p className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Ventas del rango
                            </p>
                            <p className="text-xl font-black text-primary">
                                {formatCurrency(totales.monto_total)}
                            </p>
                        </div>
                        <div>
                            <p className="text-[10px] font-black tracking-widest text-emerald-700/70 uppercase">
                                Ganancia del rango
                            </p>
                            <p className="text-xl font-black text-emerald-700">
                                {formatCurrency(totales.ganancia_total)}
                            </p>
                        </div>
                        <div>
                            <p className="text-[10px] font-black tracking-widest text-slate-500 uppercase">
                                Tickets
                            </p>
                            <p className="text-xl font-black text-slate-700">
                                {totales.total_ventas}
                            </p>
                        </div>
                        <div>
                            <p className="text-[10px] font-black tracking-widest text-slate-500 uppercase">
                                Rango consultado
                            </p>
                            <p className="text-sm font-bold text-slate-700">
                                {fechaDesde} al {fechaHasta}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 font-bold text-primary">
                                <UserCheck className="h-5 w-5" />
                                Ventas y Ganancias por Cajero
                            </CardTitle>
                            <CardDescription className="text-xs italic">
                                Rendimiento del equipo en el rango seleccionado.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader className="bg-slate-50">
                                    <TableRow>
                                        <TableHead className="text-xs font-bold uppercase">
                                            Cajero
                                        </TableHead>
                                        <TableHead className="text-xs font-bold uppercase">
                                            Ventas
                                        </TableHead>
                                        <TableHead className="text-right text-xs font-bold uppercase">
                                            Monto
                                        </TableHead>
                                        <TableHead className="text-right text-xs font-bold uppercase">
                                            Ganancia
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {ventasPorCajero.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="h-24 text-center text-xs text-muted-foreground italic"
                                            >
                                                Sin ventas en el rango
                                                seleccionado.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        ventasPorCajero.map((item) => (
                                            <TableRow
                                                key={
                                                    item.empleado_id ??
                                                    'sin-cajero'
                                                }
                                            >
                                                <TableCell className="text-xs font-bold">
                                                    {item.cajero_nombre}
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    {item.total_ventas}
                                                </TableCell>
                                                <TableCell className="text-right text-xs">
                                                    {formatCurrency(
                                                        item.monto_total,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="text-xs font-black text-emerald-600">
                                                        {formatCurrency(
                                                            item.ganancia_total,
                                                        )}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {item.margen}% margen
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 font-bold text-primary">
                                <Package className="h-5 w-5" />
                                Desglose de Productos por Cajero
                            </CardTitle>
                            <CardDescription className="text-xs italic">
                                Utilidad potencial por producto vendido.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="max-h-[420px] overflow-y-auto">
                                <Table>
                                    <TableHeader className="bg-slate-50">
                                        <TableRow>
                                            <TableHead className="text-xs font-bold uppercase">
                                                Cajero
                                            </TableHead>
                                            <TableHead className="text-xs font-bold uppercase">
                                                Producto
                                            </TableHead>
                                            <TableHead className="text-right text-xs font-bold uppercase">
                                                Cant.
                                            </TableHead>
                                            <TableHead className="text-right text-xs font-bold uppercase">
                                                Ganancia
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {desgloseProductos.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={4}
                                                    className="h-24 text-center text-xs text-muted-foreground italic"
                                                >
                                                    Sin productos vendidos en el
                                                    rango.
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            desgloseProductos.map(
                                                (item, index) => (
                                                    <TableRow
                                                        key={`${item.empleado_id}-${item.producto_id}-${index}`}
                                                    >
                                                        <TableCell className="text-xs font-medium">
                                                            {item.cajero_nombre}
                                                        </TableCell>
                                                        <TableCell className="text-xs font-bold">
                                                            {
                                                                item.producto_nombre
                                                            }
                                                        </TableCell>
                                                        <TableCell className="text-right text-xs">
                                                            {item.cantidad}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <div className="text-xs font-black text-emerald-600">
                                                                {formatCurrency(
                                                                    item.ganancia,
                                                                )}
                                                            </div>
                                                            <div className="text-[10px] text-muted-foreground">
                                                                {item.margen}%
                                                                margen
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 font-bold text-primary">
                                <TrendingUp className="h-5 w-5" />
                                Productos Estrella (Best Sellers)
                            </CardTitle>
                            <CardDescription className="text-xs italic">
                                Los que más caja generan para el negocio.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader className="bg-slate-50">
                                    <TableRow>
                                        <TableHead className="text-xs font-bold uppercase">
                                            Nombre Producto
                                        </TableHead>
                                        <TableHead className="text-xs font-bold uppercase">
                                            Unid.
                                        </TableHead>
                                        <TableHead className="text-right text-xs font-bold uppercase">
                                            Recaudación
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {ranking.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="h-24 text-center text-xs text-muted-foreground italic"
                                            >
                                                Sin datos de venta suficientes
                                                para rankear.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        ranking.map((item, i) => (
                                            <TableRow
                                                key={i}
                                                className="hover:bg-slate-50/50"
                                            >
                                                <TableCell className="text-xs font-bold">
                                                    {item.nombre}
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    {item.total_vendidos} u.
                                                </TableCell>
                                                <TableCell className="text-right text-xs font-black text-emerald-600">
                                                    {formatCurrency(
                                                        item.total_ingresos,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 font-bold text-destructive">
                                <ArrowDownRight className="h-5 w-5" />
                                Productos con Baja Rotación
                            </CardTitle>
                            <CardDescription className="text-xs italic">
                                Mercadería estancada (Huesos) que requiere
                                ofertas.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader className="bg-destructive/5 text-destructive">
                                    <TableRow>
                                        <TableHead className="text-xs font-bold uppercase">
                                            Producto
                                        </TableHead>
                                        <TableHead className="text-xs font-bold uppercase">
                                            Días Stock
                                        </TableHead>
                                        <TableHead className="text-right text-xs font-bold uppercase">
                                            Sugerencia
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {ranking.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="h-24 text-center text-xs text-destructive/50 text-muted-foreground italic"
                                            >
                                                Todo el stock está rotando
                                                saludablemente.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        ranking.slice(-3).map((item, i) => (
                                            <TableRow key={i}>
                                                <TableCell className="text-xs font-medium text-muted-foreground">
                                                    {item.nombre}
                                                </TableCell>
                                                <TableCell className="text-xs text-muted-foreground">
                                                    30+ días
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-500 bg-amber-50 text-[9px] text-amber-600"
                                                    >
                                                        Liquidar 2x1
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
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
        </AppLayout>
    );
}
