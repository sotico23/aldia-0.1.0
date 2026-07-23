import { Head, useForm, router } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    X
} from 'lucide-react';
import { useState, useMemo } from 'react';
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

interface Hito {
    id: number;
    proyecto_id: number | null;
    nombre: string;
    descripcion: string | null;
    fecha_prevista: string | null;
    fecha_real: string | null;
    estado: string;
    responsable_id: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Hitos', href: '/hitos' },
];

const estados = [
    'pendiente',
    'en_progreso',
    'completado',
    'atrasado',
    'cancelado',
];

export default function Index({
    hitos,
}: {
    hitos: {
        data: Hito[];
        links: any[];
        from?: number;
        to?: number;
        total?: number;
        meta?: any;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('proyectos.hitos.create');
    const canEdit = hasPermission('proyectos.hitos.edit');
    const canDelete = hasPermission('proyectos.hitos.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Hito | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        proyecto_id: '' as string,
        nombre: '',
        descripcion: '',
        fecha_prevista: '',
        fecha_real: '',
        estado: 'pendiente',
        responsable_id: '' as string,
    });

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });

    const hitosFiltrados = useMemo(() => {
        return hitos.data.filter((h) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !h.nombre.toLowerCase().includes(busca) &&
                    !(h.descripcion || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && h.estado !== filtros.estado) return false;

            return true;
        });
    }, [hitos.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/hitos/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/hitos', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (h: Hito) => {
        setEditando(h);
        setData({
            proyecto_id: String(h.proyecto_id || ''),
            nombre: h.nombre,
            descripcion: h.descripcion || '',
            fecha_prevista: h.fecha_prevista || '',
            fecha_real: h.fecha_real || '',
            estado: h.estado,
            responsable_id: String(h.responsable_id || ''),
        });
        setIsOpen(true);
    };
    const handleNew = () => {
        reset();
        setData({
            proyecto_id: '',
            nombre: '',
            descripcion: '',
            fecha_prevista: '',
            fecha_real: '',
            estado: 'pendiente',
            responsable_id: '',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/hitos/${id}`);
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleExportCsv = () => {
        window.location.href = '/hitos/export';
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleExportExcel = () => {
        window.location.href = '/hitos/export-excel';
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleImportCsv = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('archivo', file);
        router.post('/hitos/import', formData, {
            onSuccess: () => alert('Importación completada'),
            onError: (err) => alert('Error: ' + Object.values(err)[0]),
        });
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleImportExcel = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('archivo', file);
        router.post('/hitos/import-excel', formData, {
            onSuccess: () => alert('Importación completada'),
            onError: (err) => alert('Error: ' + Object.values(err)[0]),
        });
    };

    const getEstadoBadge = (estado: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            en_progreso: 'bg-blue-500',
            completado: 'bg-green-500',
            atrasado: 'bg-red-500',
            cancelado: 'bg-gray-500',
        };
        return (
            <Badge className={colores[estado] || 'bg-gray-500'}>{estado}</Badge>
        );
    };

    return (
        <>
            <Head title="Hitos" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 px-4 sm:px-6 lg:px-8 py-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Hitos</h1>
                            <p className="text-muted-foreground">
                                Gestión de hitos
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canCreate && (
                                <Button onClick={handleNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                </Button>
                            )}
                            <BulkActions
                                baseUrl="/hitos"
                                filters={{}}
                                modelName="Hitos"
                            />
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Hitos</CardTitle>
                                    <CardDescription>
                                        {hitosFiltrados.length} registros encontrados
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
                                        placeholder="Buscar por nombre o descripción..."
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
                                            {e.toUpperCase().replace('_', ' ')}
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
                                            <th className="py-2 text-left font-medium">
                                                Nombre / Descripción
                                            </th>
                                            <th className="py-2 text-left font-medium">
                                                Fechas (Prev. / Real)
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
                                        {hitosFiltrados.map((h) => (
                                            <tr
                                                key={h.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-2">
                                                    <div className="font-medium">
                                                        {h.nombre}
                                                    </div>
                                                    <div className="max-w-[200px] truncate text-[10px] text-muted-foreground">
                                                        {h.descripcion || '-'}
                                                    </div>
                                                </td>
                                                <td className="py-2">
                                                    <div className="text-[10px]">
                                                        P:{' '}
                                                        {h.fecha_prevista
                                                            ? new Date(
                                                                  h.fecha_prevista,
                                                              ).toLocaleDateString()
                                                            : '-'}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        R:{' '}
                                                        {h.fecha_real
                                                            ? new Date(
                                                                  h.fecha_real,
                                                              ).toLocaleDateString()
                                                            : '-'}
                                                    </div>
                                                </td>
                                                <td className="py-2 text-center">
                                                    {getEstadoBadge(h.estado)}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0"
                                                                onClick={() =>
                                                                    handleEdit(h)
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
                                                                        h.id,
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
                                        {hitosFiltrados.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron hitos con
                                                    los filtros aplicados
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {hitosFiltrados.length === 0 ? (
                                    <div className="col-span-full py-8 text-center text-muted-foreground">
                                        No se encontraron hitos con los filtros aplicados
                                    </div>
                                ) : (
                                    hitosFiltrados.map((h) => (
                                        <Card key={h.id} className="flex flex-col">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-start justify-between">
                                                    <CardTitle className="text-sm">{h.nombre}</CardTitle>
                                                    {getEstadoBadge(h.estado)}
                                                </div>
                                                {h.descripcion && (
                                                    <CardDescription className="text-xs line-clamp-2">
                                                        {h.descripcion}
                                                    </CardDescription>
                                                )}
                                            </CardHeader>
                                            <CardContent className="flex-1">
                                                <div className="space-y-1 text-xs">
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Prevista:</span>
                                                        <span>{h.fecha_prevista ? new Date(h.fecha_prevista).toLocaleDateString() : '-'}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Real:</span>
                                                        <span>{h.fecha_real ? new Date(h.fecha_real).toLocaleDateString() : '-'}</span>
                                                    </div>
                                                </div>
                                            </CardContent>
                                            <div className="flex items-center justify-end gap-1 border-t p-3">
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(h)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive" onClick={() => handleDelete(h.id)}>
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
                                links={hitos.links}
                                meta={hitos.meta || hitos}
                            />
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="mx-4 sm:mx-auto w-full max-w-full sm:max-w-lg md:max-w-2xl p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nuevo'} Hito
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="max-h-[85vh] overflow-y-auto">
                        <div className="grid gap-4 py-4">
                            <div className="space-y-2">
                                <Label>Nombre *</Label>
                                <Input
                                    value={data.nombre}
                                    onChange={(e) =>
                                        setData('nombre', e.target.value)
                                    }
                                    required
                                />
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
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Fecha Prevista</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_prevista}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_prevista',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Fecha Real</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_real}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_real',
                                                e.target.value,
                                            )
                                        }
                                    />
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
