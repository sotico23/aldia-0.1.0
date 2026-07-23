import { Head, useForm, router } from '@inertiajs/react';
import {
    Save,
    Calculator,
    Package,
    Building2,
    AlertTriangle,
    CheckCircle,
    ArrowLeft,
    RotateCcw
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Producto {
    id: number;
    producto_id: number;
    producto_nombre: string;
    producto_codigo: string;
    almacen_id: number;
    almacen_nombre: string;
    stock_actual: number;
    stock_minimo: number;
}

interface Almacen {
    id: number;
    nombre: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventario', href: '/inventarios' },
    { title: 'Cierre de Inventario', href: '/inventario-cierre' },
    { title: 'Nuevo Cierre', href: '/inventario-cierre/create' },
];

export default function CierreCreate({
    almacenes,
    productos,
    ventas_esperadas,
    envases_pendientes,
    selected_almacen,
}: {
    almacenes: Almacen[];
    productos: Producto[];
    ventas_esperadas: Record<string, any>;
    envases_pendientes?: { id: number; producto: string; cantidad: number; observaciones: string | null }[];
    selected_almacen?: string;
}) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('inventario.inventarios.viewAny');
    const [selectedAlmacen, setSelectedAlmacen] = useState(
        selected_almacen && selected_almacen !== 'all' ? selected_almacen : '',
    );
    const [marcarAuditado, setMarcarAuditado] = useState(false);
    const [observaciones, setObservaciones] = useState('');
    const [stockCierreInput, setStockCierreInput] = useState('');
    const [isDetalleModalOpen, setIsDetalleModalOpen] = useState(false);
    const [detalleAlmacen, setDetalleAlmacen] = useState<{
        id: number;
        nombre: string;
    } | null>(null);

    const { post, processing, transform } = useForm({
        almacen_id: '',
        type: 'BODEGA',
        total_products: 0,
        opening_stock: 0,
        closing_stock: 0,
        expected_stock: 0,
        difference: 0,
        observations: '',
        marcar_auditado: false,
    });

    const handleAlmacenChange = (value: string) => {
        const almacenId = value === 'all' ? '' : value;
        setSelectedAlmacen(almacenId);
        router.get(
            '/inventario-cierre/create',
            { almacen_id: value === 'all' ? '' : value },
            { replace: true },
        );
    };

    const totales = useMemo(() => {
        const totalProducts = productos.length;
        const stockActual = Math.round(
            productos.reduce((sum, p) => sum + p.stock_actual, 0),
        );
        const totalVendido = Math.round(
            productos.reduce((sum, p) => {
                const venta = ventas_esperadas[p.producto_id];
                return (
                    sum +
                    (venta?.total_vendido ? parseFloat(venta.total_vendido) : 0)
                );
            }, 0),
        );
        // Stock Esperado = Stock Actual en BD (ya refleja todos los movimientos del día)
        const expectedStock = stockActual;
        const closingStockInput = stockCierreInput
            ? parseInt(stockCierreInput, 10)
            : stockActual;
        const difference = closingStockInput - expectedStock;

        return {
            totalProducts,
            openingStock: stockActual,
            closingStock: closingStockInput,
            expectedStock,
            totalVendido,
            difference,
        };
    }, [productos, ventas_esperadas, stockCierreInput]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform(() => ({
            almacen_id: selectedAlmacen || null,
            type: selectedAlmacen ? 'BODEGA' : 'GENERAL',
            total_products: totales.totalProducts,
            opening_stock: totales.openingStock,
            closing_stock: totales.closingStock,
            expected_stock: totales.expectedStock,
            difference: totales.difference,
            observations: observaciones,
            marcar_auditado: marcarAuditado,
        }));

        post('/inventario-cierre', {
            onSuccess: () => {
                router.get('/inventario-cierre');
            },
        });
    };

    if (!canAccess) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Cierre de Inventario" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => router.get('/inventario-cierre')}
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                        <div>
                            <h1 className="text-3xl font-black tracking-tight text-foreground">
                                Nuevo Cierre de Inventario
                            </h1>
                            <p className="text-sm font-medium text-muted-foreground">
                                Registra el cierre diario de inventario
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card className="border-none shadow-xl">
                            <CardHeader className="border-b pb-4">
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="h-5 w-5 text-primary" />
                                    Seleccionar Bodega
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-4">
                                <Select
                                    value={selectedAlmacen || 'all'}
                                    onValueChange={handleAlmacenChange}
                                >
                                    <SelectTrigger className="h-11">
                                        <SelectValue placeholder="Selecciona una bodega o Todas (General)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todas las Bodegas (General)
                                        </SelectItem>
                                        {almacenes.map((a) => (
                                            <SelectItem
                                                key={a.id}
                                                value={a.id.toString()}
                                            >
                                                {a.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </CardContent>
                        </Card>

                        <Card className="border-none shadow-xl">
                            <CardHeader className="border-b pb-4">
                                <CardTitle className="flex items-center gap-2">
                                    <Package className="h-5 w-5 text-primary" />
                                    Detalle de Inventario
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b bg-muted/5 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                <th className="px-4 py-3 text-left">
                                                    Producto
                                                </th>
                                                <th className="px-4 py-3 text-left">
                                                    Bodega
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Stock Actual
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Vendido Hoy
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Stock Esperado
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-muted/50">
                                            {productos.length === 0 ? (
                                                <tr>
                                                    <td
                                                        colSpan={5}
                                                        className="py-8 text-center text-muted-foreground"
                                                    >
                                                        {selectedAlmacen
                                                            ? 'No hay productos en esta bodega'
                                                            : 'Selecciona una bodega para ver los productos'}
                                                    </td>
                                                </tr>
                                            ) : (
                                                productos.map((p) => {
                                                    const venta =
                                                        ventas_esperadas[
                                                            p.producto_id
                                                        ];
                                                    const vendido = venta
                                                        ? Math.round(
                                                              parseFloat(
                                                                  venta.total_vendido,
                                                              ),
                                                          )
                                                        : 0;
                                                    const stockActual =
                                                        Math.round(
                                                            p.stock_actual,
                                                        );
                                                    const esperado =
                                                        stockActual - vendido;

                                                    return (
                                                        <tr
                                                            key={p.id}
                                                            className="hover:bg-muted/30"
                                                        >
                                                            <td className="px-4 py-3">
                                                                <div>
                                                                    <p className="font-medium">
                                                                        {
                                                                            p.producto_nombre
                                                                        }
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {
                                                                            p.producto_codigo
                                                                        }
                                                                    </p>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <button
                                                                    className="text-sm font-medium text-primary hover:underline"
                                                                    onClick={() => {
                                                                        setDetalleAlmacen(
                                                                            {
                                                                                id: p.almacen_id,
                                                                                nombre: p.almacen_nombre,
                                                                            },
                                                                        );
                                                                        setIsDetalleModalOpen(
                                                                            true,
                                                                        );
                                                                    }}
                                                                >
                                                                    {
                                                                        p.almacen_nombre
                                                                    }
                                                                </button>
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-mono font-bold">
                                                                {stockActual.toLocaleString()}
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-mono text-red-500">
{vendido.toLocaleString()}
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-mono">
                                                                {esperado.toLocaleString()}
                                                            </td>
                                                        </tr>
                                                    );
                                                })
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card className="border-none shadow-xl">
                            <CardHeader className="border-b pb-4">
                                <CardTitle className="flex items-center gap-2">
                                    <Calculator className="h-5 w-5 text-primary" />
                                    Resumen del Cierre
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 p-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="rounded-lg bg-muted/50 p-3 text-center">
                                        <p className="text-xs font-bold text-muted-foreground uppercase">
                                            Productos
                                        </p>
                                        <p className="text-2xl font-black">
                                            {totales.totalProducts}
                                        </p>
                                    </div>
                                    <div className="rounded-lg bg-muted/50 p-3 text-center">
                                        <p className="text-xs font-bold text-muted-foreground uppercase">
                                            Stock Actual (BD)
                                        </p>
                                        <p className="text-2xl font-black">
                                            {totales.openingStock.toLocaleString()}
                                        </p>
                                    </div>
                                    <div className="rounded-lg bg-muted/50 p-3 text-center">
                                        <p className="text-xs font-bold text-muted-foreground uppercase">
                                            Stock Cierre (Ingrese)
                                        </p>
                                        <input
                                            type="number"
                                            className="w-24 border-b-2 border-primary bg-transparent text-center text-2xl font-black focus:outline-none"
                                            placeholder={totales.openingStock.toLocaleString()}
                                            value={stockCierreInput}
                                            onChange={(e) =>
                                                setStockCierreInput(
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="space-y-3 rounded-lg border p-4">
                                    <div className="flex justify-between">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Ventas del día:
                                        </span>
                                        <span className="font-mono font-bold text-red-500">
                                            {totales.totalVendido.toLocaleString()}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Stock Esperado:
                                        </span>
                                        <span className="font-mono font-bold">
                                            {totales.expectedStock.toLocaleString()}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-t pt-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Diferencia:
                                        </span>
                                        <span
                                            className={`font-mono font-bold ${totales.difference === 0 ? 'text-green-500' : 'text-red-500'}`}
                                        >
                                            {totales.difference > 0 ? '+' : ''}
                                            {totales.difference.toLocaleString()}
                                        </span>
                                    </div>
                                </div>

                                {/* 5% Threshold Warning */}
                                {totales.expectedStock > 0 && Math.abs(totales.difference) / totales.expectedStock > 0.05 && (
                                    <div className="flex items-start gap-2 rounded-lg bg-amber-500/10 border border-amber-200 p-3">
                                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                        <p className="text-xs text-amber-800">
                                            <strong>Diferencia alta detectada:</strong> La diferencia ({Math.abs(totales.difference).toLocaleString()} uds) supera el 5% del stock esperado ({totales.expectedStock.toLocaleString()} uds). Verifique el conteo físico antes de cerrar.
                                        </p>
                                    </div>
                                )}

                                {totales.difference !== 0 && (
                                    <div className="flex items-start gap-2 rounded-lg bg-red-500/10 p-3">
                                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                                        <p className="text-xs text-red-600">
                                            El inventario no coincide.
                                            {totales.difference > 0
                                                ? ` Sobran ${totales.difference.toLocaleString()} unidades.`
                                                : ` Faltan ${Math.abs(totales.difference).toLocaleString()} unidades.`}
                                        </p>
                                    </div>
                                )}

                                {totales.difference === 0 && (
                                    <div className="flex items-start gap-2 rounded-lg bg-green-500/10 p-3">
                                        <CheckCircle className="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                                        <p className="text-xs text-green-600">
                                            Inventario cuadra perfectamente.
                                        </p>
                                    </div>
                                )}

                                {envases_pendientes && envases_pendientes.length > 0 && (
                                    <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-3">
                                        <div className="mb-2 flex items-center gap-2">
                                            <RotateCcw className="h-4 w-4 text-amber-600" />
                                            <span className="text-sm font-bold text-amber-800">
                                                Envases Pendientes de Retorno
                                            </span>
                                            <Badge variant="outline" className="ml-auto bg-amber-100 text-amber-700 text-xs">
                                                {envases_pendientes.reduce((s, e) => s + e.cantidad, 0)} uds.
                                            </Badge>
                                        </div>
                                        <div className="space-y-1">
                                            {envases_pendientes.map((env) => (
                                                <div key={env.id} className="flex items-center justify-between rounded bg-white/60 px-2 py-1 text-xs">
                                                    <span className="font-medium">{env.producto}</span>
                                                    <span className="font-mono font-bold text-amber-700">{env.cantidad} uds.</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-2 pt-2">
                                    <Label className="text-xs font-bold text-muted-foreground uppercase">
                                        Observaciones
                                    </Label>
                                    <textarea
                                        value={observaciones}
                                        onChange={(e) =>
                                            setObservaciones(e.target.value)
                                        }
                                        className="min-h-[80px] w-full rounded-lg border bg-background p-3 text-sm"
                                        placeholder="Notas adicionales sobre el cierre..."
                                    ></textarea>
                                </div>

                                <div className="flex items-center gap-2 pt-2">
                                    <input
                                        type="checkbox"
                                        id="marcarAuditado"
                                        checked={marcarAuditado}
                                        onChange={(e) =>
                                            setMarcarAuditado(e.target.checked)
                                        }
                                        className="h-4 w-4 rounded border-gray-300"
                                    />
                                    <Label
                                        htmlFor="marcarAuditado"
                                        className="text-sm font-medium"
                                    >
                                        Marcar como Auditado
                                    </Label>
                                </div>

                                <form onSubmit={handleSubmit} className="pt-4">
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || productos.length === 0
                                        }
                                        className="h-11 w-full rounded-full bg-primary font-bold shadow-lg"
                                    >
                                        <Save className="mr-2 h-4 w-4" />
                                        {processing
                                            ? 'Guardando...'
                                            : 'Confirmar Cierre'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <Dialog
                open={isDetalleModalOpen}
                onOpenChange={setIsDetalleModalOpen}
            >
                <DialogContent className="m-4 max-h-[90vh] w-[95vw] max-w-2xl overflow-y-auto p-4 sm:m-auto sm:p-6">
                    <DialogHeader className="mb-4">
                        <DialogTitle className="text-lg sm:text-xl">
                            Detalle de Bodega: {detalleAlmacen?.nombre}
                        </DialogTitle>
                    </DialogHeader>
                    {detalleAlmacen && (
                        <>
                            <div className="rounded-lg bg-muted/50 p-4">
                                <h3 className="mb-2 font-bold">
                                    Resumen del Día
                                </h3>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Stock Inicio
                                        </p>
                                        <p className="text-xl font-black">
                                            {productos
                                                .filter(
                                                    (p) =>
                                                        p.almacen_id ===
                                                        detalleAlmacen.id,
                                                )
                                                .reduce(
                                                    (sum, p) =>
                                                        sum +
                                                        Math.round(
                                                            p.stock_actual,
                                                        ),
                                                    0,
                                                )
                                                .toLocaleString()}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Ventas Hoy
                                        </p>
                                        <p className="text-xl font-black text-red-500">
                                            {productos
                                                .filter(
                                                    (p) =>
                                                        p.almacen_id ===
                                                        detalleAlmacen.id,
                                                )
                                                .reduce((sum, p) => {
                                                    const venta =
                                                        ventas_esperadas[
                                                            p.producto_id
                                                        ];
                                                    return (
                                                        sum +
                                                        (venta?.total_vendido
                                                            ? Math.round(
                                                                  parseFloat(
                                                                      venta.total_vendido,
                                                                  ),
                                                              )
                                                            : 0)
                                                    );
                                                }, 0)
                                                .toLocaleString()}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-lg border p-4">
                                <h3 className="mb-3 font-bold">
                                    Productos en esta Bodega
                                </h3>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-xs font-bold text-muted-foreground">
                                                <th className="pb-2 text-left">
                                                    Producto
                                                </th>
                                                <th className="pb-2 text-left">
                                                    Desde Bodega
                                                </th>
                                                <th className="pb-2 text-right">
                                                    Stock
                                                </th>
                                                <th className="pb-2 text-right">
                                                    Vendido
                                                </th>
                                                <th className="pb-2 text-right">
                                                    Esperado
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {productos
                                                .filter(
                                                    (p) =>
                                                        p.almacen_id ===
                                                        detalleAlmacen.id,
                                                )
                                                .map((p) => {
                                                    const venta =
                                                        ventas_esperadas[
                                                            p.producto_id
                                                        ];
                                                    const vendido =
                                                        venta?.total_vendido
                                                            ? Math.round(
                                                                  parseFloat(
                                                                      venta.total_vendido,
                                                                  ),
                                                              )
                                                            : 0;
                                                    const porAlmacen =
                                                        venta?.por_almacen ||
                                                        {};
                                                    const stockActual =
                                                        Math.round(
                                                            p.stock_actual,
                                                        );
                                                    const esperado =
                                                        stockActual - vendido;
                                                    return (
                                                        <tr
                                                            key={p.id}
                                                            className="border-b"
                                                        >
                                                            <td className="py-2">
                                                                {
                                                                    p.producto_nombre
                                                                }
                                                            </td>
                                                            <td className="py-2 text-left">
                                                                {Object.keys(
                                                                    porAlmacen,
                                                                ).length > 0 ? (
                                                                    <div className="flex flex-wrap gap-1">
                                                                        {Object.entries(
                                                                            porAlmacen,
                                                                        ).map(
                                                                            ([
                                                                                id,
                                                                                data,
                                                                            ]: [
                                                                                string,
                                                                                any,
                                                                            ]) => (
                                                                                <span
                                                                                    key={
                                                                                        id
                                                                                    }
                                                                                    className="rounded bg-blue-100 px-1 text-xs text-blue-800"
                                                                                >
                                                                                    {
                                                                                        data.nombre
                                                                                    }
                                                                                    :{' '}
                                                                                    {
                                                                                        data.cantidad
                                                                                    }
                                                                                </span>
                                                                            ),
                                                                        )}
                                                                    </div>
                                                                ) : (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        -
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td className="py-2 text-right font-mono">
                                                                {stockActual.toLocaleString()}
                                                            </td>
                                                            <td className="py-2 text-right font-mono text-red-500">
                                                                {vendido > 0
                                                                    ? `${vendido}`
                                                                    : '0'}
                                                            </td>
                                                            <td className="py-2 text-right font-mono">
                                                                {esperado.toLocaleString()}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button
                                    variant="outline"
                                    onClick={() => setIsDetalleModalOpen(false)}
                                >
                                    Cerrar
                                </Button>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
