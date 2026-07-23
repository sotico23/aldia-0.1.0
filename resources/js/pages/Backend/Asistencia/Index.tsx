import { Head, useForm, router } from '@inertiajs/react';
import { LayoutGrid, List, Pencil, Plus, Trash2, Calendar as CalendarIcon, Clock, User, AlertTriangle, FileText, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState, useMemo } from 'react';
import { BulkActions } from '@/components/shared/BulkActions';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Almacen {
    id: number;
    nombre: string;
}

interface Empleado {
    id: number;
    nombre: string;
    apellido: string;
    rut: string | null;
    cargo: string | null;
    departamento: string | null;
    almacen_id: number | null;
    almacen?: Almacen | null;
    hora_entrada: string | null;
    hora_salida: string | null;
}

interface Asistencia {
    id: number;
    empleado_id: number | null;
    fecha: string | null;
    hora_entrada: string | null;
    hora_salida: string | null;
    horas_trabajadas: number | null;
    estado: string;
    notes?: string | null; // Aceptamos ambas formas
    notas: string | null;
    empleado?: Empleado;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asistencia', href: '/asistencia' },
];

const estados = [
    'presente',
    'ausente',
    'vacaciones',
    'licencia',
    'permiso',
    'teletrabajo',
];

const getEstadoStyle = (estado: string) => {
    switch (estado.toLowerCase()) {
        case 'presente':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-800';
        case 'ausente':
            return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-800';
        case 'vacaciones':
            return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-800';
        case 'licencia':
            return 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/30 dark:text-purple-400 dark:border-purple-800';
        case 'permiso':
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-800';
        case 'teletrabajo':
            return 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-950/30 dark:text-cyan-400 dark:border-cyan-800';
        default:
            return 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-750';
    }
};

const getEstadoDotColor = (estado: string) => {
    switch (estado.toLowerCase()) {
        case 'presente': return 'bg-emerald-500';
        case 'ausente': return 'bg-rose-500';
        case 'vacaciones': return 'bg-blue-500';
        case 'licencia': return 'bg-purple-500';
        case 'permiso': return 'bg-amber-500';
        case 'teletrabajo': return 'bg-cyan-500';
        default: return 'bg-gray-500';
    }
};

function calcularHorasTrabajadas(horaEntrada: string | null, horaSalida: string | null): number {
    if (!horaEntrada || !horaSalida) return 0;
    const [h1, m1] = horaEntrada.split(':').map(Number);
    const [h2, m2] = horaSalida.split(':').map(Number);
    if (isNaN(h1) || isNaN(m1) || isNaN(h2) || isNaN(m2)) return 0;
    const diff = (h2 * 60 + m2) - (h1 * 60 + m1);
    if (diff <= 0) return 0;
    return Math.round((diff / 60) * 100) / 100;
}

