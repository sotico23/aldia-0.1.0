import axios from 'axios';
import {
    CheckCircle2,
    Copy,
    Link2,
    Loader2,
    MessageSquare,
    Phone,
    RefreshCw,
    Save,
    Trash2,
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

const MASKED_SECRET = '••••••••••••••••';

export type TelegramConfig = {
    bot_username?: string;
    bot_token?: string;
    has_bot_token?: boolean;
    has_webhook_secret?: boolean;
    is_active?: boolean;
    webhook_url?: string;
};

export type WhatsAppConfig = {
    phone_number_id?: string;
    business_id?: string;
    api_version?: string;
    access_token?: string;
    has_access_token?: boolean;
    verify_token?: string;
    has_verify_token?: boolean;
    app_secret?: string;
    has_app_secret?: boolean;
    webhook_key?: string;
    is_active?: boolean;
    webhook_url?: string;
};

type Props = {
    telegram?: TelegramConfig;
    whatsapp?: WhatsAppConfig;
    onUpdated?: (
        channel: 'telegram' | 'whatsapp',
        data: TelegramConfig | WhatsAppConfig,
    ) => void;
};

function errorMessage(error: any, fallback: string): string {
    return (
        error.response?.data?.errors?.bot_token?.[0] ||
        error.response?.data?.errors?.access_token?.[0] ||
        error.response?.data?.errors?.business_id?.[0] ||
        error.response?.data?.message ||
        fallback
    );
}

export default function OfficialChannels({
    telegram: initialTelegram,
    whatsapp: initialWhatsApp,
    onUpdated,
}: Props) {
    const [telegram, setTelegram] = useState<TelegramConfig>(
        initialTelegram ?? {},
    );
    const [whatsapp, setWhatsapp] = useState<WhatsAppConfig>(
        initialWhatsApp ?? {},
    );
    const [telegramSaving, setTelegramSaving] = useState(false);
    const [telegramTesting, setTelegramTesting] = useState(false);
    const [telegramWebhookLoading, setTelegramWebhookLoading] =
        useState(false);
    const [whatsappSaving, setWhatsappSaving] = useState(false);
    const [whatsappTesting, setWhatsappTesting] = useState(false);
    const [whatsappWebhookLoading, setWhatsappWebhookLoading] =
        useState(false);

    useEffect(() => {
        if (initialTelegram) {
            setTelegram(initialTelegram);
        }
    }, [initialTelegram]);

    useEffect(() => {
        if (initialWhatsApp) {
            setWhatsapp(initialWhatsApp);
        }
    }, [initialWhatsApp]);

    const copyToClipboard = async (value: string | undefined, label: string) => {
        if (!value) {
            toast.error(`${label} no está disponible.`);
            return;
        }

        try {
            await navigator.clipboard.writeText(value);
            toast.success(`${label} copiado al portapapeles.`);
        } catch {
            toast.error(`No se pudo copiar ${label.toLowerCase()}.`);
        }
    };

    const saveTelegram = async () => {
        setTelegramSaving(true);

        try {
            const response = await axios.put(
                '/configuracion-web/gateway-settings/telegram',
                telegram,
            );
            const data = response.data?.data ?? {};

            setTelegram((previous) => ({ ...previous, ...data }));
            onUpdated?.('telegram', data);
            toast.success(
                response.data?.message ||
                    'Configuración de Telegram guardada.',
            );
        } catch (error: any) {
            toast.error(
                errorMessage(error, 'Error al guardar la configuración.'),
            );
        } finally {
            setTelegramSaving(false);
        }
    };

    const testTelegram = async () => {
        setTelegramTesting(true);

        try {
            const response = await axios.post(
                '/configuracion-web/gateway-settings/telegram/test',
                {
                    bot_token:
                        telegram.bot_token === MASKED_SECRET
                            ? undefined
                            : telegram.bot_token || undefined,
                    bot_username: telegram.bot_username || undefined,
                },
            );

            if (response.data?.success) {
                toast.success(response.data.message);
                setTelegram((previous) => ({
                    ...previous,
                    bot_username:
                        response.data.bot_username || previous.bot_username,
                }));
            } else {
                toast.error(response.data?.message || 'No se pudo conectar.');
            }
        } catch (error: any) {
            toast.error(errorMessage(error, 'Error al conectar con Telegram.'));
        } finally {
            setTelegramTesting(false);
        }
    };

    const setTelegramWebhook = async () => {
        setTelegramWebhookLoading(true);

        try {
            const response = await axios.post(
                '/configuracion-web/gateway-settings/telegram/webhook',
            );

            if (response.data?.success) {
                toast.success(response.data.message);
            } else {
                toast.error(
                    response.data?.message || 'No se pudo registrar el webhook.',
                );
            }
        } catch (error: any) {
            toast.error(
                errorMessage(error, 'Error al registrar el webhook de Telegram.'),
            );
        } finally {
            setTelegramWebhookLoading(false);
        }
    };

    const deleteTelegramWebhook = async () => {
        if (
            !window.confirm(
                '¿Eliminar el webhook del bot oficial? Telegram dejará de enviar actualizaciones a la plataforma.',
            )
        ) {
            return;
        }

        setTelegramWebhookLoading(true);

        try {
            const response = await axios.delete(
                '/configuracion-web/gateway-settings/telegram/webhook',
            );

            if (response.data?.success) {
                toast.success(response.data.message);
            } else {
                toast.error(
                    response.data?.message || 'No se pudo eliminar el webhook.',
                );
            }
        } catch (error: any) {
            toast.error(
                errorMessage(error, 'Error al eliminar el webhook de Telegram.'),
            );
        } finally {
            setTelegramWebhookLoading(false);
        }
    };

    const saveWhatsApp = async () => {
        setWhatsappSaving(true);

        try {
            const response = await axios.put(
                '/configuracion-web/gateway-settings/whatsapp',
                whatsapp,
            );
            const data = response.data?.data ?? {};

            setWhatsapp((previous) => ({ ...previous, ...data }));
            onUpdated?.('whatsapp', data);
            toast.success(
                response.data?.message ||
                    'Configuración de WhatsApp guardada.',
            );
        } catch (error: any) {
            toast.error(
                errorMessage(error, 'Error al guardar la configuración.'),
            );
        } finally {
            setWhatsappSaving(false);
        }
    };

    const testWhatsApp = async () => {
        setWhatsappTesting(true);

        try {
            const response = await axios.post(
                '/configuracion-web/gateway-settings/whatsapp/test',
                {
                    access_token:
                        whatsapp.access_token === MASKED_SECRET
                            ? undefined
                            : whatsapp.access_token || undefined,
                    phone_number_id: whatsapp.phone_number_id || undefined,
                },
            );

            if (response.data?.success) {
                toast.success(response.data.message);
            } else {
                toast.error(response.data?.message || 'No se pudo conectar.');
            }
        } catch (error: any) {
            toast.error(errorMessage(error, 'Error al conectar con WhatsApp.'));
        } finally {
            setWhatsappTesting(false);
        }
    };

    const subscribeWhatsAppWebhook = async () => {
        setWhatsappWebhookLoading(true);

        try {
            const response = await axios.post(
                '/configuracion-web/gateway-settings/whatsapp/webhook',
            );

            if (response.data?.success) {
                setWhatsapp((previous) => ({
                    ...previous,
                    verify_token:
                        response.data.verify_token || previous.verify_token,
                }));
                toast.success(response.data.message);
                toast.info(
                    'Copia la Callback URL y el Verify Token en el panel de Meta para completar la configuración.',
                );
            } else {
                toast.error(
                    response.data?.message || 'No se pudo suscribir el webhook.',
                );
            }
        } catch (error: any) {
            toast.error(
                errorMessage(error, 'Error al registrar el webhook de WhatsApp.'),
            );
        } finally {
            setWhatsappWebhookLoading(false);
        }
    };

    return (
        <div className="space-y-4">
            <div>
                <h2 className="text-lg font-semibold">Canales oficiales</h2>
                <p className="text-sm text-muted-foreground">
                    Configura los canales compartidos de la plataforma. Los
                    negocios pueden usar estos canales o configurar uno propio
                    desde Canales.
                </p>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MessageSquare className="h-4 w-4" />
                            Telegram oficial
                        </CardTitle>
                        <CardDescription>
                            Bot compartido por todos los negocios que eligen el
                            canal oficial.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <Label htmlFor="official_telegram_active">
                                    Canal activo
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    {telegram.has_bot_token
                                        ? 'Token configurado'
                                        : 'Token pendiente'}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                {telegram.has_webhook_secret && (
                                    <CheckCircle2 className="h-4 w-4 text-green-600" />
                                )}
                                <Switch
                                    id="official_telegram_active"
                                    checked={Boolean(telegram.is_active)}
                                    onCheckedChange={(value) =>
                                        setTelegram((previous) => ({
                                            ...previous,
                                            is_active: value,
                                        }))
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="official_telegram_username">
                                Username del bot
                            </Label>
                            <Input
                                id="official_telegram_username"
                                value={telegram.bot_username || ''}
                                onChange={(event) =>
                                    setTelegram((previous) => ({
                                        ...previous,
                                        bot_username: event.target.value,
                                    }))
                                }
                                placeholder="@aldia_bot"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="official_telegram_token">
                                Token del bot
                            </Label>
                            <Input
                                id="official_telegram_token"
                                type="password"
                                value={telegram.bot_token || ''}
                                onChange={(event) =>
                                    setTelegram((previous) => ({
                                        ...previous,
                                        bot_token: event.target.value,
                                    }))
                                }
                                placeholder={
                                    telegram.has_bot_token
                                        ? MASKED_SECRET
                                        : '123456789:ABCdef...'
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Se almacena cifrado. Déjalo igual para mantener
                                el token actual.
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="official_telegram_webhook">
                                Callback URL
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="official_telegram_webhook"
                                    readOnly
                                    value={telegram.webhook_url || ''}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    onClick={() =>
                                        copyToClipboard(
                                            telegram.webhook_url,
                                            'Callback URL',
                                        )
                                    }
                                >
                                    <Copy className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                onClick={saveTelegram}
                                disabled={telegramSaving}
                                className="gap-2"
                            >
                                {telegramSaving ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Save className="h-4 w-4" />
                                )}
                                Guardar
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={testTelegram}
                                disabled={telegramTesting}
                                className="gap-2"
                            >
                                {telegramTesting ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <RefreshCw className="h-4 w-4" />
                                )}
                                Probar conexión
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={setTelegramWebhook}
                                disabled={telegramWebhookLoading}
                                className="gap-2"
                            >
                                {telegramWebhookLoading ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Link2 className="h-4 w-4" />
                                )}
                                Registrar webhook
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={deleteTelegramWebhook}
                                disabled={telegramWebhookLoading}
                                className="gap-2"
                            >
                                <Trash2 className="h-4 w-4" />
                                Eliminar webhook
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Phone className="h-4 w-4" />
                            WhatsApp oficial
                        </CardTitle>
                        <CardDescription>
                            Número compartido de la plataforma. Los negocios se
                            vinculan por su wa_id.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <Label htmlFor="official_whatsapp_active">
                                    Canal activo
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    {whatsapp.has_access_token
                                        ? 'Credenciales configuradas'
                                        : 'Credenciales pendientes'}
                                </p>
                            </div>
                            <Switch
                                id="official_whatsapp_active"
                                checked={Boolean(whatsapp.is_active)}
                                onCheckedChange={(value) =>
                                    setWhatsapp((previous) => ({
                                        ...previous,
                                        is_active: value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="official_whatsapp_phone_id">
                                    Phone Number ID
                                </Label>
                                <Input
                                    id="official_whatsapp_phone_id"
                                    value={whatsapp.phone_number_id || ''}
                                    onChange={(event) =>
                                        setWhatsapp((previous) => ({
                                            ...previous,
                                            phone_number_id: event.target.value,
                                        }))
                                    }
                                    placeholder="1234567890"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="official_whatsapp_business_id">
                                    Business ID
                                </Label>
                                <Input
                                    id="official_whatsapp_business_id"
                                    value={whatsapp.business_id || ''}
                                    onChange={(event) =>
                                        setWhatsapp((previous) => ({
                                            ...previous,
                                            business_id: event.target.value,
                                        }))
                                    }
                                    placeholder="123456789012345"
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="official_whatsapp_token">
                                    Access Token
                                </Label>
                                <Input
                                    id="official_whatsapp_token"
                                    type="password"
                                    value={whatsapp.access_token || ''}
                                    onChange={(event) =>
                                        setWhatsapp((previous) => ({
                                            ...previous,
                                            access_token: event.target.value,
                                        }))
                                    }
                                    placeholder={
                                        whatsapp.has_access_token
                                            ? MASKED_SECRET
                                            : 'Ingresa el Access Token'
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="official_whatsapp_version">
                                    API Version
                                </Label>
                                <Input
                                    id="official_whatsapp_version"
                                    value={whatsapp.api_version || 'v22.0'}
                                    onChange={(event) =>
                                        setWhatsapp((previous) => ({
                                            ...previous,
                                            api_version: event.target.value,
                                        }))
                                    }
                                    placeholder="v22.0"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="official_whatsapp_app_secret">
                                App Secret
                            </Label>
                            <Input
                                id="official_whatsapp_app_secret"
                                type="password"
                                value={whatsapp.app_secret || ''}
                                onChange={(event) =>
                                    setWhatsapp((previous) => ({
                                        ...previous,
                                        app_secret: event.target.value,
                                    }))
                                }
                                placeholder={
                                    whatsapp.has_app_secret
                                        ? MASKED_SECRET
                                        : 'Se usa para validar la firma HMAC'
                                }
                            />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="official_whatsapp_webhook">
                                    Callback URL
                                </Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="official_whatsapp_webhook"
                                        readOnly
                                        value={whatsapp.webhook_url || ''}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() =>
                                            copyToClipboard(
                                                whatsapp.webhook_url,
                                                'Callback URL',
                                            )
                                        }
                                    >
                                        <Copy className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="official_whatsapp_verify_token">
                                    Verify Token
                                </Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="official_whatsapp_verify_token"
                                        readOnly
                                        value={whatsapp.verify_token || ''}
                                        placeholder="Se genera al guardar"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() =>
                                            copyToClipboard(
                                                whatsapp.verify_token,
                                                'Verify Token',
                                            )
                                        }
                                    >
                                        <Copy className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Meta no permite configurar la Callback URL por API.
                            Copia la URL y el Verify Token en el panel de
                            WhatsApp Cloud API y luego suscribe la app.
                        </p>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                onClick={saveWhatsApp}
                                disabled={whatsappSaving}
                                className="gap-2"
                            >
                                {whatsappSaving ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Save className="h-4 w-4" />
                                )}
                                Guardar
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={testWhatsApp}
                                disabled={whatsappTesting}
                                className="gap-2"
                            >
                                {whatsappTesting ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <RefreshCw className="h-4 w-4" />
                                )}
                                Probar conexión
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={subscribeWhatsAppWebhook}
                                disabled={whatsappWebhookLoading}
                                className="gap-2"
                            >
                                {whatsappWebhookLoading ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Link2 className="h-4 w-4" />
                                )}
                                Suscribir app
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
