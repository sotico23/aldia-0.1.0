import { Eye } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface TruncatedTextProps {
    /** Texto a mostrar. Si viene vacío se usa `emptyLabel`. */
    text?: string | null;
    /** Cantidad de líneas visibles antes de truncar. */
    lines?: 1 | 2 | 3;
    /** Texto mostrado cuando no hay contenido. */
    emptyLabel?: string;
    className?: string;
    textClassName?: string;
    /** Acción al pulsar el botón de detalle (ej. abrir modal). */
    onExpand?: () => void;
    /** Etiqueta accesible del botón de detalle. */
    expandLabel?: string;
    /** Oculta el botón/tooltip y deja solo el texto truncado. */
    hideAction?: boolean;
}

const LINE_CLASS: Record<1 | 2 | 3, string> = {
    1: 'line-clamp-1',
    2: 'line-clamp-2',
    3: 'line-clamp-3',
};

export function TruncatedText({
    text,
    lines = 1,
    emptyLabel = 'Sin descripción',
    className,
    textClassName,
    onExpand,
    expandLabel = 'Ver detalle completo',
    hideAction = false,
}: TruncatedTextProps) {
    if (!text || !text.trim()) {
        return (
            <span className="text-xs text-muted-foreground italic">
                {emptyLabel}
            </span>
        );
    }

    if (hideAction) {
        return (
            <p
                className={cn(
                    'min-w-0 text-sm text-muted-foreground',
                    LINE_CLASS[lines],
                    textClassName,
                )}
            >
                {text}
            </p>
        );
    }

    return (
        <div className={cn('flex min-w-0 items-center gap-1.5', className)}>
            <p
                className={cn(
                    'min-w-0 text-sm text-muted-foreground',
                    LINE_CLASS[lines],
                    textClassName,
                )}
            >
                {text}
            </p>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        onClick={onExpand}
                        className="shrink-0 rounded p-0.5 text-muted-foreground transition-colors hover:text-primary focus:ring-1 focus:ring-primary focus:outline-none"
                        aria-label={expandLabel}
                    >
                        <Eye className="h-3.5 w-3.5" />
                    </button>
                </TooltipTrigger>
                <TooltipContent className="max-w-sm">
                    <p className="line-clamp-6 text-xs whitespace-pre-line">
                        {text}
                    </p>
                </TooltipContent>
            </Tooltip>
        </div>
    );
}
