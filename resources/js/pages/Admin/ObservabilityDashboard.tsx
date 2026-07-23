import { Head } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock,
    ListChecks,
    XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

interface HealthMetrics {
    pending_jobs: number;
    failed_jobs: number;
    today_executions: number;
    failed_executions: number;
    average_execution_time_ms: number | null;
    success_rate_percent: number;
    stale_reserved_jobs: number;
}

interface LastExecution {
    status: string;
    executed_at: string | null;
    execution_time_ms: number | null;
    error_message: string | null;
    uuid: string;
}

const MetricCard = ({ title, value, unit, icon, color }: {
    title: string; value: string | number; unit?: string; icon: React.ReactNode; color: string;
}) => (
    <Card>
        <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">{title}</CardTitle>
            <div className={`h-8 w-8 rounded-full flex items-center justify-center ${color}`}>
                {icon}
            </div>
        </CardHeader>
        <CardContent>
            <div className="text-2xl font-bold">
                {value}
                {unit && <span className="text-sm font-normal text-muted-foreground ml-1">{unit}</span>}
            </div>
        </CardContent>
    </Card>
);

export default function ObservabilityDashboard({
    health,
    metrics,
    lastExecutions,
    queueWaitTime,
}: {
    health: { status: string; timestamp: string; issues: Array<{ severity: string; message: string }> };
    metrics: HealthMetrics;
    lastExecutions: Record<string, LastExecution>;
    queueWaitTime: number | null;
}) {
    const breadcrumbs = [
        { title: 'Admin', href: '#' },
        { title: 'Observabilidad', href: '/admin/system/health' },
    ];

    const statusColor = (status: string) => {
        switch (status) {
            case 'healthy': return 'text-green-600 bg-green-50 dark:bg-green-950/30 dark:text-green-400';
            case 'degraded': return 'text-yellow-600 bg-yellow-50 dark:bg-yellow-950/30 dark:text-yellow-400';
            case 'unhealthy': return 'text-red-600 bg-red-50 dark:bg-red-950/30 dark:text-red-400';
            default: return 'text-gray-600 bg-gray-50';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Observabilidad del Sistema" />

            <div className="mx-auto max-w-7xl px-4 py-8 space-y-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-black tracking-tight">Observabilidad del Sistema</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Métricas en vivo del sistema de automatizaciones
                        </p>
                    </div>
                    <Badge className={`text-xs px-4 py-2 ${statusColor(health.status)}`}>
                        <Activity className="h-3 w-3 mr-1" />
                        {health.status.toUpperCase()}
                    </Badge>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <MetricCard
                        title="Ejecuciones Hoy"
                        value={metrics.today_executions}
                        icon={<ListChecks className="h-4 w-4 text-blue-600" />}
                        color="bg-blue-100 dark:bg-blue-900/30"
                    />
                    <MetricCard
                        title="Fallidas Hoy"
                        value={metrics.failed_executions}
                        icon={<XCircle className="h-4 w-4 text-red-600" />}
                        color="bg-red-100 dark:bg-red-900/30"
                    />
                    <MetricCard
                        title="Tasa de Éxito"
                        value={metrics.success_rate_percent}
                        unit="%"
                        icon={<CheckCircle2 className="h-4 w-4 text-green-600" />}
                        color="bg-green-100 dark:bg-green-900/30"
                    />
                    <MetricCard
                        title="Tiempo Promedio"
                        value={metrics.average_execution_time_ms !== null ? Math.round(metrics.average_execution_time_ms) : 'N/A'}
                        unit="ms"
                        icon={<Clock className="h-4 w-4 text-purple-600" />}
                        color="bg-purple-100 dark:bg-purple-900/30"
                    />
                    <MetricCard
                        title="Workers Estancados"
                        value={metrics.stale_reserved_jobs}
                        icon={<AlertTriangle className="h-4 w-4 text-red-600" />}
                        color="bg-red-100 dark:bg-red-900/30"
                    />
                    <MetricCard
                        title="Jobs Pendientes"
                        value={metrics.pending_jobs}
                        icon={<Activity className="h-4 w-4 text-amber-600" />}
                        color="bg-amber-100 dark:bg-amber-900/30"
                    />
                    <MetricCard
                        title="Jobs Fallidos"
                        value={metrics.failed_jobs}
                        icon={<AlertTriangle className="h-4 w-4 text-orange-600" />}
                        color="bg-orange-100 dark:bg-orange-900/30"
                    />
                    <MetricCard
                        title="Espera en Cola"
                        value={queueWaitTime !== null ? Math.round(queueWaitTime) : 'N/A'}
                        unit="s"
                        icon={<Clock className="h-4 w-4 text-cyan-600" />}
                        color="bg-cyan-100 dark:bg-cyan-900/30"
                    />
                </div>

                {health.issues.length > 0 && (
                    <Card className="border-red-200 dark:border-red-900/50">
                        <CardHeader>
                            <CardTitle className="text-red-600 flex items-center gap-2">
                                <AlertTriangle className="h-5 w-5" />
                                Problemas Detectados
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {health.issues.map((issue, i) => (
                                    <li key={i} className="flex items-start gap-2 text-sm">
                                        <span className={issue.severity === 'error' ? 'text-red-500' : 'text-yellow-500'}>
                                            {issue.severity === 'error' ? '🔴' : '⚠️'}
                                        </span>
                                        {issue.message}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Última Ejecución por Workflow</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {Object.keys(lastExecutions).length === 0 ? (
                            <p className="text-sm text-muted-foreground">Sin ejecuciones registradas.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b dark:border-slate-800">
                                            <th className="text-left py-3 px-4 font-medium">Workflow</th>
                                            <th className="text-left py-3 px-4 font-medium">Estado</th>
                                            <th className="text-right py-3 px-4 font-medium">Tiempo (ms)</th>
                                            <th className="text-right py-3 px-4 font-medium">Ejecutado</th>
                                            <th className="text-left py-3 px-4 font-medium">UUID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {Object.entries(lastExecutions).map(([workflow, exec]) => (
                                            <tr key={workflow} className="border-b dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                                <td className="py-3 px-4 font-medium">{workflow}</td>
                                                <td className="py-3 px-4">
                                                    <Badge variant={exec.status === 'success' || exec.status === 'sent_to_n8n' ? 'default' : 'destructive'}>
                                                        {exec.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono">{exec.execution_time_ms ?? '-'}</td>
                                                <td className="py-3 px-4 text-right text-muted-foreground">
                                                    {exec.executed_at ? new Date(exec.executed_at).toLocaleString() : '-'}
                                                </td>
                                                <td className="py-3 px-4 font-mono text-xs text-muted-foreground">{exec.uuid ?? '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
