import { Head } from '@inertiajs/react';
import { CalendarCheck, Clock, TrendingDown, TrendingUp } from 'lucide-react';
import AgendaWidget from '@/components/dashboard/AgendaWidget';
import type { DashboardWidget } from '@/components/dashboard/DashboardLayout';
import DashboardLayout from '@/components/dashboard/DashboardLayout';
import ProduccionMrpWidget from '@/components/dashboard/ProduccionMrpWidget';
import QuickAccessWidget from '@/components/dashboard/QuickAccessWidget';
import RendimientoInventarioWidget from '@/components/dashboard/RendimientoInventarioWidget';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';

const iconMap: Record<string, React.ElementType> = {
    'trending-up': TrendingUp,
    'trending-down': TrendingDown,
    clock: Clock,
    'calendar-check': CalendarCheck,
};

export default function Dashboard({
    savedLayout,
    inventarioData,
    produccionData,
    metricsSummary = [],
    staff = [],
    agendaAppointments = [],
    agendaWeekAppointments = [],
}: {
    savedLayout?: any;
    inventarioData?: any;
    produccionData?: any;
    metricsSummary?: any[];
    staff?: any[];
    agendaAppointments?: any[];
    agendaWeekAppointments?: any[];
}) {
    const widgets: DashboardWidget[] = [
        {
            key: 'metrics_summary',
            title: 'Indicadores Clave',
            defaultW: 12,
            component: (
                <div className="grid h-full grid-cols-1 gap-4 pb-2 sm:grid-cols-2 xl:grid-cols-4">
                    {metricsSummary.map((kpi: any) => {
                        const Icon = iconMap[kpi.icon_name] || TrendingUp;
                        const value =
                            kpi.format === 'currency'
                                ? formatCurrency(kpi.value)
                                : kpi.value;
                        return (
                            <Card
                                key={kpi.id}
                                className="group relative h-full overflow-hidden border border-slate-100/80 bg-white/95 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)]"
                            >
                                <div className="pointer-events-none absolute -top-4 -right-4 opacity-[0.03] transition-opacity duration-500 group-hover:opacity-[0.08]">
                                    <Icon
                                        className="h-32 w-32 -rotate-12 transform transition-transform duration-700 ease-out group-hover:rotate-0"
                                        style={{ color: kpi.borde_izquierdo }}
                                    />
                                </div>
                                <div
                                    className="absolute top-0 bottom-0 left-0 w-1.5 transition-all duration-300 group-hover:w-2.5"
                                    style={{
                                        backgroundColor: kpi.borde_izquierdo,
                                    }}
                                />
                                <CardContent className="relative z-10 flex h-full items-center justify-between p-4 pl-6">
                                    <div className="flex flex-col gap-0.5">
                                        <p className="text-[10px] font-bold tracking-wider text-slate-500 uppercase transition-colors group-hover:text-slate-700">
                                            {kpi.label}
                                        </p>
                                        <h3
                                            className={`text-2xl font-black tracking-tight ${kpi.color_valor} origin-left transition-transform duration-300 group-hover:scale-105`}
                                        >
                                            {value}
                                        </h3>
                                        <p
                                            className="mt-1 line-clamp-1 max-w-[150px] text-[10px] font-semibold text-slate-400"
                                            title={kpi.sub_label}
                                        >
                                            {kpi.sub_label}
                                        </p>
                                    </div>
                                    <div
                                        className={`rounded-2xl p-3 ${kpi.bgIcon} shadow-sm transition-transform duration-300 group-hover:scale-110`}
                                    >
                                        <Icon
                                            className="h-5 w-5"
                                            style={{
                                                color: kpi.borde_izquierdo,
                                            }}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            ),
        },
        {
            key: 'agenda_daily',
            title: 'Agenda del Día',
            defaultW: 12,
            component: (
                <AgendaWidget
                    staff={staff}
                    agendaAppointments={agendaAppointments}
                    agendaWeekAppointments={agendaWeekAppointments}
                />
            ),
        },
        {
            key: 'quick_access',
            title: 'Accesos Rápidos',
            defaultW: 12,
            component: <QuickAccessWidget />,
        },
        {
            key: 'rendimiento_inventario',
            title: 'Rendimiento de Inventario',
            defaultW: 12,
            component: (
                <RendimientoInventarioWidget
                    data={
                        inventarioData ?? {
                            totalProductos: 0,
                            activos: 0,
                            almacenes: 0,
                            stockCritico: 0,
                            valorInventario: 0,
                            movimientosRecientes: [],
                            productosCriticos: [],
                        }
                    }
                />
            ),
        },
        {
            key: 'produccion_mrp',
            title: 'Producción MRP',
            defaultW: 12,
            component: (
                <ProduccionMrpWidget
                    data={
                        produccionData ?? {
                            ordenes: {
                                pendientes: 0,
                                enProceso: 0,
                                completadas: 0,
                                canceladas: 0,
                            },
                            totalBoms: 0,
                            controlCalidad: {
                                pendientes: 0,
                                aprobados: 0,
                                rechazados: 0,
                            },
                            proximasOrdenes: [],
                            eficiencia: 100,
                        }
                    }
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title="Dashboard - Red-Cliente" />

            <div className="flex min-h-[calc(100vh-4rem)] flex-col gap-4 bg-slate-50 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-black text-slate-800">
                        Panel General
                    </h1>
                    <p className="text-sm font-medium text-slate-500">
                        Vista Dinámica
                    </p>
                </div>

                <DashboardLayout widgets={widgets} savedLayout={savedLayout} />
            </div>
        </AppLayout>
    );
}
