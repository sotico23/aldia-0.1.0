import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import {
    resolve,
    store,
} from '@/actions/App/Http/Controllers/Backend/WalletRefundController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import { Textarea } from '@/components/ui/textarea';
import { formatWalletAmount } from '@/lib/wallet';

const statuses = {
    pending: 'Pendiente de respuesta',
    rejected: 'Rechazado por el destinatario',
    escalated: 'En revisión por soporte',
    refunded: 'Reembolsado',
    denied: 'Rechazado por soporte',
};

interface Transfer {
    id: number;
    amount: string;
    currency: string;
    counterparty: string;
    created_at?: string;
    note?: string | null;
    refund_status: keyof typeof statuses | null;
    refund_reason: string | null;
    refund_response: string | null;
    can_request: boolean;
    can_respond: boolean;
    can_escalate: boolean;
    can_review: boolean;
}

export interface WalletRefundsPage {
    data: Transfer[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const actions = {
    request: 'Solicitar reembolso',
    accept: 'Aprobar reembolso',
    reject: 'Rechazar solicitud',
    escalate: 'Pedir revisión de soporte',
    deny: 'Rechazar reembolso',
} as const;

const dateFormat = new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'short',
    timeStyle: 'short',
});

type Action = keyof typeof actions;

const textFields: Partial<
    Record<Action, { name: 'reason' | 'response'; label: string }>
> = {
    request: { name: 'reason', label: 'Motivo del reembolso' },
    reject: { name: 'response', label: 'Motivo del rechazo' },
    deny: { name: 'response', label: 'Motivo del rechazo' },
};

export default function WalletRefunds({
    refunds,
}: {
    refunds?: WalletRefundsPage;
}) {
    const [selected, setSelected] = useState<{
        transfer: Transfer;
        action: Action;
    } | null>(null);
    const form = useForm({ reason: '', response: '', decision: '' });
    const textField = selected && textFields[selected.action];

    function open(transfer: Transfer, action: Action) {
        form.reset();
        form.clearErrors();
        setSelected({ transfer, action });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!selected) {
            return;
        }

        const { transfer, action } = selected;
        form.transform(({ reason, response }) =>
            action === 'request' ? { reason } : { decision: action, response },
        );
        form.submit(
            action === 'request' ? store(transfer.id) : resolve(transfer.id),
            {
                preserveScroll: true,
                onSuccess: () => setSelected(null),
            },
        );
    }

    const amount = (transfer: Transfer) =>
        formatWalletAmount(Number(transfer.amount), transfer.currency);

    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle>Reembolsos de billetera</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {!refunds?.data.length && (
                    <p className="text-sm text-muted-foreground">
                        Aquí podrás revisar reembolsos y solicitarlos sobre los
                        pagos enviados con tu billetera.
                    </p>
                )}
                {refunds?.data.map((transfer) => (
                    <div
                        key={transfer.id}
                        className="space-y-2 rounded-lg border p-3"
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-semibold break-words">
                                Pago #{transfer.id} · {transfer.counterparty} ·{' '}
                                {amount(transfer)}
                            </p>
                            {transfer.refund_status && (
                                <Badge variant="secondary">
                                    {statuses[transfer.refund_status]}
                                </Badge>
                            )}
                        </div>
                        {(transfer.created_at || transfer.note) && (
                            <p className="text-sm break-words text-muted-foreground">
                                {transfer.created_at &&
                                    dateFormat.format(
                                        new Date(transfer.created_at),
                                    )}
                                {transfer.created_at && transfer.note && ' · '}
                                {transfer.note}
                            </p>
                        )}
                        {transfer.refund_reason && (
                            <p className="text-sm break-words">
                                Motivo del reclamo · {transfer.refund_reason}
                            </p>
                        )}
                        {transfer.refund_response && (
                            <p className="text-sm break-words">
                                Respuesta · {transfer.refund_response}
                            </p>
                        )}
                        {transfer.refund_status === 'pending' &&
                            !transfer.can_respond &&
                            !transfer.can_escalate && (
                                <p className="text-sm text-muted-foreground">
                                    Puedes pedir revisión a soporte si pasan
                                    tres días sin respuesta.
                                </p>
                            )}
                        <div className="flex flex-wrap gap-2">
                            {transfer.can_request && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => open(transfer, 'request')}
                                >
                                    {actions.request}
                                </Button>
                            )}
                            {(transfer.can_respond || transfer.can_review) && (
                                <>
                                    <Button
                                        size="sm"
                                        onClick={() => open(transfer, 'accept')}
                                    >
                                        {actions.accept}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            open(
                                                transfer,
                                                transfer.can_review
                                                    ? 'deny'
                                                    : 'reject',
                                            )
                                        }
                                    >
                                        {transfer.can_review
                                            ? actions.deny
                                            : actions.reject}
                                    </Button>
                                </>
                            )}
                            {transfer.can_escalate && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => open(transfer, 'escalate')}
                                >
                                    {actions.escalate}
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
                {refunds && <Pagination links={refunds.links} />}
            </CardContent>
            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open && !form.processing) {
                        setSelected(null);
                    }
                }}
            >
                {selected && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {actions[selected.action]}
                            </DialogTitle>
                            <DialogDescription>
                                Pago #{selected.transfer.id} ·{' '}
                                {amount(selected.transfer)}
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submit} className="space-y-4">
                            {textField && (
                                <div className="space-y-2">
                                    <Label htmlFor="refund-details">
                                        {textField.label}
                                    </Label>
                                    <Textarea
                                        id="refund-details"
                                        required
                                        maxLength={1000}
                                        value={form.data[textField.name]}
                                        onChange={(event) =>
                                            form.setData(
                                                textField.name,
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            )}
                            {selected.action === 'accept' && (
                                <p className="text-sm">
                                    El monto completo volverá a la billetera
                                    desde la que se envió el pago.
                                </p>
                            )}
                            {Object.entries(form.errors).map(
                                ([field, error]) => (
                                    <p
                                        key={field}
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {error}
                                    </p>
                                ),
                            )}
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.processing}
                                    onClick={() => setSelected(null)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {actions[selected.action]}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                )}
            </Dialog>
        </Card>
    );
}
