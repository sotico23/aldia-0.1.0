import { Head, useForm } from '@inertiajs/react';
import { LayoutGrid, List, Pencil, Plus, Trash2, Search, X } from 'lucide-react';
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
import type { BreadcrumbItem } from '@/types';

interface Orden {
    id: number;
    numero: string;
    producto: string | null;
    cantidad: number;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    progreso: number;
    estado: string;
    notas: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Órdenes de Producción', href: '/ordenes-produccion' },
];

const estados = ['pendiente', 'en_proceso', 'completado', 'cancelado'];


export default function Index({
    ordenes,
}: {
    ordenes: {
        data: Orden[];
        links: any[];
        meta: { from: number; to: number; total: number };
    };
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Orden | null>(null);
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('mrp.produccion.create');
    const canEdit = hasPermission('mrp.produccion.edit');
    const canDelete = hasPermission('mrp.produccion.delete');
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        numero: '',
        producto: '',
        cantidad: 1,
        fecha_inicio: '',
        fecha_fin: '',
        progreso: 0,
        estado: 'pendiente',
        notas: '',
    });

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const ordenesFiltradas = useMemo(() => {
        return ordenes.data.filter((o) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !o.numero.toLowerCase().includes(busca) &&
                    !(o.producto || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && o.estado !== filtros.estado) return false;

            return true;
        });
    }, [ordenes.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const generarNumero = () => `OP-${Date.now().toString().slice(-6)}`;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando)
            put(`/ordenes-produccion/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        else
            post('/ordenes-produccion', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
    };

    const handleEdit = (o: Orden) => {
        setEditando(o);
        setData({
            numero: o.numero,
            producto: o.producto || '',
            cantidad: o.cantidad,
            fecha_inicio: o.fecha_inicio || '',
            fecha_fin: o.fecha_fin || '',
            progreso: o.progreso,
            estado: o.estado,
            notas: o.notas || '',
        });
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setData({
            numero: generarNumero(),
            producto: '',
            cantidad: 1,
            fecha_inicio: '',
            fecha_fin: '',
            progreso: 0,
            estado: 'pendiente',
            notas: '',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/ordenes-produccion/${id}`);
    };

    const getEstadoBadge = (e: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            en_proceso: 'bg-blue-500',
            completado: 'bg-green-500',
            cancelado: 'bg-red-500',
        };
        return <Badge className={colores[e] || 'bg-gray-500'}>{e}</Badge>;
    };

    return (
        <>
            <Head title="Órdenes de Producción" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-xl font-bold sm:text-2xl">
                                Órdenes de Producción
                            </h1>
                            <p className="text-xs text-muted-foreground sm:text-sm">
                                Gestión de producción
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <BulkActions 
                                baseUrl="/ordenes-produccion" 
                                modelName="Órdenes"
                                filters={{
                                    search: filtros.busqueda,
                                    estado: filtros.estado
                                }}
                            />
                            {canCreate && (
                                <Button onClick={handleNew} className="w-full sm:w-auto">
                                    <Plus className="mr-2 h-4 w-4" /> Nueva Orden
                                </Button>
                            )}
                        </div>
                    </div>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between p-4 sm:p-6">
                            <div>
                                <CardTitle className="text-sm sm:text-base">Órdenes</CardTitle>
                                <CardDescription className="text-xs sm:text-sm">
                                    {ordenesFiltradas.length} órdenes encontradas
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-1 rounded-lg bg-muted/30 p-1">
                                <Button
                                    variant={viewMode === 'table' ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => setViewMode('table')}
                                    className="h-8 w-8 p-0"
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant={viewMode === 'cards' ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => setViewMode('cards')}
                                    className="h-8 w-8 p-0"
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar por número o producto..."
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
                                    value={filtros.estado}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            estado: e.target.value,
                                        })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-3 py-1 min-w-[150px]"
                                >
                                    <option value="">Todos los estados</option>
                                    {estados.map((est) => (
                                        <option key={est} value={est}>
                                            {est.replace('_', ' ').charAt(0).toUpperCase() + est.replace('_', ' ').slice(1)}
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
                            <>
                            <div className="hidden md:block">
                                <table className="w-full text-xs sm:text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="py-2 pr-2 text-left font-medium">Número</th>
                                            <th className="py-2 pr-2 text-left font-medium">Producto</th>
                                            <th className="py-2 pr-2 text-right font-medium">Cantidad</th>
                                            <th className="py-2 pr-2 text-center font-medium">Progreso</th>
                                            <th className="py-2 pr-2 text-center font-medium">Estado</th>
                                            <th className="py-2 text-right font-medium">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ordenesFiltradas.map((o) => (
                                            <tr
                                                key={o.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-2 pr-2 font-mono text-muted-foreground">
                                                    {o.numero}
                                                </td>
                                                <td className="py-2 pr-2 font-medium">
                                                    {o.producto || '-'}
                                                </td>
                                                <td className="py-2 pr-2 text-right">
                                                    {o.cantidad}
                                                </td>
                                                <td className="py-2 pr-2 text-center">
                                                    <div className="flex items-center justify-center gap-2">
                                                        <div className="h-2 w-12 rounded-full bg-muted overflow-hidden">
                                                            <div
                                                                className="h-full bg-primary"
                                                                style={{ width: `${o.progreso}%` }}
                                                            />
                                                        </div>
                                                        <span className="text-[10px] text-muted-foreground w-6">
                                                            {o.progreso}%
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="py-2 pr-2 text-center">
                                                    {getEstadoBadge(o.estado)}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0"
                                                                onClick={() => handleEdit(o)}
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                                                onClick={() => handleDelete(o.id)}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {ordenesFiltradas.length === 0 && (
                                            <tr>
                                                <td colSpan={6} className="py-8 text-center text-muted-foreground">
                                                    No se encontraron órdenes con los filtros aplicados
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex flex-col gap-3 md:hidden">
                                {ordenesFiltradas.length === 0 ? (
                                    <p className="py-8 text-center text-xs text-muted-foreground">
                                        No se encontraron órdenes con los filtros aplicados
                                    </p>
                                ) : (
                                    ordenesFiltradas.map((o) => (
                                        <div key={o.id} className="rounded-lg border bg-card p-3 text-xs shadow-sm">
                                            <div className="mb-2 flex items-center justify-between">
                                                <span className="font-mono font-semibold">{o.numero}</span>
                                                {getEstadoBadge(o.estado)}
                                            </div>
                                            <div className="space-y-1 text-muted-foreground">
                                                <div className="flex justify-between">
                                                    <span>Producto:</span>
                                                    <span className="font-medium text-foreground">{o.producto || '-'}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Cantidad:</span>
                                                    <span className="font-medium">{o.cantidad}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Progreso:</span>
                                                    <span className="flex items-center gap-1">
                                                        <div className="h-2 w-16 rounded-full bg-muted overflow-hidden">
                                                            <div className="h-full bg-primary" style={{ width: `${o.progreso}%` }} />
                                                        </div>
                                                        <span className="text-[10px]">{o.progreso}%</span>
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="mt-2 flex justify-end gap-1 border-t pt-2">
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-7 px-2 text-xs" onClick={() => handleEdit(o)}>
                                                        <Pencil className="mr-1 h-3 w-3" /> Editar
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-7 px-2 text-xs text-destructive hover:text-destructive" onClick={() => handleDelete(o.id)}>
                                                        <Trash2 className="mr-1 h-3 w-3" /> Eliminar
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                            </>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {ordenesFiltradas.map((o) => (
                                    <Card key={o.id}>
                                        <CardHeader className="pb-2">
                                            <div className="flex items-start justify-between">
                                                <CardTitle className="text-sm font-mono">{o.numero}</CardTitle>
                                                {getEstadoBadge(o.estado)}
                                            </div>
                                            <CardDescription className="truncate text-xs">{o.producto || '-'}</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-2 text-sm">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">Cantidad</span>
                                                    <span className="font-medium">{o.cantidad}</span>
                                                </div>
                                                <div>
                                                    <div className="mb-1 flex items-center justify-between">
                                                        <span className="text-muted-foreground">Progreso</span>
                                                        <span className="text-xs text-muted-foreground">{o.progreso}%</span>
                                                    </div>
                                                    <div className="h-2 w-full rounded-full bg-muted overflow-hidden">
                                                        <div className="h-full bg-primary" style={{ width: `${o.progreso}%` }} />
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="mt-3 flex justify-end gap-1 border-t pt-2">
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(o)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(o.id)}>
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                                {ordenesFiltradas.length === 0 && (
                                    <div className="col-span-full flex flex-col items-center justify-center py-12 text-center">
                                        <div className="mb-4 rounded-full bg-muted/30 p-4">
                                            <LayoutGrid className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                        <p className="text-muted-foreground">No se encontraron órdenes con los filtros aplicados</p>
                                    </div>
                                )}
                            </div>
                            )}
                            <Pagination
                                links={ordenes.links}
                                meta={ordenes.meta}
                            />
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="w-[95vw] max-w-lg overflow-y-auto p-3 sm:p-6" style={{ maxHeight: '85vh' }}>
                    <DialogHeader className="mb-2 sm:mb-4">
                        <DialogTitle className="text-base sm:text-lg">
                            {editando ? 'Editar' : 'Nueva'} Orden
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Número *</Label>
                                <Input
                                    value={data.numero}
                                    onChange={(e) => setData('numero', e.target.value)}
                                    required
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Producto</Label>
                                <div className="flex gap-2">
                                    <Input
                                        value={data.producto}
                                        onChange={(e) => setData('producto', e.target.value)}
                                        className="flex-1 h-10 text-xs sm:text-sm"
                                    />

                                </div>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Cantidad</Label>
                                <Input
                                    type="number"
                                    min="1"
                                    value={data.cantidad}
                                    onChange={(e) => setData('cantidad', parseInt(e.target.value))}
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Progreso %</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={data.progreso}
                                    onChange={(e) => setData('progreso', parseInt(e.target.value))}
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Fecha Inicio</Label>
                                <Input
                                    type="date"
                                    value={data.fecha_inicio}
                                    onChange={(e) => setData('fecha_inicio', e.target.value)}
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Fecha Fin</Label>
                                <Input
                                    type="date"
                                    value={data.fecha_fin}
                                    onChange={(e) => setData('fecha_fin', e.target.value)}
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5 sm:space-y-2">
                            <Label className="text-xs sm:text-sm">Estado</Label>
                            <select
                                value={data.estado}
                                onChange={(e) => setData('estado', e.target.value)}
                                className="flex h-10 w-full rounded-md border bg-background px-3 py-2 text-xs sm:text-sm"
                            >
                                {estados.map((e) => (
                                    <option key={e} value={e}>{e}</option>
                                ))}
                            </select>
                        </div>
                        <DialogFooter className="flex-col gap-2 sm:flex-row">
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)} className="w-full sm:w-auto">
                                Cancelar
                            </Button>
                            <Button type="submit" className="w-full sm:w-auto">Guardar</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
