import { Head, router, useForm } from '@inertiajs/react';
import {
    format,
    startOfMonth,
    endOfMonth,
    subMonths,
    startOfWeek,
    endOfWeek,
} from 'date-fns';
import { es } from 'date-fns/locale';
import {
    BarChart3,
    Plus,
    ArrowLeft,
    TrendingUp,
    Droplets,
    Weight,
    DollarSign,
    AlertTriangle,
    Calendar,
    X,
    Pencil,
    Trash2,
    Target,
    ClipboardList,
    Package,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    LineChart,
    Line,
    PieChart,
    Pie,
    Cell,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar as CalendarPicker } from '@/components/ui/calendar';
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
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip as ShadcnTooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatNumberLocale } from '@/lib/utils';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface GrupoItem {
    id: number;
    nombre: string;
    color: string;
    estado: string;
}

interface Asignacion {
    id: number;
    grupo_trabajo_id: number;
    grupo_trabajo: GrupoItem;
    fecha_inicio: string;
    fecha_fin: string;
    meta_monto: number;
    meta_cantidad: number;
    meta_kg: number;
    meta_l: number;
    estado: string;
    notas: string | null;
}

interface GrupoRendimiento {
    id: number;
    nombre: string;
    color: string;
    monto: number;
    cantidad: number;
    kg: number;
    l: number;
}

interface TendenciaMes {
    mes: string;
    anio: string;
    mes_num: string;
    monto: number;
    cantidad: number;
    kg: number;
    l: number;
}

const CHART_COLORS = ['#6366f1', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899'];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Grupos de Trabajo', href: '/grupos-trabajo' },
    { title: 'Rendimiento', href: '/grupos-trabajo/rendimiento' },
];

