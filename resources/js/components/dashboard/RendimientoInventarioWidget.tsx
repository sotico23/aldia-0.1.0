import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Box,
    ClipboardList,
    Package,
    TrendingDown,
    TrendingUp,
    Warehouse,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/utils';

interface Movimiento {
    tipo: string;
    producto: string;
    cantidad: number;
    fecha: string;
    almacen: string;
}

interface ProductoCritico {
    nombre: string;
    stock: number;
    minimo: number;
}

interface InventarioData {
    totalProductos: number;
    activos: number;
    almacenes: number;
    stockCritico: number;
    valorInventario: number;
    movimientosRecientes: Movimiento[];
    productosCriticos: ProductoCritico[];
}

interface Props {
    data: InventarioData;
}

export default function RendimientoInventarioWidget({ data }: Props) {
    return (
        <Card className="overflow-hidden border-slate-200 shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 bg-white px-5 py-3">
                <CardTitle className="flex items-center gap-2 text-sm font-black text-slate-800">
                    <Warehouse className="h-4 w-4 text-orange-500" />
                    Rendimiento de Inventario
                </CardTitle>
                <span className="text-[10px] font-semibold text-slate-400">
                    Actualizado hoy
                </span>
            </CardHeader>

            <CardContent className="space-y-4 p-4">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Link
                        href="/productos"
                        className="block rounded-xl border border-orange-100 bg-orange-50 p-3 transition-colors hover:bg-orange-100"
                    >
                        <div className="mb-1 flex items-center gap-2">
                            <Package className="h-3.5 w-3.5 text-orange-500" />
                            <span className="text-[10px] font-semibold text-slate-500">
                                Productos
                            </span>
                        </div>
                        <p className="text-lg font-black text-slate-800">
                            {data.totalProductos}
                        </p>
                        <p className="text-[9px] font-medium text-slate-400">
                            {data.activos} activos
                        </p>
                    </Link>

                    <Link
                        href="/almacenes"
                        className="block rounded-xl border border-emerald-100 bg-emerald-50 p-3 transition-colors hover:bg-emerald-100"
                    >
                        <div className="mb-1 flex items-center gap-2">
                            <Warehouse className="h-3.5 w-3.5 text-emerald-500" />
                            <span className="text-[10px] font-semibold text-slate-500">
                                Almacenes
                            </span>
                        </div>
                        <p className="text-lg font-black text-slate-800">
                            {data.almacenes}
                        </p>
                        <p className="text-[9px] font-medium text-slate-400">
                            en operación
                        </p>
                    </Link>

                    <Link
                        href="/inventarios"
                        className="block rounded-xl border border-emerald-100 bg-emerald-50 p-3 transition-colors hover:bg-emerald-100"
                    >
                        <div className="mb-1 flex items-center gap-2">
                            <TrendingUp className="h-3.5 w-3.5 text-emerald-500" />
                            <span className="text-[10px] font-semibold text-slate-500">
                                Valor Stock
                            </span>
                        </div>
                        <p className="text-lg font-black text-slate-800">
                            {formatCurrency(data.valorInventario)}
                        </p>
                        <p className="text-[9px] font-medium text-slate-400">
                            costo total
                        </p>
                    </Link>

                    <Link
                        href="/inventarios"
                        className="block rounded-xl border border-rose-100 bg-rose-50 p-3 transition-colors hover:bg-rose-100"
                    >
                        <div className="mb-1 flex items-center gap-2">
                            <AlertTriangle className="h-3.5 w-3.5 text-rose-500" />
                            <span className="text-[10px] font-semibold text-slate-500">
                                Stock Crítico
                            </span>
                        </div>
                        <p className="text-lg font-black text-rose-600">
                            {data.stockCritico}
                        </p>
                        <p className="text-[9px] font-medium text-slate-400">
                            productos
                        </p>
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <div className="mb-2 flex items-center gap-1.5">
                            <ClipboardList className="h-3.5 w-3.5 text-slate-500" />
                            <span className="text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                Productos Críticos
                            </span>
                        </div>
                        <div className="space-y-1.5">
                            {data.productosCriticos.map((p) => (
                                <div
                                    key={p.nombre}
                                    className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                                >
                                    <div className="flex min-w-0 items-center gap-2">
                                        <TrendingDown className="h-3 w-3 shrink-0 text-rose-400" />
                                        <span className="truncate text-[11px] font-semibold text-slate-700">
                                            {p.nombre}
                                        </span>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <span className="text-[10px] font-bold text-rose-500">
                                            {p.stock}
                                        </span>
                                        <span className="text-[9px] text-slate-400">
                                            / {p.minimo} mín
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div>
                        <div className="mb-2 flex items-center gap-1.5">
                            <Box className="h-3.5 w-3.5 text-slate-500" />
                            <span className="text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                Movimientos Recientes
                            </span>
                        </div>
                        <div className="space-y-1.5">
                            {data.movimientosRecientes.map((m, idx) => (
                                <div
                                    key={idx}
                                    className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                                >
                                    <div className="flex min-w-0 items-center gap-2">
                                        {m.tipo === 'entrada' ? (
                                            <ArrowDown className="h-3 w-3 shrink-0 text-emerald-400" />
                                        ) : m.tipo === 'salida' ? (
                                            <ArrowUp className="h-3 w-3 shrink-0 text-rose-400" />
                                        ) : (
                                            <ArrowUp className="h-3 w-3 shrink-0 text-emerald-400" />
                                        )}
                                        <div className="min-w-0">
                                            <p className="truncate text-[11px] font-semibold text-slate-700">
                                                {m.producto}
                                            </p>
                                            <p className="text-[9px] text-slate-400">
                                                {m.fecha} · {m.almacen}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="ml-2 shrink-0 text-[11px] font-bold text-slate-600">
                                        {m.tipo === 'entrada'
                                            ? '+'
                                            : m.tipo === 'salida'
                                              ? '-'
                                              : ''}
                                        {m.cantidad}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
