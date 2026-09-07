import { AlertTriangle, Loader2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
    processing?: boolean;
    onConfirm: () => void;
};

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Eliminar',
    cancelLabel = 'Cancelar',
    destructive = true,
    processing = false,
    onConfirm,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="flex-col gap-4 sm:flex-row sm:items-start sm:gap-3 sm:text-left">
                    <div
                        className={`mx-auto flex size-12 shrink-0 items-center justify-center rounded-full ${destructive ? 'bg-destructive/10 text-destructive' : 'bg-primary/10 text-primary'}`}
                    >
                        <AlertTriangle className="size-6" />
                    </div>
                    <div className="min-w-0 text-center sm:text-left">
                        <DialogTitle>{title}</DialogTitle>
                        {description && (
                            <DialogDescription className="mt-1.5">
                                {description}
                            </DialogDescription>
                        )}
                    </div>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={onConfirm}
                        disabled={processing}
                    >
                        {processing && (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        )}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}