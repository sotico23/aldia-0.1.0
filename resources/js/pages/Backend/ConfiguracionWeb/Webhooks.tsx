import axios from 'axios';
import {
    Loader2,
    Webhook,
    Activity,
    AlertTriangle,
    Clock,
    Copy,
    Check,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

function StatusBadge({ status }: { status: string }) {
    const isActive = status === 'active';
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ${isActive ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'}`}
        >
            <span
                className={`h-1.5 w-1.5 rounded-full ${isActive ? 'bg-green-500' : 'bg-red-500'}`}
            />
            {isActive ? 'Activo' : 'Inactivo'}
        </span>
    );
}

type WebhookCardProps = {
    title: string;
    gateway: string;
    icon: React.ReactNode;
    info: any;
    copied: string | null;
    onCopy: (text: string, key: string) => void;
};

function WebhookCard({
    title,
    gateway,
    icon,
    info,
    copied,
    onCopy,
}: WebhookCardProps) {
    if (!info) return null;

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            {icon}
                        </div>
                        <div>
                            <CardTitle className="text-lg">{title}</CardTitle>
                        </div>
                    </div>
                    <StatusBadge status={info.status} />
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-3">
                    <div className="flex items-center justify-between rounded-lg bg-muted/30 p-3">
                        <div>
                            <p className="text-xs font-medium text-muted-foreground">
                                URL Webhook
                            </p>
                            <p className="font-mono text-sm">
                                {info.webhook_url}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() =>
                                onCopy(info.webhook_url, `url-${gateway}`)
                            }
                        >
                            {copied === `url-${gateway}` ? (
                                <Check className="h-4 w-4 text-green-500" />
                            ) : (
                                <Copy className="h-4 w-4" />
                            )}
                        </Button>
                    </div>
                    {info.webhook_id !== undefined && (
                        <div className="flex items-center justify-between rounded-lg bg-muted/30 p-3">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Webhook ID
                                </p>
                                <p className="font-mono text-sm">
                                    {info.webhook_id || 'No configurado'}
                                </p>
                            </div>
                        </div>
                    )}
                    {info.webhook_secret !== undefined && (
                        <div className="flex items-center justify-between rounded-lg bg-muted/30 p-3">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Webhook Secret
                                </p>
                                <p className="font-mono text-sm">
                                    {info.webhook_secret || 'No configurado'}
                                </p>
                            </div>
                        </div>
                    )}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-lg bg-muted/30 p-3">
                            <p className="text-xs font-medium text-muted-foreground">
                                Último evento
                            </p>
                            <p className="text-sm font-medium">
                                {info.last_event || 'Ninguno'}
                            </p>
                        </div>
                        <div className="rounded-lg bg-muted/30 p-3">
                            <p className="text-xs font-medium text-muted-foreground">
                                Última recepción
                            </p>
                            <p className="text-sm font-medium">
                                {info.last_event_at
                                    ? new Date(
                                          info.last_event_at,
                                      ).toLocaleString()
                                    : 'N/A'}
                            </p>
                        </div>
                    </div>
                </div>

                {info.recent_logs?.length > 0 && (
                    <div>
                        <p className="mb-2 text-xs font-medium text-muted-foreground">
                            Eventos recientes
                        </p>
                        <div className="max-h-[200px] space-y-1 overflow-y-auto">
                            {info.recent_logs.map((log: any) => (
                                <div
                                    key={log.id}
                                    className="flex items-center justify-between rounded-lg border px-3 py-2 text-xs"
                                >
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={`h-1.5 w-1.5 rounded-full ${log.status === 'processed' ? 'bg-green-500' : log.status === 'failed' ? 'bg-red-500' : log.status === 'duplicate' ? 'bg-amber-500' : 'bg-emerald-500'}`}
                                        />
                                        <span className="font-medium">
                                            {log.event_type || log.event_id}
                                        </span>
                                    </div>
                                    <span className="text-muted-foreground">
                                        {new Date(
                                            log.received_at,
                                        ).toLocaleString()}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function Webhooks() {
    const [loading, setLoading] = useState(true);
    const [data, setData] = useState<any>(null);
    const [copied, setCopied] = useState<string | null>(null);

    useEffect(() => {
        axios
            .get('/configuracion-web/webhook-settings')
            .then((res) => {
                setData(res.data.data);
            })
            .catch(() => {
                toast.error('Error al cargar configuración de webhooks');
            })
            .finally(() => setLoading(false));
    }, []);

    const copyToClipboard = (text: string, key: string) => {
        navigator.clipboard.writeText(text);
        setCopied(key);
        setTimeout(() => setCopied(null), 2000);
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-12">
                <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-bold">Configuración de Webhooks</h2>
                <p className="text-muted-foreground">
                    Endpoints y estado de los webhooks de pago
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <WebhookCard
                    title="PayPal"
                    gateway="paypal"
                    icon={<span className="text-lg">💳</span>}
                    info={data?.paypal}
                    copied={copied}
                    onCopy={copyToClipboard}
                />
                <WebhookCard
                    title="MercadoPago"
                    gateway="mercadopago"
                    icon={<span className="text-lg">🟡</span>}
                    info={data?.mercadopago}
                    copied={copied}
                    onCopy={copyToClipboard}
                />
            </div>

            {data?.audit && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="h-5 w-5" /> Auditoría de
                            Webhooks
                        </CardTitle>
                        <CardDescription>
                            Estadísticas globales de eventos de webhook
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="rounded-xl border bg-muted/30 p-4">
                                <div className="mb-1 flex items-center gap-2 text-sm text-muted-foreground">
                                    <Webhook className="h-4 w-4" /> Eventos
                                    recibidos
                                </div>
                                <p className="text-2xl font-bold">
                                    {data.audit.total_received}
                                </p>
                            </div>
                            <div className="rounded-xl border bg-red-50/50 p-4 dark:bg-red-950/20">
                                <div className="mb-1 flex items-center gap-2 text-sm text-muted-foreground">
                                    <AlertTriangle className="h-4 w-4 text-red-500" />{' '}
                                    Eventos fallidos
                                </div>
                                <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                                    {data.audit.total_failed}
                                </p>
                            </div>
                            <div className="rounded-xl border bg-amber-50/50 p-4 dark:bg-amber-950/20">
                                <div className="mb-1 flex items-center gap-2 text-sm text-muted-foreground">
                                    <AlertTriangle className="h-4 w-4 text-amber-500" />{' '}
                                    Eventos duplicados
                                </div>
                                <p className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                    {data.audit.total_duplicates}
                                </p>
                            </div>
                            <div className="rounded-xl border bg-muted/30 p-4">
                                <div className="mb-1 flex items-center gap-2 text-sm text-muted-foreground">
                                    <Clock className="h-4 w-4" /> Último error
                                </div>
                                <p
                                    className="truncate text-sm font-medium"
                                    title={data.audit.last_error || ''}
                                >
                                    {data.audit.last_error || 'Sin errores'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
