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
import { formatDateCLP, getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Planificacion {
    id: number;
    titulo: string;
    descripcion: string | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    proyecto_id: number | null;
    responsable_id: number | null;
    estado: string;
    prioridad: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Planificación', href: '/planificacion' },
];

const estados = ['pendiente', 'en_progreso', 'completado', 'cancelado'];
const prioridades = ['baja', 'media', 'alta', 'urgente'];


export default function Index({
    planificaciones,
}: {
    planificaciones: {
        data: Planificacion[];
        links: any[];
        meta: { from: number; to: number; total: number };
    };
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Planificacion | null>(null);
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('mrp.planificacion.create');
    const canEdit = hasPermission('mrp.planificacion.edit');
    const canDelete = hasPermission('mrp.planificacion.delete');
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        titulo: '',
        descripcion: '',
        fecha_inicio: '',
        fecha_fin: '',
        proyecto_id: '' as string,
        responsable_id: '' as string,
        estado: 'pendiente',
        prioridad: 'media',
    });

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });

    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const planificacionesFiltradas = useMemo(() => {
        return planificaciones.data.filter((p) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !p.titulo.toLowerCase().includes(busca) &&
                    !(p.descripcion || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && p.estado !== filtros.estado) return false;

            return true;
        });
    }, [planificaciones.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/planificacion/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/planificacion', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (p: Planificacion) => {
        setEditando(p);
        setData({
            titulo: p.titulo,
            descripcion: p.descripcion || '',
            fecha_inicio: p.fecha_inicio || '',
            fecha_fin: p.fecha_fin || '',
            proyecto_id: String(p.proyecto_id || ''),
            responsable_id: String(p.responsable_id || ''),
            estado: p.estado,
            prioridad: p.prioridad || 'media',
        });
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setData({
            titulo: '',
            descripcion: '',
            fecha_inicio: getLocalDateString(),
            fecha_fin: '',
            proyecto_id: '',
            responsable_id: '',
            estado: 'pendiente',
            prioridad: 'media',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/planificacion/${id}`);
    };
    const getEstadoBadge = (estado: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            en_progreso: 'bg-blue-500',
            completado: 'bg-green-500',
            cancelado: 'bg-gray-500',
        };
        return (
            <Badge className={colores[estado] || 'bg-gray-500'}>{estado}</Badge>
        );
    };

    return (
        <>
            <Head title="Planificación" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-xl font-bold sm:text-2xl">
                                Planificación
                            </h1>
                            <p className="text-xs text-muted-foreground sm:text-sm">
                                Gestión de planificación
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <BulkActions 
                                baseUrl="/planificacion" 
                                modelName="Planificaciones"
                                filters={{
                                    search: filtros.busqueda,
                                    estado: filtros.estado
                                }}
                            />
                            {canCreate && (
                                <Button onClick={handleNew} className="w-full sm:w-auto">
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                </Button>
                            )}
                        </div>
                    </div>
                    <Card>
                        <CardHeader className="p-4 sm:p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-sm sm:text-base">Planificaciones</CardTitle>
                                    <CardDescription className="text-xs sm:text-sm">
                                        {planificacionesFiltradas.length} registros encontrados
                                    </CardDescription>
                                </div>
                                <div className="flex gap-1 rounded-lg bg-muted p-1">
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
                            </div>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar por título o descripción..."
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
                                    className="flex h-9 rounded-md border bg-background px-3 py-1 min-w-[150px]"
                                >
                                    <option value="">Todos los estados</option>
                                    {estados.map((e) => (
                                        <option key={e} value={e}>
                                            {e.replace('_', ' ').toUpperCase()}
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
                            <div className="hidden md:block">
                                {viewMode === 'table' ? (
                                    <table className="w-full text-xs sm:text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-2 pr-2 text-left font-medium">Título / Descripción</th>
                                                <th className="py-2 pr-2 text-left font-medium">Fechas</th>
                                                <th className="py-2 pr-2 text-center font-medium">Estado</th>
                                                <th className="py-2 text-right font-medium">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {planificacionesFiltradas.map((p) => (
                                                <tr key={p.id} className="border-b transition-colors hover:bg-muted/30">
                                                    <td className="py-2 pr-2">
                                                        <div className="font-medium">{p.titulo}</div>
                                                        <div className="text-[10px] text-muted-foreground truncate max-w-[200px]">{p.descripcion}</div>
                                                    </td>
                                                    <td className="py-2 pr-2">
                                                        <div className="text-[10px] font-medium">Inicio: {p.fecha_inicio ? formatDateCLP(p.fecha_inicio) : '-'}</div>
                                                        <div className="text-[10px] text-muted-foreground">Fin: {p.fecha_fin ? formatDateCLP(p.fecha_fin) : '-'}</div>
                                                    </td>
                                                    <td className="py-2 pr-2 text-center">{getEstadoBadge(p.estado)}</td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            {canEdit && (
                                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(p)}>
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(p.id)}>
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                            {planificacionesFiltradas.length === 0 && (
                                                <tr><td colSpan={4} className="py-8 text-center text-muted-foreground">No se encontraron planificaciones con los filtros aplicados</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                ) : (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {planificacionesFiltradas.map((p) => (
                                            <Card key={p.id} className="overflow-hidden">
                                                <CardHeader className="pb-3">
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                            <span className="text-sm font-bold text-primary">{p.titulo.charAt(0).toUpperCase()}</span>
                                                        </div>
                                                        {getEstadoBadge(p.estado)}
                                                    </div>
                                                    <CardTitle className="mt-2 text-sm">{p.titulo}</CardTitle>
                                                    {p.descripcion && (
                                                        <CardDescription className="line-clamp-2 text-xs">{p.descripcion}</CardDescription>
                                                    )}
                                                </CardHeader>
                                                <CardContent>
                                                    <div className="space-y-1 text-xs text-muted-foreground">
                                                        <div className="flex justify-between">
                                                            <span>Inicio:</span>
                                                            <span className="font-medium text-foreground">{p.fecha_inicio ? formatDateCLP(p.fecha_inicio) : '-'}</span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span>Fin:</span>
                                                            <span className="font-medium text-foreground">{p.fecha_fin ? formatDateCLP(p.fecha_fin) : '-'}</span>
                                                        </div>
                                                    </div>
                                                    <div className="mt-3 flex justify-end gap-1 border-t pt-2">
                                                        {canEdit && (
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(p)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(p.id)}>
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                        {planificacionesFiltradas.length === 0 && (
                                            <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                                <LayoutGrid className="mb-2 h-8 w-8" />
                                                <p className="text-sm">No se encontraron planificaciones con los filtros aplicados</p>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-col gap-3 md:hidden">
                                {planificacionesFiltradas.length === 0 ? (
                                    <p className="py-8 text-center text-xs text-muted-foreground">No se encontraron planificaciones con los filtros aplicados</p>
                                ) : (
                                    planificacionesFiltradas.map((p) => (
                                        <div key={p.id} className="rounded-lg border bg-card p-3 text-xs shadow-sm">
                                            <div className="mb-2 flex items-center justify-between">
                                                <span className="font-semibold">{p.titulo}</span>
                                                {getEstadoBadge(p.estado)}
                                            </div>
                                            <div className="space-y-1 text-muted-foreground">
                                                <div className="flex justify-between">
                                                    <span>Descripción:</span>
                                                    <span className="max-w-[50%] truncate text-foreground">{p.descripcion || '-'}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Inicio:</span>
                                                    <span>{p.fecha_inicio ? formatDateCLP(p.fecha_inicio) : '-'}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Fin:</span>
                                                    <span>{p.fecha_fin ? formatDateCLP(p.fecha_fin) : '-'}</span>
                                                </div>
                                            </div>
                                            <div className="mt-2 flex justify-end gap-1 border-t pt-2">
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-7 px-2 text-xs" onClick={() => handleEdit(p)}>
                                                        <Pencil className="mr-1 h-3 w-3" /> Editar
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-7 px-2 text-xs text-destructive hover:text-destructive" onClick={() => handleDelete(p.id)}>
                                                        <Trash2 className="mr-1 h-3 w-3" /> Eliminar
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                            <Pagination
                                links={planificaciones.links}
                                meta={planificaciones.meta}
                            />
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="w-[95vw] max-w-lg overflow-y-auto p-3 sm:p-6" style={{ maxHeight: '85vh' }}>
                    <DialogHeader className="mb-2 sm:mb-4">
                        <DialogTitle className="text-base sm:text-lg">
                            {editando ? 'Editar' : 'Nueva'} Planificación
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-1.5 sm:space-y-2">
                            <Label className="text-xs sm:text-sm">Título *</Label>
                            <Input
                                value={data.titulo}
                                onChange={(e) => setData('titulo', e.target.value)}
                                required
                                className="h-10 text-xs sm:text-sm"
                            />
                        </div>
                        <div className="space-y-1.5 sm:space-y-2">
                            <Label className="text-xs sm:text-sm">Descripción</Label>
                            <Input
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                className="h-10 text-xs sm:text-sm"
                            />
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
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Prioridad</Label>
                                <select
                                    value={data.prioridad}
                                    onChange={(e) => setData('prioridad', e.target.value)}
                                    className="flex h-10 w-full rounded-md border bg-background px-3 py-2 text-xs sm:text-sm"
                                >
                                    {prioridades.map((p) => (
                                        <option key={p} value={p}>{p}</option>
                                    ))}
                                </select>
                            </div>
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
