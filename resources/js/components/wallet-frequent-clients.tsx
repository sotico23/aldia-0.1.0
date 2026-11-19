import { useForm } from '@inertiajs/react';
import { HandCoins, Pencil, Plus, Send, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
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
import { destroy, store, update } from '@/routes/billetera/frecuentes';

export interface ClienteFrecuente {
    id: number;
    nombre: string;
    account_number: string;
    nota: string | null;
}

export type ClienteFrecuenteAction = 'transfer' | 'request';

export default function WalletFrequentClients({
    clientes,
    disabled = false,
    onUse,
}: {
    clientes: ClienteFrecuente[];
    disabled?: boolean;
    onUse?: (cliente: ClienteFrecuente, action: ClienteFrecuenteAction) => void;
}) {
    const [editing, setEditing] = useState<ClienteFrecuente | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState<ClienteFrecuente | null>(null);
    const form = useForm({
        nombre: '',
        account_number: '',
        nota: '',
    });
    const deletion = useForm({});

    function create() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setDialogOpen(true);
    }

    function edit(cliente: ClienteFrecuente) {
        setEditing(cliente);
        form.clearErrors();
        form.setData({
            nombre: cliente.nombre,
            account_number: cliente.account_number,
            nota: cliente.nota ?? '',
        });
        setDialogOpen(true);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        };

        if (editing) {
            form.put(update(editing.id).url, options);

            return;
        }

        form.post(store().url, options);
    }

    function confirmDelete() {
        if (!deleting) {
            return;
        }

        deletion.delete(destroy(deleting.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onFinish: () => deletion.reset(),
        });
    }

    return (
        <>
            <Card className="min-w-0">
                <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <CardTitle>Clientes frecuentes</CardTitle>
                        <CardDescription>
                            Guarda los números de billetera de tus clientes
                            habituales para transferir o cobrar más rápido.
                        </CardDescription>
                    </div>
                    <Button type="button" size="sm" onClick={create}>
                        <Plus aria-hidden="true" />
                        Agregar
                    </Button>
                </CardHeader>
                <CardContent>
                    {clientes.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Aún no tienes clientes frecuentes. Agrega uno con su
                            nombre y número de billetera.
                        </p>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {clientes.map((cliente) => (
                                <div
                                    key={cliente.id}
                                    className="flex min-w-0 flex-col justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div className="flex min-w-0 items-start justify-between gap-2">
                                        <div className="min-w-0 space-y-1">
                                            <p className="font-medium break-words">
                                                {cliente.nombre}
                                            </p>
                                            <p className="font-mono text-xs break-all text-muted-foreground">
                                                {cliente.account_number}
                                            </p>
                                            {cliente.nota && (
                                                <p className="text-xs break-words text-muted-foreground">
                                                    {cliente.nota}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Editar ${cliente.nombre}`}
                                                onClick={() => edit(cliente)}
                                            >
                                                <Pencil aria-hidden="true" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Eliminar ${cliente.nombre}`}
                                                onClick={() =>
                                                    setDeleting(cliente)
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                    </div>
                                    {onUse && (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={disabled}
                                                onClick={() =>
                                                    onUse(cliente, 'transfer')
                                                }
                                            >
                                                <Send aria-hidden="true" />
                                                Transferir
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={disabled}
                                                onClick={() =>
                                                    onUse(cliente, 'request')
                                                }
                                            >
                                                <HandCoins aria-hidden="true" />
                                                Solicitar
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            <Dialog
                open={dialogOpen}
                onOpenChange={(open) => {
                    if (!open && !form.processing) {
                        setDialogOpen(false);
                    }
                }}
            >
                <DialogContent className="w-[calc(100%-2rem)] sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? 'Editar cliente frecuente'
                                : 'Agregar cliente frecuente'}
                        </DialogTitle>
                        <DialogDescription>
                            Asocia un nombre al número de billetera del cliente
                            para identificarlo rápidamente.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submit}>
                        <div className="space-y-2">
                            <Label htmlFor="frecuente-nombre">Nombre</Label>
                            <Input
                                id="frecuente-nombre"
                                required
                                maxLength={120}
                                value={form.data.nombre}
                                onChange={(e) =>
                                    form.setData('nombre', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="frecuente-cuenta">
                                Número de billetera
                            </Label>
                            <Input
                                id="frecuente-cuenta"
                                inputMode="numeric"
                                autoComplete="off"
                                required
                                pattern="[0-9]{16}"
                                maxLength={16}
                                value={form.data.account_number}
                                onChange={(e) =>
                                    form.setData(
                                        'account_number',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="frecuente-nota">
                                Nota (opcional)
                            </Label>
                            <Input
                                id="frecuente-nota"
                                maxLength={255}
                                value={form.data.nota}
                                onChange={(e) =>
                                    form.setData('nota', e.target.value)
                                }
                            />
                        </div>
                        {Object.values(form.errors).map((error) => (
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
                                disabled={form.processing}
                                onClick={() => setDialogOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Guardar cambios' : 'Agregar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open && !deletion.processing) {
                        setDeleting(null);
                    }
                }}
                title="Eliminar cliente frecuente"
                description={
                    deleting
                        ? `¿Eliminar a ${deleting.nombre} de tus clientes frecuentes?`
                        : undefined
                }
                processing={deletion.processing}
                onConfirm={confirmDelete}
            />
        </>
    );
}
