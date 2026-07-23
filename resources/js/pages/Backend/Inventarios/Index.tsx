import { Head, useForm, router, Link } from '@inertiajs/react';
import {
    Check,
    Pencil,
    Plus,
    Trash2,
    Search,
    AlertTriangle,
    Boxes,
    Package,
    ArrowDownZA,
    History,
    TrendingUp,
    AlertCircle,
    Edit3,
    Eye,
    ClipboardList,
    LayoutGrid,
    List,
    Info,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip as RechartsTooltip,
    ResponsiveContainer,
    AreaChart,
    Area,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell
} from 'recharts';
import { BulkActions } from '@/components/shared/BulkActions';
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
    DialogFooter,
    DialogHeader,
    DialogTitle
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
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface InventarioProducto {
    almacen_id: number;
    cantidad: number;
    cantidad_minima: number;
    ubicacion: string | null;
}

interface Producto {
    id: number;
    codigo: string;
    nombre: string;
    total_stock?: number;
    stock_minimo?: number;
    inventarios?: InventarioProducto[];
}
interface Almacen {
    id: number;
    nombre: string;
}
// eslint-disable-next-line @typescript-eslint/no-unused-vars
interface Inventario {
    id: number;
    producto_id: number;
    almacen_id: number;
    cantidad: number;
    cantidad_minima: number;
    ubicacion: string | null;
    producto?: Producto;
    almacen?: Almacen;
}

interface InventarioPorAlmacen {
    almacen: string | null;
    cantidad: number;
    cantidad_minima: number;
    ubicacion: string | null;
}

interface InventarioProyeccion {
    id: number;
    codigo: string;
    nombre: string;
    categoria: string | null;
    total_stock: number;
    stock_minimo: number;
    avg_daily_sales: number;
    days_remaining: number;
    status: 'low' | 'optimal';
    imagen: string | null;
    inventarios: InventarioPorAlmacen[];
    projection: { name: string; stock: number }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventario', href: '/inventarios' },
];

interface ChartStockBodega {
    nombre: string;
    stock: number;
}

interface ChartStockBajo {
    nombre: string;
    almacen: string;
    stock: number;
    minimo: number;
}

