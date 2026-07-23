import { Head, useForm, router } from '@inertiajs/react';
import {
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Kanban,
    List,
    Phone,
    Mail,
    Check,
    AlertCircle,
    TrendingUp,
    Building2,
    LayoutGrid,
    UserPlus,
    Briefcase,
    DollarSign
} from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';
import { toast } from 'sonner';
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
import { WhatsAppButton } from '@/components/whatsapp-button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateCLP, formatRut } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Prospecto {
    id: number;
    nombre: string;
    rut: string | null;
    email: string | null;
    telefono: string | null;
    empresa: string | null;
    giro: string | null;
    cargo: string | null;
    direccion: string | null;
    comuna: string | null;
    region: string | null;
    descripcion: string | null;
    fuente: string | null;
    estado: string;
    prioridad: string;
    valor_estimado: number;
    fecha_seguimiento: string | null;
    notas: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Prospectos y Clientes', href: '/prospectos' },
    { title: 'Pipeline de Ventas', href: '/prospectos' },
];

const ESTADOS = [
    {
        value: 'nuevo',
        label: 'Nuevo',
        color: 'bg-blue-500/10 text-blue-600 border-blue-200',
        icon: Plus,
    },
    {
        value: 'contactado',
        label: 'Contactado',
        color: 'bg-yellow-500/10 text-yellow-600 border-yellow-200',
        icon: Phone,
    },
    {
        value: 'calificado',
        label: 'Calificado',
        color: 'bg-purple-500/10 text-purple-600 border-purple-200',
        icon: TrendingUp,
    },
    {
        value: 'perdido',
        label: 'Perdido',
        color: 'bg-red-500/10 text-red-600 border-red-200',
        icon: X,
    },
    {
        value: 'convertido',
        label: 'Convertido',
        color: 'bg-green-500/10 text-green-600 border-green-200',
        icon: Check,
    },
];

const FUENTES = [
    'web',
    'referido',
    'telefono',
    'feria',
    'Instagram',
    'Facebook',
    'LinkedIn',
    'otro',
];
const PRIORIDADES = [
    { value: 'baja', label: 'Baja', color: 'bg-slate-100 text-slate-600' },
    { value: 'media', label: 'Media', color: 'bg-orange-100 text-orange-600' },
    {
        value: 'alta',
        label: 'Alta',
        color: 'bg-red-100 text-red-600 font-black',
    },
];


