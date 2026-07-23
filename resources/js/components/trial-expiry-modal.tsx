import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Clock, ExternalLink, Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { index as planesIndex } from '@/routes/planes';

export function TrialExpiryModal() {
    const { auth } = usePage().props as any;
    const user = auth?.user;
    const [open, setOpen] = React.useState(false);

    React.useEffect(() => {
        if (!user?.is_trial_expired) return;

        const storageKey = `trial_expiry_modal_shown_${user.id}`;
        const lastShown = localStorage.getItem(storageKey);
        const today = new Date().toDateString();

        if (lastShown !== today) {
            setOpen(true);
            localStorage.setItem(storageKey, today);
        }
    }, [user?.is_trial_expired, user?.id]);

    if (!user?.is_trial_expired) return null;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                        <Clock className="h-6 w-6 text-red-600" />
                    </div>
                    <DialogTitle className="text-center text-lg">
                        Tu período de prueba ha finalizado
                    </DialogTitle>
                    <DialogDescription className="text-center">
                        Puedes seguir viendo tus datos, pero no podrás crear, editar ni eliminar registros.
                        Elige un plan para continuar usando todas las funciones.
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    <div className="flex items-start gap-2">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            <strong>Acceso de solo lectura:</strong> Puedes consultar reportes, ventas, clientes y demás información, pero las acciones de escritura están bloqueadas hasta que actualices tu plan.
                        </span>
                    </div>
                </div>

                <DialogFooter className="flex flex-col gap-2 sm:flex-row">
                    <Button variant="outline" onClick={() => setOpen(false)} className="w-full sm:w-auto">
                        Cerrar
                    </Button>
                    <Button variant="outline" asChild className="w-full sm:w-auto">
                        <a href="mailto:soporte@aldia.app">
                            <Mail className="mr-2 h-4 w-4" />
                            Contactar soporte
                        </a>
                    </Button>
                    <Button asChild className="w-full bg-red-600 hover:bg-red-700 sm:w-auto">
                        <Link href={planesIndex().url}>
                            <ExternalLink className="mr-2 h-4 w-4" />
                            Ver planes
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
