import { Head, Link } from '@inertiajs/react';
import { CheckCircle, AlertCircle, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

export default function WebpayResult({ success, error, details }: { success: boolean, error?: string, details?: any }) {
    usePermissions();
    return (
        <AppLayout breadcrumbs={[{ title: 'Resultado del Pago', href: '#' }]}>
            <Head title="Resultado Webpay" />

            <div className="mx-auto max-w-4xl px-4 py-16 flex flex-col items-center justify-center text-center">
                {success ? (
                    <>
                        <div className="h-24 w-24 rounded-full bg-green-100 flex items-center justify-center mb-6 dark:bg-green-900/30">
                            <CheckCircle className="h-12 w-12 text-green-600 dark:text-green-500" />
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-slate-900 dark:text-white mb-4">
                            ¡Pago Exitoso!
                        </h1>
                        <p className="text-lg text-slate-600 dark:text-slate-400 mb-8 max-w-lg">
                            Tu transacción en Webpay Plus ha sido aprobada correctamente y confirmada en nuestros servidores.
                        </p>
                        {details && (
                            <div className="bg-slate-50 border p-6 rounded-2xl w-full max-w-md text-sm text-left shadow-sm mb-8 space-y-3 dark:bg-slate-800/50 dark:border-slate-700">
                                <div className="flex justify-between border-b pb-2 dark:border-slate-700">
                                    <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">Orden de Compra:</span>
                                    <span className="font-black text-slate-900 dark:text-white">{details.buy_order}</span>
                                </div>
                                <div className="flex justify-between border-b pb-2 dark:border-slate-700">
                                    <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">Monto Pagado:</span>
                                    <span className="font-black text-primary">${Number(details.amount).toLocaleString()}</span>
                                </div>
                                <div className="flex justify-between pb-2">
                                    <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">Cód. Autorización:</span>
                                    <span className="font-black text-slate-900 dark:text-white">{details.auth_code}</span>
                                </div>
                            </div>
                        )}
                    </>
                ) : (
                    <>
                         <div className="h-24 w-24 rounded-full bg-red-100 flex items-center justify-center mb-6 dark:bg-red-900/30">
                            <AlertCircle className="h-12 w-12 text-red-600 dark:text-red-500" />
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-slate-900 dark:text-white mb-4">
                            Pago Fallido o Rechazado
                        </h1>
                        <p className="text-lg text-slate-600 dark:text-slate-400 mb-8 max-w-lg">
                            {error || 'Hubo un error con tu pago y no se procesó ningún cobro.'}
                        </p>
                    </>
                )}

                <Button asChild className="h-14 px-8 rounded-2xl font-black uppercase tracking-[0.1em] gap-2">
                    <Link href="/tienda">
                        <ArrowLeft className="h-5 w-5" />
                        Volver a la Tienda
                    </Link>
                </Button>
            </div>
        </AppLayout>
    );
}