export default function Index({
    prospectos,
    filters,
}: {
    prospectos: { data: Prospecto[]; links: any[]; meta: any };
    filters: {
        search?: string;
        estado?: string;
        fuente?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('comercial.prospectos.create');
    const canEdit = hasPermission('comercial.prospectos.edit');
    const canDelete = hasPermission('comercial.prospectos.delete');
    const [vistaKanban, setVistaKanban] = useState(true);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Prospecto | null>(null);
    const [validatingRut, setValidatingRut] = useState(false);

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [estadoFilter, setEstadoFilter] = useState(filters.estado || 'all');
    const [fuenteFilter, setFuenteFilter] = useState(filters.fuente || 'all');
    const [draggingId, setDraggingId] = useState<number | null>(null);

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
        rut: '',
        email: '',
        telefono: '',
        empresa: '',
        giro: '',
        cargo: '',
        direccion: '',
        comuna: '',
        region: 'Región Metropolitana',
        descripcion: '',
        fuente: '',
        estado: 'nuevo',
        prioridad: 'media',
        valor_estimado: 0,
        fecha_seguimiento: '',
        notas: '',
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (estadoFilter !== 'all') query.estado = estadoFilter;
            if (fuenteFilter !== 'all') query.fuente = fuenteFilter;

            router.get('/prospectos', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, estadoFilter, fuenteFilter]);

    const handleValidarRut = async () => {
        if (!data.rut) {
            toast.error('Ingrese un RUT para validar');
            return;
        }
        setValidatingRut(true);
        try {
            const response = await fetch('/api/sii/validar-rut', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({ rut: data.rut }),
            });

            const result = await response.json();
            if (result.success) {
                setData((prev: any) => ({
                    ...prev,
                    rut: result.rut,
                    empresa: result.razon_social || prev.empresa,
                    giro: result.giro || prev.giro,
                    comuna: result.comuna || prev.comuna,
                    direccion: result.direccion || prev.direccion,
                }));
                toast.success('Datos recuperados correctamente');
            } else {
                toast.error(result.message || 'RUT no encontrado');
            }
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        } catch (e) {
            toast.error('Error al conectar con el servicio');
        } finally {
            setValidatingRut(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/prospectos/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                },
            });
        } else {
            post('/prospectos', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (prosp: Prospecto) => {
        setEditando(prosp);
        setData({
            nombre: prosp.nombre,
            rut: prosp.rut || '',
            email: prosp.email || '',
            telefono: prosp.telefono || '',
            empresa: prosp.empresa || '',
            giro: prosp.giro || '',
            cargo: prosp.cargo || '',
            direccion: prosp.direccion || '',
            comuna: prosp.comuna || '',
            region: prosp.region || 'Región Metropolitana',
            descripcion: prosp.descripcion || '',
            fuente: prosp.fuente || '',
            estado: prosp.estado,
            prioridad: prosp.prioridad || 'media',
            valor_estimado: prosp.valor_estimado || 0,
            fecha_seguimiento: prosp.fecha_seguimiento
                ? prosp.fecha_seguimiento.substring(0, 10)
                : '',
            notas: prosp.notas || '',
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Desea eliminar este prospecto?')) {
            destroy(`/prospectos/${id}`);
        }
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleExportCsv = () => {
        window.location.href = '/prospectos/export';
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleExportExcel = () => {
        window.location.href = '/prospectos/export-excel';
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleImportCsv = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('archivo', file);
        router.post('/prospectos/import', formData, {
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
        router.post('/prospectos/import-excel', formData, {
            onSuccess: () => alert('Importación completada'),
            onError: (err) => alert('Error: ' + Object.values(err)[0]),
        });
    };

    const handleConvertir = (id: number) => {
        if (confirm('¿Convertir este prospecto en cliente real?')) {
            router.post(`/prospectos/${id}/convertir`);
        }
    };

    const handleDragStart = (e: React.DragEvent, id: number) => {
        setDraggingId(id);
        e.dataTransfer.effectAllowed = 'move';
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    };

    const handleDrop = (e: React.DragEvent, nuevoEstado: string) => {
        e.preventDefault();
        if (draggingId === null) return;

        router.patch(
            `/prospectos/${draggingId}/estado`,
            { estado: nuevoEstado },
            {
                onSuccess: () => setDraggingId(null),
            },
        );
    };

    const groupedProspectos = useMemo(() => {
        const groups: Record<string, Prospecto[]> = {};
        ESTADOS.forEach((e) => (groups[e.value] = []));
        prospectos.data.forEach((p) => {
            if (groups[p.estado]) groups[p.estado].push(p);
            else groups['nuevo'].push(p);
        });
        return groups;
    }, [prospectos.data]);

    const getEstadoConfig = (val: string) => {
        return (
            ESTADOS.find((e) => e.value === val) || {
                label: val,
                color: 'bg-gray-500/10 text-gray-600 border-gray-200',
                icon: AlertCircle,
            }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pipeline de Prospectos" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="mb-1 flex items-center gap-2">
                            <TrendingUp className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                CRM & Sales Pipeline
                            </span>
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Prospectos
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Gestione sus oportunidades de venta y conviértalas
                            en clientes
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="mr-2 flex gap-1 rounded-xl bg-muted/30 p-1">
                            <Button
                                variant={vistaKanban ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setVistaKanban(true)}
                                className="h-8 rounded-lg font-bold"
                            >
                                <Kanban className="h-4 w-4 md:mr-2" />
                                <span className="hidden md:inline">Kanban</span>
                            </Button>
                            <Button
                                variant={!vistaKanban ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setVistaKanban(false)}
                                className="h-8 rounded-lg font-bold"
                            >
                                <List className="h-4 w-4 md:mr-2" />
                                <span className="hidden md:inline">Lista</span>
                            </Button>
                        </div>

                        <BulkActions
                            baseUrl="/prospectos"
                            filters={{
                                search: searchTerm,
                                estado: estadoFilter,
                                fuente: fuenteFilter,
                            }}
                            modelName="Prospectos"
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
                                <Plus className="mr-2 h-4 w-4" /> Nuevo Prospecto
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
                                placeholder="Buscar por nombre, empresa, rut o email..."
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
                                <SelectTrigger className="h-11 min-w-[140px] rounded-2xl border-none bg-background font-bold shadow-sm md:w-[160px]">
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
                            <Select
                                value={fuenteFilter}
                                onValueChange={setFuenteFilter}
                            >
                                <SelectTrigger className="h-11 min-w-[140px] rounded-2xl border-none bg-background font-bold shadow-sm md:w-[160px]">
                                    <SelectValue placeholder="Fuente" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todas las fuentes
                                    </SelectItem>
                                    {FUENTES.map((f) => (
                                        <SelectItem key={f} value={f}>
                                            {f}
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
                                    setFuenteFilter('all');
                                }}
                            >
                                <X className="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    {vistaKanban ? (
                        <div className="flex gap-5 overflow-x-auto pb-6 items-start min-h-[65vh]">
                            {ESTADOS.map((est) => {
                                const prospEnEst =
                                    (groupedProspectos as any)[est.value] || [];
                                const totalValor = prospEnEst.reduce(
                                    (sum: number, p: any) =>
                                        sum + Number(p.valor_estimado),
                                    0,
                                );

                                return (
                                    <div
                                        key={est.value}
                                        className="flex w-[320px] shrink-0 flex-col gap-4"
                                        onDragOver={handleDragOver}
                                        onDrop={(e) => handleDrop(e, est.value)}
                                    >
                                        <div
                                            className={`rounded-3xl p-4 ${est.color} flex flex-col gap-1 border-2 border-transparent`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <est.icon className="h-4 w-4" />
                                                    <span className="text-[11px] font-black tracking-widest uppercase">
                                                        {est.label}
                                                    </span>
                                                </div>
                                                <Badge className="border-none bg-background/50 text-[10px] font-black text-inherit hover:bg-background/60">
                                                    {prospEnEst.length}
                                                </Badge>
                                            </div>
                                            <div className="mt-1 text-[10px] font-bold opacity-70">
                                                {formatCurrency(totalValor)}{' '}
                                                Estimados
                                            </div>
                                        </div>

                                        <div className="flex flex-col gap-3">
                                            {prospEnEst.map((p: any) => (
                                                <div
                                                    key={p.id}
                                                    draggable
                                                    onDragStart={(e) =>
                                                        handleDragStart(e, p.id)
                                                    }
                                                    className="group cursor-grab rounded-3xl border border-muted bg-background p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/20 hover:shadow-xl active:cursor-grabbing"
                                                >
                                                    <div className="mb-3 flex items-start justify-between">
                                                        <div className="min-w-0 flex-1">
                                                            <div className="truncate text-sm font-bold tracking-tight transition-colors group-hover:text-primary">
                                                                {p.nombre}
                                                            </div>
                                                            <div className="mt-0.5 flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                <Building2 className="h-2.5 w-2.5" />
                                                                <span className="truncate">
                                                                    {p.empresa ||
                                                                        'Empresa no reg.'}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <Badge
                                                            variant="outline"
                                                            className={`rounded-md px-1 py-0 text-[8px] font-black uppercase ${PRIORIDADES.find((pr) => pr.value === p.prioridad)?.color || ''}`}
                                                        >
                                                            {p.prioridad}
                                                        </Badge>
                                                    </div>

                                                    <div className="mt-4 flex items-center justify-between">
                                                        <div className="flex -space-x-2">
                                                            {p.telefono && (
                                                                <WhatsAppButton
                                                                    phone={
                                                                        p.telefono
                                                                    }
                                                                    nombre={
                                                                        p.nombre
                                                                    }
                                                                    className="h-8 w-8 transition-transform hover:scale-110"
                                                                />
                                                            )}
                                                            {p.email && (
                                                                <a
                                                                    href={`mailto:${p.email}`}
                                                                    className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-background bg-blue-50 text-blue-500 transition-colors hover:bg-blue-100"
                                                                >
                                                                    <Mail className="h-3 w-3" />
                                                                </a>
                                                            )}
                                                        </div>
                                                        <div className="text-right">
                                                            <div className="text-[11px] font-black text-primary">
                                                                {formatCurrency(
                                                                    p.valor_estimado,
                                                                )}
                                                            </div>
                                                            <div className="text-[8px] font-bold text-muted-foreground uppercase">
                                                                {p.fuente ||
                                                                    'web'}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div className="mt-4 flex justify-end gap-1 border-t border-muted/50 pt-3 opacity-0 transition-opacity group-hover:opacity-100">
                                                        {p.estado !==
                                                            'convertido' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-7 w-7 text-green-600 hover:bg-green-50"
                                                                onClick={() =>
                                                                    handleConvertir(
                                                                        p.id,
                                                                    )
                                                                }
                                                            >
                                                                <UserPlus className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-7 w-7 text-primary hover:bg-primary/10"
                                                                onClick={() =>
                                                                    handleEdit(p)
                                                                }
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-7 w-7 text-destructive hover:bg-destructive/10"
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        p.id,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                            {prospEnEst.length === 0 && (
                                                <div className="rounded-3xl border-2 border-dashed border-muted p-10 text-center">
                                                    <p className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                                                        Sin Prospectos
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
                            <CardHeader className="px-6 py-4">
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">Prospectos</CardTitle>
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
                            <CardContent className="p-0">
                                {viewMode === 'table' ? (
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                    <th className="px-6 py-4 text-left">
                                                        Prospecto / Empresa
                                                    </th>
                                                    <th className="px-6 py-4 text-left">
                                                        Contacto
                                                    </th>
                                                    <th className="px-6 py-4 text-right">
                                                        Valor Estimado
                                                    </th>
                                                    <th className="px-6 py-4 text-center">
                                                        Estado / Prioridad
                                                    </th>
                                                    <th className="px-6 py-4 text-right">
                                                        Acciones
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-muted/50">
                                                {prospectos.data.map((p) => (
                                                    <tr
                                                        key={p.id}
                                                        className="group transition-colors hover:bg-muted/30"
                                                    >
                                                        <td className="px-6 py-4">
                                                            <div>
                                                                <div className="text-sm font-bold tracking-tight text-foreground">
                                                                    {p.nombre}
                                                                </div>
                                                                <div className="flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                    <Building2 className="h-2.5 w-2.5" />
                                                                    {p.empresa ||
                                                                        'Particular'}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex items-center gap-3">
                                                                {p.telefono && (
                                                                    <WhatsAppButton
                                                                        phone={
                                                                            p.telefono
                                                                        }
                                                                        nombre={
                                                                            p.nombre
                                                                        }
                                                                        className="h-7 w-7"
                                                                    />
                                                                )}
                                                                <div className="text-xs">
                                                                    <div className="font-bold text-primary">
                                                                        {p.email ||
                                                                            'Sin email'}
                                                                    </div>
                                                                    <div className="text-[10px] font-bold text-muted-foreground uppercase">
                                                                        {p.fuente ||
                                                                            'web'}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <div className="text-sm font-black text-primary">
                                                                {formatCurrency(
                                                                    p.valor_estimado,
                                                                )}
                                                            </div>
                                                            {p.fecha_seguimiento && (
                                                                <div className="text-[9px] font-bold text-orange-500 uppercase">
                                                                    Seguimiento:{' '}
                                                                    {formatDateCLP(
                                                                        p.fecha_seguimiento,
                                                                    )}
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="px-6 py-4 text-center">
                                                            <div className="flex flex-col items-center gap-1">
                                                                <Badge
                                                                    variant="outline"
                                                                    className={`${getEstadoConfig(p.estado).color} rounded-full border px-2 py-0.5 text-[8px] font-black uppercase`}
                                                                >
                                                                    {
                                                                        getEstadoConfig(
                                                                            p.estado,
                                                                        ).label
                                                                    }
                                                                </Badge>
                                                                <Badge
                                                                    variant="outline"
                                                                    className={`rounded-full px-2 py-0 text-[8px] font-bold ${PRIORIDADES.find((pr) => pr.value === p.prioridad)?.color || ''}`}
                                                                >
                                                                    {p.prioridad}
                                                                </Badge>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <div className="flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                                {p.estado !==
                                                                    'convertido' && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 text-green-600 hover:bg-green-50"
                                                                        onClick={() =>
                                                                            handleConvertir(
                                                                                p.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        <UserPlus className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                                {canEdit && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 text-primary hover:bg-primary/10"
                                                                        onClick={() =>
                                                                            handleEdit(
                                                                                p,
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
                                                                        className="h-8 w-8 text-destructive hover:bg-destructive/10"
                                                                        onClick={() =>
                                                                            handleDelete(
                                                                                p.id,
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
                                ) : (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        {prospectos.data.map((p) => (
                                            <Card key={p.id} className="overflow-hidden">
                                                <CardHeader className="pb-3">
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                            <Building2 className="h-5 w-5 text-primary" />
                                                        </div>
                                                        <div className="flex gap-1">
                                                            <Badge variant="outline" className={`${getEstadoConfig(p.estado).color} rounded-full border px-2 py-0.5 text-[8px] font-black uppercase`}>
                                                                {getEstadoConfig(p.estado).label}
                                                            </Badge>
                                                            <Badge variant="outline" className={`rounded-full px-2 py-0 text-[8px] font-bold ${PRIORIDADES.find((pr) => pr.value === p.prioridad)?.color || ''}`}>
                                                                {p.prioridad}
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                    <CardTitle className="mt-2 text-sm">{p.nombre}</CardTitle>
                                                    <CardDescription className="flex items-center gap-1 text-xs">
                                                        <Building2 className="h-3 w-3" />
                                                        {p.empresa || 'Particular'}
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent>
                                                    <div className="space-y-2 text-xs">
                                                        {p.email && (
                                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                                <Mail className="h-3 w-3" />
                                                                <span>{p.email}</span>
                                                            </div>
                                                        )}
                                                        <div className="flex items-center justify-between">
                                                            <span className="text-muted-foreground">Valor estimado</span>
                                                            <span className="font-black text-primary">{formatCurrency(p.valor_estimado)}</span>
                                                        </div>
                                                        {p.fuente && (
                                                            <div className="text-muted-foreground">
                                                                Fuente: {p.fuente}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="mt-3 flex justify-end gap-1 border-t pt-2">
                                                        {p.estado !== 'convertido' && (
                                                            <Button variant="ghost" size="icon" className="h-8 w-8 text-green-600 hover:bg-green-50" onClick={() => handleConvertir(p.id)}>
                                                                <UserPlus className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canEdit && (
                                                            <Button variant="ghost" size="icon" className="h-8 w-8 text-primary hover:bg-primary/10" onClick={() => handleEdit(p)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:bg-destructive/10" onClick={() => handleDelete(p.id)}>
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                        {prospectos.data.length === 0 && (
                                            <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                                <LayoutGrid className="mb-2 h-8 w-8" />
                                                <p className="text-sm">No se encontraron prospectos con los filtros aplicados</p>
                                            </div>
                                        )}
                                    </div>
                                )}
                                <div className="border-t border-muted/50 p-4">
                                    <Pagination
                                        links={prospectos.links}
                                        meta={prospectos.meta}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[95vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-4xl">
                    <DialogHeader className="sticky top-0 z-10 bg-gradient-to-r from-primary/10 to-transparent p-4 pb-4 text-left backdrop-blur-sm md:p-6">
                        <div className="mb-1 flex items-center gap-2">
                            <Briefcase className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Sales Opportunity Profile
                            </span>
                        </div>
                        <DialogTitle className="text-xl font-black tracking-tight text-primary md:text-2xl">
                            {editando
                                ? 'Editar Prospecto de Venta'
                                : 'Registrar Nuevo Prospecto'}
                        </DialogTitle>
                        <DialogDescription className="text-sm font-medium text-muted-foreground">
                            Complete la información para hacer seguimiento a la
                            oportunidad de negocio.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[calc(95vh-180px)] overflow-y-auto p-4 md:p-6">
                        <form onSubmit={handleSubmit}>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
                                {/* Columna Izquierda: Datos Principales */}
                                <div className="space-y-5">
                                    <h3 className="flex items-center gap-2 text-[11px] font-black tracking-widest text-primary uppercase">
                                        <LayoutGrid className="h-4 w-4" />{' '}
                                        Información de Contacto
                                    </h3>
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Nombre Completo *
                                            </Label>
                                            <Input
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
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                    RUT Empresa/Persona
                                                </Label>
                                                <div className="flex gap-2">
                                                     <Input
                                                         value={data.rut}
                                                         onChange={(e) =>
                                                             setData(
                                                                 'rut',
                                                                 e.target.value,
                                                             )
                                                         }
                                                         onBlur={(e) =>
                                                             setData(
                                                                 'rut',
                                                                 formatRut(e.target.value),
                                                             )
                                                         }
                                                         className="h-11 rounded-xl border-none bg-muted/30 font-bold"
                                                     />
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        onClick={
                                                            handleValidarRut
                                                        }
                                                        disabled={validatingRut}
                                                        className="h-11 w-11 rounded-xl"
                                                    >
                                                        <Search
                                                            className={`h-4 w-4 ${validatingRut ? 'animate-spin' : ''}`}
                                                        />
                                                    </Button>
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                    Prioridad
                                                </Label>
                                                <Select
                                                    value={data.prioridad}
                                                    onValueChange={(v) =>
                                                        setData('prioridad', v)
                                                    }
                                                >
                                                    <SelectTrigger className="h-11 rounded-xl border-none bg-muted/30 font-bold">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {PRIORIDADES.map(
                                                            (p) => (
                                                                <SelectItem
                                                                    key={
                                                                        p.value
                                                                    }
                                                                    value={
                                                                        p.value
                                                                    }
                                                                >
                                                                    {p.label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                    Email
                                                </Label>
                                                <Input
                                                    type="email"
                                                    value={data.email}
                                                    onChange={(e) =>
                                                        setData(
                                                            'email',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-11 rounded-xl border-none bg-muted/30 font-bold"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                    Teléfono / WhatsApp
                                                </Label>
                                                <Input
                                                    value={data.telefono}
                                                    onChange={(e) =>
                                                        setData(
                                                            'telefono',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-11 rounded-xl border-none bg-muted/30 font-bold"
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Empresa
                                            </Label>
                                            <Input
                                                value={data.empresa}
                                                onChange={(e) =>
                                                    setData(
                                                        'empresa',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-11 rounded-xl border-none bg-muted/30 font-bold"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Columna Derecha: Oportunidad Neogcio */}
                                <div className="space-y-6">
                                    <h3 className="flex items-center gap-2 text-[11px] font-black tracking-widest text-primary uppercase">
                                        <TrendingUp className="h-4 w-4" />{' '}
                                        Detalles de Negocio
                                    </h3>
                                    <div className="space-y-4 rounded-3xl border border-muted/50 bg-muted/10 p-6">
                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                    Estado Pipeline
                                                </Label>
                                                <Select
                                                    value={data.estado}
                                                    onValueChange={(v) =>
                                                        setData('estado', v)
                                                    }
                                                >
                                                    <SelectTrigger className="h-11 rounded-xl border-none bg-background font-bold shadow-sm">
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
                                                <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                    Valor Estimado (CLP)
                                                </Label>
                                                <div className="relative">
                                                    <DollarSign className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-primary" />
                                                    <Input
                                                        type="number"
                                                        value={
                                                            data.valor_estimado
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'valor_estimado',
                                                                parseFloat(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        className="h-11 rounded-xl border-none bg-background pl-10 font-black text-primary shadow-sm"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                    Fuente Origen
                                                </Label>
                                                <Select
                                                    value={data.fuente}
                                                    onValueChange={(v) =>
                                                        setData('fuente', v)
                                                    }
                                                >
                                                    <SelectTrigger className="h-11 rounded-xl border-none bg-background font-bold shadow-sm">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {FUENTES.map((f) => (
                                                            <SelectItem
                                                                key={f}
                                                                value={f}
                                                            >
                                                                {f}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                    Próx. Seguimiento
                                                </Label>
                                                <Input
                                                    type="date"
                                                    value={
                                                        data.fecha_seguimiento
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'fecha_seguimiento',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-11 rounded-xl border-none bg-background font-bold shadow-sm"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Descripción / Requerimiento
                                        </Label>
                                        <textarea
                                            value={data.descripcion}
                                            onChange={(e) =>
                                                setData(
                                                    'descripcion',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex min-h-[100px] w-full rounded-2xl border-none bg-muted/30 px-4 py-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                            placeholder="¿Qué necesita el cliente?..."
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="mt-6 space-y-2 border-t pt-6">
                                <Label className="text-xs font-bold text-muted-foreground uppercase">
                                    Notas Adicionales / Historial de
                                    Conversación
                                </Label>
                                <textarea
                                    value={data.notas}
                                    onChange={(e) =>
                                        setData('notas', e.target.value)
                                    }
                                    className="flex min-h-[80px] w-full rounded-2xl border-none bg-muted/30 px-4 py-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                    placeholder="Bitácora de interacciones..."
                                />
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
            <Toaster position="bottom-right" />
        </AppLayout>
    );
}
