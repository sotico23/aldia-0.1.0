import { Head, useForm } from '@inertiajs/react';
import { CreditCard, Save, Info, ExternalLink, ShieldCheck, HelpCircle } from 'lucide-react';
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
                toast.success('Configuración de Webpay guardada exitosamente y conexión probada.');
            },
            onError: (err) => {
                const message = err.api_key || err.commerce_code || 'Por favor, revise los errores en el formulario.';
                toast.error(message);
            }
        });
    };

    if (!canAccess) {
        return (
            <AppLayout breadcrumbs={[{ title: 'Pagos en Línea', href: '/webpay/config' }]}>
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={[{ title: 'Pagos en Línea', href: '/webpay/config' }, { title: 'Webpay Plus', href: '/webpay/config' }]}>
            <Head title="Configuración de Webpay Plus" />

            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-8 p-8 rounded-[2rem] bg-indigo-50 border border-indigo-100 flex items-start gap-6 dark:bg-indigo-950/20 dark:border-indigo-900/30">
                    <div className="h-14 w-14 rounded-2xl bg-indigo-100 flex items-center justify-center shrink-0 dark:bg-indigo-900/50">
                        <CreditCard className="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-indigo-900 dark:text-indigo-100 mb-2">Webpay Plus (Transbank)</h1>
                        <p className="text-sm font-medium text-indigo-700/80 dark:text-indigo-300/80 max-w-2xl">
                            Configura tu cuenta de Transbank para recibir pagos directamente en tu tienda. 
                            <strong> Los fondos de tus ventas ingresarán directo a tu cuenta bancaria asociada.</strong>
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div className="lg:col-span-2">
                        <form onSubmit={handleSubmit} className="bg-white rounded-[2rem] border shadow-sm overflow-hidden dark:bg-slate-900 dark:border-slate-800">
                            <div className="p-8 border-b dark:border-slate-800">
                                <h2 className="text-lg font-black uppercase tracking-tight flex items-center gap-2">
                                    <ShieldCheck className="h-5 w-5 text-indigo-500" />
                                    Credenciales de Integración
                                </h2>
                                <p className="text-sm text-slate-500 mt-1 dark:text-slate-400">Ingresa tus llaves proporcionadas por Transbank. Estas se cifrarán de forma segura.</p>
                            </div>

                            <div className="p-8 space-y-6 flex flex-col">
                                <div className="space-y-6 bg-slate-50 p-6 rounded-3xl border dark:bg-slate-800/50 dark:border-slate-700">
                                    <FormInput 
                                        id="commerce_code"
                                        label="Código de Comercio"
                                        value={data.commerce_code}
                                        onChange={e => setData('commerce_code', e.target.value)}
                                        placeholder="Ej: 597055555532"
                                        required
                                        error={errors.commerce_code}
                                    />
                                    
                                    <div className="space-y-2">
                                        <div className="flex justify-between items-center">
                                            <Label htmlFor="api_key">API Key Secret</Label>
                                            {hasSavedApiKey && (
                                                <span className="text-[10px] font-black uppercase tracking-widest text-green-500 flex items-center gap-1">
                                                    <CheckCircle className="h-3 w-3" /> Llave Configurada
                                                </span>
                                            )}
                                        </div>
                                        <FormInput 
                                            id="api_key"
                                            type="password"
                                            value={data.api_key}
                                            onChange={e => setData('api_key', e.target.value)}
                                            placeholder={hasSavedApiKey ? '••••••••••••••••••••••••••••' : 'Pega aquí tu API Key secreta'}
                                            required={!hasSavedApiKey}
                                            error={errors.api_key}
                                        />
                                        {hasSavedApiKey && (
                                            <p className="text-xs text-slate-400 mt-1">Solo ingresa un valor si deseas actualizar tu llave actual.</p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center justify-between bg-slate-50 p-6 rounded-3xl border dark:bg-slate-800/50 dark:border-slate-700">
                                    <div className="space-y-1">
                                        <Label className="text-base font-bold">Ambiente de Operación</Label>
                                        <p className="text-sm text-slate-500 flex items-center gap-1">
                                            {data.environment === 'production' ? (
                                                <span className="text-green-600 font-medium">Estás en Producción. Pagos Reales Activos.</span>
                                            ) : (
                                                <span className="text-amber-600 font-medium">Estás en Integración. Solo simulaciones de pago.</span>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex items-center space-x-3">
                                        <Label className="text-xs uppercase tracking-widest font-black text-slate-400">Pruebas</Label>
                                        <Switch 
                                            checked={data.environment === 'production'} 
                                            onCheckedChange={(checked) => setData('environment', checked ? 'production' : 'integration')}
                                        />
                                        <Label className="text-xs uppercase tracking-widest font-black text-indigo-600">Real</Label>
                                    </div>
                                </div>
                                
                                {data.environment === 'production' && (
                                    <div className="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-2xl flex gap-3 dark:bg-amber-950/30 dark:border-amber-900/50 dark:text-amber-300">
                                        <Info className="h-5 w-5 shrink-0 mt-0.5" />
                                        <div className="text-sm">
                                            <strong className="block mb-1">Pase a Producción Requerido</strong>
                                            Recuerda que para operar en ambiente real debes haber completado satisfactoriamente el proceso de validación técnica requerido por Transbank y subir las evidencias en su portal.
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="p-8 border-t bg-slate-50/50 flex justify-end dark:border-slate-800 dark:bg-slate-900/50">
                                <Button type="submit" disabled={processing} className="h-14 px-8 rounded-2xl font-black uppercase tracking-[0.1em] gap-2 shadow-lg hover:scale-105 transition-all">
                                    <Save className="h-5 w-5" />
                                    {processing ? 'Probando...' : 'Guardar y Probar Conexión'}
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div className="space-y-6">
                        <div className="bg-white rounded-[2rem] border p-8 shadow-sm dark:bg-slate-900 dark:border-slate-800">
                            <h3 className="font-black text-xs uppercase tracking-widest text-slate-400 flex items-center gap-2 mb-6">
                                <HelpCircle className="h-4 w-4" /> Ayuda Rápida
                            </h3>
                            <div className="space-y-6 text-sm text-slate-600 dark:text-slate-300">
                                <div>
                                    <strong className="block text-slate-900 dark:text-white mb-1">¿Dónde encuentro mis credenciales?</strong>
                                    Ingresa al Portal de Transbank Developers o al portal privado (si es producción) en la sección de Integraciones.
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <div>
                                    <strong className="block text-slate-900 dark:text-white mb-1 flex items-center gap-1">
                                        1. Código de Comercio
                                    </strong>
                                    Es un número único que identifica tu tienda. Usualmente inicia con '5970'.
                                </div>
                                <div>
                                    <strong className="block text-slate-900 dark:text-white mb-1 flex items-center gap-1">
                                        2. API Key Secret
                                    </strong>
                                    Una cadena larga de caracteres. <b>No la compartas con nadie</b>. Si la pierdes, deberás regenerarla en su portal.
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <a href="https://transbankdevelopers.cl" target="_blank" rel="noreferrer" className="flex items-center justify-center gap-2 w-full p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors font-bold text-xs uppercase dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700/80">
                                    Ir al Portal Púbico de Transbank <ExternalLink className="h-4 w-4" />
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
