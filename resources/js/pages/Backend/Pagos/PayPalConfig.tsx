import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { CreditCard, Save, ExternalLink, ShieldCheck, HelpCircle } from 'lucide-react';
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
                paypal_mode: data.paypal_mode
            });

            if (response.data.success) {
                toast.success(response.data.message);
            } else {
                toast.error(response.data.message);
            }
        } catch (error: any) {
            const msg = error.response?.data?.message || 'Error al conectar con PayPal.';
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
        <AppLayout breadcrumbs={[{ title: 'Pagos en Línea', href: '/webpay/config' }, { title: 'PayPal', href: '/paypal/config' }]}>
            <Head title="Configuración de PayPal" />

            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-8 p-8 rounded-[2rem] bg-blue-50 border border-blue-100 flex items-start gap-6 dark:bg-blue-950/20 dark:border-blue-900/30">
                    <div className="h-14 w-14 rounded-2xl bg-blue-100 flex items-center justify-center shrink-0 dark:bg-blue-900/50">
                        <CreditCard className="h-7 w-7 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-blue-900 dark:text-blue-100 mb-2">PayPal Checkout</h1>
                        <p className="text-sm font-medium text-blue-700/80 dark:text-blue-300/80 max-w-2xl">
                            Acepta pagos de todo el mundo mediante PayPal. 
                            <strong> Ideal para ventas internacionales y clientes que prefieren seguridad global.</strong>
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div className="lg:col-span-2">
                        <form onSubmit={handleSubmit} className="bg-white rounded-[2rem] border shadow-sm overflow-hidden dark:bg-slate-900 dark:border-slate-800">
                            <div className="p-8 border-b dark:border-slate-800 flex justify-between items-center">
                                <div>
                                    <h2 className="text-lg font-black uppercase tracking-tight flex items-center gap-2">
                                        <ShieldCheck className="h-5 w-5 text-blue-500" />
                                        Credenciales de PayPal
                                    </h2>
                                    <p className="text-sm text-slate-500 mt-1 dark:text-slate-400">Obtén tu Client ID y Secret desde el portal de PayPal Developer.</p>
                                </div>
                                <div className="flex items-center gap-3 bg-slate-100 px-4 py-2 rounded-2xl dark:bg-slate-800">
                                    <Label htmlFor="paypal_active" className="text-xs font-bold uppercase tracking-tight">Activar PayPal</Label>
                                    <Switch 
                                        id="paypal_active"
                                        checked={data.paypal_active}
                                        onCheckedChange={(checked) => setData('paypal_active', checked)}
                                    />
                                </div>
                            </div>

                            <div className="p-8 space-y-6 flex flex-col">
                                <div className="space-y-6 bg-slate-50 p-6 rounded-3xl border dark:bg-slate-800/50 dark:border-slate-700">
                                    <FormInput 
                                        id="paypal_client_id"
                                        label="PayPal Client ID"
                                        value={data.paypal_client_id}
                                        onChange={e => setData('paypal_client_id', e.target.value)}
                                        placeholder="Ej: AW9... (Cadena larga)"
                                        required
                                        error={errors.paypal_client_id}
                                    />
                                    
                                    <div className="space-y-2">
                                        <div className="flex justify-between items-center">
                                            <Label htmlFor="paypal_client_secret">PayPal Secret Key</Label>
                                            {hasSavedSecret && (
                                                <span className="text-[10px] font-black uppercase tracking-widest text-green-500 flex items-center gap-1">
                                                    <CheckCircle className="h-3 w-3" /> Llave Configurada
                                                </span>
                                            )}
                                        </div>
                                        <FormInput 
                                            id="paypal_client_secret"
                                            type="password"
                                            value={data.paypal_client_secret}
                                            onChange={e => setData('paypal_client_secret', e.target.value)}
                                            placeholder={hasSavedSecret ? '••••••••••••••••••••••••••••' : 'Pega aquí tu Secret Key'}
                                            required={!hasSavedSecret}
                                            error={errors.paypal_client_secret}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between bg-slate-50 p-6 rounded-3xl border dark:bg-slate-800/50 dark:border-slate-700">
                                    <div className="space-y-1">
                                        <Label className="text-base font-bold">Modo de Operación</Label>
                                        <p className="text-sm text-slate-500">
                                            {data.paypal_mode === 'live' ? (
                                                <span className="text-green-600 font-medium">Estás en producción (Live).</span>
                                            ) : (
                                                <span className="text-amber-600 font-medium">Estás en pruebas (Sandbox).</span>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex items-center space-x-3">
                                        <Label className="text-xs uppercase tracking-widest font-black text-slate-400">Sandbox</Label>
                                        <Switch 
                                            checked={data.paypal_mode === 'live'} 
                                            onCheckedChange={(checked) => setData('paypal_mode', checked ? 'live' : 'sandbox')}
                                        />
                                        <Label className="text-xs uppercase tracking-widest font-black text-blue-600">Live</Label>
                                    </div>
                                </div>
                            </div>

                            <div className="p-8 border-t bg-slate-50/50 flex justify-between items-center dark:border-slate-800 dark:bg-slate-900/50">
                                <Button 
                                    type="button" 
                                    variant="outline"
                                    onClick={handleTest}
                                    disabled={testing || processing} 
                                    className="h-14 px-8 rounded-2xl font-black uppercase tracking-[0.1em] gap-2 border-2 hover:bg-white transition-all"
                                >
                                    {testing ? (
                                        <div className="h-5 w-5 border-2 border-slate-300 border-t-blue-600 rounded-full animate-spin" />
                                    ) : (
                                        <ShieldCheck className="h-5 w-5 text-blue-600" />
                                    )}
                                    {testing ? 'Probando...' : 'Probar Conexión'}
                                </Button>

                                <Button type="submit" disabled={processing || testing} className="h-14 px-8 rounded-2xl font-black uppercase tracking-[0.1em] gap-2 shadow-lg hover:scale-105 transition-all bg-blue-600 hover:bg-blue-700 text-white">
                                    <Save className="h-5 w-5" />
                                    {processing ? 'Guardando...' : 'Guardar Configuración'}
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div className="space-y-6">
                        <div className="bg-white rounded-[2rem] border p-8 shadow-sm dark:bg-slate-900 dark:border-slate-800">
                            <h3 className="font-black text-xs uppercase tracking-widest text-slate-400 flex items-center gap-2 mb-6">
                                <HelpCircle className="h-4 w-4" /> Centro de Desarrolladores
                            </h3>
                            <div className="space-y-6 text-sm text-slate-600 dark:text-slate-300">
                                <div>
                                    <strong className="block text-slate-900 dark:text-white mb-1">Paso 1: Crear App</strong>
                                    Ve a "Apps & Credentials" y crea una nueva App de tipo "REST API".
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <div>
                                    <strong className="block text-slate-900 dark:text-white mb-1 flex items-center gap-1">
                                        Paso 2: Copiar llaves
                                    </strong>
                                    Copia el "Client ID" y el "Secret". Asegúrate de estar en la pestaña correcta (Sandbox para pruebas o Live para real).
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <a href="https://developer.paypal.com/dashboard/" target="_blank" rel="noreferrer" className="flex items-center justify-center gap-2 w-full p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors font-bold text-xs uppercase dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700/80">
                                    PayPal Developer Portal <ExternalLink className="h-4 w-4" />
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
