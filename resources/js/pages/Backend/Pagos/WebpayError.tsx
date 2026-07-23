import { Head, Link } from '@inertiajs/react';
import { AlertCircle, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

export default function WebpayError({ error }: { error?: string }) {
    usePermissions();
    return (
        <AppLayout>
            <Head title="Error de Pago - Webpay Plus" />

            <div className="mx-auto max-w-4xl px-4 py-16 flex flex-col items-center justify-center text-center">
                <div className="h-24 w-24 rounded-full bg-red-100 flex items-center justify-center mb-6 dark:bg-red-900/30">
                    <AlertCircle className="h-12 w-12 text-red-600 dark:text-red-500" />
                </div>
                
                <h1 className="text-3xl font-black tracking-tight text-slate-900 dark:text-white mb-4">
                    Pago Rechazado o Cancelado
                </h1>
                
                <p className="text-lg text-slate-600 dark:text-slate-400 mb-8 max-w-lg">
                    {error || 'Hubo un inconveniente al procesar tu pago con Webpay Plus. Es posible que hayas cancelado la transacción o el banco haya rechazado el cobro.'}
                </p>

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
