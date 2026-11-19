import { Head, router, useForm } from '@inertiajs/react';
import { Plus, ShieldCheck } from 'lucide-react';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatDateCLP } from '@/lib/utils';
import { formatWalletAmount as formatCurrency } from '@/lib/wallet';
import type { BreadcrumbItem } from '@/types';

interface WalletCashier {
    id: number;
    name: string;
    email: string;
}

interface WalletItem {
    id: number;
    account_number: string;
    user_id: number;
    balance: number;
    currency: string;
    status: string;
    allow_overdraft: boolean;
    cashier: WalletCashier | null;
    total_comisiones: number;
}

interface CajeroOpcion {
    user_id: number;
    wallet_id: number | null;
    name: string;
    email: string;
    balance: number;
    currency: string;
    status: string | null;
}

interface CommissionRule {
    id: number;
    tenant_id: number | null;
    user_id: number | null;
    commission_type: string;
    commission_value: number;
    min_sale_amount: number;
    is_active: boolean;
    cashier?: WalletCashier | null;
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
    wallet?: { user?: WalletCashier | null } | null;
}

interface TransaccionesPaginadas {
    data: Transaccion[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    meta: { from: number | null; to: number | null; total: number };
}

interface Filtros {
    cashier_id: number | '';
    type: string;
    category: string;
    desde: string;
    hasta: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Billeteras', href: '/billeteras' },
];

export default function Index({
    wallets = [],
    cajeros = [],
    commission_rules = [],
    transacciones = null,
    filtros,
    tenant_id = 0,
}: {
    wallets?: WalletItem[];
    cajeros?: CajeroOpcion[];
    commission_rules?: CommissionRule[];
    transacciones?: TransaccionesPaginadas | null;
    filtros?: Partial<Filtros>;
    tenant_id?: number;
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('finanzas.wallets.create');
    const canEdit = hasPermission('finanzas.wallets.edit');

    const [isOpen, setIsOpen] = useState(false);
    const [cajeroIdRecarga, setCajeroIdRecarga] = useState('');

    const [filtrosForm, setFiltrosForm] = useState<Filtros>({
        cashier_id: filtros?.cashier_id ?? '',
        type: filtros?.type ?? '',
        category: filtros?.category ?? '',
        desde: filtros?.desde ?? '',
        hasta: filtros?.hasta ?? '',
    });

    const recargaForm = useForm({
        amount: '',
        note: '',
    });

    const reglaForm = useForm({
        tenant_id: tenant_id as number | '',
        user_id: '' as string | number,
        commission_type: 'percentage',
        commission_value: '0',
        min_sale_amount: '0',
        is_active: true,
    });

    const cashiers = (wallets ?? []).filter((w) => w.cashier);

    const cajeroSeleccionado = cajeros.find(
        (cajero) => String(cajero.user_id) === cajeroIdRecarga,
    );

    const abrirRecarga = (cajeroId?: number) => {
        const id = cajeroId ?? cajeros[0]?.user_id;
        setCajeroIdRecarga(id ? String(id) : '');
        recargaForm.reset();
        recargaForm.clearErrors();
        setIsOpen(true);
    };

    const submitRecarga = (e: React.FormEvent) => {
        e.preventDefault();

        if (!cajeroIdRecarga) {
            return;
        }

        recargaForm.post(`/billeteras/cajeros/${cajeroIdRecarga}/recargas`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                recargaForm.reset();
            },
        });
    };

    const submitRegla = (e: React.FormEvent) => {
        e.preventDefault();

        reglaForm.post('/billeteras/commission-rules', {
            preserveScroll: true,
            onSuccess: () => {
                reglaForm.setData({
                    tenant_id: tenant_id,
                    user_id: '',
                    commission_type: 'percentage',
                    commission_value: '0',
                    min_sale_amount: '0',
                    is_active: true,
                });
                reglaForm.clearErrors();
            },
        });
    };

