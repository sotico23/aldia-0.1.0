import { Head, useForm, router } from '@inertiajs/react';
import {
    Check,
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    LifeBuoy,
    MessageSquare,
    Clock,
    User,
    Package,
    Eye,
    ShieldAlert,
    Activity,
    Users,
    ClipboardList,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { BulkActions } from '@/components/shared/BulkActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Ticket {
    id: number;
    titulo: string;
    descripcion: string | null;
    cliente_id: number | null;
    cliente?: { id: number; nombre: string } | null;
    producto_id: number | null;
    producto?: { id: number; nombre: string } | null;
    prioridad: string;
    estado: string;
    categoria: string | null;
    asignado_a: string | null;
    assigned_user_id: number | null;
    assigned_user?: { id: number; name: string } | null;
    created_at: string;
}

interface Cliente {
    id: number;
    nombre: string;
}

interface Producto {
    id: number;
    nombre: string;
    codigo?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'CRM', href: '/tickets' },
    { title: 'Centro de Soporte', href: '/tickets' },
];

const PRIORIDADES = [
    {
        value: 'baja',
        label: 'Baja',
        color: 'bg-green-500/10 text-green-600 border-green-200',
    },
    {
        value: 'media',
        label: 'Media',
        color: 'bg-blue-500/10 text-blue-600 border-blue-200',
    },
    {
        value: 'alta',
        label: 'Alta',
        color: 'bg-orange-500/10 text-orange-600 border-orange-200',
    },
    {
        value: 'critica',
        label: 'Crítica',
        color: 'bg-red-500/10 text-red-600 border-red-200',
    },
];

const ESTADOS = [
    {
        value: 'abierto',
        label: 'Abierto',
        color: 'bg-blue-500/10 text-blue-600 border-blue-200',
    },
    {
        value: 'en_proceso',
        label: 'En Proceso',
        color: 'bg-amber-500/10 text-amber-600 border-amber-200',
    },
    {
        value: 'pendiente',
        label: 'Pendiente',
        color: 'bg-purple-500/10 text-purple-600 border-purple-200',
    },
    {
        value: 'resuelto',
        label: 'Resuelto',
        color: 'bg-green-500/10 text-green-600 border-green-200',
    },
    {
        value: 'cerrado',
        label: 'Cerrado',
        color: 'bg-gray-500/10 text-gray-600 border-gray-200',
    },
];


interface Employee {
    id: number;
    name: string;
    email: string;
}

