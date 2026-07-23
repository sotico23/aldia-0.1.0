import { Activity, AlertCircle, CheckCircle2, ClipboardCheck, Clock, FlaskConical, Hammer, Layers, PackagePlus, Settings2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Orden {
    id: string;
    producto: string;
    cantidad: number;
    fecha: string;
    estado: string;
    progreso?: number;
}

interface ControlCalidadData {
    pendientes: number;
    aprobados: number;
    rechazados: number;
}

interface OrdenesData {
    pendientes: number;
    enProceso: number;
    completadas: number;
    canceladas: number;
}

interface ProduccionData {
    ordenes: OrdenesData;
    totalBoms: number;
    controlCalidad: ControlCalidadData;
    proximasOrdenes: Orden[];
    eficiencia: number;
}

interface Props {
    data: ProduccionData;
}

const estadoStyles: Record<string, { label: string; color: string; bg: string; icon: React.ElementType }> = {
    pendiente: { label: 'Pendiente', color: 'text-amber-600', bg: 'bg-amber-50 border-amber-200', icon: Clock },
    en_proceso: { label: 'En Proceso', color: 'text-blue-600', bg: 'bg-blue-50 border-blue-200', icon: Settings2 },
    completado: { label: 'Completado', color: 'text-emerald-600', bg: 'bg-emerald-50 border-emerald-200', icon: CheckCircle2 },
    cancelado: { label: 'Cancelado', color: 'text-rose-600', bg: 'bg-rose-50 border-rose-200', icon: AlertCircle },
};

export default function ProduccionMrpWidget({ data }: Props) {
    return (
        <Card className="border-slate-200 shadow-sm overflow-hidden">
            <CardHeader className="bg-white border-b border-slate-100 px-5 py-3 flex flex-row items-center justify-between">
                <CardTitle className="text-sm font-black flex items-center gap-2 text-slate-800">
                    <Hammer className="h-4 w-4 text-yellow-500" />
                    Producción MRP
                </CardTitle>
                <div className="flex items-center gap-1.5 bg-yellow-50 border border-yellow-200 rounded-full px-2.5 py-1">
                    <Activity className="h-3 w-3 text-yellow-600" />
                    <span className="text-[9px] font-bold text-yellow-700">{data.eficiencia}% eficiencia</span>
                </div>
            </CardHeader>

            <CardContent className="p-4 space-y-4">
                <div className="grid grid-cols-4 gap-2">
                    <div className="bg-amber-50 rounded-xl p-3 border border-amber-100 text-center">
                        <p className="text-lg font-black text-amber-600">{data.ordenes.pendientes}</p>
                        <p className="text-[9px] font-semibold text-slate-500">Pendientes</p>
                    </div>
                    <div className="bg-blue-50 rounded-xl p-3 border border-blue-100 text-center">
                        <p className="text-lg font-black text-blue-600">{data.ordenes.enProceso}</p>
                        <p className="text-[9px] font-semibold text-slate-500">En Proceso</p>
                    </div>
                    <div className="bg-emerald-50 rounded-xl p-3 border border-emerald-100 text-center">
                        <p className="text-lg font-black text-emerald-600">{data.ordenes.completadas}</p>
                        <p className="text-[9px] font-semibold text-slate-500">Completadas</p>
                    </div>
                    <div className="bg-rose-50 rounded-xl p-3 border border-rose-100 text-center">
                        <p className="text-lg font-black text-rose-600">{data.ordenes.canceladas}</p>
                        <p className="text-[9px] font-semibold text-slate-500">Canceladas</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <div className="flex items-center gap-1.5 mb-2">
                            <PackagePlus className="h-3.5 w-3.5 text-slate-500" />
                            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Próximas Órdenes</span>
                        </div>
                        <div className="space-y-1.5">
                            {data.proximasOrdenes.length === 0 && (
                                <p className="text-[11px] text-slate-400 text-center py-4 font-medium">Sin órdenes pendientes</p>
                            )}
                            {data.proximasOrdenes.map((op) => {
                                const style = estadoStyles[op.estado] || estadoStyles.pendiente;
                                const Icon = style.icon;
                                return (
                                    <div key={op.id} className={`flex items-center justify-between rounded-lg border px-3 py-2 ${style.bg}`}>
                                        <div className="flex items-center gap-2 min-w-0">
                                            <Icon className={`h-3 w-3 shrink-0 ${style.color}`} />
                                            <div className="min-w-0">
                                                <p className="text-[11px] font-semibold text-slate-700 truncate">{op.producto}</p>
                                                <p className="text-[9px] text-slate-500">{op.id} · {op.cantidad}uds · {op.fecha}</p>
                                            </div>
                                        </div>
                                        {op.progreso !== undefined && (
                                            <div className="flex items-center gap-1.5 shrink-0 ml-2">
                                                <div className="w-12 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                                    <div className="h-full bg-blue-500 rounded-full" style={{ width: `${op.progreso}%` }} />
                                                </div>
                                                <span className="text-[9px] font-bold text-blue-600">{op.progreso}%</span>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center gap-1.5 mb-2">
                            <ClipboardCheck className="h-3.5 w-3.5 text-slate-500" />
                            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Recursos</span>
                        </div>
                        <div className="space-y-2">
                            <div className="bg-slate-50 rounded-xl p-3 border border-slate-100">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Layers className="h-4 w-4 text-yellow-500" />
                                        <div>
                                            <p className="text-[11px] font-bold text-slate-700">Listas de Materiales (BOM)</p>
                                            <p className="text-[9px] text-slate-400">Recetas de fabricación activas</p>
                                        </div>
                                    </div>
                                    <span className="text-lg font-black text-yellow-600">{data.totalBoms}</span>
                                </div>
                            </div>

                            <div className="bg-slate-50 rounded-xl p-3 border border-slate-100">
                                <div className="flex items-center gap-2 mb-2">
                                    <FlaskConical className="h-3.5 w-3.5 text-slate-500" />
                                    <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Control de Calidad</span>
                                </div>
                                <div className="flex gap-2">
                                    <div className="flex-1 bg-amber-50 rounded-lg px-2.5 py-2 border border-amber-100 text-center">
                                        <p className="text-sm font-black text-amber-600">{data.controlCalidad.pendientes}</p>
                                        <p className="text-[8px] font-semibold text-slate-500">Pendientes</p>
                                    </div>
                                    <div className="flex-1 bg-emerald-50 rounded-lg px-2.5 py-2 border border-emerald-100 text-center">
                                        <p className="text-sm font-black text-emerald-600">{data.controlCalidad.aprobados}</p>
                                        <p className="text-[8px] font-semibold text-slate-500">Aprobados</p>
                                    </div>
                                    <div className="flex-1 bg-rose-50 rounded-lg px-2.5 py-2 border border-rose-100 text-center">
                                        <p className="text-sm font-black text-rose-600">{data.controlCalidad.rechazados}</p>
                                        <p className="text-[8px] font-semibold text-slate-500">Rechazados</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
