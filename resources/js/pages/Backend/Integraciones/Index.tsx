import { Head } from '@inertiajs/react';
import {
    CheckCircle2,
    CreditCard,
    Loader2,
    Plug,
    Save,
    ShoppingCart,
    Trash2,
    XCircle,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    PROVIDERS,
    type ProviderDefinition,
} from './providers';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Conexiones API', href: '/integraciones-api' },
];

interface IntegrationData {
    id?: number;
    provider: string;
    category: string;
    environment: string | null;
    is_active: boolean;
    credentials: Record<string, string>;
    last_tested_at: string | null;
    last_tested_status: 'ok' | 'error' | null;
    last_tested_message: string | null;
}

interface PageProps {
    integraciones: IntegrationData[];
    n8n_config?: {
        n8n_telegram_proxy_webhook_url: string;
        has_api_key: boolean;
        masked_api_key: string;
    };
}

interface ProviderForm {
    fields: Record<string, string>;
    environment: string;
    is_active: boolean;
    hasSavedCredentials: boolean;
}

interface TestResult {
    status: 'ok' | 'error';
    message: string;
    at: string;
}

const CATEGORY_TABS: { value: string; label: string }[] = [
    { value: 'payment', label: '💳 Pasarelas de Pago' },
    { value: 'bots', label: '⚡ Automatización & Bots' },
    { value: 'ecommerce', label: '🛒 E-commerce' },
];

const CATEGORY_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    payment: CreditCard,
    bots: Zap,
    ecommerce: ShoppingCart,
};

function getCsrfToken(): string {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
        ''
    );
}

