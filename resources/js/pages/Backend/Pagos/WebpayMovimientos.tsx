import { Head, Link } from '@inertiajs/react';
import { CreditCard, RefreshCw, FileText, Activity } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { sanitize } from '@/lib/sanitize';

const pasarelaIcons: Record<string, string> = {
    webpay: '💳',
    mercadopago: '🟣',
    paypal: '🔵',
};

const pasarelaLabels: Record<string, string> = {
    webpay: 'Webpay',
    mercadopago: 'MercadoPago',
    paypal: 'PayPal',
};

const pasarelaColors: Record<string, string> = {
    webpay: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    mercadopago:
        'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    paypal: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
};

export default function WebpayMovimientos({
    transactions,
}: {
    transactions: any;
}) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('finanzas.tesoreria.viewAny');
    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'approved':
                return (
                    <Badge className="border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                        Aprobado
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge className="border-red-200 bg-red-100 text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
                        Rechazado
                    </Badge>
                );
            case 'pending':
                return (
                    <Badge className="border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                        Pendiente
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline">{status || 'Desconocido'}</Badge>
                );
        }
    };

    const formatAmount = (amount: number | string) => {
        const num = typeof amount === 'string' ? parseFloat(amount) : amount;
        return isNaN(num) ? '$0' : `$${num.toLocaleString(currency.locale)}`;
    };

    const renderDetails = (tx: any) => {
        if (tx.pasarela === 'webpay' && tx.details) {
            if (tx.status === 'approved') {
                return (
                    <div className="flex flex-col gap-1 text-xs">
                        <span className="text-slate-600 dark:text-slate-300">
                            Auth:{' '}
                            <code className="rounded bg-slate-100 px-1 dark:bg-slate-800">
                                {tx.details.authorization_code}
                            </code>
                        </span>
                        {tx.details.installments > 0 && (
                            <span className="text-slate-500 dark:text-slate-400">
                                Cuotas: {tx.details.installments}
                            </span>
                        )}
                    </div>
                );
            }
            if (tx.status === 'failed') {
                return (
                    <span className="max-w-[150px] truncate text-xs text-red-500">
                        {tx.details?.error || 'Rechazado'}
                    </span>
                );
            }
        }

        if (tx.pasarela === 'mercadopago' && tx.details) {
            return (
                <div className="text-xs text-slate-500 dark:text-slate-400">
                    <span>ID: </span>
                    <code className="rounded bg-slate-100 px-1 dark:bg-slate-800">
                        {tx.details.payment_id || tx.details.id || '—'}
                    </code>
                </div>
            );
        }

        if (tx.pasarela === 'paypal' && tx.details) {
            return (
                <div className="text-xs text-slate-500 dark:text-slate-400">
                    <span>Order: </span>
                    <code className="rounded bg-slate-100 px-1 dark:bg-slate-800">
                        {tx.details.paypal_order_id ||
                            tx.details.orderID ||
                            '—'}
                    </code>
                </div>
            );
        }

        return <span className="text-xs text-slate-400 italic">—</span>;
    };

    if (!canAccess) {
        return (
            <AppLayout
                breadcrumbs={[
                    { title: 'Pagos en Línea', href: '/webpay/config' },
                    { title: 'Movimientos', href: '/webpay/movimientos' },
                ]}
            >
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pagos en Línea', href: '/webpay/config' },
                { title: 'Movimientos', href: '/webpay/movimientos' },
            ]}
        >
            <Head title="Movimientos de Pago" />

            <div className="mx-auto min-h-0 w-full overflow-y-auto px-2 py-3 pb-24 sm:max-w-6xl sm:px-4 sm:py-6">
                <div className="mb-4 flex flex-col gap-3 rounded-xl border-0 bg-gradient-to-r from-indigo-500 to-purple-600 p-3 text-white sm:mb-6 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:rounded-[2rem] sm:p-6">
                    <div className="flex items-start gap-3 sm:gap-4">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/20 sm:h-12 sm:w-12 sm:rounded-xl">
                            <Activity className="h-5 w-5 sm:h-6 sm:w-6" />
                        </div>
                        <div className="min-w-0">
                            <h1 className="mb-0.5 truncate text-lg font-black tracking-tight sm:mb-1 sm:text-2xl">
                                Movimientos de Pago
                            </h1>
                            <p className="line-clamp-2 text-xs font-medium text-white/80 sm:line-clamp-none sm:text-sm">
                                Historial de Webpay, MercadoPago y PayPal
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-1.5">
                        <Badge className="border-0 bg-white/20 px-2 py-0.5 text-[10px] text-white">
                            💳
                        </Badge>
                        <Badge className="border-0 bg-white/20 px-2 py-0.5 text-[10px] text-white">
                            🟣
                        </Badge>
                        <Badge className="border-0 bg-white/20 px-2 py-0.5 text-[10px] text-white">
                            🔵
                        </Badge>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-white shadow-sm sm:rounded-[2rem] dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-col gap-2 border-b border-slate-100 p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 sm:p-4 dark:border-slate-800">
                        <h2 className="flex items-center gap-1.5 text-sm font-black tracking-tight uppercase sm:gap-2 sm:text-lg">
                            <CreditCard className="h-4 w-4 text-indigo-500 sm:h-5 sm:w-5" />
                            <span className="hidden sm:inline">
                                Todas las Transacciones
                            </span>
                            <span className="sm:hidden">Transacciones</span>
                        </h2>
                        <Link
                            href="/webpay/movimientos"
                            className="flex items-center gap-1 text-xs font-bold tracking-wider text-muted-foreground uppercase hover:text-foreground"
                        >
                            <RefreshCw className="h-3 w-3 sm:h-4 sm:w-4" />
                            <span className="hidden sm:inline">Actualizar</span>
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[500px] text-left text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                            <thead className="bg-slate-50 text-[10px] font-black tracking-widest text-slate-700 uppercase sm:text-xs dark:bg-slate-800/50 dark:text-slate-300">
                                <tr>
                                    <th className="px-3 py-2 sm:px-6 sm:py-4">
                                        Fecha
                                    </th>
                                    <th className="px-3 py-2 sm:px-6 sm:py-4">
                                        Pasarela
                                    </th>
                                    <th className="px-3 py-2 sm:px-6 sm:py-4">
                                        Orden
                                    </th>
                                    <th className="px-3 py-2 text-right sm:px-6 sm:py-4">
                                        Monto
                                    </th>
                                    <th className="px-3 py-2 sm:px-6 sm:py-4">
                                        Estado
                                    </th>
                                    <th className="px-3 py-2 sm:px-6 sm:py-4">
                                        Detalles
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.data.length > 0 ? (
                                    transactions.data.map((tx: any) => (
                                        <tr
                                            key={`${tx.pasarela}-${tx.id}`}
                                            className="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50"
                                        >
                                            <td className="px-3 py-2 font-medium whitespace-nowrap text-slate-900 sm:px-6 sm:py-4 dark:text-white">
                                                {new Date(
                                                    tx.created_at,
                                                ).toLocaleString(currency.locale)}
                                            </td>
                                            <td className="px-3 py-2 sm:px-6 sm:py-4">
                                                <span
                                                    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium sm:px-2.5 sm:py-1 sm:text-xs ${pasarelaColors[tx.pasarela] || 'bg-slate-100 text-slate-700'}`}
                                                >
                                                    <span className="hidden sm:inline">
                                                        {
                                                            pasarelaIcons[
                                                                tx.pasarela
                                                            ]
                                                        }
                                                    </span>
                                                    {pasarelaLabels[
                                                        tx.pasarela
                                                    ] || tx.pasarela}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 sm:px-6 sm:py-4">
                                                <code className="rounded bg-slate-100 px-1 py-0.5 text-[10px] text-slate-700 sm:px-2 sm:py-1 sm:text-xs dark:bg-slate-800 dark:text-slate-300">
                                                    {tx.buy_order}
                                                </code>
                                            </td>
                                            <td className="px-3 py-2 text-right font-black text-slate-900 sm:px-6 sm:py-4 dark:text-white">
                                                {formatAmount(tx.amount)}
                                            </td>
                                            <td className="px-3 py-2 sm:px-6 sm:py-4">
                                                {getStatusBadge(tx.status)}
                                            </td>
                                            <td className="px-3 py-2 sm:px-6 sm:py-4">
                                                {renderDetails(tx)}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-12 text-center"
                                        >
                                            <div className="flex flex-col items-center justify-center space-y-3">
                                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                                    <FileText className="h-8 w-8 text-slate-400" />
                                                </div>
                                                <div className="text-sm font-medium text-slate-900 dark:text-white">
                                                    No hay transacciones
                                                    registradas
                                                </div>
                                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                                    Aún no has procesado pagos a
                                                    través de ninguna pasarela.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {transactions.links && transactions.links.length > 3 && (
                        <div className="flex justify-center overflow-x-auto border-t border-slate-100 bg-slate-50 p-2 sm:p-4 dark:border-slate-800 dark:bg-slate-900/50">
                            <div className="flex gap-1">
                                {transactions.links.map(
                                    (link: any, idx: number) => (
                                        <Link
                                            key={idx}
                                            href={link.url || '#'}
                                            className={`rounded-md border px-1.5 py-1 text-[10px] font-medium whitespace-nowrap sm:px-3 sm:py-1.5 sm:text-xs ${
                                                link.active
                                                    ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm hover:bg-indigo-700'
                                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                                            } ${!link.url ? 'hidden cursor-not-allowed opacity-50' : ''}`}
                                            dangerouslySetInnerHTML={{
                                                __html: sanitize(link.label),
                                            }}
                                        />
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
