import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface RenovacionDetalle {
    id: number;
    producto?: { nombre: string };
    cantidad_bordo: number;
    cantidad_llena: number;
    cantidad_vacia: number;
    cantidad_faltante: number;
    cantidad_defectuosa: number;
    cantidad_vendida: number;
    cantidad_devuelta: number;
}

interface Renovacion {
    id: number;
    fecha: string;
    tipo: string;
    notas: string | null;
    total_productos_llenos: number;
    total_productos_vacios: number;
    total_productos_faltantes: number;
    total_productos_defectuosos: number;
    ventas_totales: number;
    devoluciones_totales: number;
    created_at: string;
    cargaDiaria?: {
        id: number;
        vehiculo?: { placa: string; marca: string };
        conductor?: { nombre: string };
    };
    detalles?: RenovacionDetalle[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cargas Diarias', href: '/cargas-diarias' },
    { title: 'Ticket de Recarga', href: '#' },
];

export default function TicketRecarga({
    renovacion,
}: {
    renovacion: Renovacion;
}) {
    const { code: countryCode, currency } = useCountry();
    const handlePrint = () => {
        window.print();
    };

    return (
        <>
            <Head title={`Ticket Recarga #${renovacion.id}`} />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 pb-24">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Ticket de Recarga #{renovacion.id}
                            </h1>
                            <p className="text-muted-foreground">
                                Registro de recarga del{' '}
                                {renovacion.fecha
                                    ? renovacion.fecha.split('T')[0]
                                    : ''}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link href="/cargas-diarias">
                                <Button variant="outline" size="sm">
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Volver
                                </Button>
                            </Link>
                            <Button
                                size="sm"
                                onClick={handlePrint}
                                className="gap-2"
                            >
                                <Printer className="h-4 w-4" />
                                Imprimir
                            </Button>
                        </div>
                    </div>

                    <div className="mx-auto w-full max-w-2xl">
                        <Card className="print:shadow-none">
                            <CardHeader className="border-b print:border-gray-300">
                                <div className="text-center">
                                    <CardTitle className="text-xl font-black uppercase">
                                        Ticket de Recarga
                                    </CardTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        N° {renovacion.id} -{' '}
                                        {renovacion.fecha
                                            ? renovacion.fecha.split('T')[0]
                                            : ''}
                                    </p>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6 p-6">
                                {/* Info del vehículo y conductor */}
                                <div className="grid grid-cols-2 gap-4 rounded-lg border p-4">
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Vehículo
                                        </p>
                                        <p className="font-bold uppercase">
                                            {renovacion.cargaDiaria?.vehiculo
                                                ?.placa || '-'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {renovacion.cargaDiaria?.vehiculo
                                                ?.marca || ''}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Conductor
                                        </p>
                                        <p className="font-medium">
                                            {renovacion.cargaDiaria?.conductor
                                                ?.nombre || '-'}
                                        </p>
                                    </div>
                                </div>

                                {/* Resumen */}
                                <div className="grid grid-cols-2 gap-4 text-center sm:grid-cols-4">
                                    <div className="rounded-lg border border-green-200 bg-green-50 p-3">
                                        <p className="text-2xl font-black text-green-700">
                                            {renovacion.total_productos_llenos}
                                        </p>
                                        <p className="text-xs font-bold text-green-600">
                                            LLENOS
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                        <p className="text-2xl font-black text-amber-700">
                                            {renovacion.total_productos_vacios}
                                        </p>
                                        <p className="text-xs font-bold text-amber-600">
                                            VACÍOS
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-red-200 bg-red-50 p-3">
                                        <p className="text-2xl font-black text-red-700">
                                            {renovacion.total_productos_faltantes}
                                        </p>
                                        <p className="text-xs font-bold text-red-600">
                                            FALTANTES
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-purple-200 bg-purple-50 p-3">
                                        <p className="text-2xl font-black text-purple-700">
                                            {renovacion.total_productos_defectuosos}
                                        </p>
                                        <p className="text-xs font-bold text-purple-600">
                                            DEFECTUOSOS
                                        </p>
                                    </div>
                                </div>

                                {/* Detalle de productos */}
                                <div>
                                    <h4 className="mb-3 text-sm font-bold">
                                        Detalle de Productos
                                    </h4>
                                    <div className="overflow-hidden rounded-lg border">
                                        <table className="w-full text-xs">
                                            <thead>
                                                <tr className="border-b bg-muted/50">
                                                    <th className="px-3 py-2 text-left font-bold">
                                                        Producto
                                                    </th>
                                                    <th className="px-3 py-2 text-center font-bold">
                                                        Salió
                                                    </th>
                                                    <th className="px-3 py-2 text-center font-bold text-green-600">
                                                        Llenos
                                                    </th>
                                                    <th className="px-3 py-2 text-center font-bold text-amber-600">
                                                        Vacíos
                                                    </th>
                                                    <th className="px-3 py-2 text-center font-bold text-red-600">
                                                        Faltantes
                                                    </th>
                                                    <th className="px-3 py-2 text-center font-bold text-purple-600">
                                                        Defectuosos
                                                    </th>
                                                    <th className="px-3 py-2 text-center font-bold">
                                                        Vendidos
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {renovacion.detalles?.map(
                                                    (d) => (
                                                        <tr
                                                            key={d.id}
                                                            className="border-b"
                                                        >
                                                            <td className="px-3 py-2 font-medium">
                                                                {d.producto
                                                                    ?.nombre ||
                                                                    '-'}
                                                            </td>
                                                            <td className="px-3 py-2 text-center font-bold">
                                                                {d.cantidad_bordo}
                                                            </td>
                                                            <td className="px-3 py-2 text-center font-bold text-green-600">
                                                                {d.cantidad_llena}
                                                            </td>
                                                            <td className="px-3 py-2 text-center font-bold text-amber-600">
                                                                {d.cantidad_vacia}
                                                            </td>
                                                            <td className="px-3 py-2 text-center font-bold text-red-600">
                                                                {d.cantidad_faltante}
                                                            </td>
                                                            <td className="px-3 py-2 text-center font-bold text-purple-600">
                                                                {d.cantidad_defectuosa}
                                                            </td>
                                                            <td className="px-3 py-2 text-center font-bold">
                                                                {d.cantidad_vendida}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Totales monetarios */}
                                <div className="grid grid-cols-2 gap-4 rounded-lg border p-4">
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Total Ventas
                                        </p>
                                        <p className="text-lg font-black text-emerald-600">
                                            $
                                            {renovacion.ventas_totales.toLocaleString(
                                                currency.locale,
                                            )}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Total Devoluciones
                                        </p>
                                        <p className="text-lg font-black text-amber-600">
                                            $
                                            {renovacion.devoluciones_totales.toLocaleString(
                                                currency.locale,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                {/* Notas */}
                                {renovacion.notas && (
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Notas
                                        </p>
                                        <p className="rounded-lg bg-muted/30 p-3 text-sm">
                                            {renovacion.notas}
                                        </p>
                                    </div>
                                )}

                                {/* Firmas */}
                                <div className="mt-8 grid grid-cols-2 gap-8 border-t pt-6">
                                    <div className="text-center">
                                        <div className="mb-2 h-px w-full border-b border-dashed border-muted-foreground/30" />
                                        <p className="text-xs font-bold text-muted-foreground">
                                            Firma Conductor
                                        </p>
                                    </div>
                                    <div className="text-center">
                                        <div className="mb-2 h-px w-full border-b border-dashed border-muted-foreground/30" />
                                        <p className="text-xs font-bold text-muted-foreground">
                                            Firma Receptor
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
