import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import {
    CreditCard,
    Save,
    ExternalLink,
    ShieldCheck,
    HelpCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { FormInput } from '@/components/form-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

export default function MercadoPagoConfig({ config }: { config: any }) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('admin.configuracion.viewAny');
    const { data, setData, post, processing, errors } = useForm({
        mercadopago_public_key: config?.mercadopago_public_key || '',
        mercadopago_access_token: '', // Siempre vacío por seguridad
        mercadopago_mode: config?.mercadopago_mode || 'sandbox',
        mercadopago_active: config?.mercadopago_active ?? false,
    });

    const hasSavedToken = !!config?.mercadopago_access_token;

    const [testing, setTesting] = useState(false);

    const handleTest = async () => {
        if (!data.mercadopago_access_token && !hasSavedToken) {
            toast.error('El Access Token es requerido para la prueba.');
            return;
        }

        setTesting(true);
        try {
            const response = await axios.post('/mercadopago/test', {
                mercadopago_access_token: data.mercadopago_access_token,
                mercadopago_mode: data.mercadopago_mode,
            });

            if (response.data.success) {
                toast.success(response.data.message);
            } else {
                toast.error(response.data.message);
            }
        } catch (error: any) {
            const msg =
                error.response?.data?.message ||
                'Error al conectar con MercadoPago.';
            toast.error(msg);
        } finally {
            setTesting(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/mercadopago/config', {
            onSuccess: () => {
                toast.success(
                    'Configuración de MercadoPago guardada exitosamente.',
                );
            },
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            onError: (err) => {
                toast.error('Por favor, revise los errores en el formulario.');
            },
        });
    };

    if (!canAccess) {
        return (
            <AppLayout
                breadcrumbs={[
                    { title: 'Pagos en Línea', href: '/webpay/config' },
                ]}
            >
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">
                        No tienes permiso para acceder a esta página.
                    </p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pagos en Línea', href: '/webpay/config' },
                { title: 'MercadoPago', href: '/mercadopago/config' },
            ]}
        >
            <Head title="Configuración de MercadoPago" />

            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-8 flex items-start gap-6 rounded-[2rem] border border-sky-100 bg-sky-50 p-8 dark:border-sky-900/30 dark:bg-sky-950/20">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/50">
                        <CreditCard className="h-7 w-7 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <h1 className="mb-2 text-2xl font-black tracking-tight text-sky-900 dark:text-sky-100">
                            MercadoPago
                        </h1>
                        <p className="max-w-2xl text-sm font-medium text-sky-700/80 dark:text-sky-300/80">
                            La pasarela de pagos líder en Latinoamérica.
                            <strong>
                                {' '}
                                Acepta tarjetas de crédito, débito y dinero en
                                cuenta de MercadoPago.
                            </strong>
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <form
                            onSubmit={handleSubmit}
                            className="overflow-hidden rounded-[2rem] border bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div className="flex items-center justify-between border-b p-8 dark:border-slate-800">
                                <div>
                                    <h2 className="flex items-center gap-2 text-lg font-black tracking-tight uppercase">
                                        <ShieldCheck className="h-5 w-5 text-sky-500" />
                                        Credenciales de MercadoPago
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        Configura tus llaves de aplicación desde
                                        el panel de vendedores de MercadoPago.
                                    </p>
                                </div>
                                <div className="flex items-center gap-3 rounded-2xl bg-slate-100 px-4 py-2 dark:bg-slate-800">
                                    <Label
                                        htmlFor="mp_active"
                                        className="text-xs font-bold tracking-tight uppercase"
                                    >
                                        Activar MP
                                    </Label>
                                    <Switch
                                        id="mp_active"
                                        checked={data.mercadopago_active}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'mercadopago_active',
                                                checked,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="flex flex-col space-y-6 p-8">
                                <div className="space-y-6 rounded-3xl border bg-slate-50 p-6 dark:border-slate-700 dark:bg-slate-800/50">
                                    <FormInput
                                        id="mp_public_key"
                                        label="Public Key (Clave Pública)"
                                        value={data.mercadopago_public_key}
                                        onChange={(e) =>
                                            setData(
                                                'mercadopago_public_key',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ej: APP_USR-..."
                                        required
                                        error={errors.mercadopago_public_key}
                                    />

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="mp_access_token">
                                                Access Token (Token de Acceso)
                                            </Label>
                                            {hasSavedToken && (
                                                <span className="flex items-center gap-1 text-[10px] font-black tracking-widest text-green-500 uppercase">
                                                    <CheckCircle className="h-3 w-3" />{' '}
                                                    Token Guardado
                                                </span>
                                            )}
                                        </div>
                                        <FormInput
                                            id="mp_access_token"
                                            type="password"
                                            value={
                                                data.mercadopago_access_token
                                            }
                                            onChange={(e) =>
                                                setData(
                                                    'mercadopago_access_token',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={
                                                hasSavedToken
                                                    ? '••••••••••••••••••••••••••••'
                                                    : 'Pega aquí tu Access Token'
                                            }
                                            required={!hasSavedToken}
                                            error={
                                                errors.mercadopago_access_token
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between rounded-3xl border bg-slate-50 p-6 dark:border-slate-700 dark:bg-slate-800/50">
                                    <div className="space-y-1">
                                        <Label className="text-base font-bold">
                                            Modo de Operación
                                        </Label>
                                        <p className="text-sm text-slate-500">
                                            {data.mercadopago_mode ===
                                            'live' ? (
                                                <span className="font-medium text-green-600">
                                                    Estás en producción (Ventas
                                                    Reales).
                                                </span>
                                            ) : (
                                                <span className="font-medium text-amber-600">
                                                    Estás en Sandbox (Modo
                                                    Prueba).
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex items-center space-x-3">
                                        <Label className="text-xs font-black tracking-widest text-slate-400 uppercase">
                                            Sandbox
                                        </Label>
                                        <Switch
                                            checked={
                                                data.mercadopago_mode === 'live'
                                            }
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'mercadopago_mode',
                                                    checked
                                                        ? 'live'
                                                        : 'sandbox',
                                                )
                                            }
                                        />
                                        <Label className="text-xs font-black tracking-widest text-sky-600 uppercase">
                                            Ventas Reales
                                        </Label>
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-between border-t bg-slate-50/50 p-8 dark:border-slate-800 dark:bg-slate-900/50">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={testing}
                                    className="h-14 rounded-2xl border-2 px-8 font-black tracking-wider text-slate-500 uppercase hover:text-slate-900 dark:border-slate-700"
                                    onClick={handleTest}
                                >
                                    {testing
                                        ? 'Probando...'
                                        : 'Probar Conexión'}
                                </Button>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-14 gap-2 rounded-2xl bg-sky-600 px-8 font-black tracking-[0.1em] uppercase shadow-lg transition-all hover:scale-105 hover:bg-sky-700"
                                >
                                    <Save className="h-5 w-5" />
                                    {processing
                                        ? 'Guardando...'
                                        : 'Guardar Configuración'}
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-[2rem] border bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 className="mb-6 flex items-center gap-2 text-xs font-black tracking-widest text-slate-400 uppercase">
                                <HelpCircle className="h-4 w-4" /> Configuración
                                MercadoPago
                            </h3>
                            <div className="space-y-6 text-sm text-slate-600 dark:text-slate-300">
                                <div>
                                    <strong className="mb-1 block text-slate-900 dark:text-white">
                                        Paso 1: Mis Aplicaciones
                                    </strong>
                                    Crea una nueva aplicación en el panel de
                                    MercadoPago Developers.
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <div>
                                    <strong className="mb-1 block flex items-center gap-1 text-slate-900 dark:text-white">
                                        Paso 2: Obtener Credenciales
                                    </strong>
                                    Dentro de tu aplicación, busca "Credenciales
                                    de producción" o "Credenciales de prueba" y
                                    copia la Public Key y el Access Token.
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <a
                                    href="https://www.mercadopago.cl/developers/panel"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs font-bold text-slate-600 uppercase transition-colors hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/80"
                                >
                                    MercadoPago Developers{' '}
                                    <ExternalLink className="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function CheckCircle(props: any) {
    return (
        <svg
            {...props}
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
    );
}
