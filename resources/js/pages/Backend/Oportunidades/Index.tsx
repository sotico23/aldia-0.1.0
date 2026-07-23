import { Head, useForm, router } from '@inertiajs/react';
import {
    LayoutGrid,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Kanban,
    List,
    Building2,
    DollarSign,
    TrendingUp,
    Briefcase,
    ArrowRight,
    Check,
    AlertCircle,
    Target,
    Clock
} from 'lucide-react';
import { useState, useMemo, useEffect, useCallback } from 'react';
import { BulkActions } from '@/components/shared/BulkActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
} from '@/components/ui/dropdown-menu';
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
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateCLP } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Cliente {
    id: number;
    nombre: string;
}

interface Oportunidad {
    id: number;
    nombre: string;
    cliente_id: number;
    cliente?: Cliente;
    valor: number;
    etapa: string;
    probabilidad: number;
    fecha_cierre_estimada: string | null;
    descripcion: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ventas', href: '/oportunidades' },
    { title: 'Oportunidades de Negocio', href: '/oportunidades' },
];

const ETAPAS = [
    {
        value: 'prospeccion',
        label: 'Prospección',
        color: 'bg-slate-500/10 text-slate-600',
        icon: Search,
    },
    {
        value: 'analisis',
        label: 'Análisis',
        color: 'bg-blue-500/10 text-blue-600',
        icon: List,
    },
    {
        value: 'propuesta',
        label: 'Propuesta',
        color: 'bg-purple-500/10 text-purple-600',
        icon: Briefcase,
    },
    {
        value: 'negociacion',
        label: 'Negociación',
        color: 'bg-orange-500/10 text-orange-600',
        icon: ArrowRight,
    },
    {
        value: 'ganada',
        label: 'Ganada',
        color: 'bg-green-500/10 text-green-600',
        icon: Check,
    },
    {
        value: 'perdida',
        label: 'Perdida',
        color: 'bg-red-500/10 text-red-600',
        icon: X,
    },
];


