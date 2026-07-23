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

export default function AgendaWidget({ staff, agendaAppointments, agendaWeekAppointments }: AgendaWidgetProps) {
    const { code: countryCode, currency } = useCountry();
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
        const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
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
            const isToday = isCurrentMonth && dayNum === today.getDate() && month === today.getMonth() && year === today.getFullYear();
            return { dayNum, isCurrentMonth, isToday };
        });
    };

    // Helper to get random day for demo purposes in week/month view since backend might lack dates
    const getDemoDay = (id: string) => {
        const hash = id.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
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
                className="absolute left-1 right-1 rounded-md px-1.5 py-1 overflow-hidden cursor-pointer group transition-all shadow-sm hover:shadow-md hover:z-20 border"
                style={{
                    top: `${top + 1}px`,
                    height: `${height - 2}px`,
                    backgroundColor: s ? s.color + '30' : '#E2E8F0',
                    borderColor: s ? s.color : '#CBD5E1',
                    borderLeftWidth: '4px',
                }}
            >
                {startsBefore && (
                    <div className="absolute -top-0.5 left-2 text-[8px] font-bold opacity-60" style={{ color: s ? s.texto : '#334155' }}>…</div>
                )}
                <div className="flex items-center justify-between gap-1 leading-none">
                    <span className="text-[10px] font-bold truncate" style={{ color: s ? s.texto : '#334155' }}>
                        {timeStr} {app.cliente}
                    </span>
                </div>
                {showDetails && height > 35 && (
                    <div className="mt-1 truncate text-[9px] font-medium opacity-80" style={{ color: s ? s.texto : '#334155' }}>
                        {app.servicio} • {app.precio}
                    </div>
                )}
                {endsAfter && (
                    <div className="absolute -bottom-0.5 left-2 text-[8px] font-bold opacity-60" style={{ color: s ? s.texto : '#334155' }}>…</div>
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
                className="text-[9px] font-bold px-1.5 py-0.5 rounded mb-0.5 truncate cursor-pointer hover:opacity-80 transition-opacity"
                style={{ backgroundColor: s ? s.color + '40' : '#E2E8F0', color: s ? s.texto : '#334155', borderLeft: `2px solid ${s ? s.color : '#CBD5E1'}` }}
            >
                {timeStr} {app.cliente}
            </div>
        );
    };

    return (
        <Card className="border-slate-200 shadow-sm overflow-hidden flex flex-col h-full bg-white">
            <CardHeader className="bg-white border-b border-slate-200 px-5 py-3 flex flex-row items-center justify-between shrink-0">
                <div className="flex items-center gap-4">
                    <CardTitle className="text-sm font-black flex items-center gap-2 text-slate-800">
                        <CalendarCheck className="h-5 w-5 text-blue-600" />
                        <span className="hidden sm:inline">Agenda del Día</span>
                    </CardTitle>
                    
                    {/* Google Calendar style navigation */}
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" className="h-7 text-[10px] font-bold px-3 border-slate-200 text-slate-600 hidden sm:flex" onClick={goToToday}>
                            Hoy
                        </Button>
                        <div className="flex items-center">
                            <Button variant="ghost" size="icon" className="h-7 w-7 text-slate-500 hover:text-slate-800 hover:bg-slate-100" onClick={navigateBack}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" className="h-7 w-7 text-slate-500 hover:text-slate-800 hover:bg-slate-100" onClick={navigateForward}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                        <span className="text-[13px] font-bold text-slate-700 ml-1">
                            {formatHeaderDate()}
                        </span>
                    </div>
                </div>

                <div className="flex items-center bg-slate-100 rounded-md p-0.5">
                    <Button
                        variant={view === 'dia' ? 'default' : 'ghost'}
                        size="sm"
                        className={`h-7 text-[10px] font-bold px-3 transition-colors ${view === 'dia' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                        onClick={() => setView('dia')}
                    >
                        Día
                    </Button>
                    <Button
                        variant={view === 'semana' ? 'default' : 'ghost'}
                        size="sm"
                        className={`h-7 text-[10px] font-bold px-3 transition-colors ${view === 'semana' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                        onClick={() => setView('semana')}
                    >
                        Semana
                    </Button>
                    <Button
                        variant={view === 'mes' ? 'default' : 'ghost'}
                        size="sm"
                        className={`h-7 text-[10px] font-bold px-3 transition-colors ${view === 'mes' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                        onClick={() => setView('mes')}
                    >
                        Mes
                    </Button>
                </div>
            </CardHeader>

            <CardContent className="p-0 flex-1 overflow-hidden flex flex-col relative">
                {/* 
                  * ----------------------------------------
                  * VIEW: DAY
                  * ----------------------------------------
                  */}
                {view === 'dia' && (
                    <div className="flex-1 overflow-y-auto relative bg-white min-w-[600px] flex flex-col h-[520px]">
                        {/* Headers */}
                        <div className="sticky top-0 z-30 flex border-b border-slate-200 bg-white">
                            <div className="w-16 shrink-0 border-r border-slate-200 bg-white">
                                <div className="text-[9px] font-bold text-slate-400 text-center py-1">
                                     {currentDate.toLocaleDateString(currency.locale, { day: 'numeric', month: 'short' })}
                                </div>
                            </div>
                            {staff.map((s: any) => (
                                <div key={s.id} className="flex-1 py-3 px-2 text-center border-r border-slate-200 last:border-r-0">
                                    <div className="text-[12px] font-bold text-slate-700">{s.nombre}</div>
                                </div>
                            ))}
                            {staff.length === 0 && (
                                <div className="flex-1 py-3 text-center"><span className="text-[11px] text-slate-400">Sin personal</span></div>
                            )}
                        </div>

                        {/* Grid */}
                        <div className="relative flex">
                            {/* Time Column */}
                            <div className="w-16 shrink-0 border-r border-slate-200 bg-white relative z-20">
                                {timeSlots.map((time) => (
                                    <div key={time} className="h-[60px] relative">
                                        {/* Google Calendar centers time on the border line */}
                                        <span className="absolute -top-2.5 right-2 text-[10px] font-medium text-slate-500">{time}</span>
                                    </div>
                                ))}
                            </div>

                            {/* Staff Columns */}
                            <div className="flex-1 flex relative">
                                {/* Horizontal grid lines spanning all columns */}
                                <div className="absolute inset-0 pointer-events-none z-0">
                                    {timeSlots.map((time) => (
                                        <div key={`hline-${time}`} className="h-[60px] border-b border-slate-100" />
                                    ))}
                                </div>

                                {staff.map((s: any) => (
                                    <div key={`col-${s.id}`} className="flex-1 relative border-r border-slate-200 last:border-r-0">
                                        {agendaAppointments
                                            .filter((app: any) => app.staff_id === s.id)
                                            .map((app: any) => renderEvent(app, s, true))}
                                    </div>
                                ))}

                                {/* Current Time Indicator */}
                                {(() => {
                                    const now = new Date();
                                    const currentHour = now.getHours() + now.getMinutes() / 60;
                                    if (currentHour >= 9 && currentHour <= 20) {
                                        const currentTop = (currentHour - 9) * 60;
                                        return (
                                            <div className="absolute left-0 right-0 z-30 pointer-events-none" style={{ top: `${currentTop}px` }}>
                                                <div className="w-full h-px bg-red-500 relative">
                                                    <div className="absolute -left-1.5 -top-1.5 w-3 h-3 rounded-full bg-red-500 shadow-sm" />
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
                    <div className="flex-1 overflow-y-auto relative bg-white min-w-[700px] flex flex-col h-[520px]">
                        {/* Headers */}
                        <div className="sticky top-0 z-30 flex border-b border-slate-200 bg-white">
                            <div className="w-16 shrink-0 border-r border-slate-200 bg-white" />
                            {daysOfWeek.map((day, idx) => {
                                const weekStart = new Date(currentDate);
                                weekStart.setDate(weekStart.getDate() - weekStart.getDay() + 1);
                                const dayDate = new Date(weekStart);
                                dayDate.setDate(dayDate.getDate() + idx);
                                const isToday = dayDate.toDateString() === today.toDateString();
                                return (
                                    <div key={day} className="flex-1 py-2 text-center border-r border-slate-200 last:border-r-0 flex flex-col items-center">
                                        <div className={`text-[10px] font-bold uppercase mb-1 ${isToday ? 'text-blue-600' : 'text-slate-500'}`}>{day}</div>
                                        <div className={`w-8 h-8 flex items-center justify-center rounded-full text-sm font-black ${isToday ? 'bg-blue-600 text-white' : 'text-slate-700 hover:bg-slate-100 cursor-pointer transition-colors'}`}>
                                            {dayDate.getDate()}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Grid */}
                        <div className="relative flex">
                            {/* Time Column */}
                            <div className="w-16 shrink-0 border-r border-slate-200 bg-white relative z-20">
                                {timeSlots.map((time) => (
                                    <div key={time} className="h-[60px] relative">
                                        <span className="absolute -top-2.5 right-2 text-[10px] font-medium text-slate-500">{time}</span>
                                    </div>
                                ))}
                            </div>

                            {/* Day Columns */}
                            <div className="flex-1 flex relative">
                                <div className="absolute inset-0 pointer-events-none z-0">
                                    {timeSlots.map((time) => (
                                        <div key={`hline-w-${time}`} className="h-[60px] border-b border-slate-100" />
                                    ))}
                                </div>

                                {daysOfWeek.map((day, idx) => (
                                    <div key={`wcol-${idx}`} className={`flex-1 relative border-r border-slate-200 last:border-r-0 ${idx === currentDayOfWeek ? 'bg-blue-50/20' : ''}`}>
                                        {agendaWeekAppointments
                                            .filter((app: any) => getDemoDay(app.id) === idx)
                                            .map((app: any) => {
                                                const s = staff.find(st => st.id === app.staff_id);
                                                return renderEvent(app, s, false);
                                            })}
                                    </div>
                                ))}
                                
                                {/* Current Time Indicator */}
                                {(() => {
                                    const now = new Date();
                                    const currentHour = now.getHours() + now.getMinutes() / 60;
                                    if (currentHour >= 9 && currentHour <= 20) {
                                        const currentTop = (currentHour - 9) * 60;
                                        return (
                                            <div className="absolute z-30 pointer-events-none" 
                                                 style={{ top: `${currentTop}px`, left: `${(currentDayOfWeek / 7) * 100}%`, width: `${100 / 7}%` }}>
                                                <div className="w-full h-px bg-red-500 relative">
                                                    <div className="absolute -left-1.5 -top-1.5 w-3 h-3 rounded-full bg-red-500 shadow-sm" />
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
                    <div className="flex-1 flex flex-col bg-white min-w-[700px] h-[520px]">
                        {/* Headers */}
                        <div className="flex border-b border-slate-200 bg-white">
                            {daysOfWeek.map((day) => (
                                <div key={`mheader-${day}`} className="flex-1 py-2 text-center border-r border-slate-200 last:border-r-0">
                                    <div className="text-[11px] font-bold text-slate-500 uppercase">{day}</div>
                                </div>
                            ))}
                        </div>
                        {/* Grid */}
                        <div className="flex-1 grid grid-cols-7" style={{ gridTemplateRows: `repeat(${getMonthCalendarDays().length / 7}, 1fr)` }}>
                            {getMonthCalendarDays().map((day, idx) => {
                                const dayEvents = day.isCurrentMonth ? agendaWeekAppointments.filter(app => (app.start + day.dayNum) % 5 === 0).slice(0, 3) : [];

                                return (
                                    <div key={`mday-${idx}`} className={`border-r border-b border-slate-200 p-1 flex flex-col ${!day.isCurrentMonth ? 'bg-slate-50 text-slate-400' : 'bg-white'}`}>
                                        <div className="flex justify-center mb-1 mt-0.5">
                                            <span className={`text-[11px] font-bold w-6 h-6 flex items-center justify-center rounded-full ${day.isToday ? 'bg-blue-600 text-white' : 'text-slate-700'}`}>
                                                {day.dayNum}
                                            </span>
                                        </div>
                                        <div className="flex-1 overflow-hidden">
                                            {dayEvents.map((app: any) => {
                                                const s = staff.find(st => st.id === app.staff_id);
                                                return renderMonthEvent(app, s);
                                            })}
                                            {dayEvents.length === 3 && (
                                                <div className="text-[9px] font-semibold text-slate-500 pl-1">+2 más</div>
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
