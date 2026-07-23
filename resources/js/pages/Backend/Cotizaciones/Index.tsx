import { Head, useForm, router } from '@inertiajs/react';
import {
    Check,
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Eye,
    Calculator,
    Calendar,
    User,
    Package,
    ShoppingCart,
    AlertCircle,
    FileCheck,
    Printer,
    DollarSign,
    ClipboardList,
    MoreHorizontal
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { BulkActions } from '@/components/shared/BulkActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import {
} from '@/components/ui/dropdown-menu';
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
import { formatCurrency, formatDateCLP, getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Cliente {
    id: number;
    nombre: string;
}

interface Producto {
    id: number;
    nombre: string;
    descripcion: string | null;
    precio_venta: number;
}

interface CotizacionDetalle {
    producto_id?: number | null;
    descripcion: string;
    cantidad: number;
    precio: number;
}

interface Cotizacion {
    id: number;
    numero: string;
    cliente_id: number;
    cliente?: Cliente;
    fecha: string;
    fecha_validez: string | null;
    subtotal: number;
    impuesto: number;
    total: number;
    estado: string;
    notas: string | null;
    detalles: CotizacionDetalle[] | null;
    condiciones: string | null;
    iva_personalizado: number;
    descuento_tipo: 'monto' | 'porcentaje' | null;
    descuento_monto: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ventas', href: '/cotizaciones' },
    { title: 'Presupuestos y Cotizaciones', href: '/cotizaciones' },
];

const ESTADOS = [
    {
        value: 'borrador',
        label: 'Borrador',
        color: 'bg-gray-500/10 text-gray-600 border-gray-200',
    },
    {
        value: 'enviada',
        label: 'Enviada',
        color: 'bg-blue-500/10 text-blue-600 border-blue-200',
    },
    {
        value: 'aceptada',
        label: 'Aceptada',
        color: 'bg-green-500/10 text-green-600 border-green-200',
    },
    {
        value: 'rechazada',
        label: 'Rechazada',
        color: 'bg-red-500/10 text-red-600 border-red-200',
    },
    {
        value: 'expirada',
        label: 'Expirada',
        color: 'bg-orange-500/10 text-orange-600 border-orange-200',
    },
];

export default function Index({
    cotizaciones,
    clientes,
    productos,
    filters,
}: {
    cotizaciones: { data: Cotizacion[]; links: any[]; meta: any };
    clientes: Cliente[];
    productos: Producto[];
    filters: {
        search?: string;
        cliente_id?: string;
        estado?: string;
        fechaDesde?: string;
        fechaHasta?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('comercial.cotizaciones.create');
    const canEdit = hasPermission('comercial.cotizaciones.edit');
    const canDelete = hasPermission('comercial.cotizaciones.delete');
    const [isOpen, setIsOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [editando, setEditando] = useState<Cotizacion | null>(null);
    const [viendo, setViendo] = useState<Cotizacion | null>(null);

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [clienteFilter, setClienteFilter] = useState(
        filters.cliente_id || 'all',
    );
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [estadoFilter, setEstadoFilter] = useState(filters.estado || 'all');

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        processing,
    } = useForm({
        numero: '',
        cliente_id: '' as string | number,
        fecha: getLocalDateString(),
        fecha_validez: '',
        subtotal: 0,
        impuesto: 0,
        total: 0,
        estado: 'borrador',
        notas: '',
        detalles: [] as CotizacionDetalle[],
        condiciones:
            'Condiciones de pago: 50% al aprobar, 50% contra entrega.\nValidez de la oferta: 15 días.',
        iva_personalizado: 19,
        descuento_tipo: null as 'monto' | 'porcentaje' | null,
        descuento_monto: 0,
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (clienteFilter !== 'all') query.cliente_id = clienteFilter;
            if (estadoFilter !== 'all') query.estado = estadoFilter;

            router.get('/cotizaciones', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);

        return () => clearTimeout(timer);
    }, [searchTerm, clienteFilter, estadoFilter]);

    // Totals Calculation logic
    useEffect(() => {
        let nSubtotal = 0;
        data.detalles.forEach((d) => {
            const precio = Math.round(d.precio || 0);
            nSubtotal += Math.round((d.cantidad || 0) * precio);
        });

        let descuentoAplicado = 0;
        if (data.descuento_tipo === 'monto' && data.descuento_monto) {
            descuentoAplicado = Math.round(Number(data.descuento_monto));
        } else if (
            data.descuento_tipo === 'porcentaje' &&
            data.descuento_monto
        ) {
            descuentoAplicado = Math.round(
                nSubtotal * (Number(data.descuento_monto) / 100),
            );
        }

        const nNeto = Math.round(nSubtotal - descuentoAplicado);
        const porcIva =
            Number(data.iva_personalizado) >= 0
                ? Number(data.iva_personalizado)
                : 19;
        const nImpuesto = Math.round(nNeto * (porcIva / 100));
        const nTotal = nNeto + nImpuesto;

        setData((prev) => ({
            ...prev,
            subtotal: Math.round(nSubtotal),
            impuesto: nImpuesto,
            total: nTotal,
        }));
    }, [
        data.detalles,
        data.descuento_tipo,
        data.descuento_monto,
        data.iva_personalizado,
        setData,
    ]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editando) {
            put(`/cotizaciones/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                },
            });
        } else {
            post('/cotizaciones', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (cot: Cotizacion) => {
        setEditando(cot);
        setData({
            numero: cot.numero,
            cliente_id: String(cot.cliente_id),
            fecha: cot.fecha ? cot.fecha.substring(0, 10) : '',
            fecha_validez: cot.fecha_validez
                ? cot.fecha_validez.substring(0, 10)
                : '',
            subtotal: Number(cot.subtotal) || 0,
            impuesto: Number(cot.impuesto) || 0,
            total: Number(cot.total) || 0,
            estado: cot.estado,
            notas: cot.notas || '',
            detalles: cot.detalles || [],
            condiciones: cot.condiciones || '',
            iva_personalizado: cot.iva_personalizado ?? 19,
            descuento_tipo: cot.descuento_tipo || null,
            descuento_monto: Number(cot.descuento_monto) || 0,
        });
        setIsOpen(true);
    };

    const handleView = (cot: Cotizacion) => {
        setViendo(cot);
        setIsViewOpen(true);
    };

    const handleNew = () => {
        setEditando(null);
        reset();
        setData((prev) => ({
            ...prev,
            numero: `COT-${Date.now().toString().slice(-6)}`,
            detalles: [
                { producto_id: null, descripcion: '', cantidad: 1, precio: 0 },
            ],
        }));
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Está seguro de eliminar esta cotización?')) {
            destroy(`/cotizaciones/${id}`);
        }
    };

    const addDetalle = () => {
        setData('detalles', [
            ...data.detalles,
            { producto_id: null, descripcion: '', cantidad: 1, precio: 0 },
        ]);
    };

    const updateDetalle = (
        index: number,
        field: keyof CotizacionDetalle,
        value: any,
    ) => {
        const newDetalles = [...data.detalles];
        newDetalles[index] = { ...newDetalles[index], [field]: value };

        if (field === 'producto_id' && value) {
            const prod = productos.find((p) => p.id === Number(value));
            if (prod) {
                newDetalles[index].descripcion =
                    prod.nombre +
                    (prod.descripcion ? ` - ${prod.descripcion}` : '');
                newDetalles[index].precio = Number(prod.precio_venta) || 0;
            }
        }
        setData('detalles', newDetalles);
    };

    const removeDetalle = (index: number) => {
        setData(
            'detalles',
            data.detalles.filter((_, i) => i !== index),
        );
    };

    const getEstadoConfig = (val: string) => {
        return (
            ESTADOS.find((e) => e.value === val) || {
                label: val,
                color: 'bg-gray-500/10 text-gray-600 border-gray-200',
            }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Presupuestos y Cotizaciones" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header Section */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="mb-1 flex items-center gap-2">
                            <FileCheck className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Sales Management System
                            </span>
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Cotizaciones
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Cree y gestione presupuestos profesionales para sus
                            clientes
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <BulkActions
                            baseUrl="/cotizaciones"
                            filters={{
                                search: searchTerm,
                                cliente_id: clienteFilter,
                                estado: estadoFilter,
                            }}
                            modelName="Cotizaciones"
                        />

                        {canCreate && (
                            <Button
                                onClick={handleNew}
                                className="h-9 rounded-full bg-primary px-5 font-bold shadow-lg shadow-primary/20 transition-all hover:bg-primary/90"
                            >
                                <Plus className="mr-2 h-4 w-4" /> Nueva Cotización
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6">
                    <Card className="overflow-hidden border-none shadow-xl shadow-foreground/5">
                        <CardHeader className="bg-gradient-to-r from-muted/50 to-transparent pb-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <ShoppingCart className="h-5 w-5 text-primary" />
                                    <CardTitle>
                                        Historial de Presupuestos
                                    </CardTitle>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="flex items-center gap-1 rounded-lg border p-0.5">
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
                                    <div className="text-xs font-bold tracking-widest text-muted-foreground uppercase">
                                        {cotizaciones.meta?.total ||
                                            cotizaciones.data.length}{' '}
                                        Cotizaciones
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {/* Filters Bar */}
                            <div className="flex flex-col gap-4 border-b border-muted/30 bg-muted/20 p-4 md:flex-row md:items-center">
                                <div className="relative flex-1">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Buscar por número, cliente o notas..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="h-10 border-none bg-background/50 pl-10 pr-10 focus-visible:ring-primary/20"
                                    />

                                </div>
                                <div className="flex gap-2">
                                    <Select
                                        value={clienteFilter}
                                        onValueChange={setClienteFilter}
                                    >
                                        <SelectTrigger className="h-10 w-full border-none bg-background/50 sm:w-[200px]">
                                            <SelectValue placeholder="Cliente" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todos los clientes
                                            </SelectItem>
                                            {clientes.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={c.id.toString()}
                                                >
                                                    {c.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        value={estadoFilter}
                                        onValueChange={setEstadoFilter}
                                    >
                                        <SelectTrigger className="h-10 w-full border-none bg-background/50 sm:w-[160px]">
                                            <SelectValue placeholder="Estado" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todos los estados
                                            </SelectItem>
                                            {ESTADOS.map((e) => (
                                                <SelectItem
                                                    key={e.value}
                                                    value={e.value}
                                                >
                                                    {e.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="h-10 w-10 border-none bg-background/50"
                                        onClick={() => {
                                            setSearchTerm('');
                                            setClienteFilter('all');
                                            setEstadoFilter('all');
                                        }}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            {viewMode === 'table' ? (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                    <th className="px-6 py-4 text-left">
                                                        Número / Vence
                                                    </th>
                                                    <th className="px-6 py-4 text-left">
                                                        Cliente
                                                    </th>
                                                    <th className="px-6 py-4 text-right">
                                                        Total (CLP)
                                                    </th>
                                                    <th className="px-6 py-4 text-center">
                                                        Estado
                                                    </th>
                                                    <th className="px-6 py-4 text-right">
                                                        Acciones
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-muted/50">
                                                {cotizaciones.data.map((c) => (
                                                    <tr
                                                        key={c.id}
                                                        className="group transition-colors hover:bg-muted/30"
                                                    >
                                                        <td className="px-6 py-4">
                                                            <div>
                                                                <div className="text-sm font-bold tracking-tight text-foreground">
                                                                    {c.numero}
                                                                </div>
                                                                <div className="flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                    <Calendar className="h-2.5 w-2.5" />
                                                                    {formatDateCLP(
                                                                        c.fecha,
                                                                    )}
                                                                    {c.fecha_validez && (
                                                                        <span className="ml-1 text-orange-500">
                                                                            Vence:{' '}
                                                                            {formatDateCLP(
                                                                                c.fecha_validez,
                                                                            )}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex items-center gap-2">
                                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-[10px] font-black text-primary">
                                                                    {c.cliente?.nombre
                                                                        .charAt(0)
                                                                        .toUpperCase()}
                                                                </div>
                                                                <span className="text-xs font-bold text-foreground">
                                                                    {c.cliente
                                                                        ?.nombre ||
                                                                        'General'}
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <div className="text-sm font-black text-primary">
                                                                {formatCurrency(
                                                                    c.total,
                                                                )}
                                                            </div>
                                                            <div className="block text-[10px] text-muted-foreground">
                                                                Imp:{' '}
                                                                {formatCurrency(
                                                                    c.impuesto,
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-center">
                                                            <Badge
                                                                variant="outline"
                                                                className={`${getEstadoConfig(c.estado).color} rounded-full border px-2 py-0.5 text-[9px] font-black uppercase`}
                                                            >
                                                                {
                                                                    getEstadoConfig(
                                                                        c.estado,
                                                                    ).label
                                                                }
                                                            </Badge>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <div className="flex justify-end gap-1">
                                                                <a
                                                                    href={`/cotizaciones/${c.id}/pdf`}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 text-blue-600 hover:bg-blue-50"
                                                                    >
                                                                        <Printer className="h-4 w-4" />
                                                                    </Button>
                                                                </a>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8 text-muted-foreground hover:bg-muted"
                                                                    onClick={() =>
                                                                        handleView(c)
                                                                    }
                                                                >
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                                {canEdit && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 text-primary hover:bg-primary/10"
                                                                        onClick={() =>
                                                                            handleEdit(c)
                                                                        }
                                                                    >
                                                                        <Pencil className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                                {canDelete && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 text-destructive hover:bg-destructive/10"
                                                                        onClick={() =>
                                                                            handleDelete(
                                                                                c.id,
                                                                            )
                                                                        }
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

                                    <div className="border-t border-muted/50 p-4">
                                        <Pagination
                                            links={cotizaciones.links}
                                            meta={cotizaciones.meta}
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    {cotizaciones.data.length === 0 ? (
                                        <div className="flex flex-col items-center justify-center py-12 text-center">
                                            <ShoppingCart className="mb-4 h-12 w-12 text-muted-foreground/50" />
                                            <p className="text-muted-foreground">No hay cotizaciones</p>
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
                                            {cotizaciones.data.map((c) => (
                                                <div key={c.id} className="group rounded-xl border bg-card p-5 shadow-sm transition-all hover:shadow-md">
                                                    <div className="mb-3 flex items-start justify-between">
                                                        <div>
                                                            <p className="text-sm font-black tracking-tight text-foreground">{c.numero}</p>
                                                            <div className="flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                <Calendar className="h-2.5 w-2.5" />
                                                                {formatDateCLP(c.fecha)}
                                                                {c.fecha_validez && (
                                                                    <span className="ml-1 text-orange-500">Vence: {formatDateCLP(c.fecha_validez)}</span>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <Badge variant="outline" className={`${getEstadoConfig(c.estado).color} rounded-full border px-2 py-0.5 text-[9px] font-black uppercase`}>
                                                            {getEstadoConfig(c.estado).label}
                                                        </Badge>
                                                    </div>
                                                    <div className="mb-3 flex items-center gap-2">
                                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-[10px] font-black text-primary">
                                                            {c.cliente?.nombre.charAt(0).toUpperCase()}
                                                        </div>
                                                        <span className="text-xs font-bold text-foreground">{c.cliente?.nombre || 'General'}</span>
                                                    </div>
                                                    <div className="mb-4">
                                                        <p className="text-lg font-black text-primary">{formatCurrency(c.total)}</p>
                                                        <p className="text-[10px] text-muted-foreground">Imp: {formatCurrency(c.impuesto)}</p>
                                                    </div>
                                                    <div className="flex justify-end gap-1 border-t pt-3">
                                                        <a href={`/cotizaciones/${c.id}/pdf`} target="_blank" rel="noreferrer">
                                                            <Button variant="ghost" size="icon" className="h-8 w-8 text-blue-600 hover:bg-blue-50">
                                                                <Printer className="h-4 w-4" />
                                                            </Button>
                                                        </a>
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:bg-muted" onClick={() => handleView(c)}>
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {canEdit && (
                                                            <Button variant="ghost" size="icon" className="h-8 w-8 text-primary hover:bg-primary/10" onClick={() => handleEdit(c)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:bg-destructive/10" onClick={() => handleDelete(c.id)}>
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    <div className="border-t border-muted/50 p-4">
                                        <Pagination
                                            links={cotizaciones.links}
                                            meta={cotizaciones.meta}
                                        />
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Create/Edit dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-5xl">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-6 pb-2 text-left">
                        <div className="mb-1 flex items-center gap-2">
                            <Calculator className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Financial Document
                            </span>
                        </div>
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                            {editando
                                ? `Editar Cotización ${editando.numero}`
                                : 'Nueva Cotización de Venta'}
                        </DialogTitle>
                    </DialogHeader>

                    <form
                        onSubmit={handleSubmit}
                        className="max-h-[85vh] overflow-y-auto p-6 pt-2"
                    >
                        <div className="grid gap-6 py-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Número de Documento
                                    </Label>
                                    <Input
                                        value={data.numero}
                                        onChange={(e) =>
                                            setData('numero', e.target.value)
                                        }
                                        required
                                        className="h-10 border-none bg-muted/30 font-mono font-bold"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Fecha Emisión
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.fecha}
                                        onChange={(e) =>
                                            setData('fecha', e.target.value)
                                        }
                                        required
                                        className="h-10 border-none bg-muted/30 font-bold"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Vencimiento
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_validez}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_validez',
                                                e.target.value,
                                            )
                                        }
                                        className="h-10 border-none bg-muted/30 font-bold"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Estado
                                    </Label>
                                    <Select
                                        value={data.estado}
                                        onValueChange={(v) =>
                                            setData('estado', v)
                                        }
                                    >
                                        <SelectTrigger className="h-10 border-none bg-muted/30 font-bold">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {ESTADOS.map((e) => (
                                                <SelectItem
                                                    key={e.value}
                                                    value={e.value}
                                                >
                                                    {e.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    Cliente Asignado *
                                </Label>
                                <Select
                                    value={data.cliente_id.toString()}
                                    onValueChange={(v) =>
                                        setData('cliente_id', v)
                                    }
                                >
                                    <SelectTrigger className="h-11 border-none bg-muted/30 text-lg font-bold">
                                        <SelectValue placeholder="Seleccione un cliente..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {clientes.map((c) => (
                                            <SelectItem
                                                key={c.id}
                                                value={c.id.toString()}
                                            >
                                                {c.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Detalle de Productos */}
                            <div className="space-y-4 border-t pt-6">
                                <div className="flex items-center justify-between">
                                    <h3 className="flex items-center gap-2 text-xs font-black tracking-widest text-primary uppercase">
                                        <Package className="h-4 w-4" /> Líneas
                                        de Cotización
                                    </h3>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addDetalle}
                                        className="h-8 rounded-full border-primary/20 px-4 font-bold text-primary hover:bg-primary/5"
                                    >
                                        <Plus className="mr-2 h-3 w-3" />{' '}
                                        Agregar Item
                                    </Button>
                                </div>

                                <div className="space-y-3">
                                    {data.detalles.map((detalle, idx) => (
                                        <div
                                            key={idx}
                                            className="group relative grid grid-cols-12 gap-3 rounded-2xl border border-transparent bg-muted/20 p-4 transition-all hover:border-primary/20"
                                        >
                                            <div className="col-span-12 space-y-1 md:col-span-5">
                                                <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                                    Producto / Descripción
                                                </Label>
                                                <div className="flex gap-2">
                                                    <Select
                                                        value={
                                                            detalle.producto_id?.toString() ||
                                                            ''
                                                        }
                                                        onValueChange={(v) =>
                                                            updateDetalle(
                                                                idx,
                                                                'producto_id',
                                                                v,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="h-9 border-none bg-background/50 font-medium">
                                                            <SelectValue placeholder="Seleccionar producto..." />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">
                                                                Manual / Sin
                                                                producto
                                                            </SelectItem>
                                                            {productos.map(
                                                                (p) => (
                                                                    <SelectItem
                                                                        key={
                                                                            p.id
                                                                        }
                                                                        value={p.id.toString()}
                                                                    >
                                                                        {
                                                                            p.nombre
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <Input
                                                        value={
                                                            detalle.descripcion
                                                        }
                                                        onChange={(e) =>
                                                            updateDetalle(
                                                                idx,
                                                                'descripcion',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Descripción personalizada..."
                                                        className="h-9 border-none bg-background/50 font-medium"
                                                    />
                                                </div>
                                            </div>
                                            <div className="col-span-4 space-y-1 md:col-span-2">
                                                <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                                    Cant.
                                                </Label>
                                                <Input
                                                    type="number"
                                                    value={detalle.cantidad}
                                                    onChange={(e) =>
                                                        updateDetalle(
                                                            idx,
                                                            'cantidad',
                                                            parseFloat(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="h-9 border-none bg-background/50 font-bold"
                                                />
                                            </div>
                                            <div className="col-span-4 space-y-1 md:col-span-3">
                                                <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                                    Precio Unit.
                                                </Label>
                                                <div className="relative">
                                                    <DollarSign className="absolute top-1/2 left-2 h-3 w-3 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        type="number"
                                                        value={detalle.precio}
                                                        onChange={(e) =>
                                                            updateDetalle(
                                                                idx,
                                                                'precio',
                                                                parseFloat(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        className="h-9 border-none bg-background/50 pl-6 font-bold"
                                                    />
                                                </div>
                                            </div>
                                            <div className="col-span-3 flex items-end justify-end pb-1 md:col-span-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        removeDetalle(idx)
                                                    }
                                                    className="h-8 w-8 rounded-full text-destructive hover:bg-destructive/10"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Totales y Notas */}
                            <div className="grid grid-cols-1 gap-8 border-t pt-8 md:grid-cols-2">
                                <div className="space-y-6">
                                    <div className="space-y-2">
                                        <Label className="flex items-center gap-2 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                            <ClipboardList className="h-4 w-4" />{' '}
                                            Términos y Condiciones
                                        </Label>
                                        <textarea
                                            value={data.condiciones || ''}
                                            onChange={(e) =>
                                                setData(
                                                    'condiciones',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex min-h-[100px] w-full rounded-xl border-none bg-muted/30 px-4 py-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="flex items-center gap-2 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                            <AlertCircle className="h-4 w-4" />{' '}
                                            Notas Internas
                                        </Label>
                                        <textarea
                                            value={data.notas || ''}
                                            onChange={(e) =>
                                                setData('notas', e.target.value)
                                            }
                                            className="flex min-h-[80px] w-full rounded-xl border-none bg-muted/30 px-4 py-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-4 rounded-3xl border border-muted/50 bg-muted/10 p-6">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                                            Subtotal Neto
                                        </span>
                                        <span className="font-mono font-bold">
                                            {formatCurrency(data.subtotal)}
                                        </span>
                                    </div>

                                    <div className="grid grid-cols-2 items-center gap-4">
                                        <div className="flex items-center gap-2">
                                            <span className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                                                Descuento
                                            </span>
                                            <Select
                                                value={
                                                    data.descuento_tipo ||
                                                    'none'
                                                }
                                                onValueChange={(v) =>
                                                    setData(
                                                        'descuento_tipo',
                                                        v === 'none'
                                                            ? null
                                                            : (v as any),
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="h-7 w-24 border-none bg-background py-0 text-[10px] font-black">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">
                                                        Sin Desc.
                                                    </SelectItem>
                                                    <SelectItem value="monto">
                                                        $ CLP
                                                    </SelectItem>
                                                    <SelectItem value="porcentaje">
                                                        % Porc.
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <Input
                                            type="number"
                                            value={data.descuento_monto || 0}
                                            disabled={!data.descuento_tipo}
                                            onChange={(e) =>
                                                setData(
                                                    'descuento_monto',
                                                    parseFloat(e.target.value),
                                                )
                                            }
                                            className="h-8 border-none bg-background text-right text-xs font-bold"
                                        />
                                    </div>

                                    <div className="flex items-center justify-between border-t border-dashed pt-2 text-sm">
                                        <div className="flex items-center gap-2">
                                            <span className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                                                IVA
                                            </span>
                                            <Input
                                                type="number"
                                                value={
                                                    data.iva_personalizado || 0
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        'iva_personalizado',
                                                        parseFloat(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                className="h-7 w-16 border-none bg-background py-0 text-center text-[10px] font-black"
                                            />
                                            <span className="text-[10px] font-black">
                                                %
                                            </span>
                                        </div>
                                        <span className="font-mono font-bold text-muted-foreground">
                                            {formatCurrency(data.impuesto)}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between border-t-2 border-primary/20 pt-4">
                                        <span className="text-xs font-black tracking-widest text-primary uppercase">
                                            Total Final
                                        </span>
                                        <span className="font-mono text-2xl font-black text-primary">
                                            {formatCurrency(data.total)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="gap-2 border-t pt-6">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full px-8 font-bold"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-full bg-primary px-12 font-bold shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <Check className="mr-2 h-4 w-4" />{' '}
                                {editando
                                    ? 'Guardar Cambios'
                                    : 'Confirmar y Crear Cotización'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* View dialog */}
            <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-4xl">
                    {viendo && (
                        <>
                            <DialogHeader className="relative overflow-visible bg-slate-950 p-10 text-left">
                                <div className="absolute top-0 right-0 p-10 opacity-10">
                                    <ShoppingCart className="h-40 w-40 rotate-12 text-white" />
                                </div>
                                <div className="relative z-10">
                                    <div className="mb-4 flex items-center gap-3">
                                        <Badge className="border-none bg-primary px-4 py-1 text-[10px] font-black tracking-widest text-white uppercase">
                                            PRESUPUESTO {viendo.numero}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            className={`${getEstadoConfig(viendo.estado).color} border-none px-4 py-1 text-[10px] font-black uppercase`}
                                        >
                                            {
                                                getEstadoConfig(viendo.estado)
                                                    .label
                                            }
                                        </Badge>
                                    </div>
                                    <DialogTitle className="mb-2 text-4xl font-black tracking-tight text-white">
                                        Resumen de Cotización
                                    </DialogTitle>
                                    <div className="flex items-center gap-6 text-xs font-bold tracking-widest text-slate-400 uppercase">
                                        <span className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4" />{' '}
                                            Emitida:{' '}
                                            {formatDateCLP(viendo.fecha)}
                                        </span>
                                        {viendo.fecha_validez && (
                                            <span className="flex items-center gap-2 text-orange-400">
                                                <AlertCircle className="h-4 w-4" />{' '}
                                                Vence:{' '}
                                                {formatDateCLP(
                                                    viendo.fecha_validez,
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </DialogHeader>

                            <div className="space-y-10 p-10">
                                <div className="grid grid-cols-1 gap-10 md:grid-cols-2">
                                    <div className="space-y-4">
                                        <h3 className="text-[10px] font-black tracking-[0.2em] text-primary uppercase">
                                            Información del Cliente
                                        </h3>
                                        <div className="space-y-2 rounded-3xl border border-muted/50 bg-muted/20 p-6">
                                            <p className="text-xl font-black text-foreground">
                                                {viendo.cliente?.nombre ||
                                                    'General'}
                                            </p>
                                            <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground italic">
                                                <User className="h-3.5 w-3.5" />{' '}
                                                Registro Maestro de Clientes
                                            </div>
                                        </div>
                                    </div>
                                    <div className="space-y-4 text-right">
                                        <h3 className="text-[10px] font-black tracking-[0.2em] text-primary uppercase">
                                            Consolidado Financiero
                                        </h3>
                                        <div className="space-y-1 rounded-3xl border border-primary/10 bg-primary/5 p-6">
                                            <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                                Monto Total a Facturar
                                            </p>
                                            <p className="font-mono text-3xl font-black text-primary">
                                                {formatCurrency(
                                                    viendo.total,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <h3 className="text-[10px] font-black tracking-[0.2em] text-primary uppercase">
                                        Detalle de Partidas
                                    </h3>
                                    <div className="overflow-x-auto rounded-3xl border border-muted/50 shadow-sm">
                                        <table className="w-full text-sm">
                                            <thead className="border-b border-muted/50 bg-muted/50 text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                                <tr>
                                                    <th className="px-6 py-4 text-left">
                                                        Descripción del Item
                                                    </th>
                                                    <th className="px-6 py-4 text-center">
                                                        Cant.
                                                    </th>
                                                    <th className="px-6 py-4 text-right">
                                                        Unitario
                                                    </th>
                                                    <th className="px-6 py-4 text-right">
                                                        Subtotal
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-muted/30">
                                                {viendo.detalles?.map(
                                                    (d, i) => (
                                                        <tr
                                                            key={i}
                                                            className="transition-colors hover:bg-muted/10"
                                                        >
                                                            <td className="px-6 py-4 font-bold text-foreground">
                                                                {d.descripcion}
                                                            </td>
                                                            <td className="px-6 py-4 text-center font-mono font-bold text-muted-foreground">
                                                                {d.cantidad}
                                                            </td>
                                                            <td className="px-6 py-4 text-right font-mono text-muted-foreground">
                                                                {formatCurrency(
                                                                    d.precio,
                                                                )}
                                                            </td>
                                                            <td className="px-6 py-4 text-right font-mono font-bold text-primary">
                                                                {formatCurrency(
                                                                    d.cantidad *
                                                                        d.precio,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {(viendo.condiciones || viendo.notas) && (
                                    <div className="grid grid-cols-1 gap-10 border-t pt-10 md:grid-cols-2">
                                        {viendo.condiciones && (
                                            <div className="space-y-3">
                                                <h3 className="flex items-center gap-2 text-[10px] font-black tracking-[0.2em] text-primary uppercase">
                                                    <FileCheck className="h-4 w-4" />{' '}
                                                    Condiciones de Venta
                                                </h3>
                                                <p className="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 text-xs leading-relaxed font-medium text-slate-600 italic">
                                                    "{viendo.condiciones}"
                                                </p>
                                            </div>
                                        )}
                                        {viendo.notas && (
                                            <div className="space-y-3">
                                                <h3 className="flex items-center gap-2 text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase">
                                                    <MoreHorizontal className="h-4 w-4" />{' '}
                                                    Notas Adicionales
                                                </h3>
                                                <p className="text-xs leading-relaxed font-medium text-slate-500">
                                                    {viendo.notas}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            <DialogFooter className="items-center justify-between border-t bg-muted/10 p-10">
                                <p className="flex items-center gap-2 text-[10px] font-bold text-muted-foreground italic">
                                    <AlertCircle className="h-3 w-3" /> Validéz
                                    legal según normativa vigente.
                                </p>
                                <div className="flex gap-3">
                                    <Button
                                        variant="outline"
                                        onClick={() => setIsViewOpen(false)}
                                        className="h-11 rounded-full px-10 font-black"
                                    >
                                        Cerrar
                                    </Button>
                                    <a
                                        href={`/cotizaciones/${viendo.id}/pdf`}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Button className="h-11 rounded-full bg-primary px-10 font-black">
                                            <Printer className="mr-2 h-4 w-4" />{' '}
                                            Imprimir Documento
                                        </Button>
                                    </a>
                                </div>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
