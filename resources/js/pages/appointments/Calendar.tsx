import { Head, router, useForm } from '@inertiajs/react';
import {
    format, isSameDay, startOfMonth, endOfMonth, isWithinInterval,
    startOfWeek, addDays, addWeeks, addMonths, subWeeks, subMonths, subDays, differenceInMinutes,
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
    monthlyStats: { total: number; completadas: number; canceladas: number; ingresos: number; pendientes: number; tasaCompletitud: number };
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
    const [currentMonth, setCurrentMonth] = useState(() => startOfMonth(new Date()));
    const [selectedDay, setSelectedDay] = useState<Date | null>(new Date());
    const currentDate = useMemo(() => selectedDay || new Date(), [selectedDay]);

    const { data: googleData, setData: setGoogleData, post: postGoogle, processing: googleProcessing } = useForm({
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
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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

    const todayAppointments = appointments.filter(app => {
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

    const allAppointments = useMemo(() => [...appointments, ...syncedEvents], [appointments, syncedEvents]);

    const citasEnMes = useMemo(
        () => allAppointments.filter((c: any) => {
            const d = new Date(c.start_time || c.start?.dateTime || c.start?.date || c.start);
            return isWithinInterval(d, { start: monthStart, end: monthEnd });
        }),
        [allAppointments, monthStart, monthEnd],
    );

    const diasConCitas = useMemo(() => {
        const map = new Map<string, any[]>();
        for (const c of citasEnMes) {
            const dateStr = c.start_time || c.start?.dateTime || c.start?.date || c.start;
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

    const weekStart = useMemo(() => startOfWeek(currentDate, { weekStartsOn: 0 }), [currentDate]);

    const weekDays = useMemo(() =>
        Array.from({ length: 7 }, (_, i) => addDays(weekStart, i)),
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
                className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${map[status] || 'bg-slate-100 text-slate-700'}`}
            >
                {status}
            </span>
        );
    };

    const statusColor = (status: string) => {
        switch (status) {
            case 'completada': return 'bg-emerald-500';
            case 'cancelada': return 'bg-rose-500';
            case 'confirmada': return 'bg-blue-500';
            case 'en_curso': return 'bg-violet-500';
            case 'no_show': return 'bg-gray-400';
            default: return 'bg-amber-500';
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

            <div className="mx-auto w-full px-2 sm:px-4 lg:px-6 pt-3 sm:pt-4 pb-0 flex flex-col flex-1 min-h-0" style={{ maxWidth: 'min(1600px, 100%)' }}>
                {/* ── Header ── */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3 shrink-0">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                            Calendario
                        </h1>
                        <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
                            {format(new Date(), "EEEE d 'de' MMMM, yyyy", { locale: es })}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {googleConnected ? (
                            <button
                                onClick={() => setShowGoogleConfig(true)}
                                className="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 text-xs font-semibold hover:bg-emerald-100 transition-colors"
                            >
                                <CheckCircle2 className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">Google Sync</span>
                            </button>
                        ) : (
                            <button
                                onClick={() => setShowGoogleConfig(true)}
                                className="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 bg-white text-slate-600 text-xs font-semibold hover:bg-slate-50 transition-colors"
                            >
                                <RefreshCw className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">Conectar Google</span>
                            </button>
                        )}
                        <button
                            onClick={() => openCreateModal()}
                            className="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary/90 transition-colors shadow-sm"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">Nueva Cita</span>
                            <span className="sm:hidden">Cita</span>
                        </button>
                    </div>
                </div>

                {/* ── Monthly Stats ── */}
                <div className="mb-2 sm:mb-3 grid grid-cols-5 gap-1.5 sm:gap-2 shrink-0">
                    {[
                        { label: 'total', value: monthlyStats.total, color: 'text-slate-900', bg: 'bg-slate-100' },
                        { label: 'completadas', value: monthlyStats.completadas, color: 'text-emerald-700', bg: 'bg-emerald-100' },
                        { label: 'pendientes', value: monthlyStats.pendientes, color: 'text-amber-700', bg: 'bg-amber-100' },
                        { label: 'canceladas', value: monthlyStats.canceladas, color: 'text-rose-700', bg: 'bg-rose-100' },
                        { label: 'ingresos', value: `$${monthlyStats.ingresos.toLocaleString()}`, color: 'text-emerald-700', bg: 'bg-emerald-100' },
                    ].map(s => (
                        <div key={s.label} className={`${s.bg} rounded-lg sm:rounded-xl p-2 sm:p-3 min-w-0`}>
                            <p className="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-slate-500 truncate mb-0.5">
                                {s.label === 'ingresos' ? 'Ingresos' : s.label.charAt(0).toUpperCase() + s.label.slice(1)}
                            </p>
                            <p className={`text-sm sm:text-lg font-bold ${s.color} truncate`}>
                                {s.value}
                            </p>
                        </div>
                    ))}
                </div>

                {/* ── Charts Row ── */}
                <div className="mb-2 sm:mb-3 grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 shrink-0">
                    {/* Daily Trend */}
                    <div className="bg-white rounded-xl border border-slate-200 p-3 sm:p-4">
                        <p className="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">
                            Citas por día
                        </p>
                        <ResponsiveContainer width="100%" height={90}>
                            <BarChart data={appointmentsByDayMonth}>
                                <XAxis dataKey="date" tick={{ fontSize: 8 }} tickLine={false} axisLine={false} />
                                <YAxis hide />
                                <Tooltip
                                    contentStyle={{ fontSize: 10, borderRadius: 8, border: '1px solid #e2e8f0' }}
                                    formatter={((value: any) => [value, 'Citas']) as any}
                                    labelFormatter={((label: any) => `Día ${label}`) as any}
                                />
                                <Bar dataKey="total" fill="#3b82f6" radius={[2, 2, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Status Distribution */}
                    <div className="bg-white rounded-xl border border-slate-200 p-3 sm:p-4">
                        <p className="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">
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
                                        {statusDistributionMonth.map((_entry, i) => (
                                            <Cell key={i} fill={statusDistributionMonth[i]?.color} />
                                        ))}
                                    </Pie>
                                </PieChart>
                            </ResponsiveContainer>
                            <div className="flex-1 space-y-1 min-w-0">
                                {statusDistributionMonth.map(s => (
                                    <div key={s.name} className="flex items-center justify-between text-[9px] sm:text-[10px]">
                                        <span className="flex items-center gap-1 text-slate-600 font-medium truncate">
                                            <span className="w-1.5 h-1.5 rounded-full shrink-0" style={{ backgroundColor: s.color }} />
                                            {s.name}
                                        </span>
                                        <span className="font-bold text-slate-900 ml-1">{s.value}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Top Services */}
                    <div className="bg-white rounded-xl border border-slate-200 p-3 sm:p-4">
                        <p className="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">
                            Servicios top
                        </p>
                        <div className="space-y-1.5">
                            {monthServices.length === 0 ? (
                                <p className="text-[10px] text-slate-400">Sin datos</p>
                            ) : (
                                monthServices.slice(0, 4).map((s, i) => {
                                    const maxVal = monthServices[0]?.value || 1;
                                    const colors = ['#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6'];
                                    return (
                                        <div key={s.name}>
                                            <div className="flex justify-between text-[9px] sm:text-[10px] mb-0.5">
                                                <span className="font-medium text-slate-700 truncate mr-1">{s.name}</span>
                                                <span className="font-bold text-slate-900 shrink-0">{s.value}</span>
                                            </div>
                                            <div className="h-1 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    className="h-full rounded-full"
                                                    style={{ width: `${(s.value / maxVal) * 100}%`, backgroundColor: colors[i] }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    {/* Provider Workload */}
                    <div className="bg-white rounded-xl border border-slate-200 p-3 sm:p-4">
                        <p className="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">
                            Carga empleados
                        </p>
                        <div className="space-y-2">
                            {providerWorkload.length === 0 ? (
                                <p className="text-[10px] text-slate-400">Sin datos</p>
                            ) : (
                                providerWorkload.slice(0, 4).map(p => (
                                    <div key={p.name} className="flex items-center gap-2">
                                        {p.photo ? (
                                            <img src={p.photo} alt={p.name} className="w-5 h-5 sm:w-6 sm:h-6 rounded-full object-cover shrink-0" />
                                        ) : (
                                            <div className="w-5 h-5 sm:w-6 sm:h-6 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-[8px] sm:text-[10px] font-bold shrink-0">
                                                {p.name.charAt(0)}
                                            </div>
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-[10px] sm:text-[11px] font-semibold text-slate-700 truncate">{p.name}</p>
                                            <div className="h-1 bg-slate-100 rounded-full overflow-hidden mt-0.5 max-w-[80px] sm:max-w-none">
                                                <div
                                                    className="h-full rounded-full bg-blue-500"
                                                    style={{ width: `${Math.min((p.total / (providerWorkload[0]?.total || 1)) * 100, 100)}%` }}
                                                />
                                            </div>
                                        </div>
                                        <span className="text-[10px] sm:text-[11px] font-bold text-slate-900 shrink-0">{p.total}</span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                {/* ── Main Grid: Sidebar + Calendar + Day Detail ── */}
                <div className="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4 pb-3 sm:pb-4">
                    {/* ── Left Sidebar ── */}
                    <div className="lg:col-span-3 flex flex-col gap-3 min-h-0">
                        {/* Today's Agenda (fills available height) */}
                        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col flex-1 min-h-0">
                            <div className="px-3 py-2.5 sm:px-4 sm:py-3 bg-slate-900 text-white flex items-center justify-between shrink-0">
                                <h2 className="text-[11px] sm:text-xs font-bold flex items-center gap-1.5">
                                    <Clock className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                    Hoy
                                </h2>
                                <span className="text-[9px] sm:text-[10px] font-semibold bg-white/15 text-white px-1.5 py-0.5 rounded-md">
                                    {todayAppointments.length}
                                </span>
                            </div>
                            <div className="flex-1 overflow-y-auto divide-y divide-slate-100">
                                {todayAppointments.length === 0 ? (
                                    <div className="p-4 sm:p-5 text-center">
                                        <CalendarIcon className="h-5 w-5 sm:h-6 sm:w-6 text-slate-300 mx-auto mb-1.5" />
                                        <p className="text-xs text-slate-400 font-medium">Sin citas hoy</p>
                                    </div>
                                ) : (
                                    todayAppointments
                                        .sort((a, b) => new Date(a.start_time).getTime() - new Date(b.start_time).getTime())
                                        .map(app => (
                                            <div
                                                key={app.id}
                                                className="px-3 sm:px-4 py-2.5 hover:bg-slate-50 cursor-pointer transition-colors active:bg-slate-100"
                                                onClick={() => router.visit(`/appointments/${app.id}`)}
                                            >
                                                <div className="flex items-center justify-between mb-0.5">
                                                    <span className="text-[11px] sm:text-xs font-bold text-slate-900">
                                                        {format(new Date(app.start_time), 'HH:mm')}
                                                    </span>
                                                    {statusBadge(app.status)}
                                                </div>
                                                <p className="text-[10px] sm:text-[11px] font-semibold text-slate-600 truncate">
                                                    {app.producto?.nombre}
                                                </p>
                                                <p className="text-[9px] sm:text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
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
                            <div className="bg-white rounded-xl border border-emerald-200 overflow-hidden shrink-0">
                                <div className="px-3 py-2.5 sm:px-4 sm:py-3 bg-emerald-50 border-b border-emerald-100">
                                    <div className="flex items-center gap-1.5 text-[11px] sm:text-xs">
                                        <CheckCircle2 className="h-3.5 w-3.5 sm:h-4 sm:w-4 text-emerald-600 shrink-0" />
                                        <span className="font-semibold text-emerald-800">Google Calendar activo</span>
                                    </div>
                                </div>
                                <div className="p-3 sm:p-4 space-y-2">
                                    {googleConnectedEmail && (
                                        <p className="text-[10px] sm:text-xs text-slate-600 flex items-center gap-1.5 truncate">
                                            <Mail className="h-3 w-3 sm:h-3.5 sm:w-3.5 text-slate-400 shrink-0" />
                                            {googleConnectedEmail}
                                        </p>
                                    )}
                                    <p className="text-[9px] sm:text-[10px] text-slate-400">
                                        Sincronización automática activa.
                                    </p>
                                    <div className="flex gap-1.5">
                                        <button
                                            onClick={handleSync}
                                            disabled={syncing}
                                            className="flex-1 inline-flex items-center justify-center gap-1 h-7 sm:h-8 rounded-lg text-[10px] sm:text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-300 text-white transition-all"
                                        >
                                            {syncing ? <Loader2 className="h-3 w-3 animate-spin" /> : <RefreshCw className="h-3 w-3" />}
                                            {syncing ? 'Sincronizando...' : 'Sincronizar'}
                                        </button>
                                        <button
                                            onClick={() => setShowGoogleConfig(true)}
                                            className="text-[10px] sm:text-[11px] font-semibold text-blue-600 hover:text-blue-700 px-2 transition-colors"
                                        >
                                            Configurar
                                        </button>
                                    </div>
                                    {syncError && (
                                        <div className="flex items-start gap-1.5 text-[9px] sm:text-[10px] text-red-500 bg-red-50 rounded-lg p-2">
                                            <AlertCircle className="h-2.5 w-2.5 sm:h-3 sm:w-3 mt-0.5 shrink-0" />
                                            <span>{syncError}</span>
                                        </div>
                                    )}
                                    {syncedEvents.length > 0 && (
                                        <div className="flex items-center gap-1.5 text-[9px] sm:text-[10px] text-emerald-600 bg-emerald-50 rounded-lg p-2">
                                            <CheckCircle2 className="h-2.5 w-2.5 sm:h-3 sm:w-3 shrink-0" />
                                            <span>{syncedEvents.length} eventos sincronizados</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : googleAuthUrl ? (
                            <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shrink-0">
                                <div className="px-3 py-2.5 sm:px-4 sm:py-3 bg-slate-50 border-b border-slate-100">
                                    <div className="flex items-center gap-1.5 text-[11px] sm:text-xs">
                                        <RefreshCw className="h-3.5 w-3.5 sm:h-4 sm:w-4 text-slate-500" />
                                        <span className="font-semibold text-slate-700">Google Calendar</span>
                                    </div>
                                </div>
                                <div className="p-3 sm:p-4 space-y-2.5">
                                    <p className="text-[10px] sm:text-xs text-slate-400 leading-relaxed">
                                        Conecta tu Google Calendar para sincronizar las citas.
                                    </p>
                                    <a
                                        href={googleAuthUrl}
                                        className="inline-flex items-center justify-center gap-1.5 w-full h-8 sm:h-9 rounded-lg text-[10px] sm:text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white transition-all"
                                    >
                                        <ExternalLink className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                        Conectar con Google
                                    </a>
                                </div>
                            </div>
                        ) : null}
                    </div>

                    {/* ── Calendar + Day Detail ── */}
                    <div className="lg:col-span-9 min-h-0 h-full">
                        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col h-full">
                            {/* Calendar Header */}
                            <div className="px-3 sm:px-5 py-2.5 sm:py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/80 shrink-0 gap-2">
                                <div className="flex items-center gap-2 sm:gap-3 min-w-0">
                                    <h2 className="text-sm sm:text-base font-bold text-slate-900 capitalize whitespace-nowrap">
                                        {viewMode === 'day'
                                            ? format(currentDate, "EEEE d 'de' MMMM", { locale: es })
                                            : viewMode === 'week'
                                            ? `${format(weekDays[0], "d MMM", { locale: es })} - ${format(weekDays[6], "d MMM, yyyy", { locale: es })}`
                                            : format(currentMonth, 'MMMM yyyy', { locale: es })
                                        }
                                    </h2>
                                    <div className="flex rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden shrink-0">
                                        <button
                                            type="button"
                                            onClick={goPrev}
                                            className="p-1.5 sm:p-2 hover:bg-slate-50 transition-colors border-r border-slate-200"
                                        >
                                            <ChevronLeft className="h-3.5 w-3.5 sm:h-4 sm:w-4 text-slate-600" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={goToday}
                                            className="px-2.5 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                                        >
                                            Hoy
                                        </button>
                                        <button
                                            type="button"
                                            onClick={goNext}
                                            className="p-1.5 sm:p-2 hover:bg-slate-50 transition-colors border-l border-slate-200"
                                        >
                                            <ChevronRight className="h-3.5 w-3.5 sm:h-4 sm:w-4 text-slate-600" />
                                        </button>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    {/* View Mode Toggle */}
                                    <div className="flex rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                                        {(['day', 'week', 'month'] as const).map(mode => (
                                            <button
                                                key={mode}
                                                type="button"
                                                onClick={() => setViewMode(mode)}
                                                className={`px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs font-semibold transition-colors flex items-center gap-1
                                                    ${viewMode === mode
                                                        ? 'bg-primary text-white'
                                                        : 'text-slate-600 hover:bg-slate-50'
                                                    }
                                                `}
                                            >
                                                {mode === 'day' ? <List className="h-3 w-3" /> : mode === 'week' ? <LayoutGrid className="h-3 w-3" /> : <CalendarDays className="h-3 w-3" />}
                                                <span className="hidden sm:inline">{mode === 'day' ? 'Día' : mode === 'week' ? 'Semana' : 'Mes'}</span>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <div className="flex-1 min-h-0 flex flex-col lg:flex-row">
                                {viewMode === 'month' ? (
                                    /* ── Month View ── */
                                    <div className="flex-1 min-h-0 p-2 sm:p-3 lg:p-4 flex flex-col">
                                        <div className="grid grid-cols-7 mb-1 sm:mb-2 shrink-0">
                                            {['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'].map(d => (
                                                <div key={d} className="text-center text-[9px] sm:text-[10px] lg:text-xs font-semibold text-slate-400 uppercase tracking-wider py-1">
                                                    {d}
                                                </div>
                                            ))}
                                        </div>
                                        <div className="flex-1 grid grid-cols-7 auto-rows-fr gap-px bg-slate-100 rounded-lg overflow-hidden">
                                            {daysInMonth.map((day, i) => {
                                                if (day === null) return <div key={`empty-${i}`} className="bg-white" />;
                                                const date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
                                                const key = format(date, 'yyyy-MM-dd');
                                                const dayCitas = diasConCitas.get(key);
                                                const isToday = isSameDay(date, new Date());
                                                const isSelected = selectedDay && isSameDay(date, selectedDay);
                                                return (
                                                    <button
                                                        key={key}
                                                        type="button"
                                                        onClick={() => setSelectedDay(date)}
                                                        onDoubleClick={() => openCreateModal(key)}
                                                        className={`relative bg-white p-1 sm:p-1.5 text-left hover:bg-slate-50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-inset
                                                            ${isSelected ? 'ring-2 ring-primary ring-inset bg-primary/[0.03]' : ''}
                                                        `}
                                                    >
                                                        <span className={`inline-flex items-center justify-center w-5 h-5 sm:w-6 sm:h-6 rounded-full text-[10px] sm:text-xs font-semibold
                                                            ${isToday ? 'bg-primary text-white shadow-sm' : 'text-slate-700'}
                                                        `}>
                                                            {day}
                                                        </span>
                                                        {dayCitas && dayCitas.length > 0 && (
                                                            <div className="flex flex-wrap gap-0.5 mt-0.5 sm:mt-1">
                                                                {dayCitas.slice(0, 5).map((c: any) => (
                                                                    <span key={c.id} className={`inline-block w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full ${statusColor(c.status)}`} />
                                                                ))}
                                                                {dayCitas.length > 5 && (
                                                                    <span className="text-[8px] sm:text-[9px] font-bold text-slate-400 leading-none">+{dayCitas.length - 5}</span>
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
                                    <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
                                        <div className="grid grid-cols-7 shrink-0 border-b border-slate-200 bg-slate-50/50">
                                            {weekDays.map((day, i) => {
                                                const isToday = isSameDay(day, new Date());
                                                const isSelected = selectedDay && isSameDay(day, selectedDay);
                                                return (
                                                    <button
                                                        key={i}
                                                        type="button"
                                                        onClick={() => { setSelectedDay(day); setCurrentMonth(startOfMonth(day)); }}
                                                        className={`px-2 py-2 sm:py-2.5 text-center border-r border-slate-200 last:border-r-0 transition-colors
                                                            ${isSelected ? 'bg-primary/5' : 'hover:bg-slate-100'}
                                                        `}
                                                    >
                                                        <p className="text-[9px] sm:text-[10px] font-semibold text-slate-500 uppercase">{format(day, 'EEE', { locale: es })}</p>
                                                        <p className={`text-sm sm:text-base font-bold mt-0.5 ${isToday ? 'bg-primary text-white w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center mx-auto' : 'text-slate-800'}`}>
                                                            {format(day, 'd')}
                                                        </p>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        <div className="flex-1 overflow-y-auto p-2 sm:p-3">
                                            <div className="relative">
                                                {/* Time gutter */}
                                                <div className="absolute left-0 top-0 bottom-0 w-12 sm:w-14 border-r border-slate-200" />
                                                {hours.map(h => (
                                                    <div key={h} className="flex h-16 sm:h-20 border-b border-slate-100 last:border-b-0">
                                                        <div className="w-12 sm:w-14 shrink-0 text-right pr-2 sm:pr-3 pt-0.5 text-[10px] sm:text-xs font-medium text-slate-400">
                                                            {String(h).padStart(2, '0')}:00
                                                        </div>
                                                        <div className="flex-1 grid grid-cols-7 relative">
                                                            {weekDays.map((day, di) => {
                                                                const key = format(day, 'yyyy-MM-dd');
                                                                const dayCitas = (diasConCitas.get(key) || [])
                                                                    .filter((c: any) => {
                                                                        const startH = new Date(c.start_time || c.start?.dateTime || c.start).getHours();
                                                                        return startH === h;
                                                                    });
                                                                return (
                                                                    <div key={di} className="relative border-r border-slate-100 last:border-r-0 min-h-0">
                                                                        {dayCitas.map((c: any) => {
                                                                            const start = new Date(c.start_time || c.start?.dateTime || c.start);
                                                                            const end = new Date(c.end_time || c.end?.dateTime || c.end);
                                                                            const mins = differenceInMinutes(end, start);
                                                                            const topOffset = start.getMinutes();
                                                                            return (
                                                                                <div
                                                                                    key={c.id}
                                                                                    onClick={() => router.visit(`/appointments/${c.id}`)}
                                                                                    className="absolute left-0.5 right-0.5 rounded-md px-1.5 py-1 cursor-pointer overflow-hidden text-[10px] leading-tight hover:opacity-90 transition-opacity z-10 border-l-2 shadow-sm"
                                                                                    style={{
                                                                                        top: `${(topOffset / 60) * 100}%`,
                                                                                        height: `${(mins / 60) * 100}%`,
                                                                                        minHeight: '18px',
                                                                                        backgroundColor: c.status === 'completada' ? '#d1fae5' : c.status === 'cancelada' ? '#fee2e2' : c.status === 'confirmada' ? '#dbeafe' : '#fef3c7',
                                                                                        borderLeftColor: c.status === 'completada' ? '#10b981' : c.status === 'cancelada' ? '#ef4444' : c.status === 'confirmada' ? '#3b82f6' : '#f59e0b',
                                                                                    }}
                                                                                >
                                                                                    <p className="font-bold text-[9px] text-slate-800 truncate">{c.client?.name || 'Cliente'}</p>
                                                                                    <p className="text-[8px] text-slate-500 truncate">{c.producto?.nombre || c.title || 'Cita'}</p>
                                                                                </div>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    /* ── Day View ── */
                                    <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
                                        <div className="shrink-0 border-b border-slate-200 bg-slate-50/50 px-3 sm:px-5 py-3 sm:py-4">
                                            <p className="text-sm sm:text-base font-bold text-slate-900 capitalize">
                                                {selectedDay ? format(selectedDay, "EEEE d 'de' MMMM", { locale: es }) : 'Selecciona un día'}
                                            </p>
                                            <p className="text-[11px] text-slate-500">{citasDelDia.length} cita{citasDelDia.length !== 1 ? 's' : ''}</p>
                                        </div>
                                        <div className="flex-1 overflow-y-auto p-2 sm:p-3">
                                            <div className="relative">
                                                {hours.map(h => {
                                                    const hourCitas = citasDelDia.filter((c: any) => {
                                                        const startH = new Date(c.start_time || c.start?.dateTime || c.start).getHours();
                                                        return startH === h;
                                                    });
                                                    const hasCitas = hourCitas.length > 0;
                                                    return (
                                                        <div key={h} className={`flex border-b border-slate-100 last:border-b-0 ${hasCitas ? 'bg-blue-50/30' : ''}`}>
                                                            <div className="w-14 sm:w-16 shrink-0 text-right pr-2 sm:pr-3 pt-0.5 text-[10px] sm:text-xs font-medium text-slate-400 border-r border-slate-200 h-16 sm:h-20">
                                                                {String(h).padStart(2, '0')}:00
                                                            </div>
                                                            <div className="flex-1 min-h-[64px] sm:min-h-[80px] relative p-1 space-y-1">
                                                                {hourCitas.map((c: any) => {
                                                                    const start = new Date(c.start_time || c.start?.dateTime || c.start);
                                                                    const end = new Date(c.end_time || c.end?.dateTime || c.end);
                                                                    return (
                                                                        <div
                                                                            key={c.id}
                                                                            onClick={() => router.visit(`/appointments/${c.id}`)}
                                                                            className="rounded-lg px-3 py-2 cursor-pointer hover:opacity-90 transition-opacity border-l-4 shadow-sm"
                                                                            style={{
                                                                                backgroundColor: c.status === 'completada' ? '#d1fae5' : c.status === 'cancelada' ? '#fee2e2' : c.status === 'confirmada' ? '#dbeafe' : '#fef3c7',
                                                                                borderLeftColor: c.status === 'completada' ? '#10b981' : c.status === 'cancelada' ? '#ef4444' : c.status === 'confirmada' ? '#3b82f6' : '#f59e0b',
                                                                            }}
                                                                        >
                                                                            <div className="flex items-center justify-between">
                                                                                <span className="text-xs font-bold text-slate-800">{c.client?.name || 'Cliente'}</span>
                                                                                <span className={`inline-block px-1.5 py-0.5 rounded text-[9px] font-semibold ${statusStyles[c.status] || 'bg-slate-100 text-slate-600'}`}>
                                                                                    {statusLabels[c.status] || c.status}
                                                                                </span>
                                                                            </div>
                                                                            <p className="text-[11px] text-slate-600 mt-0.5">{c.producto?.nombre || c.title || 'Cita'}</p>
                                                                            <p className="text-[10px] text-slate-400 mt-0.5">
                                                                                {format(start, 'HH:mm')} - {format(end, 'HH:mm')}
                                                                            </p>
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* ── Day Detail Panel ── */}
                                <div className="lg:w-72 xl:w-80 shrink-0 border-t lg:border-t-0 lg:border-l border-slate-200 bg-slate-50/50 flex flex-col h-full max-h-[280px] lg:max-h-none">
                                    {selectedDay ? (
                                        <>
                                            <div className="px-3 sm:px-4 py-2.5 sm:py-3 border-b border-slate-200 flex-shrink-0">
                                                <h3 className="text-sm sm:text-base font-bold text-slate-900 capitalize flex items-center justify-between">
                                                    {format(selectedDay, "EEEE d", { locale: es })}
                                                    <span className="text-[10px] sm:text-xs font-semibold text-slate-500 bg-slate-200 px-2 py-0.5 rounded-md">
                                                        {citasDelDia.length} cita{citasDelDia.length !== 1 ? 's' : ''}
                                                    </span>
                                                </h3>
                                                <p className="text-[11px] sm:text-xs font-medium text-slate-500 capitalize">
                                                    {format(selectedDay, "MMMM yyyy", { locale: es })}
                                                </p>
                                            </div>
                                            <div className="flex-1 overflow-y-auto p-2 sm:p-3 space-y-2">
                                                {citasDelDia.length === 0 ? (
                                                    <div className="flex flex-col items-center justify-center h-full text-slate-400 py-8">
                                                        <CalendarIcon className="w-8 h-8 sm:w-10 sm:h-10 mb-2 opacity-40" />
                                                        <p className="text-xs sm:text-sm font-bold">Día libre</p>
                                                        <p className="text-[10px] sm:text-xs opacity-70">Sin citas programadas</p>
                                                        <button
                                                            onClick={() => openCreateModal(format(selectedDay, 'yyyy-MM-dd'))}
                                                            className="mt-3 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-[10px] sm:text-xs font-semibold text-primary hover:border-primary/40 transition-colors shadow-sm"
                                                        >
                                                            Agendar cita
                                                        </button>
                                                    </div>
                                                ) : (
                                                    citasDelDia
                                                        .sort((a, b) => new Date(a.start_time).getTime() - new Date(b.start_time).getTime())
                                                        .map((c: any) => (
                                                            <div
                                                                key={c.id}
                                                                onClick={() => router.visit(`/appointments/${c.id}`)}
                                                                className="bg-white border border-slate-200 rounded-lg p-2.5 sm:p-3 cursor-pointer hover:border-primary/30 hover:shadow-sm transition-all group active:scale-[0.99]"
                                                            >
                                                                <div className="flex items-center justify-between mb-1.5">
                                                                    <span className="text-[11px] sm:text-xs font-bold text-slate-900 group-hover:text-primary transition-colors">
                                                                        {format(new Date(c.start_time || c.start?.dateTime || c.start), 'HH:mm')}
                                                                    </span>
                                                                    <span className={`inline-block px-1.5 py-0.5 rounded text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider ${statusStyles[c.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                                                        {statusLabels[c.status] ?? c.status}
                                                                    </span>
                                                                </div>
                                                                <p className="text-[11px] sm:text-xs font-bold text-slate-800 mb-0.5 leading-tight">
                                                                    {c.client?.name ?? 'Cliente Google'}
                                                                </p>
                                                                <p className="text-[10px] sm:text-[11px] text-slate-500 font-medium truncate">
                                                                    {c.producto?.nombre ?? c.title ?? 'Cita'}
                                                                </p>
                                                            </div>
                                                        ))
                                                )}
                                            </div>
                                        </>
                                    ) : (
                                        <div className="flex-1 flex flex-col items-center justify-center p-4 sm:p-6 text-center text-slate-400">
                                            <CalendarIcon className="w-8 h-8 sm:w-10 sm:h-10 mb-2 sm:mb-3 opacity-30" />
                                            <p className="text-xs sm:text-sm font-bold">Selecciona un día</p>
                                            <p className="text-[10px] sm:text-xs opacity-70">Para ver las citas</p>
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
                <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-200">
                    <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-5 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <div className="h-9 w-9 sm:h-10 sm:w-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                                    <RefreshCw className="h-4 w-4 sm:h-5 sm:w-5 text-blue-600" />
                                </div>
                                <div>
                                    <h3 className="text-sm sm:text-base font-bold text-slate-900">Google Calendar</h3>
                                    <p className="text-[10px] sm:text-xs text-slate-500">Sincroniza tus citas</p>
                                </div>
                            </div>
                            <button
                                onClick={() => setShowGoogleConfig(false)}
                                className="p-1 hover:bg-slate-100 rounded-lg transition-colors"
                            >
                                <X className="h-4 w-4 text-slate-400" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveGoogleConfig} className="space-y-3 sm:space-y-4">
                            <div className="space-y-1.5">
                                <label className="text-[11px] sm:text-xs font-semibold text-slate-700">ID del Calendario (opcional)</label>
                                <input
                                    type="text"
                                    placeholder="ej: tu.calendario@gmail.com"
                                    value={googleData.google_calendar_id}
                                    onChange={e => setGoogleData('google_calendar_id', e.target.value)}
                                    className="flex h-9 sm:h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs sm:text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition-all"
                                />
                                <p className="text-[9px] sm:text-[10px] text-slate-400">Déjalo vacío para usar el calendario predeterminado.</p>
                            </div>

                            <div className="flex gap-2 pt-1">
                                <button
                                    type="button"
                                    onClick={() => setShowGoogleConfig(false)}
                                    className="flex-1 h-9 sm:h-10 rounded-lg border border-slate-200 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={googleProcessing}
                                    className="flex-1 h-9 sm:h-10 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-xs sm:text-sm font-semibold transition-all flex items-center justify-center"
                                >
                                    {googleProcessing ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Guardar'}
                                </button>
                            </div>

                            <div className="border-t border-slate-200 pt-3 sm:pt-4 space-y-3">
                                {googleConnected ? (
                                    <>
                                        <div className="flex items-center gap-2 text-[10px] sm:text-xs text-emerald-700 bg-emerald-50 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 border border-emerald-200">
                                            <CheckCircle2 className="h-3.5 w-3.5 sm:h-4 sm:w-4 shrink-0" />
                                            <span className="font-semibold truncate">
                                                Conectado como {googleConnectedEmail || 'Google Calendar'}
                                            </span>
                                        </div>
                                        <div className="flex gap-2">
                                            {googleAuthUrl ? (
                                                <a
                                                    href={googleAuthUrl}
                                                    className="flex-1 inline-flex items-center justify-center gap-1.5 h-9 sm:h-10 rounded-lg text-[10px] sm:text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white transition-all"
                                                >
                                                    <ExternalLink className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                                    Reconectar
                                                </a>
                                            ) : null}
                                            <button
                                                type="button"
                                                className="inline-flex items-center justify-center gap-1.5 h-9 sm:h-10 rounded-lg text-[10px] sm:text-xs font-semibold text-red-600 hover:text-red-700 border border-red-200 hover:border-red-300 px-3 sm:px-4 transition-all"
                                                onClick={() => {
                                                    if (confirm('¿Desconectar Google Calendar?')) {
                                                        router.post('/appointments/calendar/google-config', {
                                                            _method: 'POST',
                                                            disconnect_oauth: 'true',
                                                        });
                                                    }
                                                }}
                                            >
                                                <LogOut className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                                Desconectar
                                            </button>
                                        </div>
                                        <p className="text-[9px] sm:text-[10px] text-slate-400 text-center">
                                            Las citas nuevas se sincronizan automáticamente.
                                        </p>
                                    </>
                                ) : googleAuthUrl ? (
                                    <a
                                        href={googleAuthUrl}
                                        className="inline-flex items-center justify-center gap-2 w-full h-10 sm:h-11 rounded-lg text-xs sm:text-sm font-semibold bg-white text-slate-800 border-2 border-slate-200 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition-all shadow-sm"
                                    >
                                        <svg className="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24">
                                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                        </svg>
                                        Configurar OAuth
                                    </a>
                                ) : (
                                    <span className="inline-flex items-center justify-center gap-2 w-full h-9 sm:h-10 rounded-lg text-xs sm:text-sm font-semibold bg-slate-100 text-slate-400 cursor-not-allowed">
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
                <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-200">
                    <div className="bg-white rounded-2xl shadow-xl max-w-lg w-full p-5 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <div className="h-9 w-9 sm:h-10 sm:w-10 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
                                    <CalendarIcon className="h-4 w-4 sm:h-5 sm:w-5 text-indigo-600" />
                                </div>
                                <div>
                                    <h3 className="text-sm sm:text-base font-bold text-slate-900">Nueva Cita</h3>
                                    <p className="text-[10px] sm:text-xs text-slate-500">Agenda en el calendario</p>
                                </div>
                            </div>
                            <button
                                onClick={() => { setShowCreateModal(false); reset(); }}
                                className="p-1 hover:bg-slate-100 rounded-lg transition-colors"
                            >
                                <X className="h-4 w-4 text-slate-400" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateSubmit} className="space-y-3 sm:space-y-4">
                            <div className="space-y-1.5">
                                <label className="text-[11px] sm:text-xs font-semibold text-slate-700">Cliente</label>
                                <select
                                    value={data.client_id}
                                    onChange={e => setData('client_id', e.target.value)}
                                    className="flex h-9 sm:h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs sm:text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"
                                    required
                                >
                                    <option value="">Seleccionar cliente...</option>
                                    {clients.map(c => (
                                        <option key={c.id} value={c.user_id}>
                                            {c.nombre}{c.rut ? ` (${c.rut})` : ''} — {c.telefono || c.email}
                                        </option>
                                    ))}
                                </select>
                                {errors.client_id && <p className="text-[10px] sm:text-xs text-red-500">{errors.client_id}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-[11px] sm:text-xs font-semibold text-slate-700">Servicio</label>
                                <select
                                    value={data.producto_id}
                                    onChange={e => setData('producto_id', e.target.value)}
                                    className="flex h-9 sm:h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs sm:text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"
                                    required
                                >
                                    <option value="">Seleccionar servicio...</option>
                                    {services.map(s => (
                                        <option key={s.id} value={s.id}>
                                            {s.nombre} — ${Number(s.precio_venta).toLocaleString()}
                                            {s.duracion ? ` (${s.duracion} min)` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.producto_id && <p className="text-[10px] sm:text-xs text-red-500">{errors.producto_id}</p>}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                                <div className="space-y-1.5">
                                    <label className="text-[11px] sm:text-xs font-semibold text-slate-700">Inicio</label>
                                    <input
                                        type="datetime-local"
                                        value={data.start_time}
                                        onChange={e => {
                                            setData('start_time', e.target.value);
                                            if (!data.end_time || data.end_time <= e.target.value) {
                                                const end = new Date(new Date(e.target.value).getTime() + 60 * 60 * 1000);
                                                setData('end_time', format(end, "yyyy-MM-dd'T'HH:mm"));
                                            }
                                        }}
                                        className="flex h-9 sm:h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs sm:text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"
                                        required
                                    />
                                    {errors.start_time && <p className="text-[10px] sm:text-xs text-red-500">{errors.start_time}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-[11px] sm:text-xs font-semibold text-slate-700">Fin</label>
                                    <input
                                        type="datetime-local"
                                        value={data.end_time}
                                        onChange={e => setData('end_time', e.target.value)}
                                        className="flex h-9 sm:h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs sm:text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"
                                        required
                                    />
                                    {errors.end_time && <p className="text-[10px] sm:text-xs text-red-500">{errors.end_time}</p>}
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-[11px] sm:text-xs font-semibold text-slate-700">Notas (opcional)</label>
                                <textarea
                                    value={data.notes}
                                    onChange={e => setData('notes', e.target.value)}
                                    className="flex h-16 sm:h-20 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs sm:text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all resize-none"
                                    placeholder="Notas adicionales..."
                                />
                            </div>

                            <div className="flex gap-2 sm:gap-3 pt-1">
                                <button
                                    type="button"
                                    className="flex-1 h-9 sm:h-10 rounded-lg border border-slate-200 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                                    onClick={() => { setShowCreateModal(false); reset(); }}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 h-9 sm:h-10 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white text-xs sm:text-sm font-semibold transition-all flex items-center justify-center"
                                >
                                    {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Crear Cita'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
