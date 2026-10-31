import { Head, Link } from '@inertiajs/react';
import { CheckCircle, AlertCircle, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

export default function WebpayResult({
    success,
    error,
    details,
}: {
    success: boolean;
    error?: string;
    details?: any;
}) {
    usePermissions();
    return (
        <AppLayout breadcrumbs={[{ title: 'Resultado del Pago', href: '#' }]}>
            <Head title="Resultado Webpay" />

            <div className="mx-auto flex max-w-4xl flex-col items-center justify-center px-4 py-16 text-center">
                {success ? (
                    <>
                        <div className="mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                            <CheckCircle className="h-12 w-12 text-green-600 dark:text-green-500" />
                        </div>
                        <h1 className="mb-4 text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                            ¡Pago Exitoso!
                        </h1>
                        <p className="mb-8 max-w-lg text-lg text-slate-600 dark:text-slate-400">
                            Tu transacción en Webpay Plus ha sido aprobada
                            correctamente y confirmada en nuestros servidores.
                        </p>
                        {details && (
                            <div className="mb-8 w-full max-w-md space-y-3 rounded-2xl border bg-slate-50 p-6 text-left text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800/50">
                                <div className="flex justify-between border-b pb-2 dark:border-slate-700">
                                    <span className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">
                                        Orden de Compra:
                                    </span>
                                    <span className="font-black text-slate-900 dark:text-white">
                                        {details.buy_order}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b pb-2 dark:border-slate-700">
                                    <span className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">
                                        Monto Pagado:
                                    </span>
                                    <span className="font-black text-primary">
                                        $
                                        {Number(
                                            details.amount,
                                        ).toLocaleString()}
                                    </span>
                                </div>
                                <div className="flex justify-between pb-2">
                                    <span className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">
                                        Cód. Autorización:
                                    </span>
                                    <span className="font-black text-slate-900 dark:text-white">
                                        {details.auth_code}
                                    </span>
                                </div>
                            </div>
                        )}
                    </>
                ) : (
                    <>
                        <div className="mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                            <AlertCircle className="h-12 w-12 text-red-600 dark:text-red-500" />
                        </div>
                        <h1 className="mb-4 text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                            Pago Fallido o Rechazado
                        </h1>
                        <p className="mb-8 max-w-lg text-lg text-slate-600 dark:text-slate-400">
                            {error ||
                                'Hubo un error con tu pago y no se procesó ningún cobro.'}
                        </p>
                    </>
                )}

                <Button
                    asChild
                    className="h-14 gap-2 rounded-2xl px-8 font-black tracking-[0.1em] uppercase"
                >
                    <Link href="/tienda">
                        <ArrowLeft className="h-5 w-5" />
                        Volver a la Tienda
                    </Link>
                </Button>
            </div>
        </AppLayout>
    );
}
