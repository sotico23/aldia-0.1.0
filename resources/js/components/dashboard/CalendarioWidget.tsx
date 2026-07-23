import { Link } from '@inertiajs/react';
import { format, isSameDay, startOfMonth, endOfMonth, isWithinInterval } from 'date-fns';
import { es } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, CalendarDays } from 'lucide-react';
import { useState, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';

interface CitaResumida {
    id: number;
    start_time: string;
    end_time: string;
    status: string;
    client: { id: number; name: string } | null;
    producto: { id: number; nombre: string } | null;
}

interface CalendarioWidgetProps {
    citas: CitaResumida[];
}

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

export default function CalendarioWidget({ citas }: CalendarioWidgetProps) {
    const [currentMonth, setCurrentMonth] = useState(() => startOfMonth(new Date()));
    const [selectedDay, setSelectedDay] = useState<Date | null>(null);

    const monthStart = startOfMonth(currentMonth);
    const monthEnd = endOfMonth(currentMonth);

    const citasEnMes = useMemo(
        () => citas.filter(c => {
            const d = new Date(c.start_time);
            return isWithinInterval(d, { start: monthStart, end: monthEnd });
        }),
        [citas, monthStart, monthEnd]
    );

    const diasConCitas = useMemo(() => {
        const map = new Map<string, CitaResumida[]>();
        for (const c of citasEnMes) {
            const key = format(new Date(c.start_time), 'yyyy-MM-dd');
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
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const days: (number | null)[] = [];
        for (let i = 0; i < firstDay; i++) days.push(null);
        for (let d = 1; d <= daysInMonth; d++) days.push(d);
        return days;
    }, [currentMonth]);

    const prevMonth = () => setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1));
    const nextMonth = () => setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1));

    const today = new Date();

    return (
        <div className="flex h-full flex-col p-3">
            <div className="flex items-center justify-between mb-2">
                <h3 className="text-xs font-bold flex items-center gap-1.5">
                    <CalendarDays className="h-3.5 w-3.5 text-primary" />
                    Calendario de Citas
                </h3>
                <Link
                    href="/appointments/calendar"
                    className="text-[10px] font-semibold text-primary hover:underline"
                >
                    Ver completo &rarr;
                </Link>
            </div>

            <div className="flex items-center justify-between mb-2">
                <button
                    type="button"
                    onClick={prevMonth}
                    className="rounded-lg p-1 text-muted-foreground hover:bg-accent/60 transition-colors"
                >
                    <ChevronLeft className="h-4 w-4" />
                </button>
                <span className="text-xs font-semibold capitalize">
                    {format(currentMonth, 'MMMM yyyy', { locale: es })}
                </span>
                <button
                    type="button"
                    onClick={nextMonth}
                    className="rounded-lg p-1 text-muted-foreground hover:bg-accent/60 transition-colors"
                >
                    <ChevronRight className="h-4 w-4" />
                </button>
            </div>

            <div className="grid grid-cols-7 gap-0">
                {['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'].map(d => (
                    <div key={d} className="text-center text-[9px] font-semibold text-muted-foreground/60 py-1">
                        {d}
                    </div>
                ))}
                {daysInMonth.map((day, i) => {
                    if (day === null) return <div key={`empty-${i}`} />;
                    const date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
                    const key = format(date, 'yyyy-MM-dd');
                    const dayCitas = diasConCitas.get(key);
                    const isToday = isSameDay(date, today);
                    const isSelected = selectedDay && isSameDay(date, selectedDay);

                    return (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setSelectedDay(date)}
                            className={`relative flex flex-col items-center justify-center rounded-lg py-1 text-[11px] font-medium transition-all duration-200
                                ${isSelected
                                    ? 'bg-primary/15 text-primary ring-1 ring-primary/30'
                                    : isToday
                                        ? 'text-foreground font-bold'
                                        : 'text-muted-foreground hover:bg-accent/40'
                                }
                            `}
                        >
                            <span>{day}</span>
                            {dayCitas && (
                                <span className="flex gap-0.5 mt-0.5">
                                    {dayCitas.slice(0, 3).map(c => (
                                        <span
                                            key={c.id}
                                            className={`h-1 w-1 rounded-full ${
                                                c.status === 'completada' ? 'bg-emerald-500'
                                                : c.status === 'cancelada' ? 'bg-rose-400'
                                                : c.status === 'confirmada' ? 'bg-blue-500'
                                                : c.status === 'en_curso' ? 'bg-violet-500'
                                                : 'bg-amber-500'
                                            }`}
                                        />
                                    ))}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {selectedDay && (
                <div className="mt-2 flex-1 min-h-0 overflow-y-auto">
                    <div className="flex items-center justify-between mb-1.5">
                        <span className="text-[10px] font-bold capitalize text-muted-foreground">
                            {format(selectedDay, "d 'de' MMMM", { locale: es })}
                        </span>
                        <span className="text-[10px] text-muted-foreground/60">
                            {citasDelDia.length} cita{citasDelDia.length !== 1 ? 's' : ''}
                        </span>
                    </div>
                    {citasDelDia.length === 0 ? (
                        <p className="text-[10px] text-muted-foreground/50 italic py-2 text-center">
                            Sin citas este día
                        </p>
                    ) : (
                        <div className="flex flex-col gap-1">
                            {citasDelDia.map(c => (
                                <Link
                                    key={c.id}
                                    href={`/appointments/${c.id}`}
                                    className="flex items-center gap-2 rounded-lg border border-border/40 bg-card/50 px-2 py-1.5 transition-all duration-200 hover:bg-accent/40 hover:border-border/70"
                                >
                                    <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${
                                        c.status === 'completada' ? 'bg-emerald-500'
                                        : c.status === 'cancelada' ? 'bg-rose-400'
                                        : c.status === 'confirmada' ? 'bg-blue-500'
                                        : c.status === 'en_curso' ? 'bg-violet-500'
                                        : 'bg-amber-500'
                                    }`} />
                                    <div className="flex-1 min-w-0">
                                        <span className="text-[10px] font-medium leading-tight block truncate">
                                            {c.client?.name ?? 'Sin cliente'}
                                        </span>
                                        <span className="text-[9px] text-muted-foreground/70 block truncate">
                                            {format(new Date(c.start_time), 'HH:mm')}
                                            {c.producto?.nombre ? ` · ${c.producto.nombre}` : ''}
                                        </span>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className={`text-[8px] px-1 py-0 leading-tight ${statusStyles[c.status] ?? ''}`}
                                    >
                                        {statusLabels[c.status] ?? c.status}
                                    </Badge>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {!selectedDay && citasEnMes.length > 0 && (
                <div className="mt-2 text-center text-[9px] text-muted-foreground/50">
                    {citasEnMes.length} cita{citasEnMes.length !== 1 ? 's' : ''} este mes
                    &nbsp;— selecciona un día
                </div>
            )}
        </div>
    );
}
