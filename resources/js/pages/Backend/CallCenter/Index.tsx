import { Head, useForm, router } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Trash2,
    Search,
    X,
    Phone,
    PhoneCall,
    PhoneIncoming,
    PhoneOutgoing,
    PhoneMissed,
    Clock,
    User,
    CheckCircle2,
    AlertCircle,
    TrendingUp,
    MessageSquare,
    Check,
    Ban,
    Hash,
    Mic,
    CalendarPlus,
    Bell,
    Calendar,
    MoreHorizontal,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { BulkActions } from '@/components/shared/BulkActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {} from '@/components/ui/dropdown-menu';
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
import { Toaster } from '@/components/ui/sonner';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatDateCLP } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Contacto {
    id: number | null;
    nombre: string;
    telefono: string | null;
    tipo: 'cliente' | 'prospecto' | 'empleado' | 'marketplace';
}

interface Llamada {
    id: number;
    user_id: number;
    cliente_id: number | null;
    prospecto_id: number | null;
    tipo: 'entrante' | 'saliente';
    numero_telefono: string | null;
    estado: 'completada' | 'perdida' | 'ocupado' | 'no_contesta' | 'equivocado';
    duracion: number;
    fecha: string;
    notas: string | null;
    cliente?: { id: number; nombre: string };
    prospecto?: { id: number; nombre: string };
    gestiones?: any[];
}

