import { Head, useForm, router } from '@inertiajs/react';
import {
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Eye,
    Calendar,
    BarChart3,
    Megaphone,
    Users,
    TrendingUp,
    DollarSign,
    CheckCircle2,
    Clock,
    AlertCircle,
    LayoutGrid,
    List,
    PieChart,
    ArrowUpRight
} from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';
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

interface Campana {
    id: number;
    nombre: string;
    descripcion: string | null;
    tipo: string | null;
    canal: string | null;
    objetivo: string | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    presupuesto: number;
    presupuesto_real: number;
    visitas: number;
    leads: number;
    conversiones: number;
    alcance: number;
    roi: number;
    estado: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Marketing', href: '/campanas' },
    { title: 'Campañas Publicitarias', href: '/campanas' },
];

const ESTADOS = [
    {
        value: 'borrador',
        label: 'Borrador',
        color: 'bg-slate-500/10 text-slate-600',
        icon: Clock,
    },
    {
        value: 'planificada',
        label: 'Planificada',
        color: 'bg-blue-500/10 text-blue-600',
        icon: Calendar,
    },
    {
        value: 'activa',
        label: 'Activa',
        color: 'bg-green-500/10 text-green-600',
        icon: Megaphone,
    },
    {
        value: 'pausada',
        label: 'Pausada',
        color: 'bg-orange-500/10 text-orange-600',
        icon: Clock,
    },
    {
        value: 'finalizada',
        label: 'Finalizada',
        color: 'bg-purple-500/10 text-purple-600',
        icon: CheckCircle2,
    },
    {
        value: 'cancelada',
        label: 'Cancelada',
        color: 'bg-red-500/10 text-red-600',
        icon: X,
    },
];

const CANALES = [
    'Facebook',
    'Instagram',
    'Google Ads',
    'Email Marketing',
    'LinkedIn',
    'WhatsApp',
    'YouTube',
    'TikTok',
    'Radio',
    'Prensa',
    'Otro',
];

