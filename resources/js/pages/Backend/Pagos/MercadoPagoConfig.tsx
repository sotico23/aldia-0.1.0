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
                mercadopago_mode: data.mercadopago_mode
            });

            if (response.data.success) {
                toast.success(response.data.message);
            } else {
                toast.error(response.data.message);
            }
        } catch (error: any) {
            const msg = error.response?.data?.message || 'Error al conectar con MercadoPago.';
            toast.error(msg);
        } finally {
            setTesting(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/mercadopago/config', {
            onSuccess: () => {
                toast.success('Configuración de MercadoPago guardada exitosamente.');
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
        <AppLayout breadcrumbs={[{ title: 'Pagos en Línea', href: '/webpay/config' }, { title: 'MercadoPago', href: '/mercadopago/config' }]}>
            <Head title="Configuración de MercadoPago" />

            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-8 p-8 rounded-[2rem] bg-sky-50 border border-sky-100 flex items-start gap-6 dark:bg-sky-950/20 dark:border-sky-900/30">
                    <div className="h-14 w-14 rounded-2xl bg-sky-100 flex items-center justify-center shrink-0 dark:bg-sky-900/50">
                        <CreditCard className="h-7 w-7 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-sky-900 dark:text-sky-100 mb-2">MercadoPago</h1>
                        <p className="text-sm font-medium text-sky-700/80 dark:text-sky-300/80 max-w-2xl">
                            La pasarela de pagos líder en Latinoamérica. 
                            <strong> Acepta tarjetas de crédito, débito y dinero en cuenta de MercadoPago.</strong>
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div className="lg:col-span-2">
                        <form onSubmit={handleSubmit} className="bg-white rounded-[2rem] border shadow-sm overflow-hidden dark:bg-slate-900 dark:border-slate-800">
                            <div className="p-8 border-b dark:border-slate-800 flex justify-between items-center">
                                <div>
                                    <h2 className="text-lg font-black uppercase tracking-tight flex items-center gap-2">
                                        <ShieldCheck className="h-5 w-5 text-sky-500" />
                                        Credenciales de MercadoPago
                                    </h2>
                                    <p className="text-sm text-slate-500 mt-1 dark:text-slate-400">Configura tus llaves de aplicación desde el panel de vendedores de MercadoPago.</p>
                                </div>
                                <div className="flex items-center gap-3 bg-slate-100 px-4 py-2 rounded-2xl dark:bg-slate-800">
                                    <Label htmlFor="mp_active" className="text-xs font-bold uppercase tracking-tight">Activar MP</Label>
                                    <Switch 
                                        id="mp_active"
                                        checked={data.mercadopago_active}
                                        onCheckedChange={(checked) => setData('mercadopago_active', checked)}
                                    />
                                </div>
                            </div>

                            <div className="p-8 space-y-6 flex flex-col">
                                <div className="space-y-6 bg-slate-50 p-6 rounded-3xl border dark:bg-slate-800/50 dark:border-slate-700">
                                    <FormInput 
                                        id="mp_public_key"
                                        label="Public Key (Clave Pública)"
                                        value={data.mercadopago_public_key}
                                        onChange={e => setData('mercadopago_public_key', e.target.value)}
                                        placeholder="Ej: APP_USR-..."
                                        required
                                        error={errors.mercadopago_public_key}
                                    />
                                    
                                    <div className="space-y-2">
                                        <div className="flex justify-between items-center">
                                            <Label htmlFor="mp_access_token">Access Token (Token de Acceso)</Label>
                                            {hasSavedToken && (
                                                <span className="text-[10px] font-black uppercase tracking-widest text-green-500 flex items-center gap-1">
                                                    <CheckCircle className="h-3 w-3" /> Token Guardado
                                                </span>
                                            )}
                                        </div>
                                        <FormInput 
                                            id="mp_access_token"
                                            type="password"
                                            value={data.mercadopago_access_token}
                                            onChange={e => setData('mercadopago_access_token', e.target.value)}
                                            placeholder={hasSavedToken ? '••••••••••••••••••••••••••••' : 'Pega aquí tu Access Token'}
                                            required={!hasSavedToken}
                                            error={errors.mercadopago_access_token}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between bg-slate-50 p-6 rounded-3xl border dark:bg-slate-800/50 dark:border-slate-700">
                                    <div className="space-y-1">
                                        <Label className="text-base font-bold">Modo de Operación</Label>
                                        <p className="text-sm text-slate-500">
                                            {data.mercadopago_mode === 'live' ? (
                                                <span className="text-green-600 font-medium">Estás en producción (Ventas Reales).</span>
                                            ) : (
                                                <span className="text-amber-600 font-medium">Estás en Sandbox (Modo Prueba).</span>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex items-center space-x-3">
                                        <Label className="text-xs uppercase tracking-widest font-black text-slate-400">Sandbox</Label>
                                        <Switch 
                                            checked={data.mercadopago_mode === 'live'} 
                                            onCheckedChange={(checked) => setData('mercadopago_mode', checked ? 'live' : 'sandbox')}
                                        />
                                        <Label className="text-xs uppercase tracking-widest font-black text-sky-600">Ventas Reales</Label>
                                    </div>
                                </div>
                            </div>

                            <div className="p-8 border-t bg-slate-50/50 flex justify-between dark:border-slate-800 dark:bg-slate-900/50">
                                <Button 
                                    type="button" 
                                    variant="outline"
                                    disabled={testing} 
                                    className="h-14 px-8 rounded-2xl font-black uppercase tracking-wider text-slate-500 hover:text-slate-900 border-2 dark:border-slate-700"
                                    onClick={handleTest}
                                >
                                    {testing ? 'Probando...' : 'Probar Conexión'}
                                </Button>

                                <Button type="submit" disabled={processing} className="h-14 px-8 rounded-2xl font-black uppercase tracking-[0.1em] gap-2 shadow-lg hover:scale-105 transition-all bg-sky-600 hover:bg-sky-700">
                                    <Save className="h-5 w-5" />
                                    {processing ? 'Guardando...' : 'Guardar Configuración'}
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div className="space-y-6">
                        <div className="bg-white rounded-[2rem] border p-8 shadow-sm dark:bg-slate-900 dark:border-slate-800">
                            <h3 className="font-black text-xs uppercase tracking-widest text-slate-400 flex items-center gap-2 mb-6">
                                <HelpCircle className="h-4 w-4" /> Configuración MercadoPago
                            </h3>
                            <div className="space-y-6 text-sm text-slate-600 dark:text-slate-300">
                                <div>
                                    <strong className="block text-slate-900 dark:text-white mb-1">Paso 1: Mis Aplicaciones</strong>
                                    Crea una nueva aplicación en el panel de MercadoPago Developers.
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <div>
                                    <strong className="block text-slate-900 dark:text-white mb-1 flex items-center gap-1">
                                        Paso 2: Obtener Credenciales
                                    </strong>
                                    Dentro de tu aplicación, busca "Credenciales de producción" o "Credenciales de prueba" y copia la Public Key y el Access Token.
                                </div>
                                <hr className="border-slate-100 dark:border-slate-800" />
                                <a href="https://www.mercadopago.cl/developers/panel" target="_blank" rel="noreferrer" className="flex items-center justify-center gap-2 w-full p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors font-bold text-xs uppercase dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700/80">
                                    MercadoPago Developers <ExternalLink className="h-4 w-4" />
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
