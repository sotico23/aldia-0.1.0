import { Head, usePage } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Plus,
    Trash2,
    CheckCircle2,
    Clock,
    AlertCircle,
    XCircle,
    Package,
    Scale,
    Droplets,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface User {
    id: number;
    name: string;
}

interface Tarea {
    id: number;
    titulo: string;
    descripcion: string | null;
    estado: string;
    prioridad: string;
    fecha_limite: string | null;
    usuario: User;
    empleado: User;
    productos_json: any[] | null;
    created_at: string;
}

interface Producto {
    id: number;
    nombre: string;
    unidad_medida: string;
    cantidad_medida: number | null;
    tipo_medida: string;
    medida_pesable: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tareas', href: '/tareas' },
];

const prioridadColors: Record<string, string> = {
    baja: 'bg-gray-100 text-gray-600',
    media: 'bg-blue-100 text-blue-600',
    alta: 'bg-orange-100 text-orange-600',
    urgente: 'bg-red-100 text-red-600',
};

const estadoIcons: Record<string, React.ElementType> = {
    pendiente: Clock,
    en_progreso: AlertCircle,
    completada: CheckCircle2,
    cancelada: XCircle,
};

const estadoColors: Record<string, string> = {
    pendiente: 'text-gray-500',
    en_progreso: 'text-blue-500',
    completada: 'text-green-500',
    cancelada: 'text-red-500',
};

