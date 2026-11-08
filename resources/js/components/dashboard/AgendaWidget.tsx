import { CalendarCheck, ChevronLeft, ChevronRight } from 'lucide-react';
import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';

interface AgendaWidgetProps {
    staff: any[];
    agendaAppointments: any[];
    agendaWeekAppointments: any[];
}

export default function AgendaWidget({
    staff,
    agendaAppointments,
    agendaWeekAppointments,
}: AgendaWidgetProps) {
    const { currency } = useCountry();
    const [view, setView] = useState<'dia' | 'semana' | 'mes'>('mes');
    const [currentDate, setCurrentDate] = useState(new Date());
    const today = new Date();

    // Generate time slots 09:00 to 20:00
    const timeSlots: string[] = [];
    for (let i = 9; i <= 20; i++) {
        timeSlots.push(`${i.toString().padStart(2, '0')}:00`);
    }

    const daysOfWeek = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    const currentDayOfWeek = today.getDay() === 0 ? 6 : today.getDay() - 1; // 0-6 (Mon-Sun)

    const navigateBack = () => {
        const next = new Date(currentDate);
        if (view === 'dia') next.setDate(next.getDate() - 1);
        else if (view === 'semana') next.setDate(next.getDate() - 7);
        else next.setMonth(next.getMonth() - 1);
        setCurrentDate(next);
    };

    const navigateForward = () => {
        const next = new Date(currentDate);
        if (view === 'dia') next.setDate(next.getDate() + 1);
        else if (view === 'semana') next.setDate(next.getDate() + 7);
        else next.setMonth(next.getMonth() + 1);
        setCurrentDate(next);
    };

    const goToToday = () => setCurrentDate(new Date());

    const formatHeaderDate = () => {
        const months = [
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
        ];
        if (view === 'dia') {
            return `${currentDate.getDate()} de ${months[currentDate.getMonth()]} de ${currentDate.getFullYear()}`;
        }
        if (view === 'semana') {
            const weekStart = new Date(currentDate);
            weekStart.setDate(weekStart.getDate() - weekStart.getDay() + 1);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekEnd.getDate() + 6);
            return `${weekStart.getDate()} – ${weekEnd.getDate()} de ${months[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
        }
        return `${months[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
    };

    const getMonthCalendarDays = () => {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const startOffset = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;

        return Array.from({ length: totalCells }).map((_, idx) => {
            let dayNum: number, isCurrentMonth: boolean;
            if (idx < startOffset) {
                dayNum = daysInPrevMonth - startOffset + idx + 1;
                isCurrentMonth = false;
            } else if (idx >= startOffset + daysInMonth) {
                dayNum = idx - startOffset - daysInMonth + 1;
                isCurrentMonth = false;
            } else {
                dayNum = idx - startOffset + 1;
                isCurrentMonth = true;
            }
            const isToday =
                isCurrentMonth &&
                dayNum === today.getDate() &&
                month === today.getMonth() &&
                year === today.getFullYear();
            return { dayNum, isCurrentMonth, isToday };
        });
    };

    // Helper to get random day for demo purposes in week/month view since backend might lack dates
    const getDemoDay = (id: string) => {
        const hash = id
            .split('')
            .reduce((acc, char) => acc + char.charCodeAt(0), 0);
        return hash % 7;
    };

    const renderEvent = (app: any, s: any, showDetails: boolean = true) => {
        const startPx = (app.start - 9) * 60;
        const endPx = startPx + Math.max(app.duration, 0.5) * 60;
        const clampedStart = Math.max(startPx, 0);
        const clampedEnd = Math.min(endPx, 12 * 60);
        const top = clampedStart;
        const height = Math.max(clampedEnd - clampedStart, 24);
        const startsBefore = startPx < 0;
        const endsAfter = endPx > 12 * 60;

        const hour = Math.floor(app.start);
        const minute = Math.round((app.start - hour) * 60);
        const timeStr = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;

        return (
            <div
                key={app.id}
                className="group absolute right-1 left-1 cursor-pointer overflow-hidden rounded-md border px-1.5 py-1 shadow-sm transition-all hover:z-20 hover:shadow-md"
                style={{
                    top: `${top + 1}px`,
                    height: `${height - 2}px`,
                    backgroundColor: s ? s.color + '30' : '#E2E8F0',
                    borderColor: s ? s.color : '#CBD5E1',
                    borderLeftWidth: '4px',
                }}
            >
                {startsBefore && (
                    <div
                        className="absolute -top-0.5 left-2 text-[8px] font-bold opacity-60"
                        style={{ color: s ? s.texto : '#334155' }}
                    >
                        …
                    </div>
                )}
                <div className="flex items-center justify-between gap-1 leading-none">
                    <span
                        className="truncate text-[10px] font-bold"
                        style={{ color: s ? s.texto : '#334155' }}
                    >
                        {timeStr} {app.cliente}
                    </span>
                </div>
                {showDetails && height > 35 && (
                    <div
                        className="mt-1 truncate text-[9px] font-medium opacity-80"
                        style={{ color: s ? s.texto : '#334155' }}
                    >
                        {app.servicio} • {app.precio}
                    </div>
                )}
                {endsAfter && (
                    <div
                        className="absolute -bottom-0.5 left-2 text-[8px] font-bold opacity-60"
                        style={{ color: s ? s.texto : '#334155' }}
                    >
                        …
                    </div>
                )}
            </div>
        );
    };

    const renderMonthEvent = (app: any, s: any) => {
        const hour = Math.floor(app.start);
        const minute = Math.round((app.start - hour) * 60);
        const timeStr = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
        return (
            <div
                key={app.id}
                className="mb-0.5 cursor-pointer truncate rounded px-1.5 py-0.5 text-[9px] font-bold transition-opacity hover:opacity-80"
                style={{
                    backgroundColor: s ? s.color + '40' : '#E2E8F0',
                    color: s ? s.texto : '#334155',
                    borderLeft: `2px solid ${s ? s.color : '#CBD5E1'}`,
                }}
            >
                {timeStr} {app.cliente}
            </div>
        );
    };

    return (
        <Card className="flex h-full flex-col overflow-hidden border-slate-200 bg-white shadow-sm">
            <CardHeader className="flex shrink-0 flex-row items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                <div className="flex items-center gap-4">
                    <CardTitle className="flex items-center gap-2 text-sm font-black text-slate-800">
                        <CalendarCheck className="h-5 w-5 text-emerald-600" />
                        <span className="hidden sm:inline">Agenda del Día</span>
                    </CardTitle>

                    {/* Google Calendar style navigation */}
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            className="hidden h-7 border-slate-200 px-3 text-[10px] font-bold text-slate-600 sm:flex"
                            onClick={goToToday}
                        >
                            Hoy
                        </Button>
                        <div className="flex items-center">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                onClick={navigateBack}
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                onClick={navigateForward}
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                        <span className="ml-1 text-[13px] font-bold text-slate-700">
                            {formatHeaderDate()}
                        </span>
                    </div>
                </div>

                <div className="flex items-center rounded-md bg-slate-100 p-0.5">
                    <Button
                        variant={view === 'dia' ? 'default' : 'ghost'}
                        size="sm"
                        className={`h-7 px-3 text-[10px] font-bold transition-colors ${view === 'dia' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                        onClick={() => setView('dia')}
                    >
                        Día
                    </Button>
                    <Button
                        variant={view === 'semana' ? 'default' : 'ghost'}
                        size="sm"
                        className={`h-7 px-3 text-[10px] font-bold transition-colors ${view === 'semana' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                        onClick={() => setView('semana')}
                    >
                        Semana
                    </Button>
                    <Button
                        variant={view === 'mes' ? 'default' : 'ghost'}
                        size="sm"
                        className={`h-7 px-3 text-[10px] font-bold transition-colors ${view === 'mes' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                        onClick={() => setView('mes')}
                    >
                        Mes
                    </Button>
                </div>
            </CardHeader>

            <CardContent className="relative flex flex-1 flex-col overflow-hidden p-0">
                {/*
                 * ----------------------------------------
                 * VIEW: DAY
                 * ----------------------------------------
                 */}
                {view === 'dia' && (
                    <div className="relative flex h-[520px] min-w-[600px] flex-1 flex-col overflow-y-auto bg-white">
                        {/* Headers */}
                        <div className="sticky top-0 z-30 flex border-b border-slate-200 bg-white">
                            <div className="w-16 shrink-0 border-r border-slate-200 bg-white">
                                <div className="py-1 text-center text-[9px] font-bold text-slate-400">
                                    {currentDate.toLocaleDateString(
                                        currency.locale,
                                        { day: 'numeric', month: 'short' },
                                    )}
                                </div>
                            </div>
                            {staff.map((s: any) => (
                                <div
                                    key={s.id}
                                    className="flex-1 border-r border-slate-200 px-2 py-3 text-center last:border-r-0"
                                >
                                    <div className="text-[12px] font-bold text-slate-700">
                                        {s.nombre}
                                    </div>
                                </div>
                            ))}
                            {staff.length === 0 && (
                                <div className="flex-1 py-3 text-center">
                                    <span className="text-[11px] text-slate-400">
                                        Sin personal
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Grid */}
                        <div className="relative flex">
                            {/* Time Column */}
                            <div className="relative z-20 w-16 shrink-0 border-r border-slate-200 bg-white">
                                {timeSlots.map((time) => (
                                    <div
                                        key={time}
                                        className="relative h-[60px]"
                                    >
                                        {/* Google Calendar centers time on the border line */}
                                        <span className="absolute -top-2.5 right-2 text-[10px] font-medium text-slate-500">
                                            {time}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            {/* Staff Columns */}
                            <div className="relative flex flex-1">
                                {/* Horizontal grid lines spanning all columns */}
                                <div className="pointer-events-none absolute inset-0 z-0">
                                    {timeSlots.map((time) => (
                                        <div
                                            key={`hline-${time}`}
                                            className="h-[60px] border-b border-slate-100"
                                        />
                                    ))}
                                </div>

                                {staff.map((s: any) => (
                                    <div
                                        key={`col-${s.id}`}
                                        className="relative flex-1 border-r border-slate-200 last:border-r-0"
                                    >
                                        {agendaAppointments
                                            .filter(
                                                (app: any) =>
                                                    app.staff_id === s.id,
                                            )
                                            .map((app: any) =>
                                                renderEvent(app, s, true),
                                            )}
                                    </div>
                                ))}

                                {/* Current Time Indicator */}
                                {(() => {
                                    const now = new Date();
                                    const currentHour =
                                        now.getHours() + now.getMinutes() / 60;
                                    if (currentHour >= 9 && currentHour <= 20) {
                                        const currentTop =
                                            (currentHour - 9) * 60;
                                        return (
                                            <div
                                                className="pointer-events-none absolute right-0 left-0 z-30"
                                                style={{
                                                    top: `${currentTop}px`,
                                                }}
                                            >
                                                <div className="relative h-px w-full bg-red-500">
                                                    <div className="absolute -top-1.5 -left-1.5 h-3 w-3 rounded-full bg-red-500 shadow-sm" />
                                                </div>
                                            </div>
                                        );
                                    }
                                    return null;
                                })()}
                            </div>
                        </div>
                    </div>
                )}

                {/*
                 * ----------------------------------------
                 * VIEW: WEEK
                 * ----------------------------------------
                 */}
                {view === 'semana' && (
                    <div className="relative flex h-[520px] min-w-[700px] flex-1 flex-col overflow-y-auto bg-white">
                        {/* Headers */}
                        <div className="sticky top-0 z-30 flex border-b border-slate-200 bg-white">
                            <div className="w-16 shrink-0 border-r border-slate-200 bg-white" />
                            {daysOfWeek.map((day, idx) => {
                                const weekStart = new Date(currentDate);
                                weekStart.setDate(
                                    weekStart.getDate() -
                                        weekStart.getDay() +
                                        1,
                                );
                                const dayDate = new Date(weekStart);
                                dayDate.setDate(dayDate.getDate() + idx);
                                const isToday =
                                    dayDate.toDateString() ===
                                    today.toDateString();
                                return (
                                    <div
                                        key={day}
                                        className="flex flex-1 flex-col items-center border-r border-slate-200 py-2 text-center last:border-r-0"
                                    >
                                        <div
                                            className={`mb-1 text-[10px] font-bold uppercase ${isToday ? 'text-emerald-600' : 'text-slate-500'}`}
                                        >
                                            {day}
                                        </div>
                                        <div
                                            className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-black ${isToday ? 'bg-emerald-600 text-white' : 'cursor-pointer text-slate-700 transition-colors hover:bg-slate-100'}`}
                                        >
                                            {dayDate.getDate()}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Grid */}
                        <div className="relative flex">
                            {/* Time Column */}
                            <div className="relative z-20 w-16 shrink-0 border-r border-slate-200 bg-white">
                                {timeSlots.map((time) => (
                                    <div
                                        key={time}
                                        className="relative h-[60px]"
                                    >
                                        <span className="absolute -top-2.5 right-2 text-[10px] font-medium text-slate-500">
                                            {time}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            {/* Day Columns */}
                            <div className="relative flex flex-1">
                                <div className="pointer-events-none absolute inset-0 z-0">
                                    {timeSlots.map((time) => (
                                        <div
                                            key={`hline-w-${time}`}
                                            className="h-[60px] border-b border-slate-100"
                                        />
                                    ))}
                                </div>

                                {daysOfWeek.map((day, idx) => (
                                    <div
                                        key={`wcol-${idx}`}
                                        className={`relative flex-1 border-r border-slate-200 last:border-r-0 ${idx === currentDayOfWeek ? 'bg-emerald-50/20' : ''}`}
                                    >
                                        {agendaWeekAppointments
                                            .filter(
                                                (app: any) =>
                                                    getDemoDay(app.id) === idx,
                                            )
                                            .map((app: any) => {
                                                const s = staff.find(
                                                    (st) =>
                                                        st.id === app.staff_id,
                                                );
                                                return renderEvent(
                                                    app,
                                                    s,
                                                    false,
                                                );
                                            })}
                                    </div>
                                ))}

                                {/* Current Time Indicator */}
                                {(() => {
                                    const now = new Date();
                                    const currentHour =
                                        now.getHours() + now.getMinutes() / 60;
                                    if (currentHour >= 9 && currentHour <= 20) {
                                        const currentTop =
                                            (currentHour - 9) * 60;
                                        return (
                                            <div
                                                className="pointer-events-none absolute z-30"
                                                style={{
                                                    top: `${currentTop}px`,
                                                    left: `${(currentDayOfWeek / 7) * 100}%`,
                                                    width: `${100 / 7}%`,
                                                }}
                                            >
                                                <div className="relative h-px w-full bg-red-500">
                                                    <div className="absolute -top-1.5 -left-1.5 h-3 w-3 rounded-full bg-red-500 shadow-sm" />
                                                </div>
                                            </div>
                                        );
                                    }
                                    return null;
                                })()}
                            </div>
                        </div>
                    </div>
                )}

                {/*
                 * ----------------------------------------
                 * VIEW: MONTH
                 * ----------------------------------------
                 */}
                {view === 'mes' && (
                    <div className="flex h-[520px] min-w-[700px] flex-1 flex-col bg-white">
                        {/* Headers */}
                        <div className="flex border-b border-slate-200 bg-white">
                            {daysOfWeek.map((day) => (
                                <div
                                    key={`mheader-${day}`}
                                    className="flex-1 border-r border-slate-200 py-2 text-center last:border-r-0"
                                >
                                    <div className="text-[11px] font-bold text-slate-500 uppercase">
                                        {day}
                                    </div>
                                </div>
                            ))}
                        </div>
                        {/* Grid */}
                        <div
                            className="grid flex-1 grid-cols-7"
                            style={{
                                gridTemplateRows: `repeat(${getMonthCalendarDays().length / 7}, 1fr)`,
                            }}
                        >
                            {getMonthCalendarDays().map((day, idx) => {
                                const dayEvents = day.isCurrentMonth
                                    ? agendaWeekAppointments
                                          .filter(
                                              (app) =>
                                                  (app.start + day.dayNum) %
                                                      5 ===
                                                  0,
                                          )
                                          .slice(0, 3)
                                    : [];

                                return (
                                    <div
                                        key={`mday-${idx}`}
                                        className={`flex flex-col border-r border-b border-slate-200 p-1 ${!day.isCurrentMonth ? 'bg-slate-50 text-slate-400' : 'bg-white'}`}
                                    >
                                        <div className="mt-0.5 mb-1 flex justify-center">
                                            <span
                                                className={`flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold ${day.isToday ? 'bg-emerald-600 text-white' : 'text-slate-700'}`}
                                            >
                                                {day.dayNum}
                                            </span>
                                        </div>
                                        <div className="flex-1 overflow-hidden">
                                            {dayEvents.map((app: any) => {
                                                const s = staff.find(
                                                    (st) =>
                                                        st.id === app.staff_id,
                                                );
                                                return renderMonthEvent(app, s);
                                            })}
                                            {dayEvents.length === 3 && (
                                                <div className="pl-1 text-[9px] font-semibold text-slate-500">
                                                    +2 más
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
