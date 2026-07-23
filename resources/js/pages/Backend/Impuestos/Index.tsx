import { Head, useForm } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Calculator
} from 'lucide-react';
import { useState } from 'react';
import { useMemo } from 'react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
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
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

interface Impuesto {
    id: number;
    nombre: string;
    codigo: string | null;
    tasa: number;
    tipo: string | null;
    descripcion: string | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    estado: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Impuestos', href: '/impuestos' },
];

const estados = ['activo', 'inactivo', 'pendiente'];
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const tipos = ['fijo', 'porcentaje', 'variable'];

interface ChartData {
    tasaPromedio: number;
    porTipo: Record<string, number>;
    porEstado: Record<string, number>;
    impuestosList: { nombre: string; tasa: number; tipo: string }[];
    totalImpuestos: number;
    activos: number;
}

export default function Index({
    impuestos,
    chartData,
}: {
    impuestos: {
        data: Impuesto[];
        links: any[];
        from?: number;
        to?: number;
        total?: number;
        meta?: any;
    };
    chartData?: ChartData;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Impuesto | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        nombre: '',
        codigo: '',
        tasa: 0,
        tipo: 'porcentaje',
        descripcion: '',
        fecha_inicio: '',
        fecha_fin: '',
        estado: 'activo',
    });

    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('finanzas.impuestos.create');
    const canEdit = hasPermission('finanzas.impuestos.edit');
    const canDelete = hasPermission('finanzas.impuestos.delete');

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });

    const impuestosFiltrados = useMemo(() => {
        return impuestos.data.filter((i) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !i.nombre.toLowerCase().includes(busca) &&
                    !(i.codigo || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && i.estado !== filtros.estado) return false;

            return true;
        });
    }, [impuestos.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/impuestos/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/impuestos', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (i: Impuesto) => {
        setEditando(i);
        setData({
            nombre: i.nombre,
            codigo: i.codigo || '',
            tasa: i.tasa,
            tipo: i.tipo || 'porcentaje',
            descripcion: i.descripcion || '',
            fecha_inicio: i.fecha_inicio || '',
            fecha_fin: i.fecha_fin || '',
            estado: i.estado,
        });
        setIsOpen(true);
    };
    const handleNew = () => {
        reset();
        setData({
            nombre: '',
            codigo: '',
            tasa: 0,
            tipo: 'porcentaje',
            descripcion: '',
            fecha_inicio: '',
            fecha_fin: '',
            estado: 'activo',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/impuestos/${id}`);
    };
    const getEstadoBadge = (estado: string) => {
        const colores: Record<string, string> = {
            activo: 'bg-green-500',
            inactivo: 'bg-gray-500',
            pendiente: 'bg-yellow-500',
        };
        return (
            <Badge className={colores[estado] || 'bg-gray-500'}>{estado}</Badge>
        );
    };

    return (
        <>
            <Head title="Impuestos" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 pb-24">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Impuestos</h1>
                            <p className="text-muted-foreground">
                                Gestión de impuestos
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canCreate && (
                                <Button onClick={handleNew} size="sm">
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                </Button>
                            )}
                            <BulkActions
                                baseUrl="/impuestos"
                                filters={{}}
                                modelName="Impuestos"
                            />
                        </div>
                    </div>

                    {chartData && chartData.totalImpuestos > 0 && (
                        <div className="hidden grid-cols-1 gap-4 lg:grid lg:grid-cols-3">
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Calculator className="h-4 w-4" />
                                        Tasa Promedio
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-black text-amber-600">
                                        {chartData.tasaPromedio}%
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Promedio de todas las tasas
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base">
                                        Por Tipo
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="h-32">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <PieChart>
                                                <Pie
                                                    data={Object.entries(
                                                        chartData.porTipo,
                                                    ).map(([name, value]) => ({
                                                        name,
                                                        value,
                                                    }))}
                                                    dataKey="value"
                                                    nameKey="name"
                                                    cx="50%"
                                                    cy="50%"
                                                    outerRadius={40}
                                                >
                                                    {Object.keys(
                                                        chartData.porTipo,
                                                    ).map((_, index) => (
                                                        <Cell
                                                            key={`cell-${index}`}
                                                            fill={
                                                                COLORS[
                                                                    index %
                                                                        COLORS.length
                                                                ]
                                                            }
                                                        />
                                                    ))}
                                                </Pie>
                                                <Tooltip />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base">
                                        Impuestos por Tasa
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="h-32">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <BarChart
                                                data={chartData.impuestosList.slice(
                                                    0,
                                                    5,
                                                )}
                                            >
                                                <CartesianGrid strokeDasharray="3 3" />
                                                <XAxis
                                                    dataKey="nombre"
                                                    tick={{ fontSize: 10 }}
                                                    interval={0}
                                                />
                                                <YAxis
                                                    tick={{ fontSize: 10 }}
                                                />
                                                <Tooltip />
                                                <Bar
                                                    dataKey="tasa"
                                                    fill="#f59e0b"
                                                    name="Tasa %"
                                                />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Impuestos</CardTitle>
                                    <CardDescription>
                                        {impuestosFiltrados.length} registros
                                        encontrados
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
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar por nombre o código..."
                                            value={filtros.busqueda}
                                            onChange={(e) =>
                                                setFiltros({
                                                    ...filtros,
                                                    busqueda: e.target.value,
                                                })
                                            }
                                            className="h-9 pl-8"
                                        />
                                    </div>
                                </div>
                                <select
                                    value={filtros.estado}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            estado: e.target.value,
                                        })
                                    }
                                    className="flex h-9 min-w-[150px] rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Todos los estados</option>
                                    {estados.map((e) => (
                                        <option key={e} value={e}>
                                            {e.toUpperCase()}
                                        </option>
                                    ))}
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
                            <div className="overflow-x-auto">
                                <table className="w-full text-xs sm:text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="py-3 text-left font-medium">
                                                Nombre / Código
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Tasa
                                            </th>
                                            <th className="py-3 text-left font-medium">
                                                Tipo
                                            </th>
                                            <th className="py-3 text-center font-medium">
                                                Estado
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {impuestosFiltrados.map((i) => (
                                            <tr
                                                key={i.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-3">
                                                    <div className="font-medium">
                                                        {i.nombre}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {i.codigo || '-'}
                                                    </div>
                                                </td>
                                                <td className="py-3 text-right font-bold text-primary">
                                                    {i.tasa}%
                                                </td>
                                                <td className="py-3">
                                                    <div className="text-[10px] font-medium uppercase">
                                                        {i.tipo || '-'}
                                                    </div>
                                                </td>
                                                <td className="py-3 text-center">
                                                    {getEstadoBadge(i.estado)}
                                                </td>
                                                <td className="py-3 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0"
                                                                onClick={() =>
                                                                    handleEdit(i)
                                                                }
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        i.id,
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
                                        {impuestosFiltrados.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron impuestos
                                                    con los filtros aplicados
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {impuestosFiltrados.length === 0 ? (
                                    <div className="col-span-full py-8 text-center text-muted-foreground">
                                        No se encontraron impuestos con los filtros aplicados
                                    </div>
                                ) : (
                                    impuestosFiltrados.map((i) => (
                                        <Card key={i.id} className="flex flex-col">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-start justify-between">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <Calculator className="h-5 w-5 text-amber-500" />
                                                            <CardTitle className="text-sm">{i.nombre}</CardTitle>
                                                        </div>
                                                        {i.codigo && (
                                                            <CardDescription className="text-xs">
                                                                {i.codigo}
                                                            </CardDescription>
                                                        )}
                                                    </div>
                                                    {getEstadoBadge(i.estado)}
                                                </div>
                                            </CardHeader>
                                            <CardContent className="flex-1">
                                                <div className="space-y-1 text-xs">
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Tasa:</span>
                                                        <span className="font-bold text-primary">{i.tasa}%</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Tipo:</span>
                                                        <span className="font-medium uppercase">{i.tipo || '-'}</span>
                                                    </div>
                                                    {i.descripcion && (
                                                        <div className="pt-1 text-muted-foreground line-clamp-2">
                                                            {i.descripcion}
                                                        </div>
                                                    )}
                                                </div>
                                            </CardContent>
                                            <div className="flex items-center justify-end gap-1 border-t p-3">
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(i)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive" onClick={() => handleDelete(i.id)}>
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
                                    links={impuestos.links}
                                    meta={impuestos.meta || impuestos}
                                />
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-w-lg md:max-w-2xl">
                    <DialogHeader className="bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            {editando ? 'Editar' : 'Nuevo'} Impuesto
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="px-4 py-4 md:px-6">
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Nombre *
                                    </Label>
                                    <Input
                                        value={data.nombre}
                                        onChange={(e) =>
                                            setData('nombre', e.target.value)
                                        }
                                        placeholder="Ej: IVA"
                                        required
                                        className="h-9"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Código
                                    </Label>
                                    <Input
                                        value={data.codigo}
                                        onChange={(e) =>
                                            setData('codigo', e.target.value)
                                        }
                                        placeholder="Ej: 19"
                                        className="h-9"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Tasa (%) *
                                    </Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={data.tasa}
                                        onChange={(e) =>
                                            setData(
                                                'tasa',
                                                parseFloat(e.target.value) || 0,
                                            )
                                        }
                                        placeholder="19"
                                        required
                                        className="h-9"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Tipo
                                    </Label>
                                    <select
                                        value={data.tipo}
                                        onChange={(e) =>
                                            setData('tipo', e.target.value)
                                        }
                                        className="flex h-9 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                    >
                                        <option value="porcentaje">
                                            Porcentaje
                                        </option>
                                        <option value="fijo">Fijo</option>
                                        <option value="variable">
                                            Variable
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Fecha Inicio
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_inicio}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_inicio',
                                                e.target.value,
                                            )
                                        }
                                        className="h-9"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Fecha Fin
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_fin}
                                        onChange={(e) =>
                                            setData('fecha_fin', e.target.value)
                                        }
                                        className="h-9"
                                    />
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold uppercase">
                                    Descripción
                                </Label>
                                <Input
                                    value={data.descripcion}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
                                    }
                                    placeholder="Descripción del impuesto"
                                    className="h-9"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold uppercase">
                                    Estado
                                </Label>
                                <select
                                    value={data.estado}
                                    onChange={(e) =>
                                        setData('estado', e.target.value)
                                    }
                                    className="flex h-9 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                >
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                    <option value="pendiente">Pendiente</option>
                                </select>
                            </div>
                        </div>
                        <div className="mt-4 flex flex-col gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full sm:px-4"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                className="rounded-full sm:px-6"
                            >
                                Guardar
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
