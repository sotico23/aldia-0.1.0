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
import { getLocalDateString } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Timesheet {
    id: number;
    empleado_id: number | null;
    proyecto_id: number | null;
    fecha: string | null;
    horas: number;
    descripcion: string | null;
    tipo: string | null;
    estado: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Timesheets', href: '/timesheets' },
];

const estados = ['pendiente', 'aprobado', 'rechazado'];
const tipos = ['normal', 'extra', 'enfermedad', 'vacacion'];

export default function Index({
    timesheets,
}: {
    timesheets: {
        data: Timesheet[];
        links: any[];
        from?: number;
        to?: number;
        total?: number;
        meta?: any;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('proyectos.timesheets.create');
    const canEdit = hasPermission('proyectos.timesheets.edit');
    const canDelete = hasPermission('proyectos.timesheets.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Timesheet | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        empleado_id: '' as string,
        proyecto_id: '' as string,
        fecha: '',
        horas: 0,
        descripcion: '',
        tipo: 'normal',
        estado: 'pendiente',
    });

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });

    const timesheetsFiltrados = useMemo(() => {
        return timesheets.data.filter((t) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (!(t.descripcion || '').toLowerCase().includes(busca)) {
                    return false;
                }
            }
            if (filtros.estado && t.estado !== filtros.estado) return false;

            return true;
        });
    }, [timesheets.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/timesheets/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/timesheets', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (t: Timesheet) => {
        setEditando(t);
        setData({
            empleado_id: String(t.empleado_id || ''),
            proyecto_id: String(t.proyecto_id || ''),
            fecha: t.fecha || '',
            horas: t.horas,
            descripcion: t.descripcion || '',
            tipo: t.tipo || 'normal',
            estado: t.estado,
        });
        setIsOpen(true);
    };
    const handleNew = () => {
        reset();
        setData({
            empleado_id: '',
            proyecto_id: '',
            fecha: getLocalDateString(),
            horas: 0,
            descripcion: '',
            tipo: 'normal',
            estado: 'pendiente',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/timesheets/${id}`);
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleExportCsv = () => {
        window.location.href = '/timesheets/export';
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleExportExcel = () => {
        window.location.href = '/timesheets/export-excel';
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleImportCsv = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('archivo', file);
        router.post('/timesheets/import', formData, {
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
        router.post('/timesheets/import-excel', formData, {
            onSuccess: () => alert('Importación completada'),
            onError: (err) => alert('Error: ' + Object.values(err)[0]),
        });
    };

    const getEstadoBadge = (estado: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            aprobado: 'bg-green-500',
            rechazado: 'bg-red-500',
        };
        return (
            <Badge className={colores[estado] || 'bg-gray-500'}>{estado}</Badge>
        );
    };

    return (
        <>
            <Head title="Timesheets" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 px-4 sm:px-6 lg:px-8 py-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Timesheets</h1>
                            <p className="text-muted-foreground">
                                Registro de tiempo
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canCreate && (
                                <Button onClick={handleNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                </Button>
                            )}
                            <BulkActions
                                baseUrl="/timesheets"
                                filters={{}}
                                modelName="Timesheets"
                            />
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Timesheets</CardTitle>
                                    <CardDescription>
                                        {timesheetsFiltrados.length} registros
                                        encontrados
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button variant={viewMode === 'table' ? 'default' : 'outline'} size="icon" onClick={() => setViewMode('table')}>
                                        <List className="h-4 w-4" />
                                    </Button>
                                    <Button variant={viewMode === 'cards' ? 'default' : 'outline'} size="icon" onClick={() => setViewMode('cards')}>
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
                                        placeholder="Buscar por descripción..."
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
                                    className="flex h-9 w-full rounded-md border bg-background px-3 py-1"
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
                            <div className="w-full overflow-x-auto rounded-lg border border-gray-100">
                                <table className="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="py-2 text-left font-medium">
                                                Fecha / Tipo
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Horas
                                            </th>
                                            <th className="py-2 text-left font-medium">
                                                Descripción
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
                                        {timesheetsFiltrados.map((t) => (
                                            <tr
                                                key={t.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-2">
                                                    <div className="font-medium">
                                                        {t.fecha
                                                            ? new Date(
                                                                  t.fecha,
                                                              ).toLocaleDateString()
                                                            : '-'}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground uppercase">
                                                        {t.tipo || '-'}
                                                    </div>
                                                </td>
                                                <td className="py-2 text-right font-bold text-primary">
                                                    {t.horas}h
                                                </td>
                                                <td className="py-2">
                                                    <div className="max-w-[200px] truncate text-[10px]">
                                                        {t.descripcion || '-'}
                                                    </div>
                                                </td>
                                                <td className="py-2 text-center">
                                                    {getEstadoBadge(t.estado)}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0"
                                                                onClick={() =>
                                                                    handleEdit(t)
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
                                                                        t.id,
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
                                        {timesheetsFiltrados.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron registros
                                                    con los filtros aplicados
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                                <Pagination
                                    links={timesheets.links}
                                    meta={timesheets.meta || timesheets}
                                />
                            </div>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {timesheetsFiltrados.length === 0 ? (
                                    <div className="col-span-full flex flex-col items-center py-12 text-center text-muted-foreground">
                                        <Search className="mb-4 h-12 w-12 opacity-20" />
                                        <p className="font-medium">No se encontraron registros</p>
                                    </div>
                                ) : timesheetsFiltrados.map((t) => (
                                    <Card key={t.id} className="overflow-hidden">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-center justify-between">
                                                <Badge variant="outline" className="uppercase text-xs">{t.tipo || '-'}</Badge>
                                                {getEstadoBadge(t.estado)}
                                            </div>
                                            <CardTitle className="text-xl font-bold text-primary">
                                                {t.horas}h
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2 pt-0">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Fecha</span>
                                                <span className="font-medium">{t.fecha ? new Date(t.fecha).toLocaleDateString() : '-'}</span>
                                            </div>
                                            {t.descripcion && (
                                                <div className="text-sm text-muted-foreground">
                                                    <p className="line-clamp-2">{t.descripcion}</p>
                                                </div>
                                            )}
                                            <div className="flex justify-end gap-1 border-t pt-2">
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(t)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(t.id)}>
                                                        <Trash2 className="h-4 w-4" />
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
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="mx-4 sm:mx-auto w-full max-w-full sm:max-w-lg md:max-w-2xl p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nuevo'} Timesheet
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="max-h-[85vh] overflow-y-auto">
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                                    <Label>Horas *</Label>
                                    <Input
                                        type="number"
                                        step="1"
                                        value={data.horas}
                                        onChange={(e) =>
                                            setData(
                                                'horas',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        required
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                            <div className="space-y-2">
                                <Label>Descripción</Label>
                                <Input
                                    value={data.descripcion}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
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
