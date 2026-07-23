import { Head, Link } from '@inertiajs/react';
import { History, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

interface Execution {
    id: number;
    workflow: string;
    status: string;
    triggered_by: string;
    error_message: string | null;
    execution_time_ms: number | null;
    executed_at: string | null;
    created_at: string;
}

export default function AutomationHistory({ executions }: { executions: any }) {
    const breadcrumbs = [
        { title: 'Canales', href: '/canales' },
        { title: 'Historial', href: '/automatizaciones/historial' },
    ];

    const statusBadge = (status: string) => {
        const colors: Record<string, string> = {
            success: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            sent_to_n8n: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
            partial_error: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            dispatched: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
        };

        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors[status] || 'bg-gray-100 text-gray-800'}`}>
                {status}
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Historial de Automatizaciones" />

            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-8 p-8 rounded-[2rem] bg-purple-50 border border-purple-100 flex items-start gap-6 dark:bg-purple-950/20 dark:border-purple-900/30">
                    <div className="h-14 w-14 rounded-2xl bg-purple-100 flex items-center justify-center shrink-0 dark:bg-purple-900/50">
                        <History className="h-7 w-7 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-purple-900 dark:text-purple-100 mb-2">
                            Historial de Automatizaciones
                        </h1>
                        <p className="text-sm font-medium text-purple-700/80 dark:text-purple-300/80 max-w-2xl">
                            Revisa el estado de las ejecuciones automáticas de reportes y mensajes.
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Ejecuciones</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {executions.data.length === 0 ? (
                            <p className="text-center text-muted-foreground py-8">
                                No hay ejecuciones registradas. Las automatizaciones aparecerán aquí cuando se ejecuten.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b dark:border-slate-800">
                                            <th className="text-left py-3 px-4 font-medium">ID</th>
                                            <th className="text-left py-3 px-4 font-medium">Workflow</th>
                                            <th className="text-left py-3 px-4 font-medium">Estado</th>
                                            <th className="text-left py-3 px-4 font-medium">Origen</th>
                                            <th className="text-right py-3 px-4 font-medium">Tiempo (ms)</th>
                                            <th className="text-right py-3 px-4 font-medium">Ejecutado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {executions.data.map((exec: Execution) => (
                                            <tr key={exec.id} className="border-b dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                                <td className="py-3 px-4 font-mono text-xs">{exec.id}</td>
                                                <td className="py-3 px-4">{exec.workflow}</td>
                                                <td className="py-3 px-4">{statusBadge(exec.status)}</td>
                                                <td className="py-3 px-4 capitalize">{exec.triggered_by}</td>
                                                <td className="py-3 px-4 text-right font-mono">{exec.execution_time_ms ?? '-'}</td>
                                                <td className="py-3 px-4 text-right text-muted-foreground">{exec.executed_at}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {executions.links && executions.total > executions.per_page && (
                            <div className="flex items-center justify-between pt-6">
                                <div className="text-sm text-muted-foreground">
                                    Mostrando {executions.from}-{executions.to} de {executions.total}
                                </div>
                                <div className="flex gap-2">
                                    {executions.prev_page_url && (
                                        <Link href={executions.prev_page_url}>
                                            <Button variant="outline" size="sm">
                                                <ChevronLeft className="h-4 w-4 mr-1" /> Anterior
                                            </Button>
                                        </Link>
                                    )}
                                    {executions.next_page_url && (
                                        <Link href={executions.next_page_url}>
                                            <Button variant="outline" size="sm">
                                                Siguiente <ChevronRight className="h-4 w-4 ml-1" />
                                            </Button>
                                        </Link>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
