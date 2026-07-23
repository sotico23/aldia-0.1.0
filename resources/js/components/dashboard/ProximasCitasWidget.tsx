import { Link } from '@inertiajs/react';
import { Calendar, Clock, ChevronRight } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';

interface Cita {
    id: number;
    start_time: string;
    end_time: string;
    status: string;
    client: { id: number; name: string } | null;
    provider: { id: number; name: string } | null;
    producto: { id: number; nombre: string } | null;
}

interface ProximasCitasWidgetProps {
    citas: Cita[];
}

const statusColors: Record<string, string> = {
    pendiente: 'bg-amber-500/10 text-amber-600',
    confirmada: 'bg-blue-500/10 text-blue-600',
    en_curso: 'bg-emerald-500/10 text-emerald-600',
    completada: 'bg-green-500/10 text-green-600',
    cancelada: 'bg-rose-500/10 text-rose-600',
    no_show: 'bg-gray-500/10 text-gray-600',
};

const statusLabels: Record<string, string> = {
    pendiente: 'Pendiente',
    confirmada: 'Confirmada',
    en_curso: 'En Curso',
    completada: 'Completada',
    cancelada: 'Cancelada',
    no_show: 'No Asistió',
};

export default function ProximasCitasWidget({ citas }: ProximasCitasWidgetProps) {
    const { code: countryCode, currency } = useCountry();
    return (
        <Card className="h-full border-0 shadow-none">
            <CardHeader className="flex flex-row items-center gap-2 px-4 pt-3 pb-0">
                <Calendar className="h-4 w-4 text-primary" />
                <CardTitle className="text-xs font-bold">Próximas Citas</CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-3 pt-2">
                {citas.length === 0 ? (
                    <p className="py-6 text-center text-xs text-muted-foreground">
                        No tienes citas próximas
                    </p>
                ) : (
                    <ul className="divide-y divide-border/50">
                        {citas.map(cita => {
                            const start = new Date(cita.start_time);
                            const today = new Date();
                            const isToday = start.toDateString() === today.toDateString();
                            const timeStr = start.toLocaleTimeString(currency.locale, {
                                hour: '2-digit',
                                minute: '2-digit',
                            });
                            const dateStr = isToday
                                ? 'Hoy'
                                : start.toLocaleDateString(currency.locale, {
                                    weekday: 'short',
                                    day: 'numeric',
                                    month: 'short',
                                });

                            return (
                                <li key={cita.id}>
                                    <Link
                                        href={`/appointments/${cita.id}`}
                                        className="flex items-center gap-3 py-2 transition-colors hover:bg-muted/50 -mx-2 px-2 rounded-lg group"
                                    >
                                        <div className="flex shrink-0 flex-col items-center">
                                            <span className={`text-[10px] font-bold ${isToday ? 'text-primary' : 'text-muted-foreground'}`}>
                                                {dateStr}
                                            </span>
                                            <span className="flex items-center gap-0.5 text-[9px] text-muted-foreground/60">
                                                <Clock className="h-2.5 w-2.5" />
                                                {timeStr}
                                            </span>
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-xs font-medium">
                                                {cita.client?.name ?? 'Sin cliente'}
                                            </p>
                                            {cita.producto && (
                                                <p className="truncate text-[10px] text-muted-foreground">
                                                    {cita.producto.nombre}
                                                </p>
                                            )}
                                        </div>

                                        <Badge
                                            variant="outline"
                                            className={`shrink-0 text-[9px] px-1.5 py-0 ${statusColors[cita.status] ?? 'bg-gray-500/10 text-gray-600'}`}
                                        >
                                            {statusLabels[cita.status] ?? cita.status}
                                        </Badge>

                                        <ChevronRight className="h-3.5 w-3.5 shrink-0 text-muted-foreground/40 group-hover:text-muted-foreground transition-colors" />
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
                {citas.length > 0 && (
                    <div className="mt-1 text-center">
                        <Link
                            href="/appointments/calendar"
                            className="text-[10px] font-medium text-primary hover:text-primary/80 transition-colors"
                        >
                            Ver calendario →
                        </Link>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
