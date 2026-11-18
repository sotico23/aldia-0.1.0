import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateCLP } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface WalletCashier {
    id: number;
    name: string;
    email: string;
}

interface Wallet {
    id: number;
    user_id: number;
    balance: number;
    currency: string;
    status: string;
    allow_overdraft: boolean;
    cashier?: WalletCashier | null;
    total_comisiones: number;
}

interface Transaccion {
    id: number;
    type: string;
    category: string;
    amount: number;
    balance_before: number;
    balance_after: number;
    description: string | null;
    created_at: string | null;
    reference_type: string | null;
    reference_id: number | null;
}

interface TransaccionesPaginadas {
    data: Transaccion[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    meta: { from: number | null; to: number | null; total: number };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Mi Billetera', href: '/billetera' },
];

export default function CajeroWallet({
    wallet,
    comisiones_mes,
    transacciones,
    filtros,
    moneda,
}: {
    wallet: Wallet;
    comisiones_mes: number;
    transacciones: TransaccionesPaginadas | null;
    filtros?: { desde?: string; hasta?: string };
    moneda: string;
}) {
    const { hasPermission } = usePermissions();
    const puedeVerBilletera = hasPermission('finanzas.wallets.view');

    const [filtrosForm, setFiltrosForm] = useState({
        desde: filtros?.desde ?? '',
        hasta: filtros?.hasta ?? '',
    });

    const transaccionesData = transacciones?.data ?? [];
    const transaccionesLinks = transacciones?.links ?? [];
    const transaccionesMeta = transacciones?.meta ?? {
        from: 0,
        to: 0,
        total: 0,
    };

    const saldo = Number(wallet.balance) || 0;

    const aplicarFiltros = (e: React.FormEvent) => {
        e.preventDefault();

        router.get(
            '/billetera',
            {
                desde: filtrosForm.desde || undefined,
                hasta: filtrosForm.hasta || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const limpiarFiltros = () => {
        setFiltrosForm({ desde: '', hasta: '' });
        router.get(
            '/billetera',
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    if (!puedeVerBilletera) {
        return (
            <>
                <Head title="Mi Billetera" />
                <AppLayout breadcrumbs={breadcrumbs}>
                    <div className="p-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Sin acceso</CardTitle>
                                <CardDescription>
                                    No tienes permisos para consultar la
                                    billetera.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>
                </AppLayout>
            </>
        );
    }

    return (
        <>
            <Head title="Mi Billetera" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Mi Billetera</h1>
                            <p className="text-muted-foreground">
                                Saldo disponible y comisiones acumuladas
                            </p>
                        </div>
                        <Badge
                            className={
                                wallet.status === 'active'
                                    ? 'bg-green-500'
                                    : 'bg-amber-500'
                            }
                        >
                            {wallet.status}
                        </Badge>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    Saldo disponible
                                </CardDescription>
                                <CardTitle className="text-2xl">
                                    {formatCurrency(saldo, wallet.currency)}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">
                                    Moneda {wallet.currency ?? moneda}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    Comisiones acumuladas en el mes
                                </CardDescription>
                                <CardTitle className="text-2xl">
                                    {formatCurrency(
                                        Number(comisiones_mes) || 0,
                                        wallet.currency,
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">
                                    Comisiones generadas por cierre de ventas
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    Total comisiones (historial)
                                </CardDescription>
                                <CardTitle className="text-2xl">
                                    {formatCurrency(
                                        Number(wallet.total_comisiones) || 0,
                                        wallet.currency,
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">
                                    Acreditadas a esta billetera
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Historial de movimientos</CardTitle>
                            <CardDescription>
                                Detalle de movimientos con venta asociada
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={aplicarFiltros}
                                className="mb-4 grid gap-2 md:grid-cols-4"
                            >
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Desde
                                    </Label>
                                    <Input
                                        type="date"
                                        value={filtrosForm.desde}
                                        onChange={(e) =>
                                            setFiltrosForm({
                                                ...filtrosForm,
                                                desde: e.target.value,
                                            })
                                        }
                                        className="h-9"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Hasta
                                    </Label>
                                    <Input
                                        type="date"
                                        value={filtrosForm.hasta}
                                        onChange={(e) =>
                                            setFiltrosForm({
                                                ...filtrosForm,
                                                hasta: e.target.value,
                                            })
                                        }
                                        className="h-9"
                                    />
                                </div>
                                <div className="flex items-end">
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="w-full"
                                    >
                                        Filtrar
                                    </Button>
                                </div>
                                <div className="flex items-end">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="w-full"
                                        onClick={limpiarFiltros}
                                    >
                                        Limpiar filtros
                                    </Button>
                                </div>
                            </form>

                            <div className="overflow-x-auto">
                                <table className="w-full text-xs sm:text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="py-3 text-left font-medium">
                                                Fecha
                                            </th>
                                            <th className="py-3 text-left font-medium">
                                                Concepto
                                            </th>
                                            <th className="py-3 text-left font-medium">
                                                Venta
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Monto
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Saldo resultante
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {transaccionesData.map((t) => (
                                            <tr
                                                key={t.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-3">
                                                    {formatDateCLP(
                                                        t.created_at,
                                                    )}
                                                </td>
                                                <td className="py-3">
                                                    <div className="font-medium">
                                                        {t.description}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground uppercase">
                                                        {t.category}
                                                    </div>
                                                </td>
                                                <td className="py-3">
                                                    {t.reference_id &&
                                                    (t.category ===
                                                        'sale_commission' ||
                                                        t.category ===
                                                            'refund') ? (
                                                        <Link
                                                            href={`/pos/mis-ventas/${t.reference_id}`}
                                                            className="font-medium text-primary underline-offset-2 hover:underline"
                                                        >
                                                            Ver venta
                                                        </Link>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 text-right font-bold">
                                                    <span className="inline-flex items-center gap-1">
                                                        {t.type === 'credit' ? (
                                                            <ArrowDownLeft className="h-3.5 w-3.5 text-green-600" />
                                                        ) : (
                                                            <ArrowUpRight className="h-3.5 w-3.5 text-amber-600" />
                                                        )}
                                                        {t.type === 'credit'
                                                            ? '+'
                                                            : '-'}
                                                        {formatCurrency(
                                                            Number(t.amount) ||
                                                                0,
                                                            wallet.currency,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="py-3 text-right font-bold">
                                                    {formatCurrency(
                                                        Number(
                                                            t.balance_after,
                                                        ) || 0,
                                                        wallet.currency,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                        {transaccionesData.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron
                                                    movimientos
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {transacciones && (
                                <Pagination
                                    links={transaccionesLinks}
                                    meta={{
                                        from: transaccionesMeta.from ?? 0,
                                        to: transaccionesMeta.to ?? 0,
                                        total: transaccionesMeta.total,
                                    }}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        </>
    );
}