export default function Rendimiento({
    grupos,
    asignacionesActivas,
    rendimiento,
    tendencia,
    comparativa,
    proximoCorte,
    diasParaCorte,
    fechaInicio,
    fechaFin,
}: {
    grupos: GrupoItem[];
    asignacionesActivas: Asignacion[];
    rendimiento: {
        totalMonto: number;
        totalCantidad: number;
        totalKg: number;
        totalL: number;
        porGrupo: GrupoRendimiento[];
    };
    tendencia: TendenciaMes[];
    comparativa: GrupoRendimiento[];
    proximoCorte: string;
    diasParaCorte: number;
    fechaInicio: string;
    fechaFin: string;
}) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canEdit = hasPermission('flota.grupos-trabajo.edit');
    const canDelete = hasPermission('flota.grupos-trabajo.delete');

    const formatNumber = (value: number): string => {
        return formatNumberLocale(value, currency.locale);
    };

    const [selectedGroups, setSelectedGroups] = useState<number[]>([]);
    const [isAsignacionOpen, setIsAsignacionOpen] = useState(false);
    const [editandoAsignacion, setEditandoAsignacion] = useState<Asignacion | null>(null);
    const [datePickerOpen, setDatePickerOpen] = useState<'inicio' | 'fin' | null>(null);

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        errors,
        transform,
    } = useForm({
        grupo_trabajo_id: '',
        fecha_inicio: '',
        fecha_fin: '',
        meta_monto: '',
        meta_cantidad: '',
        meta_kg: '',
        meta_l: '',
        notas: '',
    });

    const quickPeriods = useMemo(() => {
        const now = new Date();
        return [
            {
                label: 'Esta Semana',
                start: format(startOfWeek(now, { weekStartsOn: 1 }), 'yyyy-MM-dd'),
                end: format(endOfWeek(now, { weekStartsOn: 1 }), 'yyyy-MM-dd'),
            },
            {
                label: 'Esta Quincena',
                start: format(startOfMonth(now), 'yyyy-MM-dd'),
                end: format(now.getDate() <= 15 ? new Date(now.getFullYear(), now.getMonth(), 15) : now, 'yyyy-MM-dd'),
            },
            {
                label: 'Este Mes',
                start: format(startOfMonth(now), 'yyyy-MM-dd'),
                end: format(endOfMonth(now), 'yyyy-MM-dd'),
            },
            {
                label: 'Mes Pasado',
                start: format(startOfMonth(subMonths(now, 1)), 'yyyy-MM-dd'),
                end: format(endOfMonth(subMonths(now, 1)), 'yyyy-MM-dd'),
            },
        ];
    }, []);

    const handleQuickPeriod = (start: string, end: string) => {
        router.get('/grupos-trabajo/rendimiento', {
            fecha_inicio: start,
            fecha_fin: end,
            grupo_ids: selectedGroups.length > 0 ? selectedGroups : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const toggleGroup = (id: number) => {
        setSelectedGroups((prev) => {
            const next = prev.includes(id)
                ? prev.filter((g) => g !== id)
                : [...prev, id];
            router.get('/grupos-trabajo/rendimiento', {
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                grupo_ids: next.length > 0 ? next : undefined,
            }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
            return next;
        });
    };

    const handleOpenNewAsignacion = () => {
        reset();
        setEditandoAsignacion(null);
        setData({
            grupo_trabajo_id: '',
            fecha_inicio: format(new Date(), 'yyyy-MM-dd'),
            fecha_fin: format(endOfMonth(new Date()), 'yyyy-MM-dd'),
            meta_monto: '',
            meta_cantidad: '',
            meta_kg: '',
            meta_l: '',
            notas: '',
        });
        setIsAsignacionOpen(true);
    };

    const handleEditAsignacion = (asignacion: Asignacion) => {
        setEditandoAsignacion(asignacion);
        setData({
            grupo_trabajo_id: String(asignacion.grupo_trabajo_id),
            fecha_inicio: asignacion.fecha_inicio,
            fecha_fin: asignacion.fecha_fin,
            meta_monto: String(asignacion.meta_monto),
            meta_cantidad: String(asignacion.meta_cantidad),
            meta_kg: String(asignacion.meta_kg),
            meta_l: String(asignacion.meta_l),
            notas: asignacion.notas || '',
        });
        setIsAsignacionOpen(true);
    };

    const handleSubmitAsignacion = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            meta_monto: formData.meta_monto ? parseFloat(formData.meta_monto) : 0,
            meta_cantidad: formData.meta_cantidad ? parseInt(formData.meta_cantidad) : 0,
            meta_kg: formData.meta_kg ? parseFloat(formData.meta_kg) : 0,
            meta_l: formData.meta_l ? parseFloat(formData.meta_l) : 0,
        }));

        if (editandoAsignacion) {
            put(`/grupos-trabajo/rendimiento/${editandoAsignacion.id}`, {
                onSuccess: () => {
                    setIsAsignacionOpen(false);
                    reset();
                    setEditandoAsignacion(null);
                },
            });
        } else {
            post('/grupos-trabajo/rendimiento', {
                onSuccess: () => {
                    setIsAsignacionOpen(false);
                    reset();
                },
            });
        }
    };

    const handleDeleteAsignacion = (id: number) => {
        if (confirm('¿Estás seguro de eliminar esta asignación?')) {
            destroy(`/grupos-trabajo/rendimiento/${id}`);
        }
    };

    const computeProgress = (asignacion: Asignacion, grupoRendimiento?: GrupoRendimiento) => {
        if (!grupoRendimiento) return null;
        return {
            monto: asignacion.meta_monto > 0 ? Math.min(grupoRendimiento.monto / asignacion.meta_monto * 100, 100) : 0,
            cantidad: asignacion.meta_cantidad > 0 ? Math.min(grupoRendimiento.cantidad / asignacion.meta_cantidad * 100, 100) : 0,
            kg: asignacion.meta_kg > 0 ? Math.min(grupoRendimiento.kg / asignacion.meta_kg * 100, 100) : 0,
            l: asignacion.meta_l > 0 ? Math.min(grupoRendimiento.l / asignacion.meta_l * 100, 100) : 0,
        };
    };

    const rendimientoPorGrupoId = useMemo(() => {
        const map: Record<number, GrupoRendimiento> = {};
        rendimiento.porGrupo.forEach((g) => { map[g.id] = g; });
        return map;
    }, [rendimiento]);

    const pieData = useMemo(() => {
        return rendimiento.porGrupo.map((g) => ({
            name: g.nombre,
            value: g.monto,
            color: g.color,
        }));
    }, [rendimiento]);

    const comparativaMonto = useMemo(() => {
        return comparativa
            .filter((g) => g.monto > 0 || g.cantidad > 0)
            .map((g) => ({
                name: g.nombre,
                Monto: g.monto,
                Cantidad: g.cantidad,
                fill: g.color,
            }));
    }, [comparativa]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rendimiento - Grupos de Trabajo" />
            <div className="flex flex-col gap-6 p-4 md:p-6 lg:p-8">
                {diasParaCorte >= 0 && diasParaCorte <= 2 && (
                    <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p className="font-semibold">
                                El período actual está por finalizar
                            </p>
                            <p className="mt-1 text-sm">
                                {diasParaCorte === 0
                                    ? 'El período de asignaciones finaliza HOY. Revisa las metas y crea nuevas asignaciones para el próximo período.'
                                    : `Faltan ${diasParaCorte} día${diasParaCorte !== 1 ? 's' : ''} para el cierre del período (${format(new Date(proximoCorte), "dd 'de' MMMM", { locale: es })}). Prepara las nuevas asignaciones.`}
                            </p>
                        </div>
                    </div>
                )}

                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => router.get('/grupos-trabajo')}
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold md:text-3xl">
                                Rendimiento de Grupos
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Métricas, comparativas y asignaciones por período
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {quickPeriods.map((period) => (
                        <Button
                            key={period.label}
                            variant={fechaInicio === period.start && fechaFin === period.end ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleQuickPeriod(period.start, period.end)}
                            className="h-8"
                        >
                            {period.label}
                        </Button>
                    ))}
                    <div className="flex items-center gap-1">
                        <Popover
                            open={datePickerOpen === 'inicio'}
                            onOpenChange={(open) => setDatePickerOpen(open ? 'inicio' : null)}
                        >
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1"
                                >
                                    <Calendar className="h-3.5 w-3.5" />
                                    {format(new Date(fechaInicio), 'dd/MM/yyyy')}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                                <CalendarPicker
                                    mode="single"
                                    selected={new Date(fechaInicio)}
                                    onSelect={(date) => {
                                        if (date) {
                                            const start = format(date, 'yyyy-MM-dd');
                                            setDatePickerOpen('fin');
                                            router.get('/grupos-trabajo/rendimiento', {
                                                fecha_inicio: start,
                                                fecha_fin: fechaFin,
                                                grupo_ids: selectedGroups.length > 0 ? selectedGroups : undefined,
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                                replace: true,
                                            });
                                        }
                                    }}
                                    initialFocus
                                />
                            </PopoverContent>
                        </Popover>
                        <span className="text-xs text-muted-foreground">a</span>
                        <Popover
                            open={datePickerOpen === 'fin'}
                            onOpenChange={(open) => setDatePickerOpen(open ? 'fin' : null)}
                        >
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1"
                                >
                                    <Calendar className="h-3.5 w-3.5" />
                                    {format(new Date(fechaFin), 'dd/MM/yyyy')}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                                <CalendarPicker
                                    mode="single"
                                    selected={new Date(fechaFin)}
                                    onSelect={(date) => {
                                        if (date) {
                                            const end = format(date, 'yyyy-MM-dd');
                                            setDatePickerOpen(null);
                                            router.get('/grupos-trabajo/rendimiento', {
                                                fecha_inicio: fechaInicio,
                                                fecha_fin: end,
                                                grupo_ids: selectedGroups.length > 0 ? selectedGroups : undefined,
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                                replace: true,
                                            });
                                        }
                                    }}
                                    initialFocus
                                />
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>

                {grupos.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-xs font-medium text-muted-foreground">
                            Grupos:
                        </span>
                        {grupos.map((grupo) => (
                            <Badge
                                key={grupo.id}
                                variant={selectedGroups.length === 0 || selectedGroups.includes(grupo.id) ? 'default' : 'outline'}
                                className="cursor-pointer"
                                style={selectedGroups.length === 0 || selectedGroups.includes(grupo.id) ? { backgroundColor: grupo.color } : {}}
                                onClick={() => toggleGroup(grupo.id)}
                            >
                                {grupo.nombre}
                            </Badge>
                        ))}
                        {selectedGroups.length > 0 && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-6 px-2 text-xs"
                                onClick={() => {
                                    setSelectedGroups([]);
                                    router.get('/grupos-trabajo/rendimiento', {
                                        fecha_inicio: fechaInicio,
                                        fecha_fin: fechaFin,
                                    }, {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                    });
                                }}
                            >
                                <X className="mr-1 h-3 w-3" />
                                Limpiar filtro
                            </Button>
                        )}
                    </div>
                )}

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Ventas
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(rendimiento.totalMonto)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {rendimiento.totalCantidad} pedidos
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Kg
                            </CardTitle>
                            <Weight className="h-4 w-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatNumber(rendimiento.totalKg)}
                            </div>
                            <p className="text-xs text-muted-foreground">Kilogramos</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Litros
                            </CardTitle>
                            <Droplets className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatNumber(rendimiento.totalL)}
                            </div>
                            <p className="text-xs text-muted-foreground">Litros</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Productos Vendidos
                            </CardTitle>
                            <Package className="h-4 w-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatNumber(rendimiento.totalCantidad)}
                            </div>
                            <p className="text-xs text-muted-foreground">Unidades</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <BarChart3 className="h-5 w-5 text-primary" />
                                Comparativa por Grupo
                            </CardTitle>
                            <CardDescription>
                                Monto vendido en el período seleccionado
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {comparativaMonto.length > 0 ? (
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart data={comparativaMonto}>
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                            <YAxis tick={{ fontSize: 11 }} />
                                            <Tooltip
                                                formatter={(value) => formatCurrency(Number(value) || 0)}
                                            />
                                            <Bar dataKey="Monto" radius={[4, 4, 0, 0]}>
                                                {comparativaMonto.map((entry, index) => (
                                                    <Cell key={`cell-${index}`} fill={entry.fill || CHART_COLORS[index % CHART_COLORS.length]} />
                                                ))}
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <div className="flex h-72 items-center justify-center text-sm text-muted-foreground">
                                    No hay datos en el período seleccionado
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <TrendingUp className="h-5 w-5 text-primary" />
                                Tendencia Mensual
                            </CardTitle>
                            <CardDescription>
                                Últimos {tendencia.length} meses
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {tendencia.length > 0 ? (
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={tendencia}>
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                            <XAxis dataKey="mes" tick={{ fontSize: 11 }} />
                                            <YAxis tick={{ fontSize: 11 }} />
                                            <Tooltip
                                                formatter={(value) => formatCurrency(Number(value) || 0)}
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="monto"
                                                stroke="#6366f1"
                                                strokeWidth={2}
                                                dot={{ fill: '#6366f1', r: 4 }}
                                                name="Monto"
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <div className="flex h-72 items-center justify-center text-sm text-muted-foreground">
                                    No hay datos históricos disponibles
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Target className="h-5 w-5 text-primary" />
                                Distribución por Grupo
                            </CardTitle>
                            <CardDescription>
                                Participación en ventas del período
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {pieData.length > 0 ? (
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart>
                                            <Pie
                                                data={pieData}
                                                cx="50%"
                                                cy="50%"
                                                innerRadius={60}
                                                outerRadius={100}
                                                paddingAngle={2}
                                                dataKey="value"
                                                label={({ name, percent }) =>
                                                    `${name} ${((percent ?? 0) * 100).toFixed(0)}%`
                                                }
                                                labelLine={false}
                                            >
                                                {pieData.map((entry, index) => (
                                                    <Cell key={`cell-${index}`} fill={entry.color || CHART_COLORS[index % CHART_COLORS.length]} />
                                                ))}
                                            </Pie>
                                            <Tooltip formatter={(value) => formatCurrency(Number(value) || 0)} />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <div className="flex h-72 items-center justify-center text-sm text-muted-foreground">
                                    No hay datos en el período seleccionado
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {tendencia.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Package className="h-5 w-5 text-primary" />
                                    Producción Mensual (Kg / L)
                                </CardTitle>
                                <CardDescription>
                                    Tendencia de producción en los últimos {tendencia.length} meses
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={tendencia}>
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                            <XAxis dataKey="mes" tick={{ fontSize: 11 }} />
                                            <YAxis tick={{ fontSize: 11 }} />
                                            <Tooltip />
                                            <Line
                                                type="monotone"
                                                dataKey="kg"
                                                stroke="#f59e0b"
                                                strokeWidth={2}
                                                dot={{ fill: '#f59e0b', r: 4 }}
                                                name="Kg"
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="l"
                                                stroke="#3b82f6"
                                                strokeWidth={2}
                                                dot={{ fill: '#3b82f6', r: 4 }}
                                                name="L"
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <ClipboardList className="h-5 w-5 text-primary" />
                                Asignaciones Activas
                            </CardTitle>
                            <CardDescription>
                                Metas planificadas vs rendimiento real
                            </CardDescription>
                        </div>
                        {canEdit && (
                            <Button onClick={handleOpenNewAsignacion} className="gap-2">
                                <Plus className="h-4 w-4" />
                                Nueva Asignación
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="p-0">
                        {asignacionesActivas.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Target className="mb-3 h-10 w-10 text-muted-foreground/30" />
                                <p className="font-medium text-muted-foreground">
                                    No hay asignaciones activas
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Crea asignaciones para establecer metas a los grupos
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                            <th className="px-4 py-3 text-left">Grupo</th>
                                            <th className="px-4 py-3 text-left">Período</th>
                                            <th className="px-4 py-3 text-right">Meta $</th>
                                            <th className="px-4 py-3 text-right">Real $</th>
                                            <th className="px-4 py-3 text-right">%</th>
                                            <th className="px-4 py-3 text-right">Meta Kg</th>
                                            <th className="px-4 py-3 text-right">Real Kg</th>
                                            <th className="px-4 py-3 text-right">Meta L</th>
                                            <th className="px-4 py-3 text-right">Real L</th>
                                            <th className="px-4 py-3 text-center">Estado</th>
                                            {canEdit && <th className="px-4 py-3 text-right">Acciones</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-muted/50">
                                        {asignacionesActivas.map((asignacion) => {
                                            const real = rendimientoPorGrupoId[asignacion.grupo_trabajo_id];
                                            const progress = computeProgress(asignacion, real);
                                            return (
                                                <tr key={asignacion.id} className="transition-colors hover:bg-muted/30">
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <div
                                                                className="h-3 w-3 rounded-full"
                                                                style={{ backgroundColor: asignacion.grupo_trabajo.color }}
                                                            />
                                                            <span className="text-sm font-medium">
                                                                {asignacion.grupo_trabajo.nombre}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">
                                                        {format(new Date(asignacion.fecha_inicio), 'dd/MM/yyyy')}
                                                        {' - '}
                                                        {format(new Date(asignacion.fecha_fin), 'dd/MM/yyyy')}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono text-xs">
                                                        {formatCurrency(asignacion.meta_monto)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono text-xs font-bold">
                                                        {real ? formatCurrency(real.monto) : '-'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {progress && (
                                                            <span className={`text-xs font-bold ${progress.monto >= 100 ? 'text-green-600' : progress.monto >= 50 ? 'text-amber-600' : 'text-red-600'}`}>
                                                                {progress.monto.toFixed(0)}%
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono text-xs">
                                                        {formatNumber(asignacion.meta_kg)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono text-xs font-bold">
                                                        {real ? formatNumber(real.kg) : '-'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono text-xs">
                                                        {formatNumber(asignacion.meta_l)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono text-xs font-bold">
                                                        {real ? formatNumber(real.l) : '-'}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        <Badge className={asignacion.estado === 'activa' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}>
                                                            {asignacion.estado === 'activa' ? 'Activa' : asignacion.estado === 'completada' ? 'Completada' : 'Cancelada'}
                                                        </Badge>
                                                    </td>
                                                    {canEdit && (
                                                        <td className="px-4 py-3 text-right">
                                                            <div className="flex justify-end gap-1">
                                                                <TooltipProvider>
                                                                    <ShadcnTooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => handleEditAsignacion(asignacion)}>
                                                                                <Pencil className="h-3.5 w-3.5" />
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>Editar</TooltipContent>
                                                                    </ShadcnTooltip>
                                                                </TooltipProvider>
                                                                {canDelete && (
                                                                    <TooltipProvider>
                                                                        <ShadcnTooltip>
                                                                            <TooltipTrigger asChild>
                                                                                <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive" onClick={() => handleDeleteAsignacion(asignacion.id)}>
                                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                                </Button>
                                                                            </TooltipTrigger>
                                                                            <TooltipContent>Eliminar</TooltipContent>
                                                                        </ShadcnTooltip>
                                                                    </TooltipProvider>
                                                                )}
                                                            </div>
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={isAsignacionOpen} onOpenChange={setIsAsignacionOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-full flex-col overflow-y-auto sm:max-w-lg md:max-w-xl lg:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editandoAsignacion ? 'Editar Asignación' : 'Nueva Asignación'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmitAsignacion} className="space-y-4">
                        <div className="space-y-2">
                            <Label>Grupo de Trabajo</Label>
                            <Select
                                value={data.grupo_trabajo_id}
                                onValueChange={(val) => setData('grupo_trabajo_id', val)}
                                disabled={!!editandoAsignacion}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar grupo" />
                                </SelectTrigger>
                                <SelectContent>
                                    {grupos.map((grupo) => (
                                        <SelectItem key={grupo.id} value={String(grupo.id)}>
                                            <div className="flex items-center gap-2">
                                                <div className="h-3 w-3 rounded-full" style={{ backgroundColor: grupo.color }} />
                                                {grupo.nombre}
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.grupo_trabajo_id && (
                                <p className="text-sm text-red-500">{errors.grupo_trabajo_id}</p>
                            )}
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Fecha Inicio</Label>
                                <Input
                                    type="date"
                                    value={data.fecha_inicio}
                                    onChange={(e) => setData('fecha_inicio', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Fecha Fin</Label>
                                <Input
                                    type="date"
                                    value={data.fecha_fin}
                                    onChange={(e) => setData('fecha_fin', e.target.value)}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Meta Monto ($)</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.meta_monto}
                                    onChange={(e) => setData('meta_monto', e.target.value)}
                                    placeholder="0"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Meta Cantidad</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={data.meta_cantidad}
                                    onChange={(e) => setData('meta_cantidad', e.target.value)}
                                    placeholder="0"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Meta Kg</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.meta_kg}
                                    onChange={(e) => setData('meta_kg', e.target.value)}
                                    placeholder="0"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Meta Litros</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.meta_l}
                                    onChange={(e) => setData('meta_l', e.target.value)}
                                    placeholder="0"
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label>Notas</Label>
                            <Input
                                value={data.notas}
                                onChange={(e) => setData('notas', e.target.value)}
                                placeholder="Notas opcionales"
                            />
                        </div>
                        <DialogFooter className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button type="button" variant="outline" onClick={() => setIsAsignacionOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit">
                                {editandoAsignacion ? 'Guardar' : 'Crear'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
