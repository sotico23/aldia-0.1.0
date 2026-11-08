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

export default function PayPalConfig({ config }: { config: any }) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('admin.configuracion.viewAny');
    const { data, setData, post, processing, errors } = useForm({
        paypal_client_id: config?.paypal_client_id || '',
        paypal_client_secret: '', // Siempre vacío por seguridad
        paypal_mode: config?.paypal_mode || 'sandbox',
        paypal_active: config?.paypal_active ?? false,
    });

    const [testing, setTesting] = useState(false);

    const handleTest = async () => {
        if (!data.paypal_client_id) {
            toast.error('El Client ID es requerido para la prueba.');
            return;
        }

        setTesting(true);
        try {
            const response = await axios.post('/paypal/test', {
                paypal_client_id: data.paypal_client_id,
                paypal_client_secret: data.paypal_client_secret,
                paypal_mode: data.paypal_mode,
            });

            if (response.data.success) {
                toast.success(response.data.message);
            } else {
                toast.error(response.data.message);
            }
        } catch (error: any) {
            const msg =
                error.response?.data?.message ||
                'Error al conectar con PayPal.';
            toast.error(msg);
        } finally {
            setTesting(false);
        }
    };

    const hasSavedSecret = !!config?.paypal_client_secret;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/paypal/config', {
            onSuccess: () => {
                toast.success('Configuración de PayPal guardada exitosamente.');
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
                { title: 'PayPal', href: '/paypal/config' },
            ]}
        >
            <Head title="Configuración de PayPal" />

            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-8 flex items-start gap-6 rounded-[2rem] border border-emerald-100 bg-emerald-50 p-8 dark:border-emerald-900/30 dark:bg-emerald-950/20">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/50">
                        <CreditCard className="h-7 w-7 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <h1 className="mb-2 text-2xl font-black tracking-tight text-emerald-900 dark:text-emerald-100">
                            PayPal Checkout
                        </h1>
                        <p className="max-w-2xl text-sm font-medium text-emerald-700/80 dark:text-emerald-300/80">
                            Acepta pagos de todo el mundo mediante PayPal.
                            <strong>
                                {' '}
                                Ideal para ventas internacionales y clientes que
                                prefieren seguridad global.
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
                                        <ShieldCheck className="h-5 w-5 text-emerald-500" />
                                        Credenciales de PayPal
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        Obtén tu Client ID y Secret desde el
                                        portal de PayPal Developer.
                                    </p>
                                </div>
                                <div className="flex items-center gap-3 rounded-2xl bg-slate-100 px-4 py-2 dark:bg-slate-800">
                                    <Label
                                        htmlFor="paypal_active"
                                        className="text-xs font-bold tracking-tight uppercase"
                                    >
                                        Activar PayPal
                                    </Label>
                                    <Switch
                                        id="paypal_active"
                                        checked={data.paypal_active}
                                        onCheckedChange={(checked) =>
                                            setData('paypal_active', checked)
                                        }
                                    />
                                </div>
                            </div>

                            <div className="flex flex-col space-y-6 p-8">
                                <div className="space-y-6 rounded-3xl border bg-slate-50 p-6 dark:border-slate-700 dark:bg-slate-800/50">
                                    <FormInput
                                        id="paypal_client_id"
                                        label="PayPal Client ID"
                                        value={data.paypal_client_id}
                                        onChange={(e) =>
                                            setData(
                                                'paypal_client_id',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ej: AW9... (Cadena larga)"
                                        required
                                        error={errors.paypal_client_id}
                                    />

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="paypal_client_secret">
                                                PayPal Secret Key
                                            </Label>
                                            {hasSavedSecret && (
                                                <span className="flex items-center gap-1 text-[10px] font-black tracking-widest text-green-500 uppercase">
                                                    <CheckCircle className="h-3 w-3" />{' '}
                                                    Llave Configurada
                                                </span>
                                            )}
                                        </div>
                                        <FormInput
                                            id="paypal_client_secret"
                                            type="password"
                                            value={data.paypal_client_secret}
                                            onChange={(e) =>
                                                setData(
                                                    'paypal_client_secret',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={
                                                hasSavedSecret
                                                    ? '••••••••••••••••••••••••••••'
                                                    : 'Pega aquí tu Secret Key'
                                            }
                                            required={!hasSavedSecret}
                                            error={errors.paypal_client_secret}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between rounded-3xl border bg-slate-50 p-6 dark:border-slate-700 dark:bg-slate-800/50">
                                    <div className="space-y-1">
                                        <Label className="text-base font-bold">
                                            Modo de Operación
                                        </Label>
                                        <p className="text-sm text-slate-500">
                                            {data.paypal_mode === 'live' ? (
                                                <span className="font-medium text-green-600">
                                                    Estás en producción (Live).
                                                </span>
                                            ) : (
                                                <span className="font-medium text-amber-600">
                                                    Estás en pruebas (Sandbox).
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
                                                data.paypal_mode === 'live'
                                            }
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'paypal_mode',
                                                    checked
                                                        ? 'live'
                                                        : 'sandbox',
                                                )
                                            }
                                        />
                                        <Label className="text-xs font-black tracking-widest text-emerald-600 uppercase">
                                            Live
                                        </Label>
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center justify-between border-t bg-slate-50/50 p-8 dark:border-slate-800 dark:bg-slate-900/50">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleTest}
                                    disabled={testing || processing}
                                    className="h-14 gap-2 rounded-2xl border-2 px-8 font-black tracking-[0.1em] uppercase transition-all hover:bg-white"
                                >
                                    {testing ? (
                                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-600" />
                                    ) : (
                                        <ShieldCheck className="h-5 w-5 text-emerald-600" />
                                    )}
                                    {testing
                                        ? 'Probando...'
                                        : 'Probar Conexión'}
                                </Button>

                                <Button
                                    type="submit"
                                    disabled={processing || testing}
                                    className="h-14 gap-2 rounded-2xl bg-emerald-600 px-8 font-black tracking-[0.1em] text-white uppercase shadow-lg transition-all hover:scale-105 hover:bg-emerald-700"
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
                                <HelpCircle className="h-4 w-4" /> Centro de
                                Desarrolladores
                            </h3>
                            <div className="space-y-6 text-sm text-slate-600 dark:text-slate-300">
                                <div>
                                    <strong className="mb-1 block text-slate-900 dark:text-white">
                                        Paso 1: Crear App
                                    </strong>
                                    Ve a "Apps & Credentials" y crea una nueva
                                    App de tipo "REST API".
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <div>
                                    <strong className="mb-1 block flex items-center gap-1 text-slate-900 dark:text-white">
                                        Paso 2: Copiar llaves
                                    </strong>
                                    Copia el "Client ID" y el "Secret".
                                    Asegúrate de estar en la pestaña correcta
                                    (Sandbox para pruebas o Live para real).
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <a
                                    href="https://developer.paypal.com/dashboard/"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs font-bold text-slate-600 uppercase transition-colors hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/80"
                                >
                                    PayPal Developer Portal{' '}
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
