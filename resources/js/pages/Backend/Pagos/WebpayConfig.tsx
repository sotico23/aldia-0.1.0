import { Head, useForm } from '@inertiajs/react';
import {
    CreditCard,
    Save,
    Info,
    ExternalLink,
    ShieldCheck,
    HelpCircle,
} from 'lucide-react';
import { toast } from 'sonner';
import { FormInput } from '@/components/form-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

export default function WebpayConfig({ config }: { config: any }) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('admin.configuracion.viewAny');
    const { data, setData, post, processing, errors } = useForm({
        commerce_code: config?.commerce_code || '',
        api_key: '', // Siempre vacío por seguridad, si config.api_key existe mostramos mensaje
        environment: config?.environment || 'integration',
        is_active: config?.is_active ?? true,
    });

    const hasSavedApiKey = !!config?.api_key;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/webpay/config', {
            onSuccess: () => {
                toast.success(
                    'Configuración de Webpay guardada exitosamente y conexión probada.',
                );
            },
            onError: (err) => {
                const message =
                    err.api_key ||
                    err.commerce_code ||
                    'Por favor, revise los errores en el formulario.';
                toast.error(message);
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
                { title: 'Webpay Plus', href: '/webpay/config' },
            ]}
        >
            <Head title="Configuración de Webpay Plus" />

            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-8 flex items-start gap-6 rounded-[2rem] border border-indigo-100 bg-indigo-50 p-8 dark:border-indigo-900/30 dark:bg-indigo-950/20">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 dark:bg-indigo-900/50">
                        <CreditCard className="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h1 className="mb-2 text-2xl font-black tracking-tight text-indigo-900 dark:text-indigo-100">
                            Webpay Plus (Transbank)
                        </h1>
                        <p className="max-w-2xl text-sm font-medium text-indigo-700/80 dark:text-indigo-300/80">
                            Configura tu cuenta de Transbank para recibir pagos
                            directamente en tu tienda.
                            <strong>
                                {' '}
                                Los fondos de tus ventas ingresarán directo a tu
                                cuenta bancaria asociada.
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
                            <div className="border-b p-8 dark:border-slate-800">
                                <h2 className="flex items-center gap-2 text-lg font-black tracking-tight uppercase">
                                    <ShieldCheck className="h-5 w-5 text-indigo-500" />
                                    Credenciales de Integración
                                </h2>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Ingresa tus llaves proporcionadas por
                                    Transbank. Estas se cifrarán de forma
                                    segura.
                                </p>
                            </div>

                            <div className="flex flex-col space-y-6 p-8">
                                <div className="space-y-6 rounded-3xl border bg-slate-50 p-6 dark:border-slate-700 dark:bg-slate-800/50">
                                    <FormInput
                                        id="commerce_code"
                                        label="Código de Comercio"
                                        value={data.commerce_code}
                                        onChange={(e) =>
                                            setData(
                                                'commerce_code',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ej: 597055555532"
                                        required
                                        error={errors.commerce_code}
                                    />

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="api_key">
                                                API Key Secret
                                            </Label>
                                            {hasSavedApiKey && (
                                                <span className="flex items-center gap-1 text-[10px] font-black tracking-widest text-green-500 uppercase">
                                                    <CheckCircle className="h-3 w-3" />{' '}
                                                    Llave Configurada
                                                </span>
                                            )}
                                        </div>
                                        <FormInput
                                            id="api_key"
                                            type="password"
                                            value={data.api_key}
                                            onChange={(e) =>
                                                setData(
                                                    'api_key',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={
                                                hasSavedApiKey
                                                    ? '••••••••••••••••••••••••••••'
                                                    : 'Pega aquí tu API Key secreta'
                                            }
                                            required={!hasSavedApiKey}
                                            error={errors.api_key}
                                        />
                                        {hasSavedApiKey && (
                                            <p className="mt-1 text-xs text-slate-400">
                                                Solo ingresa un valor si deseas
                                                actualizar tu llave actual.
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center justify-between rounded-3xl border bg-slate-50 p-6 dark:border-slate-700 dark:bg-slate-800/50">
                                    <div className="space-y-1">
                                        <Label className="text-base font-bold">
                                            Ambiente de Operación
                                        </Label>
                                        <p className="flex items-center gap-1 text-sm text-slate-500">
                                            {data.environment ===
                                            'production' ? (
                                                <span className="font-medium text-green-600">
                                                    Estás en Producción. Pagos
                                                    Reales Activos.
                                                </span>
                                            ) : (
                                                <span className="font-medium text-amber-600">
                                                    Estás en Integración. Solo
                                                    simulaciones de pago.
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex items-center space-x-3">
                                        <Label className="text-xs font-black tracking-widest text-slate-400 uppercase">
                                            Pruebas
                                        </Label>
                                        <Switch
                                            checked={
                                                data.environment ===
                                                'production'
                                            }
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'environment',
                                                    checked
                                                        ? 'production'
                                                        : 'integration',
                                                )
                                            }
                                        />
                                        <Label className="text-xs font-black tracking-widest text-indigo-600 uppercase">
                                            Real
                                        </Label>
                                    </div>
                                </div>

                                {data.environment === 'production' && (
                                    <div className="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                                        <Info className="mt-0.5 h-5 w-5 shrink-0" />
                                        <div className="text-sm">
                                            <strong className="mb-1 block">
                                                Pase a Producción Requerido
                                            </strong>
                                            Recuerda que para operar en ambiente
                                            real debes haber completado
                                            satisfactoriamente el proceso de
                                            validación técnica requerido por
                                            Transbank y subir las evidencias en
                                            su portal.
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="flex justify-end border-t bg-slate-50/50 p-8 dark:border-slate-800 dark:bg-slate-900/50">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-14 gap-2 rounded-2xl px-8 font-black tracking-[0.1em] uppercase shadow-lg transition-all hover:scale-105"
                                >
                                    <Save className="h-5 w-5" />
                                    {processing
                                        ? 'Probando...'
                                        : 'Guardar y Probar Conexión'}
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-[2rem] border bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 className="mb-6 flex items-center gap-2 text-xs font-black tracking-widest text-slate-400 uppercase">
                                <HelpCircle className="h-4 w-4" /> Ayuda Rápida
                            </h3>
                            <div className="space-y-6 text-sm text-slate-600 dark:text-slate-300">
                                <div>
                                    <strong className="mb-1 block text-slate-900 dark:text-white">
                                        ¿Dónde encuentro mis credenciales?
                                    </strong>
                                    Ingresa al Portal de Transbank Developers o
                                    al portal privado (si es producción) en la
                                    sección de Integraciones.
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <div>
                                    <strong className="mb-1 block flex items-center gap-1 text-slate-900 dark:text-white">
                                        1. Código de Comercio
                                    </strong>
                                    Es un número único que identifica tu tienda.
                                    Usualmente inicia con '5970'.
                                </div>
                                <div>
                                    <strong className="mb-1 block flex items-center gap-1 text-slate-900 dark:text-white">
                                        2. API Key Secret
                                    </strong>
                                    Una cadena larga de caracteres.{' '}
                                    <b>No la compartas con nadie</b>. Si la
                                    pierdes, deberás regenerarla en su portal.
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <a
                                    href="https://transbankdevelopers.cl"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs font-bold text-slate-600 uppercase transition-colors hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/80"
                                >
                                    Ir al Portal Púbico de Transbank{' '}
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
