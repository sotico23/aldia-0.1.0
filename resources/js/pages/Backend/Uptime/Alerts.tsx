import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, Bell, Plus, Trash2 } from 'lucide-react';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Alert {
    id: number;
    type: string;
    email_enabled: boolean;
    webhook_enabled: boolean;
    webhook_url: string | null;
    is_active: boolean;
    site: { id: number; name: string } | null;
}

interface Site {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Alertas Uptime', href: '/uptime/alerts' },
];

const alertTypes = [
    { value: 'site_down', label: 'Cuando el sitio se cae' },
    { value: 'site_recovery', label: 'Cuando el sitio se recupera' },
    { value: 'slow_response', label: 'Respuesta lenta' },
];

export default function AlertsIndex({
    alerts = [],
    sites = [],
}: {
    alerts?: Alert[];
    sites?: Site[];
}) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('uptime.monitores.viewAny');
    const [isOpen, setIsOpen] = useState(false);
    const {
        data,
        setData,
        post,
        delete: destroy,
        reset,
    } = useForm({
        monitored_site_id: '',
        type: 'site_down',
        email_enabled: true,
        webhook_enabled: false,
        webhook_url: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/uptime/alerts', {
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
        });
    };

    const handleNew = () => {
        reset();
        setData({
            monitored_site_id: '',
            type: 'site_down',
            email_enabled: true,
            webhook_enabled: false,
            webhook_url: '',
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar esta alerta?')) {
            destroy(`/uptime/alerts/${id}`);
        }
    };

    const getTypeLabel = (type: string) => {
        return alertTypes.find((t) => t.value === type)?.label || type;
    };

    if (!canAccess) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <>
            <Head title="Alertas Uptime" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4 sm:p-6">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Alertas de Monitoreo
                            </h1>
                            <p className="text-muted-foreground">
                                Configura notificaciones para cuando ocurran
                                incidentes
                            </p>
                        </div>
                        <Button onClick={handleNew}>
                            <Plus className="mr-2 h-4 w-4" /> Nueva Alerta
                        </Button>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Alertas Configuradas</CardTitle>
                            <CardDescription>
                                {alerts.length} alertas activas
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {alerts.length === 0 ? (
                                <div className="py-12 text-center">
                                    <Bell className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-semibold">
                                        Sin alertas configuradas
                                    </h3>
                                    <p className="mb-4 text-muted-foreground">
                                        Crea una alerta para recibir
                                        notificaciones.
                                    </p>
                                    <Button onClick={handleNew}>
                                        Crear Alerta
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {alerts.map((alert) => (
                                        <div
                                            key={alert.id}
                                            className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-lg border p-4 sm:p-6"
                                        >
                                            <div className="flex items-center gap-4">
                                                <AlertCircle className="h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <div className="font-medium">
                                                        {getTypeLabel(
                                                            alert.type,
                                                        )}
                                                    </div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {alert.site
                                                            ? `Sitio: ${alert.site.name}`
                                                            : 'Todos los sitios'}
                                                    </div>
                                                    <div className="mt-1 flex gap-2">
                                                        {alert.email_enabled && (
                                                            <Badge variant="secondary">
                                                                Email
                                                            </Badge>
                                                        )}
                                                        {alert.webhook_enabled && (
                                                            <Badge variant="secondary">
                                                                Webhook
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    className={
                                                        alert.is_active
                                                            ? 'bg-green-500'
                                                            : 'bg-gray-500'
                                                    }
                                                >
                                                    {alert.is_active
                                                        ? 'Activa'
                                                        : 'Inactiva'}
                                                </Badge>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        handleDelete(alert.id)
                                                    }
                                                    className="text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Nueva Alerta</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="space-y-2">
                                <Label>Tipo de Alerta</Label>
                                <select
                                    value={data.type}
                                    onChange={(e) =>
                                        setData('type', e.target.value)
                                    }
                                    className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                >
                                    {alertTypes.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-2">
                                <Label>Sitio (opcional)</Label>
                                <select
                                    value={data.monitored_site_id}
                                    onChange={(e) =>
                                        setData(
                                            'monitored_site_id',
                                            e.target.value,
                                        )
                                    }
                                    className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                >
                                    <option value="">Todos los sitios</option>
                                    {sites.map((site) => (
                                        <option key={site.id} value={site.id}>
                                            {site.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="email_enabled"
                                        checked={data.email_enabled}
                                        onChange={(e) =>
                                            setData(
                                                'email_enabled',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <Label htmlFor="email_enabled">
                                        Notificar por Email
                                    </Label>
                                </div>

                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="webhook_enabled"
                                        checked={data.webhook_enabled}
                                        onChange={(e) =>
                                            setData(
                                                'webhook_enabled',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <Label htmlFor="webhook_enabled">
                                        Notificar por Webhook
                                    </Label>
                                </div>

                                {data.webhook_enabled && (
                                    <div className="space-y-2 pl-6">
                                        <Label>URL del Webhook</Label>
                                        <Input
                                            value={data.webhook_url}
                                            onChange={(e) =>
                                                setData(
                                                    'webhook_url',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="https://api.example.com/webhook"
                                            type="url"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit">Crear</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
