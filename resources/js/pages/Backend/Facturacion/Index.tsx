import { Head, useForm, router } from '@inertiajs/react';
import {
    FileText,
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Eye,
    CreditCard,
    TrendingUp,
    DollarSign,
    Clock,
    CalendarDays,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { useMemo } from 'react';
import {
    LineChart,
    Line,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';
import { toast } from 'sonner';
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
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import {
    formatCurrency,
    formatDateCLP,
    formatRut,
    cleanRut,
    getLocalDateString,
} from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Producto {
    id: number;
    nombre: string;
    precio_venta: number;
    inventario?: {
        cantidad: number;
    };
}

interface Cliente {
    id: number;
    nombre: string;
    nit?: string;
}

interface Almacen {
    id: number;
    nombre: string;
}

interface DetalleFactura {
    id?: number;
    producto_id: number;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    impuesto: number;
    total: number;
    producto?: Producto;
}

interface Factura {
    id: number;
    numero: string;
    cliente_id: number;
    fecha: string;
    fecha_vencimiento: string | null;
    subtotal: number;
    impuesto: number;
    total: number;
    tipo: string;
    estado: string;
    notas: string | null;
    total_descuento: number;
    iva_porcentaje: number;
    descuento_valor: number;
    descuento_tipo: string;
    iva_incluido: boolean;
    almacen_id: number | null;
    cliente?: Cliente;
    detalles?: DetalleFactura[];
    emisor?: {
        name: string;
        razon_social?: string;
        rut?: string;
        giro?: string;
        direccion?: string;
        comuna?: string;
        ciudad?: string;
        telefono?: string;
        email: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación', href: '/facturacion' },
];

const comunasChile = [
    'Santiago',
    'Las Condes',
    'Providencia',
    'Maipú',
    'Puente Alto',
    'La Florida',
    'Viña del Mar',
    'Valparaíso',
    'Concepción',
    'Antofagasta',
    'Temuco',
    'Rancagua',
    'Iquique',
    'Puerto Montt',
    'La Serena',
    'Coquimbo',
    'Chillán',
    'Arica',
    'Talca',
    'Copiapó',
    'Quillota',
    'San Antonio',
    'Curicó',
    'Punta Arenas',
    'Osorno',
].sort();

interface Stats {
    total_facturado_mes: number;
    total_pendiente: number;
    total_pagado_mes: number;
    facturas_hoy: number;
    tendencia_mensual: { mes: string; cantidad: number; total: number }[];
    distribucion_estado: { estado: string; cantidad: number; total: number }[];
    distribucion_tipo: { tipo: string; cantidad: number; total: number }[];
}

const ESTADO_COLORS: Record<string, string> = {
    pendiente: '#F59E0B',
    pagada: '#10B981',
    anulada: '#EF4444',
};

const TIPO_LABELS: Record<string, string> = {
    venta: 'Factura',
    compra: 'Compra',
    cotizacion: 'Cotización',
    proforma: 'Proforma',
};

export default function Index({
    facturas,
    clientes,
    productos,
    almacenes,
    stats,
}: {
    facturas: {
        data: Factura[];
        links: any[];
        meta?: any;
        total: number;
    };
    clientes: Cliente[];
    productos: Producto[];
    almacenes: Almacen[];
    stats?: Stats;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [isVerOpen, setIsVerOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [facturaSeleccionada, setFacturaSeleccionada] =
        useState<Factura | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const {
        data,
        setData,
        post,
        delete: destroy,
        reset,
        errors,
        transform,
    } = useForm({
        numero: '',
        cliente_id: '' as string,
        fecha: '',
        fecha_vencimiento: '',
        tipo: 'venta',
        notas: '',
        iva_porcentaje: 19,
        iva_incluido: true,
        descuento_tipo: 'none',
        descuento_valor: 0,
        almacen_id: '' as string,
        detalles: [] as DetalleFactura[],

        // Campos para Cliente Genérico / Manual
        is_manual_cliente: false,
        manual_rut: '',
        manual_razon_social: '',
        manual_giro: '',
        manual_direccion: '',
        manual_comuna: '',
        manual_ciudad: '',
    });

    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('finanzas.facturacion.create');
    const canEdit = hasPermission('finanzas.facturacion.edit');
    const canDelete = hasPermission('finanzas.facturacion.delete');

    const [filtros, setFiltros] = useState({
        busqueda: '',
        cliente_id: '',
        tipo: '',
        estado: '',
        fechaDesde: '',
        fechaHasta: '',
    });

    const facturasFiltradas = useMemo(() => {
        return facturas.data.filter((f) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !f.numero?.toLowerCase().includes(busca) &&
                    !f.cliente?.nombre?.toLowerCase().includes(busca) &&
                    !f.notas?.toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.tipo && f.tipo !== filtros.tipo) return false;
            if (
                filtros.cliente_id &&
                f.cliente_id.toString() !== filtros.cliente_id
            )
                return false;
            if (filtros.estado && f.estado !== filtros.estado) return false;
            if (filtros.fechaDesde && f.fecha < filtros.fechaDesde)
                return false;
            if (filtros.fechaHasta && f.fecha > filtros.fechaHasta)
                return false;
            return true;
        });
    }, [facturas, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            cliente_id: '',
            tipo: '',
            estado: '',
            fechaDesde: '',
            fechaHasta: '',
        });
    };

    const [validatingRut, setValidatingRut] = useState(false);
    const [extraGiros, setExtraGiros] = useState<string[]>([]);

    const handleValidarRut = async () => {
        const rut = data.manual_rut;
        if (!rut) return;

        setValidatingRut(true);
        try {
            const response = await fetch(`/api/sii/consultar/${cleanRut(rut)}`);
            const result = await response.json();

            if (result.success) {
                const info = result.data || result; // Handle both wrapped and flat responses
                const girosList = (info.giros || [])
                    .map((g: any) => g.name || g.glosa)
                    .filter(Boolean);
                setExtraGiros(girosList);

                setData((prev) => ({
                    ...prev,
                    manual_razon_social:
                        info.razon_social || prev.manual_razon_social,
                    manual_giro: info.giro || prev.manual_giro,
                    manual_direccion: info.direccion || prev.manual_direccion,
                    manual_comuna: info.comuna || prev.manual_comuna,
                    manual_ciudad: info.region || prev.manual_ciudad,
                }));
                toast.success('Información recuperada del SII');
            } else {
                toast.error(
                    result.message ||
                        'No se encontró información para este RUT',
                );
            }
        } catch (error) {
            console.error('Error validando RUT:', error);
            toast.error('Error al conectar con el servicio de validación');
        } finally {
            setValidatingRut(false);
        }
    };

    const handleAddDetalle = () => {
        setData('detalles', [
            ...data.detalles,
            {
                producto_id: 0,
                cantidad: 1,
                precio_unitario: 0,
                subtotal: 0,
                impuesto: 0,
                total: 0,
            },
        ]);
    };

    const handleRemoveDetalle = (index: number) => {
        const newDetalles = data.detalles.filter((_, i) => i !== index);
        setData('detalles', newDetalles);
    };

    const handleDetalleChange = (
        index: number,
        field: keyof DetalleFactura,
        value: number | string,
    ) => {
        const newDetalles = [...data.detalles];
        const detalle = { ...newDetalles[index] };

        if (field === 'producto_id') {
            const producto = productos.find((p) => p.id === Number(value));
            if (producto) {
                detalle.producto_id = Number(value);
                detalle.precio_unitario = producto.precio_venta;
                detalle.subtotal = detalle.cantidad * producto.precio_venta;
                detalle.impuesto = Math.round(
                    detalle.subtotal * (data.iva_porcentaje / 100),
                );
                detalle.total = detalle.subtotal + detalle.impuesto;
            }
        } else if (field === 'cantidad') {
            detalle.cantidad = Number(value);
            detalle.subtotal = detalle.cantidad * detalle.precio_unitario;
            detalle.impuesto = Math.round(
                detalle.subtotal * (data.iva_porcentaje / 100),
            );
            detalle.total = detalle.subtotal + detalle.impuesto;
        }

        newDetalles[index] = detalle;
        setData('detalles', newDetalles);
    };

    const bruto = data.detalles.reduce((sum, d) => sum + d.subtotal, 0);

    let totalDescuento = 0;
    if (data.descuento_tipo === 'porcentaje') {
        totalDescuento = Math.round(bruto * (data.descuento_valor / 100));
    } else if (data.descuento_tipo === 'monto') {
        totalDescuento = data.descuento_valor;
    }

    const subtotal = bruto - totalDescuento;
    const impuesto = data.iva_incluido
        ? Math.round(subtotal * (data.iva_porcentaje / 100))
        : 0;
    const total = subtotal + impuesto;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((data) => ({
            ...data,
            cliente_id: Number(data.cliente_id),
            iva_porcentaje: Number(data.iva_porcentaje),
            descuento_valor: Number(data.descuento_valor),
            detalles: data.detalles.map((d: any) => ({
                ...d,
                producto_id: Number(d.producto_id),
                cantidad: Number(d.cantidad),
                precio_unitario: Number(d.precio_unitario),
            })),
        }));

        post('/facturacion', {
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Está seguro de eliminar esta factura?')) {
            destroy(`/facturacion/${id}`);
        }
    };

    const getEstadoBadge = (estado: string) => {
        switch (estado) {
            case 'pagada':
                return <Badge className="bg-green-500">Pagada</Badge>;
            case 'anulada':
                return <Badge variant="destructive">Anulada</Badge>;
            default:
                return <Badge variant="secondary">Pendiente</Badge>;
        }
    };

    const generarNumero = (tipo: string) => {
        const year = new Date().getFullYear();
        const prefix =
            tipo === 'cotizacion' ? 'COT' : tipo === 'proforma' ? 'PRF' : 'F';
        const count = facturas.data.length + 1;
        return `${prefix}${year}-${count.toString().padStart(4, '0')}`;
    };

    const handleOpenDialog = (tipo: string = 'venta') => {
        setData({
            numero: generarNumero(tipo),
            cliente_id: '',
            fecha: getLocalDateString(),
            fecha_vencimiento: '',
            tipo: tipo,
            notas: '',
            iva_porcentaje: 19,
            iva_incluido: true,
            descuento_tipo: 'none',
            descuento_valor: 0,
            almacen_id: almacenes.length > 0 ? almacenes[0].id.toString() : '',
            detalles: [],
        });
        setIsOpen(true);
    };

    return (
        <>
            <Head title="Facturación" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Facturación</h1>
                            <p className="text-muted-foreground">
                                Gestione las facturas
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {canCreate && (
                                <Button onClick={() => handleOpenDialog('venta')}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Nueva Factura
                                </Button>
                            )}
                            <BulkActions
                                baseUrl="/facturacion"
                                filters={{}}
                                modelName="Facturas"
                            />
                        </div>
                    </div>

                    {stats && (
                        <>
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                <Card className="border-l-4 border-l-blue-500 shadow-sm">
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                                    Facturado este Mes
                                                </p>
                                                <p className="mt-1 text-2xl font-black text-blue-600">
                                                    {formatCurrency(stats.total_facturado_mes)}
                                                </p>
                                            </div>
                                            <div className="rounded-full bg-blue-100 p-3">
                                                <DollarSign className="h-5 w-5 text-blue-600" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card className="border-l-4 border-l-amber-500 shadow-sm">
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                                    Pendiente
                                                </p>
                                                <p className="mt-1 text-2xl font-black text-amber-600">
                                                    {formatCurrency(stats.total_pendiente)}
                                                </p>
                                            </div>
                                            <div className="rounded-full bg-amber-100 p-3">
                                                <Clock className="h-5 w-5 text-amber-600" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card className="border-l-4 border-l-green-500 shadow-sm">
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                                    Cobrado este Mes
                                                </p>
                                                <p className="mt-1 text-2xl font-black text-green-600">
                                                    {formatCurrency(stats.total_pagado_mes)}
                                                </p>
                                            </div>
                                            <div className="rounded-full bg-green-100 p-3">
                                                <TrendingUp className="h-5 w-5 text-green-600" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card className="border-l-4 border-l-purple-500 shadow-sm">
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                                    Facturas Hoy
                                                </p>
                                                <p className="mt-1 text-2xl font-black text-purple-600">
                                                    {stats.facturas_hoy}
                                                </p>
                                            </div>
                                            <div className="rounded-full bg-purple-100 p-3">
                                                <CalendarDays className="h-5 w-5 text-purple-600" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <Card className="shadow-sm">
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-bold">Tendencia Mensual</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-48">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <LineChart data={stats.tendencia_mensual}>
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis dataKey="mes" tick={{ fontSize: 10 }} tickFormatter={(v) => v.slice(5)} />
                                                    <YAxis tick={{ fontSize: 10 }} tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`} />
                                                    <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                                                    <Line type="monotone" dataKey="total" stroke="#3B82F6" strokeWidth={2} dot={{ r: 3 }} />
                                                </LineChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card className="shadow-sm">
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-bold">Por Estado</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-48">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <PieChart>
                                                    <Pie
                                                        data={stats.distribucion_estado}
                                                        dataKey="total"
                                                        nameKey="estado"
                                                        cx="50%"
                                                        cy="50%"
                                                        outerRadius={70}
                                                        label={({ name, percent }) => `${name} ${(Number(percent) * 100).toFixed(0)}%`}
                                                    >
                                                        {stats.distribucion_estado.map((entry) => (
                                                            <Cell key={entry.estado} fill={ESTADO_COLORS[entry.estado] || '#6B7280'} />
                                                        ))}
                                                    </Pie>
                                                    <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                                                </PieChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card className="shadow-sm">
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-bold">Por Tipo</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-48">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart data={stats.distribucion_tipo}>
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                                    <XAxis dataKey="tipo" tick={{ fontSize: 10 }} tickFormatter={(v) => TIPO_LABELS[v] || v} />
                                                    <YAxis tick={{ fontSize: 10 }} tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`} />
                                                    <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                                                    <Bar dataKey="total" fill="#8B5CF6" radius={[4, 4, 0, 0]} />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </>
                    )}

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Facturas</CardTitle>
                                    <CardDescription>
                                        {facturasFiltradas.length} registros encontrados
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-1 rounded-lg border p-1">
                                    <Button
                                        variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('table')}
                                        className="h-8 w-8 p-0"
                                    >
                                        <List className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('cards')}
                                        className="h-8 w-8 p-0"
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar factura, cliente o notas..."
                                            value={filtros.busqueda}
                                            onChange={(e) =>
                                                setFiltros({
                                                    ...filtros,
                                                    busqueda: e.target.value,
                                                })
                                            }
                                            className="h-9 pl-8 pr-8"
                                        />
                                    </div>
                                </div>
                                <select
                                    value={filtros.tipo}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            tipo: e.target.value,
                                        })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Todos los tipos</option>
                                    <option value="venta">Factura</option>
                                    <option value="cotizacion">
                                        Cotización
                                    </option>
                                    <option value="proforma">Proforma</option>
                                </select>
                                <select
                                    value={filtros.cliente_id}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            cliente_id: e.target.value,
                                        })
                                    }
                                    className="flex h-9 min-w-[150px] rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Todos los clientes</option>
                                    {clientes.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.nombre}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    value={filtros.estado}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            estado: e.target.value,
                                        })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Todos los estados</option>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="pagada">Pagada</option>
                                    <option value="anulada">Anulada</option>
                                </select>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9"
                                    onClick={limpiarFiltros}
                                >
                                    <X className="mr-1 h-4 w-4" />
                                    Limpiar
                                </Button>
                            </div>
                            {viewMode === 'table' ? (
                            <>
                                {facturas.data.length === 0 ? (
                                    <div className="py-8 text-center text-muted-foreground">
                                        No hay facturas registradas
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="px-2 py-3 text-left">
                                                        Número
                                                    </th>
                                                    <th className="px-2 py-3 text-left">
                                                        Tipo
                                                    </th>
                                                    <th className="px-2 py-3 text-left">
                                                        Cliente
                                                    </th>
                                                    <th className="px-2 py-3 text-left">
                                                        Fecha
                                                    </th>
                                                    <th className="px-2 py-3 text-right">
                                                        Total
                                                    </th>
                                                    <th className="px-2 py-3 text-center">
                                                        Estado
                                                    </th>
                                                    <th className="px-2 py-3 text-right">
                                                        Acciones
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {facturasFiltradas.map(
                                                    (factura) => (
                                                        <tr
                                                            key={factura.id}
                                                            className="border-b transition-colors hover:bg-muted/30"
                                                        >
                                                            <td className="px-2 py-3">
                                                                <span className="font-mono font-medium">
                                                                    {factura.numero}
                                                                </span>
                                                            </td>
                                                            <td className="px-2 py-3">
                                                                <Badge
                                                                    variant="outline"
                                                                    className="capitalize"
                                                                >
                                                                    {factura.tipo ===
                                                                    'venta'
                                                                        ? 'Factura'
                                                                        : factura.tipo}
                                                                </Badge>
                                                            </td>
                                                            <td className="px-2 py-3">
                                                                {factura.cliente
                                                                    ?.nombre ||
                                                                    'N/A'}
                                                            </td>
                                                            <td className="px-2 py-3 text-muted-foreground">
                                                                {formatDateCLP(
                                                                    factura.fecha,
                                                                )}
                                                            </td>
                                                            <td className="px-2 py-3 text-right font-bold text-green-600">
                                                                {formatCurrency(
                                                                    factura.total,
                                                                )}
                                                            </td>
                                                            <td className="px-2 py-3 text-center">
                                                                {getEstadoBadge(
                                                                    factura.estado,
                                                                )}
                                                            </td>
                                                            <td className="px-2 py-3 text-right">
                                                                <div className="flex justify-end gap-1">
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                                        onClick={() => {
                                                                            setFacturaSeleccionada(
                                                                                factura,
                                                                            );
                                                                            setIsVerOpen(
                                                                                true,
                                                                            );
                                                                        }}
                                                                        title="Ver Detalles"
                                                                    >
                                                                        <Eye className="h-4 w-4" />
                                                                    </Button>
                                                                    {canEdit && (
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            className="h-8 w-8 p-0 text-amber-600 hover:bg-amber-50 hover:text-amber-700"
                                                                            onClick={() => {
                                                                                setFacturaSeleccionada(
                                                                                    factura,
                                                                                );
                                                                                setIsEditOpen(
                                                                                    true,
                                                                                );
                                                                            }}
                                                                            title="Editar / Cambiar Estado"
                                                                        >
                                                                            <Pencil className="h-4 w-4" />
                                                                        </Button>
                                                                    )}
                                                                    <a
                                                                        href={`/facturacion/${factura.id}/pdf`}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input bg-background text-sm font-medium ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                                                                    >
                                                                        <FileText className="h-4 w-4" />
                                                                    </a>
                                                                    {canDelete && (
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                                                            onClick={() =>
                                                                                handleDelete(
                                                                                    factura.id,
                                                                                )
                                                                            }
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                                {facturasFiltradas.length === 0 && (
                                                    <tr>
                                                        <td
                                                            colSpan={6}
                                                            className="py-8 text-center text-muted-foreground"
                                                        >
                                                            No se encontraron
                                                            facturas con los filtros
                                                            aplicados
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {facturasFiltradas.length === 0 ? (
                                    <div className="col-span-full py-8 text-center text-muted-foreground">
                                        No se encontraron facturas con los filtros aplicados
                                    </div>
                                ) : (
                                    facturasFiltradas.map((factura) => (
                                        <Card key={factura.id} className="flex flex-col">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-start justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <CreditCard className="h-5 w-5 text-primary" />
                                                        <CardTitle className="text-sm font-mono font-medium">
                                                            {factura.numero}
                                                        </CardTitle>
                                                    </div>
                                                    {getEstadoBadge(factura.estado)}
                                                </div>
                                                <CardDescription>
                                                    {factura.cliente?.nombre || 'N/A'}
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent className="flex-1">
                                                <div className="space-y-2 text-sm">
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Fecha:</span>
                                                        <span>{formatDateCLP(factura.fecha)}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Total:</span>
                                                        <span className="font-bold text-green-600">{formatCurrency(factura.total)}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Tipo:</span>
                                                        <Badge variant="outline" className="capitalize">
                                                            {factura.tipo === 'venta' ? 'Factura' : factura.tipo}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </CardContent>
                                            <div className="flex items-center gap-1 border-t p-3">
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-blue-600" onClick={() => { setFacturaSeleccionada(factura); setIsVerOpen(true); }} title="Ver Detalles">
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-amber-600" onClick={() => { setFacturaSeleccionada(factura); setIsEditOpen(true); }} title="Editar">
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                <a href={`/facturacion/${factura.id}/pdf`} target="_blank" rel="noreferrer" className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-input bg-background text-sm font-medium transition-colors hover:bg-accent">
                                                    <FileText className="h-4 w-4" />
                                                </a>
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive" onClick={() => handleDelete(factura.id)}>
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </Card>
                                    ))
                                )}
                            </div>
                            )}
                            <Pagination
                                links={facturas.links}
                                meta={
                                    facturas.meta || {
                                        from: (facturas as any).from,
                                        to: (facturas as any).to,
                                        total: facturas.total,
                                    }
                                }
                            />
                        </CardContent>
                    </Card>
                </div>

                <Dialog open={isOpen} onOpenChange={setIsOpen}>
                    <DialogContent className="max-h-[90vh] overflow-x-hidden overflow-y-auto sm:max-w-2xl md:max-w-4xl">
                        <DialogHeader>
                            <DialogTitle>
                                {data.tipo === 'cotizacion'
                                    ? 'Nueva Cotización'
                                    : data.tipo === 'proforma'
                                      ? 'Nueva Proforma'
                                      : 'Nueva Factura'}
                            </DialogTitle>
                            <DialogDescription>
                                Ingrese los datos de la {data.tipo}
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleSubmit}>
                            {Object.keys(errors).length > 0 && (
                                <div className="mb-4 rounded-md bg-destructive/15 p-3 text-sm text-destructive">
                                    <p className="font-semibold">
                                        Por favor corrija los siguientes
                                        errores:
                                    </p>
                                    <ul className="list-inside list-disc">
                                        {Object.values(errors).map((err, i) => (
                                            <li key={i}>
                                                {typeof err === 'string'
                                                    ? err
                                                    : JSON.stringify(err)}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            <div className="grid gap-4 py-4">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="numero">Número</Label>
                                        <Input
                                            id="numero"
                                            value={data.numero}
                                            onChange={(e) =>
                                                setData(
                                                    'numero',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="cliente">Cliente</Label>
                                        <select
                                            id="cliente"
                                            value={data.cliente_id}
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setData('cliente_id', val);
                                                setData(
                                                    'is_manual_cliente',
                                                    val === 'new',
                                                );
                                            }}
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                            required
                                        >
                                            <option value="">
                                                Seleccionar cliente
                                            </option>
                                            <option
                                                value="new"
                                                className="font-bold text-primary"
                                            >
                                                + NUEVO CLIENTE (INGRESO MANUAL)
                                            </option>
                                            {clientes.map((cliente) => (
                                                <option
                                                    key={cliente.id}
                                                    value={cliente.id}
                                                >
                                                    {cliente.nombre}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Campos de Cliente Manual */}
                                    {data.is_manual_cliente && (
                                        <div className="col-span-full grid grid-cols-1 gap-4 rounded-lg bg-muted/50 p-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>RUT Cliente *</Label>
                                                <div className="flex gap-2">
                                                    <Input
                                                        value={data.manual_rut}
                                                        onChange={(e) =>
                                                            setData(
                                                                'manual_rut',
                                                                e.target.value,
                                                            )
                                                        }
                                                        onBlur={(e) =>
                                                            setData(
                                                                'manual_rut',
                                                                formatRut(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        placeholder="12345678-9"
                                                        required
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        onClick={
                                                            handleValidarRut
                                                        }
                                                        disabled={
                                                            validatingRut ||
                                                            !data.manual_rut
                                                        }
                                                    >
                                                        {validatingRut
                                                            ? '...'
                                                            : 'Validar'}
                                                    </Button>
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Razón Social / Nombre *
                                                </Label>
                                                <Input
                                                    value={
                                                        data.manual_razon_social
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'manual_razon_social',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Ej: Empresa S.A."
                                                    required
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Giro Comercial *</Label>
                                                <Input
                                                    value={data.manual_giro}
                                                    onChange={(e) =>
                                                        setData(
                                                            'manual_giro',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Ej: Venta de materiales"
                                                    required
                                                    list="giros-suggestions"
                                                />
                                                <datalist id="giros-suggestions">
                                                    {extraGiros.map((g, i) => (
                                                        <option
                                                            key={i}
                                                            value={g}
                                                        />
                                                    ))}
                                                </datalist>
                                                {extraGiros.length > 1 && (
                                                    <p className="text-[10px] text-muted-foreground italic">
                                                        Este RUT tiene{' '}
                                                        {extraGiros.length}{' '}
                                                        actividades sugeridas.
                                                    </p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Dirección *</Label>
                                                <Input
                                                    value={
                                                        data.manual_direccion
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'manual_direccion',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Calle, Número, Depto"
                                                    required
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Comuna *</Label>
                                                <select
                                                    value={data.manual_comuna}
                                                    onChange={(e) =>
                                                        setData(
                                                            'manual_comuna',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                                    required
                                                >
                                                    <option value="">
                                                        Seleccionar comuna
                                                    </option>
                                                    {comunasChile.map((c) => (
                                                        <option
                                                            key={c}
                                                            value={c}
                                                        >
                                                            {c}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Ciudad / Región</Label>
                                                <Input
                                                    value={data.manual_ciudad}
                                                    onChange={(e) =>
                                                        setData(
                                                            'manual_ciudad',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Ej: Santiago"
                                                />
                                            </div>
                                        </div>
                                    )}
                                    <div className="space-y-2">
                                        <Label htmlFor="almacen">
                                            Almacén (Origen de Stock)
                                        </Label>
                                        <select
                                            id="almacen"
                                            value={data.almacen_id}
                                            onChange={(e) =>
                                                setData(
                                                    'almacen_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                            required
                                        >
                                            <option value="">
                                                Seleccionar almacén
                                            </option>
                                            {almacenes.map((almacen) => (
                                                <option
                                                    key={almacen.id}
                                                    value={almacen.id}
                                                >
                                                    {almacen.nombre}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="fecha">Fecha</Label>
                                        <Input
                                            id="fecha"
                                            type="date"
                                            value={data.fecha}
                                            onChange={(e) =>
                                                setData('fecha', e.target.value)
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="fecha_vencimiento">
                                            Vencimiento
                                        </Label>
                                        <Input
                                            id="fecha_vencimiento"
                                            type="date"
                                            value={data.fecha_vencimiento}
                                            onChange={(e) =>
                                                setData(
                                                    'fecha_vencimiento',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="notas">Notas</Label>
                                    <Input
                                        id="notas"
                                        value={data.notas}
                                        onChange={(e) =>
                                            setData('notas', e.target.value)
                                        }
                                        placeholder="Notas adicionales"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <Label>Productos</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleAddDetalle}
                                        >
                                            <Plus className="mr-1 h-4 w-4" />
                                            Agregar
                                        </Button>
                                    </div>
                                    <div className="max-h-60 space-y-2 overflow-y-auto">
                                        {data.detalles.map((detalle, index) => (
                                            <div
                                                key={index}
                                                className="flex items-start gap-2"
                                            >
                                                <select
                                                    value={detalle.producto_id}
                                                    onChange={(e) =>
                                                        handleDetalleChange(
                                                            index,
                                                            'producto_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="flex h-10 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                    required
                                                >
                                                    <option value={0}>
                                                        Seleccionar producto
                                                    </option>
                                                    {productos.map(
                                                        (producto) => (
                                                            <option
                                                                key={
                                                                    producto.id
                                                                }
                                                                value={
                                                                    producto.id
                                                                }
                                                            >
                                                                {
                                                                    producto.nombre
                                                                }{' '}
                                                                -{' '}
                                                                {formatCurrency(
                                                                    producto.precio_venta,
                                                                )}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <Input
                                                    type="number"
                                                    placeholder="Cant"
                                                    value={
                                                        detalle.cantidad || ''
                                                    }
                                                    onChange={(e) =>
                                                        handleDetalleChange(
                                                            index,
                                                            'cantidad',
                                                            parseInt(
                                                                e.target.value,
                                                            ) || 0,
                                                        )
                                                    }
                                                    className="w-20"
                                                    min="1"
                                                    required
                                                />
                                                <Input
                                                    type="number"
                                                    placeholder="Precio"
                                                    value={
                                                        detalle.precio_unitario ||
                                                        ''
                                                    }
                                                    onChange={(e) =>
                                                        handleDetalleChange(
                                                            index,
                                                            'precio_unitario',
                                                            parseInt(
                                                                e.target.value,
                                                            ) || 0,
                                                        )
                                                    }
                                                    className="w-24"
                                                    step="1"
                                                    readOnly
                                                />
                                                <div className="w-24 py-2 text-right">
                                                    {formatCurrency(
                                                        detalle.total,
                                                    )}
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        handleRemoveDetalle(
                                                            index,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="grid grid-cols-1 gap-4 border-t pt-4 md:grid-cols-2">
                                        <div className="space-y-4">
                                            <div className="space-y-2">
                                                <Label>Impuestos (IVA)</Label>
                                                <div className="flex items-center gap-4">
                                                    <div className="flex items-center space-x-2">
                                                        <input
                                                            type="checkbox"
                                                            id="iva_incluido"
                                                            checked={
                                                                data.iva_incluido
                                                            }
                                                            onChange={(e) =>
                                                                setData(
                                                                    'iva_incluido',
                                                                    e.target
                                                                        .checked,
                                                                )
                                                            }
                                                            className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                        />
                                                        <Label
                                                            htmlFor="iva_incluido"
                                                            className="cursor-pointer"
                                                        >
                                                            Aplicar IVA
                                                        </Label>
                                                    </div>
                                                    {data.iva_incluido && (
                                                        <div className="flex items-center gap-2">
                                                            <Input
                                                                type="number"
                                                                value={
                                                                    data.iva_porcentaje
                                                                }
                                                                onChange={(e) =>
                                                                    setData(
                                                                        'iva_porcentaje',
                                                                        parseInt(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ) || 0,
                                                                    )
                                                                }
                                                                className="h-8 w-20"
                                                            />
                                                            <span className="text-sm text-muted-foreground">
                                                                %
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Descuento</Label>
                                                <div className="flex items-center gap-2">
                                                    <select
                                                        value={
                                                            data.descuento_tipo
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'descuento_tipo',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="flex h-8 w-32 rounded-md border border-input bg-background px-2 py-1 font-sans text-sm"
                                                        style={{
                                                            appearance: 'auto',
                                                        }}
                                                    >
                                                        <option value="none">
                                                            Sin desc.
                                                        </option>
                                                        <option value="porcentaje">
                                                            Porcentaje (%)
                                                        </option>
                                                        <option value="monto">
                                                            Monto ($)
                                                        </option>
                                                    </select>
                                                    {data.descuento_tipo !==
                                                        'none' && (
                                                        <Input
                                                            type="number"
                                                            value={
                                                                data.descuento_valor
                                                            }
                                                            onChange={(e) =>
                                                                setData(
                                                                    'descuento_valor',
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0,
                                                                )
                                                            }
                                                            className="h-8 w-24"
                                                            placeholder={
                                                                data.descuento_tipo ===
                                                                'porcentaje'
                                                                    ? '%'
                                                                    : '$'
                                                            }
                                                        />
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-1 rounded-lg bg-muted/30 p-3 text-right">
                                            <p className="text-sm text-muted-foreground">
                                                Bruto:{' '}
                                                {formatCurrency(bruto)}
                                            </p>
                                            {totalDescuento > 0 && (
                                                <p className="text-sm text-destructive">
                                                    Descuento: -
                                                    {formatCurrency(
                                                        totalDescuento,
                                                    )}
                                                </p>
                                            )}
                                            <p className="text-sm">
                                                Subtotal:{' '}
                                                <span className="font-medium">
                                                    {formatCurrency(
                                                        subtotal,
                                                    )}
                                                </span>
                                            </p>
                                            <p className="text-sm">
                                                IVA (
                                                {data.iva_incluido
                                                    ? data.iva_porcentaje
                                                    : 0}
                                                %):{' '}
                                                <span className="font-medium">
                                                    {formatCurrency(
                                                        impuesto,
                                                    )}
                                                </span>
                                            </p>
                                            <p className="mt-2 text-xl font-bold">
                                                Total:{' '}
                                                <span className="font-extrabold text-green-600">
                                                    {formatCurrency(total)}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsOpen(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        data.detalles.length === 0 ||
                                        !data.cliente_id
                                    }
                                >
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
                <VerFacturaDialog
                    factura={facturaSeleccionada}
                    isOpen={isVerOpen}
                    setIsOpen={setIsVerOpen}
                />

                <EditarFacturaDialog
                    factura={facturaSeleccionada}
                    isOpen={isEditOpen}
                    setIsOpen={setIsEditOpen}
                    clientes={clientes}
                    productos={productos}
                    almacenes={almacenes}
                />
            </AppLayout>
        </>
    );
}

function VerFacturaDialog({
    factura,
    isOpen,
    setIsOpen,
}: {
    factura: Factura | null;
    isOpen: boolean;
    setIsOpen: (o: boolean) => void;
}) {
    if (!factura) return null;

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-hidden bg-white p-0">
                <div className="max-h-[calc(90vh)] overflow-y-auto overflow-x-auto p-8 font-sans text-xs text-gray-800">
                    {/* Header: SII Box and Company Info */}
                    <div className="mb-8 flex flex-wrap justify-between gap-4">
                        <div className="w-full sm:w-3/5">
                            <h1 className="text-xl font-black text-blue-900 uppercase">
                                {factura.emisor?.razon_social ||
                                    factura.emisor?.name ||
                                    'NOMBRE EMPRESA'}
                            </h1>
                            <p className="text-[10px] font-bold text-gray-600 uppercase">
                                {factura.emisor?.giro || 'GIRO COMERCIAL'}
                            </p>
                            <div className="mt-2 text-[10px] text-gray-500">
                                <p>
                                    {factura.emisor?.direccion || 'DIRECCIÓN'}
                                </p>
                                <p>
                                    {factura.emisor?.comuna || ''}
                                    {factura.emisor?.ciudad
                                        ? `, ${factura.emisor.ciudad}`
                                        : ''}
                                </p>
                                <p>
                                    Teléfono: {factura.emisor?.telefono || '-'}
                                </p>
                                <p>Email: {factura.emisor?.email}</p>
                            </div>
                        </div>

                        <div className="w-full border-2 border-red-600 p-4 text-center sm:w-[220px]">
                            <h2 className="text-sm font-bold text-red-600">
                                R.U.T.: {factura.emisor?.rut || 'N/A'}
                            </h2>
                            <div className="my-2 border-y border-red-600 py-2 text-lg font-black text-red-600 uppercase">
                                {factura.tipo === 'venta'
                                    ? 'FACTURA'
                                    : factura.tipo === 'compra'
                                      ? 'FACTURA DE COMPRA'
                                      : factura.tipo.toUpperCase()}
                            </div>
                            <h2 className="text-xl font-black text-red-600">
                                Nº {factura.numero}
                            </h2>
                            <p className="mt-2 text-[9px] font-bold text-red-600 uppercase">
                                S.I.I. - SANTIAGO
                            </p>
                        </div>
                    </div>

                    <div className="mb-4">
                        <p className="text-sm">
                            <span className="font-bold uppercase">
                                SANTIAGO,{' '}
                            </span>
                            {formatDateCLP(factura.fecha)}
                        </p>
                    </div>

                    {/* Client Info Section */}
                    <div className="mb-6">
                        <div className="mb-2 border border-gray-300 bg-gray-100 p-1 px-3 text-[10px] font-bold uppercase">
                            Información del Receptor
                        </div>
                        <table className="w-full border-collapse">
                            <tbody>
                                <tr>
                                    <td className="w-1/6 border border-gray-300 bg-gray-50 p-2 font-bold">
                                        Señor(es)
                                    </td>
                                    <td className="w-2/6 border border-gray-300 p-2 uppercase">
                                        {factura.cliente?.nombre}
                                    </td>
                                    <td className="w-1/6 border border-gray-300 bg-gray-50 p-2 font-bold">
                                        R.U.T.
                                    </td>
                                    <td className="w-2/6 border border-gray-300 p-2">
                                        {factura.cliente?.nit || 'N/A'}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="border border-gray-300 bg-gray-50 p-2 font-bold">
                                        Giro
                                    </td>
                                    <td
                                        colSpan={3}
                                        className="border border-gray-300 p-2 uppercase"
                                    >
                                        PARTICULAR / GIRO NO ESPECIFICADO
                                    </td>
                                </tr>
                                <tr>
                                    <td className="border border-gray-300 bg-gray-50 p-2 font-bold">
                                        Dirección
                                    </td>
                                    <td className="border border-gray-300 p-2 uppercase">
                                        S/D
                                    </td>
                                    <td className="border border-gray-300 bg-gray-50 p-2 font-bold">
                                        Comuna
                                    </td>
                                    <td className="border border-gray-300 p-2 uppercase">
                                        N/A
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Items Table */}
                    <div className="mb-6">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="bg-blue-900 text-[9px] font-bold text-white uppercase">
                                    <th className="w-[8%] border border-blue-900 p-2 text-center">
                                        Cant.
                                    </th>
                                    <th className="border border-blue-900 p-2 text-left">
                                        Descripción
                                    </th>
                                    <th className="w-[15%] border border-blue-900 p-2 text-right">
                                        P. Unitario
                                    </th>
                                    <th className="w-[15%] border border-blue-900 p-2 text-right">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {factura.detalles?.map((item, i) => (
                                    <tr
                                        key={i}
                                        className="border border-gray-300"
                                    >
                                        <td className="border border-gray-300 p-2 text-center">
                                            {item.cantidad}
                                        </td>
                                        <td className="border border-gray-300 p-2 uppercase">
                                            {item.producto?.nombre ||
                                                'Producto'}
                                        </td>
                                        <td className="border border-gray-300 p-2 text-right">
                                            {formatCurrency(
                                                item.precio_unitario,
                                            )}
                                        </td>
                                        <td className="border border-gray-300 p-2 text-right font-bold">
                                            {formatCurrency(item.total)}
                                        </td>
                                    </tr>
                                ))}
                                {(!factura.detalles ||
                                    factura.detalles.length === 0) && (
                                    <tr className="border border-gray-300">
                                        <td
                                            colSpan={4}
                                            className="p-10 text-center text-gray-400 italic"
                                        >
                                            No se encontraron productos
                                            registrados en este documento.
                                        </td>
                                    </tr>
                                )}
                                {/* Spacer rows like in PDF */}
                                {factura.detalles &&
                                    factura.detalles.length > 0 &&
                                    Array.from({
                                        length: Math.max(
                                            0,
                                            5 - factura.detalles.length,
                                        ),
                                    }).map((_, i) => (
                                        <tr
                                            key={`spacer-${i}`}
                                            className="h-8 border border-gray-300"
                                        >
                                            <td className="border border-gray-300 p-2"></td>
                                            <td className="border border-gray-300 p-2"></td>
                                            <td className="border border-gray-300 p-2"></td>
                                            <td className="border border-gray-300 p-2"></td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Totals Summary */}
                    <div className="mb-8 flex justify-end">
                        <div className="w-72">
                            <table className="w-full border-collapse">
                                <tbody>
                                    <tr>
                                        <td className="w-1/2 border border-gray-300 bg-gray-50 p-2 text-right font-bold">
                                            Sub Total (Neto)
                                        </td>
                                        <td className="border border-gray-300 p-2 text-right font-bold">
                                            {formatCurrency(
                                                factura.subtotal,
                                            )}
                                        </td>
                                    </tr>
                                    {factura.total_descuento > 0 && (
                                        <tr>
                                            <td className="border border-gray-300 bg-gray-50 p-2 text-right font-bold">
                                                Descuento
                                            </td>
                                            <td className="border border-gray-300 p-2 text-right font-bold text-red-600">
                                                -
                                                {formatCurrency(
                                                    factura.total_descuento,
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                    <tr>
                                        <td className="border border-gray-300 bg-gray-50 p-2 text-right font-bold">
                                            IVA ({factura.iva_porcentaje || 19}
                                            %)
                                        </td>
                                        <td className="border border-gray-300 p-2 text-right font-bold">
                                            {formatCurrency(
                                                factura.impuesto,
                                            )}
                                        </td>
                                    </tr>
                                    <tr className="bg-gray-100 text-lg">
                                        <td className="border border-gray-300 p-2 text-right font-black">
                                            TOTAL
                                        </td>
                                        <td className="border border-gray-300 p-2 text-right font-black text-blue-900">
                                            {formatCurrency(factura.total)}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Notes and Footer */}
                    {factura.notas && (
                        <div className="mb-4 border border-gray-300 bg-white p-3">
                            <p className="mb-1 font-bold underline">
                                Observaciones / Notas:
                            </p>
                            <p className="whitespace-pre-wrap">
                                {factura.notas}
                            </p>
                        </div>
                    )}

                    {factura.fecha_vencimiento && (
                        <p className="mb-4 text-[10px] font-bold">
                            Fecha de Vencimiento:{' '}
                            {formatDateCLP(factura.fecha_vencimiento)}
                        </p>
                    )}

                    <div className="mt-10 border-t border-gray-200 pt-4 text-center text-[9px] text-gray-500">
                        <p>Gracias por su preferencia.</p>
                        <p className="mt-1 font-bold text-gray-800">
                            DOCUMENTO TRIBUTARIO ELECTRÓNICO SEGÚN ART. 1° D.L.
                            3505 Y RES. EX. SII N° 48/2016
                        </p>
                        <p className="mt-2 italic">
                            Generado el {new Date().toLocaleString()}
                        </p>
                    </div>
                </div>

                <DialogFooter className="border-t bg-gray-50 p-4">
                    <div className="flex w-full items-center justify-between">
                        <div>{caseEstado(factura.estado)}</div>
                        <div className="flex gap-2">
                            <a
                                href={`/facturacion/${factura.id}/pdf`}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition-colors hover:bg-gray-50"
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Descargar PDF
                            </a>
                            <Button
                                onClick={() => setIsOpen(false)}
                                variant="secondary"
                                className="font-bold"
                            >
                                Cerrar
                            </Button>
                        </div>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function EditarFacturaDialog({
    factura,
    isOpen,
    setIsOpen,
    clientes,
    productos,
    almacenes,
}: {
    factura: Factura | null;
    isOpen: boolean;
    setIsOpen: (o: boolean) => void;
    clientes: Cliente[];
    productos: Producto[];
    almacenes: Almacen[];
}) {
    const [processing, setProcessing] = useState(false);

    const { data, setData, reset } = useForm<{
        numero: string;
        cliente_id: string;
        fecha: string;
        fecha_vencimiento: string;
        tipo: string;
        notas: string;
        iva_porcentaje: number;
        iva_incluido: boolean;
        descuento_tipo: string;
        descuento_valor: number;
        almacen_id: string;
        estado: string;
        detalles: DetalleFactura[];
    }>({
        numero: factura?.numero || '',
        cliente_id: factura?.cliente_id?.toString() || '',
        fecha: factura?.fecha
            ? (() => {
                const parts = factura.fecha.split('-');
                return `${parts[0]}-${parts[1]}-${parts[2]}`;
            })()
            : '',
        fecha_vencimiento: factura?.fecha_vencimiento
            ? (() => {
                const parts = factura.fecha_vencimiento.split('-');
                return `${parts[0]}-${parts[1]}-${parts[2]}`;
            })()
            : '',
        tipo: factura?.tipo || '',
        notas: factura?.notas || '',
        iva_porcentaje: factura?.iva_porcentaje || 0,
        iva_incluido: factura?.iva_incluido || false,
        descuento_tipo: factura?.descuento_tipo || '',
        descuento_valor: factura?.descuento_valor || 0,
        almacen_id: (factura?.almacen_id || '').toString(),
        estado: factura?.estado || '',
        detalles: factura?.detalles || ([] as DetalleFactura[]),
    });

    // Sync form whenever the selected factura changes
    useEffect(() => {
        if (!factura) return;
        setData({
            numero: factura.numero,
            cliente_id: factura.cliente_id.toString(),
            fecha: factura.fecha
                ? (() => {
                    const parts = factura.fecha.split('-');
                    return `${parts[0]}-${parts[1]}-${parts[2]}`;
                })()
                : '',
            fecha_vencimiento: factura.fecha_vencimiento
                ? (() => {
                    const parts = factura.fecha_vencimiento.split('-');
                    return `${parts[0]}-${parts[1]}-${parts[2]}`;
                })()
                : '',
            tipo: factura.tipo,
            notas: factura.notas || '',
            iva_porcentaje: factura.iva_porcentaje,
            iva_incluido: factura.iva_incluido,
            descuento_tipo: factura.descuento_tipo,
            descuento_valor: factura.descuento_valor,
            almacen_id: (factura.almacen_id || '').toString(),
            estado: factura.estado,
            detalles: factura.detalles || [],
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [factura]);

    const addDetalle = () => {
        setData('detalles', [
            ...data.detalles,
            {
                producto_id: 0,
                cantidad: 1,
                precio_unitario: 0,
                subtotal: 0,
                impuesto: 0,
                total: 0,
            },
        ]);
    };

    const removeDetalle = (index: number) => {
        const newDetalles = [...data.detalles];
        newDetalles.splice(index, 1);
        setData('detalles', newDetalles);
    };

    const updateDetalle = (
        index: number,
        field: keyof DetalleFactura,
        value: any,
    ) => {
        const newDetalles = [...data.detalles];
        newDetalles[index] = { ...newDetalles[index], [field]: value };

        // Auto-fill price if product changes
        if (field === 'producto_id') {
            const product = productos.find((p) => p.id === Number(value));
            if (product) {
                newDetalles[index].precio_unitario = product.precio_venta;
            }
        }

        setData('detalles', newDetalles);
    };

    const subtotal = useMemo(() => {
        return data.detalles.reduce(
            (acc, curr) => acc + curr.cantidad * curr.precio_unitario,
            0,
        );
    }, [data.detalles]);

    const totalDescuento = useMemo(() => {
        if (data.descuento_tipo === 'porcentaje') {
            return Math.round(subtotal * (data.descuento_valor / 100));
        } else if (data.descuento_tipo === 'monto') {
            return data.descuento_valor;
        }
        return 0;
    }, [subtotal, data.descuento_tipo, data.descuento_valor]);

    const montoFinal = subtotal - totalDescuento;

    const impuesto = useMemo(() => {
        if (data.iva_incluido) {
            return Math.round(
                montoFinal - montoFinal / (1 + data.iva_porcentaje / 100),
            );
        }
        return Math.round(montoFinal * (data.iva_porcentaje / 100));
    }, [montoFinal, data.iva_porcentaje, data.iva_incluido]);

    const total = data.iva_incluido ? montoFinal : montoFinal + impuesto;

    if (!factura) return null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.put(
            `/facturacion/${factura.id}`,
            {
                ...data,
                cliente_id: Number(data.cliente_id),
                almacen_id: data.almacen_id ? Number(data.almacen_id) : null,
                // eslint-disable-next-line @typescript-eslint/no-unused-vars
                detalles: data.detalles.map(({ producto: _p, ...d }) => ({
                    ...d,
                    producto_id: Number(d.producto_id),
                    cantidad: Number(d.cantidad),
                    precio_unitario: Number(d.precio_unitario),
                })),
            },
            {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl md:max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="text-2xl font-black uppercase">
                        Editar Documento {factura.numero}
                    </DialogTitle>
                    <DialogDescription>
                        Modifica los datos del documento tributario.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-6 py-4">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label>Número de Documento</Label>
                            <Input
                                value={data.numero}
                                onChange={(e) =>
                                    setData('numero', e.target.value)
                                }
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Tipo de Documento</Label>
                            <Select
                                value={data.tipo}
                                onValueChange={(val) => setData('tipo', val)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="venta">
                                        Factura
                                    </SelectItem>
                                    <SelectItem value="compra">
                                        Factura Compra
                                    </SelectItem>
                                    <SelectItem value="cotizacion">
                                        Cotización
                                    </SelectItem>
                                    <SelectItem value="proforma">
                                        Proforma
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Estado</Label>
                            <Select
                                value={data.estado}
                                onValueChange={(val) => setData('estado', val)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pendiente">
                                        Pendiente
                                    </SelectItem>
                                    <SelectItem value="pagada">
                                        Pagada
                                    </SelectItem>
                                    <SelectItem value="anulada">
                                        Anulada
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Cliente / Receptor</Label>
                            <Select
                                value={data.cliente_id}
                                onValueChange={(val) =>
                                    setData('cliente_id', val)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar cliente" />
                                </SelectTrigger>
                                <SelectContent>
                                    {clientes.map((c) => (
                                        <SelectItem
                                            key={c.id}
                                            value={c.id.toString()}
                                        >
                                            {c.nombre} (RUT: {c.nit})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Almacén / Depósito</Label>
                            <Select
                                value={data.almacen_id}
                                onValueChange={(val) =>
                                    setData('almacen_id', val)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar almacén" />
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
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Fecha Emisión</Label>
                            <Input
                                type="date"
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Fecha Vencimiento</Label>
                            <Input
                                type="date"
                                value={data.fecha_vencimiento}
                                onChange={(e) =>
                                    setData('fecha_vencimiento', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="flex items-center justify-between border-b pb-2">
                            <h3 className="flex items-center gap-2 font-bold uppercase">
                                <CreditCard className="h-4 w-4" />
                                Ítems / Productos
                            </h3>
                            <Button
                                type="button"
                                size="sm"
                                onClick={addDetalle}
                                className="gap-1"
                            >
                                <Plus className="h-4 w-4" /> Añadir Ítem
                            </Button>
                        </div>

                        {data.detalles.length === 0 && (
                            <div className="rounded-xl border-2 border-dashed py-10 text-center text-muted-foreground italic">
                                No hay ítems en este documento. Haz clic en
                                "Añadir Ítem".
                            </div>
                        )}

                        {data.detalles.map((detalle, index) => (
                            <div
                                key={index}
                                className="flex animate-in items-end gap-4 duration-300 fade-in slide-in-from-top-2"
                            >
                                <div className="flex-1 space-y-2">
                                    <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                        Producto
                                    </Label>
                                    <Select
                                        value={detalle.producto_id.toString()}
                                        onValueChange={(val) =>
                                            updateDetalle(
                                                index,
                                                'producto_id',
                                                val,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Producto" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {productos.map((p) => (
                                                <SelectItem
                                                    key={p.id}
                                                    value={p.id.toString()}
                                                >
                                                    {p.nombre} (
                                                    {formatCurrency(
                                                        p.precio_venta,
                                                    )}
                                                    )
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="w-24 space-y-2">
                                    <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                        Cant.
                                    </Label>
                                    <Input
                                        type="number"
                                        value={detalle.cantidad}
                                        onChange={(e) =>
                                            updateDetalle(
                                                index,
                                                'cantidad',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        min="0.01"
                                        step="1"
                                    />
                                </div>
                                <div className="w-32 space-y-2">
                                    <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                        Precio Unit.
                                    </Label>
                                    <Input
                                        type="number"
                                        value={detalle.precio_unitario}
                                        onChange={(e) =>
                                            updateDetalle(
                                                index,
                                                'precio_unitario',
                                                parseInt(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="w-32 space-y-2">
                                    <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                        Suma
                                    </Label>
                                    <div className="flex h-10 items-center rounded-md bg-muted px-3 font-bold">
                                        {formatCurrency(
                                            detalle.cantidad *
                                                detalle.precio_unitario,
                                        )}
                                    </div>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="text-red-500 hover:bg-red-50 hover:text-red-700"
                                    onClick={() => removeDetalle(index)}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                    </div>

                    <div className="grid grid-cols-1 gap-10 border-t pt-6 md:grid-cols-2">
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div className="space-y-2">
                                    <Label>Tipo Descuento</Label>
                                    <Select
                                        value={data.descuento_tipo}
                                        onValueChange={(val) =>
                                            setData('descuento_tipo', val)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Sin Descuento
                                            </SelectItem>
                                            <SelectItem value="porcentaje">
                                                Porcentaje (%)
                                            </SelectItem>
                                            <SelectItem value="monto">
                                                Monto Fijo ($)
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Valor Descuento</Label>
                                    <Input
                                        type="number"
                                        value={data.descuento_valor}
                                        onChange={(e) =>
                                            setData(
                                                'descuento_valor',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        disabled={
                                            data.descuento_tipo === 'none'
                                        }
                                    />
                                </div>
                            </div>
                            <div className="flex items-center gap-4 rounded-xl border border-dashed bg-muted/20 p-4">
                                <div className="flex-1 space-y-1">
                                    <Label className="text-xs font-bold uppercase">
                                        Configuración de Impuestos
                                    </Label>
                                    <div className="mt-2 flex items-center gap-4">
                                        <div className="flex items-center space-x-2">
                                            <Input
                                                type="checkbox"
                                                id="edit-iva-incluido"
                                                className="h-4 w-4"
                                                checked={data.iva_incluido}
                                                onChange={(e) =>
                                                    setData(
                                                        'iva_incluido',
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor="edit-iva-incluido"
                                                className="cursor-pointer text-xs"
                                            >
                                                Precios con IVA incluido
                                            </Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Label className="text-xs">
                                                Tasa IVA:
                                            </Label>
                                            <Input
                                                type="number"
                                                className="h-8 w-16 text-xs"
                                                value={data.iva_porcentaje}
                                                onChange={(e) =>
                                                    setData(
                                                        'iva_porcentaje',
                                                        parseInt(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                            <span className="text-xs">%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Observaciones / Notas Públicas</Label>
                                <textarea
                                    className="min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    value={data.notas}
                                    onChange={(e) =>
                                        setData('notas', e.target.value)
                                    }
                                    placeholder="Estas notas aparecerán en el PDF..."
                                />
                            </div>
                        </div>

                        <div className="space-y-3 rounded-2xl bg-gray-50 p-6">
                            <h4 className="mb-4 border-b pb-2 text-sm font-bold text-gray-400 uppercase">
                                Resumen Cálculos
                            </h4>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Subtotal Bruto:
                                </span>
                                <span>{formatCurrency(subtotal)}</span>
                            </div>
                            <div className="flex items-center justify-between text-sm text-red-600">
                                <span className="text-muted-foreground">
                                    Descuento Global ({data.descuento_tipo}):
                                </span>
                                <span>
                                    -{formatCurrency(totalDescuento)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Total Neto:
                                </span>
                                <span className="font-bold">
                                    {formatCurrency(montoFinal)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    IVA ({data.iva_porcentaje}%):
                                </span>
                                <span className="font-bold">
                                    {formatCurrency(impuesto)}
                                </span>
                            </div>
                            <div className="mt-2 flex items-center justify-between border-t border-gray-200 pt-4">
                                <span className="text-lg font-black uppercase">
                                    Total a Pagar:
                                </span>
                                <span className="text-3xl font-black text-blue-800">
                                    {formatCurrency(total)}
                                </span>
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="pt-6">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setIsOpen(false)}
                            disabled={processing}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || data.detalles.length === 0}
                            className="min-w-[150px] bg-blue-600 font-bold uppercase hover:bg-blue-700"
                        >
                            {processing ? 'Guardando...' : 'Guardar Cambios'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const caseEstado = (estado: string) => {
    switch (estado) {
        case 'pagada':
            return (
                <Badge className="rounded-full bg-green-500 px-4 py-1 font-black tracking-tight uppercase shadow-lg shadow-green-500/20">
                    PAGADA
                </Badge>
            );
        case 'anulada':
            return (
                <Badge
                    variant="destructive"
                    className="rounded-full px-4 py-1 font-black tracking-tight uppercase shadow-lg shadow-destructive/20"
                >
                    ANULADA
                </Badge>
            );
        default:
            return (
                <Badge
                    variant="secondary"
                    className="rounded-full px-4 py-1 font-black tracking-tight uppercase"
                >
                    PENDIENTE
                </Badge>
            );
    }
};
