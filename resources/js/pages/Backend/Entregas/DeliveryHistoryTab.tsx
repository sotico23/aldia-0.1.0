import { Camera, CheckCircle2, Clock, MapPin, User } from 'lucide-react';
import { useState } from 'react';
import { evidencePhoto } from '@/actions/App/Http/Controllers/Backend/EntregaController';
import { Badge } from '@/components/ui/badge';
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
import Pagination from '@/components/ui/Pagination';
import { useCountry } from '@/hooks/use-country';

export interface DeliveryRecord {
    id: number;
    pedido_number: string | null;
    cliente: string | null;
    direccion: string | null;
    driver_name: string | null;
    accepted_at: string | null;
    picked_up_at: string | null;
    delivered_at: string | null;
    has_photo: boolean;
}

export interface DeliveryHistory {
    data: DeliveryRecord[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

export default function DeliveryHistoryTab({
    history,
}: {
    history: DeliveryHistory;
}) {
    const { locale, timezone } = useCountry();
    const [selected, setSelected] = useState<DeliveryRecord | null>(null);
    const formatDate = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat(locale, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
                  timeZone: timezone,
              }).format(new Date(value))
            : 'Sin hora registrada';

    return (
        <Card data-testid="delivery-history">
            <CardHeader>
                <CardTitle>Registro de entregas</CardTitle>
                <CardDescription>
                    {history.total} entregas completadas. Se actualiza
                    automáticamente.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {history.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        <CheckCircle2 className="size-10 opacity-40" />
                        <p>No hay entregas completadas todavía.</p>
                        <p className="text-sm">
                            Aquí verás quién entregó cada pedido, sus horarios y
                            la foto de evidencia.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                        {history.data.map((record) => (
                            <article
                                key={record.id}
                                className="min-w-0 overflow-hidden rounded-xl border bg-background"
                            >
                                <div className="space-y-3 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <h3 className="font-semibold">
                                            Entrega #{record.id}
                                        </h3>
                                        <Badge className="bg-emerald-600 text-white">
                                            Entregado
                                        </Badge>
                                    </div>
                                    {record.pedido_number && (
                                        <p className="text-xs break-words text-muted-foreground">
                                            {record.pedido_number}
                                        </p>
                                    )}
                                    <p className="font-medium break-words">
                                        {record.cliente ||
                                            'Sin cliente registrado'}
                                    </p>
                                    <p className="flex gap-2 text-sm text-muted-foreground">
                                        <MapPin className="mt-0.5 size-4 shrink-0" />
                                        <span className="break-words">
                                            {record.direccion ||
                                                'Sin dirección registrada'}
                                        </span>
                                    </p>
                                    <p className="flex items-center gap-2 text-sm">
                                        <User className="size-4 shrink-0 text-primary" />
                                        <span className="break-words">
                                            {record.driver_name ||
                                                'Sin repartidor registrado'}
                                        </span>
                                    </p>
                                    <dl className="space-y-2 border-t pt-3 text-sm">
                                        {(
                                            [
                                                [
                                                    'Aceptado',
                                                    record.accepted_at,
                                                ],
                                                [
                                                    'Retirado',
                                                    record.picked_up_at,
                                                ],
                                                [
                                                    'Entregado',
                                                    record.delivered_at,
                                                ],
                                            ] as const
                                        ).map(([label, date]) => (
                                            <div
                                                key={label}
                                                className="flex flex-wrap justify-between gap-x-3 gap-y-1"
                                            >
                                                <dt className="flex items-center gap-1 text-muted-foreground">
                                                    <Clock className="size-3" />
                                                    {label}
                                                </dt>
                                                <dd
                                                    className={
                                                        label === 'Entregado'
                                                            ? 'font-semibold'
                                                            : ''
                                                    }
                                                >
                                                    {formatDate(date)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </div>
                                {record.has_photo ? (
                                    <button
                                        type="button"
                                        onClick={() => setSelected(record)}
                                        className="group relative block w-full border-t text-left"
                                        aria-label={`Ver foto de la entrega ${record.id}`}
                                    >
                                        <img
                                            src={evidencePhoto.url(record.id)}
                                            alt={`Evidencia de entrega ${record.id}`}
                                            loading="lazy"
                                            className="h-44 w-full bg-muted object-cover transition-opacity group-hover:opacity-90"
                                        />
                                        <span className="absolute right-3 bottom-3 flex items-center gap-2 rounded-lg bg-background/95 px-3 py-2 text-xs font-medium shadow-sm">
                                            <Camera className="size-4" />
                                            Ver foto
                                        </span>
                                    </button>
                                ) : (
                                    <p className="flex items-center gap-2 border-t bg-muted/30 p-4 text-sm text-muted-foreground">
                                        <Camera className="size-4" />
                                        Sin foto registrada
                                    </p>
                                )}
                            </article>
                        ))}
                    </div>
                )}
                <Pagination links={history.links} />
                <Dialog
                    open={selected !== null}
                    onOpenChange={(open) => {
                        if (!open) setSelected(null);
                    }}
                >
                    <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                        <DialogHeader>
                            <DialogTitle>
                                Foto de la entrega #{selected?.id}
                            </DialogTitle>
                        </DialogHeader>
                        {selected && (
                            <>
                                <p className="text-sm text-muted-foreground">
                                    {selected.driver_name ||
                                        'Sin repartidor registrado'}{' '}
                                    · {formatDate(selected.delivered_at)}
                                </p>
                                <img
                                    src={evidencePhoto.url(selected.id)}
                                    alt={`Evidencia completa de entrega ${selected.id}`}
                                    className="max-h-[65dvh] w-full rounded-lg object-contain"
                                />
                            </>
                        )}
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}
