import { Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { ArrowLeft, DollarSign, Banknote, Calendar, User, AlertCircle } from 'lucide-react';
import { useState } from 'react';
import { formatCurrency } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Cuota {
    id: number;
    numero_cuota: number;
    monto: number;
    fecha_vencimiento: string;
    estado: string;
    prestamo?: {
        id: number;
        tipo: string;
        empleado?: { nombre: string; apellido: string; rut: string };
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cuotas Pendientes', href: '/prestamos/cuotas-pendientes' },
];

export default function CuotasPendientes({
    cuotas,
}: {
    cuotas: Cuota[];
}) {
    const [isPagarOpen, setIsPagarOpen] = useState(false);
    const [isNominaOpen, setIsNominaOpen] = useState(false);
    const [cuotaSeleccionada, setCuotaSeleccionada] = useState<Cuota | null>(null);

    const [pagoData, setPagoData] = useState({
        monto_pagado: 0,
        fecha_pago: getLocalDateString(),
        metodo_pago: 'efectivo',
        referencia_pago: '',
        notas: '',
    });

    const [nominaData, setNominaData] = useState({
        nomina_periodo: '',
    });

    const handlePagar = (cuota: Cuota) => {
        setCuotaSeleccionada(cuota);
        setPagoData({
            monto_pagado: cuota.monto,
            fecha_pago: getLocalDateString(),
            metodo_pago: 'efectivo',
            referencia_pago: '',
            notas: '',
        });
        setIsPagarOpen(true);
    };

    const handleAplicarNomina = (cuota: Cuota) => {
        setCuotaSeleccionada(cuota);
        const now = new Date();
        setNominaData({
            nomina_periodo: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`,
        });
        setIsNominaOpen(true);
    };

    const confirmarPago = () => {
        if (!cuotaSeleccionada) return;

        router.post(`/prestamo-cuotas/${cuotaSeleccionada.id}/pagar`, pagoData, {
            onSuccess: () => {
                setIsPagarOpen(false);
            },
        });
    };

    const confirmarNomina = () => {
        if (!cuotaSeleccionada) return;

        router.post(`/prestamo-cuotas/${cuotaSeleccionada.id}/aplicar-nomina`, nominaData, {
            onSuccess: () => {
                setIsNominaOpen(false);
            },
        });
    };

    const totalPendiente = cuotas.reduce((sum, c) => sum + c.monto, 0);

    return (
        <>
            <Head title="Cuotas Pendientes" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 pb-24">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Cuotas Pendientes
                            </h1>
                            <p className="text-muted-foreground">
                                {cuotas.length} cuotas por vencer este mes
                            </p>
                        </div>
                        <Link href="/prestamos">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver a Préstamos
                            </Button>
                        </Link>
                    </div>

                    {/* Resumen */}
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-4">
                                <div className="rounded-full bg-amber-100 p-3">
                                    <AlertCircle className="h-6 w-6 text-amber-600" />
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Total pendiente de cobro
                                    </p>
                                    <p className="text-2xl font-black text-amber-600">
                                        {formatCurrency(totalPendiente)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Cuotas por Vencer</CardTitle>
                            <CardDescription>
                                Cuotas con vencimiento en el mes actual o anteriores
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {cuotas.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs sm:text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-2 text-left font-medium">Empleado</th>
                                                <th className="py-2 text-left font-medium">Tipo</th>
                                                <th className="py-2 text-center font-medium">Cuota</th>
                                                <th className="py-2 text-right font-medium">Monto</th>
                                                <th className="py-2 text-center font-medium">Vencimiento</th>
                                                <th className="py-2 text-right font-medium">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {cuotas.map((cuota) => (
                                                <tr
                                                    key={cuota.id}
                                                    className="border-b transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="py-2">
                                                        <div className="font-bold">
                                                            {cuota.prestamo?.empleado?.nombre}{' '}
                                                            {cuota.prestamo?.empleado?.apellido}
                                                        </div>
                                                        <div className="text-[10px] text-muted-foreground">
                                                            {cuota.prestamo?.empleado?.rut}
                                                        </div>
                                                    </td>
                                                    <td className="py-2">
                                                        <span className="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                                                            {cuota.prestamo?.tipo === 'prestamo'
                                                                ? 'Préstamo'
                                                                : 'Adelanto'}
                                                        </span>
                                                    </td>
                                                    <td className="py-2 text-center font-bold">
                                                        #{cuota.numero_cuota}
                                                    </td>
                                                    <td className="py-2 text-right font-bold">
                                                        {formatCurrency(cuota.monto)}
                                                    </td>
                                                    <td className="py-2 text-center">
                                                        <span className="text-amber-600">
                                                            {cuota.fecha_vencimiento?.split('T')[0]}
                                                        </span>
                                                    </td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-7 text-xs text-green-600 hover:bg-green-50 hover:text-green-700"
                                                                onClick={() => handlePagar(cuota)}
                                                            >
                                                                <DollarSign className="mr-1 h-3 w-3" />
                                                                Pagar
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-7 text-xs text-purple-600 hover:bg-purple-50 hover:text-purple-700"
                                                                onClick={() => handleAplicarNomina(cuota)}
                                                            >
                                                                <Banknote className="mr-1 h-3 w-3" />
                                                                Nómina
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="py-12 text-center text-muted-foreground">
                                    <Calendar className="mx-auto mb-3 h-12 w-12 opacity-20" />
                                    <p>No hay cuotas pendientes este mes.</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>

            {/* Modal Registrar Pago */}
            <Dialog open={isPagarOpen} onOpenChange={setIsPagarOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-w-md">
                    <DialogHeader className="bg-gradient-to-r from-green-600 to-emerald-700 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            Registrar Pago
                        </DialogTitle>
                    </DialogHeader>
                    <div className="px-4 py-4 md:px-6">
                        {cuotaSeleccionada && (
                            <div className="space-y-4">
                                <div className="rounded-lg bg-green-50 p-3 text-center">
                                    <p className="text-xs text-muted-foreground">
                                        {cuotaSeleccionada.prestamo?.empleado?.nombre}{' '}
                                        {cuotaSeleccionada.prestamo?.empleado?.apellido} - Cuota #{cuotaSeleccionada.numero_cuota}
                                    </p>
                                    <p className="text-2xl font-black text-green-700">
                                        {formatCurrency(cuotaSeleccionada.monto)}
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Monto *</Label>
                                    <Input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={pagoData.monto_pagado}
                                        onChange={(e) =>
                                            setPagoData({ ...pagoData, monto_pagado: parseFloat(e.target.value) || 0 })
                                        }
                                        className="h-9"
                                        required
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Fecha *</Label>
                                    <Input
                                        type="date"
                                        value={pagoData.fecha_pago}
                                        onChange={(e) => setPagoData({ ...pagoData, fecha_pago: e.target.value })}
                                        className="h-9"
                                        required
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Método *</Label>
                                    <Select
                                        value={pagoData.metodo_pago}
                                        onValueChange={(val) => setPagoData({ ...pagoData, metodo_pago: val })}
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Método" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="efectivo">Efectivo</SelectItem>
                                            <SelectItem value="transferencia">Transferencia</SelectItem>
                                            <SelectItem value="nomina">Descuento Nómina</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex justify-end gap-3 border-t pt-4">
                                    <Button
                                        variant="outline"
                                        onClick={() => setIsPagarOpen(false)}
                                        className="rounded-full"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        onClick={confirmarPago}
                                        className="gap-2 rounded-full bg-green-600 font-bold hover:bg-green-700"
                                    >
                                        <DollarSign className="h-4 w-4" />
                                        Confirmar
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Modal Aplicar en Nómina */}
            <Dialog open={isNominaOpen} onOpenChange={setIsNominaOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-w-md">
                    <DialogHeader className="bg-gradient-to-r from-purple-600 to-indigo-700 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            Aplicar en Nómina
                        </DialogTitle>
                    </DialogHeader>
                    <div className="px-4 py-4 md:px-6">
                        {cuotaSeleccionada && (
                            <div className="space-y-4">
                                <div className="rounded-lg bg-purple-50 p-3 text-center">
                                    <p className="text-xs text-muted-foreground">
                                        {cuotaSeleccionada.prestamo?.empleado?.nombre}{' '}
                                        {cuotaSeleccionada.prestamo?.empleado?.apellido} - Cuota #{cuotaSeleccionada.numero_cuota}
                                    </p>
                                    <p className="text-2xl font-black text-purple-700">
                                        {formatCurrency(cuotaSeleccionada.monto)}
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Período de Nómina *</Label>
                                    <Input
                                        type="month"
                                        value={nominaData.nomina_periodo}
                                        onChange={(e) => setNominaData({ ...nominaData, nomina_periodo: e.target.value })}
                                        className="h-9"
                                        required
                                    />
                                </div>
                                <div className="flex justify-end gap-3 border-t pt-4">
                                    <Button
                                        variant="outline"
                                        onClick={() => setIsNominaOpen(false)}
                                        className="rounded-full"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        onClick={confirmarNomina}
                                        className="gap-2 rounded-full bg-purple-600 font-bold hover:bg-purple-700"
                                    >
                                        <Banknote className="h-4 w-4" />
                                        Aplicar
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
