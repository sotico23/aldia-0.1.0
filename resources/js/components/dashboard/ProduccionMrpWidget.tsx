import {
    Activity,
    AlertCircle,
    CheckCircle2,
    ClipboardCheck,
    Clock,
    FlaskConical,
    Hammer,
    Layers,
    PackagePlus,
    Settings2,
} from 'lucide-react';
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

const estadoStyles: Record<
    string,
    { label: string; color: string; bg: string; icon: React.ElementType }
> = {
    pendiente: {
        label: 'Pendiente',
        color: 'text-amber-600',
        bg: 'bg-amber-50 border-amber-200',
        icon: Clock,
    },
    en_proceso: {
        label: 'En Proceso',
        color: 'text-emerald-600',
        bg: 'bg-emerald-50 border-emerald-200',
        icon: Settings2,
    },
    completado: {
        label: 'Completado',
        color: 'text-emerald-600',
        bg: 'bg-emerald-50 border-emerald-200',
        icon: CheckCircle2,
    },
    cancelado: {
        label: 'Cancelado',
        color: 'text-rose-600',
        bg: 'bg-rose-50 border-rose-200',
        icon: AlertCircle,
    },
};

export default function ProduccionMrpWidget({ data }: Props) {
    return (
        <Card className="overflow-hidden border-slate-200 shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 bg-white px-5 py-3">
                <CardTitle className="flex items-center gap-2 text-sm font-black text-slate-800">
                    <Hammer className="h-4 w-4 text-yellow-500" />
                    Producción MRP
                </CardTitle>
                <div className="flex items-center gap-1.5 rounded-full border border-yellow-200 bg-yellow-50 px-2.5 py-1">
                    <Activity className="h-3 w-3 text-yellow-600" />
                    <span className="text-[9px] font-bold text-yellow-700">
                        {data.eficiencia}% eficiencia
                    </span>
                </div>
            </CardHeader>

            <CardContent className="space-y-4 p-4">
                <div className="grid grid-cols-4 gap-2">
                    <div className="rounded-xl border border-amber-100 bg-amber-50 p-3 text-center">
                        <p className="text-lg font-black text-amber-600">
                            {data.ordenes.pendientes}
                        </p>
                        <p className="text-[9px] font-semibold text-slate-500">
                            Pendientes
                        </p>
                    </div>
                    <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-center">
                        <p className="text-lg font-black text-emerald-600">
                            {data.ordenes.enProceso}
                        </p>
                        <p className="text-[9px] font-semibold text-slate-500">
                            En Proceso
                        </p>
                    </div>
                    <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-center">
                        <p className="text-lg font-black text-emerald-600">
                            {data.ordenes.completadas}
                        </p>
                        <p className="text-[9px] font-semibold text-slate-500">
                            Completadas
                        </p>
                    </div>
                    <div className="rounded-xl border border-rose-100 bg-rose-50 p-3 text-center">
                        <p className="text-lg font-black text-rose-600">
                            {data.ordenes.canceladas}
                        </p>
                        <p className="text-[9px] font-semibold text-slate-500">
                            Canceladas
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <div className="mb-2 flex items-center gap-1.5">
                            <PackagePlus className="h-3.5 w-3.5 text-slate-500" />
                            <span className="text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                Próximas Órdenes
                            </span>
                        </div>
                        <div className="space-y-1.5">
                            {data.proximasOrdenes.length === 0 && (
                                <p className="py-4 text-center text-[11px] font-medium text-slate-400">
                                    Sin órdenes pendientes
                                </p>
                            )}
                            {data.proximasOrdenes.map((op) => {
                                const style =
                                    estadoStyles[op.estado] ||
                                    estadoStyles.pendiente;
                                const Icon = style.icon;
                                return (
                                    <div
                                        key={op.id}
                                        className={`flex items-center justify-between rounded-lg border px-3 py-2 ${style.bg}`}
                                    >
                                        <div className="flex min-w-0 items-center gap-2">
                                            <Icon
                                                className={`h-3 w-3 shrink-0 ${style.color}`}
                                            />
                                            <div className="min-w-0">
                                                <p className="truncate text-[11px] font-semibold text-slate-700">
                                                    {op.producto}
                                                </p>
                                                <p className="text-[9px] text-slate-500">
                                                    {op.id} · {op.cantidad}uds ·{' '}
                                                    {op.fecha}
                                                </p>
                                            </div>
                                        </div>
                                        {op.progreso !== undefined && (
                                            <div className="ml-2 flex shrink-0 items-center gap-1.5">
                                                <div className="h-1.5 w-12 overflow-hidden rounded-full bg-slate-200">
                                                    <div
                                                        className="h-full rounded-full bg-emerald-500"
                                                        style={{
                                                            width: `${op.progreso}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="text-[9px] font-bold text-emerald-600">
                                                    {op.progreso}%
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div>
                        <div className="mb-2 flex items-center gap-1.5">
                            <ClipboardCheck className="h-3.5 w-3.5 text-slate-500" />
                            <span className="text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                Recursos
                            </span>
                        </div>
                        <div className="space-y-2">
                            <div className="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Layers className="h-4 w-4 text-yellow-500" />
                                        <div>
                                            <p className="text-[11px] font-bold text-slate-700">
                                                Listas de Materiales (BOM)
                                            </p>
                                            <p className="text-[9px] text-slate-400">
                                                Recetas de fabricación activas
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-lg font-black text-yellow-600">
                                        {data.totalBoms}
                                    </span>
                                </div>
                            </div>

                            <div className="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <div className="mb-2 flex items-center gap-2">
                                    <FlaskConical className="h-3.5 w-3.5 text-slate-500" />
                                    <span className="text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                        Control de Calidad
                                    </span>
                                </div>
                                <div className="flex gap-2">
                                    <div className="flex-1 rounded-lg border border-amber-100 bg-amber-50 px-2.5 py-2 text-center">
                                        <p className="text-sm font-black text-amber-600">
                                            {data.controlCalidad.pendientes}
                                        </p>
                                        <p className="text-[8px] font-semibold text-slate-500">
                                            Pendientes
                                        </p>
                                    </div>
                                    <div className="flex-1 rounded-lg border border-emerald-100 bg-emerald-50 px-2.5 py-2 text-center">
                                        <p className="text-sm font-black text-emerald-600">
                                            {data.controlCalidad.aprobados}
                                        </p>
                                        <p className="text-[8px] font-semibold text-slate-500">
                                            Aprobados
                                        </p>
                                    </div>
                                    <div className="flex-1 rounded-lg border border-rose-100 bg-rose-50 px-2.5 py-2 text-center">
                                        <p className="text-sm font-black text-rose-600">
                                            {data.controlCalidad.rechazados}
                                        </p>
                                        <p className="text-[8px] font-semibold text-slate-500">
                                            Rechazados
                                        </p>
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