interface ChartTopStock {
    nombre: string;
    stock: number;
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
interface ChartMovimientos {
    fecha: string;
    entradas: number;
    salidas: number;
}

const COLORS = [
    '#3b82f6',
    '#10b981',
    '#f59e0b',
    '#ef4444',
    '#8b5cf6',
    '#ec4899',
    '#06b6d4',
    '#84cc16',
];

export default function Index({
    inventarios,
    productos,
    almacenes,
    filters,
    stockPorBodega = [],
    productosStockBajo = [],
    productosTopStock = [],
    ventasPorFecha = [],
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    ventasPorAlmacen = [],
}: {
    inventarios: {
        data: InventarioProyeccion[];
        links: any[];
        meta?: any;
        total: number;
    };
    productos: Producto[];
    almacenes: Almacen[];
    filters: {
        search?: string;
        stock_bajo?: string;
        almacen_id?: string;
    };
    stockPorBodega?: ChartStockBodega[];
    productosStockBajo?: ChartStockBajo[];
    productosTopStock?: ChartTopStock[];
    ventasPorFecha?: { fecha: string; ventas: number }[];
    ventasPorAlmacen?: { fecha: string; almacen: string; cantidad: number }[];
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('inventario.inventarios.create');
    const canEdit = hasPermission('inventario.inventarios.edit');
    const canDelete = hasPermission('inventario.inventarios.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<any>(null);
    const [viewingProjection, setViewingProjection] =
        useState<InventarioProyeccion | null>(null);
    const [ajuste, setAjuste] = useState<string>('');
    const [showViewModal, setShowViewModal] = useState(false);
    const [viewingItem, setViewingItem] = useState<InventarioProyeccion | null>(
        null,
    );

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [almacenFilter, setAlmacenFilter] = useState(
        filters.almacen_id || 'all',
    );
    const [stockBajoFilter, setStockBajoFilter] = useState(
        filters.stock_bajo === '1',
    );
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const {
        data,
        setData,
        post,
        delete: destroy,
        reset,
        processing,
        errors,
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        transform,
    } = useForm({
        producto_id: '',
        almacen_id: '',
        cantidad: 0,
        cantidad_minima: 0,
        ubicacion: '',
    });

    const handleProductoSelect = (v: string) => {
        const prod = productos.find((p) => p.id.toString() === v);
        setData((prev) => ({
            ...prev,
            producto_id: v,
            cantidad: 0,
            cantidad_minima: Number(prod?.stock_minimo || 0),
            almacen_id: '',
        }));
        setAjuste('');
    };

    const handleAlmacenChange = (v: string) => {
        setData('almacen_id', v);
        const prod = productos.find(
            (p) => p.id.toString() === data.producto_id,
        );
        const inv = prod?.inventarios?.find(
            (i: any) => i.almacen_id.toString() === v,
        );
        const cantidadActual = inv ? Number(inv.cantidad) : 0;

        if (ajuste !== '') {
            const ajusteNum = parseFloat(ajuste) || 0;
            const nuevaCantidad = cantidadActual + ajusteNum;
            setData('cantidad', nuevaCantidad < 0 ? 0 : nuevaCantidad);
        } else {
            setData('cantidad', cantidadActual);
        }
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleAjusteChange = (val: number) => {
        setAjuste(val.toString());
        const prod = productos.find(
            (p) => p.id.toString() === data.producto_id,
        );
        const inv = prod?.inventarios?.find(
            (i: any) => i.almacen_id.toString() === data.almacen_id,
        );
        const base = inv ? Number(inv.cantidad) : 0;
        const nuevaCantidad = base + val;
        setData('cantidad', nuevaCantidad < 0 ? 0 : nuevaCantidad);
    };

    const handleAjusteInputChange = (
        e: React.ChangeEvent<HTMLInputElement>,
    ) => {
        const inputValue = e.target.value;
        const cleanedValue = inputValue.replace(/[^0-9.+-]/g, '');
        const numValue = parseFloat(cleanedValue) || 0;

        setAjuste(cleanedValue);

        const prod = productos.find(
            (p) => p.id.toString() === data.producto_id,
        );
        const inv = prod?.inventarios?.find(
            (i: any) => i.almacen_id.toString() === data.almacen_id,
        );
        const base = inv ? Number(inv.cantidad) : 0;
        const nuevaCantidad = base + numValue;
        setData('cantidad', nuevaCantidad < 0 ? 0 : nuevaCantidad);
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (almacenFilter !== 'all') query.almacen_id = almacenFilter;
            if (stockBajoFilter) query.stock_bajo = '1';

            router.get('/inventarios', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);

        return () => clearTimeout(timer);
    }, [searchTerm, almacenFilter, stockBajoFilter]);

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const limpiarFiltros = () => {
        setSearchTerm('');
        setAlmacenFilter('all');
        setStockBajoFilter(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        setData((prev) => ({
            ...prev,
            producto_id: String(Number(prev.producto_id)),
            almacen_id: String(Number(prev.almacen_id)),
            cantidad: Number(prev.cantidad),
            cantidad_minima: Number(prev.cantidad_minima),
            ubicacion: prev.ubicacion || '',
        }));

        if ((editando && data.almacen_id) || !editando) {
            post('/inventarios', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleNew = () => {
        setEditando(null);
        reset();
        setAjuste('');
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Está seguro de eliminar este registro?')) {
            destroy(`/inventarios/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Control de Stock e Inventario" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header Section with Quick Stats */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Control General de Inventario
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Vista consolidada de todos los productos y
                            proyecciones de demanda basadas en ventas.
                        </p>
                    </div>

                    <div className="flex flex-nowrap items-center gap-2">
                        <BulkActions
                            baseUrl="/inventarios"
                            modelName="Inventarios"
                            filters={{
                                search: searchTerm,
                                almacen_id: almacenFilter,
                                stock_bajo: stockBajoFilter ? '1' : undefined,
                            }}
                        />

                        <Button
                            variant="outline"
                            onClick={() => router.get('/inventario-cierre')}
                            className="h-10 rounded-xl border-2 px-4 font-bold shadow-md hover:border-orange-300 hover:bg-orange-50"
                        >
                            <ClipboardList className="mr-2 h-4 w-4 text-orange-600" />
                            <span className="text-orange-700">Cierre</span>
                        </Button>

                        {canCreate && (
                            <Button
                                onClick={handleNew}
                                className="h-10 rounded-xl bg-primary px-6 font-bold shadow-lg shadow-primary/20 transition-all hover:bg-primary/90"
                            >
                                <Plus className="mr-2 h-4 w-4" /> Registrar Stock
                            </Button>
                        )}
                    </div>
                </div>

                {/* Charts Section */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {/* Stock por Bodega */}
                    <Card className="border-none shadow-lg">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                Stock por Bodega
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="h-48">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={stockPorBodega}>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        className="stroke-muted"
                                    />
                                    <XAxis
                                        dataKey="nombre"
                                        tick={{ fontSize: 10 }}
                                        className="fill-muted-foreground"
                                    />
                                    <YAxis
                                        tick={{ fontSize: 10 }}
                                        className="fill-muted-foreground"
                                    />
                                    <RechartsTooltip />
                                    <Bar
                                        dataKey="stock"
                                        fill="#3b82f6"
                                        radius={[4, 4, 0, 0]}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    {/* Productos Stock Bajo */}
                    <Card className="border-none shadow-lg">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                Stock Bajo (Bajo Mínimo)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="h-48">
                            {productosStockBajo &&
                            productosStockBajo.length > 0 ? (
                                <ResponsiveContainer width="100%" height="100%">
                                    <PieChart>
                                        <Pie
                                            data={productosStockBajo.map(
                                                (p) => ({
                                                    ...p,
                                                    stock: Math.abs(
                                                        Number(p.stock),
                                                    ),
                                                }),
                                            )}
                                            dataKey="stock"
                                            nameKey="nombre"
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={60}
                                            label={(entry: any) =>
                                                `${String(entry.nombre).substring(0, 8)}.. (${String(entry.almacen || '').substring(0, 5) || '?'})`
                                            }
                                            labelLine={true}
                                        >
                                            {productosStockBajo.map(
                                                (_, index) => (
                                                    <Cell
                                                        key={`cell-${index}`}
                                                        fill={
                                                            COLORS[
                                                                index %
                                                                    COLORS.length
                                                            ]
                                                        }
                                                    />
                                                ),
                                            )}
                                        </Pie>
                                        <RechartsTooltip
                                            formatter={(value, name, props) => [
                                                `${props?.payload?.nombre || name} (${props?.payload?.almacen || 'Sin bodega'})`,
                                                `Stock: ${value}`,
                                            ]}
                                        />
                                    </PieChart>
                                </ResponsiveContainer>
                            ) : (
                                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                    Sin productos bajo mínimo
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Top Productos Stock */}
                    <Card className="border-none shadow-lg">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                Top 10 Productos (Mayor Stock)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="h-48">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart
                                    data={productosTopStock}
                                    layout="vertical"
                                >
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        className="stroke-muted"
                                    />
                                    <XAxis
                                        type="number"
                                        tick={{ fontSize: 10 }}
                                        className="fill-muted-foreground"
                                    />
                                    <YAxis
                                        dataKey="nombre"
                                        type="category"
                                        tick={{ fontSize: 9 }}
                                        className="fill-muted-foreground"
                                        width={80}
                                    />
                                    <RechartsTooltip />
                                    <Bar
                                        dataKey="stock"
                                        fill="#10b981"
                                        radius={[0, 4, 4, 0]}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    {/* Tendencia de Inventario */}
                    <Card className="border-none shadow-lg">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                Ventas (Últimos 30 días)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="h-48">
                            {ventasPorFecha && ventasPorFecha.length > 0 ? (
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart data={ventasPorFecha}>
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            className="stroke-muted"
                                        />
                                        <XAxis
                                            dataKey="fecha"
                                            tick={{ fontSize: 10 }}
                                            className="fill-muted-foreground"
                                        />
                                        <YAxis
                                            tick={{ fontSize: 10 }}
                                            className="fill-muted-foreground"
                                        />
                                        <RechartsTooltip />

                                        <Line
                                            type="monotone"
                                            dataKey="ventas"
                                            stroke="#ef4444"
                                            strokeWidth={2}
                                            name="Unidades Vendidas"
                                            dot={{ r: 3 }}
                                        />
                                    </LineChart>
                                </ResponsiveContainer>
                            ) : (
                                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                    Sin ventas registradas
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Area */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Table Section */}
                    <Card className="overflow-hidden border-none shadow-xl shadow-foreground/5 lg:col-span-2">
                        <CardHeader className="bg-gradient-to-r from-muted/50 to-transparent pb-4">
                            <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Boxes className="h-5 w-5 text-primary" />
                                <CardTitle>Listado Consolidado</CardTitle>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="flex items-center gap-1 rounded-lg border bg-background/50 p-0.5">
                                    <Button
                                        variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('table')}
                                        className="h-7 w-7 p-0"
                                    >
                                        <List className="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('cards')}
                                        className="h-7 w-7 p-0"
                                    >
                                        <LayoutGrid className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                                <div className="text-xs font-bold tracking-widest text-muted-foreground uppercase">
                                    {inventarios.total} Productos Reflejados
                                </div>
                            </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="flex flex-col gap-4 border-b border-muted/30 bg-muted/20 p-4 md:flex-row md:items-center">
                                <div className="relative flex-1">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Buscar producto o código..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="h-10 border-none bg-background/50 pl-10 pr-10 focus-visible:ring-primary/20"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Select
                                        value={almacenFilter}
                                        onValueChange={setAlmacenFilter}
                                    >
                                        <SelectTrigger className="h-10 w-full border-none bg-background/50 font-bold sm:w-[180px]">
                                            <SelectValue placeholder="Bodega" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todas
                                            </SelectItem>
                                            {almacenes.map((a) => (
                                                <SelectItem
                                                    key={a.id}
                                                    value={a.id.toString()}
                                                >
                                                    {a.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        variant={
                                            stockBajoFilter
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                        className={`h-10 gap-2 border-none px-4 font-bold shadow-sm ${stockBajoFilter ? 'bg-destructive text-white' : 'bg-background/50 text-muted-foreground'}`}
                                        onClick={() =>
                                            setStockBajoFilter(!stockBajoFilter)
                                        }
                                    >
                                        <AlertTriangle className="h-4 w-4" />
                                        <span>Stock Bajo</span>
                                    </Button>
                                </div>
                            </div>

                            {viewMode === 'table' ? (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                            <th className="px-6 py-4 text-left">
                                                SKU / Artículo
                                            </th>
                                            <th className="px-6 py-4 text-center">
                                                {almacenFilter === 'all'
                                                    ? 'Stock Total (Consolidado)'
                                                    : `Stock en ${almacenes.find((a) => a.id.toString() === almacenFilter)?.nombre || 'Bodega'}`}
                                            </th>
                                            <th className="px-6 py-4 text-center">
                                                Mínimo
                                            </th>
                                            <th className="px-6 py-4 text-center">
                                                Días Restantes
                                            </th>
                                            <th className="px-6 py-4 text-right">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-muted/50">
                                        {inventarios.data.map((inv) => (
                                            <tr
                                                key={inv.id}
                                                className={`group cursor-pointer transition-all hover:bg-muted/30 ${viewingProjection?.id === inv.id ? 'bg-primary/5' : ''}`}
                                                onClick={() =>
                                                    setViewingProjection(inv)
                                                }
                                            >
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        {inv.imagen ? (
                                                            <img
                                                                src={`/storage/${inv.imagen}`}
                                                                alt={inv.nombre}
                                                                className="h-10 w-10 rounded-lg object-cover"
                                                            />
                                                        ) : (
                                                            <div
                                                                className={`flex h-10 w-10 items-center justify-center rounded-lg ${inv.status === 'low' ? 'bg-red-500/10 text-red-600' : 'bg-primary/10 text-primary'}`}
                                                            >
                                                                <Package className="h-5 w-5" />
                                                            </div>
                                                        )}
                                                        <div>
                                                            <div className="text-sm font-bold tracking-tight">
                                                                {inv.nombre}
                                                            </div>
                                                            <div className="font-mono text-[10px] text-muted-foreground">
                                                                {inv.codigo}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <div className="flex flex-col items-center">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <div className="flex cursor-pointer items-center">
                                                                    <Badge
                                                                        className={`rounded-xl border-none px-3 py-1 font-mono text-sm font-black ${inv.status === 'low' ? 'animate-pulse bg-red-500 text-white' : 'bg-green-500 text-white'}`}
                                                                    >
                                                                        {inv.total_stock}
                                                                    </Badge>
                                                                    {almacenFilter ===
                                                                        'all' && (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="ml-2 border-slate-200 bg-slate-50 text-[10px] text-slate-600"
                                                                        >
                                                                            Consolidado
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent className="border border-slate-100 bg-white p-3 shadow-xl">
                                                                <div className="min-w-[180px] space-y-1.5">
                                                                    <p className="border-b pb-1 text-xs font-bold text-slate-700">
                                                                        Desglose por Bodega
                                                                    </p>
                                                                    {inv.inventarios &&
                                                                    inv.inventarios
                                                                        .length >
                                                                        0 ? (
                                                                        inv.inventarios.map(
                                                                            (
                                                                                invAlm: any,
                                                                                idx: number,
                                                                            ) => (
                                                                                <div
                                                                                    key={
                                                                                        idx
                                                                                    }
                                                                                    className="flex justify-between text-xs text-slate-600"
                                                                                >
                                                                                    <span>
                                                                                        {invAlm.almacen ||
                                                                                            'Bodega'}
                                                                                    </span>
                                                                                    <span className="font-mono font-bold">
                                                                                        {invAlm.cantidad}
                                                                                    </span>
                                                                                </div>
                                                                            ),
                                                                        )
                                                                    ) : (
                                                                        <p className="text-xs text-slate-500">
                                                                            Sin
                                                                            existencias
                                                                            registradas
                                                                        </p>
                                                                    )}
                                                                    <div className="flex justify-between border-t pt-1 text-xs font-bold text-slate-800">
                                                                        <span>
                                                                            Total
                                                                        </span>
                                                                        <span className="font-mono">
                                                                            {
                                                                                inv.total_stock
                                                                            }
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </TooltipContent>
                                                        </Tooltip>

                                                        {almacenFilter ===
                                                            'all' &&
                                                            inv.inventarios &&
                                                            inv.inventarios
                                                                .length >
                                                                0 && (
                                                                <div className="mt-1 flex max-w-[200px] flex-wrap justify-center gap-1">
                                                                    {inv.inventarios.map(
                                                                        (
                                                                            invAlm: any,
                                                                            idx: number,
                                                                        ) => (
                                                                            <span
                                                                                key={
                                                                                    idx
                                                                                }
                                                                                className="rounded bg-slate-100/80 px-1 text-[10px] text-slate-500"
                                                                            >
                                                                                {(
                                                                                    invAlm.almacen ||
                                                                                    'Bod'
                                                                                ).substring(
                                                                                    0,
                                                                                    8,
                                                                                )}
                                                                                :{' '}
                                                                                <strong>
                                                                                    {
                                                                                        invAlm.cantidad
                                                                                    }
                                                                                </strong>
                                                                            </span>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-center text-xs font-bold text-muted-foreground">
                                                    {inv.stock_minimo}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <div
                                                        className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${inv.days_remaining <= 7 ? 'bg-orange-500/10 text-orange-600' : 'bg-blue-500/10 text-blue-600'}`}
                                                    >
                                                        <History className="h-3 w-3" />
                                                        {inv.days_remaining >
                                                        365
                                                            ? 'Estable'
                                                            : `${inv.days_remaining} días`}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 text-blue-600 hover:bg-blue-100"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setViewingItem(
                                                                    inv,
                                                                );
                                                                setShowViewModal(
                                                                    true,
                                                                );
                                                            }}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 text-primary hover:bg-primary/10"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    setEditando({
                                                                        id: inv.id,
                                                                    });
                                                                    setData({
                                                                        producto_id:
                                                                            '',
                                                                        cantidad: 0,
                                                                        cantidad_minima:
                                                                            inv.stock_minimo ||
                                                                            0,
                                                                        ubicacion:
                                                                            '',
                                                                        almacen_id:
                                                                            '',
                                                                    });
                                                                    setAjuste('');
                                                                    setIsOpen(true);
                                                                }}
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 text-destructive hover:bg-destructive/10"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    handleDelete(
                                                                        inv.id,
                                                                    );
                                                                }}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                {inventarios.data.map((inv) => (
                                    <Card key={inv.id} className="overflow-hidden border-none shadow-md transition-all hover:shadow-lg" onClick={() => setViewingProjection(inv)}>
                                        <CardContent className="p-4">
                                            <div className="flex items-center gap-3 mb-3">
                                                {inv.imagen ? (
                                                    <img src={`/storage/${inv.imagen}`} alt={inv.nombre} className="h-10 w-10 rounded-lg object-cover" />
                                                ) : (
                                                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${inv.status === 'low' ? 'bg-red-500/10 text-red-600' : 'bg-primary/10 text-primary'}`}>
                                                        <Package className="h-5 w-5" />
                                                    </div>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate text-sm font-bold">{inv.nombre}</div>
                                                    <div className="font-mono text-[10px] text-muted-foreground">{inv.codigo}</div>
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between mb-2">
                                                <div className="flex flex-col items-start">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <div className="flex cursor-pointer items-center">
                                                                <Badge className={`rounded-xl border-none px-3 py-1 font-mono text-sm font-black ${inv.status === 'low' ? 'animate-pulse bg-red-500 text-white' : 'bg-green-500 text-white'}`}>
                                                                    {inv.total_stock}
                                                                </Badge>
                                                                {almacenFilter ===
                                                                    'all' && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="ml-2 border-slate-200 bg-slate-50 text-[10px] text-slate-600"
                                                                    >
                                                                        Consolidado
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </TooltipTrigger>
                                                        <TooltipContent className="border border-slate-100 bg-white p-3 shadow-xl">
                                                            <div className="min-w-[180px] space-y-1.5">
                                                                <p className="border-b pb-1 text-xs font-bold text-slate-700">
                                                                    Desglose por Bodega
                                                                </p>
                                                                {inv.inventarios &&
                                                                inv.inventarios
                                                                    .length >
                                                                    0 ? (
                                                                    inv.inventarios.map(
                                                                        (
                                                                            invAlm: any,
                                                                            idx: number,
                                                                        ) => (
                                                                            <div
                                                                                key={
                                                                                    idx
                                                                                }
                                                                                className="flex justify-between text-xs text-slate-600"
                                                                            >
                                                                                <span>
                                                                                    {invAlm.almacen ||
                                                                                        'Bodega'}
                                                                                </span>
                                                                                <span className="font-mono font-bold">
                                                                                    {invAlm.cantidad}
                                                                                </span>
                                                                            </div>
                                                                        ),
                                                                    )
                                                                ) : (
                                                                    <p className="text-xs text-slate-500">
                                                                        Sin
                                                                        existencias
                                                                        registradas
                                                                    </p>
                                                                )}
                                                                <div className="flex justify-between border-t pt-1 text-xs font-bold text-slate-800">
                                                                    <span>
                                                                        Total
                                                                    </span>
                                                                    <span className="font-mono">
                                                                        {
                                                                            inv.total_stock
                                                                        }
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </TooltipContent>
                                                    </Tooltip>

                                                    {almacenFilter === 'all' &&
                                                        inv.inventarios &&
                                                        inv.inventarios.length >
                                                            0 && (
                                                            <div className="mt-1 flex max-w-[200px] flex-wrap gap-1">
                                                                {inv.inventarios.map(
                                                                    (
                                                                        invAlm: any,
                                                                        idx: number,
                                                                    ) => (
                                                                        <span
                                                                            key={
                                                                                idx
                                                                            }
                                                                            className="rounded bg-slate-100/80 px-1 text-[10px] text-slate-500"
                                                                        >
                                                                            {(
                                                                                invAlm.almacen ||
                                                                                'Bod'
                                                                            ).substring(
                                                                                0,
                                                                                8,
                                                                            )}
                                                                            :{' '}
                                                                            <strong>
                                                                                {
                                                                                    invAlm.cantidad
                                                                                }
                                                                            </strong>
                                                                        </span>
                                                                    ),
                                                                )}
                                                            </div>
                                                        )}
                                                </div>
                                                <div className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-black uppercase ${inv.days_remaining <= 7 ? 'bg-orange-500/10 text-orange-600' : 'bg-blue-500/10 text-blue-600'}`}>
                                                    <History className="h-3 w-3" />
                                                    {inv.days_remaining > 365 ? 'Estable' : `${inv.days_remaining} días`}
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between pt-2 border-t border-muted/20">
                                                <span className="text-xs text-muted-foreground">Mín: {inv.stock_minimo}</span>
                                                <div className="flex gap-1">
                                                    <Button variant="ghost" size="icon" className="h-7 w-7 text-blue-600 hover:bg-blue-100" onClick={(e) => { e.stopPropagation(); setViewingItem(inv); setShowViewModal(true); }}>
                                                        <Eye className="h-3.5 w-3.5" />
                                                    </Button>
                                                    {canEdit && (
                                                        <Button variant="ghost" size="icon" className="h-7 w-7 text-primary hover:bg-primary/10" onClick={(e) => { e.stopPropagation(); setEditando({ id: inv.id }); setData({ producto_id: '', cantidad: 0, cantidad_minima: inv.stock_minimo || 0, ubicacion: '', almacen_id: '' }); setAjuste(''); setIsOpen(true); }}>
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive hover:bg-destructive/10" onClick={(e) => { e.stopPropagation(); handleDelete(inv.id); }}>
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                                {inventarios.data.length === 0 && (
                                    <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                        <Package className="mb-2 h-12 w-12" />
                                        <p className="font-bold">No se encontraron productos</p>
                                    </div>
                                )}
                            </div>
                            )}
                            <div className="border-t border-muted/50 p-4">
                                <Pagination
                                    links={inventarios.links}
                                    meta={inventarios.meta || inventarios}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Projections Section */}
                    <Card className="group relative overflow-hidden border-none bg-white/10 shadow-xl shadow-foreground/5 backdrop-blur-xl lg:col-span-1 dark:bg-zinc-900/40">
                        <div className="absolute -top-24 -right-24 h-48 w-48 rounded-full bg-primary/20 opacity-50 blur-3xl transition-all group-hover:bg-primary/30" />

                        <CardHeader className="relative z-10 border-b border-muted/20 pb-4">
                            <div className="flex items-center gap-2 text-primary">
                                <TrendingUp className="h-5 w-5" />
                                <CardTitle className="text-foreground">
                                    Proyección de Demanda
                                </CardTitle>
                            </div>
                            <CardDescription className="text-muted-foreground">
                                Pronóstico de stock basado en la tasa de ventas
                                diaria.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {viewingProjection ? (
                                <div className="relative z-10 space-y-8 p-6">
                                    <div className="space-y-1">
                                        <h4 className="text-2xl font-black tracking-tight text-foreground">
                                            {viewingProjection.nombre}
                                        </h4>
                                        <p className="font-mono text-xs tracking-widest text-primary uppercase">
                                            {viewingProjection.codigo}
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="rounded-2xl border border-muted/20 bg-background/40 p-4 backdrop-blur-sm transition-all hover:border-primary/30">
                                            <p className="mb-1 text-[10px] font-black text-muted-foreground uppercase">
                                                Ventas Diarias (Avg)
                                            </p>
                                            <p className="font-mono text-xl font-black text-primary">
                                                {
                                                    viewingProjection.avg_daily_sales
                                                }{' '}
                                                <span className="text-[10px] uppercase">
                                                    und
                                                </span>
                                            </p>
                                        </div>
                                        <div className="rounded-2xl border border-muted/20 bg-background/40 p-4 backdrop-blur-sm transition-all hover:border-primary/30">
                                            <p className="mb-1 text-[10px] font-black text-muted-foreground uppercase">
                                                Días de Stock
                                            </p>
                                            <p
                                                className={`font-mono text-xl font-black ${viewingProjection.days_remaining < 5 ? 'text-red-500' : 'text-green-500'}`}
                                            >
                                                {viewingProjection.days_remaining >
                                                365
                                                    ? '∞'
                                                    : viewingProjection.days_remaining}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="h-[200px] w-full pt-4">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <AreaChart
                                                data={
                                                    viewingProjection.projection
                                                }
                                            >
                                                <defs>
                                                    <linearGradient
                                                        id="colorStock"
                                                        x1="0"
                                                        y1="0"
                                                        x2="0"
                                                        y2="1"
                                                    >
                                                        <stop
                                                            offset="5%"
                                                            stopColor="#6366f1"
                                                            stopOpacity={0.8}
                                                        />
                                                        <stop
                                                            offset="95%"
                                                            stopColor="#6366f1"
                                                            stopOpacity={0}
                                                        />
                                                    </linearGradient>
                                                </defs>
                                                <CartesianGrid
                                                    strokeDasharray="3 3"
                                                    stroke="currentColor"
                                                    strokeOpacity={0.1}
                                                    vertical={false}
                                                />
                                                <XAxis
                                                    dataKey="name"
                                                    stroke="currentColor"
                                                    strokeOpacity={0.4}
                                                    fontSize={10}
                                                    tickLine={false}
                                                    axisLine={false}
                                                />
                                                <YAxis
                                                    stroke="currentColor"
                                                    strokeOpacity={0.4}
                                                    fontSize={10}
                                                    tickLine={false}
                                                    axisLine={false}
                                                />
                                                <Tooltip
                                                    contentStyle={{
                                                        backgroundColor:
                                                            'rgba(255, 255, 255, 0.8)',
                                                        backdropFilter:
                                                            'blur(12px)',
                                                        borderColor:
                                                            'rgba(0,0,0,0.1)',
                                                        borderRadius: '12px',
                                                        boxShadow:
                                                            '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
                                                    }}
                                                    itemStyle={{
                                                        color: '#6366f1',
                                                        fontWeight: 'bold',
                                                    }}
                                                />
                                                <Area
                                                    type="monotone"
                                                    dataKey="stock"
                                                    stroke="#6366f1"
                                                    strokeWidth={3}
                                                    fillOpacity={1}
                                                    fill="url(#colorStock)"
                                                />
                                            </AreaChart>
                                        </ResponsiveContainer>
                                    </div>

                                    <div className="flex items-start gap-3 rounded-2xl border border-indigo-500/20 bg-indigo-500/10 p-4">
                                        <AlertCircle className="h-5 w-5 shrink-0 text-indigo-500" />
                                        <div className="space-y-1">
                                            <p className="text-[10px] font-black text-indigo-600 uppercase dark:text-indigo-400">
                                                Diagnóstico Logístico
                                            </p>
                                            <p className="text-xs font-medium text-muted-foreground">
                                                {viewingProjection.status ===
                                                'low'
                                                    ? 'Se requiere reabastecimiento inmediato. El nivel está por debajo de la reserva crítica.'
                                                    : viewingProjection.days_remaining <
                                                        15
                                                      ? 'Se aproxima el punto de reorden. Planificar compra en la próxima semana.'
                                                      : 'Stock saludable. La tasa de salida está dentro de los parámetros esperados.'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="relative z-10 flex flex-col items-center justify-center space-y-4 p-20 text-center">
                                    <div className="flex h-16 w-16 animate-bounce items-center justify-center rounded-full bg-primary/10">
                                        <ArrowDownZA className="h-8 w-8 text-primary" />
                                    </div>
                                    <div>
                                        <p className="font-bold text-foreground">
                                            Selecciona un producto
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Para visualizar su proyección de
                                            agotamiento
                                        </p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* View Details Modal */}
            <Dialog open={showViewModal} onOpenChange={setShowViewModal}>
                <DialogContent className="max-h-[85vh] w-[95vw] max-w-md border-none p-0 shadow-2xl sm:max-w-lg">
                    <DialogHeader className="bg-gradient-to-r from-blue-500/10 to-transparent p-3 pb-2 text-left sm:p-4">
                        <div className="mb-1 flex items-center gap-2">
                            <Eye className="h-4 w-4 text-blue-500" />
                            <span className="text-[9px] font-black tracking-widest text-blue-500/70 uppercase">
                                Detalles del Producto
                            </span>
                        </div>
                        <DialogTitle className="text-lg font-black tracking-tight text-foreground">
                            {viewingItem?.nombre}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="max-h-[calc(85vh-80px)] space-y-3 overflow-y-auto p-3 sm:p-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-0.5">
                                <p className="text-[9px] font-black text-muted-foreground uppercase">
                                    Código SKU
                                </p>
                                <p className="font-mono text-sm font-bold text-foreground">
                                    {viewingItem?.codigo}
                                </p>
                            </div>
                            <div className="space-y-0.5">
                                <p className="text-[9px] font-black text-muted-foreground uppercase">
                                    Categoría
                                </p>
                                <p className="text-sm font-bold text-foreground">
                                    {viewingItem?.categoria || 'Sin categoría'}
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 rounded-xl bg-muted/30 p-2">
                            <div className="text-center">
                                <p className="text-[8px] font-black text-muted-foreground uppercase">
                                    Stock
                                </p>
                                <p
                                    className={`text-base font-black ${viewingItem?.status === 'low' ? 'text-red-500' : 'text-green-500'}`}
                                >
                                    {viewingItem?.total_stock ?? 0}
                                </p>
                            </div>
                            <div className="text-center">
                                <p className="text-[8px] font-black text-muted-foreground uppercase">
                                    Mínimo
                                </p>
                                <p className="text-base font-black text-amber-500">
                                    {viewingItem?.stock_minimo ?? 0}
                                </p>
                            </div>
                            <div className="text-center">
                                <p className="text-[8px] font-black text-muted-foreground uppercase">
                                    Días
                                </p>
                                <p
                                    className={`text-base font-black ${(viewingItem?.days_remaining ?? 0) <= 7 ? 'text-orange-500' : 'text-blue-500'}`}
                                >
                                    {(viewingItem?.days_remaining ?? 0) > 365
                                        ? '∞'
                                        : viewingItem?.days_remaining}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-1.5">
                            <span
                                className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${viewingItem?.status === 'low' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}`}
                            >
                                {viewingItem?.status === 'low'
                                    ? 'Bajo'
                                    : 'Óptimo'}
                            </span>
                            <span
                                className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${(viewingItem?.days_remaining ?? 0) <= 7 ? 'bg-orange-500 text-white' : 'bg-blue-500 text-white'}`}
                            >
                                {(viewingItem?.days_remaining ?? 0) > 365
                                    ? 'Estable'
                                    : `${viewingItem?.days_remaining}d`}
                            </span>
                        </div>

                        {viewingItem?.projection &&
                            viewingItem.projection.length > 0 && (
                                <div className="space-y-1">
                                    <p className="text-[9px] font-black text-muted-foreground uppercase">
                                        Proyección
                                    </p>
                                    <div className="flex justify-between gap-1">
                                        {viewingItem.projection.map(
                                            (p, idx) => (
                                                <div
                                                    key={idx}
                                                    className="flex-1 rounded bg-muted/20 p-1.5 text-center"
                                                >
                                                    <p className="text-[8px] font-bold text-muted-foreground">
                                                        {p.name}
                                                    </p>
                                                    <p className="text-xs font-black text-foreground">
                                                        {p.stock}
                                                    </p>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}

                        {viewingItem?.inventarios &&
                            viewingItem.inventarios.length > 0 && (
                                <div className="space-y-1">
                                    <p className="text-[9px] font-black text-muted-foreground uppercase">
                                        Stock por Bodega
                                    </p>
                                    <div className="max-h-[120px] space-y-1 overflow-y-auto rounded-lg border border-muted/20 p-2">
                                        {viewingItem.inventarios.map(
                                            (inv, idx) => (
                                                <div
                                                    key={idx}
                                                    className="flex items-center justify-between rounded-lg bg-muted/30 p-3 transition-colors hover:bg-muted/50"
                                                >
                                                    <div className="flex-1">
                                                        <Link
                                                            href={`/almacenes?search=${encodeURIComponent(inv.almacen || '')}`}
                                                            className="text-xs font-bold text-primary hover:underline"
                                                        >
                                                            {inv.almacen ||
                                                                'Sin bodega'}
                                                        </Link>
                                                        {inv.ubicacion && (
                                                            <p className="text-[8px] text-muted-foreground">
                                                                {inv.ubicacion}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="text-right">
                                                        <p
                                                            className={`text-sm font-black ${inv.cantidad <= inv.cantidad_minima ? 'text-red-500' : 'text-green-500'}`}
                                                        >
                                                            {inv.cantidad}
                                                        </p>
                                                        <p className="text-[8px] text-muted-foreground">
                                                            mín:{' '}
                                                            {
                                                                inv.cantidad_minima
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}
                    </div>

                    <DialogFooter className="gap-2 border-t p-2 sm:p-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setShowViewModal(false)}
                            className="h-8 text-xs font-bold"
                        >
                            Cerrar
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                setShowViewModal(false);
                                setEditando({ id: viewingItem?.id });
                                setData({
                                    producto_id: '',
                                    cantidad: 0,
                                    cantidad_minima:
                                        viewingItem?.stock_minimo || 0,
                                    ubicacion: '',
                                    almacen_id: '',
                                });
                                setAjuste('');
                                setIsOpen(true);
                            }}
                            className="h-8 bg-primary text-xs font-bold"
                        >
                            <Pencil className="mr-1 h-3 w-3" /> Editar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Create/Edit dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[85vh] w-[95vw] max-w-2xl border-none p-0 shadow-2xl">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-3 pb-2 text-left sm:p-4">
                        <div className="mb-1 flex items-center gap-2">
                            <History className="h-4 w-4 text-primary" />
                            <span className="text-[9px] font-black tracking-widest text-primary/70 uppercase">
                                Módulo de Logística
                            </span>
                        </div>
                        <DialogTitle className="text-lg font-black tracking-tight text-primary sm:text-xl">
                            {editando
                                ? 'Actualizar Ficha de Stock'
                                : 'Alta de Registro de Inventario'}
                        </DialogTitle>
                    </DialogHeader>

                    <form
                        onSubmit={handleSubmit}
                        className="max-h-[calc(85vh-120px)] overflow-y-auto p-3 sm:p-4"
                    >
                        <div className="grid gap-4">
                            <div className="space-y-1.5">
                                <Label className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Producto / Artículo SKUs *
                                </Label>
                                <Select
                                    value={data.producto_id}
                                    onValueChange={handleProductoSelect}
                                >
                                    <SelectTrigger className="h-9 border-none bg-muted/30 text-sm font-bold focus-visible:ring-primary/20">
                                        <SelectValue placeholder="Seleccione el producto..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {productos.map((p: Producto) => (
                                            <SelectItem
                                                key={p.id}
                                                value={p.id.toString()}
                                            >
                                                {p.nombre} ({p.codigo})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.producto_id && (
                                    <p className="text-[9px] font-bold text-destructive">
                                        {errors.producto_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <Label className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Bodega *
                                </Label>
                                <Select
                                    value={data.almacen_id}
                                    onValueChange={handleAlmacenChange}
                                >
                                    <SelectTrigger className="h-9 border-none bg-muted/30 text-sm font-bold">
                                        <SelectValue placeholder="Seleccione la bodega..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {almacenes.map((a) => (
                                            <SelectItem
                                                key={a.id}
                                                value={a.id.toString()}
                                            >
                                                {a.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.almacen_id && (
                                    <p className="text-[9px] font-bold text-destructive">
                                        {errors.almacen_id}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div className="space-y-1.5 rounded-xl border border-blue-500/10 bg-blue-500/5 p-3">
                                    <Label className="text-[9px] font-black tracking-widest text-blue-600 uppercase">
                                        Ajuste
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            type="text"
                                            value={ajuste}
                                            onChange={handleAjusteInputChange}
                                            className="h-9 border-none bg-background text-center text-lg font-black text-blue-600 shadow-inner"
                                            placeholder="+/- (ej: -288)"
                                        />
                                        <Plus className="h-4 w-4 text-blue-500/40" />
                                    </div>
                                    <p className="text-[8px] leading-tight text-muted-foreground italic">
                                        Sumar/restar
                                    </p>
                                </div>
                                <div className="space-y-1.5 rounded-xl border border-primary/10 bg-primary/5 p-3">
                                    <Label className="text-[9px] font-black tracking-widest text-primary uppercase">
                                        Cantidad
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            type="number"
                                            value={data.cantidad}
                                            onChange={(e) =>
                                                setData(
                                                    'cantidad',
                                                    parseInt(e.target.value) ||
                                                        0,
                                                )
                                            }
                                            className="h-9 border-none bg-background text-center text-lg font-black text-primary shadow-inner"
                                            required
                                        />
                                        <Edit3 className="h-4 w-4 text-primary/40" />
                                    </div>
                                    <p className="text-[8px] leading-tight text-muted-foreground italic">
                                        Stock final
                                    </p>
                                </div>
                                <div className="space-y-1.5 rounded-xl border border-amber-500/10 bg-amber-500/5 p-3">
                                    <Label className="text-[9px] font-black tracking-widest text-amber-600 uppercase">
                                        Mínimo
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            type="number"
                                            value={data.cantidad_minima}
                                            onChange={(e) =>
                                                setData(
                                                    'cantidad_minima',
                                                    parseInt(e.target.value) ||
                                                        0,
                                                )
                                            }
                                            className="h-9 border-none bg-background text-center text-lg font-black text-amber-600 shadow-inner"
                                            required
                                        />
                                        <AlertTriangle className="h-4 w-4 text-amber-500/40" />
                                    </div>
                                    <p className="text-[8px] leading-tight text-muted-foreground italic">
                                        Alerta
                                    </p>
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="gap-2 border-t p-2 sm:p-3">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                                className="h-8 text-sm font-bold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-8 rounded-full bg-primary px-6 text-sm font-bold shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <Check className="mr-1.5 h-3.5 w-3.5" />
                                {editando ? 'Actualizar' : 'Confirmar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
