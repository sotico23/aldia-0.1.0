import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Eye, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
    detalles?: RenovacionDetalle[];
}

interface CargaDiaria {
    id: number;
    fecha: string;
    estado: string;
    vehiculo?: { placa: string; marca: string; modelo: string };
    conductor?: { nombre: string };
    renovaciones?: Renovacion[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cargas Diarias', href: '/cargas-diarias' },
    { title: 'Historial de Recargas', href: '#' },
];

export default function Renovaciones({
    carga,
}: {
    carga: CargaDiaria;
}) {
    const { code: countryCode, currency } = useCountry();
    return (
        <>
            <Head title={`Recargas - ${carga.vehiculo?.placa || ''}`} />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 pb-24">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Historial de Recargas
                            </h1>
                            <p className="text-muted-foreground">
                                Vehículo: {carga.vehiculo?.placa} -{' '}
                                {carga.vehiculo?.marca} | Conductor:{' '}
                                {carga.conductor?.nombre}
                            </p>
                        </div>
                        <Link href="/cargas-diarias">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver
                            </Button>
                        </Link>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <RefreshCw className="h-5 w-5" />
                                Recargas Registradas
                            </CardTitle>
                            <CardDescription>
                                {carga.renovaciones?.length || 0} recargas
                                encontradas
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {carga.renovaciones &&
                            carga.renovaciones.length > 0 ? (
                                <div className="space-y-4">
                                    {carga.renovaciones.map((renovacion) => (
                                        <div
                                            key={renovacion.id}
                                            className="rounded-xl border border-border/50 bg-muted/20 p-4"
                                        >
                                            <div className="mb-3 flex items-center justify-between">
                                                <div>
                                                    <p className="font-bold">
                                                        Recarga N°{' '}
                                                        {renovacion.id}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {renovacion.fecha
                                                            ? renovacion.fecha.split(
                                                                  'T',
                                                              )[0]
                                                            : ''}{' '}
                                                        -{' '}
                                                        {renovacion.created_at
                                                            ? new Date(
                                                                  renovacion.created_at,
                                                              ).toLocaleTimeString()
                                                            : ''}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-4">
                                                    <div className="grid grid-cols-2 gap-2 text-center text-xs sm:grid-cols-4">
                                                        <div className="rounded bg-green-100 px-2 py-1">
                                                            <p className="font-bold text-green-700">
                                                                {
                                                                    renovacion.total_productos_llenos
                                                                }
                                                            </p>
                                                            <p className="text-[9px] text-green-600">
                                                                LLENOS
                                                            </p>
                                                        </div>
                                                        <div className="rounded bg-amber-100 px-2 py-1">
                                                            <p className="font-bold text-amber-700">
                                                                {
                                                                    renovacion.total_productos_vacios
                                                                }
                                                            </p>
                                                            <p className="text-[9px] text-amber-600">
                                                                VACÍOS
                                                            </p>
                                                        </div>
                                                        <div className="rounded bg-red-100 px-2 py-1">
                                                            <p className="font-bold text-red-700">
                                                                {
                                                                    renovacion.total_productos_faltantes
                                                                }
                                                            </p>
                                                            <p className="text-[9px] text-red-600">
                                                                FALTANTES
                                                            </p>
                                                        </div>
                                                        <div className="rounded bg-purple-100 px-2 py-1">
                                                            <p className="font-bold text-purple-700">
                                                                {
                                                                    renovacion.total_productos_defectuosos
                                                                }
                                                            </p>
                                                            <p className="text-[9px] text-purple-600">
                                                                DEFECTUOSOS
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <Link
                                                        href={`/cargas-diarias/renovacion/${renovacion.id}`}
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                            title="Ver Ticket"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </div>

                                            {/* Detalle de productos */}
                                            {renovacion.detalles &&
                                                renovacion.detalles.length >
                                                    0 && (
                                                    <div className="mt-2 overflow-x-auto">
                                                        <table className="w-full text-xs">
                                                            <thead>
                                                                <tr className="border-b">
                                                                    <th className="py-1 text-left font-medium">
                                                                        Producto
                                                                    </th>
                                                                    <th className="py-1 text-center font-medium">
                                                                        Salió
                                                                    </th>
                                                                    <th className="py-1 text-center font-medium text-green-600">
                                                                        Llenos
                                                                    </th>
                                                                    <th className="py-1 text-center font-medium text-amber-600">
                                                                        Vacíos
                                                                    </th>
                                                                    <th className="py-1 text-center font-medium text-red-600">
                                                                        Faltantes
                                                                    </th>
                                                                    <th className="py-1 text-center font-medium text-purple-600">
                                                                        Defectuosos
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {renovacion.detalles.map(
                                                                    (d) => (
                                                                        <tr
                                                                            key={
                                                                                d.id
                                                                            }
                                                                            className="border-b"
                                                                        >
                                                                            <td className="py-1">
                                                                                {d
                                                                                    .producto
                                                                                    ?.nombre ||
                                                                                    '-'}
                                                                            </td>
                                                                            <td className="py-1 text-center font-bold">
                                                                                {
                                                                                    d.cantidad_bordo
                                                                                }
                                                                            </td>
                                                                            <td className="py-1 text-center font-bold text-green-600">
                                                                                {
                                                                                    d.cantidad_llena
                                                                                }
                                                                            </td>
                                                                            <td className="py-1 text-center font-bold text-amber-600">
                                                                                {
                                                                                    d.cantidad_vacia
                                                                                }
                                                                            </td>
                                                                            <td className="py-1 text-center font-bold text-red-600">
                                                                                {
                                                                                    d.cantidad_faltante
                                                                                }
                                                                            </td>
                                                                            <td className="py-1 text-center font-bold text-purple-600">
                                                                                {
                                                                                    d.cantidad_defectuosa
                                                                                }
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                )}

                                            {/* Totales monetarios */}
                                            {(renovacion.ventas_totales > 0 ||
                                                renovacion.devoluciones_totales >
                                                    0) && (
                                                <div className="mt-3 grid grid-cols-2 gap-4 border-t pt-3 text-xs">
                                                    <div>
                                                        <span className="text-muted-foreground">
                                                            Ventas:{' '}
                                                        </span>
                                                        <span className="font-bold text-emerald-600">
                                                            $
                                                            {renovacion.ventas_totales.toLocaleString(
                                                                currency.locale,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">
                                                            Devoluciones:{' '}
                                                        </span>
                                                        <span className="font-bold text-amber-600">
                                                            $
                                                            {renovacion.devoluciones_totales.toLocaleString(
                                                                currency.locale,
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Notas */}
                                            {renovacion.notas && (
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    <span className="font-bold">
                                                        Notas:{' '}
                                                    </span>
                                                    {renovacion.notas}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-12 text-center text-muted-foreground">
                                    <RefreshCw className="mx-auto mb-3 h-12 w-12 opacity-20" />
                                    <p>
                                        No hay recargas registradas para esta
                                        carga.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        </>
    );
}
