import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowDown, ArrowUp, Box, ClipboardList, Package, TrendingDown, TrendingUp, Warehouse } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';
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
        <Card className="border-slate-200 shadow-sm overflow-hidden">
            <CardHeader className="bg-white border-b border-slate-100 px-5 py-3 flex flex-row items-center justify-between">
                <CardTitle className="text-sm font-black flex items-center gap-2 text-slate-800">
                    <Warehouse className="h-4 w-4 text-orange-500" />
                    Rendimiento de Inventario
                </CardTitle>
                <span className="text-[10px] font-semibold text-slate-400">Actualizado hoy</span>
            </CardHeader>

            <CardContent className="p-4 space-y-4">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <Link href="/productos" className="block bg-orange-50 rounded-xl p-3 border border-orange-100 hover:bg-orange-100 transition-colors">
                        <div className="flex items-center gap-2 mb-1">
                            <Package className="h-3.5 w-3.5 text-orange-500" />
                            <span className="text-[10px] font-semibold text-slate-500">Productos</span>
                        </div>
                        <p className="text-lg font-black text-slate-800">{data.totalProductos}</p>
                        <p className="text-[9px] text-slate-400 font-medium">{data.activos} activos</p>
                    </Link>

                    <Link href="/almacenes" className="block bg-emerald-50 rounded-xl p-3 border border-emerald-100 hover:bg-emerald-100 transition-colors">
                        <div className="flex items-center gap-2 mb-1">
                            <Warehouse className="h-3.5 w-3.5 text-emerald-500" />
                            <span className="text-[10px] font-semibold text-slate-500">Almacenes</span>
                        </div>
                        <p className="text-lg font-black text-slate-800">{data.almacenes}</p>
                        <p className="text-[9px] text-slate-400 font-medium">en operación</p>
                    </Link>

                    <Link href="/inventarios" className="block bg-blue-50 rounded-xl p-3 border border-blue-100 hover:bg-blue-100 transition-colors">
                        <div className="flex items-center gap-2 mb-1">
                            <TrendingUp className="h-3.5 w-3.5 text-blue-500" />
                            <span className="text-[10px] font-semibold text-slate-500">Valor Stock</span>
                        </div>
                        <p className="text-lg font-black text-slate-800">{formatCurrency(data.valorInventario)}</p>
                        <p className="text-[9px] text-slate-400 font-medium">costo total</p>
                    </Link>

                    <Link href="/inventarios" className="block bg-rose-50 rounded-xl p-3 border border-rose-100 hover:bg-rose-100 transition-colors">
                        <div className="flex items-center gap-2 mb-1">
                            <AlertTriangle className="h-3.5 w-3.5 text-rose-500" />
                            <span className="text-[10px] font-semibold text-slate-500">Stock Crítico</span>
                        </div>
                        <p className="text-lg font-black text-rose-600">{data.stockCritico}</p>
                        <p className="text-[9px] text-slate-400 font-medium">productos</p>
                    </Link>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <div className="flex items-center gap-1.5 mb-2">
                            <ClipboardList className="h-3.5 w-3.5 text-slate-500" />
                            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Productos Críticos</span>
                        </div>
                        <div className="space-y-1.5">
                            {data.productosCriticos.map((p) => (
                                <div key={p.nombre} className="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <TrendingDown className="h-3 w-3 shrink-0 text-rose-400" />
                                        <span className="text-[11px] font-semibold text-slate-700 truncate">{p.nombre}</span>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <span className="text-[10px] font-bold text-rose-500">{p.stock}</span>
                                        <span className="text-[9px] text-slate-400">/ {p.minimo} mín</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center gap-1.5 mb-2">
                            <Box className="h-3.5 w-3.5 text-slate-500" />
                            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Movimientos Recientes</span>
                        </div>
                        <div className="space-y-1.5">
                            {data.movimientosRecientes.map((m, idx) => (
                                <div key={idx} className="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2">
                                    <div className="flex items-center gap-2 min-w-0">
                                        {m.tipo === 'entrada' ? (
                                            <ArrowDown className="h-3 w-3 shrink-0 text-emerald-400" />
                                        ) : m.tipo === 'salida' ? (
                                            <ArrowUp className="h-3 w-3 shrink-0 text-rose-400" />
                                        ) : (
                                            <ArrowUp className="h-3 w-3 shrink-0 text-blue-400" />
                                        )}
                                        <div className="min-w-0">
                                            <p className="text-[11px] font-semibold text-slate-700 truncate">{m.producto}</p>
                                            <p className="text-[9px] text-slate-400">{m.fecha} · {m.almacen}</p>
                                        </div>
                                    </div>
                                    <span className="text-[11px] font-bold text-slate-600 shrink-0 ml-2">
                                        {m.tipo === 'entrada' ? '+' : m.tipo === 'salida' ? '-' : ''}{m.cantidad}
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
