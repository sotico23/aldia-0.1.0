import { Head, Link, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Calendar as CalendarIcon,
    TrendingUp,
    Download,
    Upload,
    FileSpreadsheet,
    BarChart3,
    PieChart as PieChartIcon,
    Clock,
    CheckCircle2,
} from 'lucide-react';
import { useRef } from 'react';
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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
export default function Dashboard({
    citasHoy,
    stats,
    appointmentsByDay,
    statusDistribution,
    topServices,
}: {
    citasHoy: any[];
    stats: {
        hoy: number;
        semana: number;
        mes: number;
        ingresosHoy: number;
        ingresosSemana: number;
        ingresosMes: number;
        clientesHoy: number;
        ocupacion: number;
    };
    appointmentsByDay: { date: string; total: number; ingresos: number }[];
    statusDistribution: { name: string; value: number; color: string }[];
    topServices: { name: string; value: number }[];
}) {
    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);

    const handleImportCSV = () => {
        csvInputRef.current?.click();
    };

    const handleImportExcel = () => {
        excelInputRef.current?.click();
    };

    const handleFileChange = (
        e: React.ChangeEvent<HTMLInputElement>,
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        type: 'csv' | 'excel',
    ) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        router.post('/appointments/importar', formData, {
            forceFormData: true,
            onSuccess: () => {
                e.target.value = '';
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: 'Dashboard de Citas',
                    href: '/appointments/dashboard',
                },
            ]}
        >
            <Head title="Dashboard | Citas y Reservas" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-3xl font-extrabold text-slate-900 dark:text-white">
                            Panel Principal
                        </h1>
                        <p className="text-slate-500">
                            Resumen de tu agenda para hoy,{' '}
                            {format(new Date(), "EEEE d 'de' MMMM", {
                                locale: es,
                            })}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            className="gap-2 rounded-xl bg-primary font-bold text-white hover:bg-primary/90"
                            onClick={() => router.visit('/appointments/create')}
                        >
                            <CalendarIcon className="h-3.5 w-3.5" />
                            Nueva Cita
                        </Button>
                        <Link
                            href="/appointments/calendar"
                            className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-lg shadow-primary/20 transition-all hover:bg-primary/90"
                        >
                            <CalendarIcon className="h-4 w-4" />
                            Ver Calendario
                        </Link>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9 gap-2 rounded-xl border-muted-foreground/10 font-bold"
                                >
                                    <Download className="h-4 w-4 text-primary" />
                                    <span>Herramientas</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem onClick={handleImportCSV}>
                                    <Upload className="mr-2 h-4 w-4" />
                                    Importar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={handleImportExcel}>
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Importar Excel
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() =>
                                        window.open(
                                            '/appointments/exportar?format=csv',
                                            '_blank',
                                        )
                                    }
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() =>
                                        window.open(
                                            '/appointments/exportar?format=excel',
                                            '_blank',
                                        )
                                    }
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar Excel
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* Stats Grid - Daily/Weekly/Monthly */}
                <div className="mb-8 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="flex flex-row items-center justify-between px-5 pt-4 pb-2">
                            <CardTitle className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                Citas Hoy
                            </CardTitle>
                            <CalendarIcon className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent className="px-5 pb-4">
                            <div className="text-2xl font-black text-slate-900">
                                {stats.hoy}
                            </div>
                            <p className="text-[11px] font-semibold text-slate-400">
                                {stats.clientesHoy} cliente(s)
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="flex flex-row items-center justify-between px-5 pt-4 pb-2">
                            <CardTitle className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                Esta Semana
                            </CardTitle>
                            <Clock className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent className="px-5 pb-4">
                            <div className="text-2xl font-black text-slate-900">
                                {stats.semana}
                            </div>
                            <p className="text-[11px] font-semibold text-slate-400">
                                ${stats.ingresosSemana.toLocaleString()}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="flex flex-row items-center justify-between px-5 pt-4 pb-2">
                            <CardTitle className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                Este Mes
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent className="px-5 pb-4">
                            <div className="text-2xl font-black text-slate-900">
                                {stats.mes}
                            </div>
                            <p className="text-[11px] font-semibold text-slate-400">
                                ${stats.ingresosMes.toLocaleString()}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="flex flex-row items-center justify-between px-5 pt-4 pb-2">
                            <CardTitle className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                Ocupación
                            </CardTitle>
                            <BarChart3 className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent className="px-5 pb-4">
                            <div className="text-2xl font-black text-slate-900">
                                {stats.ocupacion}%
                            </div>
                            <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className="h-full rounded-full bg-amber-500"
                                    style={{
                                        width: `${Math.min(stats.ocupacion, 100)}%`,
                                    }}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Row */}
                <div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="rounded-2xl border-slate-200/70 lg:col-span-2">
                        <CardHeader className="px-5 py-4">
                            <CardTitle className="flex items-center gap-2 text-sm font-bold text-slate-700">
                                <BarChart3 className="h-4 w-4 text-emerald-500" />
                                Citas e Ingresos (Últimos 7 días)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-2 pt-0 pb-4">
                            <ResponsiveContainer width="100%" height={220}>
                                <BarChart data={appointmentsByDay}>
                                    <XAxis
                                        dataKey="date"
                                        tickFormatter={(d) =>
                                            format(new Date(d), 'EEE', {
                                                locale: es,
                                            })
                                        }
                                        tick={{ fontSize: 10, fill: '#94a3b8' }}
                                        axisLine={false}
                                        tickLine={false}
                                    />
                                    <YAxis
                                        yAxisId="left"
                                        allowDecimals={false}
                                        tick={{ fontSize: 10, fill: '#94a3b8' }}
                                        axisLine={false}
                                        tickLine={false}
                                    />
                                    <YAxis
                                        yAxisId="right"
                                        orientation="right"
                                        tick={{ fontSize: 10, fill: '#94a3b8' }}
                                        axisLine={false}
                                        tickLine={false}
                                        tickFormatter={(v) => `$${v}`}
                                    />
                                    <Tooltip
                                        labelFormatter={(d) =>
                                            format(
                                                new Date(d as string),
                                                "EEEE d 'de' MMMM",
                                                { locale: es },
                                            )
                                        }
                                        contentStyle={{
                                            borderRadius: 12,
                                            border: '1px solid #e2e8f0',
                                            fontSize: 12,
                                        }}
                                    />
                                    <Bar
                                        yAxisId="left"
                                        dataKey="total"
                                        radius={[4, 4, 0, 0]}
                                        fill="#10b981"
                                        maxBarSize={24}
                                        name="Citas"
                                    />
                                    <Bar
                                        yAxisId="right"
                                        dataKey="ingresos"
                                        radius={[4, 4, 0, 0]}
                                        fill="#22c55e"
                                        maxBarSize={24}
                                        name="Ingresos"
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="px-5 py-4">
                            <CardTitle className="flex items-center gap-2 text-sm font-bold text-slate-700">
                                <PieChartIcon className="h-4 w-4 text-emerald-500" />
                                Estado
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-2 pt-0 pb-4">
                            <ResponsiveContainer width="100%" height={180}>
                                <PieChart>
                                    <Pie
                                        data={statusDistribution.filter(
                                            (d) => d.value > 0,
                                        )}
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={45}
                                        outerRadius={70}
                                        dataKey="value"
                                        stroke="none"
                                    >
                                        {statusDistribution
                                            .filter((d) => d.value > 0)
                                            .map((e) => (
                                                <Cell
                                                    key={e.name}
                                                    fill={e.color}
                                                />
                                            ))}
                                    </Pie>
                                    <Tooltip
                                        formatter={(value, name) => [
                                            value,
                                            name,
                                        ]}
                                        contentStyle={{
                                            borderRadius: 12,
                                            border: '1px solid #e2e8f0',
                                            fontSize: 12,
                                        }}
                                    />
                                </PieChart>
                            </ResponsiveContainer>
                            <div className="mt-2 grid grid-cols-2 gap-1 px-3">
                                {statusDistribution
                                    .filter((d) => d.value > 0)
                                    .map((d) => (
                                        <div
                                            key={d.name}
                                            className="flex items-center gap-2 text-[11px] font-semibold text-slate-600"
                                        >
                                            <span
                                                className="h-2.5 w-2.5 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor: d.color,
                                                }}
                                            />
                                            {d.name}: {d.value}
                                        </div>
                                    ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Bottom Row: Today's Agenda + Top Services */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-2xl border border-slate-200/70 bg-white">
                        <div className="border-b border-slate-100 px-5 py-4">
                            <h2 className="flex items-center gap-2 text-sm font-bold text-slate-700">
                                <CalendarIcon className="h-4 w-4 text-emerald-500" />
                                Agenda del Día
                            </h2>
                        </div>
                        <div className="p-5">
                            {citasHoy.length === 0 ? (
                                <div className="py-8 text-center text-slate-400">
                                    <CheckCircle2 className="mx-auto mb-2 h-8 w-8 text-emerald-400" />
                                    <p className="text-sm font-semibold">
                                        Sin citas programadas
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {citasHoy.map((cita) => (
                                        <div
                                            key={cita.id}
                                            className="flex items-center gap-4 rounded-xl border border-slate-100 bg-slate-50 p-3"
                                        >
                                            <div className="shrink-0 text-center">
                                                <div className="text-lg leading-none font-black text-slate-900">
                                                    {format(
                                                        new Date(
                                                            cita.start_time,
                                                        ),
                                                        'HH:mm',
                                                    )}
                                                </div>
                                                <div className="mt-0.5 text-[10px] font-semibold text-slate-400">
                                                    {cita.producto?.duracion}{' '}
                                                    min
                                                </div>
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <h4 className="truncate text-sm font-bold text-slate-900">
                                                    {cita.client?.name}
                                                </h4>
                                                <p className="truncate text-xs text-slate-500">
                                                    {cita.producto?.nombre}
                                                </p>
                                            </div>
                                            <span
                                                className={`shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase ${cita.status === 'confirmada' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}
                                            >
                                                {cita.status}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200/70 bg-white">
                        <div className="border-b border-slate-100 px-5 py-4">
                            <h2 className="flex items-center gap-2 text-sm font-bold text-slate-700">
                                <BarChart3 className="h-4 w-4 text-violet-500" />
                                Top Servicios
                            </h2>
                        </div>
                        <div className="p-5">
                            {topServices.length === 0 ? (
                                <div className="py-8 text-center text-slate-400">
                                    <p className="text-sm font-semibold">
                                        Sin datos de reservas
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {topServices.map((s, i) => (
                                        <div
                                            key={s.name}
                                            className="flex items-center gap-3"
                                        >
                                            <span className="w-5 text-center text-xs font-black text-slate-400">
                                                {i + 1}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold text-slate-800">
                                                    {s.name}
                                                </p>
                                                <p className="text-[11px] text-slate-400">
                                                    {s.value} reservas
                                                </p>
                                            </div>
                                            <div className="h-2 w-20 overflow-hidden rounded-full bg-slate-100">
                                                <div
                                                    className="h-full rounded-full bg-violet-500"
                                                    style={{
                                                        width: `${Math.min((s.value / Math.max(...topServices.map((x) => x.value))) * 100, 100)}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <input
                ref={csvInputRef}
                type="file"
                accept=".csv"
                className="hidden"
                onChange={(e) => handleFileChange(e, 'csv')}
            />
            <input
                ref={excelInputRef}
                type="file"
                accept=".xlsx,.xls"
                className="hidden"
                onChange={(e) => handleFileChange(e, 'excel')}
            />
        </AppLayout>
    );
}
