import { Head, Link, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Calendar as CalendarIcon,
    Clock,
    Trash2,
    CheckCircle2,
    XCircle,
    AlertCircle,
    DollarSign,
    BarChart3,
    PieChart as PieChartIcon,
    Search,
    Users
} from 'lucide-react';
import React, { useState } from 'react';
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
    AreaChart,
    Area
} from 'recharts';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';

const statusStyles: Record<string, string> = {
    pendiente: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-800',
    confirmada: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/20 dark:text-blue-400 dark:border-blue-800',
    completada: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-800',
    cancelada: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-800',
};

export default function Index({
    appointments,
    stats,
    appointmentsByDay,
    statusDistribution,
    revenueByDay,
    ingresosReales,
    tasaConversion,
    proyeccionDiaria,
    promedioSemanal,
    topServices,
    providerStats,
}: {
    appointments: any[];
    stats: Record<string, number>;
    appointmentsByDay: { date: string; total: number }[];
    statusDistribution: { name: string; value: number; color: string }[];
    revenueByDay: { date: string; ingresos: number }[];
    ingresosReales: number;
    tasaConversion: number;
    proyeccionDiaria: number;
    promedioSemanal: number;
    topServices: { name: string; reservas: number; ingresos: number }[];
    providerStats: { name: string; photo: string; total: number; ingresos: number }[];
}) {
    const [search, setSearch] = useState('');

    const filtered = appointments.filter((a) => {
        if (!search) return true;
        const q = search.toLowerCase();
        return (
            a.client?.name?.toLowerCase().includes(q) ||
            a.producto?.nombre?.toLowerCase().includes(q) ||
            a.status?.toLowerCase().includes(q)
        );
    });

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar esta cita definitivamente?')) {
            router.delete(`/appointments/${id}`);
        }
    };

    const handleStatus = (id: number, status: string) => {
        router.put(`/appointments/${id}`, { status });
    };

    const todayCount = appointments.filter(
        (a) =>
            format(new Date(a.start_time), 'yyyy-MM-dd') ===
            format(new Date(), 'yyyy-MM-dd')
    ).length;

    const statCards = [
        {
            label: 'Total Citas',
            value: stats.total,
            icon: CalendarIcon,
            color: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-950/20',
        },
        {
            label: 'Pendientes',
            value: stats.pendiente,
            icon: AlertCircle,
            color: 'text-amber-600 bg-amber-50 dark:bg-amber-950/20',
        },
        {
            label: 'Hoy',
            value: todayCount,
            icon: Clock,
            color: 'text-blue-600 bg-blue-50 dark:bg-blue-950/20',
        },
        {
            label: 'Completadas',
            value: stats.completada,
            icon: CheckCircle2,
            color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/20',
        },
        {
            label: 'Canceladas',
            value: stats.cancelada,
            icon: XCircle,
            color: 'text-rose-600 bg-rose-50 dark:bg-rose-950/20',
        },
        {
            label: 'Ingresos Est.',
            value: `$${stats.ingresos_estimados.toLocaleString()}`,
            icon: DollarSign,
            color: 'text-violet-600 bg-violet-50 dark:bg-violet-950/20',
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Gestión de Citas', href: '/appointments' },
            ]}
        >
            <Head title="Gestión de Citas" />

            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-extrabold text-slate-900 dark:text-white">
                            Gestión de Citas
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            {format(new Date(), "EEEE d 'de' MMMM, yyyy", {
                                locale: es,
                            })}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/appointments/calendar">
                            <Button
                                variant="outline"
                                size="sm"
                                className="rounded-xl"
                            >
                                <CalendarIcon className="h-4 w-4" />
                                Calendario
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {statCards.map((s) => (
                        <Card
                            key={s.label}
                            className="rounded-2xl border-slate-200/70 py-4 dark:border-slate-800"
                        >
                            <CardContent className="px-4">
                                <div className="flex items-center gap-3">
                                    <div
                                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${s.color}`}
                                    >
                                        <s.icon className="h-5 w-5" />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="truncate text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            {s.label}
                                        </p>
                                        <p className="text-lg font-black text-slate-900 dark:text-white">
                                            {s.value}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Projection Cards */}
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Card className="rounded-2xl border-slate-200/70 py-4 dark:border-slate-800">
                            <CardContent className="px-4">
                                <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Ingresos Reales</p>
                                <p className="text-xl font-black text-emerald-600">${ingresosReales.toLocaleString()}</p>
                            </CardContent>
                        </Card>
                        <Card className="rounded-2xl border-slate-200/70 py-4 dark:border-slate-800">
                            <CardContent className="px-4">
                                <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Tasa Conversión</p>
                                <p className="text-xl font-black text-blue-600">{tasaConversion}%</p>
                            </CardContent>
                        </Card>
                        <Card className="rounded-2xl border-slate-200/70 py-4 dark:border-slate-800">
                            <CardContent className="px-4">
                                <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Proyección Diaria</p>
                                <p className="text-xl font-black text-violet-600">${proyeccionDiaria.toLocaleString()}</p>
                            </CardContent>
                        </Card>
                        <Card className="rounded-2xl border-slate-200/70 py-4 dark:border-slate-800">
                            <CardContent className="px-4">
                                <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Promedio Semanal</p>
                                <p className="text-xl font-black text-amber-600">${promedioSemanal.toLocaleString()}</p>
                            </CardContent>
                        </Card>
                    </div>

                {/* Charts View */}
                <div className="mb-6 space-y-6">
                        {/* Row 1: Appointments + Revenue */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <Card className="rounded-2xl border-slate-200/70 dark:border-slate-800">
                                <CardHeader className="flex flex-row items-center justify-between px-5 py-4">
                                    <CardTitle className="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                                        <BarChart3 className="h-4 w-4 text-indigo-500" />
                                        Citas por Día (Últimos 30 días)
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="px-2 pb-4 pt-0">
                                    <ResponsiveContainer width="100%" height={200}>
                                        <BarChart data={appointmentsByDay}>
                                            <XAxis dataKey="date" tickFormatter={(d) => format(new Date(d), 'dd')} tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} interval="preserveStartEnd" />
                                            <YAxis allowDecimals={false} tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
                                            <Tooltip labelFormatter={(d) => format(new Date(d), "d 'de' MMMM", { locale: es })} formatter={(value) => [value, 'Citas']} contentStyle={{ borderRadius: 12, border: '1px solid #e2e8f0', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1)', fontSize: 12 }} />
                                            <Bar dataKey="total" radius={[4, 4, 0, 0]} fill="#6366f1" maxBarSize={20} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>

                            <Card className="rounded-2xl border-slate-200/70 dark:border-slate-800">
                                <CardHeader className="flex flex-row items-center justify-between px-5 py-4">
                                    <CardTitle className="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                                        <DollarSign className="h-4 w-4 text-emerald-500" />
                                        Ingresos (Últimos 30 días)
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="px-2 pb-4 pt-0">
                                    <ResponsiveContainer width="100%" height={200}>
                                        <AreaChart data={revenueByDay}>
                                            <XAxis dataKey="date" tickFormatter={(d) => format(new Date(d), 'dd')} tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} interval="preserveStartEnd" />
                                            <YAxis tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} tickFormatter={(v) => `$${v}`} />
                                            <Tooltip labelFormatter={(d) => format(new Date(d), "d 'de' MMMM", { locale: es })} formatter={(value) => [`$${value}`, 'Ingresos']} contentStyle={{ borderRadius: 12, border: '1px solid #e2e8f0', fontSize: 12 }} />
                                            <Area type="monotone" dataKey="ingresos" stroke="#22c55e" fill="#22c55e" fillOpacity={0.15} strokeWidth={2} />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Row 2: Status + Top Services + Providers */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                            <Card className="rounded-2xl border-slate-200/70 dark:border-slate-800">
                                <CardHeader className="flex flex-row items-center justify-between px-5 py-4">
                                    <CardTitle className="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                                        <PieChartIcon className="h-4 w-4 text-indigo-500" />
                                        Distribución
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="px-2 pb-4 pt-0">
                                    <ResponsiveContainer width="100%" height={180}>
                                        <PieChart>
                                            <Pie data={statusDistribution.filter((d) => d.value > 0)} cx="50%" cy="50%" innerRadius={45} outerRadius={70} dataKey="value" stroke="none">
                                                {statusDistribution.filter((d) => d.value > 0).map((entry) => (<Cell key={entry.name} fill={entry.color} />))}
                                            </Pie>
                                            <Tooltip formatter={(value, name) => [value, name]} contentStyle={{ borderRadius: 12, border: '1px solid #e2e8f0', fontSize: 12 }} />
                                        </PieChart>
                                    </ResponsiveContainer>
                                    <div className="mt-2 grid grid-cols-2 gap-1 px-3">
                                        {statusDistribution.filter((d) => d.value > 0).map((d) => (
                                            <div key={d.name} className="flex items-center gap-2 text-[11px] font-semibold text-slate-600 dark:text-slate-400">
                                                <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: d.color }} />
                                                {d.name}: {d.value}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="rounded-2xl border-slate-200/70 dark:border-slate-800">
                                <CardHeader className="px-5 py-4">
                                    <CardTitle className="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                                        <BarChart3 className="h-4 w-4 text-violet-500" />
                                        Top Servicios
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="px-5 pb-4 pt-0 space-y-2">
                                    {topServices.map((s, i) => (
                                        <div key={s.name} className="flex items-center gap-3">
                                            <span className="text-[11px] font-black text-slate-400 w-4">{i + 1}</span>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-xs font-semibold text-slate-800 truncate">{s.name}</p>
                                                <div className="flex items-center gap-3 text-[10px] text-slate-400">
                                                    <span>{s.reservas} reservas</span>
                                                    <span>${s.ingresos.toLocaleString()}</span>
                                                </div>
                                            </div>
                                            <div className="h-6 w-16 bg-slate-100 rounded-full overflow-hidden dark:bg-slate-800">
                                                <div className="h-full bg-violet-500 rounded-full" style={{ width: `${Math.min((s.reservas / Math.max(...topServices.map(x => x.reservas))) * 100, 100)}%` }} />
                                            </div>
                                        </div>
                                    ))}
                                    {topServices.length === 0 && <p className="text-xs text-slate-400 text-center py-4">Sin datos</p>}
                                </CardContent>
                            </Card>

                            <Card className="rounded-2xl border-slate-200/70 dark:border-slate-800">
                                <CardHeader className="px-5 py-4">
                                    <CardTitle className="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                                        <Users className="h-4 w-4 text-amber-500" />
                                        Proveedores
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="px-5 pb-4 pt-0 space-y-2">
                                    {providerStats.map((p) => (
                                        <div key={p.name} className="flex items-center gap-3">
                                            <img src={p.photo} alt={p.name} className="h-8 w-8 rounded-full object-cover shrink-0" />
                                            <div className="flex-1 min-w-0">
                                                <p className="text-xs font-semibold text-slate-800 truncate">{p.name}</p>
                                                <p className="text-[10px] text-slate-400">{p.total} citas · ${p.ingresos.toLocaleString()}</p>
                                            </div>
                                        </div>
                                    ))}
                                    {providerStats.length === 0 && <p className="text-xs text-slate-400 text-center py-4">Sin datos</p>}
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                {/* Search & Actions Bar */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            placeholder="Buscar cliente, servicio..."
                            className="h-9 rounded-xl pl-9 text-sm"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <span className="text-xs font-semibold text-slate-400">
                        {filtered.length} de {appointments.length} citas
                    </span>
                </div>

                {/* Table */}
                <Card className="overflow-hidden rounded-2xl border-slate-200/70 dark:border-slate-800">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-900/50">
                                    <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        Fecha / Hora
                                    </th>
                                    <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        Cliente
                                    </th>
                                    <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        Servicio
                                    </th>
                                    <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        Pago
                                    </th>
                                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {filtered.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-12 text-center text-slate-400"
                                        >
                                            {search
                                                ? 'No se encontraron citas con ese filtro.'
                                                : 'No hay citas agendadas aún.'}
                                        </td>
                                    </tr>
                                ) : (
                                    filtered.map((app) => (
                                        <tr
                                            key={app.id}
                                            className="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2 font-semibold text-slate-900 dark:text-white">
                                                    <CalendarIcon className="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                                    <span>
                                                        {format(
                                                            new Date(
                                                                app.start_time
                                                            ),
                                                            'dd MMM',
                                                            { locale: es }
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="mt-0.5 flex items-center gap-1.5 text-[11px] text-slate-400">
                                                    <Clock className="h-3 w-3" />
                                                    {format(
                                                        new Date(
                                                            app.start_time
                                                        ),
                                                        'HH:mm'
                                                    )}{' '}
                                                    -{' '}
                                                    {format(
                                                        new Date(
                                                            app.end_time
                                                        ),
                                                        'HH:mm'
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                                {app.client?.name || (
                                                    <span className="text-slate-400">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600 dark:text-slate-300">
                                                {app.producto?.nombre ||
                                                    '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <select
                                                    value={app.status}
                                                    onChange={(e) =>
                                                        handleStatus(
                                                            app.id,
                                                            e.target.value
                                                        )
                                                    }
                                                    className={`rounded-lg border px-2 py-1 text-[11px] font-bold uppercase tracking-wider outline-none cursor-pointer ${statusStyles[app.status] || 'border-slate-200 text-slate-600'}`}
                                                >
                                                    <option value="pendiente">
                                                        Pendiente
                                                    </option>
                                                    <option value="confirmada">
                                                        Confirmada
                                                    </option>
                                                    <option value="completada">
                                                        Completada
                                                    </option>
                                                    <option value="cancelada">
                                                        Cancelada
                                                    </option>
                                                </select>
                                            </td>
                                            <td className="px-4 py-3">
                                                <select
                                                    value={
                                                        app.payment_status ||
                                                        'pendiente'
                                                    }
                                                    onChange={(e) =>
                                                        router.put(
                                                            `/appointments/${app.id}`,
                                                            {
                                                                payment_status:
                                                                    e.target
                                                                        .value,
                                                            }
                                                        )
                                                    }
                                                    className={`rounded-lg border px-2 py-1 text-[11px] font-bold uppercase tracking-wider outline-none cursor-pointer ${
                                                        app.payment_status ===
                                                        'pagado'
                                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                            : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400'
                                                    }`}
                                                >
                                                    <option value="pendiente">
                                                        Pendiente
                                                    </option>
                                                    <option value="pagado">
                                                        Pagado
                                                    </option>
                                                </select>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-xs"
                                                        className="text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                                        title="Eliminar"
                                                        onClick={() =>
                                                            handleDelete(
                                                                app.id
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
