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
    RotateCcw,
    Package,
    Download,
    Upload,
    FileText,
    AlertTriangle,
    Boxes,
    TrendingUp
} from 'lucide-react';
import { useState, useRef, useEffect, useMemo } from 'react';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    PieChart, Pie, Cell,
} from 'recharts';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
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
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Producto {
    id: number;
    codigo: string;
    nombre: string;
    tipo_envase: string | null;
}

interface Vacio {
    id: number;
    producto_id: number;
    cantidad: number;
    cantidad_minima: number;
    estado: string;
    ubicacion: string | null;
    observaciones: string | null;
    producto?: Producto;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vacíos', href: '/vacios' },
];

const estados = ['disponible', 'entregado', 'retornado', 'perdido'];

const estadoConfig: Record<
    string,
    { label: string; color: string; bgColor: string }
> = {
    disponible: {
        label: 'Disponible',
        color: 'text-green-600',
        bgColor: 'bg-green-50',
    },
    entregado: {
        label: 'Entregado',
        color: 'text-blue-600',
        bgColor: 'bg-blue-50',
    },
    retornado: {
        label: 'Retornado',
        color: 'text-purple-600',
        bgColor: 'bg-purple-50',
    },
    perdido: { label: 'Perdido', color: 'text-red-600', bgColor: 'bg-red-50' },
};