interface Programacion {
    id: number;
    titulo: string;
    descripcion: string | null;
    numero_telefono: string | null;
    fecha_programada: string;
    recordatorio_minutos: number;
    completada: boolean;
    notificado_at: string | null;
    user: { id: number; name: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customer Support', href: '/call-center' },
    { title: 'Centro de Llamadas', href: '/call-center' },
];

const ESTADOS = [
    {
        value: 'completada',
        label: 'Completada',
        color: 'bg-green-500/10 text-green-600',
        icon: CheckCircle2,
    },
    {
        value: 'perdida',
        label: 'Perdida',
        color: 'bg-red-500/10 text-red-600',
        icon: PhoneMissed,
    },
    {
        value: 'ocupado',
        label: 'Ocupado',
        color: 'bg-orange-500/10 text-orange-600',
        icon: Ban,
    },
    {
        value: 'no_contesta',
        label: 'Sin Respuesta',
        color: 'bg-yellow-500/10 text-yellow-600',
        icon: Clock,
    },
    {
        value: 'equivocado',
        label: 'Equivocado',
        color: 'bg-slate-500/10 text-slate-600',
        icon: AlertCircle,
    },
];

export default function Index({
    llamadas,
    contactos,
    stats,
    filters,
    programaciones = [],
}: {
    llamadas: { data: Llamada[]; links: any[]; meta: any };
    contactos: Contacto[];
    stats: {
        total_llamadas: number;
        llamadas_hoy: number;
        gestiones_hoy: number;
        promedio_duracion: number;
    };
    filters: {
        search?: string;
        tipo?: string;
        estado?: string;
    };
    programaciones?: Programacion[];
}) {
    const { currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('comercial.call-center.create');
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const canEdit = hasPermission('comercial.call-center.edit');
    const canDelete = hasPermission('comercial.call-center.delete');
    const [isOpen, setIsOpen] = useState(false);
    const [isProgOpen, setIsProgOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [tipoFilter, setTipoFilter] = useState(filters.tipo || 'all');
    const [estadoFilter, setEstadoFilter] = useState(filters.estado || 'all');
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [menuOpenId, setMenuOpenId] = useState<number | null>(null);
    const [now, setNow] = useState(() => Date.now());
    const [fechaProgramadaInicial] = useState(() =>
        new Date(Date.now() + 3600000)
            .toLocaleString('sv-SE', { timeZone: 'America/Santiago' })
            .replace(' ', 'T')
            .substring(0, 16),
    );

    useEffect(() => {
        const interval = window.setInterval(() => setNow(Date.now()), 60_000);

        return () => window.clearInterval(interval);
    }, []);

    const { data, setData, post, reset, processing } = useForm({
        cliente_id: '' as string | number,
        prospecto_id: '' as string | number,
        tipo: 'saliente',
        numero_telefono: '',
        estado: 'completada',
        duracion: 0,
        fecha: new Date()
            .toLocaleString('sv-SE', { timeZone: 'America/Santiago' })
            .replace(' ', 'T')
            .substring(0, 16),
        notas: '',
    });

    const {
        data: progData,
        setData: setProgData,
        post: postProg,
        reset: resetProg,
        processing: progProcessing,
    } = useForm({
        titulo: '',
        descripcion: '',
        contacto_type: '' as string,
        contacto_id: '' as string | number,
        numero_telefono: '',
        fecha_programada: fechaProgramadaInicial,
        recordatorio_minutos: 5,
    });

    const handleSelectContactoProg = (v: string) => {
        if (v === 'manual') {
            setProgData({
                ...progData,
                contacto_type: '',
                contacto_id: '',
                titulo: '',
                numero_telefono: '',
            });
            return;
        }
        const [type, id] = v.split('-');
        const contacto = contactos.find(
            (c) => c.tipo === type && String(c.id) === id,
        );
        setProgData({
            ...progData,
            contacto_type: type,
            contacto_id: id,
            titulo: contacto?.nombre || progData.titulo,
            numero_telefono: contacto?.telefono || progData.numero_telefono,
        });
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (tipoFilter !== 'all') query.tipo = tipoFilter;
            if (estadoFilter !== 'all') query.estado = estadoFilter;

            router.get('/call-center', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, tipoFilter, estadoFilter]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/call-center/llamadas', {
            onSuccess: () => {
                setIsOpen(false);
                reset();
                toast.success('Llamada registrada correctamente');
            },
        });
    };

    const handleSubmitProgramacion = (e: React.FormEvent) => {
        e.preventDefault();
        postProg('/call-center/programaciones', {
            onSuccess: () => {
                setIsProgOpen(false);
                resetProg();
                toast.success('Llamada programada correctamente');
            },
        });
    };

    const handleDeleteProgramacion = (id: number) => {
        if (confirm('¿Desea eliminar esta programación?')) {
            router.delete(`/call-center/programaciones/${id}`, {
                onSuccess: () => toast.success('Programación eliminada'),
            });
        }
    };

    const handleCompleteProgramacion = (id: number) => {
        router.patch(
            `/call-center/programaciones/${id}/completar`,
            {},
            {
                onSuccess: () =>
                    toast.success('Llamada marcada como completada'),
            },
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Desea eliminar este registro de llamada?')) {
            router.delete(`/call-center/llamadas/${id}`, {
                onSuccess: () => toast.success('Registro eliminado'),
            });
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

    const getTipoIcon = (tipo: string) => {
        return tipo === 'entrante' ? (
            <PhoneIncoming className="h-4 w-4 text-emerald-500" />
        ) : (
            <PhoneOutgoing className="h-4 w-4 text-green-500" />
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Call Center Management" />
            <Toaster position="bottom-right" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="mb-1 flex items-center gap-2">
                            <Mic className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Voice Response Center
                            </span>
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Call Center
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Gestión centralizada de comunicaciones y atención al
                            cliente
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <BulkActions
                            baseUrl="/call-center"
                            filters={{
                                search: searchTerm,
                                tipo: tipoFilter,
                                estado: estadoFilter,
                            }}
                            modelName="Llamadas"
                        />

                        {canCreate && (
                            <Button
                                onClick={() => {
                                    reset();
                                    setIsOpen(true);
                                }}
                                className="h-9 rounded-full bg-primary px-5 font-bold shadow-lg shadow-primary/20 transition-all hover:bg-primary/90"
                            >
                                <PhoneCall className="mr-2 h-4 w-4" /> Registrar
                                Llamada
                            </Button>
                        )}
                        {canCreate && (
                            <Button
                                onClick={() => {
                                    resetProg();
                                    setIsProgOpen(true);
                                }}
                                variant="outline"
                                className="h-9 rounded-full border-primary/30 px-5 font-bold text-primary transition-all hover:bg-primary/5"
                            >
                                <CalendarPlus className="mr-2 h-4 w-4" />{' '}
                                Programar Llamada
                            </Button>
                        )}
                    </div>
                </div>

                {/* Performance Dashboard */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    {[
                        {
                            label: 'Llamadas Totales',
                            val: stats.total_llamadas,
                            icon: Phone,
                            cardBg: 'bg-emerald-500/5',
                            labelColor: 'text-emerald-600/70',
                            valColor: 'text-emerald-700',
                            iconBg: 'bg-emerald-500/10',
                            iconColor: 'text-emerald-600',
                        },
                        {
                            label: 'Atención Hoy',
                            val: stats.llamadas_hoy,
                            icon: PhoneIncoming,
                            cardBg: 'bg-green-500/5',
                            labelColor: 'text-green-600/70',
                            valColor: 'text-green-700',
                            iconBg: 'bg-green-500/10',
                            iconColor: 'text-green-600',
                        },
                        {
                            label: 'Gestiones Hoy',
                            val: stats.gestiones_hoy,
                            icon: MessageSquare,
                            cardBg: 'bg-purple-500/5',
                            labelColor: 'text-purple-600/70',
                            valColor: 'text-purple-700',
                            iconBg: 'bg-purple-500/10',
                            iconColor: 'text-purple-600',
                        },
                        {
                            label: 'Duración Promedio',
                            val: `${stats.promedio_duracion}s`,
                            icon: Clock,
                            cardBg: 'bg-orange-500/5',
                            labelColor: 'text-orange-600/70',
                            valColor: 'text-orange-700',
                            iconBg: 'bg-orange-500/10',
                            iconColor: 'text-orange-600',
                        },
                    ].map((s, idx) => (
                        <Card
                            key={idx}
                            className={`border-none shadow-sm ${s.cardBg} rounded-3xl`}
                        >
                            <CardContent className="flex items-center justify-between p-6">
                                <div>
                                    <p
                                        className={`text-[10px] font-black tracking-widest uppercase ${s.labelColor} mb-1`}
                                    >
                                        {s.label}
                                    </p>
                                    <h3
                                        className={`text-2xl font-black ${s.valColor}`}
                                    >
                                        {s.val}
                                    </h3>
                                </div>
                                <div
                                    className={`h-12 w-12 rounded-2xl ${s.iconBg} flex items-center justify-center ${s.iconColor}`}
                                >
                                    <s.icon className="h-6 w-6" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Upcoming Scheduled Calls */}
                {programaciones.length > 0 && (
                    <Card className="overflow-hidden rounded-[32px] border-none bg-gradient-to-br from-primary/5 via-transparent to-transparent shadow-xl shadow-foreground/5">
                        <CardHeader className="px-6 pt-6 pb-3">
                            <div className="flex items-center gap-2">
                                <Bell className="h-5 w-5 text-primary" />
                                <CardTitle className="text-base font-black">
                                    Recordatorios de llamadas
                                </CardTitle>
                                <Badge
                                    variant="secondary"
                                    className="ml-1 text-[10px] font-black"
                                >
                                    {programaciones.length} pendientes
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="px-6 pb-6">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {programaciones.map((p) => {
                                    const fecha = new Date(p.fecha_programada);
                                    const diffMs = fecha.getTime() - now;
                                    const diffMin = Math.max(
                                        0,
                                        Math.floor(diffMs / 60000),
                                    );
                                    const horas = Math.floor(diffMin / 60);
                                    const minutos = diffMin % 60;
                                    const proximo = diffMin <= 60;

                                    return (
                                        <div
                                            key={p.id}
                                            className={`relative flex flex-col gap-2 rounded-2xl border p-4 shadow-sm transition-all hover:shadow-md ${proximo ? 'border-primary/40 bg-primary/5' : 'bg-card'}`}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex min-w-0 items-center gap-2">
                                                    <div
                                                        className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${proximo ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'}`}
                                                    >
                                                        <Calendar className="h-4 w-4" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-bold">
                                                            {p.titulo}
                                                        </p>
                                                        {p.numero_telefono && (
                                                            <p className="truncate text-[11px] font-semibold text-muted-foreground">
                                                                {
                                                                    p.numero_telefono
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="relative shrink-0">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-7 w-7 rounded-full"
                                                        onClick={() =>
                                                            setMenuOpenId(
                                                                menuOpenId ===
                                                                    p.id
                                                                    ? null
                                                                    : p.id,
                                                            )
                                                        }
                                                    >
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                    {menuOpenId === p.id && (
                                                        <div className="absolute top-full right-0 z-50 mt-1 w-40 rounded-xl border bg-popover p-1 shadow-lg">
                                                            <button
                                                                onClick={() => {
                                                                    handleCompleteProgramacion(
                                                                        p.id,
                                                                    );
                                                                    setMenuOpenId(
                                                                        null,
                                                                    );
                                                                }}
                                                                className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-green-600 hover:bg-green-50"
                                                            >
                                                                <Check className="h-3.5 w-3.5" />{' '}
                                                                Marcar
                                                                completada
                                                            </button>
                                                            <button
                                                                onClick={() => {
                                                                    handleDeleteProgramacion(
                                                                        p.id,
                                                                    );
                                                                    setMenuOpenId(
                                                                        null,
                                                                    );
                                                                }}
                                                                className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50"
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5" />{' '}
                                                                Eliminar
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2 text-[11px] font-semibold text-muted-foreground">
                                                <Clock className="h-3 w-3" />
                                                <span>
                                                    {fecha.toLocaleDateString(
                                                        currency.locale,
                                                        {
                                                            day: 'numeric',
                                                            month: 'short',
                                                            year: 'numeric',
                                                        },
                                                    )}
                                                </span>
                                                <span className="font-black">
                                                    {fecha.toLocaleTimeString(
                                                        currency.locale,
                                                        {
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        },
                                                    )}
                                                </span>
                                            </div>
                                            <div
                                                className={`text-[10px] font-black tracking-wider uppercase ${proximo ? 'text-primary' : 'text-muted-foreground'}`}
                                            >
                                                {diffMin === 0
                                                    ? 'Ya deberías estar llamando'
                                                    : `en ${horas > 0 ? `${horas}h ` : ''}${minutos}min`}
                                            </div>
                                            {proximo && (
                                                <div className="absolute top-2 right-12">
                                                    <span className="relative flex h-2 w-2">
                                                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                                                        <span className="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6">
                    {/* Filters Bar */}
                    <div className="flex flex-col gap-4 rounded-3xl border border-muted/50 bg-muted/40 p-4 md:flex-row md:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Buscar por teléfono o nombre..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="h-11 rounded-2xl border-none bg-background pl-12 shadow-sm focus-visible:ring-primary/20"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Select
                                value={tipoFilter}
                                onValueChange={setTipoFilter}
                            >
                                <SelectTrigger className="h-11 w-full rounded-2xl border-none bg-background font-bold shadow-sm sm:w-[150px]">
                                    <SelectValue placeholder="Tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tipos</SelectItem>
                                    <SelectItem value="entrante">
                                        Entrantes
                                    </SelectItem>
                                    <SelectItem value="saliente">
                                        Salientes
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={estadoFilter}
                                onValueChange={setEstadoFilter}
                            >
                                <SelectTrigger className="h-11 w-full rounded-2xl border-none bg-background font-bold shadow-sm sm:w-[160px]">
                                    <SelectValue placeholder="Estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Estados</SelectItem>
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
                                    setTipoFilter('all');
                                    setEstadoFilter('all');
                                }}
                            >
                                <X className="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    <Card className="overflow-hidden rounded-[32px] border-none shadow-xl shadow-foreground/5">
                        <CardHeader className="px-6 pt-6 pb-0">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-lg font-black">
                                        Registro de Llamadas
                                    </CardTitle>
                                    <CardDescription>
                                        {llamadas.data.length} llamadas
                                        registradas
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-1 rounded-lg border bg-muted/30 p-0.5">
                                    <button
                                        onClick={() => setViewMode('table')}
                                        className={`rounded-md p-1.5 transition-colors ${viewMode === 'table' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                        title="Vista tabla"
                                    >
                                        <List className="h-4 w-4" />
                                    </button>
                                    <button
                                        onClick={() => setViewMode('cards')}
                                        className={`rounded-md p-1.5 transition-colors ${viewMode === 'cards' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                        title="Vista tarjetas"
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {viewMode === 'table' ? (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b bg-muted/5 text-[11px] font-black tracking-wider text-muted-foreground uppercase">
                                                    <th className="px-6 py-4 text-left">
                                                        Dirección
                                                    </th>
                                                    <th className="px-6 py-4 text-left">
                                                        Contacto / Cliente
                                                    </th>
                                                    <th className="px-6 py-4 text-center">
                                                        Estado
                                                    </th>
                                                    <th className="px-6 py-4 text-center">
                                                        Duración
                                                    </th>
                                                    <th className="px-6 py-4 text-center">
                                                        Fecha / Hora
                                                    </th>
                                                    <th className="px-6 py-4 text-right">
                                                        Acciones
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-muted/50">
                                                {llamadas.data.map((l) => (
                                                    <tr
                                                        key={l.id}
                                                        className="group transition-colors hover:bg-muted/30"
                                                    >
                                                        <td className="px-6 py-4">
                                                            <div className="flex items-center gap-2">
                                                                <div className="flex h-10 w-10 items-center justify-center rounded-full border bg-background shadow-sm transition-transform group-hover:scale-110">
                                                                    {getTipoIcon(
                                                                        l.tipo,
                                                                    )}
                                                                </div>
                                                                <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                                                    {l.tipo}
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex flex-col">
                                                                <div className="flex items-center gap-2 text-sm font-bold text-foreground">
                                                                    {l.cliente
                                                                        ?.nombre ||
                                                                        l
                                                                            .prospecto
                                                                            ?.nombre ||
                                                                        'Contacto Desconocido'}
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="px-1.5 py-0 text-[8px] font-black uppercase opacity-70"
                                                                    >
                                                                        {l.cliente_id
                                                                            ? 'Cliente'
                                                                            : l.prospecto_id
                                                                              ? 'Prospecto'
                                                                              : 'Directa'}
                                                                    </Badge>
                                                                </div>
                                                                <div className="mt-0.5 flex items-center gap-1 text-[10px] font-bold text-primary">
                                                                    <Hash className="h-2.5 w-2.5" />{' '}
                                                                    {l.numero_telefono ||
                                                                        'S/N'}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-center">
                                                            <Badge
                                                                variant="outline"
                                                                className={`${getEstadoConfig(l.estado).color} rounded-full border-none px-2.5 py-1 text-[9px] font-black uppercase`}
                                                            >
                                                                {
                                                                    getEstadoConfig(
                                                                        l.estado,
                                                                    ).label
                                                                }
                                                            </Badge>
                                                        </td>
                                                        <td className="px-6 py-4 text-center">
                                                            <div className="inline-block rounded-lg bg-muted/30 px-2 py-1 text-xs font-black text-foreground">
                                                                {Math.floor(
                                                                    l.duracion /
                                                                        60,
                                                                )}
                                                                m{' '}
                                                                {l.duracion %
                                                                    60}
                                                                s
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-center">
                                                            <div className="text-xs font-bold text-foreground">
                                                                {formatDateCLP(
                                                                    l.fecha,
                                                                )}
                                                            </div>
                                                            <div className="text-[9px] font-bold text-muted-foreground uppercase">
                                                                {l.fecha.split(
                                                                    ' ',
                                                                )[1] || ''}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <div className="flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8 rounded-xl text-primary hover:bg-primary/10"
                                                                >
                                                                    <TrendingUp className="h-4 w-4" />
                                                                </Button>
                                                                {canDelete && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 rounded-xl text-destructive hover:bg-destructive/10"
                                                                        onClick={() =>
                                                                            handleDelete(
                                                                                l.id,
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
                                    <div className="border-t border-muted/50 bg-muted/5 p-6">
                                        <Pagination
                                            links={llamadas.links}
                                            meta={llamadas.meta}
                                        />
                                    </div>
                                </>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {llamadas.data.length === 0 ? (
                                        <div className="col-span-full flex flex-col items-center justify-center gap-2 py-12 text-center text-muted-foreground">
                                            <Phone className="h-8 w-8 text-muted-foreground/50" />
                                            <p className="text-lg font-semibold">
                                                No hay llamadas registradas
                                            </p>
                                            <p className="text-sm">
                                                Prueba ajustando los filtros.
                                            </p>
                                        </div>
                                    ) : (
                                        llamadas.data.map((l) => (
                                            <div
                                                key={l.id}
                                                className="flex flex-col gap-3 rounded-2xl border bg-card p-4 shadow-sm"
                                            >
                                                <div className="flex items-start justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-full border bg-background shadow-sm">
                                                            {getTipoIcon(
                                                                l.tipo,
                                                            )}
                                                        </div>
                                                        <div>
                                                            <div className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                                                {l.tipo}
                                                            </div>
                                                            <div className="text-sm font-bold">
                                                                {l.cliente
                                                                    ?.nombre ||
                                                                    l.prospecto
                                                                        ?.nombre ||
                                                                    'Contacto Desconocido'}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <Badge
                                                        variant="outline"
                                                        className={`${getEstadoConfig(l.estado).color} rounded-full border-none px-2.5 py-1 text-[9px] font-black uppercase`}
                                                    >
                                                        {
                                                            getEstadoConfig(
                                                                l.estado,
                                                            ).label
                                                        }
                                                    </Badge>
                                                </div>
                                                <div className="space-y-1.5 text-sm">
                                                    <div className="flex items-center gap-1 text-xs font-bold text-primary">
                                                        <Hash className="h-3 w-3" />{' '}
                                                        {l.numero_telefono ||
                                                            'S/N'}
                                                    </div>
                                                    <div className="flex justify-between text-xs">
                                                        <span className="text-muted-foreground">
                                                            Duración:
                                                        </span>
                                                        <span className="font-black">
                                                            {Math.floor(
                                                                l.duracion / 60,
                                                            )}
                                                            m {l.duracion % 60}s
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between text-xs">
                                                        <span className="text-muted-foreground">
                                                            Fecha:
                                                        </span>
                                                        <span className="font-bold">
                                                            {formatDateCLP(
                                                                l.fecha,
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="mt-auto flex justify-end gap-1 border-t pt-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 rounded-xl text-primary hover:bg-primary/10"
                                                    >
                                                        <TrendingUp className="h-4 w-4" />
                                                    </Button>
                                                    {canDelete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 rounded-xl text-destructive hover:bg-destructive/10"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    l.id,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-w-[95vw] overflow-y-auto rounded-[32px] border-none p-0 shadow-2xl md:max-w-3xl">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-8 pb-4 text-left">
                        <div className="mb-1 flex items-center gap-2">
                            <PhoneCall className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Communication Log Entry
                            </span>
                        </div>
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                            Registar Llamada
                        </DialogTitle>
                        <DialogDescription className="font-medium text-muted-foreground">
                            Ingrese los detalles de la interacción telefónica
                            para mantener el historial actualizado.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="p-8 pt-2">
                        <div className="grid grid-cols-1 gap-8 py-4 md:grid-cols-2">
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                        Tipo de Comunicación
                                    </Label>
                                    <div className="grid grid-cols-2 gap-2 rounded-2xl bg-muted/20 p-1">
                                        <Button
                                            type="button"
                                            variant={
                                                data.tipo === 'saliente'
                                                    ? 'default'
                                                    : 'ghost'
                                            }
                                            onClick={() =>
                                                setData('tipo', 'saliente')
                                            }
                                            className="h-10 rounded-xl font-bold"
                                        >
                                            Saliente
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={
                                                data.tipo === 'entrante'
                                                    ? 'default'
                                                    : 'ghost'
                                            }
                                            onClick={() =>
                                                setData('tipo', 'entrante')
                                            }
                                            className="h-10 rounded-xl font-bold"
                                        >
                                            Entrante
                                        </Button>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                        Vincular Contacto
                                    </Label>
                                    <Select
                                        value={
                                            data.cliente_id
                                                ? `c-${data.cliente_id}`
                                                : data.prospecto_id
                                                  ? `p-${data.prospecto_id}`
                                                  : ''
                                        }
                                        onValueChange={(v) => {
                                            if (v.startsWith('c-')) {
                                                setData({
                                                    ...data,
                                                    cliente_id: v.split('-')[1],
                                                    prospecto_id: '',
                                                });
                                            } else if (v.startsWith('p-')) {
                                                setData({
                                                    ...data,
                                                    prospecto_id:
                                                        v.split('-')[1],
                                                    cliente_id: '',
                                                });
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="h-12 rounded-2xl border-none bg-muted/30 font-bold">
                                            <SelectValue placeholder="Seleccione un contacto..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {contactos.map((c, idx) => (
                                                <SelectItem
                                                    key={idx}
                                                    value={`${c.tipo === 'cliente' ? 'c' : 'p'}-${c.id}`}
                                                >
                                                    <span className="flex items-center gap-2">
                                                        <User className="h-3 w-3 opacity-50" />{' '}
                                                        {c.nombre}{' '}
                                                        <Badge
                                                            variant="outline"
                                                            className="text-[7px]"
                                                        >
                                                            {c.tipo}
                                                        </Badge>
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                        Número Telefónico
                                    </Label>
                                    <div className="relative">
                                        <Hash className="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={data.numero_telefono}
                                            onChange={(e) =>
                                                setData(
                                                    'numero_telefono',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-12 rounded-2xl border-none bg-muted/30 pl-12 font-bold"
                                            placeholder="+56 9 ..."
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-6">
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                    <div className="space-y-2">
                                        <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                            Estado Resultante
                                        </Label>
                                        <Select
                                            value={data.estado}
                                            onValueChange={(v: any) =>
                                                setData('estado', v)
                                            }
                                        >
                                            <SelectTrigger className="h-12 rounded-2xl border-2 border-none border-primary/20 bg-muted/10 font-bold">
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
                                        <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                            Duración (seg)
                                        </Label>
                                        <div className="relative">
                                            <Clock className="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                type="number"
                                                value={data.duracion}
                                                onChange={(e) =>
                                                    setData(
                                                        'duracion',
                                                        parseInt(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                className="h-12 rounded-2xl border-none bg-muted/30 pl-12 font-black"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                        Fecha y Hora
                                    </Label>
                                    <Input
                                        type="datetime-local"
                                        value={data.fecha}
                                        onChange={(e) =>
                                            setData('fecha', e.target.value)
                                        }
                                        className="h-12 rounded-2xl border-none bg-muted/30 text-center font-bold"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                        Notas de la Conversación
                                    </Label>
                                    <textarea
                                        value={data.notas || ''}
                                        onChange={(e) =>
                                            setData('notas', e.target.value)
                                        }
                                        className="flex min-h-[100px] w-full rounded-[24px] border-none bg-muted/30 px-5 py-4 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                        placeholder="Breve resumen de lo conversado..."
                                    />
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="mt-8 gap-2 border-t pt-6 font-black">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full px-8"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-full bg-primary px-12 shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <Check className="mr-2 h-4 w-4" /> Registrar
                                Comunicación
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={isProgOpen} onOpenChange={setIsProgOpen}>
                <DialogContent className="max-w-[95vw] overflow-y-auto rounded-[32px] border-none p-0 shadow-2xl md:max-w-xl">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-8 pb-4 text-left">
                        <div className="mb-1 flex items-center gap-2">
                            <CalendarPlus className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Scheduled Call
                            </span>
                        </div>
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                            Programar Llamada
                        </DialogTitle>
                        <DialogDescription className="font-medium text-muted-foreground">
                            Agenda una llamada y recibe un recordatorio antes de
                            la fecha programada.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={handleSubmitProgramacion}
                        className="p-8 pt-2"
                    >
                        <div className="grid grid-cols-1 gap-6 py-4">
                            <div className="space-y-2">
                                <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                    Vincular Contacto
                                </Label>
                                <Select
                                    value={
                                        progData.contacto_type &&
                                        progData.contacto_id
                                            ? `${progData.contacto_type}-${progData.contacto_id}`
                                            : ''
                                    }
                                    onValueChange={handleSelectContactoProg}
                                >
                                    <SelectTrigger className="h-12 rounded-2xl border-none bg-muted/30 font-bold">
                                        <SelectValue placeholder="Seleccione un contacto..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="manual">
                                            <span className="flex items-center gap-2">
                                                <User className="h-3 w-3 opacity-50" />{' '}
                                                Escribir manualmente
                                            </span>
                                        </SelectItem>
                                        {contactos.map((c, idx) => (
                                            <SelectItem
                                                key={idx}
                                                value={`${c.tipo}-${c.id}`}
                                            >
                                                <span className="flex items-center gap-2">
                                                    <User className="h-3 w-3 opacity-50" />{' '}
                                                    {c.nombre}{' '}
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[7px]"
                                                    >
                                                        {c.tipo}
                                                    </Badge>
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                    Título *
                                </Label>
                                <Input
                                    value={progData.titulo}
                                    onChange={(e) =>
                                        setProgData('titulo', e.target.value)
                                    }
                                    className="h-12 rounded-2xl border-none bg-muted/30 font-bold"
                                    placeholder="Ej: Seguimiento cliente VIP"
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                    Descripción
                                </Label>
                                <textarea
                                    value={progData.descripcion || ''}
                                    onChange={(e) =>
                                        setProgData(
                                            'descripcion',
                                            e.target.value,
                                        )
                                    }
                                    className="flex min-h-[80px] w-full rounded-[24px] border-none bg-muted/30 px-5 py-4 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                    placeholder="Motivo o contexto de la llamada..."
                                />
                            </div>

                            <div className="space-y-2">
                                <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                    Número Telefónico
                                </Label>
                                <div className="relative">
                                    <Phone className="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={progData.numero_telefono}
                                        onChange={(e) =>
                                            setProgData(
                                                'numero_telefono',
                                                e.target.value,
                                            )
                                        }
                                        className="h-12 rounded-2xl border-none bg-muted/30 pl-12 font-bold"
                                        placeholder="+56 9 ..."
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                        Fecha y Hora *
                                    </Label>
                                    <Input
                                        type="datetime-local"
                                        value={progData.fecha_programada}
                                        onChange={(e) =>
                                            setProgData(
                                                'fecha_programada',
                                                e.target.value,
                                            )
                                        }
                                        className="h-12 rounded-2xl border-none bg-muted/30 text-center font-bold"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-black tracking-wider text-muted-foreground uppercase">
                                        Recordatorio
                                    </Label>
                                    <Select
                                        value={String(
                                            progData.recordatorio_minutos,
                                        )}
                                        onValueChange={(v) =>
                                            setProgData(
                                                'recordatorio_minutos',
                                                parseInt(v),
                                            )
                                        }
                                    >
                                        <SelectTrigger className="h-12 rounded-2xl border-none bg-muted/30 font-bold">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="5">
                                                5 minutos antes
                                            </SelectItem>
                                            <SelectItem value="10">
                                                10 minutos antes
                                            </SelectItem>
                                            <SelectItem value="15">
                                                15 minutos antes
                                            </SelectItem>
                                            <SelectItem value="30">
                                                30 minutos antes
                                            </SelectItem>
                                            <SelectItem value="60">
                                                1 hora antes
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="mt-8 gap-2 border-t pt-6 font-black">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsProgOpen(false)}
                                className="rounded-full px-8"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={progProcessing}
                                className="rounded-full bg-primary px-12 shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <CalendarPlus className="mr-2 h-4 w-4" />{' '}
                                Programar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
