import { Head, useForm, router } from '@inertiajs/react';
import {
    MessageCircle,
    Send,
    Save,
    Bot,
    Phone,
    ShieldCheck,
    ExternalLink,
    HelpCircle,
    CheckCircle2,
    AlertCircle,
    Settings,
    History,
    MessageSquare,
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
        whatsapp_phone_number_id: string | null;
        whatsapp_access_token: string | null;
        whatsapp_business_id: string | null;
        whatsapp_api_version: string | null;
    } | null;
    has_credentials: boolean;
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

function TelegramLoginWidget({ botUsername }: TelegramLoginWidgetProps) {
    const scriptLoaded = typeof window !== 'undefined' && document.querySelector('script[src*="telegram-widget.js"]');

    if (!scriptLoaded) {
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://telegram.org/js/telegram-widget.js?22';
        script.setAttribute('data-telegram-login', botUsername);
        script.setAttribute('data-size', 'large');
        script.setAttribute('data-radius', '8');
        script.setAttribute('data-auth-url', '/telegram/login-callback');
        script.setAttribute('data-request-access', 'write');
        document.body.appendChild(script);
    }

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
    automation,
}: PageProps) {
    const [testingTelegram, setTestingTelegram] = useState(false);
    const [testingWhatsapp, setTestingWhatsapp] = useState(false);
    const [sendingTestMessage, setSendingTestMessage] = useState(false);
    const [telegramStatus, setTelegramStatus] = useState<'idle' | 'success' | 'error'>('idle');
    const [whatsappStatus, setWhatsappStatus] = useState<'idle' | 'success' | 'error'>('idle');
    const [botUsername, setBotUsername] = useState(credentials?.telegram_bot_username ?? '');

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
                toast.error('Error al guardar las credenciales. Revisa los campos.');
            },
        });
    };

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

            const result = await resp.json();

            if (result.success) {
                setTelegramStatus('success');
                const username = result.bot_username ?? '';
                setBotUsername(username);
                setData('telegram_bot_username', username);
                toast.success(result.message);
            } else {
                setTelegramStatus('error');
                toast.error(result.message);
            }
        } catch {
            setTelegramStatus('error');
            toast.error('Error de conexión con el servidor.');
        } finally {
            setTestingTelegram(false);
        }
    };

    const sendTestMessage = async () => {
        if (!botUsername) {
            toast.error('El username del bot no está configurado. Haz clic en "Probar Conexión" primero.');
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
                body: JSON.stringify({ telegram_bot_token: botToken }),
            });

            const result = await resp.json();

            if (result.success) {
                toast.success('Mensaje de prueba enviado. Revisa tu Telegram.');
            } else {
                // Handle specific error when user hasn't started the bot
                if (result.error_code === 403 || result.message?.includes('chat not found') || result.message?.includes('blocked')) {
                    toast.error('No se pudo enviar el mensaje. Primero debes abrir el chat del bot y presionar "Iniciar" (/start).');
                } else {
                    toast.error(result.message || 'Error al enviar mensaje de prueba.');
                }
            }
        } catch {
            toast.error('Error de conexión con el servidor.');
        } finally {
            setSendingTestMessage(false);
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

            const result = await resp.json();

            if (result.success) {
                setWhatsappStatus('success');
                if (result.business_id) {
                    setData('whatsapp_business_id', result.business_id);
                }
                toast.success(result.message);
            } else {
                setWhatsappStatus('error');
                toast.error(result.message);
            }
        } catch {
            setWhatsappStatus('error');
            toast.error('Error de conexión con el servidor.');
        } finally {
            setTestingWhatsapp(false);
        }
    };

    return (
        <>
            <Head title="Automatiza tu negocio" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="mx-auto flex max-w-5xl flex-col gap-6 p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                Automatiza tu negocio
                            </h1>
                            <p className="text-muted-foreground">
                                Conecta tus canales de comunicación para automatizar
                                respuestas con n8n
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
                                {processing ? 'Guardando...' : 'Guardar Cambios'}
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
                                        <CardTitle className="text-base">Telegram</CardTitle>
                                    </div>
                                    <CardDescription>
                                        Conecta tu Bot de Telegram para automatizar mensajes
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="telegram_bot_token">
                                            Token del Bot
                                        </Label>
                                        <Input
                                            id="telegram_bot_token"
                                            type="password"
                                            value={data.telegram_bot_token}
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
                                                {errors.telegram_bot_token}
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
                                                const value = e.target.value;
                                                setBotUsername(value);
                                                setData('telegram_bot_username', value);
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
                                        {telegramStatus === 'success' && (
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
                                    {telegramStatus === 'success' && botUsername && (
                                        <div className="space-y-2 pt-2 border-t">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="w-full gap-2"
                                                onClick={() => window.open(`https://t.me/${botUsername}`, '_blank', 'noopener,noreferrer')}
                                            >
                                                <MessageSquare className="h-3.5 w-3.5" />
                                                Abrir Chat en Telegram
                                            </Button>
                                            {/*      <Button
                                                type="button"
                                                variant="default"
                                                size="sm"
                                                className="w-full gap-2"
                                                onClick={sendTestMessage}
                                                disabled={sendingTestMessage}
                                            >
                                                {sendingTestMessage ? (
                                                    <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                ) : (
                                                    <Send className="h-3.5 w-3.5" />
                                                )}
                                                {sendingTestMessage ? 'Enviando...' : 'Enviar Mensaje de Prueba'}
                                            </Button> */}
                                            <p className="text-[11px] text-muted-foreground text-center">
                                                Para recibir notificaciones, abre el chat y presiona <kbd className="px-1.5 py-0.5 bg-muted rounded text-xs font-mono">Iniciar</kbd> o <kbd className="px-1.5 py-0.5 bg-muted rounded text-xs font-mono">/start</kbd> en Telegram.
                                            </p>
                                        </div>
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
                                            <li>2. Envía /newbot y sigue las instrucciones</li>
                                            <li>3. Copia el token que te entregue BotFather</li>
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
                                        <CardTitle className="text-base">WhatsApp</CardTitle>
                                    </div>
                                    <CardDescription>
                                        Conecta WhatsApp Cloud API de Meta
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="whatsapp_phone_number_id">
                                            Phone Number ID
                                        </Label>
                                        <Input
                                            id="whatsapp_phone_number_id"
                                            value={data.whatsapp_phone_number_id}
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
                                                {errors.whatsapp_phone_number_id}
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
                                            value={data.whatsapp_access_token}
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
                                                {errors.whatsapp_access_token}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="whatsapp_business_id">
                                            Business ID
                                        </Label>
                                        <Input
                                            id="whatsapp_business_id"
                                            value={data.whatsapp_business_id}
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
                                            value={data.whatsapp_api_version}
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
                                        {whatsappStatus === 'success' && (
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

                                    <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                                        <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                            <ShieldCheck className="h-3 w-3" />
                                            Tus credenciales están cifradas
                                        </div>
                                        <p className="mt-1 text-[11px] text-muted-foreground">
                                            Los tokens se almacenan encriptados en la base de
                                            datos usando el cifrado de Laravel. n8n los leerá
                                            a través de la API interna.
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
                                            <li>2. Configura un producto WhatsApp</li>
                                            <li>3. Consigue un Token de Acceso Permanente</li>
                                        </ol>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </form>

                    {/* Telegram Login Widget */}
                    {has_credentials && botUsername && (
                        <Card className="border-sky-200/50 bg-sky-50/30 dark:bg-sky-900/10">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400">
                                        <Bot className="h-4 w-4" />
                                    </div>
                                    <CardTitle className="text-base">Iniciar Sesión con Telegram</CardTitle>
                                </div>
                                <CardDescription>
                                    Permite a tus usuarios autenticarse en la plataforma usando su cuenta de Telegram
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <TelegramLoginWidget botUsername={botUsername} />
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
                                Configura reportes automáticos para recibir información
                                clave de tu negocio.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {automation && (
                                <div className="mb-4 rounded-lg border border-border/50 bg-muted/20 p-3">
                                    <div className="flex items-center gap-2 text-xs font-medium">
                                        <span
                                            className={`inline-block h-2 w-2 rounded-full ${automation.enabled
                                                    ? 'bg-emerald-500'
                                                    : 'bg-muted-foreground'
                                                }`}
                                        />
                                        {automation.enabled ? 'Activo' : 'Inactivo'}
                                    </div>
                                    <div className="mt-1.5 space-y-0.5 text-[11px] text-muted-foreground">
                                        <p>
                                            <span className="font-medium">Canal:</span>{' '}
                                            {automation.channel === 'telegram'
                                                ? 'Telegram'
                                                : automation.channel === 'whatsapp'
                                                    ? 'WhatsApp'
                                                    : 'Telegram + WhatsApp'}{' '}
                                            | {automation.frequency === 'daily'
                                                ? 'Diario'
                                                : automation.frequency === 'weekly'
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
                                                <span className="font-medium">Estado:</span>{' '}
                                                <span
                                                    className={
                                                        automation.last_run_status === 'success'
                                                            ? 'text-emerald-600'
                                                            : 'text-destructive'
                                                    }
                                                >
                                                    {automation.last_run_status === 'success'
                                                        ? 'Exitosa'
                                                        : 'Error'}
                                                </span>
                                            </p>
                                        )}
                                        {automation.selected_reports.length > 0 && (
                                            <p>
                                                <span className="font-medium">Reportes:</span>{' '}
                                                {automation.selected_reports.length} configurados
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
        </>
    );
}