export default function Index({
    vacios,
    productos,
    filters,
}: {
    vacios: Vacio[];
    productos: Producto[];
    filters: {
        search?: string;
        estado?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('inventario.vacios.create');
    const canEdit = hasPermission('inventario.vacios.edit');
    const canDelete = hasPermission('inventario.vacios.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Vacio | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const importFileRef = useRef<HTMLInputElement>(null);
    const importExcelRef = useRef<HTMLInputElement>(null);

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [estadoFilter, setEstadoFilter] = useState(filters.estado || '');

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                '/vacios',
                {
                    search: searchTerm,
                    estado: estadoFilter,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, estadoFilter]);

    const handleExport = (type: 'csv' | 'excel') => {
        const baseUrl = type === 'csv' ? '/vacios/export' : '/vacios/export-excel';
        const params = new URLSearchParams({
            search: searchTerm,
            estado: estadoFilter,
        });
        window.location.href = `${baseUrl}?${params.toString()}`;
    };

    const handleImport = (e: React.ChangeEvent<HTMLInputElement>, isExcel = false) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('archivo', file);

        router.post(isExcel ? '/vacios/import-excel' : '/vacios/import', formData, {
            onSuccess: () => {
                if (importFileRef.current) importFileRef.current.value = '';
                if (importExcelRef.current) importExcelRef.current.value = '';
            },
        });
    };

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        producto_id: '' as string,
        cantidad: 0,
        cantidad_minima: 5,
        estado: 'disponible',
        ubicacion: '',
        observaciones: '',
    });

    const limpiarFiltros = () => {
        setSearchTerm('');
        setEstadoFilter('');
    };

    const getTotalVacios = () => vacios.reduce((sum, v) => sum + v.cantidad, 0);
    const getVaciosDisponibles = () =>
        vacios
            .filter((v) => v.estado === 'disponible')
            .reduce((sum, v) => sum + v.cantidad, 0);
    const getVaciosEntregados = () =>
        vacios
            .filter((v) => v.estado === 'entregado')
            .reduce((sum, v) => sum + v.cantidad, 0);
    const getVaciosPerdidos = () =>
        vacios
            .filter((v) => v.estado === 'perdido')
            .reduce((sum, v) => sum + v.cantidad, 0);

    const estadoChartData = useMemo(() => {
        const disponible = vacios.filter((v) => v.estado === 'disponible').reduce((s, v) => s + v.cantidad, 0);
        const entregado = vacios.filter((v) => v.estado === 'entregado').reduce((s, v) => s + v.cantidad, 0);
        const perdido = vacios.filter((v) => v.estado === 'perdido').reduce((s, v) => s + v.cantidad, 0);
        return [
            { name: 'Disponibles', value: disponible, color: '#22c55e' },
            { name: 'Entregados', value: entregado, color: '#3b82f6' },
            { name: 'Perdidos', value: perdido, color: '#f43f5e' },
        ].filter((d) => d.value > 0);
    }, [vacios]);

    const productChartData = useMemo(() => {
        const map = new Map<string, number>();
        vacios.forEach((v) => {
            const name = v.producto?.nombre || `ID ${v.producto_id}`;
            map.set(name, (map.get(name) || 0) + v.cantidad);
        });
        return Array.from(map.entries())
            .map(([producto, cantidad]) => ({ producto, cantidad }))
            .sort((a, b) => b.cantidad - a.cantidad)
            .slice(0, 10);
    }, [vacios]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/vacios/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                },
            });
        } else {
            post('/vacios', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (vacio: Vacio) => {
        setEditando(vacio);
        setData({
            producto_id: vacio.producto_id.toString(),
            cantidad: vacio.cantidad,
            cantidad_minima: vacio.cantidad_minima,
            estado: vacio.estado,
            ubicacion: vacio.ubicacion || '',
            observaciones: vacio.observaciones || '',
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar este registro de vacío?'))
            destroy(`/vacios/${id}`);
    };

    const handleRetornar = (vacio: Vacio) => {
        const cantidad = prompt('¿Cuántos envases va a retornar?', '1');
        if (cantidad && Number(cantidad) > 0) {
            router.patch(`/vacios/${vacio.id}/retornar`, {
                cantidad: Number(cantidad),
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vacíos - Envases Retornables" />
            <div className="flex flex-col gap-3 p-3">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-lg font-bold">Vacíos</h1>
                        <p className="text-xs text-muted-foreground">Control de envases retornables</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="h-8 gap-1 text-xs">
                                    <Download className="h-3.5 w-3.5" />
                                    Herramientas
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem onClick={() => handleExport('csv')} className="gap-2 text-xs">
                                    <Download className="h-3.5 w-3.5 text-green-500" />
                                    Exportar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => handleExport('excel')} className="gap-2 text-xs">
                                    <FileText className="h-3.5 w-3.5 text-blue-500" />
                                    Exportar Excel
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <label className="flex cursor-pointer items-center gap-2 text-xs">
                                        <Upload className="h-3.5 w-3.5 text-orange-500" />
                                        Importar CSV
                                        <input type="file" ref={importFileRef} accept=".csv,.txt" onChange={(e) => handleImport(e)} className="hidden" />
                                    </label>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <label className="flex cursor-pointer items-center gap-2 text-xs">
                                        <Upload className="h-3.5 w-3.5 text-purple-500" />
                                        Importar Excel
                                        <input type="file" ref={importExcelRef} accept=".xlsx,.xls" onChange={(e) => handleImport(e, true)} className="hidden" />
                                    </label>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        {canCreate && (
                            <Button size="sm" className="h-8 text-xs" onClick={() => { setEditando(null); reset(); setIsOpen(true); }}>
                                <Plus className="mr-1 h-3.5 w-3.5" />
                                Nuevo
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-2 xl:grid-cols-4">
                    <Card className="border-l-4 border-l-indigo-500">
                        <CardHeader className="p-3 pb-1">
                            <CardTitle className="flex items-center gap-1.5 text-[11px] font-medium">
                                <Package className="h-3 w-3 text-indigo-500" />
                                Total Vacíos
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-3 pt-0">
                            <div className="text-lg font-bold">{getTotalVacios()}</div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-emerald-500">
                        <CardHeader className="p-3 pb-1">
                            <CardTitle className="flex items-center gap-1.5 text-[11px] font-medium">
                                <Boxes className="h-3 w-3 text-emerald-500" />
                                Disponibles
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-3 pt-0">
                            <div className="text-lg font-bold text-emerald-600">{getVaciosDisponibles()}</div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-blue-500">
                        <CardHeader className="p-3 pb-1">
                            <CardTitle className="flex items-center gap-1.5 text-[11px] font-medium">
                                <TrendingUp className="h-3 w-3 text-blue-500" />
                                Entregados
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-3 pt-0">
                            <div className="text-lg font-bold text-blue-600">{getVaciosEntregados()}</div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-rose-500">
                        <CardHeader className="p-3 pb-1">
                            <CardTitle className="flex items-center gap-1.5 text-[11px] font-medium">
                                <AlertTriangle className="h-3 w-3 text-rose-500" />
                                Perdidos
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-3 pt-0">
                            <div className="text-lg font-bold text-rose-600">{getVaciosPerdidos()}</div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="p-3 pb-1">
                            <CardTitle className="text-xs font-medium sm:text-sm">Distribución por Estado</CardTitle>
                        </CardHeader>
                        <CardContent className="p-3">
                            <div className="flex h-36 items-center justify-center sm:h-44">
                                <ResponsiveContainer width="60%" height="100%">
                                    <PieChart>
                                        <Pie
                                            data={estadoChartData}
                                            cx="50%" cy="50%"
                                            innerRadius={35}
                                            outerRadius={55}
                                            paddingAngle={3}
                                            dataKey="value"
                                        >
                                            {estadoChartData.map((e, i) => (
                                                <Cell key={i} fill={e.color} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                    </PieChart>
                                </ResponsiveContainer>
                                <div className="space-y-1 text-[11px] sm:text-xs">
                                    {estadoChartData.map((e) => (
                                        <div key={e.name} className="flex items-center gap-1.5">
                                            <span className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: e.color }} />
                                            <span>{e.name}: {e.value}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="p-3 pb-1">
                            <CardTitle className="text-xs font-medium sm:text-sm">Top Productos</CardTitle>
                        </CardHeader>
                        <CardContent className="p-3">
                            <div className="h-36 sm:h-44">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={productChartData} layout="vertical" margin={{ left: 10, right: 10 }}>
                                        <CartesianGrid strokeDasharray="3 3" className="stroke-muted" horizontal={false} />
                                        <XAxis type="number" tick={{ fontSize: 10 }} />
                                        <YAxis type="category" dataKey="producto" tick={{ fontSize: 10 }} width={100} />
                                        <Tooltip contentStyle={{ fontSize: 11 }} />
                                        <Bar dataKey="cantidad" fill="#6366f1" radius={[0, 4, 4, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="p-3 pb-0">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-sm font-bold">Control de Vacíos</CardTitle>
                            <div className="flex items-center gap-2">
                                <span className="text-[11px] text-muted-foreground">{vacios.length} registros</span>
                                <Button variant={viewMode === 'table' ? 'default' : 'outline'} size="icon" className="h-7 w-7" onClick={() => setViewMode('table')}>
                                    <List className="h-3.5 w-3.5" />
                                </Button>
                                <Button variant={viewMode === 'cards' ? 'default' : 'outline'} size="icon" className="h-7 w-7" onClick={() => setViewMode('cards')}>
                                    <LayoutGrid className="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-3">
                        <div className="mb-2 flex flex-wrap gap-1.5">
                            <div className="min-w-[140px] flex-1">
                            <div className="relative">
                                <Search className="absolute top-2 left-2 h-3.5 w-3.5 text-muted-foreground" />
                                <Input
                                    placeholder="Buscar producto o ubicación..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="h-8 pl-7 pr-7 text-xs"
                                />

                            </div>
                            </div>
                            <select
                                value={estadoFilter}
                                onChange={(e) => setEstadoFilter(e.target.value)}
                                className="flex h-8 rounded-md border bg-background px-2 py-1 text-xs"
                            >
                                <option value="">Todos</option>
                                {estados.map((e) => (
                                    <option key={e} value={e}>
                                        {estadoConfig[e].label}
                                    </option>
                                ))}
                            </select>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 text-xs"
                                onClick={limpiarFiltros}
                            >
                                <X className="mr-1 h-3 w-3" />
                                Limpiar
                            </Button>
                        </div>

                        {viewMode === 'table' ? (
                            vacios.length === 0 ? (
                                <p className="py-6 text-center text-xs text-muted-foreground">
                                    No hay registros de vacíos
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="border-b text-muted-foreground">
                                                <th className="px-2 py-1.5 text-left font-medium">Producto</th>
                                                <th className="px-2 py-1.5 text-left font-medium">Tipo</th>
                                                <th className="px-2 py-1.5 text-right font-medium">Cant</th>
                                                <th className="px-2 py-1.5 text-right font-medium">Mín</th>
                                                <th className="px-2 py-1.5 text-center font-medium">Estado</th>
                                                <th className="px-2 py-1.5 text-left font-medium">Ubicación</th>
                                                <th className="px-2 py-1.5 text-right font-medium">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {vacios.map((v) => (
                                                <tr key={v.id} className="border-b last:border-0 hover:bg-muted/30">
                                                    <td className="px-2 py-1.5 font-medium">{v.producto?.nombre}</td>
                                                    <td className="px-2 py-1.5 text-muted-foreground">{v.producto?.tipo_envase || '-'}</td>
                                                    <td className="px-2 py-1.5 text-right font-bold">{v.cantidad}</td>
                                                    <td className="px-2 py-1.5 text-right text-muted-foreground">{v.cantidad_minima}</td>
                                                    <td className="px-2 py-1.5 text-center">
                                                        <span className={`inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium ${estadoConfig[v.estado]?.bgColor} ${estadoConfig[v.estado]?.color}`}>
                                                            {estadoConfig[v.estado]?.label}
                                                        </span>
                                                    </td>
                                                    <td className="px-2 py-1.5 text-muted-foreground">{v.ubicacion || '-'}</td>
                                                    <td className="px-2 py-1.5 text-right whitespace-nowrap">
                                                        {v.estado === 'entregado' && (
                                                            <Button variant="ghost" size="icon" className="h-6 w-6" title="Retornar" onClick={() => handleRetornar(v)}>
                                                                <RotateCcw className="h-3 w-3" />
                                                            </Button>
                                                        )}
                                                        {canEdit && (
                                                            <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => handleEdit(v)}>
                                                                <Pencil className="h-3 w-3" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button variant="ghost" size="icon" className="h-6 w-6 text-red-500 hover:text-red-600" onClick={() => handleDelete(v.id)}>
                                                                <Trash2 className="h-3 w-3" />
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )
                        ) : (
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {vacios.length === 0 ? (
                                    <div className="col-span-full flex flex-col items-center py-12 text-center text-muted-foreground">
                                        <Package className="mb-4 h-12 w-12 opacity-20" />
                                        <p className="font-medium">No hay registros de vacíos</p>
                                    </div>
                                ) : vacios.map((v) => (
                                    <Card key={v.id} className="overflow-hidden">
                                        <CardHeader className="pb-2">
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <CardTitle className="text-sm font-bold">{v.producto?.nombre}</CardTitle>
                                                    <CardDescription className="text-xs">{v.producto?.tipo_envase || '-'}</CardDescription>
                                                </div>
                                                <span className={`inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium ${estadoConfig[v.estado]?.bgColor} ${estadoConfig[v.estado]?.color}`}>
                                                    {estadoConfig[v.estado]?.label}
                                                </span>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-2 pt-0">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Cantidad</span>
                                                <span className="font-bold">{v.cantidad}</span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Stock Mín</span>
                                                <span>{v.cantidad_minima}</span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Ubicación</span>
                                                <span>{v.ubicacion || '-'}</span>
                                            </div>
                                            {v.observaciones && (
                                                <div className="text-xs text-muted-foreground line-clamp-2">{v.observaciones}</div>
                                            )}
                                            <div className="flex justify-end gap-1 border-t pt-2">
                                                {v.estado === 'entregado' && (
                                                    <Button variant="ghost" size="icon" className="h-6 w-6" title="Retornar" onClick={() => handleRetornar(v)}>
                                                        <RotateCcw className="h-3 w-3" />
                                                    </Button>
                                                )}
                                                {canEdit && (
                                                    <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => handleEdit(v)}>
                                                        <Pencil className="h-3 w-3" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="icon" className="h-6 w-6 text-red-500 hover:text-red-600" onClick={() => handleDelete(v.id)}>
                                                        <Trash2 className="h-3 w-3" />
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="p-0 sm:max-w-md">
                    <DialogHeader className="p-4 pb-1">
                        <DialogTitle className="text-base">
                            {editando ? 'Editar Vacío' : 'Nuevo Vacío'}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {editando ? 'Modifique los datos del vacío' : 'Ingrese los datos del vacío'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-3 px-4 pb-1">
                            {!editando && (
                                <div className="grid gap-1">
                                    <Label className="text-xs">Producto</Label>
                                    <Select value={data.producto_id} onValueChange={(v) => setData('producto_id', v)}>
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue placeholder="Seleccionar producto" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {productos.map((p) => (
                                                <SelectItem key={p.id} value={p.id.toString()}>{p.nombre} ({p.codigo})</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-3">
                                <div className="grid gap-1">
                                    <Label className="text-xs">Cantidad</Label>
                                    <Input type="number" value={data.cantidad} onChange={(e) => setData('cantidad', parseInt(e.target.value) || 0)} required className="h-8 text-xs" />
                                </div>
                                <div className="grid gap-1">
                                    <Label className="text-xs">Stock Mín</Label>
                                    <Input type="number" value={data.cantidad_minima} onChange={(e) => setData('cantidad_minima', parseInt(e.target.value) || 0)} required className="h-8 text-xs" />
                                </div>
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs">Estado</Label>
                                <Select value={data.estado} onValueChange={(v) => setData('estado', v)}>
                                    <SelectTrigger className="h-8 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {estados.map((e) => (
                                            <SelectItem key={e} value={e}>{estadoConfig[e].label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs">Ubicación</Label>
                                <Input value={data.ubicacion} onChange={(e) => setData('ubicacion', e.target.value)} placeholder="Ej: Bodega A" className="h-8 text-xs" />
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs">Observaciones</Label>
                                <Input value={data.observaciones} onChange={(e) => setData('observaciones', e.target.value)} placeholder="Notas adicionales" className="h-8 text-xs" />
                            </div>
                        </div>
                        <DialogFooter className="border-t bg-muted/10 p-4 pt-3">
                            <Button type="button" variant="outline" size="sm" className="h-8 text-xs" onClick={() => setIsOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" size="sm" className="h-8 text-xs">
                                <Check className="mr-1 h-3.5 w-3.5" />
                                Guardar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

        </AppLayout>
    );
}
