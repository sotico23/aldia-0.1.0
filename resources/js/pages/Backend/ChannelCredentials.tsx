import { Head, useForm, router } from '@inertiajs/react';
import {
    MessageCircle,
    Send,
    Save,
    Bot,
    Phone,
    ShieldCheck,
    ExternalLink,
    Eye,
    EyeOff,
    HelpCircle,
    CheckCircle2,
    AlertCircle,
    Settings,
    History,
    MessageSquare,
    Link,
    Clock,
    XCircle,
    Globe,
    User,
    Radio,
    Copy,
    Zap,
    Unlink,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import ErrorBoundary from '@/components/error-boundary';
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
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import AutomationConfigDialog from '@/pages/Backend/partials/AutomationConfigDialog';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Automatiza tu negocio', href: '/canales' },
];

interface PageProps {
    credentials: {
        telegram_bot_token: string | null;
        telegram_bot_username: string | null;
        telegram_chat_id: string | null;
        bot_type: string | null;
        whatsapp_phone_number_id: string | null;
        whatsapp_access_token: string | null;
        whatsapp_business_id: string | null;
        whatsapp_api_version: string | null;
    } | null;
    has_credentials: boolean;
    global_telegram_bot_username?: string | null;
    global_whatsapp?: {
        phone_number_id: string | null;
        access_token: string | null;
        business_id: string | null;
        api_version: string | null;
        is_active: boolean;
    } | null;
    global_n8n?: {
        base_url: string | null;
        webhook_url: string | null;
        telegram_proxy_url: string | null;
        is_active: boolean;
    };
    n8n_config?: {
        n8n_base_url: string;
        n8n_telegram_proxy_webhook_url: string;
        has_api_key: boolean;
    };
    app_name?: string;
    automation: {
        channel: string;
        frequency: string;
        execution_time: string;
        enabled: boolean;
        selected_reports: string[];
        last_run_at: string | null;
        next_run_at: string | null;
        last_run_status: string | null;
    } | null;
}

interface TelegramLoginWidgetProps {
    botUsername: string;
}

const sanitizeTelegramUsername = (
    username: string | null | undefined,
): string => (username ?? '').trim().replace(/^@+/, '');

function TelegramLoginWidget({ botUsername }: TelegramLoginWidgetProps) {
    useEffect(() => {
        if (typeof window === 'undefined') return;
        if (document.querySelector('script[src*="telegram-widget.js"]')) return;

        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://telegram.org/js/telegram-widget.js?22';
        script.setAttribute('data-telegram-login', botUsername);
        script.setAttribute('data-size', 'large');
        script.setAttribute('data-radius', '8');
        script.setAttribute('data-auth-url', '/telegram/login-callback');
        script.setAttribute('data-request-access', 'write');
        document.body.appendChild(script);

        return () => {
            document.body.removeChild(script);
        };
    }, [botUsername]);

    return (
        <div
            id="telegram-login-widget"
            data-telegram-login={botUsername}
            data-size="large"
            data-radius="8"
            data-auth-url="/telegram/login-callback"
            data-request-access="write"
            style={{ display: 'block', width: '100%' }}
        />
    );
}