export default function TareasIndex({
    tareas,
    empleados,
    productos,
}: {
    tareas: Tarea[];
    empleados: any[];
    productos: Producto[];
}) {
    const { auth } = usePage().props as any;
    const isEmpleado = auth?.user?.roles?.includes('Empleado');
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('general.tareas.create');
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const canEdit = hasPermission('general.tareas.edit');
    const canDelete = hasPermission('general.tareas.delete');
    const [isOpen, setIsOpen] = useState(false);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('cards');

    const {
        data,
        setData,
        post,
        delete: destroy,
        reset,
    } = useForm({
        empleado_id: '',
        titulo: '',
        descripcion: '',
        prioridad: 'media',
        fecha_limite: '',
        productos: [] as { producto_id: number; cantidad: number }[],
    });

    const [selectedProductoId, setSelectedProductoId] = useState<string>('');
    const [cantidadProducto, setCantidadProducto] = useState<number>(1);

    const agregarProducto = () => {
        if (!selectedProductoId) return;
        const prodId = parseInt(selectedProductoId);
        const yaExiste = data.productos.find((p) => p.producto_id === prodId);

        if (yaExiste) {
            setData(
                'productos',
                data.productos.map((p) =>
                    p.producto_id === prodId
                        ? { ...p, cantidad: p.cantidad + cantidadProducto }
                        : p,
                ),
            );
        } else {
            setData('productos', [
                ...data.productos,
                { producto_id: prodId, cantidad: cantidadProducto },
            ]);
        }
        setSelectedProductoId('');
        setCantidadProducto(1);
    };

    const removerProducto = (prodId: number) => {
        setData(
            'productos',
            data.productos.filter((p) => p.producto_id !== prodId),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/tareas', {
            onSuccess: () => {
                reset();
                setIsOpen(false);
            },
        });
    };

    const cambiarEstado = (tarea: Tarea, nuevoEstado: string) => {
        fetch(`/tareas/${tarea.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
            },
            body: JSON.stringify({ estado: nuevoEstado }),
        }).then(() => {
            window.location.reload();
        });
    };

    const eliminarTarea = (tarea: Tarea) => {
        if (confirm('¿Estás seguro de eliminar esta tarea?')) {
            destroy(`/tareas/${tarea.id}`);
        }
    };

    const getPrioridadLabel = (prioridad: string) => {
        const labels: Record<string, string> = {
            baja: 'Baja',
            media: 'Media',
            alta: 'Alta',
            urgente: 'Urgente',
        };
        return labels[prioridad] || prioridad;
    };

    const getEstadoLabel = (estado: string) => {
        const labels: Record<string, string> = {
            pendiente: 'Pendiente',
            en_progreso: 'En Progreso',
            completada: 'Completada',
            cancelada: 'Cancelada',
        };
        return labels[estado] || estado;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tareas" />
            <div className="space-y-6">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Tareas {isEmpleado ? 'Asignadas' : 'de Empleados'}
                            </h1>
                            <p className="text-muted-foreground">
                                {isEmpleado
                                    ? 'Tareas asignadas a ti'
                                    : 'Asigna tareas a tus empleados'}
                            </p>
                        </div>
                        {!isEmpleado && canCreate && (
                            <Button onClick={() => setIsOpen(!isOpen)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Nueva Tarea
                            </Button>
                        )}
                    </div>

                {isOpen && !isEmpleado && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Asignar Nueva Tarea</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Empleado</Label>
                                        <select
                                            value={data.empleado_id}
                                            onChange={(e) =>
                                                setData(
                                                    'empleado_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                            required
                                        >
                                            <option value="">
                                                Seleccionar empleado...
                                            </option>
                                            {empleados.map((emp: any) => (
                                                <option
                                                    key={emp.id}
                                                    value={emp.id}
                                                >
                                                    {emp.nombre}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Fecha Límite</Label>
                                        <Input
                                            type="date"
                                            value={data.fecha_limite}
                                            onChange={(e) =>
                                                setData(
                                                    'fecha_limite',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label>Título</Label>
                                    <Input
                                        value={data.titulo}
                                        onChange={(e) =>
                                            setData('titulo', e.target.value)
                                        }
                                        placeholder="Título de la tarea"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Descripción</Label>
                                    <Textarea
                                        value={data.descripcion}
                                        onChange={(e) =>
                                            setData(
                                                'descripcion',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Descripción detallada de la tarea"
                                        rows={3}
                                    />
                                </div>
                                <div className="space-y-4 rounded-lg border p-4 sm:p-6">
                                    <Label className="text-base font-semibold">
                                        Productos a Entregar
                                    </Label>
                                    <div className="flex gap-2">
                                        <div className="flex-1">
                                            <select
                                                value={selectedProductoId}
                                                onChange={(e) =>
                                                    setSelectedProductoId(
                                                        e.target.value,
                                                    )
                                                }
                                                className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                            >
                                                <option value="">
                                                    Seleccionar producto...
                                                </option>
                                                {productos.map((prod) => (
                                                    <option
                                                        key={prod.id}
                                                        value={prod.id}
                                                    >
                                                        {prod.nombre} (
                                                        {prod.unidad_medida})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="w-24">
                                            <Input
                                                type="number"
                                                min="1"
                                                step="1"
                                                value={cantidadProducto}
                                                onChange={(e) =>
                                                    setCantidadProducto(
                                                        parseFloat(
                                                            e.target.value,
                                                        ) || 0,
                                                    )
                                                }
                                                placeholder="Cant."
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            onClick={agregarProducto}
                                            variant="secondary"
                                        >
                                            <Plus className="h-4 w-4" />
                                        </Button>
                                    </div>

                                    {data.productos.length > 0 && (
                                        <div className="space-y-2">
                                            {data.productos.map((item) => {
                                                const prod = productos.find(
                                                    (p) =>
                                                        p.id ===
                                                        item.producto_id,
                                                );
                                                return (
                                                    <div
                                                        key={item.producto_id}
                                                        className="flex items-center justify-between rounded-md bg-muted p-2 text-sm"
                                                    >
                                                        <span>
                                                            {prod?.nombre} x{' '}
                                                            {item.cantidad}
                                                        </span>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                removerProducto(
                                                                    item.producto_id,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4 text-destructive" />
                                                        </Button>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label>Prioridad</Label>
                                    <select
                                        value={data.prioridad}
                                        onChange={(e) =>
                                            setData('prioridad', e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        <option value="baja">Baja</option>
                                        <option value="media">Media</option>
                                        <option value="alta">Alta</option>
                                        <option value="urgente">Urgente</option>
                                    </select>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit">Asignar Tarea</Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIsOpen(false)}
                                    >
                                        Cancelar
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div className="flex items-center justify-end gap-2">
                    <Button variant={viewMode === 'table' ? 'default' : 'outline'} size="sm" onClick={() => setViewMode('table')}>
                        <List className="mr-1 h-4 w-4" /> Tabla
                    </Button>
                    <Button variant={viewMode === 'cards' ? 'default' : 'outline'} size="sm" onClick={() => setViewMode('cards')}>
                        <LayoutGrid className="mr-1 h-4 w-4" /> Tarjetas
                    </Button>
                </div>

                {viewMode === 'cards' ? (
                <div className="space-y-4">
                    {tareas.length === 0 ? (
                        <Card>
                            <CardContent className="py-8 text-center text-muted-foreground">
                                No hay tareas{' '}
                                {isEmpleado ? 'asignadas a ti' : ''}
                            </CardContent>
                        </Card>
                    ) : (
                        tareas.map((tarea) => {
                            const EstadoIcon =
                                estadoIcons[tarea.estado] || Clock;
                            return (
                                <Card
                                    key={tarea.id}
                                    className="overflow-hidden"
                                >
                                    <CardContent className="p-4 sm:p-6">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex-1">
                                                <div className="mb-1 flex items-center gap-2">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${prioridadColors[tarea.prioridad]}`}
                                                    >
                                                        {getPrioridadLabel(
                                                            tarea.prioridad,
                                                        )}
                                                    </span>
                                                    <EstadoIcon
                                                        className={`h-4 w-4 ${estadoColors[tarea.estado]}`}
                                                    />
                                                    <span className="text-sm text-muted-foreground">
                                                        {getEstadoLabel(
                                                            tarea.estado,
                                                        )}
                                                    </span>
                                                </div>
                                                <h3 className="font-semibold">
                                                    {tarea.titulo}
                                                </h3>
                                                {tarea.descripcion && (
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {tarea.descripcion}
                                                    </p>
                                                )}
                                                <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                                    <span>
                                                        Para:{' '}
                                                        {tarea.empleado?.name}
                                                    </span>
                                                    {tarea.fecha_limite && (
                                                        <span>
                                                            Límite:{' '}
                                                            {new Date(
                                                                tarea.fecha_limite,
                                                            ).toLocaleDateString()}
                                                        </span>
                                                    )}
                                                </div>

                                                {tarea.productos_json &&
                                                    tarea.productos_json
                                                        .length > 0 && (
                                                        <div className="mt-3 space-y-2">
                                                            <div className="flex flex-wrap gap-2">
                                                                {tarea.productos_json.map(
                                                                    (
                                                                        item: any,
                                                                        idx: number,
                                                                    ) => {
                                                                        const prod =
                                                                            productos.find(
                                                                                (
                                                                                    p,
                                                                                ) =>
                                                                                    p.id ===
                                                                                    item.producto_id,
                                                                            );
                                                                        return (
                                                                            <span
                                                                                key={
                                                                                    idx
                                                                                }
                                                                                className="inline-flex items-center gap-1 rounded bg-muted px-2 py-1 text-xs"
                                                                            >
                                                                                <Package className="h-3 w-3" />
                                                                                {
                                                                                    prod?.nombre
                                                                                }{' '}
                                                                                x{' '}
                                                                                {
                                                                                    item.cantidad
                                                                                }
                                                                            </span>
                                                                        );
                                                                    },
                                                                )}
                                                            </div>
                                                            <div className="flex flex-wrap gap-4 border-t pt-2 text-xs font-bold text-primary">
                                                                {(() => {
                                                                    let totalKilos = 0;
                                                                    let totalLitros = 0;
                                                                    tarea.productos_json.forEach(
                                                                        (
                                                                            item: any,
                                                                        ) => {
                                                                            const prod =
                                                                                productos.find(
                                                                                    (
                                                                                        p,
                                                                                    ) =>
                                                                                        p.id ===
                                                                                        item.producto_id,
                                                                                );
                                                                            if (
                                                                                prod
                                                                            ) {
                                                                                const valor =
                                                                                    (prod.medida_pesable
                                                                                        ? prod.cantidad_medida ||
                                                                                          0
                                                                                        : 0) *
                                                                                    item.cantidad;
                                                                                if (
                                                                                    prod.tipo_medida ===
                                                                                    'kilo'
                                                                                ) {
                                                                                    totalKilos +=
                                                                                        valor;
                                                                                } else if (
                                                                                    prod.tipo_medida ===
                                                                                    'litro'
                                                                                ) {
                                                                                    totalLitros +=
                                                                                        valor;
                                                                                }
                                                                            }
                                                                        },
                                                                    );
                                                                    return (
                                                                        <>
                                                                            {totalKilos >
                                                                                0 && (
                                                                                <span className="flex items-center gap-1">
                                                                                    <Scale className="h-3 w-3" />
                                                                                    Total:{' '}
                                                                                    {totalKilos.toFixed(
                                                                                        2,
                                                                                    )}{' '}
                                                                                    kg
                                                                                </span>
                                                                            )}
                                                                            {totalLitros >
                                                                                0 && (
                                                                                <span className="flex items-center gap-1">
                                                                                    <Droplets className="h-3 w-3" />
                                                                                    Total:{' '}
                                                                                    {totalLitros.toFixed(
                                                                                        2,
                                                                                    )}{' '}
                                                                                    L
                                                                                </span>
                                                                            )}
                                                                        </>
                                                                    );
                                                                })()}
                                                            </div>
                                                        </div>
                                                    )}
                                            </div>
                                            {isEmpleado &&
                                                tarea.estado !== 'completada' &&
                                                tarea.estado !==
                                                    'cancelada' && (
                                                    <div className="flex gap-1">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                cambiarEstado(
                                                                    tarea,
                                                                    'en_progreso',
                                                                )
                                                            }
                                                            title="Iniciar"
                                                        >
                                                            <AlertCircle className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                cambiarEstado(
                                                                    tarea,
                                                                    'completada',
                                                                )
                                                            }
                                                            title="Completar"
                                                        >
                                                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                                                        </Button>
                                                    </div>
                                                )}
                                            {!isEmpleado && canDelete && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        eliminarTarea(tarea)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                </Button>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })
                    )}
                </div>
                ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="px-4 py-3 text-left font-medium">Título</th>
                                <th className="px-4 py-3 text-left font-medium">Prioridad</th>
                                <th className="px-4 py-3 text-left font-medium">Estado</th>
                                <th className="px-4 py-3 text-left font-medium">Asignado</th>
                                <th className="px-4 py-3 text-left font-medium">Límite</th>
                                <th className="px-4 py-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tareas.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                                        No hay tareas {isEmpleado ? 'asignadas a ti' : ''}
                                    </td>
                                </tr>
                            ) : tareas.map((tarea) => {
                                const EstadoIcon = estadoIcons[tarea.estado] || Clock;
                                return (
                                    <tr key={tarea.id} className="border-b hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{tarea.titulo}</div>
                                            {tarea.descripcion && (
                                                <div className="text-xs text-muted-foreground line-clamp-1">{tarea.descripcion}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${prioridadColors[tarea.prioridad]}`}>
                                                {getPrioridadLabel(tarea.prioridad)}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1.5">
                                                <EstadoIcon className={`h-4 w-4 ${estadoColors[tarea.estado]}`} />
                                                <span className="text-sm text-muted-foreground">{getEstadoLabel(tarea.estado)}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm">{tarea.empleado?.name}</td>
                                        <td className="px-4 py-3 text-sm">{tarea.fecha_limite ? new Date(tarea.fecha_limite).toLocaleDateString() : '-'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                {isEmpleado && tarea.estado !== 'completada' && tarea.estado !== 'cancelada' && (
                                                    <>
                                                        <Button size="sm" variant="ghost" onClick={() => cambiarEstado(tarea, 'en_progreso')} title="Iniciar">
                                                            <AlertCircle className="h-4 w-4" />
                                                        </Button>
                                                        <Button size="sm" variant="ghost" onClick={() => cambiarEstado(tarea, 'completada')} title="Completar">
                                                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                                                        </Button>
                                                    </>
                                                )}
                                                {!isEmpleado && canDelete && (
                                                    <Button size="sm" variant="ghost" onClick={() => eliminarTarea(tarea)}>
                                                        <Trash2 className="h-4 w-4 text-red-500" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                )}
            </div>
        </AppLayout>
    );
}
