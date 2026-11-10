import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type PanelTone =
    | 'emerald'
    | 'blue'
    | 'amber'
    | 'violet'
    | 'rose'
    | 'indigo'
    | 'cyan'
    | 'slate'
    | 'orange'
    | 'teal';

interface ToneStyles {
    header: string;
    icon: string;
    ring: string;
}

const TONES: Record<PanelTone, ToneStyles> = {
    emerald: {
        header: 'border-emerald-100 bg-emerald-50',
        icon: 'bg-white text-emerald-700',
        ring: 'ring-emerald-100',
    },
    blue: {
        header: 'border-blue-100 bg-blue-50',
        icon: 'bg-white text-blue-700',
        ring: 'ring-blue-100',
    },
    amber: {
        header: 'border-amber-100 bg-amber-50',
        icon: 'bg-white text-amber-700',
        ring: 'ring-amber-100',
    },
    violet: {
        header: 'border-violet-100 bg-violet-50',
        icon: 'bg-white text-violet-700',
        ring: 'ring-violet-100',
    },
    rose: {
        header: 'border-rose-100 bg-rose-50',
        icon: 'bg-white text-rose-700',
        ring: 'ring-rose-100',
    },
    indigo: {
        header: 'border-indigo-100 bg-indigo-50',
        icon: 'bg-white text-indigo-700',
        ring: 'ring-indigo-100',
    },
    cyan: {
        header: 'border-cyan-100 bg-cyan-50',
        icon: 'bg-white text-cyan-700',
        ring: 'ring-cyan-100',
    },
    slate: {
        header: 'border-slate-200 bg-slate-50',
        icon: 'bg-white text-slate-700',
        ring: 'ring-slate-100',
    },
    orange: {
        header: 'border-orange-100 bg-orange-50',
        icon: 'bg-white text-orange-700',
        ring: 'ring-orange-100',
    },
    teal: {
        header: 'border-teal-100 bg-teal-50',
        icon: 'bg-white text-teal-700',
        ring: 'ring-teal-100',
    },
};

interface SectionPanelProps {
    title: string;
    description?: string;
    icon?: LucideIcon;
    tone?: PanelTone;
    count?: number;
    countLabel?: string;
    badges?: ReactNode;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
}

/**
 * Panel de control reutilizable para agrupar registros por tipo, estado o
 * categoría. Mantiene el mismo lenguaje visual en todos los módulos.
 */
export function SectionPanel({
    title,
    description,
    icon: Icon,
    tone = 'slate',
    count,
    countLabel = 'registro(s)',
    badges,
    action,
    children,
    className,
    contentClassName,
}: SectionPanelProps) {
    const styles = TONES[tone];

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border bg-card shadow-sm ring-1',
                styles.ring,
                className,
            )}
        >
            <div
                className={cn(
                    'flex flex-col gap-2 border-b p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4',
                    styles.header,
                )}
            >
                <div className="flex min-w-0 items-center gap-3">
                    {Icon && (
                        <div
                            className={cn(
                                'shrink-0 rounded-xl p-2 shadow-sm',
                                styles.icon,
                            )}
                        >
                            <Icon className="h-5 w-5" />
                        </div>
                    )}
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h3 className="truncate text-sm font-black sm:text-base">
                                {title}
                            </h3>
                            {typeof count === 'number' && (
                                <Badge
                                    variant="outline"
                                    className="bg-white/70 text-[10px]"
                                >
                                    {count} {countLabel}
                                </Badge>
                            )}
                        </div>
                        {description && (
                            <p className="text-[11px] text-muted-foreground sm:text-xs">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {(badges || action) && (
                    <div className="flex flex-wrap items-center gap-1.5">
                        {badges}
                        {action}
                    </div>
                )}
            </div>
            <div className={contentClassName}>{children}</div>
        </div>
    );
}