export default function Index({
    tickets,
    clientes,
    productos,
    employees,
    filters,
}: {
    tickets: { data: Ticket[]; links: any[]; meta: any };
    clientes: Cliente[];
    productos: Producto[];
    employees: Employee[];
    filters: {
        search?: string;
        estado?: string;
        prioridad?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('comercial.tickets.create');
    const canEdit = hasPermission('comercial.tickets.edit');
    const canDelete = hasPermission('comercial.tickets.delete');
    const [isOpen, setIsOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [editando, setEditando] = useState<Ticket | null>(null);
    const [viendo, setViendo] = useState<Ticket | null>(null);

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [estadoFilter, setEstadoFilter] = useState(filters.estado || 'all');
    const [prioridadFilter, setPrioridadFilter] = useState(
        filters.prioridad || 'all',
    );
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        processing,
        errors,
    } = useForm({
        titulo: '',
        descripcion: '',
        cliente_id: '' as string | number,
        producto_id: '' as string | number,
        prioridad: 'media',
        estado: 'abierto',
        categoria: '',
        asignado_a: '',
        assigned_user_id: '' as string | number,
        es_soporte: false,
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (estadoFilter !== 'all') query.estado = estadoFilter;
            if (prioridadFilter !== 'all') query.prioridad = prioridadFilter;

            router.get('/tickets', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);

        return () => clearTimeout(timer);
    }, [searchTerm, estadoFilter, prioridadFilter]);

    const limpiarFiltros = () => {
        setSearchTerm('');
        setEstadoFilter('all');
        setPrioridadFilter('all');
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/tickets/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                },
            });
        } else {
            post('/tickets', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (ticket: Ticket) => {
        setEditando(ticket);
        setData({
            titulo: ticket.titulo,
            descripcion: ticket.descripcion || '',
            cliente_id: ticket.cliente_id || '',
            producto_id: ticket.producto_id || '',
            prioridad: ticket.prioridad,
            estado: ticket.estado,
            categoria: ticket.categoria || '',
            asignado_a: ticket.asignado_a || '',
            assigned_user_id: ticket.assigned_user_id || '',
            es_soporte: false,
        });
        setIsOpen(true);
    };

    const handleView = (ticket: Ticket) => {
        setViendo(ticket);
        setIsViewOpen(true);
    };

    const handleNew = () => {
        setEditando(null);
        reset();
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Está seguro de eliminar este ticket?')) {
            destroy(`/tickets/${id}`);
        }
    };

    const getPrioridadConfig = (val: string) => {
        return (
            PRIORIDADES.find((p) => p.value === val) || {
                label: val,
                color: 'bg-gray-500/10 text-gray-600 border-gray-200',
            }
        );
    };

    const getEstadoConfig = (val: string) => {
        return (
            ESTADOS.find((e) => e.value === val) || {
                label: val,
                color: 'bg-gray-500/10 text-gray-600 border-gray-200',
            }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Centro de Soporte y Tickets" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header Section */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="mb-1 flex items-center gap-2">
                            <LifeBuoy className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Support Operations
                            </span>
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Soporte Técnico
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Gestión centralizada de incidencias, requerimientos
                            y atención al cliente
                        </p>
                    </div>

                    <div className="flex flex-nowrap items-center gap-2">
                        <BulkActions
                            baseUrl="/tickets"
                            modelName="Tickets"
                            filters={{
                                search: searchTerm,
                                estado: estadoFilter,
                                prioridad: prioridadFilter,
                            }}
                        />

                        {canCreate && (
                            <Button
                                onClick={handleNew}
                                className="h-9 rounded-full bg-primary px-5 font-bold shadow-lg shadow-primary/20 transition-all hover:bg-primary/90"
                            >
                                <Plus className="mr-2 h-4 w-4" /> Nuevo Ticket
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6">
                    <Card className="overflow-hidden border-none shadow-xl shadow-foreground/5">
                        <CardHeader className="bg-gradient-to-r from-muted/50 to-transparent pb-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <ClipboardList className="h-5 w-5 text-primary" />
                                    <CardTitle>Cola de Atención</CardTitle>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="text-xs font-bold tracking-widest text-muted-foreground uppercase">
                                        {tickets.meta?.total || tickets.data.length}{' '}
                                        Registros
                                    </div>
                                    <Button variant={viewMode === 'table' ? 'default' : 'outline'} size="icon" className="h-8 w-8" onClick={() => setViewMode('table')}>
                                        <List className="h-4 w-4" />
                                    </Button>
                                    <Button variant={viewMode === 'cards' ? 'default' : 'outline'} size="icon" className="h-8 w-8" onClick={() => setViewMode('cards')}>
                                        <LayoutGrid className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {/* Filters Bar */}
                            <div className="flex flex-col gap-4 border-b border-muted/30 bg-muted/20 p-4 md:flex-row md:items-center">
                                <div className="relative flex-1">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Buscar por título, cliente o asignado..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="h-10 border-none bg-background/50 pl-10 focus-visible:ring-primary/20"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Select
                                        value={prioridadFilter}
                                        onValueChange={setPrioridadFilter}
                                    >
                                        <SelectTrigger className="h-10 w-full border-none bg-background/50 sm:w-[160px]">
                                            <SelectValue placeholder="Prioridad" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todas
                                            </SelectItem>
                                            {PRIORIDADES.map((p) => (
                                                <SelectItem
                                                    key={p.value}
                                                    value={p.value}
                                                >
                                                    {p.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        value={estadoFilter}
                                        onValueChange={setEstadoFilter}
                                    >
                                        <SelectTrigger className="h-10 w-full border-none bg-background/50 sm:w-[160px]">
                                            <SelectValue placeholder="Estado" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todos los estados
                                            </SelectItem>
                                            {ESTADOS.map((e) => (
                                                <SelectItem
                                                    key={e.value}
                                                    value={e.value}
                                                >
                                                    {e.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="h-10 w-10 border-none bg-background/50"
                                        onClick={limpiarFiltros}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            {viewMode === 'table' ? (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                            <th className="px-6 py-4 text-left">
                                                Asunto / ID
                                            </th>
                                            <th className="px-6 py-4 text-left">
                                                Cliente / Producto
                                            </th>
                                            <th className="px-6 py-4 text-center">
                                                Prioridad
                                            </th>
                                            <th className="px-6 py-4 text-center">
                                                Estado
                                            </th>
                                            <th className="px-6 py-4 text-left">
                                                Asignado
                                            </th>
                                            <th className="px-6 py-4 text-right">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-muted/50">
                                        {tickets.data.map((t) => (
                                            <tr
                                                key={t.id}
                                                className="group transition-colors hover:bg-muted/30"
                                            >
                                                <td className="px-6 py-4">
                                                    <div>
                                                        <div className="line-clamp-1 text-sm font-bold tracking-tight text-foreground">
                                                            {t.titulo}
                                                        </div>
                                                        <div className="font-mono text-[10px] text-muted-foreground">
                                                            TK-
                                                            {t.id
                                                                .toString()
                                                                .padStart(
                                                                    5,
                                                                    '0',
                                                                )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-1.5 text-xs font-bold">
                                                            <Users className="h-3 w-3 text-primary/60" />
                                                            {t.cliente
                                                                ?.nombre ||
                                                                'General'}
                                                        </div>
                                                        {t.producto && (
                                                            <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                                                <Package className="h-2.5 w-2.5" />
                                                                {
                                                                    t.producto
                                                                        .nombre
                                                                }
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <Badge
                                                        variant="outline"
                                                        className={`${getPrioridadConfig(t.prioridad).color} rounded-full border px-2 py-0.5 text-[9px] font-black uppercase`}
                                                    >
                                                        {
                                                            getPrioridadConfig(
                                                                t.prioridad,
                                                            ).label
                                                        }
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <Badge
                                                        variant="outline"
                                                        className={`${getEstadoConfig(t.estado).color} rounded-full border px-2 py-0.5 text-[9px] font-black uppercase`}
                                                    >
                                                        {
                                                            getEstadoConfig(
                                                                t.estado,
                                                            ).label
                                                        }
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-[10px] font-black text-primary">
                                                            {t.asignado_a
                                                                ? t.asignado_a
                                                                      .charAt(0)
                                                                      .toUpperCase()
                                                                : '?'}
                                                        </div>
                                                        <span className="text-xs font-medium text-muted-foreground">
                                                            {t.asignado_a ||
                                                                'Sin asignar'}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <div className="flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 text-muted-foreground hover:bg-muted"
                                                            onClick={() =>
                                                                handleView(t)
                                                            }
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 text-primary hover:bg-primary/10"
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
                                                                size="icon"
                                                                className="h-8 w-8 text-destructive hover:bg-destructive/10"
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
                                        {tickets.data.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="py-20 text-center"
                                                >
                                                    <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                                        <Activity className="h-10 w-10 opacity-20" />
                                                        <p className="font-medium">
                                                            No hay tickets que
                                                            coincidan con la
                                                            búsqueda
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {tickets.data.length === 0 ? (
                                    <div className="col-span-full flex flex-col items-center py-20 text-center text-muted-foreground">
                                        <Activity className="mb-4 h-12 w-12 opacity-20" />
                                        <p className="font-medium">No hay tickets que coincidan con la búsqueda</p>
                                    </div>
                                ) : tickets.data.map((t) => (
                                    <Card key={t.id} className="overflow-hidden transition-all hover:shadow-md">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1 min-w-0">
                                                    <CardTitle className="text-sm font-bold line-clamp-1">{t.titulo}</CardTitle>
                                                    <CardDescription className="font-mono text-[10px]">
                                                        TK-{t.id.toString().padStart(5, '0')}
                                                    </CardDescription>
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap gap-1.5 pt-1">
                                                <Badge variant="outline" className={`${getPrioridadConfig(t.prioridad).color} rounded-full border px-2 py-0.5 text-[9px] font-black uppercase`}>
                                                    {getPrioridadConfig(t.prioridad).label}
                                                </Badge>
                                                <Badge variant="outline" className={`${getEstadoConfig(t.estado).color} rounded-full border px-2 py-0.5 text-[9px] font-black uppercase`}>
                                                    {getEstadoConfig(t.estado).label}
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-2 pt-0">
                                            <div className="flex items-center gap-1.5 text-xs">
                                                <Users className="h-3 w-3 text-primary/60" />
                                                <span className="font-medium">{t.cliente?.nombre || 'General'}</span>
                                            </div>
                                            {t.producto && (
                                                <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                                    <Package className="h-2.5 w-2.5" />
                                                    <span>{t.producto.nombre}</span>
                                                </div>
                                            )}
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-[9px] font-black text-primary">
                                                    {t.asignado_a ? t.asignado_a.charAt(0).toUpperCase() : '?'}
                                                </div>
                                                <span>{t.asignado_a || 'Sin asignar'}</span>
                                            </div>
                                            <div className="flex justify-end gap-1 border-t pt-2">
                                                <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:bg-muted" onClick={() => handleView(t)}>
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                                {canEdit && (
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-primary hover:bg-primary/10" onClick={() => handleEdit(t)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:bg-destructive/10" onClick={() => handleDelete(t.id)}>
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                            )}

                            <div className="border-t border-muted/50 p-4">
                                <Pagination
                                    links={tickets.links}
                                    meta={tickets.meta || tickets}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Create/Edit dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-2xl">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-6 pb-2 text-left">
                        <div className="mb-1 flex items-center gap-2">
                            <MessageSquare className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Incidence Management
                            </span>
                        </div>
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                            {editando
                                ? 'Modificar Ticket'
                                : 'Registrar Nuevo Incidente'}
                        </DialogTitle>
                    </DialogHeader>

                    <form
                        onSubmit={handleSubmit}
                        className="max-h-[80vh] overflow-y-auto p-6 pt-2"
                    >
                        <div className="grid gap-6 py-4">
                            <div className="space-y-2">
                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    Título / Asunto *
                                </Label>
                                <Input
                                    value={data.titulo}
                                    onChange={(e) =>
                                        setData('titulo', e.target.value)
                                    }
                                    required
                                    placeholder="Ej: No puedo acceder al panel de inventarios"
                                    className="h-11 border-none bg-muted/30 font-bold"
                                />
                                {errors.titulo && (
                                    <p className="text-[10px] font-bold text-destructive">
                                        {errors.titulo}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Cliente Relacionado
                                    </Label>
                                    <Select
                                        value={data.cliente_id.toString()}
                                        onValueChange={(v) =>
                                            setData('cliente_id', v === 'none' ? '' : v)
                                        }
                                    >
                                        <SelectTrigger className="h-11 border-none bg-muted/30 font-bold">
                                            <SelectValue placeholder="Seleccione cliente..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Sin cliente (General)
                                            </SelectItem>
                                            {clientes.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={c.id.toString()}
                                                >
                                                    {c.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Producto Afectado
                                    </Label>
                                    <div className="flex gap-2">
                                        <div className="flex-1">
                                            <Select
                                                value={data.producto_id.toString()}
                                                onValueChange={(v) =>
                                                    setData('producto_id', v === 'none' ? '' : v)
                                                }
                                            >
                                                <SelectTrigger className="h-11 border-none bg-muted/30 font-bold">
                                                    <SelectValue placeholder="Asociar a producto..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">
                                                        Ningún producto en particular
                                                    </SelectItem>
                                                    {productos.map((p) => (
                                                        <SelectItem
                                                            key={p.id}
                                                            value={p.id.toString()}
                                                        >
                                                            {p.nombre}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 border-t pt-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Prioridad
                                    </Label>
                                    <Select
                                        value={data.prioridad}
                                        onValueChange={(v) =>
                                            setData('prioridad', v)
                                        }
                                    >
                                        <SelectTrigger className="h-11 border-none bg-muted/30 font-bold">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {PRIORIDADES.map((p) => (
                                                <SelectItem
                                                    key={p.value}
                                                    value={p.value}
                                                >
                                                    {p.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Estado
                                    </Label>
                                    <Select
                                        value={data.estado}
                                        onValueChange={(v) =>
                                            setData('estado', v)
                                        }
                                    >
                                        <SelectTrigger className="h-11 border-none bg-muted/30 font-bold">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {ESTADOS.map((e) => (
                                                <SelectItem
                                                    key={e.value}
                                                    value={e.value}
                                                >
                                                    {e.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Asignar a
                                    </Label>
                                    <Select
                                        value={data.assigned_user_id.toString()}
                                        onValueChange={(v) =>
                                            setData('assigned_user_id', v === 'none' ? '' : v)
                                        }
                                    >
                                        <SelectTrigger className="h-11 border-none bg-muted/30 font-bold">
                                            <SelectValue placeholder="Seleccionar responsable..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Sin asignar
                                            </SelectItem>
                                            {employees.map((emp) => (
                                                <SelectItem
                                                    key={emp.id}
                                                    value={emp.id.toString()}
                                                >
                                                    {emp.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2 flex items-end pb-2">
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <Switch
                                            id="es_soporte"
                                            checked={data.es_soporte}
                                            onCheckedChange={(v) =>
                                                setData('es_soporte', v)
                                            }
                                        />
                                        <div>
                                            <span className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                                Soporte Aldia
                                            </span>
                                            <p className="text-[10px] text-muted-foreground/60">
                                                Enviar al equipo global de soporte
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div className="space-y-2 border-t pt-4">
                                <Label className="flex items-center gap-2 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    <ClipboardList className="h-4 w-4" />{' '}
                                    Descripción Detallada
                                </Label>
                                <textarea
                                    value={data.descripcion || ''}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
                                    }
                                    className="flex min-h-[120px] w-full rounded-xl border-none bg-muted/30 px-4 py-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                    placeholder="Explique el problema, pasos para reproducir o requerimiento técnico..."
                                />
                            </div>
                        </div>

                        <DialogFooter className="gap-2 border-t pt-6">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                                className="font-bold"
                            >
                                Cerrar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-full bg-primary px-12 font-bold shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <Check className="mr-2 h-4 w-4" />{' '}
                                {editando
                                    ? 'Actualizar Información'
                                    : 'Generar Ticket'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* View dialog */}
            <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
                <DialogContent className="max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-2xl">
                    {viendo && (
                        <>
                            <DialogHeader className="relative overflow-hidden bg-gradient-to-br from-slate-950 to-indigo-950 p-8 text-left">
                                <div className="absolute top-0 right-0 p-8 opacity-5">
                                    <LifeBuoy className="h-32 w-32 rotate-12 text-white" />
                                </div>
                                <div className="relative z-10">
                                    <div className="mb-3 flex items-center gap-2">
                                        <Badge className="border-white/20 bg-white/10 px-3 text-[9px] font-black tracking-widest text-white uppercase">
                                            Ticket ID: TK-
                                            {viendo.id
                                                .toString()
                                                .padStart(5, '0')}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            className={`${getPrioridadConfig(viendo.prioridad).color} border-none px-3 py-1 text-[9px] font-black uppercase`}
                                        >
                                            {
                                                getPrioridadConfig(
                                                    viendo.prioridad,
                                                ).label
                                            }
                                        </Badge>
                                    </div>
                                    <DialogTitle className="text-3xl leading-tight font-black tracking-tight text-white">
                                        {viendo.titulo}
                                    </DialogTitle>
                                    <DialogDescription className="font-medium text-indigo-200/60">
                                        Cronología y seguimiento de incidencia
                                        operativa
                                    </DialogDescription>
                                </div>
                            </DialogHeader>
                            <div className="space-y-8 p-8">
                                <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
                                    <div className="space-y-4">
                                        <h3 className="flex items-center gap-2 text-xs font-black tracking-widest text-primary uppercase">
                                            <User className="h-4 w-4" />{' '}
                                            Intervinientes
                                        </h3>
                                        <div className="space-y-3 rounded-2xl border border-muted/50 bg-muted/10 p-5">
                                            <div className="space-y-1">
                                                <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                                    Solicitante
                                                </span>
                                                <p className="text-sm font-bold">
                                                    {viendo.cliente?.nombre ||
                                                        'General / Interno'}
                                                </p>
                                            </div>
                                            <div className="border-t border-muted/50 pt-3">
                                                <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                                    Responsable Asignado
                                                </span>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary/20 text-[9px] font-black text-primary">
                                                        {viendo.asignado_a
                                                            ? viendo.asignado_a
                                                                  .charAt(0)
                                                                  .toUpperCase()
                                                            : '?'}
                                                    </div>
                                                    <p className="text-xs font-bold italic">
                                                        {viendo.asignado_a ||
                                                            'Pendiente de asignación'}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="space-y-4">
                                        <h3 className="flex items-center gap-2 text-xs font-black tracking-widest text-primary uppercase">
                                            <Activity className="h-4 w-4" />{' '}
                                            Estado Operativo
                                        </h3>
                                        <div className="space-y-4 rounded-2xl border border-muted/50 bg-muted/10 p-5">
                                            <div className="flex items-center justify-between">
                                                <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                                    Estado
                                                </span>
                                                <Badge
                                                    className={`${getEstadoConfig(viendo.estado).color} border-none px-3 py-1 text-[10px] font-black uppercase`}
                                                >
                                                    {
                                                        getEstadoConfig(
                                                            viendo.estado,
                                                        ).label
                                                    }
                                                </Badge>
                                            </div>
                                            <div className="space-y-1 border-t border-muted/50 pt-3">
                                                <span className="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                                    <Clock className="h-3 w-3" />{' '}
                                                    Fecha de Apertura
                                                </span>
                                                <p className="text-xs font-bold">
                                                    {new Date(
                                                        viendo.created_at,
                                                    ).toLocaleString()}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {viendo.producto && (
                                    <div className="flex items-center gap-3 rounded-2xl border border-muted/50 bg-muted/30 p-4">
                                        <Package className="h-5 w-5 text-muted-foreground" />
                                        <div className="flex-1">
                                            <span className="text-[9px] font-black text-muted-foreground uppercase">
                                                Ítem Relacionado
                                            </span>
                                            <p className="text-xs font-bold">
                                                {viendo.producto.nombre}
                                            </p>
                                        </div>
                                        <div className="rounded-full border bg-background px-3 py-1 text-[9px] font-black text-muted-foreground">
                                            MÓDULO PRODUCTOS
                                        </div>
                                    </div>
                                )}

                                <div className="rounded-3xl border border-slate-200 bg-slate-50 p-6 shadow-inner">
                                    <div className="space-y-2">
                                        <div className="mb-2 flex items-center gap-2">
                                            <ShieldAlert className="h-4 w-4 text-slate-400" />
                                            <span className="text-[10px] font-black tracking-widest text-slate-500 uppercase">
                                                Expedición de Motivos
                                            </span>
                                        </div>
                                        <p className="text-sm leading-relaxed font-medium text-slate-800">
                                            {viendo.descripcion ||
                                                'Sin descripción técnica detallada.'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <DialogFooter className="items-center justify-between border-t bg-muted/10 p-8">
                                <p className="max-w-[200px] text-[10px] font-bold text-muted-foreground italic">
                                    Este ticket forma parte del registro de
                                    calidad ISO-9001.
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => setIsViewOpen(false)}
                                        className="rounded-full px-8 font-black"
                                    >
                                        Cerrar
                                    </Button>
                                    <Button
                                        onClick={() => {
                                            setIsViewOpen(false);
                                            handleEdit(viendo);
                                        }}
                                        className="rounded-full bg-primary px-8 font-black"
                                    >
                                        Intervenir Ticket
                                    </Button>
                                </div>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

        </AppLayout>
    );
}
