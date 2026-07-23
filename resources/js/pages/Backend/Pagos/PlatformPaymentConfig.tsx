import { Head, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    CreditCard,
    Landmark,
    PiggyBank,
    ShieldAlert,
    XCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

type GatewayInfo = {
    key: string;
    label: string;
    icon: React.ElementType;
    href: string;
    active: boolean;
    configured: boolean;
};

export default function PlatformPaymentConfig({ config }: { config: any }) {
    const { hasPermission } = usePermissions();
    const canEdit = hasPermission('admin.configuracion.edit');

    const { data, setData, post, processing } = useForm({
        use_platform_config: config?.use_platform_config ?? false,
    });

    const gateways: GatewayInfo[] = [
        {
            key: 'webpay',
            label: 'Webpay',
            icon: Landmark,
            href: '/webpay/config',
            active: config?.is_active ?? false,
            configured: !!(config?.commerce_code && config?.commerce_code !== 'PRESET'),
        },
        {
            key: 'paypal',
            label: 'PayPal',
            icon: PiggyBank,
            href: '/paypal/config',
            active: config?.paypal_active ?? false,
            configured: !!config?.paypal_client_id,
        },
        {
            key: 'mercadopago',
            label: 'MercadoPago',
            icon: CreditCard,
            href: '/mercadopago/config',
            active: config?.mercadopago_active ?? false,
            configured: !!(config?.mercadopago_public_key && config?.mercadopago_public_key !== 'PRESET'),
        },
    ];

    const hasAnyConfigured = gateways.some((g) => g.configured && g.active);
    const showFallbackNotice = !hasAnyConfigured || data.use_platform_config;

    const handleToggle = (checked: boolean) => {
        setData('use_platform_config', checked);
        post('/pagos/plataforma', {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Pagos en Línea', href: '/webpay/config' },
                { title: 'Pago Plataforma', href: '/pagos/plataforma' },
            ]}
        >
            <Head title="Pago Plataforma" />
            <div className="p-6 max-w-2xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Plataforma de Pago Principal</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Administra si tu tienda usa la plataforma de pago principal del sistema.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2">
                            <ShieldAlert className="h-5 w-5 text-primary" />
                            Usar plataforma de pago principal
                        </CardTitle>
                        <CardDescription>
                            Al activar, tus clientes pagarán usando la plataforma de pago del sistema
                            (administrada por el usuario Master). Debes configurar tus datos de pago
                            (commerce_code, api_key, etc.) para que la plataforma funcione correctamente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div>
                                <Label htmlFor="platform-toggle" className="font-semibold text-sm cursor-pointer">
                                    Plataforma de pago principal
                                </Label>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    {data.use_platform_config ? 'Activada' : 'Desactivada'}
                                </p>
                            </div>
                            <Switch
                                id="platform-toggle"
                                checked={data.use_platform_config}
                                onCheckedChange={handleToggle}
                                disabled={processing || !canEdit}
                            />
                        </div>
                    </CardContent>
                </Card>

                {showFallbackNotice && (
                    <Card className="border-primary/20 bg-primary/5">
                        <CardContent className="pt-6">
                            <div className="flex items-start gap-3">
                                <CheckCircle2 className="h-5 w-5 text-primary shrink-0 mt-0.5" />
                                <div>
                                    <p className="text-sm font-semibold">
                                        {data.use_platform_config
                                            ? 'Usando plataforma de pago principal'
                                            : 'Usando plataforma de pago principal (fallback)'}
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {data.use_platform_config
                                            ? 'Los pagos se procesarán con las credenciales globales del sistema.'
                                            : 'No tienes métodos de pago activos. Los pagos se procesarán con las credenciales globales del sistema.'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Tus métodos de pago</CardTitle>
                        <CardDescription>
                            Configura tus propias credenciales para usar tus propias cuentas de pago.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {gateways.map((gw) => (
                            <div
                                key={gw.key}
                                className="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-3">
                                    <div className={`flex h-9 w-9 items-center justify-center rounded-lg ${
                                        gw.configured
                                            ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            : 'bg-muted text-muted-foreground'
                                    }`}>
                                        <gw.icon className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold">{gw.label}</p>
                                        <div className="flex items-center gap-1.5 mt-0.5">
                                            {gw.configured ? (
                                                <>
                                                    <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                                                    <span className="text-xs text-emerald-600 dark:text-emerald-400">
                                                        Configurado
                                                    </span>
                                                    {gw.active && (
                                                        <span className="text-[10px] uppercase font-bold text-emerald-500 bg-emerald-100 dark:bg-emerald-900/30 px-1.5 py-0.5 rounded">
                                                            Activo
                                                        </span>
                                                    )}
                                                </>
                                            ) : (
                                                <>
                                                    <XCircle className="h-3 w-3 text-muted-foreground" />
                                                    <span className="text-xs text-muted-foreground">
                                                        No configurado
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <a href={gw.href}>Configurar</a>
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
