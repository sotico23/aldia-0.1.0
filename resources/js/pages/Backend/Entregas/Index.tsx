import { Head, useForm } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import {
    Pencil,
    Trash2,
    Search,
    X,
    Eye,
    Truck,
    Calendar,
    MapPin,
    User,
    Package,
    Info,
    Scale,
    Droplets,
    Check,
    Navigation,
    Users,
    LayoutGrid,
    List
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { useCountry } from '@/hooks/use-country';
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
    DialogTitle,
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
import type { BreadcrumbItem } from '@/types';

interface EntregaItem {
    id: number;
    producto: { nombre: string; unidad_medida: string };
    cantidad_pedida: number;
    cantidad_entregada: number;
    subtotal_metrica: number;
    unidades_totales: number;
    unidad_medida?: string;
}

interface Entrega {
    id: number;
    venta_id: number | null;
    vehiculo_id: number | null;
    conductor_id: number | null;
    cliente: string | null;
    direccion: string | null;
    fecha_entrega: string | null;
    estado: string;
    notas: string | null;
    productos_json: string | null;
    items?: EntregaItem[];
    conductor?: { nombre: string };
    vehiculo?: { placa: string; marca: string };
    created_at?: string;
}

interface Vehiculo {
    id: number;
    marca: string;
    modelo: string;
    placa: string;
}

interface Conductor {
    id: number;
    nombre: string;
}

interface Cliente {
    id: number;
    nombre: string;
    telefono: string | null;
    direccion: string | null;
    ciudad: string | null;
    comuna: string | null;
    region: string | null;
}

interface DetalleVenta {
    id: number;
    producto_id: number | null;
    producto?: { nombre: string };
    cantidad: number;
    precio_unitario: number;
}

interface Venta {
    id: number;
    numero: string;
    numero_factura?: string;
    cliente_id: number | null;
    cliente?: {
        nombre: string;
        telefono?: string;
        direccion?: string;
        comuna?: string;
        ciudad?: string;
        region?: string;
        fecha?: string;
    };
    fecha?: string;
    estado: string;
    detalle_ventas: DetalleVenta[];
}

interface GrupoTrabajo {
    id: number;
    nombre: string;
    conductores?: { id: number; nombre: string }[];
    miembros?: { id: number; name: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Entregas', href: '/entregas' },
];

export default function Index({
    entregas,
    vehiculos,
    conductores,
    clientes,
    ventas,
    stats,
    filters,
    grupos_trabajo = [],
}: {
    entregas: { data: Entrega[]; links: any[]; meta?: any; total: number };
    vehiculos: Vehiculo[];
    conductores: Conductor[];
    clientes: Cliente[];
    ventas: Venta[];
    stats: { kg: number; litros: number; unidades: number };
    filters: any;
    grupos_trabajo?: GrupoTrabajo[];
}) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('flota.entregas.create');
    const canEdit = hasPermission('flota.entregas.edit');
    const canDelete = hasPermission('flota.entregas.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [isVerOpen, setIsVerOpen] = useState(false);
    const [editando, setEditando] = useState<Entrega | null>(null);
    const [entregaSeleccionada, setEntregaSeleccionada] =
        useState<Entrega | null>(null);
    const [productosSeleccionados, setProductosSeleccionados] = useState<{
        [key: number]: number;
    }>({});
    const {
        data,
        setData,
        delete: destroy,
        reset,
    } = useForm({
        venta_id: '',
        grupo_trabajo_id: '',
        vehiculo_id: '',
        conductor_id: '',
        cliente: '',
        direccion: '',
        fecha_entrega: '',
        estado: 'pendiente',
        notas: '',
        productos_json: '',
    });

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const entregasFiltradas = useMemo(() => {
        return (entregas.data || []).filter((e: Entrega) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !(e.cliente || '').toLowerCase().includes(busca) &&
                    !(e.direccion || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && e.estado !== filtros.estado) return false;

            return true;
        });
    }, [entregas, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const getSelectedVenta = () => {
        return ventas.find((v) => v.id.toString() === data.venta_id);
    };

    const handleVentaChange = (ventaId: string) => {
        const venta = ventas.find((v) => v.id.toString() === ventaId);

        if (venta) {
            const fullAddress = [
                venta.cliente?.direccion,
                venta.cliente?.comuna,
                venta.cliente?.ciudad,
                venta.cliente?.region,
            ]
                .filter(Boolean)
                .join(', ');

            setData((prev) => ({
                ...prev,
                venta_id: ventaId,
                cliente: venta.cliente?.nombre || '',
                direccion: fullAddress || '',
                fecha_entrega: venta.fecha
                    ? venta.fecha.split('T')[0]
                    : prev.fecha_entrega,
            }));

            // Set productos seleccionados from venta detalle_ventas
            const productos: { [key: number]: number } = {};
            venta.detalle_ventas.forEach((item) => {
                productos[item.producto_id || item.id] = item.cantidad;
            });
            setProductosSeleccionados(productos);
        } else {
            setData('venta_id', ventaId);
            setProductosSeleccionados({});
        }
    };

    const handleGrupoChange = (val: string) => {
        setData('grupo_trabajo_id', val);
        const grupo = grupos_trabajo.find((g) => g.id.toString() === val);
        if (grupo) {
            if (grupo.conductores && grupo.conductores.length > 0) {
                setData('conductor_id', grupo.conductores[0].id.toString());
            }
            const miembros = grupo.miembros?.map((m) => m.name).join(', ') || '';
            if (miembros) {
                setData('notas', `Grupo: ${grupo.nombre} — Equipo: ${miembros}`);
            }
        }
    };

    const handleCantidadChange = (itemId: number, cantidad: number) => {
        setProductosSeleccionados((prev) => ({
            ...prev,
            [itemId]: cantidad,
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Build productos_json from selected items
        const venta = getSelectedVenta();
        let productosJson = '';
        if (venta && Object.keys(productosSeleccionados).length > 0) {
            const items = venta.detalle_ventas.map((item) => ({
                producto_id: item.producto_id || item.id,
                nombre_producto: item.producto?.nombre || 'Producto',
                cantidad:
                    productosSeleccionados[item.producto_id || item.id] ||
                    item.cantidad,
                precio_unitario: item.precio_unitario,
            }));
            productosJson = JSON.stringify(items);
        }

        // Clean data for submission
        const cleanData = {
            ...data,
            venta_id: data.venta_id === '' ? null : data.venta_id,
            vehiculo_id: data.vehiculo_id === '' ? null : data.vehiculo_id,
            conductor_id: data.conductor_id === '' ? null : data.conductor_id,
            grupo_trabajo_id: data.grupo_trabajo_id === '' ? null : data.grupo_trabajo_id,
            productos_json: productosJson,
        };

        if (editando) {
            import('@inertiajs/react').then(({ router }) => {
                router.put(`/entregas/${editando.id}`, cleanData, {
                    onSuccess: () => {
                        setIsOpen(false);
                        setEditando(null);
                        setProductosSeleccionados({});
                        reset();
                    },
                });
            });
        } else {
            import('@inertiajs/react').then(({ router }) => {
                router.post('/entregas', cleanData, {
                    onSuccess: () => {
                        setIsOpen(false);
                        setProductosSeleccionados({});
                        reset();
                    },
                });
            });
        }
    };

    const handleEdit = (e: Entrega) => {
        setEditando(e);

        // Format date string (YYYY-MM-DDTHH:mm:ss.sssZ -> YYYY-MM-DD)
        const datePart = e.fecha_entrega ? e.fecha_entrega.split('T')[0] : '';

        setData({
            venta_id: e.venta_id?.toString() || '',
            grupo_trabajo_id: (e as any).grupo_trabajo_id?.toString() || '',
            vehiculo_id: e.vehiculo_id?.toString() || '',
            conductor_id: e.conductor_id?.toString() || '',
            cliente: e.cliente || '',
            direccion: e.direccion || '',
            fecha_entrega: datePart,
            estado: e.estado,
            notas: e.notas || '',
            productos_json: e.productos_json || '',
        });

        // Load productos from entrega if exists
        if (e.productos_json) {
            try {
                const items = JSON.parse(e.productos_json);
                const productos: { [key: number]: number } = {};
                items.forEach((item: any) => {
                    productos[item.producto_id] = item.cantidad;
                });
                setProductosSeleccionados(productos);
            } catch {
                setProductosSeleccionados({});
            }
        } else {
            setProductosSeleccionados({});
        }
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setData({
            venta_id: '',
            grupo_trabajo_id: '',
            vehiculo_id: '',
            conductor_id: '',
            cliente: '',
            direccion: '',
            fecha_entrega: '',
            estado: 'pendiente',
            notas: '',
            productos_json: '',
        });
        setProductosSeleccionados({});
        setEditando(null);
        setIsOpen(true);
    };

    const handleVer = (e: Entrega) => {
        setEntregaSeleccionada(e);
        setIsVerOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/entregas/${id}`);
    };

    const getEstadoBadge = (e: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            en_ruta: 'bg-blue-500',
            entregado: 'bg-green-500',
            cancelado: 'bg-red-500',
        };
        return <Badge className={colores[e] || 'bg-gray-500'}>{e}</Badge>;
    };

    return (
        <>
            <Head title="Entregas" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-black tracking-tight text-primary uppercase">
                                Entregas
                            </h1>
                            <p className="text-sm font-medium text-muted-foreground">
                                Monitoreo automático de métricas por despacho
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <BulkActions
                                baseUrl="/entregas"
                                modelName="Entregas"
                                filters={filters}
                            />
                            {canCreate && (
                                <Button
                                    onClick={handleNew}
                                    className="h-11 rounded-xl px-6 shadow-lg shadow-primary/20 transition-all hover:scale-105 active:scale-95"
                                >
                                    <Truck className="mr-2 h-5 w-5" /> Nuevo Despacho
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <Card className="border-none bg-gradient-to-br from-blue-500/10 to-blue-500/5 shadow-none ring-1 ring-blue-500/20">
                            <CardContent className="p-6">
                                <div className="flex items-center gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-500 text-white shadow-lg shadow-blue-500/20">
                                        <Scale className="h-6 w-6" />
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-blue-600/60 uppercase">
                                            Total Kilos ⚖️
                                        </p>
                                        <h3 className="text-2xl font-black text-blue-700">
                                            {stats.kg.toLocaleString()} kg
                                        </h3>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="border-none bg-gradient-to-br from-cyan-500/10 to-cyan-500/5 shadow-none ring-1 ring-cyan-500/20">
                            <CardContent className="p-6">
                                <div className="flex items-center gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-500 text-white shadow-lg shadow-cyan-500/20">
                                        <Droplets className="h-6 w-6" />
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-cyan-600/60 uppercase">
                                            Total Litros 💧
                                        </p>
                                        <h3 className="text-2xl font-black text-cyan-700">
                                            {stats.litros.toLocaleString()} L
                                        </h3>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="border-none bg-gradient-to-br from-orange-500/10 to-orange-500/5 shadow-none ring-1 ring-orange-500/20">
                            <CardContent className="p-6">
                                <div className="flex items-center gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-500 text-white shadow-lg shadow-orange-500/20">
                                        <Package className="h-6 w-6" />
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-orange-600/60 uppercase">
                                            Total Unidades 📦
                                        </p>
                                        <h3 className="text-2xl font-black text-orange-700">
                                            {stats.unidades.toLocaleString()}{' '}
                                            unid
                                        </h3>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Entregas</CardTitle>
                                    <CardDescription>
                                        {entregasFiltradas.length} entregas encontradas
                                    </CardDescription>
                                </div>
                                <div className="flex gap-1 rounded-lg border p-0.5">
                                    <button type="button" onClick={() => setViewMode('table')} className={`rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors ${viewMode === 'table' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tabla"><List className="h-4 w-4" /></button>
                                    <button type="button" onClick={() => setViewMode('cards')} className={`rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors ${viewMode === 'cards' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tarjetas"><LayoutGrid className="h-4 w-4" /></button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar por cliente o dirección..."
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
                                <Select
                                    value={filters?.conductor_id || 'all'}
                                    onValueChange={(val) => {
                                        router.get(
                                            '/entregas',
                                            {
                                                ...filters,
                                                conductor_id:
                                                    val === 'all' ? '' : val,
                                            },
                                            { preserveState: true },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="h-9 w-full bg-background sm:w-[180px]">
                                        <SelectValue placeholder="Conductor" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos los Choferes
                                        </SelectItem>
                                        {conductores.map((c) => (
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
                                    value={filtros.estado}
                                    onValueChange={(val) =>
                                        setFiltros({
                                            ...filtros,
                                            estado: val === 'all' ? '' : val,
                                        })
                                    }
                                >
                                    <SelectTrigger className="h-9 w-full bg-background sm:w-[180px]">
                                        <SelectValue placeholder="Estado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos
                                        </SelectItem>
                                        {[
                                            'pendiente',
                                            'en_ruta',
                                            'entregado',
                                            'cancelado',
                                        ].map((e) => (
                                            <SelectItem key={e} value={e}>
                                                {e
                                                    .toUpperCase()
                                                    .replace('_', ' ')}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
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
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs sm:text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-2 text-left font-medium">Pedido / Cliente</th>
                                                <th className="py-2 text-left font-medium">Dirección</th>
                                                <th className="py-2 text-left font-medium">Fecha</th>
                                                <th className="py-2 text-center font-medium">Estado</th>
                                                <th className="py-2 text-right font-medium">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {entregasFiltradas.map((e) => (
                                                <tr key={e.id} className="border-b transition-colors hover:bg-muted/30">
                                                    <td className="py-2">
                                                        <div className="font-bold">#{e.venta_id || '-'}</div>
                                                        <div className="text-[10px] text-muted-foreground uppercase">{e.cliente || '-'}</div>
                                                    </td>
                                                    <td className="py-2"><div className="line-clamp-1 text-[10px]">{e.direccion || '-'}</div></td>
                                                    <td className="py-2"><div className="text-[10px]">{e.fecha_entrega ? e.fecha_entrega.split('T')[0] : '-'}</div></td>
                                                    <td className="py-2 text-center">{getEstadoBadge(e.estado)}</td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700" onClick={() => handleVer(e)} title="Ver Detalles"><Eye className="h-4 w-4" /></Button>
                                                            {canEdit && <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(e)} title="Editar"><Pencil className="h-4 w-4" /></Button>}
                                                            {canDelete && <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(e.id)}><Trash2 className="h-4 w-4" /></Button>}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                            {entregasFiltradas.length === 0 && (
                                                <tr><td colSpan={5} className="py-8 text-center text-muted-foreground">No se encontraron entregas con los filtros aplicados</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {entregasFiltradas.map((e) => (
                                        <Card key={e.id} className="overflow-hidden border-muted/80 transition-all hover:shadow-md">
                                            <CardHeader className="pb-2">
                                                <div className="flex items-start justify-between">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                        <Truck className="h-5 w-5 text-primary" />
                                                    </div>
                                                    {getEstadoBadge(e.estado)}
                                                </div>
                                                <div className="mt-2">
                                                    <span className="font-bold">#{e.venta_id || '-'}</span>
                                                    <p className="text-sm text-muted-foreground">{e.cliente || 'Sin cliente'}</p>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="space-y-2 text-sm pb-3">
                                                <div className="flex items-center gap-2 text-muted-foreground">
                                                    <MapPin className="h-3.5 w-3.5 shrink-0" />
                                                    <span className="text-xs line-clamp-1">{e.direccion || 'Sin dirección'}</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-muted-foreground">
                                                    <Calendar className="h-3.5 w-3.5 shrink-0" />
                                                    <span className="text-xs">{e.fecha_entrega ? e.fecha_entrega.split('T')[0] : 'Sin fecha'}</span>
                                                </div>
                                            </CardContent>
                                            <div className="border-t bg-muted/20 p-3 flex justify-end gap-1">
                                                <Button variant="outline" size="sm" className="h-8 w-8 p-0" onClick={() => handleVer(e)} title="Ver"><Eye className="h-4 w-4" /></Button>
                                                {canEdit && <Button variant="outline" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(e)} title="Editar"><Pencil className="h-4 w-4" /></Button>}
                                                {canDelete && <Button variant="outline" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(e.id)}><Trash2 className="h-4 w-4" /></Button>}
                                            </div>
                                        </Card>
                                    ))}
                                    {entregasFiltradas.length === 0 && (
                                        <div className="col-span-full py-8 text-center text-muted-foreground">No se encontraron entregas con los filtros aplicados</div>
                                    )}
                                </div>
                            )}
                            <Pagination
                                links={entregas.links}
                                meta={entregas.meta}
                            />
                        </CardContent>
                    </Card>
                </div>

            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none bg-background p-0 shadow-2xl md:max-w-2xl">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-6 pb-2">
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                            {editando
                                ? 'Modificar Registro'
                                : 'Nueva Planificación'}{' '}
                            de Entrega
                        </DialogTitle>
                    </DialogHeader>
                    <div className="max-h-[calc(90vh-140px)] overflow-y-auto p-6 pt-0">
                        <form onSubmit={handleSubmit}>
                            <div className="grid gap-6 py-4">
                                <div className="space-y-2">
                                    <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                        Grupo de Trabajo
                                    </Label>
                                    <Select
                                        value={data.grupo_trabajo_id || ''}
                                        onValueChange={handleGrupoChange}
                                    >
                                        <SelectTrigger className="h-11 border-none bg-muted/30 focus-visible:ring-primary/20">
                                            <div className="flex items-center gap-2">
                                                <Users className="h-4 w-4 text-muted-foreground" />
                                                <SelectValue placeholder="Seleccionar Grupo de Trabajo" />
                                            </div>
                                        </SelectTrigger>
                                        <SelectContent>
                                            {grupos_trabajo.map((g) => (
                                                <SelectItem
                                                    key={g.id}
                                                    value={g.id.toString()}
                                                >
                                                    {g.nombre}
                                                    {g.conductores && g.conductores.length > 0
                                                        ? ` — ${g.conductores[0].nombre}`
                                                        : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Seleccionar Pedido
                                        </Label>
                                        <Select
                                            value={data.venta_id}
                                            onValueChange={handleVentaChange}
                                        >
                                            <SelectTrigger className="h-11 border-none bg-muted/30 focus-visible:ring-primary/20">
                                                <div className="flex items-center gap-2">
                                                    <Package className="h-4 w-4 text-muted-foreground" />
                                                    <SelectValue placeholder="Seleccionar Pedido" />
                                                </div>
                                            </SelectTrigger>
                                            <SelectContent>
                                                {ventas.map((v) => (
                                                    <SelectItem
                                                        key={v.id}
                                                        value={v.id.toString()}
                                                    >
                                                        {v.numero} -{' '}
                                                        {v.cliente?.nombre ||
                                                            'Sin cliente'}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Fecha Estimada
                                        </Label>
                                        <div className="relative">
                                            <Calendar className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                type="date"
                                                value={data.fecha_entrega}
                                                onChange={(e) =>
                                                    setData(
                                                        'fecha_entrega',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-11 border-none bg-muted/30 pl-10 font-bold focus-visible:ring-primary/20"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Unidad de Transporte
                                        </Label>
                                        <Select
                                            value={data.vehiculo_id || ''}
                                            onValueChange={(val) =>
                                                setData('vehiculo_id', val)
                                            }
                                        >
                                            <SelectTrigger className="h-11 border-none bg-muted/30 focus-visible:ring-primary/20">
                                                <div className="flex items-center gap-2">
                                                    <Truck className="h-4 w-4 text-muted-foreground" />
                                                    <SelectValue placeholder="Asignar Vehículo" />
                                                </div>
                                            </SelectTrigger>
                                            <SelectContent>
                                                {vehiculos.map((v) => (
                                                    <SelectItem
                                                        key={v.id}
                                                        value={v.id.toString()}
                                                    >
                                                        {v.placa} ({v.marca}{' '}
                                                        {v.modelo})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Conductor Responsable
                                        </Label>
                                        <Select
                                            value={data.conductor_id || ''}
                                            onValueChange={(val) =>
                                                setData('conductor_id', val)
                                            }
                                        >
                                            <SelectTrigger className="h-11 border-none bg-muted/30 focus-visible:ring-primary/20">
                                                <div className="flex items-center gap-2">
                                                    <User className="h-4 w-4 text-muted-foreground" />
                                                    <SelectValue placeholder="Asignar Conductor" />
                                                </div>
                                            </SelectTrigger>
                                            <SelectContent>
                                                {conductores.map((c) => (
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
                                </div>

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Destinatario / Cliente
                                        </Label>
                                        <Select
                                            value={data.cliente}
                                            onValueChange={(val) => {
                                                const selected = clientes.find(
                                                    (c) => c.nombre === val,
                                                );
                                                setData('cliente', val);
                                                if (selected) {
                                                    const fullAddress = [
                                                        selected.direccion,
                                                        selected.comuna,
                                                        selected.ciudad,
                                                        selected.region,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(', ');
                                                    setData(
                                                        'direccion',
                                                        fullAddress || '',
                                                    );
                                                }
                                            }}
                                        >
                                            <SelectTrigger className="h-11 border-none bg-muted/30 font-bold italic focus-visible:ring-primary/20">
                                                <SelectValue placeholder="Seleccionar Cliente" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {clientes.map((c) => (
                                                    <SelectItem
                                                        key={c.id}
                                                        value={c.nombre}
                                                    >
                                                        {c.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Etapa Operativa
                                        </Label>
                                        <Select
                                            value={data.estado}
                                            onValueChange={(val) =>
                                                setData('estado', val)
                                            }
                                        >
                                            <SelectTrigger className="h-11 border-none bg-muted/30 focus-visible:ring-primary/20">
                                                <SelectValue placeholder="Estado inicial" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {[
                                                    'pendiente',
                                                    'en_ruta',
                                                    'entregado',
                                                    'cancelado',
                                                ].map((e) => (
                                                    <SelectItem
                                                        key={e}
                                                        value={e}
                                                    >
                                                        {e
                                                            .toUpperCase()
                                                            .replace('_', ' ')}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                        Punto de Destino / Dirección
                                    </Label>
                                    <div className="relative">
                                        <MapPin className="absolute top-3 left-3 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            value={data.direccion}
                                            onChange={(e) =>
                                                setData(
                                                    'direccion',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Calle, Número, Ciudad..."
                                            className="h-11 border-none bg-muted/30 pl-10 focus-visible:ring-primary/20"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                        Comentarios Adicionales
                                    </Label>
                                    <Input
                                        value={data.notas}
                                        onChange={(e) =>
                                            setData('notas', e.target.value)
                                        }
                                        placeholder="Instrucciones para la entrega..."
                                        className="h-11 border-none bg-muted/30 focus-visible:ring-primary/20"
                                    />
                                </div>

                                {/* Productos Section */}
                                {getSelectedVenta()?.detalle_ventas &&
                                    getSelectedVenta()!.detalle_ventas.length >
                                        0 && (
                                        <div className="space-y-2">
                                            <Label className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                                Productos a Entregar
                                            </Label>
                                            <div className="space-y-3 rounded-xl border border-border/50 bg-muted/30 p-4">
                                                {getSelectedVenta()?.detalle_ventas.map(
                                                    (item) => (
                                                        <div
                                                            key={item.id}
                                                            className="flex items-center justify-between"
                                                        >
                                                            <div className="flex-1">
                                                                <p className="text-sm font-bold">
                                                                    {
                                                                        item
                                                                            .producto
                                                                            ?.nombre
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    $
                                                                    {(
                                                                        item.precio_unitario ||
                                                                        0
                                                                    ).toLocaleString(
                                                                        currency.locale,
                                                                    )}{' '}
                                                                    x{' '}
                                                                    {
                                                                        item.cantidad
                                                                    }{' '}
                                                                    = $
                                                                    {(
                                                                        item.precio_unitario *
                                                                        item.cantidad
                                                                    ).toLocaleString(
                                                                        currency.locale,
                                                                    )}
                                                                </p>
                                                            </div>
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                max={
                                                                    item.cantidad
                                                                }
                                                                value={
                                                                    productosSeleccionados[
                                                                        item.producto_id ||
                                                                            item.id
                                                                    ] ||
                                                                    item.cantidad
                                                                }
                                                                onChange={(e) =>
                                                                    handleCantidadChange(
                                                                        item.producto_id ||
                                                                            item.id,
                                                                        parseInt(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ) || 0,
                                                                    )
                                                                }
                                                                className="h-8 w-20 text-center"
                                                            />
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}
                            </div>
                            <DialogFooter className="gap-2 border-t pt-6 sm:gap-0">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setIsOpen(false)}
                                    className="w-full font-bold sm:w-auto"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    className="w-full rounded-full bg-primary px-12 font-black shadow-lg shadow-primary/20 transition-all hover:bg-primary/90 active:scale-95 sm:w-auto"
                                >
                                    {editando
                                        ? 'Guardar Cambios'
                                        : 'Confirmar Entrega'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </div>
                </DialogContent>
            </Dialog>

            {/* View Modal */}
            <Dialog open={isVerOpen} onOpenChange={setIsVerOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto rounded-3xl border-none bg-background p-0 shadow-2xl md:max-w-3xl">
                    {entregaSeleccionada && (
                        <div className="flex flex-col">
                            <div className="relative shrink-0">
                                <div className="pointer-events-none absolute top-0 left-0 h-32 w-full bg-gradient-to-br from-primary/20 via-primary/5 to-transparent" />
                                <DialogHeader className="relative z-10 p-6 pb-4 md:p-8">
                                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-16 w-16 items-center justify-center rounded-2xl border border-primary/10 bg-white text-primary shadow-xl md:h-20 md:w-20">
                                                <Truck className="h-8 w-8 md:h-10 md:w-10" />
                                            </div>
                                            <div>
                                                <div className="mb-1 flex flex-wrap items-center gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className="border-primary/30 bg-white px-2 py-0.5 text-xs font-black text-primary md:px-3 md:py-1"
                                                    >
                                                        PEDIDO #
                                                        {
                                                            entregaSeleccionada.venta_id
                                                        }
                                                    </Badge>
                                                    {getEstadoBadge(
                                                        entregaSeleccionada.estado,
                                                    )}
                                                </div>
                                                <DialogTitle className="text-2xl font-black tracking-tight text-foreground md:text-3xl lg:text-4xl">
                                                    Detalles de Entrega
                                                </DialogTitle>
                                            </div>
                                        </div>
                                    </div>
                                </DialogHeader>
                            </div>

                            <div className="grid grid-cols-1 gap-6 overflow-y-auto px-6 pb-6 md:grid-cols-3 md:gap-8 md:px-8">
                                <div className="space-y-6 md:col-span-2">
                                    <section>
                                        <h3 className="mb-3 flex items-center gap-2 text-xs font-black tracking-widest text-primary/70 uppercase md:text-[10px]">
                                            <MapPin className="h-3 w-3" />{' '}
                                            Información de Destino
                                        </h3>
                                        <div className="space-y-3 rounded-2xl border border-border/50 bg-muted/30 p-4 md:space-y-4 md:rounded-3xl md:p-6">
                                            <div className="flex items-start gap-3 md:gap-4">
                                                <div className="rounded-xl bg-white p-2 text-primary shadow-sm">
                                                    <User className="h-4 w-4 md:h-5 md:w-5" />
                                                </div>
                                                <div>
                                                    <p className="text-[10px] font-black text-muted-foreground uppercase md:text-[9px]">
                                                        Destinatario
                                                    </p>
                                                    <p className="text-sm font-bold md:text-base">
                                                        {entregaSeleccionada.cliente ||
                                                            'Cliente no registrado'}
                                                    </p>
                                                </div>
                                            </div>
                                            {entregaSeleccionada.direccion && (
                                                <div className="flex items-start gap-3 md:gap-4">
                                                    <div className="rounded-xl bg-white p-2 text-primary shadow-sm">
                                                        <Navigation className="h-4 w-4 md:h-5 md:w-5" />
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] font-black text-muted-foreground uppercase md:text-[9px]">
                                                            Dirección
                                                        </p>
                                                        <p className="text-sm leading-relaxed font-medium">
                                                            {
                                                                entregaSeleccionada.direccion
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                            )}
                                            {entregaSeleccionada.notas && (
                                                <div className="flex items-start gap-3 md:gap-4">
                                                    <div className="rounded-xl bg-white p-2 text-primary shadow-sm">
                                                        <Info className="h-4 w-4 md:h-5 md:w-5" />
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] font-black text-muted-foreground uppercase md:text-[9px]">
                                                            Notas
                                                        </p>
                                                        <p className="text-sm leading-relaxed font-medium">
                                                            {
                                                                entregaSeleccionada.notas
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                            )}
                                            {!entregaSeleccionada.notas &&
                                                !entregaSeleccionada.direccion && (
                                                    <p className="rounded-xl bg-white p-4 text-center text-xs text-muted-foreground italic">
                                                        No se han registrado
                                                        instrucciones especiales
                                                        para este despacho.
                                                    </p>
                                                )}
                                        </div>
                                    </section>

                                    {entregaSeleccionada.items &&
                                    entregaSeleccionada.items.length > 0 ? (
                                        <section>
                                            <h3 className="mb-3 flex items-center gap-2 text-xs font-black tracking-widest text-primary/70 uppercase md:text-[10px]">
                                                <Scale className="h-3 w-3" />{' '}
                                                Hoja de Carga (Métricas)
                                            </h3>
                                            <div className="space-y-3">
                                                {entregaSeleccionada.items.map(
                                                    (item) => (
                                                        <div
                                                            key={item.id}
                                                            className="rounded-xl border border-border/50 bg-white p-3 shadow-sm transition-all hover:border-primary/20 md:rounded-2xl md:p-4"
                                                        >
                                                            <div className="mb-2 flex flex-col gap-2 md:mb-3 md:flex-row md:items-center md:justify-between">
                                                                <div>
                                                                    <p className="text-sm font-bold text-foreground md:text-base">
                                                                        {item
                                                                            .producto
                                                                            ?.nombre ||
                                                                            'Producto Desconocido'}
                                                                    </p>
                                                                    <p className="text-[10px] text-muted-foreground uppercase md:text-xs">
                                                                        Cant.
                                                                        Pedida:{' '}
                                                                        {
                                                                            item.cantidad_pedida
                                                                        }{' '}
                                                                        |
                                                                        Entregada:{' '}
                                                                        {
                                                                            item.cantidad_entregada
                                                                        }
                                                                    </p>
                                                                </div>
                                                                <Badge
                                                                    variant="outline"
                                                                    className="bg-primary/5 text-[9px] font-black text-primary uppercase md:text-xs"
                                                                >
                                                                    {item
                                                                        .producto
                                                                        ?.unidad_medida ||
                                                                        item.unidad_medida ||
                                                                        'Unid'}
                                                                </Badge>
                                                            </div>
                                                            <div className="grid grid-cols-3 gap-2">
                                                                <div className={`flex flex-col items-center justify-center rounded-lg p-2 text-center ring-1 md:rounded-xl md:p-2 ${item.unidad_medida === 'kg' ? 'bg-blue-500/5 ring-blue-500/10' : 'bg-cyan-500/5 ring-cyan-500/10'}`}>
                                                                    {item.unidad_medida === 'kg' ? (
                                                                        <Scale className="mb-0.5 h-2.5 w-2.5 text-blue-500 md:h-3 md:w-3" />
                                                                    ) : (
                                                                        <Droplets className="mb-0.5 h-2.5 w-2.5 text-cyan-500 md:h-3 md:w-3" />
                                                                    )}
                                                                    <p className={`text-[8px] font-black uppercase md:text-[8px] ${item.unidad_medida === 'kg' ? 'text-blue-600/60' : 'text-cyan-600/60'}`}>
                                                                        {item.unidad_medida?.toUpperCase() === 'KG' ? 'Kilos' : 'Litros'}
                                                                    </p>
                                                                    <p className={`text-xs font-black md:text-sm ${item.unidad_medida === 'kg' ? 'text-blue-700' : 'text-cyan-700'}`}>
                                                                        {Number(
                                                                            item.subtotal_metrica,
                                                                        ).toFixed(
                                                                            1,
                                                                        )}
                                                                        {item.unidad_medida?.toUpperCase() === 'KG' ? 'kg' : 'L'}
                                                                    </p>
                                                                </div>
                                                                <div className="flex flex-col items-center justify-center rounded-lg bg-orange-500/5 p-2 text-center ring-1 ring-orange-500/10 md:rounded-xl md:p-2">
                                                                    <Package className="mb-0.5 h-2.5 w-2.5 text-orange-500 md:h-3 md:w-3" />
                                                                    <p className="text-[8px] font-black text-orange-600/60 uppercase md:text-[8px]">
                                                                        Unid
                                                                    </p>
                                                                    <p className="text-xs font-black text-orange-700 md:text-sm">
                                                                        {
                                                                            item.unidades_totales
                                                                        }
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </section>
                                    ) : (
                                        entregaSeleccionada.productos_json && (
                                            <section>
                                                <h3 className="mb-3 flex items-center gap-2 text-xs font-black tracking-widest text-primary/70 uppercase md:text-[10px]">
                                                    <Package className="h-3 w-3" />{' '}
                                                    Productos a Entregar
                                                </h3>
                                                <div className="space-y-2 rounded-2xl border border-primary/10 bg-primary/[0.03] p-4 md:rounded-3xl md:p-6">
                                                    {(() => {
                                                        try {
                                                            const items =
                                                                JSON.parse(
                                                                    entregaSeleccionada.productos_json,
                                                                );
                                                            return items.map(
                                                                (
                                                                    item: any,
                                                                    idx: number,
                                                                ) => (
                                                                    <div
                                                                        key={
                                                                            idx
                                                                        }
                                                                        className="flex items-center justify-between border-b border-border/30 pb-2 last:border-0 last:pb-0"
                                                                    >
                                                                        <div>
                                                                            <p className="text-xs font-bold md:text-sm">
                                                                                {
                                                                                    item.nombre_producto
                                                                                }
                                                                            </p>
                                                                            <p className="text-[10px] text-muted-foreground md:text-xs">
                                                                                $
                                                                                {(
                                                                                    item.precio_unitario ||
                                                                                    0
                                                                                ).toLocaleString(
                                                                                    currency.locale,
                                                                                )}{' '}
                                                                                c/u
                                                                            </p>
                                                                        </div>
                                                                        <div className="text-right">
                                                                            <p className="text-sm font-black md:text-base">
                                                                                x
                                                                                {
                                                                                    item.cantidad
                                                                                }
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                ),
                                                            );
                                                        } catch {
                                                            return null;
                                                        }
                                                    })()}
                                                </div>
                                            </section>
                                        )
                                    )}
                                </div>

                                <div className="space-y-6">
                                    <section>
                                        <h3 className="mb-3 text-xs font-black tracking-widest text-primary/70 uppercase md:text-[10px]">
                                            Logística
                                        </h3>
                                        <div className="space-y-3 md:space-y-4">
                                            <div className="flex items-center gap-3 rounded-xl border border-primary/10 bg-primary/[0.03] p-3 md:rounded-3xl md:p-5">
                                                <div className="rounded-lg border border-primary/5 bg-white p-1.5 shadow-sm md:rounded-xl md:p-2">
                                                    <Calendar className="h-3.5 w-3.5 text-primary md:h-4 md:w-4" />
                                                </div>
                                                <div>
                                                    <p className="mb-0.5 text-[9px] leading-none font-black text-primary/60 uppercase md:text-[9px]">
                                                        Fecha
                                                    </p>
                                                    <p className="text-xs leading-none font-black md:text-sm">
                                                        {entregaSeleccionada.fecha_entrega
                                                            ? entregaSeleccionada.fecha_entrega.split(
                                                                  'T',
                                                              )[0]
                                                            : 'No agendada'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3 rounded-xl border border-primary/10 bg-primary/[0.03] p-3 md:rounded-3xl md:p-5">
                                                <div className="rounded-lg border border-primary/5 bg-white p-1.5 shadow-sm md:rounded-xl md:p-2">
                                                    <Truck className="h-3.5 w-3.5 text-primary md:h-4 md:w-4" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="mb-0.5 text-[9px] leading-none font-black text-primary/60 uppercase md:text-[9px]">
                                                        Vehículo
                                                    </p>
                                                    <p className="truncate text-xs leading-none font-black md:text-sm">
                                                        {vehiculos.find(
                                                            (v) =>
                                                                v.id.toString() ===
                                                                entregaSeleccionada.vehiculo_id?.toString(),
                                                        )?.placa ||
                                                            'No asignado'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3 rounded-xl border border-primary/10 bg-primary/[0.03] p-3 md:rounded-3xl md:p-5">
                                                <div className="rounded-lg border border-primary/5 bg-white p-1.5 shadow-sm md:rounded-xl md:p-2">
                                                    <User className="h-3.5 w-3.5 text-primary md:h-4 md:w-4" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="mb-0.5 text-[9px] leading-none font-black text-primary/60 uppercase md:text-[9px]">
                                                        Conductor
                                                    </p>
                                                    <p className="truncate text-xs leading-none font-black md:text-sm">
                                                        {conductores.find(
                                                            (c) =>
                                                                c.id.toString() ===
                                                                entregaSeleccionada.conductor_id?.toString(),
                                                        )?.nombre ||
                                                            'Pendiente'}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </section>

                                    <section>
                                        <h3 className="mb-3 text-xs font-black tracking-widest text-primary/70 uppercase md:text-[10px]">
                                            Línea de Vida
                                        </h3>
                                        <div className="relative space-y-4 pl-5 before:absolute before:top-2 before:bottom-2 before:left-1.5 before:w-[2px] before:rounded-full before:bg-muted md:pl-6">
                                            <div className="group relative flex items-center gap-3">
                                                <div className="absolute -left-5 z-10 flex h-3 w-3 items-center justify-center rounded-full bg-green-500 shadow-sm ring-2 ring-white transition-transform group-hover:scale-125 md:-left-6 md:h-4 md:w-4">
                                                    <Check className="h-1.5 w-1.5 text-white md:h-2 md:w-2" />
                                                </div>
                                                <div className="rounded-xl border border-green-500/10 bg-green-50/50 p-2 md:rounded-2xl md:p-3">
                                                    <p className="text-[10px] font-black text-green-600 uppercase md:text-xs">
                                                        Creado
                                                    </p>
                                                    <p className="text-[9px] text-muted-foreground md:text-[10px]">
                                                        {new Date(
                                                            entregaSeleccionada.created_at ||
                                                                '',
                                                        ).toLocaleDateString(
                                                            currency.locale,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="group relative flex items-center gap-3">
                                                <div
                                                    className={`absolute -left-5 z-10 flex h-3 w-3 items-center justify-center rounded-full shadow-sm ring-2 ring-white transition-transform group-hover:scale-125 md:-left-6 md:h-4 md:w-4 ${['en_ruta', 'entregado'].includes(entregaSeleccionada.estado) ? 'bg-blue-500' : 'bg-muted'}`}
                                                ></div>
                                                <div
                                                    className={`rounded-xl border p-2 md:rounded-2xl md:p-3 ${['en_ruta', 'entregado'].includes(entregaSeleccionada.estado) ? 'border-blue-500/10 bg-blue-50/50' : 'border-border/50 bg-muted/30'}`}
                                                >
                                                    <p
                                                        className={`text-[10px] font-black uppercase md:text-xs ${['en_ruta', 'entregado'].includes(entregaSeleccionada.estado) ? 'text-blue-600' : 'text-muted-foreground'}`}
                                                    >
                                                        En Tránsito
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="group relative flex items-center gap-3">
                                                <div
                                                    className={`absolute -left-5 z-10 flex h-3 w-3 items-center justify-center rounded-full shadow-sm ring-2 ring-white transition-transform group-hover:scale-125 md:-left-6 md:h-4 md:w-4 ${entregaSeleccionada.estado === 'entregado' ? 'bg-green-500' : 'bg-muted'}`}
                                                >
                                                    {entregaSeleccionada.estado ===
                                                        'entregado' && (
                                                        <Check className="h-1.5 w-1.5 text-white md:h-2 md:w-2" />
                                                    )}
                                                </div>
                                                <div
                                                    className={`rounded-xl border p-2 md:rounded-2xl md:p-3 ${entregaSeleccionada.estado === 'entregado' ? 'border-green-500/10 bg-green-50/50' : 'border-border/50 bg-muted/30'}`}
                                                >
                                                    <p
                                                        className={`text-[10px] font-black uppercase md:text-xs ${entregaSeleccionada.estado === 'entregado' ? 'text-green-600' : 'text-muted-foreground'}`}
                                                    >
                                                        Entregado
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </section>

                                    <Button
                                        type="button"
                                        onClick={() => setIsVerOpen(false)}
                                        className="h-12 w-full rounded-2xl bg-foreground px-8 text-base font-black tracking-tight text-background shadow-xl shadow-foreground/10 transition-all hover:bg-foreground/90 active:scale-95 md:rounded-[20px] md:px-12 md:text-lg"
                                    >
                                        Cerrar
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

        </>
    );
}