export default function Index({
    oportunidades,
    clientes,
    filters,
}: {
    oportunidades: { data: Oportunidad[]; links: any[]; meta: any };
    clientes: Cliente[];
    filters: {
        search?: string;
        cliente_id?: string;
        etapa?: string;
        fechaDesde?: string;
        fechaHasta?: string;
        view?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('comercial.oportunidades.create');
    const canEdit = hasPermission('comercial.oportunidades.edit');
    const canDelete = hasPermission('comercial.oportunidades.delete');
    const [vistaKanban, setVistaKanban] = useState(filters.view !== 'lista');
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Oportunidad | null>(null);
    const [draggingId, setDraggingId] = useState<number | null>(null);

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [clienteFilter, setClienteFilter] = useState(
        filters.cliente_id || 'all',
    );
    const [etapaFilter, setEtapaFilter] = useState(filters.etapa || 'all');

    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        processing,
    } = useForm({
        nombre: '',
        cliente_id: '' as string | number,
        valor: 0,
        etapa: 'prospeccion',
        probabilidad: 10,
        fecha_cierre_estimada: '',
        descripcion: '',
    });

    const buildQuery = useCallback((kanban: boolean) => {
        const query: any = { view: kanban ? 'kanban' : 'lista' };
        if (searchTerm) query.search = searchTerm;
        if (clienteFilter !== 'all') query.cliente_id = clienteFilter;
        // In kanban mode we don't filter by etapa so all columns stay visible
        if (!kanban && etapaFilter !== 'all') query.etapa = etapaFilter;
        return query;
    }, [searchTerm, clienteFilter, etapaFilter]);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get('/oportunidades', buildQuery(vistaKanban), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, clienteFilter, etapaFilter, vistaKanban, buildQuery]);

    const switchView = (kanban: boolean) => {
        setVistaKanban(kanban);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/oportunidades/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                },
            });
        } else {
            post('/oportunidades', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (op: Oportunidad) => {
        setEditando(op);
        setData({
            nombre: op.nombre,
            cliente_id: String(op.cliente_id),
            valor: op.valor,
            etapa: op.etapa,
            probabilidad: op.probabilidad,
            fecha_cierre_estimada: op.fecha_cierre_estimada
                ? op.fecha_cierre_estimada.substring(0, 10)
                : '',
            descripcion: op.descripcion || '',
        });
        setIsOpen(true);
    };

    const handleDragStart = (e: React.DragEvent, id: number) => {
        setDraggingId(id);
        e.dataTransfer.effectAllowed = 'move';
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    };

    const handleDrop = (e: React.DragEvent, nuevaEtapa: string) => {
        e.preventDefault();
        if (draggingId === null) return;

        router.patch(
            `/oportunidades/${draggingId}/etapa`,
            { etapa: nuevaEtapa },
            {
                onSuccess: () => setDraggingId(null),
            },
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Desea eliminar esta oportunidad?')) {
            destroy(`/oportunidades/${id}`);
        }
    };

    const getEtapaConfig = (val: string) => {
        return (
            ETAPAS.find((e) => e.value === val) || {
                label: val,
                color: 'bg-gray-500/10 text-gray-600',
                icon: AlertCircle,
            }
        );
    };

    const groupedOportunidades = useMemo(() => {
        const groups: Record<string, Oportunidad[]> = {};
        ETAPAS.forEach((e) => (groups[e.value] = []));
        oportunidades.data.forEach((o) => {
            if (groups[o.etapa]) groups[o.etapa].push(o);
            else groups['prospeccion'].push(o);
        });
        return groups;
    }, [oportunidades.data]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pipeline de Oportunidades" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="mb-1 flex items-center gap-2">
                            <Target className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Sales Management System
                            </span>
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Oportunidades
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Gestione su embudo de ventas y visualice sus
                            ingresos proyectados
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="mr-2 flex gap-1 rounded-xl bg-muted/30 p-1">
                            <Button
                                variant={vistaKanban ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => switchView(true)}
                                className="h-8 rounded-lg font-bold"
                            >
                                <Kanban className="h-4 w-4 md:mr-2" />
                                <span className="hidden md:inline">Kanban</span>
                            </Button>
                            <Button
                                variant={!vistaKanban ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => switchView(false)}
                                className="h-8 rounded-lg font-bold"
                            >
                                <List className="h-4 w-4 md:mr-2" />
                                <span className="hidden md:inline">Lista</span>
                            </Button>
                        </div>

                        <BulkActions
                            baseUrl="/oportunidades"
                            filters={{
                                search: searchTerm,
                                cliente_id: clienteFilter,
                                etapa: etapaFilter,
                            }}
                            modelName="Oportunidades"
                        />

                        {canCreate && (
                            <Button
                                onClick={() => {
                                    setEditando(null);
                                    reset();
                                    setIsOpen(true);
                                }}
                                className="h-9 rounded-full bg-primary px-5 font-bold shadow-lg shadow-primary/20 transition-all hover:bg-primary/90"
                            >
                                <Plus className="mr-2 h-4 w-4" /> Nueva Oportunidad
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6">
                    {/* Filters Bar */}
                    <div className="flex flex-col gap-4 rounded-3xl border border-muted/50 bg-muted/40 p-4 md:flex-row md:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Buscar por nombre o descripción..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="h-11 rounded-2xl border-none bg-background pl-12 shadow-sm focus-visible:ring-primary/20"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Select
                                value={clienteFilter}
                                onValueChange={setClienteFilter}
                            >
                                <SelectTrigger className="h-11 min-w-[140px] rounded-2xl border-none bg-background font-bold shadow-sm md:w-[200px]">
                                    <SelectValue placeholder="Cliente" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos los clientes
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
                            <Select
                                value={etapaFilter}
                                onValueChange={setEtapaFilter}
                            >
                                <SelectTrigger className="h-11 min-w-[140px] rounded-2xl border-none bg-background font-bold shadow-sm md:w-[160px]">
                                    <SelectValue placeholder="Etapa" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todas las etapas
                                    </SelectItem>
                                    {ETAPAS.map((e) => (
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
                                className="h-11 w-11 rounded-2xl border-none bg-background text-muted-foreground shadow-sm"
                                onClick={() => {
                                    setSearchTerm('');
                                    setClienteFilter('all');
                                    setEtapaFilter('all');
                                }}
                            >
                                <X className="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    {vistaKanban ? (
                        <div className="flex gap-5 overflow-x-auto pb-6 items-start min-h-[65vh]">
                            {ETAPAS.map((etapa) => {
                                const opsEnEtapa =
                                    groupedOportunidades[etapa.value] || [];
                                const totalValor = opsEnEtapa.reduce(
                                    (sum, o) => sum + Number(o.valor),
                                    0,
                                );
                                // eslint-disable-next-line @typescript-eslint/no-unused-vars
                                const totalPonderado = opsEnEtapa.reduce(
                                    (sum, o) =>
                                        sum +
                                        Number(o.valor) *
                                            (o.probabilidad / 100),
                                    0,
                                );

                                return (
                                    <div
                                        key={etapa.value}
                                        className="flex w-[320px] shrink-0 flex-col gap-4"
                                        onDragOver={handleDragOver}
                                        onDrop={(e) =>
                                            handleDrop(e, etapa.value)
                                        }
                                    >
                                        <div
                                            className={`rounded-3xl p-4 ${etapa.color} flex flex-col gap-1 border-2 border-transparent`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <etapa.icon className="h-4 w-4" />
                                                    <span className="text-[11px] font-black tracking-widest uppercase">
                                                        {etapa.label}
                                                    </span>
                                                </div>
                                                <Badge className="border-none bg-background/50 text-[10px] font-black text-inherit hover:bg-background/60">
                                                    {opsEnEtapa.length}
                                                </Badge>
                                            </div>
                                            <div className="mt-1 text-[10px] font-bold opacity-70">
                                                {formatCurrency(totalValor)}{' '}
                                                Proyectados
                                            </div>
                                        </div>

                                        <div className="flex flex-col gap-3">
                                            {opsEnEtapa.map((op) => (
                                                <div
                                                    key={op.id}
                                                    draggable
                                                    onDragStart={(e) =>
                                                        handleDragStart(
                                                            e,
                                                            op.id,
                                                        )
                                                    }
                                                    className="group cursor-grab rounded-3xl border border-muted bg-background p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/20 hover:shadow-xl active:cursor-grabbing"
                                                >
                                                    <div className="mb-3 flex items-start justify-between">
                                                        <div className="min-w-0 flex-1">
                                                            <div className="truncate text-sm font-bold tracking-tight transition-colors group-hover:text-primary">
                                                                {op.nombre}
                                                            </div>
                                                            <div className="mt-0.5 flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                <Building2 className="h-2.5 w-2.5" />
                                                                <span className="truncate">
                                                                    {op.cliente
                                                                        ?.nombre ||
                                                                        'General'}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div className="flex h-8 w-8 items-center justify-center rounded-xl border border-muted bg-muted/50 text-[10px] font-black text-primary">
                                                            {op.probabilidad}%
                                                        </div>
                                                    </div>

                                                    <div className="mt-4 flex items-center justify-between">
                                                        <div className="flex items-center gap-1 text-[10px] font-black text-primary">
                                                            <DollarSign className="h-3 w-3" />
                                                            {formatCurrency(
                                                                op.valor,
                                                            )}
                                                        </div>
                                                        {op.fecha_cierre_estimada && (
                                                            <div className="flex items-center gap-1 text-[9px] font-bold text-muted-foreground">
                                                                <Clock className="h-2.5 w-2.5" />
                                                                {formatDateCLP(
                                                                    op.fecha_cierre_estimada,
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div className="mt-3 h-1 w-full overflow-hidden rounded-full bg-muted/30">
                                                        <div
                                                            className="h-full bg-primary transition-all duration-500"
                                                            style={{
                                                                width: `${op.probabilidad}%`,
                                                            }}
                                                        />
                                                    </div>

                                                    <div className="mt-4 flex justify-end gap-1 border-t border-muted/50 pt-4 opacity-0 transition-opacity group-hover:opacity-100">
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 rounded-xl text-primary hover:bg-primary/10"
                                                                onClick={() =>
                                                                    handleEdit(op)
                                                                }
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 rounded-xl text-destructive hover:bg-destructive/10"
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        op.id,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                            {opsEnEtapa.length === 0 && (
                                                <div className="rounded-3xl border-2 border-dashed border-muted p-10 text-center">
                                                    <p className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                                                        Sin Oportunidades
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <Card className="overflow-hidden border-none shadow-xl shadow-foreground/5">
                            <CardHeader className="flex flex-row items-center justify-between px-6 py-4">
                                <CardTitle>Oportunidades</CardTitle>
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
                            {viewMode === 'table' ? (
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                <th className="px-6 py-4 text-left">
                                                    Oportunidad / Cliente
                                                </th>
                                                <th className="px-6 py-4 text-right">
                                                    Valor Estimado
                                                </th>
                                                <th className="px-6 py-4 text-center">
                                                    Etapa / Prob.
                                                </th>
                                                <th className="px-6 py-4 text-center">
                                                    Cierre Estimado
                                                </th>
                                                <th className="px-6 py-4 text-right">
                                                    Acciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-muted/50">
                                            {oportunidades.data.map((op) => (
                                                <tr
                                                    key={op.id}
                                                    className="group transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="px-6 py-4">
                                                        <div>
                                                            <div className="text-sm font-bold tracking-tight text-foreground">
                                                                {op.nombre}
                                                            </div>
                                                            <div className="mt-0.5 flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                <Building2 className="h-2.5 w-2.5" />
                                                                {op.cliente
                                                                    ?.nombre ||
                                                                    'General'}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 text-right">
                                                        <div className="text-sm font-black text-primary">
                                                            {formatCurrency(
                                                                op.valor,
                                                            )}
                                                        </div>
                                                        <div className="text-[10px] text-muted-foreground">
                                                            Ponderado:{' '}
                                                            {formatCurrency(
                                                                op.valor *
                                                                    (op.probabilidad /
                                                                        100),
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        <div className="flex flex-col items-center gap-1">
                                                            <Badge
                                                                variant="outline"
                                                                className={`${getEtapaConfig(op.etapa).color} rounded-full border px-2 py-0.5 text-[8px] font-black uppercase`}
                                                            >
                                                                {
                                                                    getEtapaConfig(
                                                                        op.etapa,
                                                                    ).label
                                                                }
                                                            </Badge>
                                                            <div className="text-[10px] font-black text-primary">
                                                                {
                                                                    op.probabilidad
                                                                }
                                                                % Confianza
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        {op.fecha_cierre_estimada ? (
                                                            <div className="flex flex-col items-center">
                                                                <span className="text-xs font-bold text-foreground">
                                                                    {formatDateCLP(
                                                                        op.fecha_cierre_estimada,
                                                                    )}
                                                                </span>
                                                                <span className="text-[9px] font-bold text-muted-foreground uppercase">
                                                                    Proyectado
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground italic">
                                                                No definida
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 text-right">
                                                        <div className="flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                            {canEdit && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8 rounded-xl text-primary hover:bg-primary/10"
                                                                    onClick={() =>
                                                                        handleEdit(
                                                                            op,
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
                                                                    className="h-8 w-8 rounded-xl text-destructive hover:bg-destructive/10"
                                                                    onClick={() =>
                                                                        handleDelete(
                                                                            op.id,
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
                                        </tbody>
                                    </table>
                                </div>
                                <div className="border-t border-muted/50 p-4">
                                    <Pagination
                                        links={oportunidades.links}
                                        meta={oportunidades.meta}
                                    />
                                </div>
                            </CardContent>
                            ) : (
                            <CardContent className="p-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {oportunidades.data.map((op) => (
                                        <Card key={op.id}>
                                            <CardHeader className="pb-2">
                                                <div className="flex items-start justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <div className={`rounded-lg p-2 ${getEtapaConfig(op.etapa).color}`}>
                                                            {(() => {
                                                                const Icon = getEtapaConfig(op.etapa).icon;
                                                                return <Icon className="h-4 w-4" />;
                                                            })()}
                                                        </div>
                                                        <div>
                                                            <CardTitle className="text-sm">{op.nombre}</CardTitle>
                                                            <CardDescription className="flex items-center gap-1 text-xs">
                                                                <Building2 className="h-3 w-3" />
                                                                {op.cliente?.nombre || 'General'}
                                                            </CardDescription>
                                                        </div>
                                                    </div>
                                                </div>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="space-y-2 text-sm">
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-muted-foreground">Valor</span>
                                                        <span className="font-black text-primary">{formatCurrency(op.valor)}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-muted-foreground">Confianza</span>
                                                        <span className="font-bold">{op.probabilidad}%</span>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-muted-foreground">Etapa</span>
                                                        <Badge variant="outline" className={`${getEtapaConfig(op.etapa).color} border text-[10px]`}>
                                                            {getEtapaConfig(op.etapa).label}
                                                        </Badge>
                                                    </div>
                                                    {op.fecha_cierre_estimada && (
                                                        <div className="flex items-center justify-between">
                                                            <span className="text-muted-foreground">Cierre</span>
                                                            <span className="font-medium">{formatDateCLP(op.fecha_cierre_estimada)}</span>
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="mt-3 flex justify-end gap-1 border-t pt-2">
                                                    {canEdit && (
                                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-primary" onClick={() => handleEdit(op)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive" onClick={() => handleDelete(op.id)}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                                {oportunidades.data.length === 0 && (
                                    <div className="flex flex-col items-center justify-center py-12 text-center">
                                        <div className="mb-4 rounded-full bg-muted/30 p-4">
                                            <LayoutGrid className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                        <p className="text-muted-foreground">No se encontraron oportunidades</p>
                                    </div>
                                )}
                                <div className="mt-4 border-t border-muted/50 pt-4">
                                    <Pagination
                                        links={oportunidades.links}
                                        meta={oportunidades.meta}
                                    />
                                </div>
                            </CardContent>
                            )}
                        </Card>
                    )}
                </div>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[95vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-3xl">
                    <DialogHeader className="sticky top-0 z-10 bg-gradient-to-r from-primary/10 to-transparent p-4 pb-4 text-left backdrop-blur-sm md:p-6">
                        <div className="mb-1 flex items-center gap-2">
                            <TrendingUp className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Sales Opportunity Profile
                            </span>
                        </div>
                        <DialogTitle className="text-xl font-black tracking-tight text-primary md:text-2xl">
                            {editando
                                ? 'Editar Oportunidad de Negocio'
                                : 'Registrar Nueva Oportunidad'}
                        </DialogTitle>
                        <DialogDescription className="text-sm font-medium text-muted-foreground">
                            Complete los detalles para proyectar el cierre del
                            negocio.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[calc(95vh-180px)] overflow-y-auto p-4 md:p-6">
                        <form onSubmit={handleSubmit}>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
                                <div className="space-y-6">
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Nombre de la Oportunidad *
                                        </Label>
                                        <Input
                                            placeholder="Ej: Implementación ERP Fase 1"
                                            value={data.nombre}
                                            onChange={(e) =>
                                                setData(
                                                    'nombre',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                            className="h-11 rounded-xl border-none bg-muted/30 font-bold"
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Cliente Asignado *
                                        </Label>
                                        <Select
                                            value={data.cliente_id.toString()}
                                            onValueChange={(v) =>
                                                setData('cliente_id', v)
                                            }
                                        >
                                            <SelectTrigger className="h-11 rounded-xl border-none bg-muted/30 font-bold">
                                                <SelectValue placeholder="Seleccione un cliente..." />
                                            </SelectTrigger>
                                            <SelectContent>
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

                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Valor del Negocio
                                            </Label>
                                            <div className="relative">
                                                <DollarSign className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-primary" />
                                                <Input
                                                    type="number"
                                                    value={data.valor}
                                                    onChange={(e) =>
                                                        setData(
                                                            'valor',
                                                            parseFloat(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="h-11 rounded-xl border-none bg-muted/30 pl-10 font-black text-primary"
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Cierre Estimado
                                            </Label>
                                            <Input
                                                type="date"
                                                value={
                                                    data.fecha_cierre_estimada
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        'fecha_cierre_estimada',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-11 rounded-xl border-none bg-muted/30 text-center font-bold"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Etapa Actual
                                            </Label>
                                            <Select
                                                value={data.etapa}
                                                onValueChange={(v) =>
                                                    setData('etapa', v)
                                                }
                                            >
                                                <SelectTrigger className="h-11 rounded-xl border-none bg-muted/30 font-bold">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {ETAPAS.map((e) => (
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
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Probabilidad (%)
                                            </Label>
                                            <div className="relative">
                                                <Target className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    value={data.probabilidad}
                                                    onChange={(e) =>
                                                        setData(
                                                            'probabilidad',
                                                            parseInt(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="h-11 rounded-xl border-none bg-muted/30 pl-10 font-bold"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Descripción del Alcance /
                                            Requerimiento
                                        </Label>
                                        <textarea
                                            value={data.descripcion || ''}
                                            onChange={(e) =>
                                                setData(
                                                    'descripcion',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex min-h-[120px] w-full rounded-2xl border-none bg-muted/30 px-4 py-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                            placeholder="Detalle los servicios o productos involucrados..."
                                        />
                                    </div>
                                </div>
                            </div>

                            <DialogFooter className="sticky bottom-0 mt-6 gap-2 border-t bg-background pt-4">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setIsOpen(false)}
                                    className="rounded-full px-8 font-bold"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-full bg-primary px-12 font-bold shadow-lg shadow-primary/20 hover:bg-primary/90"
                                >
                                    <Check className="mr-2 h-4 w-4" />{' '}
                                    {editando
                                        ? 'Guardar Cambios'
                                        : 'Registrar Oportunidad'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
