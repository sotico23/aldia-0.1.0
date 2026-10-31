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

            <div className="mx-auto flex max-w-4xl flex-col items-center justify-center px-4 py-16 text-center">
                <div className="mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <AlertCircle className="h-12 w-12 text-red-600 dark:text-red-500" />
                </div>

                <h1 className="mb-4 text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                    Pago Rechazado o Cancelado
                </h1>

                <p className="mb-8 max-w-lg text-lg text-slate-600 dark:text-slate-400">
                    {error ||
                        'Hubo un inconveniente al procesar tu pago con Webpay Plus. Es posible que hayas cancelado la transacción o el banco haya rechazado el cobro.'}
                </p>

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
