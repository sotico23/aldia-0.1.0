import { Link, useForm, usePoll } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeftRight, Copy, HandCoins, QrCode } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import {
    store as createPaymentLink,
    show as showPaymentLink,
} from '@/actions/App/Http/Controllers/Backend/WalletPaymentLinkController';
import {
    store as requestPayment,
    resolve as resolvePayment,
} from '@/actions/App/Http/Controllers/Backend/WalletPaymentRequestController';
import {
    recipient as findRecipient,
    store as transfer,
} from '@/actions/App/Http/Controllers/Backend/WalletTransferController';
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
import Pagination from '@/components/ui/Pagination';
import { formatWalletAmount as formatCurrency } from '@/lib/wallet';

interface PaymentRequest {
    id: number;
    amount: string;
    currency: string;
    note: string | null;
    status: string;
    incoming: boolean;
    counterparty: string;
    created_at: string;
    share_token?: string;
}

export interface WalletPaymentRequests {
    data: PaymentRequest[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Recipient {
    account_number: string;
    name: string;
    currency: string;
}

const statuses: Record<string, string> = {
    pending: 'Pendiente',
    paid: 'Pagada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada',
};

export default function WalletOperations({
    wallet,
    requests,
}: {
    wallet: {
        account_number: string;
        balance: number;
        currency: string;
        status: string;
    };
    requests?: WalletPaymentRequests;
}) {
    usePoll(30000, { only: ['wallet', 'payment_requests', 'transacciones'] });
    const [mode, setMode] = useState<'transfer' | 'request' | 'link' | null>(
        null,
    );
    const [recipient, setRecipient] = useState<Recipient | null>(null);
    const [checking, setChecking] = useState(false);
    const [selectedPayment, setSelectedPayment] =
        useState<PaymentRequest | null>(null);
    const operation = useForm({
        account_number: '',
        amount: '',
        note: '',
        request_key: crypto.randomUUID(),
    });
    const decision = useForm({ decision: '' });
    const active = wallet.status === 'active';

    function open(nextMode: 'transfer' | 'request' | 'link') {
        operation.reset();
        operation.clearErrors();
        operation.setData('request_key', crypto.randomUUID());
        setRecipient(null);
        setMode(nextMode);
    }

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (mode === 'link') {
            operation.post(createPaymentLink().url, {
                onSuccess: () => setMode(null),
            });
            return;
        }
        if (!recipient) {
            setChecking(true);
            operation.clearErrors();
            try {
                const { data } = await axios.get<Recipient>(
                    findRecipient.url({
                        query: {
                            account_number: operation.data.account_number,
                        },
                    }),
                );
                if (data.currency !== wallet.currency) {
                    operation.setError(
                        'account_number',
                        'La billetera debe utilizar la misma moneda.',
                    );
                } else {
                    setRecipient(data);
                }
            } catch (error) {
                const message = axios.isAxiosError(error)
                    ? error.response?.data?.errors?.account_number?.[0]
                    : null;
                operation.setError(
                    'account_number',
                    message ||
                        'No se pudo verificar la billetera. Intenta nuevamente.',
                );
            } finally {
                setChecking(false);
            }
            return;
        }
        operation.post(
            (mode === 'transfer' ? transfer() : requestPayment()).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMode(null);
                    operation.reset();
                    operation.setData('request_key', crypto.randomUUID());
                },
            },
        );
    }

    function resolve(
        payment: PaymentRequest,
        action: 'accept' | 'reject' | 'cancel',
    ) {
        decision.clearErrors();
        decision.transform(() => ({ decision: action }));
        decision.post(resolvePayment(payment.id).url, {
            preserveScroll: true,
            onSuccess: () => setSelectedPayment(null),
        });
    }

    return (
        <>
            <Card className="min-w-0">
                <CardHeader>
                    <CardTitle>Tu número de billetera</CardTitle>
                    <CardDescription>
                        Personal y permanente. Compártelo para recibir
                        transferencias o solicitudes de pago.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="flex min-w-0 items-center gap-2">
                        <span className="min-w-0 font-mono text-lg font-semibold tracking-wider break-all">
                            {wallet.account_number}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Copiar número de billetera"
                            onClick={async () => {
                                try {
                                    await navigator.clipboard.writeText(
                                        wallet.account_number,
                                    );
                                    toast.success('Número copiado');
                                } catch {
                                    toast.error(
                                        'No se pudo copiar. Selecciona el número para copiarlo.',
                                    );
                                }
                            }}
                        >
                            <Copy aria-hidden="true" />
                        </Button>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button
                            disabled={!active || Number(wallet.balance) <= 0}
                            onClick={() => open('transfer')}
                        >
                            <ArrowLeftRight aria-hidden="true" />
                            Transferir
                        </Button>
                        <Button
                            variant="outline"
                            disabled={!active}
                            onClick={() => open('request')}
                        >
                            <HandCoins aria-hidden="true" />
                            Solicitar pago
                        </Button>
                        <Button
                            variant="outline"
                            disabled={!active}
                            onClick={() => open('link')}
                        >
                            <QrCode aria-hidden="true" />
                            Crear enlace de pago
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card className="min-w-0">
                <CardHeader>
                    <CardTitle>Solicitudes de pago</CardTitle>
                    <CardDescription>
                        Recibidas y enviadas. El saldo se mueve únicamente
                        cuando el pagador acepta.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {!selectedPayment &&
                        Object.values(decision.errors).map((error) => (
                            <p
                                key={error}
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        ))}
                    {!requests?.data.length && (
                        <p className="text-sm text-muted-foreground">
                            No tienes solicitudes de pago.
                        </p>
                    )}
                    {requests?.data.map((payment) => (
                        <div
                            key={payment.id}
                            className="flex min-w-0 flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0 space-y-1">
                                <p className="font-medium break-words">
                                    {payment.incoming
                                        ? 'Te solicita'
                                        : 'Solicitaste a'}
                                    : {payment.counterparty}
                                </p>
                                <p>
                                    {formatCurrency(
                                        Number(payment.amount),
                                        payment.currency,
                                    )}{' '}
                                    ·{' '}
                                    {statuses[payment.status] ?? payment.status}
                                </p>
                                {payment.note && (
                                    <p className="text-sm break-words text-muted-foreground">
                                        {payment.note}
                                    </p>
                                )}
                                {!payment.incoming &&
                                    payment.share_token &&
                                    payment.status === 'pending' && (
                                        <Button
                                            asChild
                                            variant="link"
                                            className="h-auto px-0"
                                        >
                                            <Link
                                                href={showPaymentLink(
                                                    payment.share_token,
                                                )}
                                            >
                                                <QrCode aria-hidden="true" />
                                                Enlace y QR
                                            </Link>
                                        </Button>
                                    )}
                            </div>
                            {payment.status === 'pending' && (
                                <div className="flex shrink-0 flex-wrap gap-2">
                                    {payment.incoming ? (
                                        <>
                                            <Button
                                                disabled={
                                                    !active ||
                                                    decision.processing
                                                }
                                                onClick={() => {
                                                    decision.clearErrors();
                                                    setSelectedPayment(payment);
                                                }}
                                            >
                                                Revisar pago
                                            </Button>
                                            <Button
                                                variant="outline"
                                                disabled={decision.processing}
                                                onClick={() =>
                                                    resolve(payment, 'reject')
                                                }
                                            >
                                                Rechazar
                                            </Button>
                                        </>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            disabled={decision.processing}
                                            onClick={() =>
                                                resolve(payment, 'cancel')
                                            }
                                        >
                                            Cancelar solicitud
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                    {!!requests?.links.length && (
                        <Pagination links={requests.links} />
                    )}
                </CardContent>
            </Card>

            <Dialog
                open={mode !== null}
                onOpenChange={(open) => {
                    if (!open && !operation.processing && !checking) {
                        setMode(null);
                    }
                }}
            >
                <DialogContent className="flex max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] flex-col overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {mode === 'transfer'
                                ? 'Transferir saldo'
                                : mode === 'link'
                                  ? 'Crear enlace de pago'
                                  : 'Solicitar pago'}
                        </DialogTitle>
                        <DialogDescription>
                            {mode === 'transfer'
                                ? `Disponible: ${formatCurrency(Number(wallet.balance), wallet.currency)}`
                                : mode === 'link'
                                  ? 'Indica el monto y comparte el enlace o el QR para recibir un único pago.'
                                  : 'El otro usuario recibirá la solicitud y decidirá si acepta pagarla con su saldo disponible.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submit}>
                        {recipient ? (
                            <div className="space-y-2 rounded-lg border bg-muted/30 p-3">
                                <p className="font-semibold break-words">
                                    {recipient.name}
                                </p>
                                <p className="font-mono text-sm break-all">
                                    {recipient.account_number}
                                </p>
                                <p>
                                    {mode === 'transfer'
                                        ? 'Enviarás'
                                        : 'Solicitarás'}{' '}
                                    {formatCurrency(
                                        Number(operation.data.amount),
                                        wallet.currency,
                                    )}
                                </p>
                                {operation.data.note && (
                                    <p className="text-sm break-words">
                                        {operation.data.note}
                                    </p>
                                )}
                                <Button
                                    type="button"
                                    variant="link"
                                    disabled={operation.processing}
                                    onClick={() => setRecipient(null)}
                                >
                                    Modificar datos
                                </Button>
                            </div>
                        ) : (
                            <>
                                {mode !== 'link' && (
                                    <div className="space-y-2">
                                        <Label htmlFor="wallet-account">
                                            Número de billetera del otro usuario
                                        </Label>
                                        <Input
                                            id="wallet-account"
                                            inputMode="numeric"
                                            autoComplete="off"
                                            required
                                            pattern="[0-9]{16}"
                                            maxLength={16}
                                            value={
                                                operation.data.account_number
                                            }
                                            onChange={(e) =>
                                                operation.setData(
                                                    'account_number',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                )}
                                <div className="space-y-2">
                                    <Label htmlFor="wallet-amount">
                                        Monto ({wallet.currency})
                                    </Label>
                                    <Input
                                        id="wallet-amount"
                                        type="number"
                                        inputMode="decimal"
                                        required
                                        min="0.01"
                                        step="0.01"
                                        max={
                                            mode === 'transfer'
                                                ? Number(wallet.balance)
                                                : 9999999999.99
                                        }
                                        value={operation.data.amount}
                                        onChange={(e) =>
                                            operation.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="wallet-note">
                                        Concepto (opcional)
                                    </Label>
                                    <Input
                                        id="wallet-note"
                                        maxLength={255}
                                        value={operation.data.note}
                                        onChange={(e) =>
                                            operation.setData(
                                                'note',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </>
                        )}
                        {Object.values(operation.errors).map((error) => (
                            <p
                                key={error}
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        ))}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={operation.processing || checking}
                                onClick={() => setMode(null)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={operation.processing || checking}
                            >
                                {mode === 'link'
                                    ? 'Crear enlace y QR'
                                    : checking
                                      ? 'Verificando…'
                                      : !recipient
                                        ? 'Revisar datos'
                                        : mode === 'transfer'
                                          ? 'Confirmar transferencia'
                                          : 'Enviar solicitud de pago'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={selectedPayment !== null}
                onOpenChange={(open) => {
                    if (!open && !decision.processing) {
                        setSelectedPayment(null);
                    }
                }}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Confirmar pago</DialogTitle>
                        <DialogDescription>
                            Revisa el destinatario y el monto antes de pagar.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedPayment && (
                        <div className="min-w-0 space-y-3">
                            <p className="font-semibold break-words">
                                Pagar a {selectedPayment.counterparty}
                            </p>
                            <p>
                                {formatCurrency(
                                    Number(selectedPayment.amount),
                                    selectedPayment.currency,
                                )}
                            </p>
                            {selectedPayment.note && (
                                <p className="break-words">
                                    {selectedPayment.note}
                                </p>
                            )}
                            <p className="text-sm text-muted-foreground">
                                Disponible:{' '}
                                {formatCurrency(
                                    Number(wallet.balance),
                                    wallet.currency,
                                )}
                            </p>
                            {Number(selectedPayment.amount) >
                                Number(wallet.balance) && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    Saldo insuficiente para aceptar este pago.
                                </p>
                            )}
                            {Object.values(decision.errors).map((error) => (
                                <p
                                    key={error}
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {error}
                                </p>
                            ))}
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    disabled={decision.processing}
                                    onClick={() => setSelectedPayment(null)}
                                >
                                    Volver
                                </Button>
                                <Button
                                    disabled={
                                        !active ||
                                        decision.processing ||
                                        Number(selectedPayment.amount) >
                                            Number(wallet.balance)
                                    }
                                    onClick={() =>
                                        resolve(selectedPayment, 'accept')
                                    }
                                >
                                    Confirmar pago
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
