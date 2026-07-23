import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Clock, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { index as planesIndex } from '@/routes/planes';

export function TrialWarningModal() {
    const { auth } = usePage().props as any;
    const user = auth?.user;
    const [open, setOpen] = React.useState(false);

    const daysRemaining = user?.trial_days_remaining ?? 0;
    const showWarning = user?.is_trial_active && daysRemaining > 0 && daysRemaining <= 3;

    React.useEffect(() => {
        if (!showWarning) return;

        const storageKey = `trial_warning_modal_shown_${user.id}_${daysRemaining}`;
        const lastShown = localStorage.getItem(storageKey);
        const today = new Date().toDateString();

        if (lastShown !== today) {
            setOpen(true);
            localStorage.setItem(storageKey, today);
        }
    }, [showWarning, user?.id, daysRemaining]);

    if (!showWarning) return null;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                        <Clock className="h-6 w-6 text-amber-600" />
                    </div>
                    <DialogTitle className="text-center text-lg">
                        Tu prueba termina en {daysRemaining} {daysRemaining === 1 ? 'día' : 'días'}
                    </DialogTitle>
                    <DialogDescription className="text-center">
                        Para no perder acceso a todas las funciones, elige un plan de suscripción antes de que finalice tu período de prueba.
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
                    <div className="flex items-start gap-2">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            <strong>Mientras tanto:</strong> Todavía tienes acceso completo. Crea los registros que necesites antes de que expire tu prueba.
                        </span>
                    </div>
                </div>

                <DialogFooter className="flex flex-col gap-2 sm:flex-row">
                    <Button variant="outline" onClick={() => setOpen(false)} className="w-full sm:w-auto">
                        Cerrar
                    </Button>
                    <Button asChild className="w-full bg-amber-600 hover:bg-amber-700 sm:w-auto">
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