export default function Index({ integraciones }: PageProps) {
    const [forms, setForms] = useState<Record<string, ProviderForm>>({});
    const [saving, setSaving] = useState<Record<string, boolean>>({});
    const [testing, setTesting] = useState<Record<string, boolean>>({});
    const [testResults, setTestResults] = useState<Record<string, TestResult>>(
        {},
    );

    useEffect(() => {
        const next: Record<string, ProviderForm> = {};

        for (const definition of PROVIDERS) {
            const saved = integraciones.find(
                (integration) => integration.provider === definition.id,
            );

            next[definition.id] = {
                fields: saved?.credentials ?? {},
                environment:
                    saved?.environment ??
                    definition.environments?.[0]?.value ??
                    '',
                is_active: saved?.is_active ?? false,
                hasSavedCredentials: saved !== undefined,
            };
        }

        setForms(next);
    }, [integraciones]);

    const updateField = (
        provider: string,
        key: string,
        value: string,
    ): void => {
        setForms((prev) => ({
            ...prev,
            [provider]: {
                ...prev[provider],
                fields: { ...prev[provider].fields, [key]: value },
            },
        }));
    };

    const updateEnvironment = (provider: string, value: string): void => {
        setForms((prev) => ({
            ...prev,
            [provider]: { ...prev[provider], environment: value },
        }));
    };

    const toggleActive = (provider: string, checked: boolean): void => {
        setForms((prev) => ({
            ...prev,
            [provider]: { ...prev[provider], is_active: checked },
        }));
    };

    const saveIntegration = async (
        definition: ProviderDefinition,
    ): Promise<void> => {
        const form = forms[definition.id];

        if (!form) return;

        setSaving((prev) => ({ ...prev, [definition.id]: true }));

        try {
            const response = await fetch('/integraciones-api/save', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    provider: definition.id,
                    environment: form.environment,
                    is_active: form.is_active,
                    credentials: form.fields,
                }),
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                const message = result.message ?? 'Error al guardar la integración.';
                throw new Error(message);
            }

            setForms((prev) => ({
                ...prev,
                [definition.id]: {
                    ...prev[definition.id],
                    fields: result.data.credentials,
                    hasSavedCredentials: true,
                },
            }));

            toast.success(result.message ?? 'Integración guardada correctamente.');
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Error al guardar la integración.',
            );
        } finally {
            setSaving((prev) => ({ ...prev, [definition.id]: false }));
        }
    };

    const testIntegration = async (provider: string): Promise<void> => {
        setTesting((prev) => ({ ...prev, [provider]: true }));

        try {
            const response = await fetch(`/integraciones-api/test/${provider}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
            });

            const result = await response.json();
            const ok = response.ok && result.success;

            setTestResults((prev) => ({
                ...prev,
                [provider]: {
                    status: ok ? 'ok' : 'error',
                    message: result.message ?? '',
                    at: new Date().toISOString(),
                },
            }));

            if (ok) {
                toast.success(result.message ?? 'Conexión exitosa.');
            } else {
                toast.error(result.message ?? 'Error al probar la conexión.');
            }
        } catch {
            setTestResults((prev) => ({
                ...prev,
                [provider]: {
                    status: 'error',
                    message: 'Error de conexión con el servidor.',
                    at: new Date().toISOString(),
                },
            }));
            toast.error('Error de conexión con el servidor.');
        } finally {
            setTesting((prev) => ({ ...prev, [provider]: false }));
        }
    };

    const renderProviderCard = (definition: ProviderDefinition) => {
        const form = forms[definition.id];

        if (!form) return null;

        const saved = integraciones.find(
            (integration) => integration.provider === definition.id,
        );
        const lastTest = testResults[definition.id];
        const testStatus = lastTest?.status ?? saved?.last_tested_status;
        const testStatusLabel = lastTest ? null : saved?.last_tested_at;
        const Icon = definition.icon;

        return (
            <Card key={definition.id} className="flex flex-col">
                <CardHeader>
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <div className="rounded-lg border bg-muted p-2">
                                <Icon className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle className="text-base">
                                    {definition.name}
                                </CardTitle>
                                <CardDescription>
                                    {definition.description}
                                </CardDescription>
                            </div>
                        </div>
                        {testStatus && (
                            <Badge
                                variant={
                                    testStatus === 'ok'
                                        ? 'default'
                                        : 'destructive'
                                }
                            >
                                {testStatus === 'ok' ? 'Conectado' : 'Error'}
                            </Badge>
                        )}
                    </div>
                </CardHeader>

                <CardContent className="flex flex-1 flex-col gap-4">
                    {lastTest?.message && (
                        <p className="text-xs text-muted-foreground">
                            {lastTest.message}
                        </p>
                    )}

                    {testStatusLabel && (
                        <p className="text-xs text-muted-foreground">
                            Última prueba:{' '}
                            {new Date(testStatusLabel).toLocaleString()}
                        </p>
                    )}

                    {definition.environments && (
                        <div className="grid gap-2">
                            <Label>Entorno</Label>
                            <Select
                                value={form.environment}
                                onValueChange={(value) =>
                                    updateEnvironment(definition.id, value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecciona un entorno" />
                                </SelectTrigger>
                                <SelectContent>
                                    {definition.environments.map(
                                        (environment) => (
                                            <SelectItem
                                                key={environment.value}
                                                value={environment.value}
                                            >
                                                {environment.label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {definition.fields.map((field) => (
                        <div key={field.key} className="grid gap-2">
                            <Label htmlFor={`${definition.id}-${field.key}`}>
                                {field.label}
                            </Label>
                            <Input
                                id={`${definition.id}-${field.key}`}
                                type={field.type ?? 'text'}
                                placeholder={field.placeholder}
                                value={form.fields[field.key] ?? ''}
                                onChange={(event) =>
                                    updateField(
                                        definition.id,
                                        field.key,
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    ))}

                    <div className="mt-auto flex flex-col gap-3 pt-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor={`${definition.id}-active`}>
                                Activa
                            </Label>
                            <Switch
                                id={`${definition.id}-active`}
                                checked={form.is_active}
                                onCheckedChange={(checked) =>
                                    toggleActive(definition.id, checked)
                                }
                            />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                onClick={() => saveIntegration(definition)}
                                disabled={saving[definition.id]}
                                className="flex-1"
                            >
                                {saving[definition.id] ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Save className="h-4 w-4" />
                                )}
                                Guardar
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => testIntegration(definition.id)}
                                disabled={testing[definition.id]}
                                className="flex-1"
                            >
                                {testing[definition.id] ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Plug className="h-4 w-4" />
                                )}
                                Probar conexión
                            </Button>
                        </div>

                        {form.hasSavedCredentials && (
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                {form.is_active ? (
                                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                                ) : (
                                    <XCircle className="h-3.5 w-3.5 text-muted-foreground" />
                                )}
                                {form.is_active
                                    ? 'Integración activa'
                                    : 'Integración inactiva'}
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Conexiones e Integraciones API" />

            <div className="mx-auto flex max-w-7xl flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Conexiones e Integraciones API
                        </h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Conecta tus pasarelas de pago, bots y tiendas online
                            en un solo lugar
                        </p>
                    </div>
                    <div className="flex items-center gap-2 rounded-lg border bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                        <Trash2 className="h-4 w-4" />
                        Las credenciales se guardan encriptadas
                    </div>
                </div>

                <Tabs defaultValue="payment">
                    <TabsList className="w-fit">
                        {CATEGORY_TABS.map((tab) => {
                            const Icon = CATEGORY_ICONS[tab.value];

                            return (
                                <TabsTrigger
                                    key={tab.value}
                                    value={tab.value}
                                    className="gap-2 rounded-lg data-[state=active]:shadow-sm"
                                >
                                    <Icon className="h-4 w-4" />
                                    {tab.label}
                                </TabsTrigger>
                            );
                        })}
                    </TabsList>

                    {CATEGORY_TABS.map((tab) => (
                        <TabsContent
                            key={tab.value}
                            value={tab.value}
                            className="mt-4"
                        >
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {PROVIDERS.filter(
                                    (definition) =>
                                        definition.category === tab.value,
                                ).map(renderProviderCard)}
                            </div>
                        </TabsContent>
                    ))}
                </Tabs>
            </div>
        </AppLayout>
    );
}