export default function ChannelCredentials({
    credentials,
    has_credentials,
    global_telegram_bot_username,
    global_whatsapp,
    global_n8n,
    n8n_config,
    app_name = 'Aldia',
    automation,
}: PageProps) {
    const [testingTelegram, setTestingTelegram] = useState(false);
    const [testingWhatsapp, setTestingWhatsapp] = useState(false);
    const [sendingWhatsappTestMessage, setSendingWhatsappTestMessage] =
        useState(false);
    const [whatsappStatus, setWhatsappStatus] = useState<
        'idle' | 'success' | 'error'
    >('idle');
    const [whatsappTo, setWhatsappTo] = useState('');
    const [botUsername, setBotUsername] = useState(
        credentials?.telegram_bot_username ?? '',
    );
    const [telegramStatus, setTelegramStatus] = useState<
        'idle' | 'success' | 'error'
    >('idle');
    const [sendingTestMessage, setSendingTestMessage] = useState(false);
    const [linkingStatus, setLinkingStatus] = useState<
        'idle' | 'pending' | 'success' | 'error' | 'expired'
    >('idle');
    const [linkingLink, setLinkingLink] = useState('');
    const [linkingToken, setLinkingToken] = useState('');
    const [activeTab, setActiveTab] = useState<'global' | 'custom'>(
        credentials?.bot_type === 'global' ? 'global' : 'custom',
    );

    const [whatsappActiveTab, setWhatsappActiveTab] = useState<
        'global' | 'custom'
    >('custom');

    const [n8nForm, setN8nForm] = useState({
        base_url: n8n_config?.n8n_base_url ?? '',
        webhook_url: n8n_config?.n8n_telegram_proxy_webhook_url ?? '',
        api_key: '',
    });
    const [n8nHasApiKey, setN8nHasApiKey] = useState(
        n8n_config?.has_api_key ?? false,
    );
    const [showN8nApiKey, setShowN8nApiKey] = useState(false);
    const [n8nDirty, setN8nDirty] = useState(false);
    const [n8nTested, setN8nTested] = useState(false);
    const [n8nTesting, setN8nTesting] = useState(false);
    const [n8nSaving, setN8nSaving] = useState(false);

    const globalN8nProxyUrl =
        global_n8n?.telegram_proxy_url || global_n8n?.webhook_url || '';
    const n8nCanTest = !!(
        n8nForm.base_url.trim() ||
        n8nForm.webhook_url.trim() ||
        globalN8nProxyUrl
    );

    const isGlobalMode = activeTab === 'global';
    const isWhatsappGlobalMode = whatsappActiveTab === 'global';
    const isLinked =
        credentials?.telegram_chat_id !== null &&
        credentials?.telegram_chat_id !== undefined;
    const isWhatsappGlobalConfigured =
        global_whatsapp?.phone_number_id &&
        global_whatsapp?.access_token &&
        global_whatsapp?.is_active;

    const { data, setData, put, processing, errors } = useForm({
        telegram_bot_token: '',
        telegram_bot_username: credentials?.telegram_bot_username ?? '',
        whatsapp_phone_number_id: credentials?.whatsapp_phone_number_id ?? '',
        whatsapp_access_token: '',
        whatsapp_business_id: credentials?.whatsapp_business_id ?? '',
        whatsapp_api_version: credentials?.whatsapp_api_version ?? 'v22.0',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/canales', {
            onSuccess: () => {
                toast.success('Credenciales guardadas correctamente.');
                router.reload({ only: ['credentials', 'has_credentials'] });
            },
            onError: () => {
                toast.error(
                    'Error al guardar las credenciales. Revisa los campos.',
                );
            },
        });
    };

    useEffect(() => {
        const autofillFromIntegrations = async () => {
            try {
                const resp = await fetch(
                    '/api/v1/tenant-credentials/autocomplete',
                    { headers: { Accept: 'application/json' } },
                );

                if (!resp.ok) return;

                const result = await resp.json();
                const creds = result?.data ?? {};
                const patch: Record<string, string> = {};

                if (creds.telegram?.telegram_bot_username && !data.telegram_bot_username) {
                    patch.telegram_bot_username = creds.telegram.telegram_bot_username;
                }
                if (creds.whatsapp) {
                    if (creds.whatsapp.whatsapp_phone_number_id && !data.whatsapp_phone_number_id) {
                        patch.whatsapp_phone_number_id = creds.whatsapp.whatsapp_phone_number_id;
                    }
                    if (creds.whatsapp.whatsapp_business_id && !data.whatsapp_business_id) {
                        patch.whatsapp_business_id = creds.whatsapp.whatsapp_business_id;
                    }
                    if (creds.whatsapp.whatsapp_api_version) {
                        patch.whatsapp_api_version = creds.whatsapp.whatsapp_api_version;
                    }
                }

                if (Object.keys(patch).length > 0) {
                    setData(patch);
                }
            } catch {
                // Silently skip autofill failures
            }
        };

        autofillFromIntegrations();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const getCsrfToken = () =>
        document.head
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || '';

    const testTelegram = async () => {
        setTestingTelegram(true);
        setTelegramStatus('idle');

        try {
            const resp = await fetch('/canales/test-telegram', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    telegram_bot_token: data.telegram_bot_token,
                }),
            });

            if (!resp.ok) {
                throw new Error(`HTTP error: ${resp.status}`);
            }

            const result = await resp.json();

            if (result.success) {
                setTelegramStatus('success');
                const username = result.bot_username ?? '';
                setBotUsername(username);
                setData('telegram_bot_username', username);
                toast.success(result.message ?? 'Conexión exitosa.');
            } else {
                setTelegramStatus('error');
                toast.error(result.message ?? 'Error al conectar.');
            }
        } catch {
            setTelegramStatus('error');
            toast.error('Error de conexión con el servidor.');
        } finally {
            setTestingTelegram(false);
        }
    };

    const testN8nConnection = async () => {
        setN8nTesting(true);
        setN8nTested(false);

        try {
            const resp = await fetch('/canales/n8n-config/test', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    n8n_base_url: n8nForm.base_url,
                    n8n_telegram_proxy_webhook_url: n8nForm.webhook_url,
                }),
            });

            const result = await resp.json().catch(() => null);

            if (resp.ok && result?.success) {
                setN8nTested(true);
                toast.success(result.message ?? 'Conexión exitosa.');
            } else {
                toast.error(
                    result?.message ?? 'Error al probar la conexión n8n.',
                );
            }
        } catch {
            toast.error('Error de conexión con el servidor.');
        } finally {
            setN8nTesting(false);
        }
    };

    const saveN8nConfig = async () => {
        setN8nSaving(true);

        try {
            const resp = await fetch('/canales/n8n-config', {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    n8n_base_url: n8nForm.base_url,
                    n8n_telegram_proxy_webhook_url: n8nForm.webhook_url,
                    n8n_api_key: n8nForm.api_key,
                }),
            });

            const result = await resp.json().catch(() => null);

            if (resp.ok && result?.success) {
                toast.success(result.message ?? 'Configuración guardada.');
                setN8nForm((prev) => ({ ...prev, api_key: '' }));
                setN8nHasApiKey(Boolean(result.data?.has_api_key));
                setN8nDirty(false);
            } else {
                const errors = result?.errors
                    ? Object.values(result.errors).flat().join(', ')
                    : '';
                toast.error(
                    result?.message ||
                        errors ||
                        'Error al guardar la configuración n8n.',
                );
            }
        } catch {
            toast.error('Error de conexión con el servidor.');
        } finally {
            setN8nSaving(false);
        }
    };

    const handleN8nFieldChange = (
        field: 'base_url' | 'webhook_url' | 'api_key',
        value: string,
    ) => {
        setN8nForm((prev) => ({ ...prev, [field]: value }));
        setN8nDirty(true);
        setN8nTested(false);
    };

    const sendTestMessage = async () => {
        if (!botUsername) {
            toast.error(
                'El username del bot no está configurado. Haz clic en "Probar Conexión" primero.',
            );
            return;
        }

        setSendingTestMessage(true);

        try {
            const resp = await fetch('/canales/send-test-message', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    telegram_bot_token: data.telegram_bot_token,
                }),
            });

            if (!resp.ok) {
                throw new Error(`HTTP error: ${resp.status}`);
            }

            const result = await resp.json();

            if (result.success) {
                toast.success('Mensaje de prueba enviado. Revisa tu Telegram.');
            } else {
                if (
                    result.error_code === 403 ||
                    result.message?.includes('chat not found') ||
                    result.message?.includes('blocked')
                ) {
                    toast.error(
                        'No se pudo enviar el mensaje. Primero debes abrir el chat del bot y presionar "Iniciar" (/start) en Telegram.',
                    );
                } else {
                    toast.error(
                        result.message || 'Error al enviar mensaje de prueba.',
                    );
                }
            }
        } catch {
            toast.error('Error de conexión con el servidor.');
        } finally {
            setSendingTestMessage(false);
        }
    };

    const generateLinking = async (
        type: 'global' | 'custom',
    ): Promise<string | null> => {
        setLinkingStatus('pending');
        setLinkingLink('');

        try {
            const resp = await fetch('/canales/telegram/generate-link', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ type }),
            });

            if (!resp.ok) {
                const errData = await resp.json().catch(() => null);
                throw new Error(
                    errData?.message ?? `HTTP error: ${resp.status}`,
                );
            }

            const result = await resp.json();

            if (result.success) {
                const token = result.token ?? '';
                const cleanUsername = sanitizeTelegramUsername(
                    result.bot_username,
                );
                const telegramUrl =
                    cleanUsername && token
                        ? `https://t.me/${cleanUsername}?start=${token}`
                        : (result.telegram_url ?? null);

                setLinkingLink(telegramUrl ?? '');
                setLinkingToken(token);
                setLinkingStatus('pending');
                toast.success(
                    'Enlace de vinculación generado. Ábrelo en Telegram para vincular tu cuenta.',
                );
                startLinkingPoll();

                return telegramUrl;
            }

            setLinkingStatus('error');
            toast.error(
                result.message ?? 'Error al generar el enlace de vinculación.',
            );
        } catch {
            setLinkingStatus('error');
            toast.error('Error de conexión con el servidor.');
        }

        return null;
    };

    const openChatWithLinking = async (type: 'global' | 'custom') => {
        // 1. Abrir la pestaña en blanco de forma síncrona (dentro del evento de
        //    clic) para evitar el bloqueo de popups. NO usar 'noopener' como
        //    feature string: hace que window.open() retorne null y la pestaña
        //    quede en blanco para siempre.
        const newTab = window.open('about:blank', '_blank');

        try {
            // 2. Obtener el token y la URL de vinculación desde el backend.
            const url = await generateLinking(type);

            if (url && newTab) {
                // Cortar la relación opener (equivale a noopener) sin perder la
                // referencia a la pestaña, y cargar el deep link en ella.
                newTab.opener = null;
                newTab.location.href = url;

                return;
            }

            if (url) {
                // Fallback: si el navegador bloqueó la pestaña, redirigir en la
                // misma pestaña.
                window.location.assign(url);

                return;
            }

            if (newTab) {
                newTab.close();
            }
        } catch {
            if (newTab) {
                newTab.close();
            }
        }
    };

    const startLinkingPoll = () => {
        let attempts = 0;
        const maxAttempts = 30;
        let cleared = false;

        const poll = setInterval(async () => {
            attempts++;

            if (attempts >= maxAttempts || cleared) {
                clearInterval(poll);
                if (!cleared && linkingStatus === 'pending') {
                    setLinkingStatus('expired');
                }
                return;
            }

            try {
                const resp = await fetch('/canales', {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!resp.ok) return;

                const data = await resp.json();

                if (data.credentials?.telegram_chat_id) {
                    cleared = true;
                    clearInterval(poll);
                    setLinkingStatus('success');
                    setLinkingLink('');
                    setLinkingToken('');
                    toast.success(
                        '¡Cuenta de Telegram vinculada exitosamente!',
                    );
                    router.reload({
                        only: ['credentials', 'has_credentials'],
                        onSuccess: () => {
                            toast.success(
                                '¡Cuenta de Telegram vinculada exitosamente!',
                            );
                        },
                    });
                }
            } catch {
                // Silently ignore poll errors
            }
        }, 2000);
    };

    const unlinkTelegram = async () => {
        try {
            const resp = await fetch('/canales/telegram/unlink', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
            });

            const result = await resp.json().catch(() => null);

            if (resp.ok && result?.success) {
                setLinkingStatus('idle');
                toast.success(
                    result.message ??
                        'Cuenta de Telegram desvinculada correctamente.',
                );
                router.reload({
                    only: ['credentials', 'has_credentials'],
                });
            } else {
                toast.error(
                    result?.message ?? 'Error al desvincular Telegram.',
                );
            }
        } catch {
            toast.error('Error de conexión con el servidor.');
        }
    };

    const testWhatsapp = async () => {
        setTestingWhatsapp(true);
        setWhatsappStatus('idle');

        try {
            const resp = await fetch('/canales/test-whatsapp', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
            });

            if (!resp.ok) {
                throw new Error(`HTTP error: ${resp.status}`);
            }

            const result = await resp.json();

            if (result.success) {
                setWhatsappStatus('success');
                if (result.business_id) {
                    setData('whatsapp_business_id', result.business_id);
                }
                toast.success(result.message ?? 'Conexión exitosa.');
            } else {
                setWhatsappStatus('error');
                toast.error(result.message ?? 'Error al conectar.');
            }
        } catch {
            setWhatsappStatus('error');
            toast.error('Error de conexión con el servidor.');
        } finally {
            setTestingWhatsapp(false);
        }
    };

    const sendWhatsAppTestMessage = async () => {
        if (!whatsappTo) {
            toast.error('Debes proporcionar un número de WhatsApp destino.');
            return;
        }

        setSendingWhatsappTestMessage(true);

        try {
            const resp = await fetch('/canales/send-whatsapp-test-message', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ whatsapp_to: whatsappTo }),
            });

            if (!resp.ok) {
                throw new Error(`HTTP error: ${resp.status}`);
            }

            const result = await resp.json();

            if (result.success) {
                toast.success('Mensaje de prueba enviado. Revisa tu WhatsApp.');
            } else {
                toast.error(
                    result.message || 'Error al enviar mensaje de prueba.',
                );
            }
        } catch {
            toast.error('Error de conexión con el servidor.');
        } finally {
            setSendingWhatsappTestMessage(false);
        }
    };

    return (
        <ErrorBoundary>
            <Head title="Automatiza tu negocio" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="mx-auto flex max-w-5xl flex-col gap-6 p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                Automatiza tu negocio
                            </h1>
                            <p className="text-muted-foreground">
                                Conecta tus canales de comunicación para
                                automatizar respuestas con n8n
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" asChild className="gap-2">
                                <a href="/automatizaciones/historial">
                                    <History className="h-4 w-4" />
                                    Historial
                                </a>
                            </Button>
                            <Button
                                type="submit"
                                form="channels-form"
                                disabled={processing}
                                className="gap-2"
                            >
                                <Save className="h-4 w-4" />
                                {processing
                                    ? 'Guardando...'
                                    : 'Guardar Cambios'}
                            </Button>
                        </div>
                    </div>

                    <form id="channels-form" onSubmit={handleSubmit}>
                        <div className="grid gap-6 md:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400">
                                            <Send className="h-4 w-4" />
                                        </div>
                                        <CardTitle className="text-base">
                                            Telegram
                                        </CardTitle>
                                    </div>
                                    <CardDescription>
                                        Conecta tu Bot de Telegram para
                                        automatizar mensajes
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Tab selector */}
                                    <div className="flex items-center gap-1 rounded-lg bg-muted p-1">
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                activeTab === 'global'
                                            }
                                            className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${
                                                activeTab === 'global'
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                            onClick={() =>
                                                setActiveTab('global')
                                            }
                                        >
                                            <Globe className="h-3.5 w-3.5" />
                                            Bot Oficial
                                        </button>
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                activeTab === 'custom'
                                            }
                                            className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${
                                                activeTab === 'custom'
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                            onClick={() =>
                                                setActiveTab('custom')
                                            }
                                        >
                                            <User className="h-3.5 w-3.5" />
                                            Mi Bot Personalizado
                                        </button>
                                    </div>

                                    {isGlobalMode ? (
                                        <>
                                            {/* Global mode: no token/username fields */}
                                            <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                                                <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                                    <Globe className="h-3 w-3" />
                                                    Modo Bot Oficial de{' '}
                                                    {app_name}
                                                </div>
                                                <p className="mt-1 text-[11px] text-muted-foreground">
                                                    Conecta tu cuenta de
                                                    Telegram usando el bot
                                                    oficial de {app_name}. No
                                                    necesitas configurar un
                                                    token ni username. Solo
                                                    presiona el botón de abajo
                                                    para vincular tu cuenta.
                                                </p>
                                                {global_telegram_bot_username && (
                                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                                        Bot oficial: @
                                                        {
                                                            global_telegram_bot_username
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            {/* Connection status */}
                                            {isLinked ? (
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        Telegram Vinculado (ID:{' '}
                                                        {
                                                            credentials?.telegram_chat_id
                                                        }
                                                        )
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 gap-1.5 px-2 text-xs text-destructive hover:text-destructive"
                                                        onClick={
                                                            unlinkTelegram
                                                        }
                                                    >
                                                        <Unlink className="h-3.5 w-3.5" />
                                                        Desvincular
                                                    </Button>
                                                </div>
                                            ) : (
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                                    <AlertCircle className="h-3.5 w-3.5" />
                                                    Telegram No Vinculado
                                                </span>
                                            )}

                                            {/* Action buttons for global mode */}
                                            {global_telegram_bot_username && (
                                                <div className="space-y-2 border-t pt-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="w-full gap-2"
                                                        onClick={() =>
                                                            openChatWithLinking(
                                                                'global',
                                                            )
                                                        }
                                                    >
                                                        <MessageSquare className="h-3.5 w-3.5" />
                                                        Abrir Chat en Telegram
                                                    </Button>
                                                </div>
                                            )}

                                            {/* Linking section for global mode */}
                                            {isLinked ? (
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        ¡Cuenta de Telegram
                                                        vinculada exitosamente!
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 gap-1.5 px-2 text-xs text-destructive hover:text-destructive"
                                                        onClick={
                                                            unlinkTelegram
                                                        }
                                                    >
                                                        <Unlink className="h-3.5 w-3.5" />
                                                        Desvincular
                                                    </Button>
                                                </div>
                                            ) : (
                                                <div className="space-y-2 border-t pt-2">
                                                    <Button
                                                        type="button"
                                                        variant="default"
                                                        size="sm"
                                                        className="w-full gap-2"
                                                        onClick={() =>
                                                            generateLinking(
                                                                'global',
                                                            )
                                                        }
                                                        disabled={
                                                            linkingStatus ===
                                                            'pending'
                                                        }
                                                    >
                                                        {linkingStatus ===
                                                        'pending' ? (
                                                            <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                        ) : (
                                                            <Link className="h-3.5 w-3.5" />
                                                        )}
                                                        {linkingStatus ===
                                                        'pending'
                                                            ? 'Generando enlace...'
                                                            : 'Conectar con Bot Oficial'}
                                                    </Button>

                                                    {linkingLink &&
                                                        linkingStatus ===
                                                            'pending' && (
                                                            <div className="space-y-2 rounded-lg border border-border/50 bg-muted/20 p-3">
                                                                <p className="text-[11px] text-muted-foreground">
                                                                    Abre este
                                                                    enlace en
                                                                    Telegram
                                                                    para
                                                                    vincular tu
                                                                    cuenta:
                                                                </p>
                                                                <div className="flex items-center gap-2">
                                                                    <code className="flex-1 truncate rounded bg-background px-2 py-1 font-mono text-[11px]">
                                                                        {
                                                                            linkingLink
                                                                        }
                                                                    </code>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="h-7 w-7 p-0"
                                                                        onClick={() =>
                                                                            openChatWithLinking(
                                                                                'global',
                                                                            )
                                                                        }
                                                                    >
                                                                        <ExternalLink className="h-3.5 w-3.5" />
                                                                    </Button>
                                                                </div>
                                                                <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                                                                    <Clock className="h-3 w-3" />
                                                                    El enlace
                                                                    expira en 15
                                                                    minutos
                                                                </div>
                                                                {linkingToken && (
                                                                    <div className="space-y-1 pt-1">
                                                                        <p className="text-[11px] text-muted-foreground">
                                                                            Si
                                                                            el
                                                                            chat
                                                                            ya
                                                                            existe,
                                                                            abre
                                                                            el
                                                                            chat
                                                                            y
                                                                            escribe:
                                                                        </p>
                                                                        <div className="flex items-center gap-2">
                                                                            <code className="flex-1 truncate rounded bg-background px-2 py-1 font-mono text-[11px]">
                                                                                /start{' '}
                                                                                {
                                                                                    linkingToken
                                                                                }
                                                                            </code>
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                className="h-7 w-7 p-0"
                                                                                onClick={async () => {
                                                                                    await navigator.clipboard.writeText(
                                                                                        `/start ${linkingToken}`,
                                                                                    );
                                                                                    toast.success(
                                                                                        'Comando copiado al portapapeles.',
                                                                                    );
                                                                                }}
                                                                            >
                                                                                <Copy className="h-3.5 w-3.5" />
                                                                            </Button>
                                                                        </div>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        )}

                                                    {linkingStatus ===
                                                        'success' && (
                                                        <div className="flex items-center gap-2 text-xs text-emerald-600">
                                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                                            ¡Cuenta vinculada
                                                            exitosamente!
                                                        </div>
                                                    )}

                                                    {linkingStatus ===
                                                        'error' && (
                                                        <div className="flex items-center gap-2 text-xs text-destructive">
                                                            <XCircle className="h-3.5 w-3.5" />
                                                            Error al generar el
                                                            enlace. Intenta de
                                                            nuevo.
                                                        </div>
                                                    )}

                                                    {linkingStatus ===
                                                        'expired' && (
                                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                            <Clock className="h-3.5 w-3.5" />
                                                            El enlace expiró.
                                                            Genera uno nuevo.
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            {/* Custom mode: token and username fields */}
                                            <div className="space-y-1.5">
                                                <Label htmlFor="telegram_bot_token">
                                                    Token del Bot
                                                </Label>
                                                <Input
                                                    id="telegram_bot_token"
                                                    type="password"
                                                    value={
                                                        data.telegram_bot_token
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'telegram_bot_token',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder={
                                                        has_credentials
                                                            ? '••••••••••••••••'
                                                            : '1234567890:ABCdefGHIjkl...'
                                                    }
                                                />
                                                {errors.telegram_bot_token && (
                                                    <p className="text-xs text-destructive">
                                                        {
                                                            errors.telegram_bot_token
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label htmlFor="telegram_bot_username">
                                                    Username del Bot
                                                </Label>
                                                <Input
                                                    id="telegram_bot_username"
                                                    value={botUsername}
                                                    onChange={(e) => {
                                                        const value =
                                                            e.target.value;
                                                        setBotUsername(value);
                                                        setData(
                                                            'telegram_bot_username',
                                                            value,
                                                        );
                                                    }}
                                                    placeholder="@tunombre_bot"
                                                />
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={testTelegram}
                                                    disabled={testingTelegram}
                                                    className="gap-1.5"
                                                >
                                                    {testingTelegram ? (
                                                        <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                    ) : (
                                                        <Bot className="h-3.5 w-3.5" />
                                                    )}
                                                    {testingTelegram
                                                        ? 'Probando...'
                                                        : 'Probar Conexión'}
                                                </Button>
                                                {telegramStatus ===
                                                    'success' && (
                                                    <span className="flex items-center gap-1 text-xs text-emerald-600">
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        Conectado
                                                    </span>
                                                )}
                                                {telegramStatus === 'error' && (
                                                    <span className="flex items-center gap-1 text-xs text-destructive">
                                                        <AlertCircle className="h-3.5 w-3.5" />
                                                        Error
                                                    </span>
                                                )}
                                            </div>

                                            {/* Botones de acción post-conexión */}
                                            {telegramStatus === 'success' &&
                                                botUsername && (
                                                    <div className="space-y-2 border-t pt-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="w-full gap-2"
                                                            onClick={() =>
                                                                openChatWithLinking(
                                                                    'custom',
                                                                )
                                                            }
                                                        >
                                                            <MessageSquare className="h-3.5 w-3.5" />
                                                            Abrir Chat en
                                                            Telegram
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="default"
                                                            size="sm"
                                                            className="w-full gap-2"
                                                            onClick={
                                                                sendTestMessage
                                                            }
                                                            disabled={
                                                                sendingTestMessage
                                                            }
                                                        >
                                                            {sendingTestMessage ? (
                                                                <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                            ) : (
                                                                <Send className="h-3.5 w-3.5" />
                                                            )}
                                                            {sendingTestMessage
                                                                ? 'Enviando...'
                                                                : 'Enviar Mensaje de Prueba'}
                                                        </Button>
                                                        <p className="text-center text-[11px] text-muted-foreground">
                                                            Para recibir
                                                            notificaciones, abre
                                                            el chat y presiona{' '}
                                                            <kbd className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                                                Iniciar
                                                            </kbd>{' '}
                                                            o{' '}
                                                            <kbd className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                                                /start
                                                            </kbd>{' '}
                                                            en Telegram.
                                                        </p>
                                                    </div>
                                                )}

                                            {/* Multi-tenant linking section */}
                                            {telegramStatus === 'success' &&
                                                botUsername && (
                                                    <div className="space-y-2 border-t pt-2">
                                                        {credentials?.telegram_chat_id ? (
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                                                    Telegram
                                                                    Vinculado (ID:{' '}
                                                                    {
                                                                        credentials.telegram_chat_id
                                                                    }
                                                                    )
                                                                </span>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-7 gap-1.5 px-2 text-xs text-destructive hover:text-destructive"
                                                                    onClick={
                                                                        unlinkTelegram
                                                                    }
                                                                >
                                                                    <Unlink className="h-3.5 w-3.5" />
                                                                    Desvincular
                                                                </Button>
                                                            </div>
                                                        ) : (
                                                            <>
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="w-full gap-2"
                                                                    onClick={() =>
                                                                        generateLinking(
                                                                            'custom',
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        linkingStatus ===
                                                                        'pending'
                                                                    }
                                                                >
                                                                    {linkingStatus ===
                                                                    'pending' ? (
                                                                        <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                                    ) : (
                                                                        <Link className="h-3.5 w-3.5" />
                                                                    )}
                                                                    {linkingStatus ===
                                                                    'pending'
                                                                        ? 'Generando enlace...'
                                                                        : 'Generar Enlace de Vinculación'}
                                                                </Button>

                                                                {linkingLink &&
                                                                    linkingStatus ===
                                                                        'pending' && (
                                                                        <div className="space-y-2 rounded-lg border border-border/50 bg-muted/20 p-3">
                                                                            <p className="text-[11px] text-muted-foreground">
                                                                                Abre
                                                                                este
                                                                                enlace
                                                                                en
                                                                                Telegram
                                                                                para
                                                                                vincular
                                                                                tu
                                                                                cuenta:
                                                                            </p>
                                                                            <div className="flex items-center gap-2">
                                                                                <code className="flex-1 truncate rounded bg-background px-2 py-1 font-mono text-[11px]">
                                                                                    {
                                                                                        linkingLink
                                                                                    }
                                                                                </code>
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    className="h-7 w-7 p-0"
                                                                                    onClick={() =>
                                                                                        openChatWithLinking(
                                                                                            'custom',
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                                                </Button>
                                                                            </div>
                                                                            <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                                                                                <Clock className="h-3 w-3" />
                                                                                El
                                                                                enlace
                                                                                expira
                                                                                en
                                                                                15
                                                                                minutos
                                                                            </div>
                                                                            {linkingToken && (
                                                                                <div className="space-y-1 pt-1">
                                                                                    <p className="text-[11px] text-muted-foreground">
                                                                                        Si
                                                                                        el
                                                                                        chat
                                                                                        ya
                                                                                        existe,
                                                                                        abre
                                                                                        el
                                                                                        chat
                                                                                        y
                                                                                        escribe:
                                                                                    </p>
                                                                                    <div className="flex items-center gap-2">
                                                                                        <code className="flex-1 truncate rounded bg-background px-2 py-1 font-mono text-[11px]">
                                                                                            /start{' '}
                                                                                            {
                                                                                                linkingToken
                                                                                            }
                                                                                        </code>
                                                                                        <Button
                                                                                            type="button"
                                                                                            variant="ghost"
                                                                                            size="sm"
                                                                                            className="h-7 w-7 p-0"
                                                                                            onClick={async () => {
                                                                                                await navigator.clipboard.writeText(
                                                                                                    `/start ${linkingToken}`,
                                                                                                );
                                                                                                toast.success(
                                                                                                    'Comando copiado al portapapeles.',
                                                                                                );
                                                                                            }}
                                                                                        >
                                                                                            <Copy className="h-3.5 w-3.5" />
                                                                                        </Button>
                                                                                    </div>
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    )}

                                                                {linkingStatus ===
                                                                    'success' && (
                                                                    <div className="flex items-center gap-2 text-xs text-emerald-600">
                                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                                        ¡Cuenta
                                                                        vinculada
                                                                        exitosamente!
                                                                    </div>
                                                                )}

                                                                {linkingStatus ===
                                                                    'error' && (
                                                                    <div className="flex items-center gap-2 text-xs text-destructive">
                                                                        <XCircle className="h-3.5 w-3.5" />
                                                                        Error al
                                                                        generar
                                                                        el
                                                                        enlace.
                                                                        Intenta
                                                                        de
                                                                        nuevo.
                                                                    </div>
                                                                )}

                                                                {linkingStatus ===
                                                                    'expired' && (
                                                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                        <Clock className="h-3.5 w-3.5" />
                                                                        El
                                                                        enlace
                                                                        expiró.
                                                                        Genera
                                                                        uno
                                                                        nuevo.
                                                                    </div>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                )}
                                        </>
                                    )}

                                    <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                                        <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                            <HelpCircle className="h-3 w-3" />
                                            ¿Cómo crear un Bot?
                                        </div>
                                        <ol className="mt-2 space-y-1 text-[11px] text-muted-foreground">
                                            <li>
                                                1. Abre{' '}
                                                <a
                                                    href="https://t.me/BotFather"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="font-medium text-primary underline-offset-2 hover:underline"
                                                >
                                                    @BotFather
                                                    <ExternalLink className="ml-0.5 inline h-2.5 w-2.5" />
                                                </a>{' '}
                                                en Telegram
                                            </li>
                                            <li>
                                                2. Envía /newbot y sigue las
                                                instrucciones
                                            </li>
                                            <li>
                                                3. Copia el token que te
                                                entregue BotFather y No Olvides
                                                agregar el Nombre Del Bot
                                            </li>
                                        </ol>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            <MessageCircle className="h-4 w-4" />
                                        </div>
                                        <CardTitle className="text-base">
                                            WhatsApp
                                        </CardTitle>
                                    </div>
                                    <CardDescription>
                                        Conecta WhatsApp Cloud API de Meta
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Tab selector for Global vs Custom WhatsApp */}
                                    <div className="flex items-center gap-1 rounded-lg bg-muted p-1">
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                whatsappActiveTab === 'global'
                                            }
                                            disabled={
                                                !isWhatsappGlobalConfigured
                                            }
                                            className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${
                                                whatsappActiveTab === 'global'
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            } ${!isWhatsappGlobalConfigured ? 'cursor-not-allowed opacity-50' : ''}`}
                                            onClick={() =>
                                                isWhatsappGlobalConfigured &&
                                                setWhatsappActiveTab('global')
                                            }
                                        >
                                            <Globe className="h-3.5 w-3.5" />
                                            WhatsApp Global
                                        </button>
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                whatsappActiveTab === 'custom'
                                            }
                                            className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${
                                                whatsappActiveTab === 'custom'
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                            onClick={() =>
                                                setWhatsappActiveTab('custom')
                                            }
                                        >
                                            <User className="h-3.5 w-3.5" />
                                            Mi WhatsApp Personalizado
                                        </button>
                                    </div>

                                    {isWhatsappGlobalMode ? (
                                        <>
                                            {/* Global mode: show global WhatsApp config */}
                                            <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                                                <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                                    <Globe className="h-3 w-3" />
                                                    Modo WhatsApp Global de{' '}
                                                    {app_name}
                                                </div>
                                                <p className="mt-1 text-[11px] text-muted-foreground">
                                                    Usa la configuración global
                                                    de WhatsApp definida en la
                                                    página de configuración de
                                                    n8n (Configuración Web &gt;
                                                    n8n). No necesitas
                                                    configurar credenciales
                                                    aquí.
                                                </p>
                                                {global_whatsapp && (
                                                    <div className="mt-2 space-y-1 text-[11px] text-muted-foreground">
                                                        <p>
                                                            Phone Number ID:{' '}
                                                            <code className="font-mono">
                                                                {global_whatsapp.phone_number_id ||
                                                                    'No configurado'}
                                                            </code>
                                                        </p>
                                                        <p>
                                                            Business ID:{' '}
                                                            <code className="font-mono">
                                                                {global_whatsapp.business_id ||
                                                                    'No configurado'}
                                                            </code>
                                                        </p>
                                                        <p>
                                                            API Version:{' '}
                                                            <code className="font-mono">
                                                                {global_whatsapp.api_version ||
                                                                    'v22.0'}
                                                            </code>
                                                        </p>
                                                        <p
                                                            className={`font-medium ${global_whatsapp.is_active ? 'text-emerald-600' : 'text-destructive'}`}
                                                        >
                                                            Estado:{' '}
                                                            {global_whatsapp.is_active
                                                                ? 'Activo'
                                                                : 'Inactivo'}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>

                                            {/* Connection status for global mode */}
                                            {isWhatsappGlobalConfigured ? (
                                                <div className="flex items-center gap-2 text-xs text-emerald-600">
                                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                                    WhatsApp Global configurado
                                                    y activo
                                                </div>
                                            ) : (
                                                <div className="flex items-center gap-2 text-xs text-amber-600">
                                                    <AlertCircle className="h-3.5 w-3.5" />
                                                    WhatsApp Global no
                                                    configurado. Ve a
                                                    Configuración Web &gt; n8n
                                                    para configurarlo.
                                                </div>
                                            )}

                                            {/* Test connection button for global mode */}
                                            {isWhatsappGlobalConfigured && (
                                                <div className="flex items-center gap-2 border-t pt-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={testWhatsapp}
                                                        disabled={
                                                            testingWhatsapp
                                                        }
                                                        className="gap-1.5"
                                                    >
                                                        {testingWhatsapp ? (
                                                            <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                        ) : (
                                                            <Phone className="h-3.5 w-3.5" />
                                                        )}
                                                        {testingWhatsapp
                                                            ? 'Probando...'
                                                            : 'Probar Conexión Global'}
                                                    </Button>
                                                    {whatsappStatus ===
                                                        'success' && (
                                                        <span className="flex items-center gap-1 text-xs text-emerald-600">
                                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                                            Conectado
                                                        </span>
                                                    )}
                                                    {whatsappStatus ===
                                                        'error' && (
                                                        <span className="flex items-center gap-1 text-xs text-destructive">
                                                            <AlertCircle className="h-3.5 w-3.5" />
                                                            Error
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            {/* Custom mode: show credential fields */}
                                            <div className="space-y-1.5">
                                                <Label htmlFor="whatsapp_phone_number_id">
                                                    Phone Number ID
                                                </Label>
                                                <Input
                                                    id="whatsapp_phone_number_id"
                                                    value={
                                                        data.whatsapp_phone_number_id
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'whatsapp_phone_number_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="123456789012345"
                                                />
                                                {errors.whatsapp_phone_number_id && (
                                                    <p className="text-xs text-destructive">
                                                        {
                                                            errors.whatsapp_phone_number_id
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label htmlFor="whatsapp_access_token">
                                                    Access Token (Permanente)
                                                </Label>
                                                <Input
                                                    id="whatsapp_access_token"
                                                    type="password"
                                                    value={
                                                        data.whatsapp_access_token
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'whatsapp_access_token',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder={
                                                        has_credentials
                                                            ? '••••••••••••••••'
                                                            : 'EAAx...'
                                                    }
                                                />
                                                {errors.whatsapp_access_token && (
                                                    <p className="text-xs text-destructive">
                                                        {
                                                            errors.whatsapp_access_token
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label htmlFor="whatsapp_business_id">
                                                    Business ID
                                                </Label>
                                                <Input
                                                    id="whatsapp_business_id"
                                                    value={
                                                        data.whatsapp_business_id
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'whatsapp_business_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="123456789012345"
                                                />
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label htmlFor="whatsapp_api_version">
                                                    API Version
                                                </Label>
                                                <Input
                                                    id="whatsapp_api_version"
                                                    value={
                                                        data.whatsapp_api_version
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'whatsapp_api_version',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="v22.0"
                                                />
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={testWhatsapp}
                                                    disabled={testingWhatsapp}
                                                    className="gap-1.5"
                                                >
                                                    {testingWhatsapp ? (
                                                        <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                    ) : (
                                                        <Phone className="h-3.5 w-3.5" />
                                                    )}
                                                    {testingWhatsapp
                                                        ? 'Probando...'
                                                        : 'Probar Conexión'}
                                                </Button>
                                                {whatsappStatus ===
                                                    'success' && (
                                                    <span className="flex items-center gap-1 text-xs text-emerald-600">
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        Conectado
                                                    </span>
                                                )}
                                                {whatsappStatus === 'error' && (
                                                    <span className="flex items-center gap-1 text-xs text-destructive">
                                                        <AlertCircle className="h-3.5 w-3.5" />
                                                        Error
                                                    </span>
                                                )}
                                            </div>

                                            {whatsappStatus === 'success' && (
                                                <div className="space-y-2 border-t pt-2">
                                                    <div className="space-y-1.5">
                                                        <Label htmlFor="whatsapp_test_to">
                                                            Número destino para
                                                            mensaje de prueba
                                                        </Label>
                                                        <Input
                                                            id="whatsapp_test_to"
                                                            value={whatsappTo}
                                                            onChange={(e) =>
                                                                setWhatsappTo(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="+34600123456"
                                                        />
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="default"
                                                        size="sm"
                                                        className="w-full gap-2"
                                                        onClick={
                                                            sendWhatsAppTestMessage
                                                        }
                                                        disabled={
                                                            sendingWhatsappTestMessage
                                                        }
                                                    >
                                                        {sendingWhatsappTestMessage ? (
                                                            <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                        ) : (
                                                            <Send className="h-3.5 w-3.5" />
                                                        )}
                                                        {sendingWhatsappTestMessage
                                                            ? 'Enviando...'
                                                            : 'Enviar Mensaje de Prueba'}
                                                    </Button>
                                                    <p className="text-center text-[11px] text-muted-foreground">
                                                        El mensaje de prueba se
                                                        enviará al número
                                                        proporcionado usando la
                                                        API de WhatsApp Cloud.
                                                    </p>
                                                </div>
                                            )}
                                        </>
                                    )}

                                    <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                                        <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                            <ShieldCheck className="h-3 w-3" />
                                            Tus credenciales están cifradas
                                        </div>
                                        <p className="mt-1 text-[11px] text-muted-foreground">
                                            Los tokens se almacenan encriptados
                                            en la base de datos usando el
                                            cifrado de Laravel. n8n los leerá a
                                            través de la API interna.
                                        </p>
                                    </div>

                                    <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                                        <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                            <HelpCircle className="h-3 w-3" />
                                            ¿Cómo obtener credenciales?
                                        </div>
                                        <ol className="mt-2 space-y-1 text-[11px] text-muted-foreground">
                                            <li>
                                                1. Ve al{' '}
                                                <a
                                                    href="https://developers.facebook.com/apps/"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="font-medium text-primary underline-offset-2 hover:underline"
                                                >
                                                    Portal de Meta Developers
                                                    <ExternalLink className="ml-0.5 inline h-2.5 w-2.5" />
                                                </a>
                                            </li>
                                            <li>
                                                2. Configura un producto
                                                WhatsApp
                                            </li>
                                            <li>
                                                3. Consigue un Token de Acceso
                                                Permanente
                                            </li>
                                        </ol>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </form>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                    <Zap className="h-4 w-4" />
                                </div>
                                <CardTitle className="text-base">
                                    ⚡ Configuración Personal de n8n
                                </CardTitle>
                            </div>
                            <CardDescription>
                                Configuración de n8n para automatización de
                                reportes y canal proxy de Telegram. Cada
                                usuario/negocio gestiona sus propios datos de
                                manera aislada.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="n8n_base_url">
                                    Base URL de n8n
                                </Label>
                                <Input
                                    id="n8n_base_url"
                                    type="url"
                                    value={n8nForm.base_url}
                                    onChange={(e) =>
                                        handleN8nFieldChange(
                                            'base_url',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="https://n8n.tu-dominio.com"
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    URL principal de tu servidor o instancia de
                                    n8n.
                                </p>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="n8n_telegram_proxy_webhook_url">
                                    Webhook URL (Proxy Telegram)
                                </Label>
                                <Input
                                    id="n8n_telegram_proxy_webhook_url"
                                    type="url"
                                    value={n8nForm.webhook_url}
                                    onChange={(e) =>
                                        handleN8nFieldChange(
                                            'webhook_url',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="https://n8n.tu-dominio.com/webhook/webhook-telegram-proxy"
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    URL del webhook en tu n8n que procesa las
                                    peticiones y proxifica Telegram.
                                </p>
                                {globalN8nProxyUrl && (
                                    <p className="text-[11px] text-muted-foreground">
                                        Si se deja vacío, se usará el webhook
                                        global de la plataforma:{' '}
                                        <code className="font-mono">
                                            {globalN8nProxyUrl}
                                        </code>
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="n8n_api_key">
                                    n8n API Key
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="n8n_api_key"
                                        type={
                                            showN8nApiKey
                                                ? 'text'
                                                : 'password'
                                        }
                                        value={n8nForm.api_key}
                                        onChange={(e) =>
                                            handleN8nFieldChange(
                                                'api_key',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            n8nHasApiKey
                                                ? '•••••••••••••••• (ya configurada)'
                                                : 'Ingresa la API Key'
                                        }
                                        className="pr-10"
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowN8nApiKey((prev) => !prev)
                                        }
                                        className="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground transition-colors hover:text-foreground"
                                        aria-label={
                                            showN8nApiKey
                                                ? 'Ocultar API Key'
                                                : 'Mostrar API Key'
                                        }
                                        title={
                                            showN8nApiKey
                                                ? 'Ocultar API Key'
                                                : 'Mostrar API Key'
                                        }
                                    >
                                        {showN8nApiKey ? (
                                            <EyeOff className="h-4 w-4" />
                                        ) : (
                                            <Eye className="h-4 w-4" />
                                        )}
                                    </button>
                                </div>
                                <p className="text-[11px] text-muted-foreground">
                                    La API Key se almacena encriptada de forma
                                    segura. Si la dejas vacía, se mantendrá la
                                    existente.
                                </p>
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={testN8nConnection}
                                    disabled={n8nTesting || !n8nCanTest}
                                    className="gap-2"
                                >
                                    {n8nTesting ? (
                                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                    ) : (
                                        <Radio className="h-4 w-4" />
                                    )}
                                    {n8nTesting
                                        ? 'Probando...'
                                        : 'Probar Conexión n8n'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="default"
                                    onClick={saveN8nConfig}
                                    disabled={
                                        n8nSaving ||
                                        (!n8nDirty && !n8nTested)
                                    }
                                    className="gap-2"
                                >
                                    {n8nSaving ? (
                                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                    ) : (
                                        <Save className="h-4 w-4" />
                                    )}
                                    {n8nSaving
                                        ? 'Guardando...'
                                        : 'Guardar Configuración n8n'}
                                </Button>
                                {n8nTested && (
                                    <span className="flex items-center gap-1 text-xs text-emerald-600">
                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                        Conexión exitosa
                                    </span>
                                )}
                            </div>

                            {!isLinked && (
                                <p className="flex items-start gap-1.5 text-[11px] text-muted-foreground">
                                    <AlertCircle className="mt-0.5 h-3 w-3 shrink-0" />
                                    No tienes un chat de Telegram vinculado:
                                    esta prueba solo verificará la conexión con
                                    n8n. Vincula tu chat con los botones de la
                                    sección Telegram para probar el flujo
                                    completo.
                                </p>
                            )}

                            {n8nHasApiKey && (
                                <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <ShieldCheck className="h-3 w-3" />
                                    API Key configurada y almacenada encriptada.
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Telegram Login Widget */}
                    {has_credentials && botUsername && (
                        <Card className="border-sky-200/50 bg-sky-50/30 dark:bg-sky-900/10">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400">
                                        <Bot className="h-4 w-4" />
                                    </div>
                                    <CardTitle className="text-base">
                                        Iniciar Sesión con Telegram
                                    </CardTitle>
                                </div>
                                <CardDescription>
                                    Login web opcional con Telegram. No vincula
                                    el canal; usa los botones de "Abrir Chat en
                                    Telegram" arriba para vincular el chat del
                                    bot.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <TelegramLoginWidget
                                    botUsername={botUsername}
                                />
                            </CardContent>
                        </Card>
                    )}

                    <Separator />

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400">
                                    <Settings className="h-4 w-4" />
                                </div>
                                <CardTitle className="text-base">
                                    Automatizaciones y Reportes
                                </CardTitle>
                            </div>
                            <CardDescription>
                                Configura reportes automáticos para recibir
                                información clave de tu negocio.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {automation && (
                                <div className="mb-4 rounded-lg border border-border/50 bg-muted/20 p-3">
                                    <div className="flex items-center gap-2 text-xs font-medium">
                                        <span
                                            className={`inline-block h-2 w-2 rounded-full ${
                                                automation.enabled
                                                    ? 'bg-emerald-500'
                                                    : 'bg-muted-foreground'
                                            }`}
                                        />
                                        {automation.enabled
                                            ? 'Activo'
                                            : 'Inactivo'}
                                    </div>
                                    <div className="mt-1.5 space-y-0.5 text-[11px] text-muted-foreground">
                                        <p>
                                            <span className="font-medium">
                                                Canal:
                                            </span>{' '}
                                            {automation.channel === 'telegram'
                                                ? 'Telegram'
                                                : automation.channel ===
                                                    'whatsapp'
                                                  ? 'WhatsApp'
                                                  : 'Telegram + WhatsApp'}{' '}
                                            |{' '}
                                            {automation.frequency === 'daily'
                                                ? 'Diario'
                                                : automation.frequency ===
                                                    'weekly'
                                                  ? 'Semanal'
                                                  : 'Mensual'}{' '}
                                            a las {automation.execution_time}
                                        </p>
                                        {automation.last_run_at && (
                                            <p>
                                                <span className="font-medium">
                                                    Última ejecución:
                                                </span>{' '}
                                                {automation.last_run_at}
                                            </p>
                                        )}
                                        {automation.last_run_status && (
                                            <p>
                                                <span className="font-medium">
                                                    Estado:
                                                </span>{' '}
                                                <span
                                                    className={
                                                        automation.last_run_status ===
                                                        'success'
                                                            ? 'text-emerald-600'
                                                            : 'text-destructive'
                                                    }
                                                >
                                                    {automation.last_run_status ===
                                                    'success'
                                                        ? 'Exitosa'
                                                        : 'Error'}
                                                </span>
                                            </p>
                                        )}
                                        {automation.selected_reports.length >
                                            0 && (
                                            <p>
                                                <span className="font-medium">
                                                    Reportes:
                                                </span>{' '}
                                                {
                                                    automation.selected_reports
                                                        .length
                                                }{' '}
                                                configurados
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            <AutomationConfigDialog automation={automation} />
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        </ErrorBoundary>
    );
}
