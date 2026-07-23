import { Head, useForm } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    X
} from 'lucide-react';
import { useState } from 'react';
import { useMemo } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateCLP, getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Gasto {
    id: number;
    proyecto_id: number | null;
    categoria: string | null;
    descripcion: string | null;
    monto: number;
    fecha: string | null;
    referencia: string | null;
    aprobado: boolean;
    aprobador_id: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Gastos de Proyecto', href: '/gasto-proyecto' },
];

const categorias = ['materiales', 'mano_obra', 'equipos', 'transporte', 'otro'];

export default function Index({
    gastos,
}: {
    gastos: {
        data: Gasto[];
        links: any[];
        meta?: any;
        total: number;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('proyectos.gastos.create');
    const canEdit = hasPermission('proyectos.gastos.edit');
    const canDelete = hasPermission('proyectos.gastos.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Gasto | null>(null);
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        proyecto_id: '' as string,
        categoria: 'otro',
        descripcion: '',
        monto: 0,
        fecha: '',
        referencia: '',
        aprobado: false,
        aprobador_id: '' as string,
    });

    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [filtros, setFiltros] = useState({
        busqueda: '',
        categoria: '',
        aprobado: '', // '1' para Sí, '0' para No, '' para Todos
        fechaDesde: '',
        fechaHasta: '',
    });

    const gastosFiltrados = useMemo(() => {
        return gastos.data.filter((g) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !(g.referencia || '').toLowerCase().includes(busca) &&
                    !(g.descripcion || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.categoria && g.categoria !== filtros.categoria)
                return false;

            if (filtros.aprobado !== '') {
                const isAprobado = filtros.aprobado === '1';
                if (g.aprobado !== isAprobado) return false;
            }

            if (filtros.fechaDesde && g.fecha && g.fecha < filtros.fechaDesde)
                return false;
            if (filtros.fechaHasta && g.fecha && g.fecha > filtros.fechaHasta)
                return false;

            return true;
        });
    }, [gastos, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            categoria: '',
            aprobado: '',
            fechaDesde: '',
            fechaHasta: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/gasto-proyecto/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/gasto-proyecto', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (g: Gasto) => {
        setEditando(g);
        setData({
            proyecto_id: String(g.proyecto_id || ''),
            categoria: g.categoria || 'otro',
            descripcion: g.descripcion || '',
            monto: g.monto,
            fecha: g.fecha || '',
            referencia: g.referencia || '',
            aprobado: g.aprobado,
            aprobador_id: String(g.aprobador_id || ''),
        });
        setIsOpen(true);
    };
    const handleNew = () => {
        reset();
        setData({
            proyecto_id: '',
            categoria: 'otro',
            descripcion: '',
            monto: 0,
            fecha: getLocalDateString(),
            referencia: '',
            aprobado: false,
            aprobador_id: '',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/gasto-proyecto/${id}`);
    };

    return (
        <>
            <Head title="Gastos de Proyecto" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 px-4 sm:px-6 lg:px-8 py-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Gastos de Proyecto
                            </h1>
                            <p className="text-muted-foreground">
                                Gestión de gastos
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canCreate && (
                                <Button onClick={handleNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                </Button>
                            )}
                            <BulkActions
                                baseUrl="/gastos-proyecto"
                                filters={{}}
                                modelName="Gastos"
                            />
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Gastos</CardTitle>
                                    <CardDescription>
                                        {gastos.total} registros encontrados
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
                            <div className="mb-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Buscar por referencia o descripción..."
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
                                <select
                                    value={filtros.categoria}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            categoria: e.target.value,
                                        })
                                    }
                                    className="flex h-9 w-full rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">
                                        Todas las categorías
                                    </option>
                                    {categorias.map((c) => (
                                        <option key={c} value={c}>
                                            {c.charAt(0).toUpperCase() +
                                                c.slice(1).replace('_', ' ')}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    value={filtros.aprobado}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            aprobado: e.target.value,
                                        })
                                    }
                                    className="flex h-9 w-full rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Aprobación (Todos)</option>
                                    <option value="1">Aprobados</option>
                                    <option value="0">Pendientes</option>
                                </select>
                                <div className="flex items-center gap-2">
                                    <Input
                                        type="date"
                                        value={filtros.fechaDesde}
                                        onChange={(e) =>
                                            setFiltros({
                                                ...filtros,
                                                fechaDesde: e.target.value,
                                            })
                                        }
                                        className="h-9 w-full sm:w-[130px]"
                                    />
                                    <span className="text-muted-foreground">
                                        -
                                    </span>
                                    <Input
                                        type="date"
                                        value={filtros.fechaHasta}
                                        onChange={(e) =>
                                            setFiltros({
                                                ...filtros,
                                                fechaHasta: e.target.value,
                                            })
                                        }
                                        className="h-9 w-full sm:w-[130px]"
                                    />
                                </div>
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
                            <div className="w-full overflow-x-auto rounded-lg border border-gray-100">
                                <table className="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="py-2 text-left font-medium">
                                                Categoría / Ref.
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Monto
                                            </th>
                                            <th className="py-2 text-left font-medium">
                                                Fecha
                                            </th>
                                            <th className="py-2 text-center font-medium">
                                                Aprobado
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {gastosFiltrados.map((g) => (
                                            <tr
                                                key={g.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-2">
                                                    <div className="text-[10px] font-medium whitespace-nowrap uppercase">
                                                        {g.categoria?.replace(
                                                            '_',
                                                            ' ',
                                                        ) || '-'}
                                                    </div>
                                                    <div className="max-w-[150px] truncate text-[10px] text-muted-foreground">
                                                        {g.referencia || ''}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2 text-right font-bold text-green-600">
                                                    {formatCurrency(g.monto)}
                                                </td>
                                                <td className="py-2 whitespace-nowrap text-muted-foreground">
                                                    {formatDateCLP(g.fecha)}
                                                </td>
                                                <td className="py-2 text-center">
                                                    <Badge
                                                        variant={
                                                            g.aprobado
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                        className={
                                                            g.aprobado
                                                                ? 'bg-green-500 hover:bg-green-600'
                                                                : 'bg-yellow-500 hover:bg-yellow-600'
                                                        }
                                                    >
                                                        {g.aprobado
                                                            ? 'Aprobado'
                                                            : 'Pendiente'}
                                                    </Badge>
                                                </td>
                                                <td className="py-2 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0"
                                                                onClick={() =>
                                                                    handleEdit(g)
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
                                                                        g.id,
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
                                        {gastosFiltrados.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron gastos con
                                                    los filtros aplicados
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {gastosFiltrados.length === 0 ? (
                                    <div className="col-span-full py-8 text-center text-muted-foreground">
                                        No se encontraron gastos con los filtros aplicados
                                    </div>
                                ) : (
                                    gastosFiltrados.map((g) => (
                                        <Card key={g.id} className="flex flex-col">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-start justify-between">
                                                    <div>
                                                        <CardTitle className="text-sm uppercase">
                                                            {g.categoria?.replace('_', ' ') || '-'}
                                                        </CardTitle>
                                                        {g.referencia && (
                                                            <CardDescription className="text-xs">
                                                                {g.referencia}
                                                            </CardDescription>
                                                        )}
                                                    </div>
                                                    <Badge
                                                        variant={g.aprobado ? 'default' : 'secondary'}
                                                        className={g.aprobado ? 'bg-green-500' : 'bg-yellow-500'}
                                                    >
                                                        {g.aprobado ? 'Aprobado' : 'Pendiente'}
                                                    </Badge>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="flex-1">
                                                <div className="space-y-1 text-xs">
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Monto:</span>
                                                        <span className="font-bold text-green-600">{formatCurrency(g.monto)}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Fecha:</span>
                                                        <span>{formatDateCLP(g.fecha)}</span>
                                                    </div>
                                                    {g.descripcion && (
                                                        <div className="pt-1 text-muted-foreground line-clamp-2">
                                                            {g.descripcion}
                                                        </div>
                                                    )}
                                                </div>
                                            </CardContent>
                                            <div className="flex items-center justify-end gap-1 border-t p-3">
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(g)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive" onClick={() => handleDelete(g.id)}>
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
                                links={gastos.links}
                                meta={
                                    gastos.meta || {
                                        from: (gastos as any).from,
                                        to: (gastos as any).to,
                                        total: gastos.total,
                                    }
                                }
                            />
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="mx-4 sm:mx-auto w-full max-w-full sm:max-w-lg md:max-w-2xl p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nuevo'} Gasto
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="max-h-[85vh] overflow-y-auto">
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Monto *</Label>
                                    <Input
                                        type="number"
                                        step="1"
                                        value={data.monto}
                                        onChange={(e) =>
                                            setData(
                                                'monto',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Fecha</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha}
                                        onChange={(e) =>
                                            setData('fecha', e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Categoría</Label>
                                    <select
                                        value={data.categoria}
                                        onChange={(e) =>
                                            setData('categoria', e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        {categorias.map((c) => (
                                            <option key={c} value={c}>
                                                {c}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Referencia</Label>
                                    <Input
                                        value={data.referencia}
                                        onChange={(e) =>
                                            setData(
                                                'referencia',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Descripción</Label>
                                <Input
                                    value={data.descripcion}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
                                    }
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.aprobado}
                                    onChange={(e) =>
                                        setData('aprobado', e.target.checked)
                                    }
                                />
                                <Label>Aprobado</Label>
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
                            <Button type="submit">Guardar</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
