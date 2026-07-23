import { Head, useForm, router } from '@inertiajs/react';
import {
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    LayoutGrid,
    List,
    ShoppingCart,
    Package,
    CheckCircle2,
    Clock,
    AlertCircle,
    Download,
    Upload,
    Receipt,
} from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { toast } from 'sonner';
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
    DialogDescription,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
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
import { Toaster } from '@/components/ui/sonner';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateCLP, getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Proveedor {
    id: number;
    nombre: string;
}

interface Producto {
    id: number;
    nombre: string;
    precio_venta: number;
}

interface Almacen {
    id: number;
    nombre: string;
}

interface DetalleCompra {
    id: number;
    producto_id: number;
    producto?: Producto;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
}

interface Compra {
    id: number;
    numero: string;
    proveedor_id: number;
    proveedor?: Proveedor;
    almacen_id: number | null;
    almacen?: Almacen;
    fecha: string;
    subtotal: number;
    iva: number;
    total: number;
    estado: 'pendiente' | 'recibida' | 'cancelada';
    notas: string | null;
    detalle_compras?: DetalleCompra[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/inventarios' },
    { title: 'Ordenes de Compra', href: '/compras' },
];

const ESTADOS = [
    { value: 'pendiente', label: 'Pendiente', color: 'bg-orange-500/10 text-orange-600', icon: Clock },
    { value: 'recibida', label: 'Recibida', color: 'bg-green-500/10 text-green-600', icon: CheckCircle2 },
    { value: 'cancelada', label: 'Cancelada', color: 'bg-red-500/10 text-red-600', icon: X },
];

export default function Index({
    compras,
    proveedors,
    productos,
    almacenes,
    filters,
}: {
    compras: { data: Compra[]; links: any[]; meta: any };
    proveedors: Proveedor[];
    productos: Producto[];
    almacenes: Almacen[];
    filters: {
        search?: string;
        estado?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('inventario.compras.create');
    const canEdit = hasPermission('inventario.compras.edit');
    const canDelete = hasPermission('inventario.compras.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Compra | null>(null);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [estadoFilter, setEstadoFilter] = useState(filters.estado || 'all');
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);

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
        proveedor_id: '' as string | number,
        almacen_id: '' as string | number,
        fecha: getLocalDateString(),
        estado: 'pendiente',
        notas: '',
        productos: [] as { producto_id: number | string; cantidad: number; precio_unitario: number }[],
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (estadoFilter !== 'all') query.estado = estadoFilter;

            router.get('/compras', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, estadoFilter]);

    const handleExport = (type: 'csv' | 'excel') => {
        const baseUrl = type === 'csv' ? '/compras/export' : '/compras/export-excel';
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (estadoFilter !== 'all') params.append('estado', estadoFilter);
        window.location.href = `${baseUrl}?${params.toString()}`;
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleImport = (e: React.ChangeEvent<HTMLInputElement>, type: 'csv' | 'excel') => {
        const file = e.target.files?.[0];
        if (file) {
            const formData = new FormData();
            formData.append('archivo', file);
            router.post('/compras/import', formData, {
                onSuccess: () => {
                    if (csvInputRef.current) csvInputRef.current.value = '';
                    if (excelInputRef.current) excelInputRef.current.value = '';
                    toast.success('Compras importadas');
                },
            });
        }
    };

    const addProducto = () => {
        setData('productos', [...data.productos, { producto_id: '', cantidad: 1, precio_unitario: 0 }]);
    };

    const removeProducto = (index: number) => {
        const newProds = [...data.productos];
        newProds.splice(index, 1);
        setData('productos', newProds);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/compras/${editando.id}`, {
                onSuccess: () => { setIsOpen(false); setEditando(null); reset(); toast.success('Compra actualizada'); },
            });
        } else {
            post('/compras', {
                onSuccess: () => { setIsOpen(false); reset(); toast.success('Compra registrada'); },
            });
        }
    };

    const handleEdit = (c: Compra) => {
        setEditando(c);
        setData({
            numero: c.numero,
            proveedor_id: c.proveedor_id,
            almacen_id: (c as any).almacen_id || '',
            fecha: c.fecha.substring(0, 10),
            estado: c.estado,
            notas: c.notas || '',
            productos: c.detalle_compras?.map(d => ({
                producto_id: d.producto_id,
                cantidad: d.cantidad,
                precio_unitario: d.precio_unitario
            })) || [],
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Desea eliminar esta orden de compra?')) {
            destroy(`/compras/${id}`, {
                onSuccess: () => toast.success('Compra eliminada'),
            });
        }
    };

    const getEstadoConfig = (val: string) => {
        return ESTADOS.find(e => e.value === val) || { label: val, color: 'bg-gray-500/10 text-gray-600', icon: AlertCircle };
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestión de Compras" />
            <Toaster position="bottom-right" />
            
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <ShoppingCart className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black uppercase tracking-widest text-primary/70">Procurement & Sourcing Hub</span>
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">Ordenes de Compra</h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Gestione sus adquisiciones, proveedores e ingresos de mercadería
                        </p>
                    </div>
                    
                    <div className="flex flex-wrap gap-2">
                        <input type="file" ref={csvInputRef} className="hidden" accept=".csv" onChange={(e) => handleImport(e, 'csv')} />
                        <input type="file" ref={excelInputRef} className="hidden" accept=".xlsx,.xls" onChange={(e) => handleImport(e, 'excel')} />
                        
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="h-9 px-3 gap-2 rounded-xl">
                                    <Download className="h-4 w-4 text-primary" /> Herramientas
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56 p-2 rounded-xl border-none shadow-2xl">
                                <DropdownMenuItem onClick={() => csvInputRef.current?.click()} className="rounded-lg py-3">
                                    <Upload className="mr-2 h-4 w-4 text-blue-500" /> Importar Compras
                                </DropdownMenuItem>
                                <hr className="my-2" />
                                <DropdownMenuItem onClick={() => handleExport('csv')} className="rounded-lg py-3">
                                    <Download className="mr-2 h-4 w-4 text-green-500" /> Exportar a CSV
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        
                        {canCreate && (
                            <Button onClick={() => { setEditando(null); reset(); setIsOpen(true); }} className="h-9 px-5 bg-primary shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all font-bold rounded-full">
                                <Plus className="mr-2 h-4 w-4" /> Generar Orden
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6">
                    {/* Filters Bar */}
                    <div className="flex flex-col p-4 gap-4 md:flex-row md:items-center bg-muted/40 rounded-3xl border border-muted/50">
                        <div className="relative flex-1">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Buscar por número o proveedor..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="h-11 pl-12 pr-12 border-none bg-background shadow-sm focus-visible:ring-primary/20 rounded-2xl"
                            />

                        </div>
                        <div className="flex gap-2">
                            <Select value={estadoFilter} onValueChange={setEstadoFilter}>
                                <SelectTrigger className="h-11 w-full border-none bg-background shadow-sm rounded-2xl font-bold sm:w-[180px]">
                                    <SelectValue placeholder="Estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos los estados</SelectItem>
                                    {ESTADOS.map((e) => (
                                        <SelectItem key={e.value} value={e.value}>{e.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button variant="outline" size="icon" className="h-11 w-11 border-none bg-background shadow-sm rounded-2xl text-muted-foreground" onClick={() => { setSearchTerm(''); setEstadoFilter('all'); }}>
                                <X className="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    <Card className="border-none shadow-xl shadow-foreground/5 overflow-hidden rounded-[32px]">
                        <CardHeader className="flex flex-row items-center justify-between px-6 py-4">
                            <div>
                                <CardTitle>Ordenes de Compra</CardTitle>
                                <CardDescription>{compras.data.length} registros encontrados</CardDescription>
                            </div>
                            <div className="flex items-center gap-1 rounded-lg border p-0.5">
                                <Button
                                    variant={viewMode === 'table' ? 'default' : 'ghost'}
                                    size="sm"
                                    className="h-8 w-8 p-0"
                                    onClick={() => setViewMode('table')}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant={viewMode === 'cards' ? 'default' : 'ghost'}
                                    size="sm"
                                    className="h-8 w-8 p-0"
                                    onClick={() => setViewMode('cards')}
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {viewMode === 'table' ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-muted/5 border-b text-[11px] font-black uppercase tracking-wider text-muted-foreground">
                                            <th className="px-6 py-4 text-left">Número</th>
                                            <th className="px-6 py-4 text-left">Proveedor</th>
                                            <th className="px-6 py-4 text-center">Estado</th>
                                            <th className="px-6 py-4 text-center">Fecha</th>
                                            <th className="px-6 py-4 text-right">Total</th>
                                            <th className="px-6 py-4 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-muted/50 font-medium">
                                        {compras.data.map((c) => {
                                            const config = getEstadoConfig(c.estado);
                                            return (
                                                <tr key={c.id} className="group transition-colors hover:bg-muted/30">
                                                    <td className="px-6 py-4">
                                                        <div className="flex items-center gap-2">
                                                            <div className="p-2 bg-background border rounded-xl shadow-sm text-primary group-hover:scale-110 transition-transform">
                                                                <Receipt className="h-4 w-4" />
                                                            </div>
                                                            <span className="font-black text-foreground">{c.numero}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <div className="flex flex-col">
                                                            <span className="font-bold text-foreground">{c.proveedor?.nombre || 'N/A'}</span>
                                                            <span className="text-[10px] text-muted-foreground font-black uppercase">Vendor Account</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        <Badge variant="outline" className={`${config.color} border-none text-[9px] font-black uppercase px-2.5 py-1 rounded-full`}>
                                                            {config.label}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        <span className="text-xs font-bold">{formatDateCLP(c.fecha)}</span>
                                                    </td>
                                                    <td className="px-6 py-4 text-right">
                                                        <span className="font-black text-foreground">{formatCurrency(c.total)}</span>
                                                    </td>
                                                    <td className="px-6 py-4 text-right">
                                                        <div className="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                            {canEdit && (
                                                                <Button variant="ghost" size="icon" className="h-8 w-8 text-primary hover:bg-primary/10 rounded-xl" onClick={() => handleEdit(c)}>
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:bg-destructive/10 rounded-xl" onClick={() => handleDelete(c.id)}>
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                {compras.data.map((c) => {
                                    const config = getEstadoConfig(c.estado);
                                    const Icon = config.icon;
                                    return (
                                        <Card key={c.id} className="overflow-hidden border-muted/50">
                                            <CardContent className="space-y-3 p-4">
                                                <div className="flex items-center justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <div className="rounded-xl bg-background p-2 shadow-sm">
                                                            <Receipt className="h-4 w-4 text-primary" />
                                                        </div>
                                                        <span className="font-black">{c.numero}</span>
                                                    </div>
                                                    <Badge variant="outline" className={`${config.color} border-none text-[9px] font-black uppercase px-2.5 py-1 rounded-full`}>
                                                        {config.label}
                                                    </Badge>
                                                </div>
                                                <div>
                                                    <p className="font-bold text-foreground">{c.proveedor?.nombre || 'N/A'}</p>
                                                    <p className="text-xs font-bold text-muted-foreground">{formatDateCLP(c.fecha)}</p>
                                                </div>
                                                <p className="text-lg font-black text-foreground">{formatCurrency(c.total)}</p>
                                                <div className="flex justify-end gap-1 border-t pt-2">
                                                    {canEdit && (
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-primary hover:bg-primary/10 rounded-xl" onClick={() => handleEdit(c)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:bg-destructive/10 rounded-xl" onClick={() => handleDelete(c.id)}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                                {compras.data.length === 0 && (
                                    <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                        <ShoppingCart className="mb-3 h-12 w-12 opacity-20" />
                                        <p>No se encontraron ordenes de compra</p>
                                    </div>
                                )}
                            </div>
                            )}
                            <div className="border-t border-muted/50 bg-muted/5 p-6">
                                <Pagination links={compras.links} meta={compras.meta} />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-w-[95vw] md:max-w-4xl border-none shadow-2xl p-0 overflow-y-auto rounded-[40px]">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-8 pb-4 text-left">
                        <div className="flex items-center gap-2 mb-1">
                            <ShoppingCart className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black uppercase tracking-widest text-primary/70">Procurement Officer Panel</span>
                        </div>
                        <DialogTitle className="text-3xl font-black tracking-tight text-primary">
                            {editando ? 'Editar Orden de Compra' : 'Nueva Orden de Compra'}
                        </DialogTitle>
                        <DialogDescription className="text-muted-foreground font-medium">Capture los detalles de adquisición y recepción de inventario.</DialogDescription>
                    </DialogHeader>
                    
                    <form onSubmit={handleSubmit} className="p-8 pt-2 overflow-y-auto max-h-[80vh]">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-10 py-4">
                            <div className="space-y-6">
                                <h3 className="text-[11px] font-black uppercase tracking-widest text-primary flex items-center gap-2">
                                    <Receipt className="h-4 w-4" /> Encabezado de Orden
                                </h3>
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold uppercase text-muted-foreground">Número de Orden *</Label>
                                            <Input value={data.numero} onChange={(e) => setData('numero', e.target.value)} required className="h-12 border-none bg-muted/30 font-bold rounded-2xl" placeholder="OC-0001" />
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold uppercase text-muted-foreground">Fecha Emisión</Label>
                                            <Input type="date" value={data.fecha} onChange={(e) => setData('fecha', e.target.value)} required className="h-12 border-none bg-muted/30 font-bold rounded-2xl text-center" />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold uppercase text-muted-foreground">Proveedor Asociado *</Label>
                                        <Select value={String(data.proveedor_id)} onValueChange={(v) => setData('proveedor_id', v)}>
                                            <SelectTrigger className="h-12 border-none bg-muted/30 font-bold rounded-2xl">
                                                <SelectValue placeholder="Seleccione proveedor..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {proveedors.map((p) => (
                                                    <SelectItem key={p.id} value={String(p.id)}>{p.nombre}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold uppercase text-muted-foreground">Almacén de Destino *</Label>
                                        <Select value={String(data.almacen_id)} onValueChange={(v) => setData('almacen_id', v)}>
                                            <SelectTrigger className="h-12 border-none bg-muted/30 font-bold rounded-2xl">
                                                <SelectValue placeholder="Seleccione almacén..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {almacenes.map((a) => (
                                                    <SelectItem key={a.id} value={String(a.id)}>{a.nombre}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold uppercase text-muted-foreground">Estado de Recepción</Label>
                                        <Select value={data.estado} onValueChange={(v: any) => setData('estado', v)}>
                                            <SelectTrigger className="h-12 border-none bg-muted/10 border-2 border-primary/20 font-bold rounded-2xl">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {ESTADOS.map((e) => (
                                                    <SelectItem key={e.value} value={e.value}>{e.label}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-6">
                                <h3 className="text-[11px] font-black uppercase tracking-widest text-primary flex items-center gap-2">
                                    <Package className="h-4 w-4" /> Líneas de Compra
                                </h3>
                                <div className="space-y-4 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                                    {data.productos.map((prod, idx) => (
                                        <div key={idx} className="p-4 bg-muted/10 border border-muted/50 rounded-2xl space-y-3 relative group/prod">
                                            <Button type="button" variant="ghost" size="icon" className="absolute -top-2 -right-2 h-6 w-6 bg-destructive text-white rounded-full opacity-0 group-hover/prod:opacity-100 transition-opacity" onClick={() => removeProducto(idx)}>
                                                <X className="h-3 w-3" />
                                            </Button>
                                            <Select value={String(prod.producto_id)} onValueChange={(v) => {
                                                const newProds = [...data.productos];
                                                newProds[idx].producto_id = v;
                                                newProds[idx].precio_unitario = productos.find(p => String(p.id) === v)?.precio_venta || 0;
                                                setData('productos', newProds);
                                            }}>
                                                <SelectTrigger className="h-10 border-none bg-background shadow-sm font-bold rounded-xl">
                                                    <SelectValue placeholder="Producto..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {productos.map((p) => (
                                                        <SelectItem key={p.id} value={String(p.id)}>{p.nombre}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-3">
                                                <div className="space-y-1">
                                                    <Label className="text-[9px] font-black uppercase text-muted-foreground px-1">Cant.</Label>
                                                    <Input type="number" value={prod.cantidad} onChange={(e) => {
                                                        const newProds = [...data.productos];
                                                        newProds[idx].cantidad = parseInt(e.target.value);
                                                        setData('productos', newProds);
                                                    }} className="h-10 border-none bg-background shadow-sm font-black rounded-xl text-center" />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label className="text-[9px] font-black uppercase text-muted-foreground px-1">P. Unitario</Label>
                                                    <Input type="number" value={prod.precio_unitario} onChange={(e) => {
                                                        const newProds = [...data.productos];
                                                        newProds[idx].precio_unitario = parseFloat(e.target.value);
                                                        setData('productos', newProds);
                                                    }} className="h-10 border-none bg-background shadow-sm font-black rounded-xl text-right" />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    <Button type="button" variant="outline" className="w-full h-12 border-dashed border-2 text-primary font-black uppercase text-[10px] rounded-2xl hover:bg-primary/5 transition-all" onClick={addProducto}>
                                        <Plus className="mr-2 h-4 w-4" /> Agregar Ítem
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-2 mt-6 pt-6 border-t">
                            <Label className="text-xs font-bold uppercase text-muted-foreground">Observaciones de la Orden</Label>
                            <textarea 
                                value={data.notas} 
                                onChange={(e) => setData('notas', e.target.value)} 
                                className="flex min-h-[100px] w-full rounded-3xl border-none bg-muted/30 px-6 py-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary/20 outline-none" 
                                placeholder="Especifique términos especiales de entrega o recepción..."
                            />
                        </div>
                        
                        <DialogFooter className="gap-2 mt-8 pt-6 border-t uppercase font-black">
                            <Button type="button" variant="ghost" onClick={() => setIsOpen(false)} className="rounded-full px-8">Cancelar</Button>
                            <Button type="submit" disabled={processing} className="rounded-full px-12 bg-primary shadow-lg shadow-primary/20 hover:bg-primary/90">
                                <CheckCircle2 className="mr-2 h-4 w-4" /> {editando ? 'Finalizar Edición' : 'Confirmar Adquisición'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

        </AppLayout>
    );
}
