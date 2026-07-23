import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Bell, Clock, Mail, Package, Smartphone, Tag } from 'lucide-react';
import { useState } from 'react';
import '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const NOTIFICATION_TYPES: { key: string; label: string; description: string; icon: typeof Bell }[] = [
    { key: 'nuevo_pedido', label: 'Nuevo pedido', description: 'Cuando recibes un pedido nuevo', icon: Bell },
    { key: 'pedido_creado', label: 'Pedido creado', description: 'Confirmación de pedido creado', icon: Bell },
    { key: 'actualizacion_pedido', label: 'Actualización de pedido', description: 'Cambios en el estado de un pedido', icon: Bell },
    { key: 'mensaje_chat', label: 'Mensajes de chat', description: 'Nuevos mensajes en conversaciones de pedidos', icon: Bell },
    { key: 'nuevo_ticket', label: 'Nuevo ticket', description: 'Creación de tickets de soporte', icon: Bell },
    { key: 'pago_recibido', label: 'Pago recibido', description: 'Pagos procesados exitosamente', icon: Bell },
    { key: 'trial_expiry', label: 'Alerta de prueba', description: 'Aviso cuando tu período de prueba está por finalizar o ha expirado', icon: AlertTriangle },
    { key: 'stock_low', label: 'Stock bajo', description: 'Cuando un producto está por debajo del mínimo', icon: Package },
    { key: 'cupon_limite', label: 'Límite de cupón', description: 'Cuando un cupón se acerca a su límite de usos', icon: Tag },
    { key: 'recordatorio_llamada', label: 'Recordatorio de llamada', description: 'Recordatorios pendientes del call center', icon: Clock },
    { key: 'reaccion', label: 'Reacciones', description: 'Me gusta y corazones en tus publicaciones', icon: Bell },
    { key: 'comentario', label: 'Comentarios', description: 'Comentarios en tus publicaciones', icon: Bell },
];

const CHANNELS: { key: string; label: string; icon: typeof Bell; }[] = [
    { key: 'database', label: 'En la app', icon: Smartphone },
    { key: 'mail', label: 'Correo electrónico', icon: Mail },
];

export default function NotificationsSettings() {
    const { preferences } = usePage().props as any;
    const [saving, setSaving] = useState<string | null>(null);

    const prefMap: Record<string, { enabled: boolean }> = preferences || {};

    const toggle = (type: string, channel: string, currentEnabled: boolean) => {
        const key = `${type}-${channel}`;
        setSaving(key);

        router.post('/settings/notifications', {
            type,
            channel,
            enabled: !currentEnabled,
        }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setSaving(null),
        });
    };

    const isEnabled = (type: string, channel: string): boolean => {
        const pref = prefMap[`${type}-${channel}`];
        return pref === undefined || pref === null ? true : pref.enabled;
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Configuración', href: '/settings/profile' },
        { title: 'Notificaciones', href: '/settings/notifications' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notificaciones" />

            <div className="mx-auto max-w-2xl space-y-6 p-4 md:p-8">
                <div className="flex items-center gap-3">
                    <Bell className="h-6 w-6 text-primary" />
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Notificaciones</h1>
                        <p className="text-sm text-muted-foreground">
                            Controla qué notificaciones recibes y por qué canal
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Preferencias de notificación</CardTitle>
                        <CardDescription>
                            Activa o desactiva cada tipo de notificación por canal. Los cambios se guardan automáticamente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-6">
                            {NOTIFICATION_TYPES.map((type) => (
                                <div key={type.key} className="space-y-3">
                                    <div className="flex items-center gap-2">
                                        <type.icon className="h-4 w-4 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium">{type.label}</p>
                                            <p className="text-xs text-muted-foreground">{type.description}</p>
                                        </div>
                                    </div>
                                    <div className="ml-6 flex flex-wrap gap-4">
                                        {CHANNELS.map((channel) => {
                                            const enabled = isEnabled(type.key, channel.key);
                                            const isSaving = saving === `${type.key}-${channel.key}`;
                                            return (
                                                <div key={channel.key} className="flex items-center gap-2">
                                                    <Switch
                                                        id={`${type.key}-${channel.key}`}
                                                        checked={enabled}
                                                        disabled={isSaving}
                                                        onCheckedChange={() => toggle(type.key, channel.key, enabled)}
                                                    />
                                                    <Label
                                                        htmlFor={`${type.key}-${channel.key}`}
                                                        className="flex cursor-pointer items-center gap-1.5 text-xs"
                                                    >
                                                        <channel.icon className="h-3 w-3" />
                                                        {channel.label}
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