    const aplicarFiltros = (e: React.FormEvent) => {
        e.preventDefault();

        router.get(
            '/billeteras',
            {
                cashier_id: filtrosForm.cashier_id || undefined,
                type: filtrosForm.type || undefined,
                category: filtrosForm.category || undefined,
                desde: filtrosForm.desde || undefined,
                hasta: filtrosForm.hasta || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const limpiarFiltros = () => {
        setFiltrosForm({
            cashier_id: '',
            type: '',
            category: '',
            desde: '',
            hasta: '',
        });
        router.get(
            '/billeteras',
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const totalSaldo = (wallets ?? []).reduce(
        (acc, w) => acc + (Number(w.balance) || 0),
        0,
    );

    const totalComisiones = (wallets ?? []).reduce(
        (acc, w) => acc + (Number(w.total_comisiones) || 0),
        0,
    );

    const transaccionesData = transacciones?.data ?? [];

    return (
        <>
            <Head title="Billeteras" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Billeteras Digitales
                            </h1>
                            <p className="text-muted-foreground">
                                Gestión de billeteras, recargas master y
                                comisiones de cajeros
                            </p>
                        </div>
                        {canCreate && (
                            <Button onClick={() => abrirRecarga()}>
                                <Plus className="mr-2 h-4 w-4" /> Recargar saldo
                            </Button>
                        )}
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    Saldo total en billeteras
                                </CardDescription>
                                <CardTitle className="text-2xl">
                                    {formatCurrency(totalSaldo)}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">
                                    {(wallets ?? []).length} billeteras
                                    registradas
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    Comisiones acreditadas
                                </CardDescription>
                                <CardTitle className="text-2xl">
                                    {formatCurrency(totalComisiones)}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">
                                    Acreditadas vía reglas de comisión
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    Usuarios con billetera
                                </CardDescription>
                                <CardTitle className="text-2xl">
                                    {cashiers.length}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">
                                    Cajeros activos en el sistema
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Usuarios y saldos</CardTitle>
                            <CardDescription>
                                Saldo actual, estado de billetera y comisiones
                                generadas por cajero
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-xs sm:text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="py-3 text-left font-medium">
                                                Cajero
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Saldo
                                            </th>
                                            <th className="py-3 text-left font-medium">
                                                Estado
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Comisiones
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(wallets ?? []).map((wallet) => (
                                            <tr
                                                key={wallet.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-3">
                                                    <div className="font-medium">
                                                        {wallet.cashier?.name ??
                                                            'Sin cajero'}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {wallet.cashier?.email}
                                                    </div>
                                                    <div className="font-mono text-xs">
                                                        {wallet.account_number}
                                                    </div>
                                                </td>
                                                <td className="py-3 text-right font-bold">
                                                    {formatCurrency(
                                                        Number(
                                                            wallet.balance,
                                                        ) || 0,
                                                        wallet.currency,
                                                    )}
                                                </td>
                                                <td className="py-3">
                                                    <Badge
                                                        className={
                                                            wallet.status ===
                                                            'active'
                                                                ? 'bg-green-500'
                                                                : 'bg-amber-500'
                                                        }
                                                    >
                                                        {wallet.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 text-right font-bold">
                                                    {formatCurrency(
                                                        Number(
                                                            wallet.total_comisiones,
                                                        ) || 0,
                                                        wallet.currency,
                                                    )}
                                                </td>
                                                <td className="py-3 text-right">
                                                    {canCreate &&
                                                        wallet.cashier && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-8"
                                                                onClick={() =>
                                                                    abrirRecarga(
                                                                        wallet
                                                                            .cashier
                                                                            ?.id,
                                                                    )
                                                                }
                                                            >
                                                                Recargar
                                                            </Button>
                                                        )}
                                                </td>
                                            </tr>
                                        ))}
                                        {(wallets ?? []).length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No hay billeteras
                                                    registradas
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Reglas de comisión</CardTitle>
                            <CardDescription>
                                Porcentaje o monto fijo sobre la venta, con
                                monto mínimo de aprobación
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {canEdit && (
                                <form
                                    onSubmit={submitRegla}
                                    className="grid gap-4 md:grid-cols-6"
                                >
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold uppercase">
                                            Cajero
                                        </Label>
                                        <select
                                            value={reglaForm.data.user_id}
                                            onChange={(e) =>
                                                reglaForm.setData(
                                                    'user_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex h-9 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                        >
                                            <option value="">
                                                General (comercio)
                                            </option>
                                            {cashiers.map((w) => (
                                                <option
                                                    key={w.id}
                                                    value={w.user_id}
                                                >
                                                    {w.cashier?.name}
                                                </option>
                                            ))}
                                        </select>
                                        {reglaForm.errors.user_id && (
                                            <p className="text-xs text-destructive">
                                                {reglaForm.errors.user_id}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold uppercase">
                                            Tipo
                                        </Label>
                                        <select
                                            value={
                                                reglaForm.data.commission_type
                                            }
                                            onChange={(e) =>
                                                reglaForm.setData(
                                                    'commission_type',
                                                    e.target.value,
                                                )
                                            }
                                            className="flex h-9 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                        >
                                            <option value="percentage">
                                                Porcentaje
                                            </option>
                                            <option value="fixed">
                                                Monto fijo
                                            </option>
                                        </select>
                                        {reglaForm.errors.commission_type && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    reglaForm.errors
                                                        .commission_type
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold uppercase">
                                            Valor
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={
                                                reglaForm.data.commission_value
                                            }
                                            onChange={(e) =>
                                                reglaForm.setData(
                                                    'commission_value',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-9"
                                        />
                                        {reglaForm.errors.commission_value && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    reglaForm.errors
                                                        .commission_value
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold uppercase">
                                            Mínimo venta
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={
                                                reglaForm.data.min_sale_amount
                                            }
                                            onChange={(e) =>
                                                reglaForm.setData(
                                                    'min_sale_amount',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-9"
                                        />
                                        {reglaForm.errors.min_sale_amount && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    reglaForm.errors
                                                        .min_sale_amount
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-end">
                                        <Button
                                            type="submit"
                                            className="w-full rounded-full px-4"
                                            disabled={reglaForm.processing}
                                        >
                                            Guardar regla
                                        </Button>
                                    </div>
                                </form>
                            )}

                            <div className="mt-6 space-y-2">
                                {(commission_rules ?? []).map((rule) => (
                                    <div
                                        key={rule.id}
                                        className="flex items-center justify-between rounded-lg border p-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <ShieldCheck className="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <div className="font-medium">
                                                    {rule.commission_type ===
                                                    'percentage'
                                                        ? `${rule.commission_value}%`
                                                        : formatCurrency(
                                                              Number(
                                                                  rule.commission_value,
                                                              ) || 0,
                                                              'CLP',
                                                          )}{' '}
                                                    sobre la venta
                                                    {Number(
                                                        rule.min_sale_amount,
                                                    ) > 0 &&
                                                        ` (mín. ${formatCurrency(Number(rule.min_sale_amount) || 0, 'CLP')})`}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {rule.user_id
                                                        ? `Cajero: ${rule.cashier?.name ?? ''}`
                                                        : 'Regla general del comercio'}
                                                </div>
                                            </div>
                                        </div>
                                        <Badge
                                            className={
                                                rule.is_active
                                                    ? 'bg-green-500'
                                                    : 'bg-gray-500'
                                            }
                                        >
                                            {rule.is_active
                                                ? 'Activa'
                                                : 'Inactiva'}
                                        </Badge>
                                    </div>
                                ))}
                                {(commission_rules ?? []).length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No hay reglas de comisión configuradas.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Historial de transacciones</CardTitle>
                            <CardDescription>
                                Libro mayor de movimientos con filtros por
                                cajero, tipo y rango de fechas
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={aplicarFiltros}
                                className="mb-4 grid gap-2 md:grid-cols-6"
                            >
                                <select
                                    value={filtrosForm.cashier_id}
                                    onChange={(e) =>
                                        setFiltrosForm({
                                            ...filtrosForm,
                                            cashier_id: e.target.value
                                                ? Number(e.target.value)
                                                : '',
                                        })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-2 py-1 text-sm"
                                >
                                    <option value="">Todos los cajeros</option>
                                    {cashiers.map((w) => (
                                        <option key={w.id} value={w.user_id}>
                                            {w.cashier?.name}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    value={filtrosForm.type}
                                    onChange={(e) =>
                                        setFiltrosForm({
                                            ...filtrosForm,
                                            type: e.target.value,
                                        })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-2 py-1 text-sm"
                                >
                                    <option value="">Todos los tipos</option>
                                    <option value="credit">Crédito</option>
                                    <option value="debit">Débito</option>
                                </select>
                                <select
                                    value={filtrosForm.category}
                                    onChange={(e) =>
                                        setFiltrosForm({
                                            ...filtrosForm,
                                            category: e.target.value,
                                        })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-2 py-1 text-sm"
                                >
                                    <option value="">
                                        Todas las categorías
                                    </option>
                                    <option value="master_recharge">
                                        Recarga master
                                    </option>
                                    <option value="sale_commission">
                                        Comisión
                                    </option>
                                    <option value="withdrawal">Retiro</option>
                                    <option value="adjustment">Ajuste</option>
                                    <option value="refund">Reversa</option>
                                    <option value="transfer">
                                        Transferencia
                                    </option>
                                </select>
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
                                    aria-label="Desde"
                                />
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
                                    aria-label="Hasta"
                                />
                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="h-9 flex-1"
                                    >
                                        Filtrar
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="h-9"
                                        onClick={limpiarFiltros}
                                    >
                                        Limpiar
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
                                                Cajero
                                            </th>
                                            <th className="py-3 text-left font-medium">
                                                Concepto
                                            </th>
                                            <th className="py-3 text-left font-medium">
                                                Categoría
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Monto
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Saldo
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
                                                    {t.wallet?.user?.name ??
                                                        '—'}
                                                </td>
                                                <td className="py-3">
                                                    {t.description}
                                                </td>
                                                <td className="py-3 uppercase">
                                                    {t.category}
                                                </td>
                                                <td className="py-3 text-right font-bold">
                                                    {t.type === 'credit'
                                                        ? '+'
                                                        : '-'}
                                                    {formatCurrency(
                                                        Number(t.amount) || 0,
                                                        'CLP',
                                                    )}
                                                </td>
                                                <td className="py-3 text-right">
                                                    {formatCurrency(
                                                        Number(
                                                            t.balance_after,
                                                        ) || 0,
                                                        'CLP',
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                        {transaccionesData.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron
                                                    transacciones
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {transacciones && (
                                <Pagination
                                    links={transacciones.links}
                                    meta={{
                                        from: transacciones.meta.from ?? 0,
                                        to: transacciones.meta.to ?? 0,
                                        total: transacciones.meta.total,
                                    }}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[85vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl md:max-w-md">
                    <DialogHeader className="bg-gradient-to-r from-green-600 to-emerald-700 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            Recarga Master —{' '}
                            {cajeroSeleccionado?.name ?? 'Usuario'}
                        </DialogTitle>
                        <DialogDescription className="text-white/90">
                            Confirma la recarga del saldo disponible
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={submitRecarga}
                        className="px-4 py-4 md:px-6"
                    >
                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold uppercase">
                                    Billetera
                                </Label>
                                <select
                                    value={cajeroIdRecarga}
                                    onChange={(e) =>
                                        setCajeroIdRecarga(e.target.value)
                                    }
                                    className="flex h-9 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                    required
                                >
                                    <option value="">
                                        Selecciona un usuario
                                    </option>
                                    {cajeros.map((cajero) => (
                                        <option
                                            key={cajero.user_id}
                                            value={cajero.user_id}
                                        >
                                            {cajero.name} —{' '}
                                            {cajero.wallet_id
                                                ? formatCurrency(
                                                      Number(cajero.balance) ||
                                                          0,
                                                      cajero.currency,
                                                  )
                                                : 'Sin billetera'}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold uppercase">
                                    Monto (CLP)
                                </Label>
                                <Input
                                    type="number"
                                    step="1"
                                    min="0.01"
                                    value={recargaForm.data.amount}
                                    onChange={(e) =>
                                        recargaForm.setData(
                                            'amount',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="0"
                                    required
                                    className="h-9"
                                />
                                {recargaForm.errors.amount && (
                                    <p className="text-xs text-destructive">
                                        {recargaForm.errors.amount}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold uppercase">
                                    Motivo / comprobante
                                </Label>
                                <Input
                                    value={recargaForm.data.note}
                                    onChange={(e) =>
                                        recargaForm.setData(
                                            'note',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ej. Recarga de efectivo por cierre de caja"
                                    className="h-9"
                                />
                                {recargaForm.errors.note && (
                                    <p className="text-xs text-destructive">
                                        {recargaForm.errors.note}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end gap-2 border-t pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full px-4"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    recargaForm.processing || !cajeroIdRecarga
                                }
                                className="rounded-full px-6"
                            >
                                Confirmar recarga
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
