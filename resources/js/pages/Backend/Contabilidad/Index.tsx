import { Head, useForm } from '@inertiajs/react';
import {
    Check,
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Calculator,
    Search,
    X,
    Eye,
    TrendingUp
} from 'lucide-react';
import { useState } from 'react';
import { useMemo } from 'react';
import {
    LineChart,
    Line,
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateCLP } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface DetalleAsiento {
    id?: number;
    cuenta: string;
    cuenta_codigo: string;
    descripcion: string;
    debe: number;
    haber: number;
}

interface Asiento {
    id: number;
    fecha: string;
    numero: string;
    descripcion: string;
    tipo: string;
    total_debe: number;
    total_haber: number;
    estado: boolean;
    detalles: DetalleAsiento[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contabilidad', href: '/contabilidad' },
];

interface ChartData {
    mes: string;
    debe: number;
    haber: number;
}

interface TotalTipo {
    tipo: string;
    total: number;
}

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

export default function Index({
    asientos,
    chartData = [],
    totalesTipo = [],
    proyeccion = [],
}: {
    asientos: {
        data: Asiento[];
        links: any[];
        from?: number;
        to?: number;
        total?: number;
        meta?: any;
    };
    chartData?: ChartData[];
    totalesTipo?: TotalTipo[];
    proyeccion?: number[];
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Asiento | null>(null);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [viendo, setViendo] = useState<Asiento | null>(null);
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        fecha: '',
        numero: '',
        descripcion: '',
        tipo: 'diario',
        detalles: [] as DetalleAsiento[],
    });

    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('finanzas.contabilidad.create');
    const canEdit = hasPermission('finanzas.contabilidad.edit');
    const canDelete = hasPermission('finanzas.contabilidad.delete');

    const [viewMode, setViewMode] = useState<'table' | 'cards'>('cards');
    const [filtros, setFiltros] = useState({
        busqueda: '',
        tipo: '',
    });

