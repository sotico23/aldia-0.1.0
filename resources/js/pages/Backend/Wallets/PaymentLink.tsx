import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { Copy, Download, Share2, Wallet } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { index as personalWallet } from '@/actions/App/Http/Controllers/Backend/CajeroWalletController';
import {
    show,
    pay,
} from '@/actions/App/Http/Controllers/Backend/WalletPaymentLinkController';
import { resolve } from '@/actions/App/Http/Controllers/Backend/WalletPaymentRequestController';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatWalletAmount } from '@/lib/wallet';

export interface PaymentLinkProps {
    payment: {
        id: number;
        token: string;
        amount: string;
        currency: string;
        note: string | null;
        status: string;
        recipient: string;
        account_number: string;
        is_owner: boolean;
        url: string;
        qr_code: string;
        can_pay: boolean;
    };
    wallet: { balance: string; currency: string };
}

const statuses: Record<string, string> = {
    pending: 'Pendiente',
    paid: 'Pagado',
    rejected: 'Rechazado',
    cancelled: 'Cancelado',
};

export default function PaymentLink({ payment, wallet }: PaymentLinkProps) {
    usePoll(30000, { only: ['payment', 'wallet'] });
    const [confirming, setConfirming] = useState(false);
    const form = useForm({});
    const cancel = useForm({ decision: 'cancel' });
    const amount = formatWalletAmount(payment.amount, payment.currency);

    async function copyLink() {
        try {
            await navigator.clipboard.writeText(payment.url);
            toast.success('Enlace copiado');
        } catch {
            toast.error(
                'No se pudo copiar. Puedes seleccionar y copiar el enlace.',
            );
        }
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Mi billetera', href: personalWallet.url() },
                { title: 'Enlace de pago', href: show.url(payment.token) },
            ]}
        >
            <Head title="Enlace de pago" />
            <div className="mx-auto flex w-full max-w-2xl min-w-0 flex-col gap-4 p-4 sm:p-6">
                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Wallet aria-hidden="true" />
                            Enlace de pago
                        </CardTitle>
                        <CardDescription>
                            {payment.is_owner
                                ? 'Comparte el enlace o el QR para recibir este pago una sola vez.'
                                : 'Revisa los datos antes de pagar con tu billetera.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="min-w-0 space-y-2">
                            <p className="text-sm text-muted-foreground">
                                Pagar a
                            </p>
                            <p className="text-xl font-semibold break-words">
                                {payment.recipient}
                            </p>
                            <p className="font-mono text-sm break-all">
                                {payment.account_number}
                            </p>
                            <p className="text-3xl font-bold break-words">
                                {amount}
                            </p>
                            {payment.note && (
                                <p className="break-words">{payment.note}</p>
                            )}
                            <p role="status" className="font-medium">
                                {statuses[payment.status] ?? payment.status}
                            </p>
                        </div>
                        {payment.status === 'pending' && (
                            <>
                                <img
                                    src={payment.qr_code}
                                    alt="Código QR del enlace de pago"
                                    className="mx-auto aspect-square h-auto w-full max-w-72 rounded-lg border bg-white p-2"
                                    width={320}
                                    height={320}
                                />
                                <div className="space-y-2">
                                    <Label htmlFor="wallet-payment-url">
                                        Enlace para pagar
                                    </Label>
                                    <Input
                                        id="wallet-payment-url"
                                        readOnly
                                        value={payment.url}
                                        onFocus={(event) =>
                                            event.target.select()
                                        }
                                    />
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={copyLink}
                                    >
                                        <Copy aria-hidden="true" />
                                        Copiar enlace
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={async () => {
                                            if (!navigator.share) {
                                                await copyLink();
                                                return;
                                            }
                                            try {
                                                await navigator.share({
                                                    title: `Solicitud de pago ${amount}`,
                                                    url: payment.url,
                                                });
                                            } catch (error) {
                                                if (!(
                                                    error instanceof
                                                        DOMException &&
                                                    error.name === 'AbortError'
                                                )) {
                                                    toast.error(
                                                        'No se pudo compartir el enlace.',
                                                    );
                                                }
                                            }
                                        }}
                                    >
                                        <Share2 aria-hidden="true" />
                                        Compartir
                                    </Button>
                                    <Button asChild variant="outline">
                                        <a
                                            href={payment.qr_code}
                                            download={`pago-${payment.id}.svg`}
                                        >
                                            <Download aria-hidden="true" />
                                            Descargar QR
                                        </a>
                                    </Button>
                                </div>
                            </>
                        )}
                        {!payment.is_owner && payment.status === 'pending' && (
                            <div className="space-y-3 border-t pt-4">
                                <p className="text-sm">
                                    Tu saldo disponible:{' '}
                                    {formatWalletAmount(
                                        wallet.balance,
                                        wallet.currency,
                                    )}
                                </p>
                                {!payment.can_pay && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        Para pagar necesitas saldo suficiente y
                                        ambas billeteras activas en la moneda
                                        del cobro.
                                    </p>
                                )}
                                <Button
                                    className="w-full"
                                    disabled={
                                        !payment.can_pay || form.processing
                                    }
                                    onClick={() => {
                                        form.clearErrors();
                                        setConfirming(true);
                                    }}
                                >
                                    Revisar y pagar
                                </Button>
                            </div>
                        )}
                        {payment.is_owner && payment.status === 'pending' && (
                            <Button
                                variant="outline"
                                disabled={cancel.processing}
                                onClick={() =>
                                    cancel.post(resolve(payment.id).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Cancelar enlace de pago
                            </Button>
                        )}
                        {Object.values(cancel.errors).map((error) => (
                            <p
                                role="alert"
                                key={error}
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        ))}
                        <Button asChild variant="link">
                            <Link href={personalWallet()}>
                                Volver a mi billetera
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
            <Dialog
                open={confirming}
                onOpenChange={(open) => {
                    if (!form.processing) {
                        setConfirming(open);
                    }
                }}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Confirmar pago</DialogTitle>
                        <DialogDescription>
                            Se descontará {amount} de tu billetera.
                        </DialogDescription>
                    </DialogHeader>
                    <p className="font-semibold break-words">
                        Destinatario: {payment.recipient}
                    </p>
                    {payment.note && (
                        <p className="break-words">{payment.note}</p>
                    )}
                    {Object.values(form.errors as Record<string, string>).map(
                        (error) => (
                            <p
                                role="alert"
                                key={error}
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        ),
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setConfirming(false)}
                        >
                            Volver
                        </Button>
                        <Button
                            disabled={form.processing || !payment.can_pay}
                            onClick={() =>
                                form.post(pay(payment.token).url, {
                                    preserveScroll: true,
                                    onSuccess: () => setConfirming(false),
                                })
                            }
                        >
                            Confirmar pago
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
