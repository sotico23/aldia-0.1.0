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
import { getLocalDateString } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Reclutamiento {
    id: number;
    puesto: string;
    candidato_id: number | null;
    fecha_postulacion: string | null;
    fecha_entrevista: string | null;
    estado: string;
    resultado: string | null;
    notas: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reclutamiento', href: '/reclutamiento' },
];

const estados = [
    'pendiente',
    'en_proceso',
    'entrevista',
    'contratado',
    'rechazado',
];
const resultados = ['aprobado', 'rechazado', 'en_espera', 'no_aplico'];

export default function Index({
    reclutamientos,
}: {
    reclutamientos: { data: Reclutamiento[]; links: any[]; from?: number; to?: number; total?: number; meta?: any };
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Reclutamiento | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        puesto: '',
        candidato_id: '' as string,
        fecha_postulacion: '',
        fecha_entrevista: '',
        estado: 'pendiente',
        resultado: '',
        notas: '',
    });

    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('rrhh.reclutamiento.create');
    const canEdit = hasPermission('rrhh.reclutamiento.edit');
    const canDelete = hasPermission('rrhh.reclutamiento.delete');

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });

    const reclutamientosFiltrados = useMemo(() => {
        return reclutamientos.data.filter((r) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !r.puesto.toLowerCase().includes(busca) &&
                    !(r.notas || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && r.estado !== filtros.estado) return false;

            return true;
        });
    }, [reclutamientos.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/reclutamiento/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/reclutamiento', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (r: Reclutamiento) => {
        setEditando(r);
        setData({
            puesto: r.puesto,
            candidato_id: String(r.candidato_id || ''),
            fecha_postulacion: r.fecha_postulacion || '',
            fecha_entrevista: r.fecha_entrevista || '',
            estado: r.estado,
            resultado: r.resultado || '',
            notas: r.notas || '',
        });
        setIsOpen(true);
    };
    const handleNew = () => {
        reset();
        setData({
            puesto: '',
            candidato_id: '',
            fecha_postulacion: getLocalDateString(),
            fecha_entrevista: '',
            estado: 'pendiente',
            resultado: '',
            notas: '',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/reclutamiento/${id}`);
    };
    const getEstadoBadge = (estado: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            en_proceso: 'bg-blue-500',
            entrevista: 'bg-purple-500',
            contratado: 'bg-green-500',
            rechazado: 'bg-red-500',
        };
        return (
            <Badge className={colores[estado] || 'bg-gray-500'}>{estado}</Badge>
        );
    };

    return (
        <>
            <Head title="Reclutamiento" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Reclutamiento
                            </h1>
                            <p className="text-muted-foreground">
                                Gestión de reclutamiento
                            </p>
                        </div>
                        <div className="flex gap-2 items-center">
                            <BulkActions
                                baseUrl="/reclutamiento"
                                modelName="Reclutamiento"
                            />
                            {canCreate && (
                                <Button onClick={handleNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                </Button>
                            )}
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Postulaciones</CardTitle>
                                    <CardDescription>
                                        {reclutamientosFiltrados.length} registros encontrados
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-1 rounded-lg border p-1">
                                    <Button
                                        variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('table')}
                                    >
                                        <List className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('cards')}
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
                                            placeholder="Buscar por puesto o notas..."
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
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs sm:text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-2 text-left font-medium">
                                                    Puesto
                                                </th>
                                                <th className="py-2 text-left font-medium">
                                                    Postulación
                                                </th>
                                                <th className="py-2 text-left font-medium">
                                                    Entrevista
                                                </th>
                                                <th className="py-2 text-center font-medium">
                                                    Estado
                                                </th>
                                                <th className="py-2 text-right font-medium">
                                                    Acciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {reclutamientosFiltrados.map((r) => (
                                                <tr
                                                    key={r.id}
                                                    className="border-b transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="py-2">
                                                        <div className="font-medium">
                                                            {r.puesto}
                                                        </div>
                                                        <div className="text-[10px] text-muted-foreground truncate max-w-[150px]">
                                                            {r.notas || ''}
                                                        </div>
                                                    </td>
                                                    <td className="py-2 text-muted-foreground">
                                                        {r.fecha_postulacion
                                                            ? new Date(
                                                                r.fecha_postulacion,
                                                            ).toLocaleDateString()
                                                            : '-'}
                                                    </td>
                                                    <td className="py-2 text-muted-foreground">
                                                        {r.fecha_entrevista
                                                            ? new Date(
                                                                r.fecha_entrevista,
                                                            ).toLocaleDateString()
                                                            : '-'}
                                                    </td>
                                                    <td className="py-2 text-center">
                                                        {getEstadoBadge(
                                                            r.estado,
                                                        )}
                                                    </td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            {canEdit && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 w-8 p-0"
                                                                    onClick={() =>
                                                                        handleEdit(r)
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
                                                                            r.id,
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
                                            {reclutamientosFiltrados.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={5}
                                                        className="py-8 text-center text-muted-foreground"
                                                    >
                                                        No se encontraron postulaciones con los filtros aplicados
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                    <Pagination links={reclutamientos.links} meta={reclutamientos.meta || reclutamientos} />
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {reclutamientosFiltrados.length === 0 ? (
                                        <div className="col-span-full flex flex-col items-center justify-center py-12 text-center text-muted-foreground">
                                            <Search className="mb-2 h-12 w-12 text-muted-foreground/50" />
                                            <p>No se encontraron postulaciones con los filtros aplicados</p>
                                        </div>
                                    ) : (
                                        reclutamientosFiltrados.map((r) => (
                                            <Card key={r.id} className="overflow-hidden">
                                                <CardContent className="p-4">
                                                    <div className="mb-3 flex items-start justify-between">
                                                        <div className="flex-1 min-w-0">
                                                            <h3 className="font-semibold truncate">{r.puesto}</h3>
                                                            {r.notas && (
                                                                <p className="mt-1 text-xs text-muted-foreground truncate">{r.notas}</p>
                                                            )}
                                                        </div>
                                                        {getEstadoBadge(r.estado)}
                                                    </div>
                                                    <div className="space-y-1 text-sm text-muted-foreground">
                                                        <div className="flex justify-between">
                                                            <span>Postulación</span>
                                                            <span>{r.fecha_postulacion ? new Date(r.fecha_postulacion).toLocaleDateString() : '-'}</span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span>Entrevista</span>
                                                            <span>{r.fecha_entrevista ? new Date(r.fecha_entrevista).toLocaleDateString() : '-'}</span>
                                                        </div>
                                                    </div>
                                                    <div className="mt-4 flex justify-end gap-1">
                                                        {canEdit && (
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(r)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(r.id)}>
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))
                                    )}
                                </div>
                            )}
                            <Pagination links={reclutamientos.links} meta={reclutamientos.meta || reclutamientos} />
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nueva'} Postulación
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="space-y-2">
                                <Label>Puesto *</Label>
                                <Input
                                    value={data.puesto}
                                    onChange={(e) =>
                                        setData('puesto', e.target.value)
                                    }
                                    required
                                />
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Fecha Postulación</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_postulacion}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_postulacion',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Fecha Entrevista</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_entrevista}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_entrevista',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                    <Label>Resultado</Label>
                                    <select
                                        value={data.resultado}
                                        onChange={(e) =>
                                            setData('resultado', e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        {resultados.map((r) => (
                                            <option key={r} value={r}>
                                                {r}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Notas</Label>
                                <Input
                                    value={data.notas}
                                    onChange={(e) =>
                                        setData('notas', e.target.value)
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