    const asientosFiltrados = useMemo(() => {
        return asientos.data.filter((a) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !a.numero.toLowerCase().includes(busca) &&
                    !a.descripcion.toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.tipo && a.tipo !== filtros.tipo) return false;

            return true;
        });
    }, [asientos.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            tipo: '',
        });
    };

    const handleAddDetalle = () => {
        setData('detalles', [
            ...data.detalles,
            {
                cuenta: '',
                cuenta_codigo: '',
                descripcion: '',
                debe: 0,
                haber: 0,
            },
        ]);
    };

    const handleRemoveDetalle = (index: number) => {
        const newDetalles = data.detalles.filter((_, i) => i !== index);
        setData('detalles', newDetalles);
    };

    const handleDetalleChange = (
        index: number,
        field: keyof DetalleAsiento,
        value: string | number,
    ) => {
        const newDetalles = [...data.detalles];
        newDetalles[index] = { ...newDetalles[index], [field]: value };
        setData('detalles', newDetalles);
    };

    const totalDebe = data.detalles.reduce(
        (sum, d) => sum + (Number(d.debe) || 0),
        0,
    );
    const totalHaber = data.detalles.reduce(
        (sum, d) => sum + (Number(d.haber) || 0),
        0,
    );
    const isBalanced = totalDebe === totalHaber && totalDebe > 0;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/contabilidad/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                },
            });
        } else {
            post('/contabilidad', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (asiento: Asiento) => {
        setEditando(asiento);
        setData({
            numero: asiento.numero,
            fecha: asiento.fecha,
            descripcion: asiento.descripcion,
            tipo: asiento.tipo,
            detalles: (asiento as any).detalles || [],
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Está seguro de eliminar este asiento?')) {
            destroy(`/contabilidad/${id}`);
        }
    };

    return (
        <>
            <Head title="Contabilidad" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 pb-24">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Contabilidad</h1>
                            <p className="text-muted-foreground">
                                Gestione los asientos contables
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canCreate && (
                                <Button onClick={() => setIsOpen(true)} size="sm">
                                    <Plus className="mr-2 h-4 w-4" />
                                    <span className="hidden sm:inline">
                                        Nuevo Asiento
                                    </span>
                                    <span className="sm:hidden">Nuevo</span>
                                </Button>
                            )}
                            <BulkActions
                                baseUrl="/contabilidad"
                                filters={{}}
                                modelName="Asientos"
                            />
                        </div>
                    </div>

                    {chartData.length > 0 && (
                        <div className="hidden grid-cols-1 gap-4 lg:grid lg:grid-cols-3">
                            <Card className="lg:col-span-2">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base">
                                        Movimiento Contable (Últimos 12 meses)
                                    </CardTitle>
                                    <CardDescription>
                                        Comparación Debe vs Haber
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="h-48 min-h-[180px] sm:h-64">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <LineChart data={chartData}>
                                                <CartesianGrid
                                                    strokeDasharray="3 3"
                                                    className="stroke-muted"
                                                />
                                                <XAxis
                                                    dataKey="mes"
                                                    tick={{ fontSize: 11 }}
                                                    className="fill-muted-foreground"
                                                />
                                                <YAxis
                                                    tick={{ fontSize: 11 }}
                                                    className="fill-muted-foreground"
                                                    tickFormatter={(value) =>
                                                        `$${(value / 1000000).toFixed(1)}M`
                                                    }
                                                />
                                                <Tooltip
                                                    formatter={(value) =>
                                                        formatCurrency(
                                                            Number(value),
                                                        )
                                                    }
                                                    contentStyle={{
                                                        backgroundColor:
                                                            'hsl(var(--card))',
                                                        border: '1px solid hsl(var(--border))',
                                                        borderRadius: '6px',
                                                        zIndex: 9999,
                                                    }}
                                                    wrapperStyle={{
                                                        zIndex: 9999,
                                                    }}
                                                />
                                                <Line
                                                    type="monotone"
                                                    dataKey="debe"
                                                    stroke="#3b82f6"
                                                    strokeWidth={2}
                                                    dot={{ r: 3 }}
                                                    name="Debe"
                                                />
                                                <Line
                                                    type="monotone"
                                                    dataKey="haber"
                                                    stroke="#10b981"
                                                    strokeWidth={2}
                                                    dot={{ r: 3 }}
                                                    name="Haber"
                                                />
                                            </LineChart>
                                        </ResponsiveContainer>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base">
                                        Por Tipo de Asiento
                                    </CardTitle>
                                    <CardDescription>
                                        Distribución últimos 12 meses
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="h-48 min-h-[180px] sm:h-64">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <PieChart>
                                                <Pie
                                                    data={totalesTipo}
                                                    dataKey="total"
                                                    nameKey="tipo"
                                                    cx="50%"
                                                    cy="50%"
                                                    outerRadius={70}
                                                >
                                                    {totalesTipo.map(
                                                        (_entry, index) => (
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
                                                <Tooltip
                                                    formatter={(value) =>
                                                        formatCurrency(
                                                            Number(value),
                                                        )
                                                    }
                                                    contentStyle={{
                                                        zIndex: 9999,
                                                    }}
                                                    wrapperStyle={{
                                                        zIndex: 9999,
                                                    }}
                                                />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {proyeccion.length > 0 && (
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <TrendingUp className="h-4 w-4" />
                                    Proyección de Movimiento
                                </CardTitle>
                                <CardDescription>
                                    Estimación basada en tendencia de los
                                    últimos 12 meses
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="h-40 min-h-[150px] sm:h-48">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <BarChart
                                            data={proyeccion.map(
                                                (value, index) => ({
                                                    mes: `Mes ${index + 1}`,
                                                    proyectado: Math.max(
                                                        0,
                                                        value,
                                                    ),
                                                }),
                                            )}
                                        >
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                className="stroke-muted"
                                            />
                                            <XAxis
                                                dataKey="mes"
                                                tick={{ fontSize: 11 }}
                                            />
                                            <YAxis
                                                tick={{ fontSize: 11 }}
                                                tickFormatter={(value) =>
                                                    `$${(value / 1000000).toFixed(1)}M`
                                                }
                                            />
                                            <Tooltip
                                                formatter={(value) =>
                                                    formatCurrency(
                                                        Number(value) || 0,
                                                    )
                                                }
                                                contentStyle={{
                                                    backgroundColor:
                                                        'hsl(var(--card))',
                                                    border: '1px solid hsl(var(--border))',
                                                    borderRadius: '6px',
                                                    zIndex: 9999,
                                                }}
                                                wrapperStyle={{ zIndex: 9999 }}
                                            />
                                            <Bar
                                                dataKey="proyectado"
                                                fill="#8b5cf6"
                                                name="Proyectado"
                                                radius={[4, 4, 0, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Asientos Contables</CardTitle>
                                    <CardDescription>
                                        {asientosFiltrados.length} registros encontrados
                                    </CardDescription>
                                </div>
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
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 flex flex-col flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:flex-row sm:text-sm">
                                <div className="min-w-[150px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar..."
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
                                    value={filtros.tipo}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            tipo: e.target.value,
                                        })
                                    }
                                    className="flex h-9 min-w-[120px] rounded-md border bg-background px-2 py-1 text-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="diario">Diario</option>
                                    <option value="apertura">Apertura</option>
                                    <option value="cierre">Cierre</option>
                                </select>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9"
                                    onClick={limpiarFiltros}
                                >
                                    <X className="mr-1 h-4 w-4" />
                                    <span className="hidden sm:inline">
                                        Limpiar
                                    </span>
                                </Button>
                            </div>
                            {asientosFiltrados.length === 0 ? (
                                <div className="py-8 text-center text-muted-foreground">
                                    No se encontraron asientos con los filtros
                                    aplicados
                                </div>
                            ) : viewMode === 'table' ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                <th className="py-3 text-left">N°</th>
                                                <th className="py-3 text-left">Descripción</th>
                                                <th className="py-3 text-left">Fecha</th>
                                                <th className="py-3 text-right">Debe</th>
                                                <th className="py-3 text-right">Haber</th>
                                                <th className="py-3 text-center">Estado</th>
                                                <th className="py-3 text-right">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border/50">
                                            {asientosFiltrados.map((asiento) => (
                                                <tr key={asiento.id} className="group transition-colors hover:bg-muted/30">
                                                    <td className="py-3 font-mono text-sm">#{asiento.numero}</td>
                                                    <td className="py-3 text-sm font-medium">{asiento.descripcion}</td>
                                                    <td className="py-3 text-sm text-muted-foreground">{formatDateCLP(asiento.fecha)}</td>
                                                    <td className="py-3 text-right text-sm">{formatCurrency(asiento.total_debe)}</td>
                                                    <td className="py-3 text-right text-sm">{formatCurrency(asiento.total_haber)}</td>
                                                    <td className="py-3 text-center">
                                                        <Badge variant={asiento.estado ? 'default' : 'secondary'} className="text-xs">
                                                            {asiento.estado ? 'Activo' : 'Inactivo'}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => { setViendo(asiento); setIsViewOpen(true); }}>
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            {canEdit && (
                                                                <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => handleEdit(asiento)}>
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button variant="ghost" size="icon" className="h-8 w-8 text-red-500 hover:text-red-600" onClick={() => handleDelete(asiento.id)}>
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
                                <div className="space-y-4">
                                    {asientosFiltrados.map((asiento) => (
                                        <div
                                            key={asiento.id}
                                            className="rounded-lg border p-4"
                                        >
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <div className="flex items-center gap-4">
                                                    <div>
                                                        <span className="font-mono text-sm">
                                                            #{asiento.numero}
                                                        </span>
                                                        <p className="font-medium">
                                                            {
                                                                asiento.descripcion
                                                            }
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {formatDateCLP(
                                                                asiento.fecha,
                                                            )}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2 sm:gap-4">
                                                    <div className="text-right">
                                                        <p className="text-xs text-muted-foreground sm:text-sm">
                                                            Debe
                                                        </p>
                                                        <p className="text-sm font-medium">
                                                            {formatCurrency(
                                                                asiento.total_debe,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="text-xs text-muted-foreground sm:text-sm">
                                                            Haber
                                                        </p>
                                                        <p className="text-sm font-medium">
                                                            {formatCurrency(
                                                                asiento.total_haber,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <Badge
                                                        variant={
                                                            asiento.estado
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                        className="text-xs"
                                                    >
                                                        {asiento.estado
                                                            ? 'Activo'
                                                            : 'Inactivo'}
                                                    </Badge>
                                                    <div className="flex gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                            onClick={() => {
                                                                setViendo(
                                                                    asiento,
                                                                );
                                                                setIsViewOpen(
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
                                                                className="h-8 w-8"
                                                                onClick={() =>
                                                                    handleEdit(
                                                                        asiento,
                                                                    )
                                                                }
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 text-red-500 hover:text-red-600"
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        asiento.id,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            {asiento.detalles &&
                                                asiento.detalles.length > 0 && (
                                                    <div className="mt-4 overflow-x-auto border-t pt-4">
                                                        <table className="w-full min-w-[400px] text-sm">
                                                            <thead>
                                                                <tr className="text-muted-foreground">
                                                                    <th className="py-1 text-left">
                                                                        Código
                                                                    </th>
                                                                    <th className="py-1 text-left">
                                                                        Cuenta
                                                                    </th>
                                                                    <th className="py-1 text-right">
                                                                        Debe
                                                                    </th>
                                                                    <th className="py-1 text-right">
                                                                        Haber
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {asiento.detalles.map(
                                                                    (
                                                                        detalle,
                                                                        idx,
                                                                    ) => (
                                                                        <tr
                                                                            key={
                                                                                idx
                                                                            }
                                                                            className="border-t"
                                                                        >
                                                                            <td className="py-1 font-mono">
                                                                                {
                                                                                    detalle.cuenta_codigo
                                                                                }
                                                                            </td>
                                                                            <td className="py-1">
                                                                                {
                                                                                    detalle.cuenta
                                                                                }
                                                                            </td>
                                                                            <td className="py-1 text-right">
                                                                                {formatCurrency(
                                                                                    detalle.debe,
                                                                                )}
                                                                            </td>
                                                                            <td className="py-1 text-right">
                                                                                {formatCurrency(
                                                                                    detalle.haber,
                                                                                )}
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                )}
                                        </div>
                                    ))}
                                </div>
                            )}
                            <div className="mt-4">
                                <Pagination
                                    links={asientos.links}
                                    meta={asientos.meta || asientos}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Dialog
                    open={isOpen}
                    onOpenChange={(open) => {
                        setIsOpen(open);
                        if (!open) setEditando(null);
                    }}
                >
                    <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-h-[85vh] sm:max-w-2xl md:max-w-3xl">
                        <DialogHeader className="bg-gradient-to-r from-blue-600 to-indigo-700 px-4 py-3 text-white md:px-6 md:py-4">
                            <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                                {editando
                                    ? 'Editar Asiento'
                                    : 'Nuevo Asiento Contable'}
                            </DialogTitle>
                            <DialogDescription className="text-xs text-blue-100">
                                {editando
                                    ? 'Modifique los datos del asiento'
                                    : 'Ingrese los datos del asiento'}
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            onSubmit={handleSubmit}
                            className="px-4 py-4 md:px-6"
                        >
                            <div className="space-y-4">
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="numero"
                                            className="text-xs font-bold uppercase"
                                        >
                                            Número
                                        </Label>
                                        <Input
                                            id="numero"
                                            value={data.numero}
                                            onChange={(e) =>
                                                setData(
                                                    'numero',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="0001"
                                            required
                                            className="h-9"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="fecha"
                                            className="text-xs font-bold uppercase"
                                        >
                                            Fecha
                                        </Label>
                                        <Input
                                            id="fecha"
                                            type="date"
                                            value={data.fecha}
                                            onChange={(e) =>
                                                setData('fecha', e.target.value)
                                            }
                                            required
                                            className="h-9"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="descripcion"
                                        className="text-xs font-bold uppercase"
                                    >
                                        Descripción
                                    </Label>
                                    <Input
                                        id="descripcion"
                                        value={data.descripcion}
                                        onChange={(e) =>
                                            setData(
                                                'descripcion',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Descripción del asiento"
                                        required
                                        className="h-9"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="tipo"
                                        className="text-xs font-bold uppercase"
                                    >
                                        Tipo
                                    </Label>
                                    <select
                                        id="tipo"
                                        value={data.tipo}
                                        onChange={(e) =>
                                            setData('tipo', e.target.value)
                                        }
                                        className="flex h-9 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                    >
                                        <option value="diario">Diario</option>
                                        <option value="apertura">
                                            Apertura
                                        </option>
                                        <option value="cierre">Cierre</option>
                                    </select>
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <Label className="text-xs font-bold uppercase">
                                            Detalles
                                        </Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleAddDetalle}
                                            className="h-7 text-xs"
                                        >
                                            <Plus className="mr-1 h-3 w-3" />{' '}
                                            Agregar
                                        </Button>
                                    </div>
                                    <div className="max-h-48 space-y-2 overflow-y-auto">
                                        {data.detalles.map((detalle, index) => (
                                            <div
                                                key={index}
                                                className="flex flex-col gap-1.5 sm:flex-row sm:items-start"
                                            >
                                                <Input
                                                    placeholder="Código"
                                                    value={
                                                        detalle.cuenta_codigo
                                                    }
                                                    onChange={(e) =>
                                                        handleDetalleChange(
                                                            index,
                                                            'cuenta_codigo',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-8 text-xs"
                                                    required
                                                />
                                                <Input
                                                    placeholder="Cuenta"
                                                    value={detalle.cuenta}
                                                    onChange={(e) =>
                                                        handleDetalleChange(
                                                            index,
                                                            'cuenta',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-8 flex-1 text-xs"
                                                    required
                                                />
                                                <div className="flex gap-1">
                                                    <Input
                                                        type="number"
                                                        placeholder="Debe"
                                                        value={
                                                            detalle.debe || ''
                                                        }
                                                        onChange={(e) =>
                                                            handleDetalleChange(
                                                                index,
                                                                'debe',
                                                                parseFloat(
                                                                    e.target
                                                                        .value,
                                                                ) || 0,
                                                            )
                                                        }
                                                        className="h-8 w-full text-xs"
                                                        step="1"
                                                        min="0"
                                                        required
                                                    />
                                                    <Input
                                                        type="number"
                                                        placeholder="Haber"
                                                        value={
                                                            detalle.haber || ''
                                                        }
                                                        onChange={(e) =>
                                                            handleDetalleChange(
                                                                index,
                                                                'haber',
                                                                parseFloat(
                                                                    e.target
                                                                        .value,
                                                                ) || 0,
                                                            )
                                                        }
                                                        className="h-8 w-full text-xs"
                                                        step="1"
                                                        min="0"
                                                        required
                                                    />
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 shrink-0"
                                                    onClick={() =>
                                                        handleRemoveDetalle(
                                                            index,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="flex flex-col gap-2 border-t pt-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-center gap-1.5">
                                            <Calculator className="h-3 w-3" />
                                            <span className="text-xs font-bold">
                                                Totales:
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap gap-2 sm:gap-3">
                                            <span
                                                className={`text-xs font-bold ${isBalanced ? 'text-green-600' : 'text-red-600'}`}
                                            >
                                                Debe:{' '}
                                                {formatCurrency(totalDebe)}
                                            </span>
                                            <span
                                                className={`text-xs font-bold ${isBalanced ? 'text-green-600' : 'text-red-600'}`}
                                            >
                                                Haber:{' '}
                                                {formatCurrency(totalHaber)}
                                            </span>
                                            {isBalanced && (
                                                <Badge
                                                    variant="default"
                                                    className="text-[10px]"
                                                >
                                                    <Check className="mr-1 h-2 w-2" />{' '}
                                                    OK
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
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

                {/* View Modal */}
                <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
                    <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-h-[85vh] md:max-w-2xl">
                        <DialogHeader className="bg-gradient-to-r from-blue-600 to-indigo-700 px-4 py-3 text-white md:px-6 md:py-4">
                            <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                                Detalle de Asiento
                            </DialogTitle>
                        </DialogHeader>
                        {viendo && (
                            <div className="px-4 py-4 md:px-6">
                                <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1">
                                        <span className="text-[10px] font-bold text-muted-foreground uppercase">
                                            Número
                                        </span>
                                        <p className="font-mono font-bold">
                                            #{viendo.numero}
                                        </p>
                                    </div>
                                    <div className="space-y-1">
                                        <span className="text-[10px] font-bold text-muted-foreground uppercase">
                                            Fecha
                                        </span>
                                        <p className="font-bold">
                                            {formatDateCLP(viendo.fecha)}
                                        </p>
                                    </div>
                                    <div className="col-span-1 space-y-1 sm:col-span-2">
                                        <span className="text-[10px] font-bold text-muted-foreground uppercase">
                                            Descripción
                                        </span>
                                        <p className="font-medium">
                                            {viendo.descripcion}
                                        </p>
                                    </div>
                                    <div className="space-y-1">
                                        <span className="text-[10px] font-bold text-muted-foreground uppercase">
                                            Tipo
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="capitalize"
                                        >
                                            {viendo.tipo}
                                        </Badge>
                                    </div>
                                    <div className="space-y-1">
                                        <span className="text-[10px] font-bold text-muted-foreground uppercase">
                                            Estado
                                        </span>
                                        <Badge
                                            variant={
                                                viendo.estado
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {viendo.estado
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
                                    </div>
                                </div>

                                {viendo.detalles &&
                                    viendo.detalles.length > 0 && (
                                        <div className="space-y-2">
                                            <span className="text-[10px] font-bold text-muted-foreground uppercase">
                                                Detalles
                                            </span>
                                            <div className="overflow-x-auto rounded-lg border">
                                                <table className="w-full min-w-[350px] text-xs">
                                                    <thead className="bg-muted/50">
                                                        <tr>
                                                            <th className="px-2 py-1.5 text-left font-bold">
                                                                Código
                                                            </th>
                                                            <th className="px-2 py-1.5 text-left font-bold">
                                                                Cuenta
                                                            </th>
                                                            <th className="px-2 py-1.5 text-right font-bold">
                                                                Debe
                                                            </th>
                                                            <th className="px-2 py-1.5 text-right font-bold">
                                                                Haber
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {viendo.detalles.map(
                                                            (detalle, idx) => (
                                                                <tr
                                                                    key={idx}
                                                                    className="border-t"
                                                                >
                                                                    <td className="px-2 py-1.5 font-mono">
                                                                        {
                                                                            detalle.cuenta_codigo
                                                                        }
                                                                    </td>
                                                                    <td className="px-2 py-1.5">
                                                                        {
                                                                            detalle.cuenta
                                                                        }
                                                                    </td>
                                                                    <td className="px-2 py-1.5 text-right">
                                                                        {detalle.debe >
                                                                        0
                                                                            ? formatCurrency(
                                                                                  detalle.debe,
                                                                              )
                                                                            : '-'}
                                                                    </td>
                                                                    <td className="px-2 py-1.5 text-right">
                                                                        {detalle.haber >
                                                                        0
                                                                            ? formatCurrency(
                                                                                  detalle.haber,
                                                                              )
                                                                            : '-'}
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                    <tfoot className="bg-muted/30 font-bold">
                                                        <tr>
                                                            <td
                                                                colSpan={2}
                                                                className="px-2 py-1.5 text-right"
                                                            >
                                                                Totales:
                                                            </td>
                                                            <td className="px-2 py-1.5 text-right">
                                                                {formatCurrency(
                                                                    viendo.total_debe,
                                                                )}
                                                            </td>
                                                            <td className="px-2 py-1.5 text-right">
                                                                {formatCurrency(
                                                                    viendo.total_haber,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    )}
                            </div>
                        )}
                        <DialogFooter className="border-t px-4 py-3">
                            <Button
                                variant="outline"
                                onClick={() => setIsViewOpen(false)}
                                className="rounded-full px-6"
                            >
                                Cerrar
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </AppLayout>
        </>
    );
}