export default function Index({
    asistencias,
    empleados = [],
    almacenes = [],
    statsAsistencias = [],
    filters = {},
}: {
    asistencias: {
        data: Asistencia[];
        links: any[];
        meta?: any;
        total: number;
    };
    empleados?: Empleado[];
    almacenes?: Almacen[];
    statsAsistencias?: Asistencia[];
    filters?: {
        empleado_id?: string;
        estado?: string;
        fecha_desde?: string;
        fecha_hasta?: string;
        almacen_id?: string;
        stats_empleado_id?: string;
        stats_mes?: string;
        stats_almacen?: string;
    };
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Asistencia | null>(null);
    const [activeTab, setActiveTab] = useState<'listado' | 'resumen'>(
        filters.stats_empleado_id || filters.stats_almacen ? 'resumen' : 'listado'
    );
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    // Filtros de listado local
    const [filterEmpleadoId, setFilterEmpleadoId] = useState(filters.empleado_id || 'all');
    const [filterEstado, setFilterEstado] = useState(filters.estado || 'all');
    const [filterDesde, setFilterDesde] = useState(filters.fecha_desde || '');
    const [filterHasta, setFilterHasta] = useState(filters.fecha_hasta || '');
    const [filterAlmacenId, setFilterAlmacenId] = useState(filters.almacen_id || 'all');

    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('rrhh.asistencia.create');
    const canEdit = hasPermission('rrhh.asistencia.edit');
    const canDelete = hasPermission('rrhh.asistencia.delete');

    // Filtros de resumen local
    const [statsEmpleadoId, setStatsEmpleadoId] = useState(filters.stats_empleado_id || '0');
    const [statsAlmacenId, setStatsAlmacenId] = useState(filters.stats_almacen || '0');
    const [statsMes, setStatsMes] = useState(filters.stats_mes || new Date().toLocaleString('sv-SE', { timeZone: 'America/Santiago' }).substring(0, 7));

    // Formulario de edición/creación
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        errors,
    } = useForm({
        empleado_id: '' as string,
        fecha: '',
        fecha_fin: '',
        hora_entrada: '',
        hora_salida: '',
        horas_trabajadas: 0,
        estado: 'presente',
        notas: '',
    });

    const selectedEmpleado = useMemo(
        () => empleados.find((e) => String(e.id) === data.empleado_id) ?? null,
        [empleados, data.empleado_id]
    );

    const diasCount = useMemo(() => {
        if (!data.fecha || !data.fecha_fin) return 1;
        const inicio = new Date(data.fecha);
        const fin = new Date(data.fecha_fin);
        if (isNaN(inicio.getTime()) || isNaN(fin.getTime())) return 1;
        if (fin < inicio) return 1;
        return Math.floor((fin.getTime() - inicio.getTime()) / (1000 * 60 * 60 * 24)) + 1;
    }, [data.fecha, data.fecha_fin]);

    const filteredEmpleados = useMemo(
        () => statsAlmacenId !== '0'
            ? empleados.filter((e) => String(e.almacen_id) === statsAlmacenId)
            : empleados,
        [empleados, statsAlmacenId]
    );

    const statsAlmacenNombre = useMemo(
        () => almacenes.find((a) => String(a.id) === statsAlmacenId)?.nombre ?? null,
        [almacenes, statsAlmacenId]
    );

    const handleApplyFilters = () => {
        router.get('/asistencia', {
            empleado_id: filterEmpleadoId === 'all' ? undefined : filterEmpleadoId,
            estado: filterEstado === 'all' ? undefined : filterEstado,
            fecha_desde: filterDesde || undefined,
            fecha_hasta: filterHasta || undefined,
            almacen_id: filterAlmacenId === 'all' ? undefined : filterAlmacenId,
            stats_empleado_id: statsEmpleadoId || undefined,
            stats_mes: statsMes || undefined,
            stats_almacen: undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        setFilterEmpleadoId('all');
        setFilterEstado('all');
        setFilterDesde('');
        setFilterHasta('');
        setFilterAlmacenId('all');
        router.get('/asistencia', {
            stats_empleado_id: statsEmpleadoId || undefined,
            stats_mes: statsMes || undefined,
        });
    };

    const handleApplyStatsFilters = (empId: string, mesStr: string, almacenId?: string) => {
        router.get('/asistencia', {
            empleado_id: filterEmpleadoId === 'all' ? undefined : filterEmpleadoId,
            estado: filterEstado === 'all' ? undefined : filterEstado,
            fecha_desde: filterDesde || undefined,
            fecha_hasta: filterHasta || undefined,
            almacen_id: undefined,
            stats_empleado_id: empId !== '0' ? empId : undefined,
            stats_mes: mesStr || undefined,
            stats_almacen: almacenId !== '0' && almacenId ? almacenId : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePrevMonth = () => {
        const parts = statsMes.split('-');
        let year = parseInt(parts[0]);
        let month = parseInt(parts[1]) - 1;
        if (month === 0) {
            month = 12;
            year -= 1;
        }
        const newMes = `${year}-${String(month).padStart(2, '0')}`;
        setStatsMes(newMes);
        if (statsEmpleadoId !== '0' || statsAlmacenId !== '0') {
            handleApplyStatsFilters(statsEmpleadoId, newMes, statsAlmacenId);
        }
    };

    const handleNextMonth = () => {
        const parts = statsMes.split('-');
        let year = parseInt(parts[0]);
        let month = parseInt(parts[1]) + 1;
        if (month === 13) {
            month = 1;
            year += 1;
        }
        const newMes = `${year}-${String(month).padStart(2, '0')}`;
        setStatsMes(newMes);
        if (statsEmpleadoId !== '0' || statsAlmacenId !== '0') {
            handleApplyStatsFilters(statsEmpleadoId, newMes, statsAlmacenId);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/asistencia/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                    window.location.reload();
                },
            });
        } else {
            post('/asistencia', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                    window.location.reload();
                },
            });
        }
    };

    const handleEdit = (a: Asistencia) => {
        setEditando(a);
        setData({
            empleado_id: String(a.empleado_id || ''),
            fecha: a.fecha ? a.fecha.split('T')[0] : '',
            fecha_fin: '',
            hora_entrada: a.hora_entrada || '',
            hora_salida: a.hora_salida || '',
            horas_trabajadas: a.horas_trabajadas || 0,
            estado: a.estado,
            notas: a.notas || a.notes || '',
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar registro de asistencia?')) destroy(`/asistencia/${id}`);
    };

    const openNew = () => {
        setEditando(null);
        reset();
        setIsOpen(true);
    };

    // Generar cuadrícula de calendario
    const calendarDays = useMemo(() => {
        const parts = statsMes.split('-');
        const year = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        
        const firstDay = new Date(year, month - 1, 1);
        let startDayOfWeek = firstDay.getDay(); // 0=Sunday, 1=Monday
        // Ajustamos para que Lunes sea 0 y Domingo sea 6
        startDayOfWeek = startDayOfWeek === 0 ? 6 : startDayOfWeek - 1;

        const totalDays = new Date(year, month, 0).getDate();
        const daysArray = [];

        // Relleno mes anterior
        const prevMonthTotalDays = new Date(year, month - 1, 0).getDate();
        for (let i = startDayOfWeek - 1; i >= 0; i--) {
            const dayNum = prevMonthTotalDays - i;
            const m = month === 1 ? 12 : month - 1;
            const y = month === 1 ? year - 1 : year;
            daysArray.push({
                day: dayNum,
                isCurrentMonth: false,
                dateString: `${y}-${String(m).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`,
            });
        }

        // Días mes actual
        for (let d = 1; d <= totalDays; d++) {
            daysArray.push({
                day: d,
                isCurrentMonth: true,
                dateString: `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`,
            });
        }

        // Relleno mes siguiente
        const totalGridCells = Math.ceil(daysArray.length / 7) * 7;
        const nextMonthDaysToAdd = totalGridCells - daysArray.length;
        for (let n = 1; n <= nextMonthDaysToAdd; n++) {
            const m = month === 12 ? 1 : month + 1;
            const y = month === 12 ? year + 1 : year;
            daysArray.push({
                day: n,
                isCurrentMonth: false,
                dateString: `${y}-${String(m).padStart(2, '0')}-${String(n).padStart(2, '0')}`,
            });
        }

        return daysArray;
    }, [statsMes]);

    // Estadísticas calculadas para el empleado seleccionado
    const calculatedStats = useMemo(() => {
        if (!statsAsistencias || statsAsistencias.length === 0) {
            return {
                presentes: 0,
                ausentes: 0,
                vacaciones: 0,
                licencias: 0,
                permisos: 0,
                teletrabajo: 0,
                totalHoras: 0,
                porcentajeAsistencia: 0,
            };
        }

        let presentes = 0;
        let ausentes = 0;
        let vacaciones = 0;
        let licencias = 0;
        let permisos = 0;
        let teletrabajo = 0;
        let totalHoras = 0;

        statsAsistencias.forEach((a) => {
            const estado = a.estado.toLowerCase();
            if (estado === 'presente') presentes++;
            else if (estado === 'ausente') ausentes++;
            else if (estado === 'vacaciones') vacaciones++;
            else if (estado === 'licencia') licencias++;
            else if (estado === 'permiso') permisos++;
            else if (estado === 'teletrabajo') teletrabajo++;

            totalHoras += Number(a.horas_trabajadas || 0);
        });

        const diasRegistrados = statsAsistencias.length;
        const diasAsistidos = presentes + teletrabajo + vacaciones + permisos + licencias;
        const porcentajeAsistencia = diasRegistrados > 0 
            ? Math.round((diasAsistidos / diasRegistrados) * 100) 
            : 0;

        return {
            presentes,
            ausentes,
            vacaciones,
            licencias,
            permisos,
            teletrabajo,
            totalHoras,
            porcentajeAsistencia,
        };
    }, [statsAsistencias]);

    const statsEmpleado = useMemo(() => {
        return empleados.find(e => String(e.id) === statsEmpleadoId);
    }, [statsEmpleadoId, empleados]);

    const mesNombre = useMemo(() => {
        const parts = statsMes.split('-');
        const monthNum = parseInt(parts[1]);
        const year = parts[0];
        const meses = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        return `${meses[monthNum - 1]} ${year}`;
    }, [statsMes]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Asistencia de Empleados" />
            <div className="space-y-6 p-6 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-5">
                    <div>
                        <h1 className="text-3xl font-extrabold tracking-tight text-foreground flex items-center gap-2">
                            <Clock className="h-8 w-8 text-primary" />
                            Control de Asistencia
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Gestione las asistencias, inasistencias, licencias y visualice resúmenes individuales de sus trabajadores.
                        </p>
                    </div>
                    <div className="flex gap-3 items-center self-start md:self-auto">
                        <BulkActions
                            baseUrl="/asistencia"
                            modelName="Asistencia"
                        />
                        {canCreate && (
                            <Button onClick={openNew} className="shadow-sm hover:shadow-md transition-all">
                                <Plus className="mr-2 h-4 w-4" />
                                Nuevo Registro
                            </Button>
                        )}
                    </div>
                </div>

                {/* Tabs Selector */}
                <div className="flex border-b border-muted overflow-x-auto">
                    <button
                        onClick={() => setActiveTab('listado')}
                        className={`px-4 md:px-5 py-3 text-xs md:text-sm font-semibold border-b-2 transition-all flex items-center gap-1.5 md:gap-2 -mb-[2px] whitespace-nowrap ${
                            activeTab === 'listado'
                                ? 'border-primary text-primary font-bold bg-muted/30 rounded-t-lg'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <FileText className="h-3.5 w-3.5 md:h-4 md:w-4" />
                        Historial de Registros
                    </button>
                    <button
                        onClick={() => setActiveTab('resumen')}
                        className={`px-4 md:px-5 py-3 text-xs md:text-sm font-semibold border-b-2 transition-all flex items-center gap-1.5 md:gap-2 -mb-[2px] whitespace-nowrap ${
                            activeTab === 'resumen'
                                ? 'border-primary text-primary font-bold bg-muted/30 rounded-t-lg'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <User className="h-3.5 w-3.5 md:h-4 md:w-4" />
                        Resumen por Empleado
                    </button>
                </div>

                {/* TAB LISTADO HISTÓRICO */}
                {activeTab === 'listado' && (
                    <div className="space-y-4">
                        {/* Filters Card */}
                        <Card className="shadow-sm border-muted/70 bg-card/60 backdrop-blur-sm">
                            <CardHeader className="py-4">
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    Filtrar Historial
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="pb-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs">Empleado</Label>
                                        <Select
                                            value={filterEmpleadoId}
                                            onValueChange={(val) => setFilterEmpleadoId(val)}
                                        >
                                            <SelectTrigger className="h-9">
                                                <SelectValue placeholder="Todos los empleados" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Todos los empleados</SelectItem>
                                                {empleados.map((emp) => (
                                                    <SelectItem key={emp.id} value={emp.id.toString()}>
                                                        {emp.nombre} {emp.apellido}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs">Estado</Label>
                                        <Select
                                            value={filterEstado}
                                            onValueChange={(val) => setFilterEstado(val)}
                                        >
                                            <SelectTrigger className="h-9">
                                                <SelectValue placeholder="Todos los estados" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Todos los estados</SelectItem>
                                                {estados.map((e) => (
                                                    <SelectItem key={e} value={e} className="capitalize">
                                                        {e}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs">Almacén</Label>
                                        <Select
                                            value={filterAlmacenId}
                                            onValueChange={(val) => setFilterAlmacenId(val)}
                                        >
                                            <SelectTrigger className="h-9">
                                                <SelectValue placeholder="Todos los almacenes" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Todos los almacenes</SelectItem>
                                                {almacenes.map((a) => (
                                                    <SelectItem key={a.id} value={a.id.toString()}>
                                                        {a.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs">Desde</Label>
                                        <Input
                                            type="date"
                                            value={filterDesde}
                                            onChange={(e) => setFilterDesde(e.target.value)}
                                            className="h-9"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs">Hasta</Label>
                                        <Input
                                            type="date"
                                            value={filterHasta}
                                            onChange={(e) => setFilterHasta(e.target.value)}
                                            className="h-9"
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2 mt-4 pt-3 border-t border-muted/50">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleClearFilters}
                                        className="h-8"
                                    >
                                        Limpiar
                                    </Button>
                                    <Button
                                        size="sm"
                                        onClick={handleApplyFilters}
                                        className="h-8"
                                    >
                                        Aplicar Filtros
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        {/* List Card */}
                        <Card className="shadow-sm">
                        <CardHeader className="pb-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Registros de Asistencia</CardTitle>
                                    <CardDescription>
                                        Se muestran {asistencias.total} registros coincidentes.
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-1 rounded-lg border bg-muted/30 p-0.5">
                                    <button onClick={() => setViewMode('table')} className={`rounded-md p-1.5 transition-colors ${viewMode === 'table' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tabla"><List className="h-4 w-4" /></button>
                                    <button onClick={() => setViewMode('cards')} className={`rounded-md p-1.5 transition-colors ${viewMode === 'cards' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tarjetas"><LayoutGrid className="h-4 w-4" /></button>
                                </div>
                            </div>
                        </CardHeader>
                            <CardContent>
{viewMode === 'table' ? (
                                <>
                                    {asistencias.data.length === 0 ? (
                                        <div className="py-12 text-center text-muted-foreground flex flex-col items-center justify-center gap-2">
                                            <AlertTriangle className="h-8 w-8 text-amber-500" />
                                            <p className="font-semibold text-lg">No hay registros de asistencia</p>
                                            <p className="text-sm">Prueba ajustando los filtros o añade un nuevo registro manualmente.</p>
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto rounded-md border max-h-[600px] overflow-y-auto">
                                            <table className="w-full text-sm">
                                                <thead className="sticky top-0 z-10 bg-muted/95 backdrop-blur-sm">
                                                    <tr className="border-b bg-muted/40">
                                                        <th className="px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground">Fecha</th>
                                                        <th className="px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground">Empleado</th>
                                                        <th className="hidden lg:table-cell px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground">Almacén</th>
                                                        <th className="px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground">Entrada</th>
                                                        <th className="px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground">Salida</th>
                                                        <th className="px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground text-center">Horas</th>
                                                        <th className="px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground">Estado</th>
                                                        <th className="hidden md:table-cell px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground max-w-[200px] truncate">Notas</th>
                                                        <th className="px-3 md:px-4 py-3 text-right font-semibold text-muted-foreground">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {asistencias.data.map((a) => (
                                                        <tr key={a.id} className="border-b hover:bg-muted/20 transition-all">
                                                            <td className="px-3 md:px-4 py-3 font-medium whitespace-nowrap text-xs md:text-sm">
                                                                {a.fecha ? a.fecha.split('T')[0] : '-'}
                                                            </td>
                                                            <td className="px-3 md:px-4 py-3">
                                                                <div className="font-medium text-foreground text-xs md:text-sm">
                                                                    {a.empleado ? `${a.empleado.nombre} ${a.empleado.apellido}` : '-'}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {a.empleado?.cargo || '-'}
                                                                </div>
                                                            </td>
                                                            <td className="hidden lg:table-cell px-3 md:px-4 py-3 text-muted-foreground text-xs md:text-sm">
                                                                {a.empleado?.almacen?.nombre || 'Sin almacén'}
                                                            </td>
                                                            <td className="px-3 md:px-4 py-3 font-mono text-xs">{a.hora_entrada || '-'}</td>
                                                            <td className="px-3 md:px-4 py-3 font-mono text-xs">{a.hora_salida || '-'}</td>
                                                            <td className="px-3 md:px-4 py-3 text-center font-semibold text-primary text-xs md:text-sm">{a.horas_trabajadas || '-'}</td>
                                                            <td className="px-3 md:px-4 py-3">
                                                                <span className={`px-2 md:px-2.5 py-0.5 rounded-full text-[10px] md:text-xs font-semibold border ${getEstadoStyle(a.estado)}`}>
                                                                    {a.estado}
                                                                </span>
                                                            </td>
                                                            <td className="hidden md:table-cell px-3 md:px-4 py-3 text-muted-foreground max-w-[200px] truncate text-xs md:text-sm">
                                                                {a.notas || a.notes || '-'}
                                                            </td>
                                                            <td className="px-3 md:px-4 py-3 text-right whitespace-nowrap">
                                                                {canEdit && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-7 w-7 md:h-8 md:w-8 hover:bg-muted/80 rounded-full"
                                                                        onClick={() => handleEdit(a)}
                                                                    >
                                                                        <Pencil className="h-3.5 w-3.5 md:h-4 md:w-4" />
                                                                    </Button>
                                                                )}
                                                                {canDelete && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-7 w-7 md:h-8 md:w-8 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-full"
                                                                        onClick={() => handleDelete(a.id)}
                                                                    >
                                                                        <Trash2 className="h-3.5 w-3.5 md:h-4 md:w-4" />
                                                                    </Button>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                    <div className="mt-4">
                                        <Pagination
                                            links={asistencias.links}
                                            meta={
                                                asistencias.meta || {
                                                    from: (asistencias as any).from,
                                                    to: (asistencias as any).to,
                                                    total: asistencias.total,
                                                }
                                            }
                                        />
                                    </div>
                                </>
                            ) : (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {asistencias.data.length === 0 ? (
                                        <div className="col-span-full py-12 text-center text-muted-foreground flex flex-col items-center justify-center gap-2">
                                            <AlertTriangle className="h-8 w-8 text-amber-500" />
                                            <p className="font-semibold text-lg">No hay registros de asistencia</p>
                                            <p className="text-sm">Prueba ajustando los filtros o añade un nuevo registro manualmente.</p>
                                        </div>
                                    ) : (
                                        asistencias.data.map((a) => (
                                            <div key={a.id} className="rounded-lg border bg-card p-4 shadow-sm flex flex-col gap-3">
                                                <div className="flex items-start justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <CalendarIcon className="h-4 w-4 text-muted-foreground" />
                                                        <span className="font-semibold">{a.fecha ? a.fecha.split('T')[0] : '-'}</span>
                                                    </div>
                                                    <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold border ${getEstadoStyle(a.estado)}`}>
                                                        {a.estado}
                                                    </span>
                                                </div>
                                                <div className="space-y-1.5 text-sm">
                                                    <div className="flex items-center gap-2 font-medium">
                                                        <User className="h-4 w-4 text-muted-foreground" />
                                                        {a.empleado ? `${a.empleado.nombre} ${a.empleado.apellido}` : '-'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground ml-6">{a.empleado?.cargo || '-'}</div>
                                                    <div className="text-xs text-muted-foreground">{a.empleado?.almacen?.nombre || 'Sin almacén'}</div>
                                                    <div className="flex gap-4 text-xs">
                                                        <span>Ent: <strong>{a.hora_entrada || '-'}</strong></span>
                                                        <span>Sal: <strong>{a.hora_salida || '-'}</strong></span>
                                                        <span>Horas: <strong className="text-primary">{a.horas_trabajadas || '-'}</strong></span>
                                                    </div>
                                                    {(a.notas || a.notes) ? (
                                                        <div className="text-xs text-muted-foreground truncate">{a.notas || a.notes}</div>
                                                    ) : null}
                                                </div>
                                                <div className="flex justify-end gap-1 border-t pt-2 mt-auto">
                                                    {canEdit && (
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 hover:bg-muted/80 rounded-full" onClick={() => handleEdit(a)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-full" onClick={() => handleDelete(a.id)}>
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
                )}

                {/* TAB RESUMEN POR EMPLEADO */}
                {activeTab === 'resumen' && (
                    <div className="space-y-6">
                        {/* Selector de almacén, empleado y mes */}
                        <Card className="shadow-sm border-muted/80 bg-card/60 backdrop-blur-sm">
                            <CardContent className="p-5 flex flex-col sm:flex-row items-end gap-4">
                                <div className="space-y-1.5 flex-1 w-full sm:max-w-[260px]">
                                    <Label className="text-xs font-semibold text-muted-foreground">Almacén</Label>
                                    <Select
                                        value={statsAlmacenId}
                                        onValueChange={(val) => {
                                            setStatsAlmacenId(val);
                                            setStatsEmpleadoId('0');
                                            handleApplyStatsFilters('0', statsMes, val);
                                        }}
                                    >
                                        <SelectTrigger className="h-10">
                                            <SelectValue placeholder="Todos los almacenes" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="0">Todos los almacenes</SelectItem>
                                            {almacenes.map((a) => (
                                                <SelectItem key={a.id} value={a.id.toString()}>{a.nombre}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5 flex-1 w-full">
                                    <Label className="text-xs font-semibold text-muted-foreground">Empleado</Label>
                                    <Select
                                        value={statsEmpleadoId}
                                        onValueChange={(val) => {
                                            setStatsEmpleadoId(val);
                                            handleApplyStatsFilters(val, statsMes, statsAlmacenId);
                                        }}
                                    >
                                        <SelectTrigger className="h-10">
                                            <SelectValue placeholder="Seleccionar empleado..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="0">Todos los empleados</SelectItem>
                                            {filteredEmpleados.map((emp) => (
                                                <SelectItem key={emp.id} value={emp.id.toString()}>
                                                    {emp.nombre} {emp.apellido} {emp.cargo ? `(${emp.cargo})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5 w-full sm:w-[240px]">
                                    <Label className="text-xs font-semibold text-muted-foreground">Período de Análisis</Label>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            onClick={handlePrevMonth}
                                            className="h-10 w-10 shrink-0"
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <div className="flex-1 text-center h-10 border border-input rounded-md flex items-center justify-center font-medium bg-background px-3 text-sm">
                                            {mesNombre}
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            onClick={handleNextMonth}
                                            className="h-10 w-10 shrink-0"
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Sin almacén ni empleado seleccionados */}
                        {statsAlmacenId === '0' && statsEmpleadoId === '0' ? (
                            <Card className="border-dashed py-16 text-center text-muted-foreground flex flex-col items-center justify-center gap-3">
                                <User className="h-12 w-12 text-muted-foreground/60 bg-muted p-2 rounded-full" />
                                <h3 className="font-semibold text-lg">Visualización por Empleado o Almacén</h3>
                                <p className="text-sm max-w-md">
                                    Selecciona un almacén para ver estadísticas agrupadas o un empleado específico para ver su calendario mensual y detalle.
                                </p>
                            </Card>
                        ) : statsEmpleadoId !== '0' ? (
                            /* Vista detallada: empleado específico */
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <div className="space-y-6 lg:col-span-1">
                                    <Card className="shadow-sm">
                                        <CardHeader className="pb-3 border-b">
                                            <div className="flex items-center gap-3">
                                                <div className="bg-primary/10 text-primary p-2 rounded-full">
                                                    <User className="h-6 w-6" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">
                                                        {statsEmpleado?.nombre} {statsEmpleado?.apellido}
                                                    </CardTitle>
                                                    <CardDescription className="text-xs">
                                                        {statsEmpleado?.cargo || 'Sin Cargo'} • {statsEmpleado?.departamento || 'Sin Departamento'}
                                                    </CardDescription>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="pt-4 space-y-4">
                                            <div className="flex flex-col items-center justify-center py-4 bg-muted/20 rounded-lg">
                                                <div className="relative flex items-center justify-center">
                                                    <svg className="w-28 h-28 transform -rotate-90">
                                                        <circle cx="56" cy="56" r="48" stroke="currentColor" strokeWidth="8" fill="transparent" className="text-muted/40" />
                                                        <circle cx="56" cy="56" r="48" stroke="currentColor" strokeWidth="8" fill="transparent" strokeDasharray={2 * Math.PI * 48} strokeDashoffset={2 * Math.PI * 48 * (1 - calculatedStats.porcentajeAsistencia / 100)} className="text-primary transition-all duration-500" />
                                                    </svg>
                                                    <div className="absolute flex flex-col items-center">
                                                        <span className="text-2xl font-black text-foreground">{calculatedStats.porcentajeAsistencia}%</span>
                                                        <span className="text-[10px] text-muted-foreground uppercase font-bold tracking-wider">Asistencia</span>
                                                    </div>
                                                </div>
                                                <p className="text-xs text-muted-foreground text-center mt-3 px-3">Porcentaje basado en los días con registro cargado en el mes.</p>
                                            </div>

                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="border rounded-md p-3 bg-card flex flex-col justify-between">
                                                    <span className="text-[10px] text-muted-foreground font-bold uppercase">Horas Totales</span>
                                                    <div className="flex items-baseline gap-1 mt-1">
                                                        <span className="text-xl font-bold text-foreground">{calculatedStats.totalHoras}</span>
                                                        <span className="text-xs text-muted-foreground">hrs</span>
                                                    </div>
                                                </div>
                                                <div className="border rounded-md p-3 bg-card flex flex-col justify-between">
                                                    <span className="text-[10px] text-muted-foreground font-bold uppercase">Inasistencias</span>
                                                    <div className="flex items-baseline gap-1 mt-1">
                                                        <span className="text-xl font-bold text-rose-600">{calculatedStats.ausentes}</span>
                                                        <span className="text-xs text-muted-foreground">días</span>
                                                    </div>
                                                </div>
                                                <div className="border rounded-md p-3 bg-card flex flex-col justify-between">
                                                    <span className="text-[10px] text-muted-foreground font-bold uppercase">Teletrabajo</span>
                                                    <div className="flex items-baseline gap-1 mt-1">
                                                        <span className="text-xl font-bold text-cyan-600">{calculatedStats.teletrabajo}</span>
                                                        <span className="text-xs text-muted-foreground">días</span>
                                                    </div>
                                                </div>
                                                <div className="border rounded-md p-3 bg-card flex flex-col justify-between">
                                                    <span className="text-[10px] text-muted-foreground font-bold uppercase">Lic./Vacaciones</span>
                                                    <div className="flex items-baseline gap-1 mt-1">
                                                        <span className="text-xl font-bold text-purple-600">{calculatedStats.licencias + calculatedStats.vacaciones}</span>
                                                        <span className="text-xs text-muted-foreground">días</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="shadow-sm">
                                        <CardHeader className="py-3 border-b">
                                            <CardTitle className="text-sm font-semibold">Leyenda de Estados</CardTitle>
                                        </CardHeader>
                                        <CardContent className="pt-3 space-y-2">
                                            {estados.map((est) => (
                                                <div key={est} className="flex items-center justify-between text-xs">
                                                    <div className="flex items-center gap-2">
                                                        <span className={`h-2.5 w-2.5 rounded-full ${getEstadoDotColor(est)}`} />
                                                        <span className="capitalize font-medium text-foreground">{est}</span>
                                                    </div>
                                                    <span className="text-muted-foreground font-semibold">
                                                        {est === 'presente' && calculatedStats.presentes}
                                                        {est === 'ausente' && calculatedStats.ausentes}
                                                        {est === 'vacaciones' && calculatedStats.vacaciones}
                                                        {est === 'licencia' && calculatedStats.licencias}
                                                        {est === 'permiso' && calculatedStats.permisos}
                                                        {est === 'teletrabajo' && calculatedStats.teletrabajo}
                                                    </span>
                                                </div>
                                            ))}
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="lg:col-span-2">
                                    <Card className="shadow-sm">
                                        <CardHeader className="pb-3 border-b flex flex-row items-center justify-between">
                                            <div>
                                                <CardTitle>Calendario Mensual</CardTitle>
                                                <CardDescription>Haga clic en un día registrado para ver más detalles.</CardDescription>
                                            </div>
                                            <div className="text-xs text-muted-foreground font-semibold flex items-center gap-1.5 bg-muted/40 px-2.5 py-1 rounded-full">
                                                <CalendarIcon className="h-3.5 w-3.5" />
                                                {mesNombre}
                                            </div>
                                        </CardHeader>
                                        <CardContent className="pt-4">
                                            <div className="grid grid-cols-7 gap-1 md:gap-2 text-center font-bold text-[10px] md:text-xs text-muted-foreground mb-2">
                                                <div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div><div>Dom</div>
                                            </div>
                                            <div className="grid grid-cols-7 gap-1 md:gap-2">
                                                {calendarDays.map((cell, idx) => {
                                                    const asistenciaDia = statsAsistencias.find(
                                                        (a) => a.fecha && a.fecha.split('T')[0] === cell.dateString
                                                    );
                                                    const isWeekend = idx % 7 === 5 || idx % 7 === 6;
                                                    return (
                                                        <div key={cell.dateString + idx}
                                                            className={`min-h-[50px] md:min-h-[75px] border rounded-md p-1 flex flex-col justify-between transition-all group relative cursor-default ${
                                                                !cell.isCurrentMonth ? 'opacity-40 bg-muted/10 border-muted/30'
                                                                : isWeekend ? 'bg-muted/20 border-muted/50'
                                                                : 'bg-background hover:border-primary/50'
                                                            } ${asistenciaDia ? 'border-l-4 ' + getEstadoStyle(asistenciaDia.estado).split(' ').pop() : ''}`}>
                                                            <div className="flex items-center justify-between">
                                                                <span className={`text-[10px] md:text-xs font-bold ${cell.isCurrentMonth ? 'text-foreground' : 'text-muted-foreground/60'}`}>{cell.day}</span>
                                                                {asistenciaDia && <span className={`h-1.5 w-1.5 md:h-2 md:w-2 rounded-full ${getEstadoDotColor(asistenciaDia.estado)}`} />}
                                                            </div>
                                                            {asistenciaDia ? (
                                                                <div className="mt-0.5 md:mt-1 space-y-0.5">
                                                                    <div className="hidden md:block text-[10px] font-semibold capitalize truncate text-foreground/80">{asistenciaDia.estado}</div>
                                                                    {asistenciaDia.hora_entrada && (
                                                                        <div className="text-[8px] md:text-[9px] text-muted-foreground font-mono flex items-center gap-0.5">
                                                                            <Clock className="h-1.5 w-1.5 md:h-2 md:w-2 shrink-0" />
                                                                            {asistenciaDia.hora_entrada.substring(0, 5)}{asistenciaDia.hora_salida && ` - ${asistenciaDia.hora_salida.substring(0, 5)}`}
                                                                        </div>
                                                                    )}
                                                                    {asistenciaDia.horas_trabajadas !== null && <div className="hidden sm:block text-[8px] md:text-[9px] text-primary/80 font-bold">{asistenciaDia.horas_trabajadas} hrs</div>}
                                                                </div>
                                                            ) : (
                                                                <div className="hidden md:block text-[8px] text-muted-foreground/50 italic mt-auto">{cell.isCurrentMonth && !isWeekend ? 'Sin marcación' : ''}</div>
                                                            )}
                                                            {asistenciaDia && (
                                                                <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-1 hidden group-hover:block z-20 w-48 bg-popover text-popover-foreground border shadow-lg rounded p-2 text-xs">
                                                                    <div className="font-bold border-b pb-1 mb-1">Asistencia: {cell.dateString}</div>
                                                                    <div className="space-y-0.5">
                                                                        <p><span className="font-semibold text-muted-foreground">Estado:</span> <span className="capitalize">{asistenciaDia.estado}</span></p>
                                                                        {asistenciaDia.hora_entrada && <p><span className="font-semibold text-muted-foreground">Ingreso:</span> {asistenciaDia.hora_entrada}</p>}
                                                                        {asistenciaDia.hora_salida && <p><span className="font-semibold text-muted-foreground">Salida:</span> {asistenciaDia.hora_salida}</p>}
                                                                        {asistenciaDia.horas_trabajadas && <p><span className="font-semibold text-muted-foreground">Trabajadas:</span> {asistenciaDia.horas_trabajadas} horas</p>}
                                                                        {(asistenciaDia.notas || asistenciaDia.notes) && <p className="mt-1 border-t pt-1 italic text-muted-foreground">"{asistenciaDia.notas || asistenciaDia.notes}"</p>}
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        ) : (
                            /* Vista agrupada: resumen por almacén */
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <div className="space-y-6 lg:col-span-1">
                                    <Card className="shadow-sm">
                                        <CardHeader className="pb-3 border-b">
                                            <div className="flex items-center gap-3">
                                                <div className="bg-primary/10 text-primary p-2 rounded-full">
                                                    <User className="h-6 w-6" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">
                                                        {statsAlmacenNombre || 'Almacén'}
                                                    </CardTitle>
                                                    <CardDescription className="text-xs">
                                                        {filteredEmpleados.length} empleado{filteredEmpleados.length !== 1 ? 's' : ''} • {mesNombre}
                                                    </CardDescription>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="pt-4 space-y-4">
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="border rounded-md p-3 bg-card flex flex-col justify-between">
                                                    <span className="text-[10px] text-muted-foreground font-bold uppercase">Horas Totales</span>
                                                    <div className="flex items-baseline gap-1 mt-1">
                                                        <span className="text-xl font-bold text-foreground">{calculatedStats.totalHoras}</span>
                                                        <span className="text-xs text-muted-foreground">hrs</span>
                                                    </div>
                                                </div>
                                                <div className="border rounded-md p-3 bg-card flex flex-col justify-between">
                                                    <span className="text-[10px] text-muted-foreground font-bold uppercase">Inasistencias</span>
                                                    <div className="flex items-baseline gap-1 mt-1">
                                                        <span className="text-xl font-bold text-rose-600">{calculatedStats.ausentes}</span>
                                                        <span className="text-xs text-muted-foreground">días</span>
                                                    </div>
                                                </div>
                                                <div className="border rounded-md p-3 bg-card flex flex-col justify-between">
                                                    <span className="text-[10px] text-muted-foreground font-bold uppercase">Teletrabajo</span>
                                                    <div className="flex items-baseline gap-1 mt-1">
                                                        <span className="text-xl font-bold text-cyan-600">{calculatedStats.teletrabajo}</span>
                                                        <span className="text-xs text-muted-foreground">días</span>
                                                    </div>
                                                </div>
                                                <div className="border rounded-md p-3 bg-card flex flex-col justify-between">
                                                    <span className="text-[10px] text-muted-foreground font-bold uppercase">Lic./Vacaciones</span>
                                                    <div className="flex items-baseline gap-1 mt-1">
                                                        <span className="text-xl font-bold text-purple-600">{calculatedStats.licencias + calculatedStats.vacaciones}</span>
                                                        <span className="text-xs text-muted-foreground">días</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="shadow-sm">
                                        <CardHeader className="py-3 border-b">
                                            <CardTitle className="text-sm font-semibold">Leyenda de Estados</CardTitle>
                                        </CardHeader>
                                        <CardContent className="pt-3 space-y-2">
                                            {estados.map((est) => (
                                                <div key={est} className="flex items-center justify-between text-xs">
                                                    <div className="flex items-center gap-2">
                                                        <span className={`h-2.5 w-2.5 rounded-full ${getEstadoDotColor(est)}`} />
                                                        <span className="capitalize font-medium text-foreground">{est}</span>
                                                    </div>
                                                    <span className="text-muted-foreground font-semibold">
                                                        {est === 'presente' && calculatedStats.presentes}
                                                        {est === 'ausente' && calculatedStats.ausentes}
                                                        {est === 'vacaciones' && calculatedStats.vacaciones}
                                                        {est === 'licencia' && calculatedStats.licencias}
                                                        {est === 'permiso' && calculatedStats.permisos}
                                                        {est === 'teletrabajo' && calculatedStats.teletrabajo}
                                                    </span>
                                                </div>
                                            ))}
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="lg:col-span-2">
                                    <Card className="shadow-sm">
                                        <CardHeader className="pb-3 border-b">
                                            <CardTitle>Empleados del Almacén</CardTitle>
                                            <CardDescription>Resumen individual de cada empleado en {statsAlmacenNombre || 'el almacén seleccionado'}</CardDescription>
                                        </CardHeader>
                                        <CardContent className="pt-4">
                                            {filteredEmpleados.length === 0 ? (
                                                <div className="py-8 text-center text-muted-foreground">
                                                    <p>No hay empleados en este almacén.</p>
                                                </div>
                                            ) : (
                                                <div className="overflow-x-auto rounded-md border max-h-[500px] overflow-y-auto">
                                                    <table className="w-full text-sm">
                                                        <thead className="sticky top-0 z-10 bg-muted/95 backdrop-blur-sm">
                                                            <tr className="border-b bg-muted/40">
                                                                <th className="px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground">Empleado</th>
                                                                <th className="hidden sm:table-cell px-3 md:px-4 py-3 text-left font-semibold text-muted-foreground">Cargo</th>
                                                                <th className="px-3 md:px-4 py-3 text-center font-semibold text-muted-foreground">Presente</th>
                                                                <th className="px-3 md:px-4 py-3 text-center font-semibold text-muted-foreground">Ausente</th>
                                                                <th className="hidden sm:table-cell px-3 md:px-4 py-3 text-center font-semibold text-muted-foreground">Lic./Vac.</th>
                                                                <th className="px-3 md:px-4 py-3 text-center font-semibold text-muted-foreground">Horas</th>
                                                                <th className="px-3 md:px-4 py-3 text-center font-semibold text-muted-foreground">% Asist.</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {filteredEmpleados.map((emp) => {
                                                                const empRecords = statsAsistencias.filter(
                                                                    (a) => String(a.empleado_id) === String(emp.id)
                                                                );
                                                                const presentes = empRecords.filter((a) => a.estado === 'presente').length;
                                                                const ausentes = empRecords.filter((a) => a.estado === 'ausente').length;
                                                                const licVac = empRecords.filter(
                                                                    (a) => a.estado === 'licencia' || a.estado === 'vacaciones'
                                                                ).length;
                                                                const totalHoras = empRecords.reduce(
                                                                    (sum, a) => sum + Number(a.horas_trabajadas || 0), 0
                                                                );
                                                                const pct = empRecords.length > 0
                                                                    ? Math.round(((presentes + empRecords.filter((a) => a.estado === 'teletrabajo' || a.estado === 'permiso').length) / empRecords.length) * 100)
                                                                    : 0;
                                                                return (
                                                                    <tr key={emp.id} className="border-b hover:bg-muted/20 transition-all">
                                                                        <td className="px-3 md:px-4 py-3">
                                                                            <div className="font-medium text-foreground text-xs md:text-sm">{emp.nombre} {emp.apellido}</div>
                                                                            {emp.rut && <div className="text-xs text-muted-foreground">{emp.rut}</div>}
                                                                        </td>
                                                                        <td className="hidden sm:table-cell px-3 md:px-4 py-3 text-muted-foreground text-xs md:text-sm">{emp.cargo || '-'}</td>
                                                                        <td className="px-3 md:px-4 py-3 text-center text-emerald-600 font-semibold">{presentes}</td>
                                                                        <td className="px-3 md:px-4 py-3 text-center text-rose-600 font-semibold">{ausentes}</td>
                                                                        <td className="hidden sm:table-cell px-3 md:px-4 py-3 text-center text-purple-600 font-semibold">{licVac}</td>
                                                                        <td className="px-3 md:px-4 py-3 text-center font-semibold text-primary">{totalHoras}</td>
                                                                        <td className="px-3 md:px-4 py-3 text-center font-semibold">{pct}%</td>
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
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* MODAL CREAR / EDITAR REGISTRO */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="flex max-h-[90vh] flex-col overflow-y-auto p-0 w-[95vw] sm:max-w-lg md:max-w-xl lg:max-w-2xl">
                    <DialogHeader className="shrink-0 p-6 pb-2">
                        <DialogTitle className="text-lg font-bold flex items-center gap-2">
                            <span className="w-2.5 h-2.5 rounded-full bg-blue-600 animate-pulse"></span>
                            {editando ? 'Editar Registro de Asistencia' : 'Nuevo Registro de Asistencia'}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="flex flex-1 flex-col overflow-hidden">
                        <div className="flex-1 overflow-y-auto p-6 pt-2 space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {/* Empleado */}
                                <div className="space-y-1.5 md:col-span-2">
                                    <Label className="text-xs font-semibold text-muted-foreground">Empleado *</Label>
                                    <Select
                                        value={data.empleado_id}
                                        onValueChange={(v) => {
                                            setData('empleado_id', v);
                                            if (!editando) {
                                                const emp = empleados.find((e) => String(e.id) === v);
                                                if (emp) {
                                                    const entrada = emp.hora_entrada || '';
                                                    const salida = emp.hora_salida || '';
                                                    setData(prev => ({
                                                        ...prev,
                                                        hora_entrada: entrada,
                                                        hora_salida: salida,
                                                        horas_trabajadas: calcularHorasTrabajadas(entrada, salida),
                                                    }));
                                                }
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="h-10 bg-background text-sm">
                                            <SelectValue placeholder="Seleccionar empleado" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {empleados.map((emp) => (
                                                <SelectItem
                                                    key={emp.id}
                                                    value={emp.id.toString()}
                                                    className="text-sm"
                                                >
                                                    {emp.nombre} {emp.apellido}
                                                    {emp.rut ? ` — ${emp.rut}` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.empleado_id && (
                                        <p className="text-xs text-red-500 font-semibold mt-1">
                                            {errors.empleado_id}
                                        </p>
                                    )}
                                </div>

                                {selectedEmpleado && (
                                    <div className="md:col-span-2 bg-muted/30 border border-muted/60 rounded-lg p-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                                        <div className="flex items-center gap-2 text-sm font-medium text-foreground shrink-0">
                                            <User className="h-4 w-4 text-primary" />
                                            {selectedEmpleado.nombre} {selectedEmpleado.apellido}
                                        </div>
                                        <div className="hidden sm:block text-muted-foreground/40">|</div>
                                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            {selectedEmpleado.rut && (
                                                <span><span className="font-semibold">RUT:</span> {selectedEmpleado.rut}</span>
                                            )}
                                            {selectedEmpleado.cargo && (
                                                <span><span className="font-semibold">Cargo:</span> {selectedEmpleado.cargo}</span>
                                            )}
                                            {selectedEmpleado.almacen?.nombre && (
                                                <span><span className="font-semibold">Almacén:</span> {selectedEmpleado.almacen.nombre}</span>
                                            )}
                                            {selectedEmpleado.hora_entrada && selectedEmpleado.hora_salida && (
                                                <span><span className="font-semibold">Horario:</span> {selectedEmpleado.hora_entrada.substring(0, 5)} → {selectedEmpleado.hora_salida.substring(0, 5)}</span>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Fecha Inicio */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-semibold text-muted-foreground">Fecha Inicio *</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha}
                                        onChange={(e) => {
                                            setData('fecha', e.target.value);
                                            if (!editando && data.fecha_fin && e.target.value > data.fecha_fin) {
                                                setData('fecha_fin', e.target.value);
                                            }
                                        }}
                                        className="h-10 text-sm"
                                    />
                                    {errors.fecha && (
                                        <p className="text-xs text-red-500 font-semibold mt-1">
                                            {errors.fecha}
                                        </p>
                                    )}
                                </div>

                                {/* Fecha Fin */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-semibold text-muted-foreground">Fecha Fin</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_fin}
                                        onChange={(e) => setData('fecha_fin', e.target.value)}
                                        className="h-10 text-sm"
                                    />
                                </div>

                                {/* Preview de días */}
                                {!editando && data.fecha && data.fecha_fin && diasCount > 1 && (
                                    <div className="md:col-span-2 -mt-2 text-xs text-muted-foreground bg-primary/5 border border-primary/10 rounded-md px-3 py-2 flex items-center gap-2">
                                        <CalendarIcon className="h-3.5 w-3.5 text-primary shrink-0" />
                                        Se crearán <span className="font-semibold text-foreground">{diasCount} registros</span> de asistencia ({data.fecha} → {data.fecha_fin})
                                    </div>
                                )}

                                {/* Estado */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-semibold text-muted-foreground">Estado *</Label>
                                    <Select
                                        value={data.estado}
                                        onValueChange={(v) => setData('estado', v)}
                                    >
                                        <SelectTrigger className="h-10 bg-background text-sm">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {estados.map((e) => (
                                                <SelectItem key={e} value={e} className="capitalize text-sm">
                                                    {e}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.estado && (
                                        <p className="text-xs text-red-500 font-semibold mt-1">
                                            {errors.estado}
                                        </p>
                                    )}
                                </div>

                                {/* Hora Entrada */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-semibold text-muted-foreground">Hora Entrada</Label>
                                    <Input
                                        placeholder="e.g. 08:00"
                                        value={data.hora_entrada}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            setData('hora_entrada', val);
                                            setData('horas_trabajadas', calcularHorasTrabajadas(val, data.hora_salida));
                                        }}
                                        className="h-10 text-sm"
                                    />
                                    {errors.hora_entrada && (
                                        <p className="text-xs text-red-500 font-semibold mt-1">
                                            {errors.hora_entrada}
                                        </p>
                                    )}
                                </div>

                                {/* Hora Salida */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-semibold text-muted-foreground">Hora Salida</Label>
                                    <Input
                                        placeholder="e.g. 17:00"
                                        value={data.hora_salida}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            setData('hora_salida', val);
                                            setData('horas_trabajadas', calcularHorasTrabajadas(data.hora_entrada, val));
                                        }}
                                        className="h-10 text-sm"
                                    />
                                    {errors.hora_salida && (
                                        <p className="text-xs text-red-500 font-semibold mt-1">
                                            {errors.hora_salida}
                                        </p>
                                    )}
                                </div>

                                {/* Horas Trabajadas */}
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-semibold text-muted-foreground">Horas Trabajadas</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        value={data.horas_trabajadas}
                                        onChange={(e) =>
                                            setData('horas_trabajadas', parseFloat(e.target.value) || 0)
                                        }
                                        className="h-10 text-sm"
                                    />
                                    {errors.horas_trabajadas && (
                                        <p className="text-xs text-red-500 font-semibold mt-1">
                                            {errors.horas_trabajadas}
                                        </p>
                                    )}
                                </div>

                                {/* Notas */}
                                <div className="space-y-1.5 md:col-span-2">
                                    <Label className="text-xs font-semibold text-muted-foreground">Notas / Observaciones</Label>
                                    <textarea
                                        value={data.notas}
                                        onChange={(e) => setData('notas', e.target.value)}
                                        placeholder="Detalles del registro, atrasos, justificaciones, etc."
                                        className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    />
                                    {errors.notas && (
                                        <p className="text-xs text-red-500 font-semibold mt-1">
                                            {errors.notas}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="shrink-0 border-t bg-muted/10 p-6 pt-4 flex gap-2 sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                                className="h-10 text-sm"
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" className="h-10 bg-blue-600 hover:bg-blue-700 text-sm text-white">
                                {editando
                                    ? 'Actualizar Registro'
                                    : `Crear Registro${!editando && data.fecha && data.fecha_fin && diasCount > 1 ? ` (${diasCount})` : ''}`
                                }
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
