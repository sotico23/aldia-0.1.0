import { Head, useForm, router } from '@inertiajs/react';
import {
    Mail,
    Plus,
    Pencil,
    Trash2,
    TestTube,
    Star,
    Check,
    AlertCircle,
    Clock,
    Loader2
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface MailConfig {
    id: number;
    name: string;
    driver: 'smtp' | 'mailgun' | 'postmark' | 'ses' | 'sendmail';
    host: string | null;
    port: number | null;
    encryption: string | null;
    username: string | null;
    from_address: string;
    from_name: string;
    is_active: boolean;
    is_default: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: any;
}

interface MailConfigLog {
    id: number;
    mail_config_id: number;
    status: 'success' | 'failed' | 'timeout';
    test_email: string;
    error_message: string | null;
    response_time: number | null;
    created_at: string;
}

interface MailConfigNotification {
    id: number;
    tipo: string;
    mensaje: string;
    leido: boolean;
    created_at: string;
}

interface Props {
    configs: MailConfig[];
    logs: MailConfigLog[];
    notifications: MailConfigNotification[];
    drivers: Record<string, string[]>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Marketing', href: '/marketing/email-config' },
    { title: 'Config. Correo', href: '/marketing/email-config' },
];

const driverOptions = [
    { value: 'smtp', label: 'SMTP', icon: '📧' },
    { value: 'mailgun', label: 'Mailgun', icon: '🔫' },
    { value: 'postmark', label: 'Postmark', icon: '📮' },
    { value: 'ses', label: 'AWS SES', icon: '☁️' },
    { value: 'sendmail', label: 'Sendmail', icon: '📤' },
];

export default function EmailConfig({
    configs,
    logs,
    notifications,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    drivers,
}: Props) {
    const [showModal, setShowModal] = useState(false);
    const [editingConfig, setEditingConfig] = useState<MailConfig | null>(null);
    const [showTestModal, setShowTestModal] = useState(false);
    const [testingConfig, setTestingConfig] = useState<MailConfig | null>(null);
    const [testEmail, setTestEmail] = useState('');
    const [testing, setTesting] = useState(false);

    const { data, setData, post, patch, processing, reset } = useForm({
        name: '',
        driver: 'smtp' as 'smtp' | 'mailgun' | 'postmark' | 'ses' | 'sendmail',
        host: '',
        port: 587,
        encryption: 'tls' as 'tls' | 'ssl' | 'none',
        username: '',
        password: '',
        secret: '',
        domain: '',
        endpoint: '',
        region: '',
        from_address: '',
        from_name: '',
        is_active: true,
        is_default: false,
    });

    const openCreateModal = () => {
        setEditingConfig(null);
        reset();
        setShowModal(true);
    };

    const openEditModal = (config: MailConfig) => {
        setEditingConfig(config);
        setData({
            name: config.name,
            driver: config.driver,
            host: config.host || '',
            port: config.port || 587,
            encryption: (config.encryption || 'none') as 'tls' | 'ssl' | 'none',
            username: config.username || '',
            password: '',
            secret: '',
            domain: config.domain || '',
            endpoint: '',
            region: '',
            from_address: config.from_address,
            from_name: config.from_name,
            is_active: config.is_active,
            is_default: config.is_default,
        });
        setShowModal(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const formData: Record<string, any> = { ...data };
        if (!formData.password) delete formData.password;
        if (!formData.secret) delete formData.secret;

        const options = {
            onSuccess: () => {
                toast.success(
                    editingConfig
                        ? 'Configuración actualizada'
                        : 'Configuración creada',
                );
                setShowModal(false);
                router.reload({ only: ['configs', 'logs'] });
            },
            onError: () => toast.error('Error al guardar'),
        };

        if (editingConfig) {
            (patch as any)(
                `/marketing/email-config/${editingConfig.id}`,
                options,
            );
        } else {
            (post as any)('/marketing/email-config', options);
        }
    };

    const handleDelete = (config: MailConfig) => {
        if (!confirm(`¿Eliminar la configuración "${config.name}"?`)) return;
        router.delete(`/marketing/email-config/${config.id}`, {
            onSuccess: () => {
                toast.success('Configuración eliminada');
                router.reload({ only: ['configs'] });
            },
            onError: () => toast.error('Error al eliminar'),
        });
    };

    const handleSetDefault = (config: MailConfig) => {
        router.post(
            `/marketing/email-config/${config.id}/set-default`,
            {},
            {
                onSuccess: () => {
                    toast.success('Configuración predeterminada');
                    router.reload({ only: ['configs'] });
                },
            },
        );
    };

    const handleSetActive = (config: MailConfig) => {
        router.post(
            `/marketing/email-config/${config.id}/set-active`,
            {},
            {
                onSuccess: () => {
                    toast.success('Configuración activada');
                    router.reload({ only: ['configs'] });
                },
            },
        );
    };

    const openTestModal = (config: MailConfig) => {
        setTestingConfig(config);
        setTestEmail(config.from_address);
        setShowTestModal(true);
    };

    const handleTest = () => {
        if (!testingConfig || !testEmail) return;

        setTesting(true);
        router.post(
            `/marketing/email-config/${testingConfig.id}/test`,
            { test_email: testEmail },
            {
                onSuccess: () => {
                    toast.success('Email de prueba enviado');
                    setShowTestModal(false);
                    router.reload({ only: ['logs'] });
                },
                onError: () => toast.error('Error al enviar prueba'),
                onFinish: () => setTesting(false),
            },
        );
    };

    const getDriverLabel = (driver: string) => {
        return driverOptions.find((d) => d.value === driver)?.label || driver;
    };

    const getDriverIcon = (driver: string) => {
        return driverOptions.find((d) => d.value === driver)?.icon || '📧';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configuración de Correo" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            Configuración de Correo
                        </h2>
                        <p className="text-muted-foreground">
                            Gestiona los servidores de correo para envío de
                            campañas
                        </p>
                    </div>
                    <Button onClick={openCreateModal}>
                        <Plus className="mr-2 h-4 w-4" />
                        Nueva Configuración
                    </Button>
                </div>

                {notifications.length > 0 && (
                    <Card className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/20">
                        <CardContent className="flex items-center gap-3 p-4">
                            <AlertCircle className="h-5 w-5 text-amber-600" />
                            <p className="flex-1 text-sm text-amber-800 dark:text-amber-200">
                                Tienes {notifications.length} notificación(es)
                                de error de correo
                            </p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {configs.map((config) => (
                        <Card
                            key={config.id}
                            className={`relative ${config.is_active ? 'ring-2 ring-primary/20' : ''}`}
                        >
                            <CardHeader className="pb-3">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xl">
                                            {getDriverIcon(config.driver)}
                                        </span>
                                        <div>
                                            <CardTitle className="text-base">
                                                {config.name}
                                            </CardTitle>
                                            <CardDescription>
                                                {getDriverLabel(config.driver)}
                                            </CardDescription>
                                        </div>
                                    </div>
                                    <div className="flex gap-1">
                                        {config.is_default && (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                                                ★ Default
                                            </span>
                                        )}
                                        {config.is_active && (
                                            <span className="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">
                                                ● Activo
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="space-y-1 text-sm">
                                    <p className="text-muted-foreground">
                                        <span className="font-medium">
                                            Remitente:
                                        </span>{' '}
                                        {config.from_address}
                                    </p>
                                    {config.host && (
                                        <p className="text-muted-foreground">
                                            <span className="font-medium">
                                                Host:
                                            </span>{' '}
                                            {config.host}:{config.port}
                                        </p>
                                    )}
                                </div>
                                <div className="flex gap-2 pt-2">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        openTestModal(config)
                                                    }
                                                >
                                                    <TestTube className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Probar conexión
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>

                                    {!config.is_default && (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            handleSetDefault(
                                                                config,
                                                            )
                                                        }
                                                    >
                                                        <Star className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    Establecer como default
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    )}

                                    {!config.is_active && (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            handleSetActive(
                                                                config,
                                                            )
                                                        }
                                                    >
                                                        <Check className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    Activar
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    )}

                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        openEditModal(config)
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Editar
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>

                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="text-destructive"
                                                    onClick={() =>
                                                        handleDelete(config)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Eliminar
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </CardContent>
                        </Card>
                    ))}

                    {configs.length === 0 && (
                        <Card className="col-span-full">
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <Mail className="mb-4 h-12 w-12 text-muted-foreground/50" />
                                <p className="mb-1 text-lg font-medium">
                                    Sin configuraciones
                                </p>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    Crea tu primera configuración de correo
                                </p>
                                <Button onClick={openCreateModal}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Nueva Configuración
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-5 w-5" />
                            Historial de Pruebas
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {logs.length === 0 ? (
                            <p className="py-8 text-center text-muted-foreground">
                                Sin pruebas realizadas
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {logs.slice(0, 10).map((log) => (
                                    <div
                                        key={log.id}
                                        className="flex items-center gap-3 rounded-lg border p-3"
                                    >
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-bold ${
                                                log.status === 'success'
                                                    ? 'bg-green-100 text-green-700'
                                                    : log.status === 'failed'
                                                      ? 'bg-red-100 text-red-700'
                                                      : 'bg-amber-100 text-amber-700'
                                            }`}
                                        >
                                            {log.status === 'success'
                                                ? '✅'
                                                : log.status === 'failed'
                                                  ? '❌'
                                                  : '⏱️'}
                                            {log.status.toUpperCase()}
                                        </span>
                                        <span className="text-sm">
                                            {log.test_email}
                                        </span>
                                        {log.response_time && (
                                            <span className="text-xs text-muted-foreground">
                                                {log.response_time}ms
                                            </span>
                                        )}
                                        <span className="ml-auto text-xs text-muted-foreground">
                                            {new Date(
                                                log.created_at,
                                            ).toLocaleString()}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={showModal} onOpenChange={setShowModal}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editingConfig
                                ? 'Editar Configuración'
                                : 'Nueva Configuración'}
                        </DialogTitle>
                        <DialogDescription>
                            Configura el servidor de correo para enviar emails
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Nombre *</Label>
                                <Input
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="Gmail Producción"
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Driver *</Label>
                                <Select
                                    value={data.driver}
                                    onValueChange={(v) =>
                                        setData('driver', v as any)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {driverOptions.map((d) => (
                                            <SelectItem
                                                key={d.value}
                                                value={d.value}
                                            >
                                                {d.icon} {d.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {data.driver === 'smtp' && (
                            <div className="space-y-4 rounded-lg border p-4">
                                <Label className="text-base font-semibold">
                                    Configuración SMTP
                                </Label>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Host *</Label>
                                        <Input
                                            value={data.host}
                                            onChange={(e) =>
                                                setData('host', e.target.value)
                                            }
                                            placeholder="smtp.gmail.com"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Puerto *</Label>
                                        <Input
                                            type="number"
                                            value={data.port}
                                            onChange={(e) =>
                                                setData(
                                                    'port',
                                                    parseInt(e.target.value),
                                                )
                                            }
                                            placeholder="587"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Encriptación</Label>
                                        <Select
                                            value={data.encryption}
                                            onValueChange={(v) =>
                                                setData('encryption', v as any)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="TLS" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="tls">
                                                    TLS (587)
                                                </SelectItem>
                                                <SelectItem value="ssl">
                                                    SSL (465)
                                                </SelectItem>
                                                <SelectItem value="none">
                                                    Ninguna
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Usuario *</Label>
                                        <Input
                                            value={data.username}
                                            onChange={(e) =>
                                                setData(
                                                    'username',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="tu@email.com"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label>Contraseña *</Label>
                                        <Input
                                            type="password"
                                            value={data.password}
                                            onChange={(e) =>
                                                setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={
                                                editingConfig
                                                    ? 'Nueva contraseña (dejar vacío para no cambiar)'
                                                    : 'Contraseña'
                                            }
                                            required={!editingConfig}
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        {data.driver === 'mailgun' && (
                            <div className="space-y-4 rounded-lg border p-4">
                                <Label className="text-base font-semibold">
                                    Configuración Mailgun
                                </Label>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Domain *</Label>
                                        <Input
                                            value={data.domain}
                                            onChange={(e) =>
                                                setData(
                                                    'domain',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="mg.tudominio.com"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Secret API Key *</Label>
                                        <Input
                                            type="password"
                                            value={data.secret}
                                            onChange={(e) =>
                                                setData(
                                                    'secret',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="key-..."
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Endpoint</Label>
                                        <Input
                                            value={data.endpoint}
                                            onChange={(e) =>
                                                setData(
                                                    'endpoint',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="api.mailgun.net"
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        {data.driver === 'postmark' && (
                            <div className="space-y-4 rounded-lg border p-4">
                                <Label className="text-base font-semibold">
                                    Configuración Postmark
                                </Label>
                                <div className="space-y-2">
                                    <Label>Server API Token *</Label>
                                    <Input
                                        type="password"
                                        value={data.secret}
                                        onChange={(e) =>
                                            setData('secret', e.target.value)
                                        }
                                        placeholder="Token..."
                                        required
                                    />
                                </div>
                            </div>
                        )}

                        {data.driver === 'ses' && (
                            <div className="space-y-4 rounded-lg border p-4">
                                <Label className="text-base font-semibold">
                                    Configuración AWS SES
                                </Label>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Access Key *</Label>
                                        <Input
                                            value={data.username}
                                            onChange={(e) =>
                                                setData(
                                                    'username',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="AKIA..."
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Secret Key *</Label>
                                        <Input
                                            type="password"
                                            value={data.password}
                                            onChange={(e) =>
                                                setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Secret..."
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Region</Label>
                                        <Input
                                            value={data.region}
                                            onChange={(e) =>
                                                setData(
                                                    'region',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="us-east-1"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Domain</Label>
                                        <Input
                                            value={data.domain}
                                            onChange={(e) =>
                                                setData(
                                                    'domain',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="tudominio.com"
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        {data.driver === 'sendmail' && (
                            <div className="space-y-4 rounded-lg border p-4">
                                <Label className="text-base font-semibold">
                                    Configuración Sendmail
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Sendmail utilizará la configuración del
                                    servidor local.
                                </p>
                            </div>
                        )}

                        <div className="space-y-4 rounded-lg border p-4">
                            <Label className="text-base font-semibold">
                                Información del Remitente
                            </Label>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Email Remitente *</Label>
                                    <Input
                                        type="email"
                                        value={data.from_address}
                                        onChange={(e) =>
                                            setData(
                                                'from_address',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="noreply@tudominio.com"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Nombre Remitente *</Label>
                                    <Input
                                        value={data.from_name}
                                        onChange={(e) =>
                                            setData('from_name', e.target.value)
                                        }
                                        placeholder="Mi Empresa"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div className="space-y-0.5">
                                <Label>Activa</Label>
                                <p className="text-sm text-muted-foreground">
                                    Esta configuración podrá enviar correos
                                </p>
                            </div>
                            <Switch
                                checked={data.is_active}
                                onCheckedChange={(c) => setData('is_active', c)}
                            />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div className="space-y-0.5">
                                <Label>Predeterminada</Label>
                                <p className="text-sm text-muted-foreground">
                                    Se usará por defecto en todos los envíos
                                </p>
                            </div>
                            <Switch
                                checked={data.is_default}
                                onCheckedChange={(c) =>
                                    setData('is_default', c)
                                }
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowModal(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Guardando...' : 'Guardar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={showTestModal} onOpenChange={setShowTestModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Enviar Email de Prueba</DialogTitle>
                        <DialogDescription>
                            Ingresa el email donde recibirás la prueba
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Email de prueba *</Label>
                            <Input
                                type="email"
                                value={testEmail}
                                onChange={(e) => setTestEmail(e.target.value)}
                                placeholder="test@email.com"
                                required
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowTestModal(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            onClick={handleTest}
                            disabled={testing || !testEmail}
                        >
                            {testing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Enviar Prueba
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