export default function Index({
    campanas,
    filters,
}: {
    campanas: { data: Campana[]; links: any[]; meta: any };
    filters: {
        search?: string;
        estado?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('comercial.campanas.create');
    const canEdit = hasPermission('comercial.campanas.edit');
    const canDelete = hasPermission('comercial.campanas.delete');
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Campana | null>(null);
    const [verOpen, setVerOpen] = useState(false);
    const [campanaVer, setCampanaVer] = useState<Campana | null>(null);

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [estadoFilter, setEstadoFilter] = useState(filters.estado || 'all');
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
        descripcion: '',
        tipo: '',
        canal: '',
        objetivo: '',
        fecha_inicio: '',
        fecha_fin: '',
        presupuesto: 0,
        presupuesto_real: 0,
        visitas: 0,
        leads: 0,
        conversiones: 0,
        roi: 0,
        estado: 'borrador',
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (estadoFilter !== 'all') query.estado = estadoFilter;

            router.get('/campanas', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, estadoFilter]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/campanas/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                },
            });
        } else {
            post('/campanas', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (cam: Campana) => {
        setEditando(cam);
        setData({
            nombre: cam.nombre,
            descripcion: cam.descripcion || '',
            tipo: cam.tipo || '',
            canal: cam.canal || '',
            objetivo: cam.objetivo || '',
            fecha_inicio: cam.fecha_inicio
                ? cam.fecha_inicio.substring(0, 10)
                : '',
            fecha_fin: cam.fecha_fin ? cam.fecha_fin.substring(0, 10) : '',
            presupuesto: cam.presupuesto,
            presupuesto_real: cam.presupuesto_real,
            visitas: cam.visitas,
            leads: cam.leads,
            conversiones: cam.conversiones,
            roi: cam.roi,
            estado: cam.estado,
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Desea eliminar esta campaña?')) {
            destroy(`/campanas/${id}`);
        }
    };

    const getEstadoConfig = (val: string) => {
        return (
            ESTADOS.find((e) => e.value === val) || {
                label: val,
                color: 'bg-gray-500/10 text-gray-600',
                icon: AlertCircle,
            }
        );
    };

    const stats = useMemo(() => {
        const totalPresupuesto = campanas.data.reduce(
            (sum, c) => sum + Number(c.presupuesto),
            0,
        );
        const totalLeads = campanas.data.reduce(
            (sum, c) => sum + Number(c.leads),
            0,
        );
        const activeCount = campanas.data.filter(
            (c) => c.estado === 'activa',
        ).length;

        return { totalPresupuesto, totalLeads, activeCount };
    }, [campanas.data]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestión de Campañas" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="min-w-0">
                        <div className="mb-1 flex items-center gap-2">
                            <PieChart className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Marketing Performance Suite
                            </span>
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Campañas
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Planifique y analice el rendimiento de sus esfuerzos
                            de marketing
                        </p>
                    </div>

                    <div className="flex flex-nowrap items-center gap-2">
                        <BulkActions
                            baseUrl="/campanas"
                            filters={{
                                search: searchTerm,
                                estado: estadoFilter,
                            }}
                            modelName="Campañas"
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
                                <Plus className="mr-2 h-4 w-4" /> Nueva Campaña
                            </Button>
                        )}
                    </div>
                </div>

                {/* Quick Stats Grid */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card className="rounded-3xl border-none bg-blue-500/5 shadow-sm">
                        <CardContent className="flex items-center justify-between p-6">
                            <div>
                                <p className="mb-1 text-[10px] font-black tracking-widest text-blue-600/70 uppercase">
                                    Inversión Total
                                </p>
                                <h3 className="text-2xl font-black text-blue-700">
                                    {formatCurrency(stats.totalPresupuesto)}
                                </h3>
                            </div>
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600">
                                <DollarSign className="h-6 w-6" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="rounded-3xl border-none bg-green-500/5 shadow-sm">
                        <CardContent className="flex items-center justify-between p-6">
                            <div>
                                <p className="mb-1 text-[10px] font-black tracking-widest text-green-600/70 uppercase">
                                    Leads Generados
                                </p>
                                <h3 className="text-2xl font-black text-green-700">
                                    {stats.totalLeads}
                                </h3>
                            </div>
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-green-500/10 text-green-600">
                                <Users className="h-6 w-6" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="rounded-3xl border-none bg-purple-500/5 shadow-sm">
                        <CardContent className="flex items-center justify-between p-6">
                            <div>
                                <p className="mb-1 text-[10px] font-black tracking-widest text-purple-600/70 uppercase">
                                    Campañas Activas
                                </p>
                                <h3 className="text-2xl font-black text-purple-700">
                                    {stats.activeCount}
                                </h3>
                            </div>
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-500/10 text-purple-600">
                                <Megaphone className="h-6 w-6" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6">
                    {/* View Toggle */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1 rounded-lg border bg-muted/30 p-0.5">
                            <button onClick={() => setViewMode('table')} className={`rounded-md p-1.5 transition-colors ${viewMode === 'table' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tabla"><List className="h-4 w-4" /></button>
                            <button onClick={() => setViewMode('cards')} className={`rounded-md p-1.5 transition-colors ${viewMode === 'cards' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tarjetas"><LayoutGrid className="h-4 w-4" /></button>
                        </div>
                    </div>

                    {/* Filters Bar */}
                    <div className="flex flex-col gap-4 rounded-3xl border border-muted/50 bg-muted/40 p-4 md:flex-row md:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Buscar por nombre, descripción o canal..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="h-11 rounded-2xl border-none bg-background pl-12 shadow-sm focus-visible:ring-primary/20"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Select
                                value={estadoFilter}
                                onValueChange={setEstadoFilter}
                            >
                                <SelectTrigger className="h-11 w-full rounded-2xl border-none bg-background font-bold shadow-sm sm:w-[180px]">
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
                                className="h-11 w-11 rounded-2xl border-none bg-background text-muted-foreground shadow-sm"
                                onClick={() => {
                                    setSearchTerm('');
                                    setEstadoFilter('all');
                                }}
                            >
                                <X className="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

{viewMode === 'cards' ? (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {campanas.data.map((cam) => {
                                const config = getEstadoConfig(cam.estado);
                                return (
                                    <Card
                                        key={cam.id}
                                        className="group overflow-hidden rounded-3xl border-none shadow-xl shadow-foreground/5 transition-all duration-300 hover:ring-2 hover:ring-primary/20"
                                    >
                                        <CardHeader className="pb-4">
                                            <div className="mb-2 flex items-start justify-between">
                                                <Badge
                                                    variant="outline"
                                                    className={`${config.color} rounded-full border-none px-2 py-0.5 text-[9px] font-black uppercase`}
                                                >
                                                    {config.label}
                                                </Badge>
                                                <div className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 rounded-full text-muted-foreground hover:bg-muted"
                                                        onClick={() => {
                                                            setCampanaVer(cam);
                                                            setVerOpen(true);
                                                        }}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                    {canEdit && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 rounded-full text-primary hover:bg-primary/10"
                                                            onClick={() =>
                                                                handleEdit(cam)
                                                            }
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 rounded-full text-destructive hover:bg-destructive/10"
                                                            onClick={() =>
                                                                handleDelete(cam.id)
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                            <CardTitle className="text-xl font-black transition-colors group-hover:text-primary">
                                                {cam.nombre}
                                            </CardTitle>
                                            <CardDescription className="line-clamp-2 min-h-[40px]">
                                                {cam.descripcion ||
                                                    'Sin descripción detallada.'}
                                            </CardDescription>
                                        </CardHeader>

                                        <CardContent className="space-y-6">
    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                                <div className="rounded-2xl border border-muted/50 bg-muted/30 p-3">
                                                    <div className="mb-1 text-[10px] font-black text-muted-foreground uppercase">
                                                        Presupuesto
                                                    </div>
                                                    <div className="text-sm font-black text-foreground">
                                                        {formatCurrency(
                                                            cam.presupuesto,
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="rounded-2xl border border-muted/50 bg-muted/30 p-3">
                                                    <div className="mb-1 text-[10px] font-black text-muted-foreground uppercase">
                                                        Canal
                                                    </div>
                                                    <div className="truncate text-sm font-black text-primary">
                                                        {cam.canal || 'Directo'}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="space-y-3">
                                                <div className="flex items-center justify-between text-xs">
                                                    <span className="font-bold text-muted-foreground">
                                                        Leads Generados
                                                    </span>
                                                    <span className="font-black text-foreground">
                                                        {cam.leads}
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between text-xs">
                                                    <span className="font-bold text-muted-foreground">
                                                        Conversiones
                                                    </span>
                                                    <span className="font-black text-green-600">
                                                        {cam.conversiones}
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between text-xs">
                                                    <span className="font-bold text-muted-foreground">
                                                        ROI Estimado
                                                    </span>
                                                    <span className="font-black text-blue-600">
                                                        {cam.roi}%
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between border-t border-muted/50 pt-4 text-[10px] font-black text-muted-foreground uppercase">
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="h-3 w-3" />
                                                    {cam.fecha_inicio
                                                        ? formatDateCLP(
                                                              cam.fecha_inicio,
                                                          )
                                                        : 'N/A'}
                                                </div>
                                                <ArrowUpRight className="h-4 w-4 text-primary" />
                                                <div className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />
                                                    {cam.fecha_fin
                                                        ? formatDateCLP(
                                                              cam.fecha_fin,
                                                          )
                                                        : 'N/A'}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-3xl border border-muted bg-background shadow-sm">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/30 text-[11px] font-black uppercase tracking-wider text-muted-foreground">
                                        <th className="px-4 py-3 text-left">Nombre</th>
                                        <th className="px-4 py-3 text-center">Estado</th>
                                        <th className="px-4 py-3 text-left">Canal</th>
                                        <th className="px-4 py-3 text-right">Presupuesto</th>
                                        <th className="px-4 py-3 text-right">Leads</th>
                                        <th className="px-4 py-3 text-right">Conv.</th>
                                        <th className="px-4 py-3 text-right">ROI</th>
                                        <th className="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {campanas.data.map((cam) => {
                                        const config = getEstadoConfig(cam.estado);
                                        return (
                                            <tr key={cam.id} className="border-b transition-colors hover:bg-muted/20">
                                                <td className="px-4 py-3 font-medium">{cam.nombre}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <Badge variant="outline" className={`${config.color} border-none text-[9px] font-black uppercase px-2 py-0.5 rounded-full`}>
                                                        {config.label}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">{cam.canal || '-'}</td>
                                                <td className="px-4 py-3 text-right font-medium">{formatCurrency(cam.presupuesto)}</td>
                                                <td className="px-4 py-3 text-right">{cam.leads}</td>
                                                <td className="px-4 py-3 text-right">{cam.conversiones}</td>
                                                <td className="px-4 py-3 text-right font-semibold text-blue-600">{cam.roi}%</td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 rounded-full text-muted-foreground hover:bg-muted" onClick={() => { setCampanaVer(cam); setVerOpen(true); }}>
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {canEdit && (
                                                            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-full text-primary hover:bg-primary/10" onClick={() => handleEdit(cam)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-full text-destructive hover:bg-destructive/10" onClick={() => handleDelete(cam.id)}>
                                                                <Trash2 className="h-4 w-4" />
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

                    <div className="rounded-3xl border border-muted bg-background p-4 shadow-sm">
                        <Pagination
                            links={campanas.links}
                            meta={campanas.meta}
                        />
                    </div>
                </div>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-4xl">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-6 pb-4 text-left">
                        <div className="mb-1 flex items-center gap-2">
                            <BarChart3 className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Campaign Strategy Builder
                            </span>
                        </div>
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                            {editando
                                ? 'Editar Campaña Publicitaria'
                                : 'Nueva Campaña Publicitaria'}
                        </DialogTitle>
                        <DialogDescription className="font-medium text-muted-foreground">
                            Defina los parámetros y presupuesto para su próxima
                            acción de marketing.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="p-6 pt-2">
                        <div className="grid grid-cols-1 gap-10 py-4 md:grid-cols-2">
                            {/* Columna Principal: Definición */}
                            <div className="space-y-6">
                                <h3 className="flex items-center gap-2 text-[11px] font-black tracking-widest text-primary uppercase">
                                    <LayoutGrid className="h-4 w-4" />{' '}
                                    Definición de Campaña
                                </h3>
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Nombre de la Campaña *
                                        </Label>
                                        <Input
                                            placeholder="Ej: Hot Sale 2024 - Google Ads"
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
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Canal
                                            </Label>
                                            <Select
                                                value={data.canal}
                                                onValueChange={(v) =>
                                                    setData('canal', v)
                                                }
                                            >
                                                <SelectTrigger className="h-11 rounded-xl border-none bg-muted/30 font-bold">
                                                    <SelectValue placeholder="Seleccione..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {CANALES.map((c) => (
                                                        <SelectItem
                                                            key={c}
                                                            value={c}
                                                        >
                                                            {c}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Estado
                                            </Label>
                                            <Select
                                                value={data.estado}
                                                onValueChange={(v) =>
                                                    setData('estado', v)
                                                }
                                            >
                                                <SelectTrigger className="h-11 rounded-xl border-2 border-none border-primary/20 bg-muted/10 font-bold">
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
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Fecha Inicio
                                            </Label>
                                            <Input
                                                type="date"
                                                value={data.fecha_inicio}
                                                onChange={(e) =>
                                                    setData(
                                                        'fecha_inicio',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-11 rounded-xl border-none bg-muted/30 text-center font-bold"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Fecha Fin
                                            </Label>
                                            <Input
                                                type="date"
                                                value={data.fecha_fin}
                                                onChange={(e) =>
                                                    setData(
                                                        'fecha_fin',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-11 rounded-xl border-none bg-muted/30 text-center font-bold"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Objetivo Principal
                                        </Label>
                                        <Input
                                            placeholder="Ej: Aumentar leads en un 20%"
                                            value={data.objetivo}
                                            onChange={(e) =>
                                                setData(
                                                    'objetivo',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-11 rounded-xl border-none bg-muted/30 font-bold"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Columna: Métricas y Presupuesto */}
                            <div className="space-y-6">
                                <h3 className="flex items-center gap-2 text-[11px] font-black tracking-widest text-primary uppercase">
                                    <TrendingUp className="h-4 w-4" /> Métricas
                                    y Finanzas
                                </h3>
                                <div className="space-y-4 rounded-[40px] border-2 border-dashed border-primary/20 bg-primary/5 p-8">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Presupuesto (CLP)
                                            </Label>
                                            <div className="relative">
                                                <DollarSign className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-primary" />
                                                <Input
                                                    type="number"
                                                    value={data.presupuesto}
                                                    onChange={(e) =>
                                                        setData(
                                                            'presupuesto',
                                                            parseFloat(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="h-11 rounded-xl border-none bg-background pl-10 font-black text-primary shadow-sm"
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Gasto Real
                                            </Label>
                                            <div className="relative">
                                                <DollarSign className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-red-500" />
                                                <Input
                                                    type="number"
                                                    value={
                                                        data.presupuesto_real
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'presupuesto_real',
                                                            parseFloat(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="h-11 rounded-xl border-none bg-background pl-10 font-black text-red-600 shadow-sm"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                                        <div className="space-y-2">
                                            <Label className="block text-center text-[9px] font-black text-muted-foreground uppercase">
                                                Visitas
                                            </Label>
                                            <Input
                                                type="number"
                                                value={data.visitas}
                                                onChange={(e) =>
                                                    setData(
                                                        'visitas',
                                                        parseInt(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                className="h-10 rounded-xl border-none bg-background text-center font-bold shadow-sm"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="block text-center text-[9px] font-black text-muted-foreground uppercase">
                                                Leads
                                            </Label>
                                            <Input
                                                type="number"
                                                value={data.leads}
                                                onChange={(e) =>
                                                    setData(
                                                        'leads',
                                                        parseInt(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                className="h-10 rounded-xl border-none bg-background text-center font-bold shadow-sm"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="block text-center text-[9px] font-black text-muted-foreground uppercase">
                                                Conv.
                                            </Label>
                                            <Input
                                                type="number"
                                                value={data.conversiones}
                                                onChange={(e) =>
                                                    setData(
                                                        'conversiones',
                                                        parseInt(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                className="h-10 rounded-xl border-none bg-background text-center font-bold shadow-sm"
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            ROI Calculado (%)
                                        </Label>
                                        <div className="relative">
                                            <TrendingUp className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-blue-500" />
                                            <Input
                                                type="number"
                                                value={data.roi}
                                                onChange={(e) =>
                                                    setData(
                                                        'roi',
                                                        parseFloat(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                className="h-11 rounded-xl border-none bg-background pl-10 font-black text-blue-600 shadow-sm"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 space-y-2 border-t pt-6">
                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                Descripción Detallada / Estrategia
                            </Label>
                            <textarea
                                value={data.descripcion}
                                onChange={(e) =>
                                    setData('descripcion', e.target.value)
                                }
                                className="flex min-h-[100px] w-full rounded-2xl border-none bg-muted/30 px-4 py-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                placeholder="Escribe aquí los detalles de la campaña..."
                            />
                        </div>

                        <DialogFooter className="mt-8 gap-2 border-t pt-6">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full px-8 font-bold text-muted-foreground"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-full bg-primary px-12 font-black shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <CheckCircle2 className="mr-2 h-4 w-4" />{' '}
                                {editando
                                    ? 'Guardar Cambios'
                                    : 'Lanzar Campaña'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal de Ver Campaña */}
            <Dialog open={verOpen} onOpenChange={setVerOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-3xl">
                    {campanaVer && (
                        <>
                            <DialogHeader className="relative overflow-visible bg-gradient-to-r from-primary/10 to-transparent p-6 pb-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="mb-2 flex items-center gap-2">
                                            <Megaphone className="h-5 w-5 text-primary" />
                                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                                Detalle de Campaña
                                            </span>
                                        </div>
                                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                                            {campanaVer.nombre}
                                        </DialogTitle>
                                    </div>
                                    <Badge
                                        className={`${getEstadoConfig(campanaVer.estado).color} rounded-full border-none px-3 py-1 text-[10px] font-black uppercase`}
                                    >
                                        {
                                            getEstadoConfig(campanaVer.estado)
                                                .label
                                        }
                                    </Badge>
                                </div>
                            </DialogHeader>

                            <div className="space-y-6 p-6">
                                {campanaVer.descripcion && (
                                    <div className="rounded-2xl border border-muted/50 bg-muted/30 p-4">
                                        <div className="mb-1 text-[10px] font-black text-muted-foreground uppercase">
                                            Descripción
                                        </div>
                                        <p className="text-sm text-foreground">
                                            {campanaVer.descripcion}
                                        </p>
                                    </div>
                                )}

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="rounded-2xl border border-blue-100 bg-blue-50/50 p-4">
                                        <div className="mb-1 text-[10px] font-black text-blue-600 uppercase">
                                            Presupuesto
                                        </div>
                                        <div className="text-xl font-black text-foreground">
                                            {formatCurrency(
                                                campanaVer.presupuesto,
                                            )}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-purple-100 bg-purple-50/50 p-4">
                                        <div className="mb-1 text-[10px] font-black text-purple-600 uppercase">
                                            Canal
                                        </div>
                                        <div className="text-lg font-black text-foreground">
                                            {campanaVer.canal || 'Directo'}
                                        </div>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div className="rounded-2xl border border-muted/50 bg-muted/30 p-4 text-center">
                                        <div className="mb-1 text-[10px] font-black text-muted-foreground uppercase">
                                            Alcance
                                        </div>
                                        <div className="text-2xl font-black text-foreground">
                                            {campanaVer.alcance || 0}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-muted/50 bg-muted/30 p-4 text-center">
                                        <div className="mb-1 text-[10px] font-black text-muted-foreground uppercase">
                                            Conversiones
                                        </div>
                                        <div className="text-2xl font-black text-green-600">
                                            {campanaVer.conversiones || 0}
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-muted/50 bg-muted/30 p-4 text-center">
                                        <div className="mb-1 text-[10px] font-black text-muted-foreground uppercase">
                                            Costo por Klik
                                        </div>
                                        <div className="text-xl font-black text-foreground">
                                            {campanaVer.alcance
                                                ? formatCurrency(
                                                      campanaVer.presupuesto /
                                                          campanaVer.alcance,
                                                  )
                                                : '$0'}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between rounded-2xl border border-muted/50 bg-muted/30 p-4">
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-xs font-bold text-muted-foreground uppercase">
                                            Fecha de inicio
                                        </span>
                                    </div>
                                    <span className="font-black text-foreground">
                                        {campanaVer.fecha_inicio
                                            ? formatDateCLP(
                                                  campanaVer.fecha_inicio,
                                              )
                                            : 'No definida'}
                                    </span>
                                </div>
                            </div>

                            <DialogFooter className="p-6 pt-0">
                                <Button
                                    variant="outline"
                                    onClick={() => setVerOpen(false)}
                                    className="rounded-full px-8 font-bold"
                                >
                                    Cerrar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
