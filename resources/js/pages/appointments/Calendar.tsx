import { Head, router, useForm } from '@inertiajs/react';
import {
    format,
    isSameDay,
    startOfMonth,
    endOfMonth,
    isWithinInterval,
    startOfWeek,
    addDays,
    addWeeks,
    addMonths,
    subWeeks,
    subMonths,
    subDays,
    differenceInMinutes,
} from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Calendar as CalendarIcon,
    Clock,
    CheckCircle2,
    AlertCircle,
    ExternalLink,
    RefreshCw,
    Mail,
    LogOut,
    Loader2,
    X,
    User,
    ChevronLeft,
    ChevronRight,
    Plus,
    CalendarDays,
    List,
    LayoutGrid,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
} from 'recharts';
import AppLayout from '@/layouts/app-layout';

export default function CalendarView({
    appointments,
    services,
    clients,
    googleConnected,
    googleAuthUrl,
    googleConnectedEmail,
    googleCalendarId,
    monthlyStats,
    appointmentsByDayMonth,
    statusDistributionMonth,
    monthServices,
    providerWorkload,
}: {
    appointments: any[];
    services: any[];
    clients: any[];
    googleConnected: boolean;
    googleAuthUrl: string | null;
    googleConnectedEmail: string | null;
    googleCalendarId: string | null;
    monthlyStats: {
        total: number;
        completadas: number;
        canceladas: number;
        ingresos: number;
        pendientes: number;
        tasaCompletitud: number;
    };
    appointmentsByDayMonth: { date: number; total: number }[];
    statusDistributionMonth: { name: string; value: number; color: string }[];
    monthServices: { name: string; value: number }[];
    providerWorkload: { name: string; photo: string; total: number }[];
}) {
    const [showGoogleConfig, setShowGoogleConfig] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [syncedEvents, setSyncedEvents] = useState<any[]>([]);
    const [syncError, setSyncError] = useState<string | null>(null);
    const [viewMode, setViewMode] = useState<'day' | 'week' | 'month'>('month');
    const [currentMonth, setCurrentMonth] = useState(() =>
        startOfMonth(new Date()),
    );
    const [selectedDay, setSelectedDay] = useState<Date | null>(new Date());
    const currentDate = useMemo(() => selectedDay || new Date(), [selectedDay]);

    const {
        data: googleData,
        setData: setGoogleData,
        post: postGoogle,
        processing: googleProcessing,
    } = useForm({
        google_calendar_id: googleCalendarId || '',
    });

    const { data, setData, post, processing, errors, reset } = useForm({
        client_id: '',
        producto_id: '',
        start_time: '',
        end_time: '',
        notes: '',
    });

    const openCreateModal = (date?: string) => {
        reset();
        if (date) {
            const startDate = new Date(date + 'T09:00');
            const endDate = new Date(startDate.getTime() + 60 * 60 * 1000);
            setData({
                client_id: '',
                producto_id: '',
                start_time: format(startDate, "yyyy-MM-dd'T'HH:mm"),
                end_time: format(endDate, "yyyy-MM-dd'T'HH:mm"),
                notes: '',
            });
        }
        setShowCreateModal(true);
    };

    const handleSaveGoogleConfig = (e: React.FormEvent) => {
        e.preventDefault();
        postGoogle('/appointments/calendar/google-config', {
            onSuccess: () => {
                setShowGoogleConfig(false);
            },
        });
    };

    const handleSync = async () => {
        setSyncing(true);
        setSyncError(null);
        try {
            const csrfToken =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') || '';
            const res = await fetch('/appointments/calendar/sync', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            });
            const json = await res.json();
            if (json.success) {
                setSyncedEvents(json.events || []);
            } else {
                setSyncError(json.error || 'Error al sincronizar.');
            }
        } catch {
            setSyncError('Error de conexión con el servidor.');
        } finally {
            setSyncing(false);
        }
    };

    const todayAppointments = appointments.filter((app) => {
        const appDate = new Date(app.start_time);
        const today = new Date();
        return (
            appDate.getFullYear() === today.getFullYear() &&
            appDate.getMonth() === today.getMonth() &&
            appDate.getDate() === today.getDate()
        );
    });

    const monthStart = startOfMonth(currentMonth);
    const monthEnd = endOfMonth(currentMonth);

    const allAppointments = useMemo(
        () => [...appointments, ...syncedEvents],
        [appointments, syncedEvents],
    );

    const citasEnMes = useMemo(
        () =>
            allAppointments.filter((c: any) => {
                const d = new Date(
                    c.start_time ||
                        c.start?.dateTime ||
                        c.start?.date ||
                        c.start,
                );
                return isWithinInterval(d, {
                    start: monthStart,
                    end: monthEnd,
                });
            }),
        [allAppointments, monthStart, monthEnd],
    );

    const diasConCitas = useMemo(() => {
        const map = new Map<string, any[]>();
        for (const c of citasEnMes) {
            const dateStr =
                c.start_time || c.start?.dateTime || c.start?.date || c.start;
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) continue;
            const key = format(d, 'yyyy-MM-dd');
            if (!map.has(key)) map.set(key, []);
            map.get(key)!.push(c);
        }
        return map;
    }, [citasEnMes]);

    const citasDelDia = useMemo(() => {
        if (!selectedDay) return [];
        return diasConCitas.get(format(selectedDay, 'yyyy-MM-dd')) ?? [];
    }, [diasConCitas, selectedDay]);

    const daysInMonth = useMemo(() => {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const numDays = new Date(year, month + 1, 0).getDate();
        const days: (number | null)[] = [];
        for (let i = 0; i < firstDay; i++) days.push(null);
        for (let d = 1; d <= numDays; d++) days.push(d);
        return days;
    }, [currentMonth]);

    const goPrev = () => {
        if (viewMode === 'month') {
            setCurrentMonth(subMonths(currentMonth, 1));
        } else if (viewMode === 'week') {
            const newDay = subWeeks(currentDate, 1);
            setSelectedDay(newDay);
            setCurrentMonth(startOfMonth(newDay));
        } else {
            const newDay = subDays(currentDate, 1);
            setSelectedDay(newDay);
            setCurrentMonth(startOfMonth(newDay));
        }
    };

    const goNext = () => {
        if (viewMode === 'month') {
            setCurrentMonth(addMonths(currentMonth, 1));
        } else if (viewMode === 'week') {
            const newDay = addWeeks(currentDate, 1);
            setSelectedDay(newDay);
            setCurrentMonth(startOfMonth(newDay));
        } else {
            const newDay = addDays(currentDate, 1);
            setSelectedDay(newDay);
            setCurrentMonth(startOfMonth(newDay));
        }
    };

    const goToday = () => {
        const today = new Date();
        setSelectedDay(today);
        setCurrentMonth(startOfMonth(today));
    };

    const weekStart = useMemo(
        () => startOfWeek(currentDate, { weekStartsOn: 0 }),
        [currentDate],
    );

    const weekDays = useMemo(
        () => Array.from({ length: 7 }, (_, i) => addDays(weekStart, i)),
        [weekStart],
    );

    const hours = Array.from({ length: 13 }, (_, i) => i + 8); // 8:00 - 20:00

    const statusStyles: Record<string, string> = {
        pendiente: 'bg-amber-500/10 text-amber-600 border-amber-500/20',
        confirmada: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
        en_curso: 'bg-violet-500/10 text-violet-600 border-violet-500/20',
        completada: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
        cancelada: 'bg-rose-500/10 text-rose-600 border-rose-500/20',
        no_show: 'bg-gray-500/10 text-gray-600 border-gray-500/20',
    };

    const statusLabels: Record<string, string> = {
        pendiente: 'Pendiente',
        confirmada: 'Confirmada',
        en_curso: 'En curso',
        completada: 'Completada',
        cancelada: 'Cancelada',
        no_show: 'No show',
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/appointments', {
            onSuccess: () => {
                setShowCreateModal(false);
                reset();
            },
        });
    };

    const statusBadge = (status: string) => {
        const map: Record<string, string> = {
            confirmada: 'bg-blue-100 text-blue-700',
            completada: 'bg-green-100 text-green-700',
            cancelada: 'bg-red-100 text-red-700',
            pendiente: 'bg-amber-100 text-amber-700',
        };
        return (
            <span
                className={`inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${map[status] || 'bg-slate-100 text-slate-700'}`}
            >
                {status}
            </span>
        );
    };

    const statusColor = (status: string) => {
        switch (status) {
            case 'completada':
                return 'bg-emerald-500';
            case 'cancelada':
                return 'bg-rose-500';
            case 'confirmada':
                return 'bg-blue-500';
            case 'en_curso':
                return 'bg-violet-500';
            case 'no_show':
                return 'bg-gray-400';
            default:
                return 'bg-amber-500';
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Citas', href: '/appointments' },
                { title: 'Calendario', href: '/appointments/calendar' },
            ]}
        >
            <Head title="Calendario | Citas y Reservas" />

            <div
                className="mx-auto flex min-h-0 w-full flex-1 flex-col px-2 pt-3 pb-0 sm:px-4 sm:pt-4 lg:px-6"
                style={{ maxWidth: 'min(1600px, 100%)' }}
            >
                {/* ── Header ── */}
                <div className="mb-3 flex shrink-0 flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                            Calendario
                        </h1>
                        <p className="mt-0.5 text-xs text-slate-500 sm:text-sm">
                            {format(new Date(), "EEEE d 'de' MMMM, yyyy", {
                                locale: es,
                            })}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {googleConnected ? (
                            <button
                                onClick={() => setShowGoogleConfig(true)}
                                className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-100"
                            >
                                <CheckCircle2 className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">
                                    Google Sync
                                </span>
                            </button>
                        ) : (
                            <button
                                onClick={() => setShowGoogleConfig(true)}
                                className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50"
                            >
                                <RefreshCw className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">
                                    Conectar Google
                                </span>
                            </button>
                        )}
                        <button
                            onClick={() => openCreateModal()}
                            className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-primary/90"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">Nueva Cita</span>
                            <span className="sm:hidden">Cita</span>
                        </button>
                    </div>
                </div>

                {/* ── Monthly Stats ── */}
                <div className="mb-2 grid shrink-0 grid-cols-5 gap-1.5 sm:mb-3 sm:gap-2">
                    {[
                        {
                            label: 'total',
                            value: monthlyStats.total,
                            color: 'text-slate-900',
                            bg: 'bg-slate-100',
                        },
                        {
                            label: 'completadas',
                            value: monthlyStats.completadas,
                            color: 'text-emerald-700',
                            bg: 'bg-emerald-100',
                        },
                        {
                            label: 'pendientes',
                            value: monthlyStats.pendientes,
                            color: 'text-amber-700',
                            bg: 'bg-amber-100',
                        },
                        {
                            label: 'canceladas',
                            value: monthlyStats.canceladas,
                            color: 'text-rose-700',
                            bg: 'bg-rose-100',
                        },
                        {
                            label: 'ingresos',
                            value: `$${monthlyStats.ingresos.toLocaleString()}`,
                            color: 'text-emerald-700',
                            bg: 'bg-emerald-100',
                        },
                    ].map((s) => (
                        <div
                            key={s.label}
                            className={`${s.bg} min-w-0 rounded-lg p-2 sm:rounded-xl sm:p-3`}
                        >
                            <p className="mb-0.5 truncate text-[9px] font-semibold tracking-wider text-slate-500 uppercase sm:text-[10px]">
                                {s.label === 'ingresos'
                                    ? 'Ingresos'
                                    : s.label.charAt(0).toUpperCase() +
                                      s.label.slice(1)}
                            </p>
                            <p
                                className={`text-sm font-bold sm:text-lg ${s.color} truncate`}
                            >
                                {s.value}
                            </p>
                        </div>
                    ))}
                </div>

                {/* ── Charts Row ── */}
                <div className="mb-2 grid shrink-0 grid-cols-2 gap-2 sm:mb-3 sm:gap-3 lg:grid-cols-4">
                    {/* Daily Trend */}
                    <div className="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                        <p className="mb-2 text-[9px] font-semibold tracking-wider text-slate-400 uppercase sm:text-[10px]">
                            Citas por día
                        </p>
                        <ResponsiveContainer width="100%" height={90}>
                            <BarChart data={appointmentsByDayMonth}>
                                <XAxis
                                    dataKey="date"
                                    tick={{ fontSize: 8 }}
                                    tickLine={false}
                                    axisLine={false}
                                />
                                <YAxis hide />
                                <Tooltip
                                    contentStyle={{
                                        fontSize: 10,
                                        borderRadius: 8,
                                        border: '1px solid #e2e8f0',
                                    }}
                                    formatter={
                                        ((value: any) => [
                                            value,
                                            'Citas',
                                        ]) as any
                                    }
                                    labelFormatter={
                                        ((label: any) => `Día ${label}`) as any
                                    }
                                />
                                <Bar
                                    dataKey="total"
                                    fill="#3b82f6"
                                    radius={[2, 2, 0, 0]}
                                />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Status Distribution */}
                    <div className="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                        <p className="mb-2 text-[9px] font-semibold tracking-wider text-slate-400 uppercase sm:text-[10px]">
                            Estado
                        </p>
                        <div className="flex items-center gap-2">
                            <ResponsiveContainer width={80} height={80}>
                                <PieChart>
                                    <Pie
                                        data={statusDistributionMonth}
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={22}
                                        outerRadius={36}
                                        dataKey="value"
                                        strokeWidth={0}
                                    >
                                        {statusDistributionMonth.map(
                                            (_entry, i) => (
                                                <Cell
                                                    key={i}
                                                    fill={
                                                        statusDistributionMonth[
                                                            i
                                                        ]?.color
                                                    }
                                                />
                                            ),
                                        )}
                                    </Pie>
                                </PieChart>
                            </ResponsiveContainer>
                            <div className="min-w-0 flex-1 space-y-1">
                                {statusDistributionMonth.map((s) => (
                                    <div
                                        key={s.name}
                                        className="flex items-center justify-between text-[9px] sm:text-[10px]"
                                    >
                                        <span className="flex items-center gap-1 truncate font-medium text-slate-600">
                                            <span
                                                className="h-1.5 w-1.5 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor: s.color,
                                                }}
                                            />
                                            {s.name}
                                        </span>
                                        <span className="ml-1 font-bold text-slate-900">
                                            {s.value}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Top Services */}
                    <div className="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                        <p className="mb-2 text-[9px] font-semibold tracking-wider text-slate-400 uppercase sm:text-[10px]">
                            Servicios top
                        </p>
                        <div className="space-y-1.5">
                            {monthServices.length === 0 ? (
                                <p className="text-[10px] text-slate-400">
                                    Sin datos
                                </p>
                            ) : (
                                monthServices.slice(0, 4).map((s, i) => {
                                    const maxVal = monthServices[0]?.value || 1;
                                    const colors = [
                                        '#3b82f6',
                                        '#22c55e',
                                        '#f59e0b',
                                        '#8b5cf6',
                                    ];
                                    return (
                                        <div key={s.name}>
                                            <div className="mb-0.5 flex justify-between text-[9px] sm:text-[10px]">
                                                <span className="mr-1 truncate font-medium text-slate-700">
                                                    {s.name}
                                                </span>
                                                <span className="shrink-0 font-bold text-slate-900">
                                                    {s.value}
                                                </span>
                                            </div>
                                            <div className="h-1 overflow-hidden rounded-full bg-slate-100">
                                                <div
                                                    className="h-full rounded-full"
                                                    style={{
                                                        width: `${(s.value / maxVal) * 100}%`,
                                                        backgroundColor:
                                                            colors[i],
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    {/* Provider Workload */}
                    <div className="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                        <p className="mb-2 text-[9px] font-semibold tracking-wider text-slate-400 uppercase sm:text-[10px]">
                            Carga empleados
                        </p>
                        <div className="space-y-2">
                            {providerWorkload.length === 0 ? (
                                <p className="text-[10px] text-slate-400">
                                    Sin datos
                                </p>
                            ) : (
                                providerWorkload.slice(0, 4).map((p) => (
                                    <div
                                        key={p.name}
                                        className="flex items-center gap-2"
                                    >
                                        {p.photo ? (
                                            <img
                                                src={p.photo}
                                                alt={p.name}
                                                className="h-5 w-5 shrink-0 rounded-full object-cover sm:h-6 sm:w-6"
                                            />
                                        ) : (
                                            <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-[8px] font-bold text-white sm:h-6 sm:w-6 sm:text-[10px]">
                                                {p.name.charAt(0)}
                                            </div>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-[10px] font-semibold text-slate-700 sm:text-[11px]">
                                                {p.name}
                                            </p>
                                            <div className="mt-0.5 h-1 max-w-[80px] overflow-hidden rounded-full bg-slate-100 sm:max-w-none">
                                                <div
                                                    className="h-full rounded-full bg-blue-500"
                                                    style={{
                                                        width: `${Math.min((p.total / (providerWorkload[0]?.total || 1)) * 100, 100)}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                        <span className="shrink-0 text-[10px] font-bold text-slate-900 sm:text-[11px]">
                                            {p.total}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                {/* ── Main Grid: Sidebar + Calendar + Day Detail ── */}
                <div className="grid min-h-0 flex-1 grid-cols-1 gap-3 pb-3 sm:gap-4 sm:pb-4 lg:grid-cols-12">
                    {/* ── Left Sidebar ── */}
                    <div className="flex min-h-0 flex-col gap-3 lg:col-span-3">
                        {/* Today's Agenda (fills available height) */}
                        <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white">
                            <div className="flex shrink-0 items-center justify-between bg-slate-900 px-3 py-2.5 text-white sm:px-4 sm:py-3">
                                <h2 className="flex items-center gap-1.5 text-[11px] font-bold sm:text-xs">
                                    <Clock className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                    Hoy
                                </h2>
                                <span className="rounded-md bg-white/15 px-1.5 py-0.5 text-[9px] font-semibold text-white sm:text-[10px]">
                                    {todayAppointments.length}
                                </span>
                            </div>
                            <div className="flex-1 divide-y divide-slate-100 overflow-y-auto">
                                {todayAppointments.length === 0 ? (
                                    <div className="p-4 text-center sm:p-5">
                                        <CalendarIcon className="mx-auto mb-1.5 h-5 w-5 text-slate-300 sm:h-6 sm:w-6" />
                                        <p className="text-xs font-medium text-slate-400">
                                            Sin citas hoy
                                        </p>
                                    </div>
                                ) : (
                                    todayAppointments
                                        .sort(
                                            (a, b) =>
                                                new Date(
                                                    a.start_time,
                                                ).getTime() -
                                                new Date(
                                                    b.start_time,
                                                ).getTime(),
                                        )
                                        .map((app) => (
                                            <div
                                                key={app.id}
                                                className="cursor-pointer px-3 py-2.5 transition-colors hover:bg-slate-50 active:bg-slate-100 sm:px-4"
                                                onClick={() =>
                                                    router.visit(
                                                        `/appointments/${app.id}`,
                                                    )
                                                }
                                            >
                                                <div className="mb-0.5 flex items-center justify-between">
                                                    <span className="text-[11px] font-bold text-slate-900 sm:text-xs">
                                                        {format(
                                                            new Date(
                                                                app.start_time,
                                                            ),
                                                            'HH:mm',
                                                        )}
                                                    </span>
                                                    {statusBadge(app.status)}
                                                </div>
                                                <p className="truncate text-[10px] font-semibold text-slate-600 sm:text-[11px]">
                                                    {app.producto?.nombre}
                                                </p>
                                                <p className="mt-0.5 flex items-center gap-1 text-[9px] text-slate-400 sm:text-[10px]">
                                                    <User className="h-2.5 w-2.5 sm:h-3 sm:w-3" />
                                                    {app.client?.name}
                                                </p>
                                            </div>
                                        ))
                                )}
                            </div>
                        </div>

                        {/* Google Calendar (fixed height) */}
                        {googleConnected ? (
                            <div className="shrink-0 overflow-hidden rounded-xl border border-emerald-200 bg-white">
                                <div className="border-b border-emerald-100 bg-emerald-50 px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div className="flex items-center gap-1.5 text-[11px] sm:text-xs">
                                        <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-600 sm:h-4 sm:w-4" />
                                        <span className="font-semibold text-emerald-800">
                                            Google Calendar activo
                                        </span>
                                    </div>
                                </div>
                                <div className="space-y-2 p-3 sm:p-4">
                                    {googleConnectedEmail && (
                                        <p className="flex items-center gap-1.5 truncate text-[10px] text-slate-600 sm:text-xs">
                                            <Mail className="h-3 w-3 shrink-0 text-slate-400 sm:h-3.5 sm:w-3.5" />
                                            {googleConnectedEmail}
                                        </p>
                                    )}
                                    <p className="text-[9px] text-slate-400 sm:text-[10px]">
                                        Sincronización automática activa.
                                    </p>
                                    <div className="flex gap-1.5">
                                        <button
                                            onClick={handleSync}
                                            disabled={syncing}
                                            className="inline-flex h-7 flex-1 items-center justify-center gap-1 rounded-lg bg-emerald-600 text-[10px] font-semibold text-white transition-all hover:bg-emerald-700 disabled:bg-emerald-300 sm:h-8 sm:text-xs"
                                        >
                                            {syncing ? (
                                                <Loader2 className="h-3 w-3 animate-spin" />
                                            ) : (
                                                <RefreshCw className="h-3 w-3" />
                                            )}
                                            {syncing
                                                ? 'Sincronizando...'
                                                : 'Sincronizar'}
                                        </button>
                                        <button
                                            onClick={() =>
                                                setShowGoogleConfig(true)
                                            }
                                            className="px-2 text-[10px] font-semibold text-blue-600 transition-colors hover:text-blue-700 sm:text-[11px]"
                                        >
                                            Configurar
                                        </button>
                                    </div>
                                    {syncError && (
                                        <div className="flex items-start gap-1.5 rounded-lg bg-red-50 p-2 text-[9px] text-red-500 sm:text-[10px]">
                                            <AlertCircle className="mt-0.5 h-2.5 w-2.5 shrink-0 sm:h-3 sm:w-3" />
                                            <span>{syncError}</span>
                                        </div>
                                    )}
                                    {syncedEvents.length > 0 && (
                                        <div className="flex items-center gap-1.5 rounded-lg bg-emerald-50 p-2 text-[9px] text-emerald-600 sm:text-[10px]">
                                            <CheckCircle2 className="h-2.5 w-2.5 shrink-0 sm:h-3 sm:w-3" />
                                            <span>
                                                {syncedEvents.length} eventos
                                                sincronizados
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : googleAuthUrl ? (
                            <div className="shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <div className="border-b border-slate-100 bg-slate-50 px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div className="flex items-center gap-1.5 text-[11px] sm:text-xs">
                                        <RefreshCw className="h-3.5 w-3.5 text-slate-500 sm:h-4 sm:w-4" />
                                        <span className="font-semibold text-slate-700">
                                            Google Calendar
                                        </span>
                                    </div>
                                </div>
                                <div className="space-y-2.5 p-3 sm:p-4">
                                    <p className="text-[10px] leading-relaxed text-slate-400 sm:text-xs">
                                        Conecta tu Google Calendar para
                                        sincronizar las citas.
                                    </p>
                                    <a
                                        href={googleAuthUrl}
                                        className="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-lg bg-blue-600 text-[10px] font-semibold text-white transition-all hover:bg-blue-700 sm:h-9 sm:text-xs"
                                    >
                                        <ExternalLink className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                        Conectar con Google
                                    </a>
                                </div>
                            </div>
                        ) : null}
                    </div>

                    {/* ── Calendar + Day Detail ── */}
                    <div className="h-full min-h-0 lg:col-span-9">
                        <div className="flex h-full flex-col overflow-hidden rounded-xl border border-slate-200 bg-white">
                            {/* Calendar Header */}
                            <div className="flex shrink-0 items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/80 px-3 py-2.5 sm:px-5 sm:py-3">
                                <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                                    <h2 className="text-sm font-bold whitespace-nowrap text-slate-900 capitalize sm:text-base">
                                        {viewMode === 'day'
                                            ? format(
                                                  currentDate,
                                                  "EEEE d 'de' MMMM",
                                                  { locale: es },
                                              )
                                            : viewMode === 'week'
                                              ? `${format(weekDays[0], 'd MMM', { locale: es })} - ${format(weekDays[6], 'd MMM, yyyy', { locale: es })}`
                                              : format(
                                                    currentMonth,
                                                    'MMMM yyyy',
                                                    { locale: es },
                                                )}
                                    </h2>
                                    <div className="flex shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                                        <button
                                            type="button"
                                            onClick={goPrev}
                                            className="border-r border-slate-200 p-1.5 transition-colors hover:bg-slate-50 sm:p-2"
                                        >
                                            <ChevronLeft className="h-3.5 w-3.5 text-slate-600 sm:h-4 sm:w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={goToday}
                                            className="px-2.5 py-1.5 text-[10px] font-semibold text-slate-700 transition-colors hover:bg-slate-50 sm:px-3 sm:py-2 sm:text-xs"
                                        >
                                            Hoy
                                        </button>
                                        <button
                                            type="button"
                                            onClick={goNext}
                                            className="border-l border-slate-200 p-1.5 transition-colors hover:bg-slate-50 sm:p-2"
                                        >
                                            <ChevronRight className="h-3.5 w-3.5 text-slate-600 sm:h-4 sm:w-4" />
                                        </button>
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    {/* View Mode Toggle */}
                                    <div className="flex overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                                        {(
                                            ['day', 'week', 'month'] as const
                                        ).map((mode) => (
                                            <button
                                                key={mode}
                                                type="button"
                                                onClick={() =>
                                                    setViewMode(mode)
                                                }
                                                className={`flex items-center gap-1 px-2 py-1.5 text-[10px] font-semibold transition-colors sm:px-3 sm:py-2 sm:text-xs ${
                                                    viewMode === mode
                                                        ? 'bg-primary text-white'
                                                        : 'text-slate-600 hover:bg-slate-50'
                                                } `}
                                            >
                                                {mode === 'day' ? (
                                                    <List className="h-3 w-3" />
                                                ) : mode === 'week' ? (
                                                    <LayoutGrid className="h-3 w-3" />
                                                ) : (
                                                    <CalendarDays className="h-3 w-3" />
                                                )}
                                                <span className="hidden sm:inline">
                                                    {mode === 'day'
                                                        ? 'Día'
                                                        : mode === 'week'
                                                          ? 'Semana'
                                                          : 'Mes'}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
                                {viewMode === 'month' ? (
                                    /* ── Month View ── */
                                    <div className="flex min-h-0 flex-1 flex-col p-2 sm:p-3 lg:p-4">
                                        <div className="mb-1 grid shrink-0 grid-cols-7 sm:mb-2">
                                            {[
                                                'Dom',
                                                'Lun',
                                                'Mar',
                                                'Mié',
                                                'Jue',
                                                'Vie',
                                                'Sáb',
                                            ].map((d) => (
                                                <div
                                                    key={d}
                                                    className="py-1 text-center text-[9px] font-semibold tracking-wider text-slate-400 uppercase sm:text-[10px] lg:text-xs"
                                                >
                                                    {d}
                                                </div>
                                            ))}
                                        </div>
                                        <div className="grid flex-1 auto-rows-fr grid-cols-7 gap-px overflow-hidden rounded-lg bg-slate-100">
                                            {daysInMonth.map((day, i) => {
                                                if (day === null)
                                                    return (
                                                        <div
                                                            key={`empty-${i}`}
                                                            className="bg-white"
                                                        />
                                                    );
                                                const date = new Date(
                                                    currentMonth.getFullYear(),
                                                    currentMonth.getMonth(),
                                                    day,
                                                );
                                                const key = format(
                                                    date,
                                                    'yyyy-MM-dd',
                                                );
                                                const dayCitas =
                                                    diasConCitas.get(key);
                                                const isToday = isSameDay(
                                                    date,
                                                    new Date(),
                                                );
                                                const isSelected =
                                                    selectedDay &&
                                                    isSameDay(
                                                        date,
                                                        selectedDay,
                                                    );
                                                return (
                                                    <button
                                                        key={key}
                                                        type="button"
                                                        onClick={() =>
                                                            setSelectedDay(date)
                                                        }
                                                        onDoubleClick={() =>
                                                            openCreateModal(key)
                                                        }
                                                        className={`relative bg-white p-1 text-left transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-inset sm:p-1.5 ${isSelected ? 'bg-primary/[0.03] ring-2 ring-primary ring-inset' : ''} `}
                                                    >
                                                        <span
                                                            className={`inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold sm:h-6 sm:w-6 sm:text-xs ${isToday ? 'bg-primary text-white shadow-sm' : 'text-slate-700'} `}
                                                        >
                                                            {day}
                                                        </span>
                                                        {dayCitas &&
                                                            dayCitas.length >
                                                                0 && (
                                                                <div className="mt-0.5 flex flex-wrap gap-0.5 sm:mt-1">
                                                                    {dayCitas
                                                                        .slice(
                                                                            0,
                                                                            5,
                                                                        )
                                                                        .map(
                                                                            (
                                                                                c: any,
                                                                            ) => (
                                                                                <span
                                                                                    key={
                                                                                        c.id
                                                                                    }
                                                                                    className={`inline-block h-1.5 w-1.5 rounded-full sm:h-2 sm:w-2 ${statusColor(c.status)}`}
                                                                                />
                                                                            ),
                                                                        )}
                                                                    {dayCitas.length >
                                                                        5 && (
                                                                        <span className="text-[8px] leading-none font-bold text-slate-400 sm:text-[9px]">
                                                                            +
                                                                            {dayCitas.length -
                                                                                5}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            )}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ) : viewMode === 'week' ? (
                                    /* ── Week View ── */
                                    <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                                        <div className="grid shrink-0 grid-cols-7 border-b border-slate-200 bg-slate-50/50">
                                            {weekDays.map((day, i) => {
                                                const isToday = isSameDay(
                                                    day,
                                                    new Date(),
                                                );
                                                const isSelected =
                                                    selectedDay &&
                                                    isSameDay(day, selectedDay);
                                                return (
                                                    <button
                                                        key={i}
                                                        type="button"
                                                        onClick={() => {
                                                            setSelectedDay(day);
                                                            setCurrentMonth(
                                                                startOfMonth(
                                                                    day,
                                                                ),
                                                            );
                                                        }}
                                                        className={`border-r border-slate-200 px-2 py-2 text-center transition-colors last:border-r-0 sm:py-2.5 ${isSelected ? 'bg-primary/5' : 'hover:bg-slate-100'} `}
                                                    >
                                                        <p className="text-[9px] font-semibold text-slate-500 uppercase sm:text-[10px]">
                                                            {format(
                                                                day,
                                                                'EEE',
                                                                { locale: es },
                                                            )}
                                                        </p>
                                                        <p
                                                            className={`mt-0.5 text-sm font-bold sm:text-base ${isToday ? 'mx-auto flex h-7 w-7 items-center justify-center rounded-full bg-primary text-white sm:h-8 sm:w-8' : 'text-slate-800'}`}
                                                        >
                                                            {format(day, 'd')}
                                                        </p>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        <div className="flex-1 overflow-y-auto p-2 sm:p-3">
                                            <div className="relative">
                                                {/* Time gutter */}
                                                <div className="absolute top-0 bottom-0 left-0 w-12 border-r border-slate-200 sm:w-14" />
                                                {hours.map((h) => (
                                                    <div
                                                        key={h}
                                                        className="flex h-16 border-b border-slate-100 last:border-b-0 sm:h-20"
                                                    >
                                                        <div className="w-12 shrink-0 pt-0.5 pr-2 text-right text-[10px] font-medium text-slate-400 sm:w-14 sm:pr-3 sm:text-xs">
                                                            {String(h).padStart(
                                                                2,
                                                                '0',
                                                            )}
                                                            :00
                                                        </div>
                                                        <div className="relative grid flex-1 grid-cols-7">
                                                            {weekDays.map(
                                                                (day, di) => {
                                                                    const key =
                                                                        format(
                                                                            day,
                                                                            'yyyy-MM-dd',
                                                                        );
                                                                    const dayCitas =
                                                                        (
                                                                            diasConCitas.get(
                                                                                key,
                                                                            ) ||
                                                                            []
                                                                        ).filter(
                                                                            (
                                                                                c: any,
                                                                            ) => {
                                                                                const startH =
                                                                                    new Date(
                                                                                        c.start_time ||
                                                                                            c
                                                                                                .start
                                                                                                ?.dateTime ||
                                                                                            c.start,
                                                                                    ).getHours();
                                                                                return (
                                                                                    startH ===
                                                                                    h
                                                                                );
                                                                            },
                                                                        );
                                                                    return (
                                                                        <div
                                                                            key={
                                                                                di
                                                                            }
                                                                            className="relative min-h-0 border-r border-slate-100 last:border-r-0"
                                                                        >
                                                                            {dayCitas.map(
                                                                                (
                                                                                    c: any,
                                                                                ) => {
                                                                                    const start =
                                                                                        new Date(
                                                                                            c.start_time ||
                                                                                                c
                                                                                                    .start
                                                                                                    ?.dateTime ||
                                                                                                c.start,
                                                                                        );
                                                                                    const end =
                                                                                        new Date(
                                                                                            c.end_time ||
                                                                                                c
                                                                                                    .end
                                                                                                    ?.dateTime ||
                                                                                                c.end,
                                                                                        );
                                                                                    const mins =
                                                                                        differenceInMinutes(
                                                                                            end,
                                                                                            start,
                                                                                        );
                                                                                    const topOffset =
                                                                                        start.getMinutes();
                                                                                    return (
                                                                                        <div
                                                                                            key={
                                                                                                c.id
                                                                                            }
                                                                                            onClick={() =>
                                                                                                router.visit(
                                                                                                    `/appointments/${c.id}`,
                                                                                                )
                                                                                            }
                                                                                            className="absolute right-0.5 left-0.5 z-10 cursor-pointer overflow-hidden rounded-md border-l-2 px-1.5 py-1 text-[10px] leading-tight shadow-sm transition-opacity hover:opacity-90"
                                                                                            style={{
                                                                                                top: `${(topOffset / 60) * 100}%`,
                                                                                                height: `${(mins / 60) * 100}%`,
                                                                                                minHeight:
                                                                                                    '18px',
                                                                                                backgroundColor:
                                                                                                    c.status ===
                                                                                                    'completada'
                                                                                                        ? '#d1fae5'
                                                                                                        : c.status ===
                                                                                                            'cancelada'
                                                                                                          ? '#fee2e2'
                                                                                                          : c.status ===
                                                                                                              'confirmada'
                                                                                                            ? '#dbeafe'
                                                                                                            : '#fef3c7',
                                                                                                borderLeftColor:
                                                                                                    c.status ===
                                                                                                    'completada'
                                                                                                        ? '#10b981'
                                                                                                        : c.status ===
                                                                                                            'cancelada'
                                                                                                          ? '#ef4444'
                                                                                                          : c.status ===
                                                                                                              'confirmada'
                                                                                                            ? '#3b82f6'
                                                                                                            : '#f59e0b',
                                                                                            }}
                                                                                        >
                                                                                            <p className="truncate text-[9px] font-bold text-slate-800">
                                                                                                {c
                                                                                                    .client
                                                                                                    ?.name ||
                                                                                                    'Cliente'}
                                                                                            </p>
                                                                                            <p className="truncate text-[8px] text-slate-500">
                                                                                                {c
                                                                                                    .producto
                                                                                                    ?.nombre ||
                                                                                                    c.title ||
                                                                                                    'Cita'}
                                                                                            </p>
                                                                                        </div>
                                                                                    );
                                                                                },
                                                                            )}
                                                                        </div>
                                                                    );
                                                                },
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    /* ── Day View ── */
                                    <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                                        <div className="shrink-0 border-b border-slate-200 bg-slate-50/50 px-3 py-3 sm:px-5 sm:py-4">
                                            <p className="text-sm font-bold text-slate-900 capitalize sm:text-base">
                                                {selectedDay
                                                    ? format(
                                                          selectedDay,
                                                          "EEEE d 'de' MMMM",
                                                          { locale: es },
                                                      )
                                                    : 'Selecciona un día'}
                                            </p>
                                            <p className="text-[11px] text-slate-500">
                                                {citasDelDia.length} cita
                                                {citasDelDia.length !== 1
                                                    ? 's'
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="flex-1 overflow-y-auto p-2 sm:p-3">
                                            <div className="relative">
                                                {hours.map((h) => {
                                                    const hourCitas =
                                                        citasDelDia.filter(
                                                            (c: any) => {
                                                                const startH =
                                                                    new Date(
                                                                        c.start_time ||
                                                                            c
                                                                                .start
                                                                                ?.dateTime ||
                                                                            c.start,
                                                                    ).getHours();
                                                                return (
                                                                    startH === h
                                                                );
                                                            },
                                                        );
                                                    const hasCitas =
                                                        hourCitas.length > 0;
                                                    return (
                                                        <div
                                                            key={h}
                                                            className={`flex border-b border-slate-100 last:border-b-0 ${hasCitas ? 'bg-blue-50/30' : ''}`}
                                                        >
                                                            <div className="h-16 w-14 shrink-0 border-r border-slate-200 pt-0.5 pr-2 text-right text-[10px] font-medium text-slate-400 sm:h-20 sm:w-16 sm:pr-3 sm:text-xs">
                                                                {String(
                                                                    h,
                                                                ).padStart(
                                                                    2,
                                                                    '0',
                                                                )}
                                                                :00
                                                            </div>
                                                            <div className="relative min-h-[64px] flex-1 space-y-1 p-1 sm:min-h-[80px]">
                                                                {hourCitas.map(
                                                                    (
                                                                        c: any,
                                                                    ) => {
                                                                        const start =
                                                                            new Date(
                                                                                c.start_time ||
                                                                                    c
                                                                                        .start
                                                                                        ?.dateTime ||
                                                                                    c.start,
                                                                            );
                                                                        const end =
                                                                            new Date(
                                                                                c.end_time ||
                                                                                    c
                                                                                        .end
                                                                                        ?.dateTime ||
                                                                                    c.end,
                                                                            );
                                                                        return (
                                                                            <div
                                                                                key={
                                                                                    c.id
                                                                                }
                                                                                onClick={() =>
                                                                                    router.visit(
                                                                                        `/appointments/${c.id}`,
                                                                                    )
                                                                                }
                                                                                className="cursor-pointer rounded-lg border-l-4 px-3 py-2 shadow-sm transition-opacity hover:opacity-90"
                                                                                style={{
                                                                                    backgroundColor:
                                                                                        c.status ===
                                                                                        'completada'
                                                                                            ? '#d1fae5'
                                                                                            : c.status ===
                                                                                                'cancelada'
                                                                                              ? '#fee2e2'
                                                                                              : c.status ===
                                                                                                  'confirmada'
                                                                                                ? '#dbeafe'
                                                                                                : '#fef3c7',
                                                                                    borderLeftColor:
                                                                                        c.status ===
                                                                                        'completada'
                                                                                            ? '#10b981'
                                                                                            : c.status ===
                                                                                                'cancelada'
                                                                                              ? '#ef4444'
                                                                                              : c.status ===
                                                                                                  'confirmada'
                                                                                                ? '#3b82f6'
                                                                                                : '#f59e0b',
                                                                                }}
                                                                            >
                                                                                <div className="flex items-center justify-between">
                                                                                    <span className="text-xs font-bold text-slate-800">
                                                                                        {c
                                                                                            .client
                                                                                            ?.name ||
                                                                                            'Cliente'}
                                                                                    </span>
                                                                                    <span
                                                                                        className={`inline-block rounded px-1.5 py-0.5 text-[9px] font-semibold ${statusStyles[c.status] || 'bg-slate-100 text-slate-600'}`}
                                                                                    >
                                                                                        {statusLabels[
                                                                                            c
                                                                                                .status
                                                                                        ] ||
                                                                                            c.status}
                                                                                    </span>
                                                                                </div>
                                                                                <p className="mt-0.5 text-[11px] text-slate-600">
                                                                                    {c
                                                                                        .producto
                                                                                        ?.nombre ||
                                                                                        c.title ||
                                                                                        'Cita'}
                                                                                </p>
                                                                                <p className="mt-0.5 text-[10px] text-slate-400">
                                                                                    {format(
                                                                                        start,
                                                                                        'HH:mm',
                                                                                    )}{' '}
                                                                                    -{' '}
                                                                                    {format(
                                                                                        end,
                                                                                        'HH:mm',
                                                                                    )}
                                                                                </p>
                                                                            </div>
                                                                        );
                                                                    },
                                                                )}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* ── Day Detail Panel ── */}
                                <div className="flex h-full max-h-[280px] shrink-0 flex-col border-t border-slate-200 bg-slate-50/50 lg:max-h-none lg:w-72 lg:border-t-0 lg:border-l xl:w-80">
                                    {selectedDay ? (
                                        <>
                                            <div className="flex-shrink-0 border-b border-slate-200 px-3 py-2.5 sm:px-4 sm:py-3">
                                                <h3 className="flex items-center justify-between text-sm font-bold text-slate-900 capitalize sm:text-base">
                                                    {format(
                                                        selectedDay,
                                                        'EEEE d',
                                                        { locale: es },
                                                    )}
                                                    <span className="rounded-md bg-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-500 sm:text-xs">
                                                        {citasDelDia.length}{' '}
                                                        cita
                                                        {citasDelDia.length !==
                                                        1
                                                            ? 's'
                                                            : ''}
                                                    </span>
                                                </h3>
                                                <p className="text-[11px] font-medium text-slate-500 capitalize sm:text-xs">
                                                    {format(
                                                        selectedDay,
                                                        'MMMM yyyy',
                                                        { locale: es },
                                                    )}
                                                </p>
                                            </div>
                                            <div className="flex-1 space-y-2 overflow-y-auto p-2 sm:p-3">
                                                {citasDelDia.length === 0 ? (
                                                    <div className="flex h-full flex-col items-center justify-center py-8 text-slate-400">
                                                        <CalendarIcon className="mb-2 h-8 w-8 opacity-40 sm:h-10 sm:w-10" />
                                                        <p className="text-xs font-bold sm:text-sm">
                                                            Día libre
                                                        </p>
                                                        <p className="text-[10px] opacity-70 sm:text-xs">
                                                            Sin citas
                                                            programadas
                                                        </p>
                                                        <button
                                                            onClick={() =>
                                                                openCreateModal(
                                                                    format(
                                                                        selectedDay,
                                                                        'yyyy-MM-dd',
                                                                    ),
                                                                )
                                                            }
                                                            className="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-semibold text-primary shadow-sm transition-colors hover:border-primary/40 sm:text-xs"
                                                        >
                                                            Agendar cita
                                                        </button>
                                                    </div>
                                                ) : (
                                                    citasDelDia
                                                        .sort(
                                                            (a, b) =>
                                                                new Date(
                                                                    a.start_time,
                                                                ).getTime() -
                                                                new Date(
                                                                    b.start_time,
                                                                ).getTime(),
                                                        )
                                                        .map((c: any) => (
                                                            <div
                                                                key={c.id}
                                                                onClick={() =>
                                                                    router.visit(
                                                                        `/appointments/${c.id}`,
                                                                    )
                                                                }
                                                                className="group cursor-pointer rounded-lg border border-slate-200 bg-white p-2.5 transition-all hover:border-primary/30 hover:shadow-sm active:scale-[0.99] sm:p-3"
                                                            >
                                                                <div className="mb-1.5 flex items-center justify-between">
                                                                    <span className="text-[11px] font-bold text-slate-900 transition-colors group-hover:text-primary sm:text-xs">
                                                                        {format(
                                                                            new Date(
                                                                                c.start_time ||
                                                                                    c
                                                                                        .start
                                                                                        ?.dateTime ||
                                                                                    c.start,
                                                                            ),
                                                                            'HH:mm',
                                                                        )}
                                                                    </span>
                                                                    <span
                                                                        className={`inline-block rounded px-1.5 py-0.5 text-[9px] font-semibold tracking-wider uppercase sm:text-[10px] ${statusStyles[c.status] ?? 'bg-slate-100 text-slate-600'}`}
                                                                    >
                                                                        {statusLabels[
                                                                            c
                                                                                .status
                                                                        ] ??
                                                                            c.status}
                                                                    </span>
                                                                </div>
                                                                <p className="mb-0.5 text-[11px] leading-tight font-bold text-slate-800 sm:text-xs">
                                                                    {c.client
                                                                        ?.name ??
                                                                        'Cliente Google'}
                                                                </p>
                                                                <p className="truncate text-[10px] font-medium text-slate-500 sm:text-[11px]">
                                                                    {c.producto
                                                                        ?.nombre ??
                                                                        c.title ??
                                                                        'Cita'}
                                                                </p>
                                                            </div>
                                                        ))
                                                )}
                                            </div>
                                        </>
                                    ) : (
                                        <div className="flex flex-1 flex-col items-center justify-center p-4 text-center text-slate-400 sm:p-6">
                                            <CalendarIcon className="mb-2 h-8 w-8 opacity-30 sm:mb-3 sm:h-10 sm:w-10" />
                                            <p className="text-xs font-bold sm:text-sm">
                                                Selecciona un día
                                            </p>
                                            <p className="text-[10px] opacity-70 sm:text-xs">
                                                Para ver las citas
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Google Calendar Config Modal ── */}
            {showGoogleConfig && (
                <div className="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/40 p-3 backdrop-blur-sm duration-200 fade-in sm:p-4">
                    <div className="max-h-[90vh] w-full max-w-md space-y-4 overflow-y-auto rounded-2xl bg-white p-5 shadow-xl sm:p-6">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-100 sm:h-10 sm:w-10">
                                    <RefreshCw className="h-4 w-4 text-blue-600 sm:h-5 sm:w-5" />
                                </div>
                                <div>
                                    <h3 className="text-sm font-bold text-slate-900 sm:text-base">
                                        Google Calendar
                                    </h3>
                                    <p className="text-[10px] text-slate-500 sm:text-xs">
                                        Sincroniza tus citas
                                    </p>
                                </div>
                            </div>
                            <button
                                onClick={() => setShowGoogleConfig(false)}
                                className="rounded-lg p-1 transition-colors hover:bg-slate-100"
                            >
                                <X className="h-4 w-4 text-slate-400" />
                            </button>
                        </div>

                        <form
                            onSubmit={handleSaveGoogleConfig}
                            className="space-y-3 sm:space-y-4"
                        >
                            <div className="space-y-1.5">
                                <label className="text-[11px] font-semibold text-slate-700 sm:text-xs">
                                    ID del Calendario (opcional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="ej: tu.calendario@gmail.com"
                                    value={googleData.google_calendar_id}
                                    onChange={(e) =>
                                        setGoogleData(
                                            'google_calendar_id',
                                            e.target.value,
                                        )
                                    }
                                    className="flex h-9 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs transition-all outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 sm:h-10 sm:text-sm"
                                />
                                <p className="text-[9px] text-slate-400 sm:text-[10px]">
                                    Déjalo vacío para usar el calendario
                                    predeterminado.
                                </p>
                            </div>

                            <div className="flex gap-2 pt-1">
                                <button
                                    type="button"
                                    onClick={() => setShowGoogleConfig(false)}
                                    className="h-9 flex-1 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 sm:h-10 sm:text-sm"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={googleProcessing}
                                    className="flex h-9 flex-1 items-center justify-center rounded-lg bg-blue-600 text-xs font-semibold text-white transition-all hover:bg-blue-700 disabled:bg-blue-400 sm:h-10 sm:text-sm"
                                >
                                    {googleProcessing ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        'Guardar'
                                    )}
                                </button>
                            </div>

                            <div className="space-y-3 border-t border-slate-200 pt-3 sm:pt-4">
                                {googleConnected ? (
                                    <>
                                        <div className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-[10px] text-emerald-700 sm:px-4 sm:py-3 sm:text-xs">
                                            <CheckCircle2 className="h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4" />
                                            <span className="truncate font-semibold">
                                                Conectado como{' '}
                                                {googleConnectedEmail ||
                                                    'Google Calendar'}
                                            </span>
                                        </div>
                                        <div className="flex gap-2">
                                            {googleAuthUrl ? (
                                                <a
                                                    href={googleAuthUrl}
                                                    className="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg bg-blue-600 text-[10px] font-semibold text-white transition-all hover:bg-blue-700 sm:h-10 sm:text-xs"
                                                >
                                                    <ExternalLink className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                                    Reconectar
                                                </a>
                                            ) : null}
                                            <button
                                                type="button"
                                                className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-red-200 px-3 text-[10px] font-semibold text-red-600 transition-all hover:border-red-300 hover:text-red-700 sm:h-10 sm:px-4 sm:text-xs"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            '¿Desconectar Google Calendar?',
                                                        )
                                                    ) {
                                                        router.post(
                                                            '/appointments/calendar/google-config',
                                                            {
                                                                _method: 'POST',
                                                                disconnect_oauth:
                                                                    'true',
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                <LogOut className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                                Desconectar
                                            </button>
                                        </div>
                                        <p className="text-center text-[9px] text-slate-400 sm:text-[10px]">
                                            Las citas nuevas se sincronizan
                                            automáticamente.
                                        </p>
                                    </>
                                ) : googleAuthUrl ? (
                                    <a
                                        href={googleAuthUrl}
                                        className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border-2 border-slate-200 bg-white text-xs font-semibold text-slate-800 shadow-sm transition-all hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 sm:h-11 sm:text-sm"
                                    >
                                        <svg
                                            className="h-4 w-4 sm:h-5 sm:w-5"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"
                                                fill="#4285F4"
                                            />
                                            <path
                                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                                fill="#34A853"
                                            />
                                            <path
                                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                                fill="#FBBC05"
                                            />
                                            <path
                                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                                fill="#EA4335"
                                            />
                                        </svg>
                                        Configurar OAuth
                                    </a>
                                ) : (
                                    <span className="inline-flex h-9 w-full cursor-not-allowed items-center justify-center gap-2 rounded-lg bg-slate-100 text-xs font-semibold text-slate-400 sm:h-10 sm:text-sm">
                                        <ExternalLink className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                        Google no configurado
                                    </span>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Create Appointment Modal ── */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/40 p-3 backdrop-blur-sm duration-200 fade-in sm:p-4">
                    <div className="max-h-[90vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-2xl bg-white p-5 shadow-xl sm:p-6">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-100 sm:h-10 sm:w-10">
                                    <CalendarIcon className="h-4 w-4 text-indigo-600 sm:h-5 sm:w-5" />
                                </div>
                                <div>
                                    <h3 className="text-sm font-bold text-slate-900 sm:text-base">
                                        Nueva Cita
                                    </h3>
                                    <p className="text-[10px] text-slate-500 sm:text-xs">
                                        Agenda en el calendario
                                    </p>
                                </div>
                            </div>
                            <button
                                onClick={() => {
                                    setShowCreateModal(false);
                                    reset();
                                }}
                                className="rounded-lg p-1 transition-colors hover:bg-slate-100"
                            >
                                <X className="h-4 w-4 text-slate-400" />
                            </button>
                        </div>

                        <form
                            onSubmit={handleCreateSubmit}
                            className="space-y-3 sm:space-y-4"
                        >
                            <div className="space-y-1.5">
                                <label className="text-[11px] font-semibold text-slate-700 sm:text-xs">
                                    Cliente
                                </label>
                                <select
                                    value={data.client_id}
                                    onChange={(e) =>
                                        setData('client_id', e.target.value)
                                    }
                                    className="flex h-9 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs transition-all outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 sm:h-10 sm:text-sm"
                                    required
                                >
                                    <option value="">
                                        Seleccionar cliente...
                                    </option>
                                    {clients.map((c) => (
                                        <option key={c.id} value={c.user_id}>
                                            {c.nombre}
                                            {c.rut ? ` (${c.rut})` : ''} —{' '}
                                            {c.telefono || c.email}
                                        </option>
                                    ))}
                                </select>
                                {errors.client_id && (
                                    <p className="text-[10px] text-red-500 sm:text-xs">
                                        {errors.client_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-[11px] font-semibold text-slate-700 sm:text-xs">
                                    Servicio
                                </label>
                                <select
                                    value={data.producto_id}
                                    onChange={(e) =>
                                        setData('producto_id', e.target.value)
                                    }
                                    className="flex h-9 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs transition-all outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 sm:h-10 sm:text-sm"
                                    required
                                >
                                    <option value="">
                                        Seleccionar servicio...
                                    </option>
                                    {services.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.nombre} — $
                                            {Number(
                                                s.precio_venta,
                                            ).toLocaleString()}
                                            {s.duracion
                                                ? ` (${s.duracion} min)`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.producto_id && (
                                    <p className="text-[10px] text-red-500 sm:text-xs">
                                        {errors.producto_id}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                                <div className="space-y-1.5">
                                    <label className="text-[11px] font-semibold text-slate-700 sm:text-xs">
                                        Inicio
                                    </label>
                                    <input
                                        type="datetime-local"
                                        value={data.start_time}
                                        onChange={(e) => {
                                            setData(
                                                'start_time',
                                                e.target.value,
                                            );
                                            if (
                                                !data.end_time ||
                                                data.end_time <= e.target.value
                                            ) {
                                                const end = new Date(
                                                    new Date(
                                                        e.target.value,
                                                    ).getTime() +
                                                        60 * 60 * 1000,
                                                );
                                                setData(
                                                    'end_time',
                                                    format(
                                                        end,
                                                        "yyyy-MM-dd'T'HH:mm",
                                                    ),
                                                );
                                            }
                                        }}
                                        className="flex h-9 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs transition-all outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 sm:h-10 sm:text-sm"
                                        required
                                    />
                                    {errors.start_time && (
                                        <p className="text-[10px] text-red-500 sm:text-xs">
                                            {errors.start_time}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-[11px] font-semibold text-slate-700 sm:text-xs">
                                        Fin
                                    </label>
                                    <input
                                        type="datetime-local"
                                        value={data.end_time}
                                        onChange={(e) =>
                                            setData('end_time', e.target.value)
                                        }
                                        className="flex h-9 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs transition-all outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 sm:h-10 sm:text-sm"
                                        required
                                    />
                                    {errors.end_time && (
                                        <p className="text-[10px] text-red-500 sm:text-xs">
                                            {errors.end_time}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-[11px] font-semibold text-slate-700 sm:text-xs">
                                    Notas (opcional)
                                </label>
                                <textarea
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    className="flex h-16 w-full resize-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs transition-all outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 sm:h-20 sm:text-sm"
                                    placeholder="Notas adicionales..."
                                />
                            </div>

                            <div className="flex gap-2 pt-1 sm:gap-3">
                                <button
                                    type="button"
                                    className="h-9 flex-1 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 sm:h-10 sm:text-sm"
                                    onClick={() => {
                                        setShowCreateModal(false);
                                        reset();
                                    }}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex h-9 flex-1 items-center justify-center rounded-lg bg-indigo-600 text-xs font-semibold text-white transition-all hover:bg-indigo-700 disabled:bg-indigo-400 sm:h-10 sm:text-sm"
                                >
                                    {processing ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        'Crear Cita'
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
