import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Search, X, LayoutGrid, List } from 'lucide-react';
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
import { usePermissions } from '@/hooks/use-permissions';
import { getLocalDateString } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Evaluacion {
    id: number;
    empleado_id: number | null;
    evaluador_id: number | null;
    fecha: string | null;
    periodo: string | null;
    puntuacion: number | null;
    comentarios: string | null;
    tipo: string | null;
    estado: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Evaluaciones', href: '/evaluaciones' },
];

const estados = ['pendiente', 'completada', 'cancelada'];
const tipos = ['desempeno', 'periodicidad', 'promocion', 'descargo'];

export default function Index({
    evaluaciones,
}: {
    evaluaciones: Evaluacion[];
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Evaluacion | null>(null);
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        empleado_id: '' as string,
        evaluador_id: '' as string,
        fecha: '',
        periodo: '',
        puntuacion: 0,
        comentarios: '',
        tipo: 'desempeno',
        estado: 'pendiente',
    });

    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('rrhh.evaluaciones.create');
    const canEdit = hasPermission('rrhh.evaluaciones.edit');
    const canDelete = hasPermission('rrhh.evaluaciones.delete');

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
        tipo: '',
    });
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const evaluacionesFiltradas = useMemo(() => {
        return evaluaciones.filter((e) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !(e.periodo || '').toLowerCase().includes(busca) &&
                    !(e.comentarios || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && e.estado !== filtros.estado) return false;
            if (filtros.tipo && e.tipo !== filtros.tipo) return false;

            return true;
        });
    }, [evaluaciones, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
            tipo: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/evaluaciones/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/evaluaciones', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (e: Evaluacion) => {
        setEditando(e);
        setData({
            empleado_id: String(e.empleado_id || ''),
            evaluador_id: String(e.evaluador_id || ''),
            fecha: e.fecha || '',
            periodo: e.periodo || '',
            puntuacion: e.puntuacion || 0,
            comentarios: e.comentarios || '',
            tipo: e.tipo || 'desempeno',
            estado: e.estado,
        });
        setIsOpen(true);
    };
    const handleNew = () => {
        reset();
        setData({
            empleado_id: '',
            evaluador_id: '',
            fecha: getLocalDateString(),
            periodo: '',
            puntuacion: 0,
            comentarios: '',
            tipo: 'desempeno',
            estado: 'pendiente',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/evaluaciones/${id}`);
    };
    const getEstadoBadge = (estado: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            completada: 'bg-green-500',
            cancelada: 'bg-gray-500',
        };
        return (
            <Badge className={colores[estado] || 'bg-gray-500'}>{estado}</Badge>
        );
    };

    return (
        <>
            <Head title="Evaluaciones" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 px-4 sm:px-6 lg:px-8 py-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Evaluaciones</h1>
                            <p className="text-muted-foreground">
                                Gestión de evaluaciones
                            </p>
                        </div>
                        <div className="flex gap-2 items-center">
                            <BulkActions
                                baseUrl="/evaluaciones"
                                modelName="Evaluaciones"
                            />
                            {canCreate && (
                                <Button onClick={handleNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nueva
                                </Button>
                            )}
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Evaluaciones</CardTitle>
                                    <CardDescription>
                                        {evaluacionesFiltradas.length} registros encontrados
                                    </CardDescription>
                                </div>
                                <div className="flex gap-1 rounded-lg border p-0.5">
                                    <button type="button" onClick={() => setViewMode('table')} className={`rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors ${viewMode === 'table' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tabla"><List className="h-4 w-4" /></button>
                                    <button type="button" onClick={() => setViewMode('cards')} className={`rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors ${viewMode === 'cards' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tarjetas"><LayoutGrid className="h-4 w-4" /></button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Buscar por período o comentarios..."
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
                                    value={filtros.tipo}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            tipo: e.target.value,
                                        })
                                    }
                                    className="flex h-9 w-full rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Todos los tipos</option>
                                    {tipos.map((t) => (
                                        <option key={t} value={t}>
                                            {t.charAt(0).toUpperCase() + t.slice(1)}
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
                                    className="flex h-9 w-full rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Todos los estados</option>
                                    {estados.map((est) => (
                                        <option key={est} value={est}>
                                            {est.charAt(0).toUpperCase() + est.slice(1)}
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
                                <div className="w-full overflow-x-auto rounded-lg border border-gray-100">
                                    <table className="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-2 text-left font-medium">Fecha</th>
                                                <th className="py-2 text-left font-medium">Período</th>
                                                <th className="py-2 text-right font-medium">Puntuación</th>
                                                <th className="py-2 text-left font-medium">Tipo</th>
                                                <th className="py-2 text-center font-medium">Estado</th>
                                                <th className="py-2 text-right font-medium">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {evaluacionesFiltradas.map((e) => (
                                                <tr key={e.id} className="border-b transition-colors hover:bg-muted/30">
                                                    <td className="py-2">
                                                        <div className="font-medium">{e.fecha ? new Date(e.fecha).toLocaleDateString() : '-'}</div>
                                                        <div className="text-[10px] text-muted-foreground truncate max-w-[150px]">{e.comentarios || ''}</div>
                                                    </td>
                                                    <td className="py-2 text-muted-foreground">{e.periodo || '-'}</td>
                                                    <td className="py-2 text-right font-medium">{e.puntuacion || 0}</td>
                                                    <td className="py-2"><Badge variant="outline" className="capitalize">{e.tipo || '-'}</Badge></td>
                                                    <td className="py-2 text-center">{getEstadoBadge(e.estado)}</td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            {canEdit && <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(e)}><Pencil className="h-4 w-4" /></Button>}
                                                            {canDelete && <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(e.id)}><Trash2 className="h-4 w-4" /></Button>}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                            {evaluacionesFiltradas.length === 0 && (
                                                <tr><td colSpan={6} className="py-8 text-center text-muted-foreground">No se encontraron evaluaciones con los filtros aplicados</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {evaluacionesFiltradas.map((e) => (
                                        <Card key={e.id} className="overflow-hidden border-muted/80 transition-all hover:shadow-md">
                                            <CardHeader className="pb-2">
                                                <div className="flex items-start justify-between">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                        <Pencil className="h-5 w-5 text-primary" />
                                                    </div>
                                                    {getEstadoBadge(e.estado)}
                                                </div>
                                                <div className="mt-2">
                                                    <p className="font-medium">{e.fecha ? new Date(e.fecha).toLocaleDateString() : 'Sin fecha'}</p>
                                                    <p className="text-xs text-muted-foreground">{e.periodo || 'Sin período'}</p>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="space-y-2 text-sm pb-3">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">Puntuación</span>
                                                    <span className="font-semibold">{e.puntuacion || 0}</span>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">Tipo</span>
                                                    <Badge variant="outline" className="capitalize">{e.tipo || '-'}</Badge>
                                                </div>
                                                {e.comentarios && (
                                                    <div className="pt-1 border-t text-xs text-muted-foreground line-clamp-2">{e.comentarios}</div>
                                                )}
                                            </CardContent>
                                            <div className="border-t bg-muted/20 p-3 flex justify-end gap-1">
                                                {canEdit && <Button variant="outline" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(e)} title="Editar"><Pencil className="h-4 w-4" /></Button>}
                                                {canDelete && <Button variant="outline" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(e.id)}><Trash2 className="h-4 w-4" /></Button>}
                                            </div>
                                        </Card>
                                    ))}
                                    {evaluacionesFiltradas.length === 0 && (
                                        <div className="col-span-full py-8 text-center text-muted-foreground">No se encontraron evaluaciones con los filtros aplicados</div>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="mx-4 sm:mx-auto w-full max-w-full sm:max-w-lg md:max-w-2xl p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nueva'} Evaluación
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="max-h-[85vh] overflow-y-auto">
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                <div className="space-y-2">
                                    <Label>Período</Label>
                                    <Input
                                        value={data.periodo}
                                        onChange={(e) =>
                                            setData('periodo', e.target.value)
                                        }
                                        placeholder="2024-Q1"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Puntuación</Label>
                                    <Input
                                        type="number"
                                        step="1"
                                        value={data.puntuacion}
                                        onChange={(e) =>
                                            setData(
                                                'puntuacion',
                                                parseInt(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Tipo</Label>
                                    <select
                                        value={data.tipo}
                                        onChange={(e) =>
                                            setData('tipo', e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        {tipos.map((t) => (
                                            <option key={t} value={t}>
                                                {t}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Estado</Label>
                                <select
                                    value={data.estado}
                                    onChange={(e) =>
                                        setData('estado', e.target.value)
                                    }
                                    className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                >
                                    {estados.map((e) => (
                                        <option key={e} value={e}>
                                            {e}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <Label>Comentarios</Label>
                                <Input
                                    value={data.comentarios}
                                    onChange={(e) =>
                                        setData('comentarios', e.target.value)
                                    }
                                />
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
