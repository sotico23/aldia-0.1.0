import { router } from '@inertiajs/react';
import { Settings2, GripVertical, X } from 'lucide-react';
import { useState, useRef, useCallback } from 'react';
import { toggleWidget, reorderWidgets } from '@/actions/App/Http/Controllers/Backend/DashboardController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import KpiWidgetGrid from './KpiWidgetGrid';

interface KpiWidget {
    key: string;
    label: string;
    icon: string;
    color: string;
    value: number | string;
    subValue: string;
    href: string;
    format: 'currency' | 'number' | 'percent';
    visible: boolean;
    order_index: number;
}

interface KpiWidgetSectionProps {
    widgets: KpiWidget[];
}

export default function KpiWidgetSection({ widgets: initialWidgets }: KpiWidgetSectionProps) {
    const [widgets, setWidgets] = useState<KpiWidget[]>(initialWidgets);
    const [showModal, setShowModal] = useState(false);
    const dragKey = useRef<string | null>(null);
    const overKey = useRef<string | null>(null);

    const visibleWidgets = widgets.filter(w => w.visible);

    const handleToggle = useCallback((key: string) => {
        setWidgets(prev => prev.map(w => w.key === key ? { ...w, visible: !w.visible } : w));
        router.post(toggleWidget().url, { key }, { preserveScroll: true, preserveState: true });
    }, []);

    const handleDragStart = (key: string) => {
        dragKey.current = key;
    };

    const handleDragOver = (e: React.DragEvent, key: string) => {
        e.preventDefault();
        overKey.current = key;
    };

    const handleDrop = () => {
        if (!dragKey.current || dragKey.current === overKey.current) return;
        setWidgets(prev => {
            const next = [...prev];
            const fromIdx = next.findIndex(w => w.key === dragKey.current);
            const toIdx = next.findIndex(w => w.key === overKey.current);
            if (fromIdx < 0 || toIdx < 0) return prev;
            const [item] = next.splice(fromIdx, 1);
            next.splice(toIdx, 0, item);
            const keys = next.map(w => w.key);
            router.post(reorderWidgets().url, { keys }, { preserveScroll: true, preserveState: true });
            return next;
        });
        dragKey.current = null;
        overKey.current = null;
    };

    return (
        <Card className="h-full border-0 shadow-none">
            <CardHeader className="flex flex-row items-center justify-between px-4 pt-3 pb-0">
                <CardTitle className="text-xs font-bold">Indicadores Clave</CardTitle>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 gap-1 px-2 text-[10px] font-medium text-muted-foreground"
                    onClick={() => setShowModal(true)}
                >
                    <Settings2 className="h-3 w-3" />
                    Personalizar
                </Button>
            </CardHeader>
            <CardContent className="px-4 pb-3 pt-3">
                <KpiWidgetGrid widgets={visibleWidgets} />
            </CardContent>

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div className="w-full max-w-md rounded-xl border border-border bg-card p-5 shadow-2xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-sm font-bold">Personalizar Indicadores</h3>
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-lg p-1 text-muted-foreground hover:bg-accent/60 transition-colors"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <p className="mb-3 text-[11px] text-muted-foreground">
                            Arrastra para reordenar. Desmarca para ocultar.
                        </p>

                        <div className="flex flex-col gap-1">
                            {widgets.map(w => (
                                <label
                                    key={w.key}
                                    draggable
                                    onDragStart={() => handleDragStart(w.key)}
                                    onDragOver={e => handleDragOver(e, w.key)}
                                    onDrop={handleDrop}
                                    className="flex cursor-pointer items-center gap-3 rounded-lg border border-border/50 px-3 py-2 text-xs font-medium transition-colors hover:bg-accent/60 has-checked:bg-primary/5 has-checked:border-primary/20 cursor-grab active:cursor-grabbing select-none"
                                >
                                    <GripVertical className="h-3.5 w-3.5 shrink-0 text-muted-foreground/40" />
                                    <input
                                        type="checkbox"
                                        checked={w.visible}
                                        onChange={() => handleToggle(w.key)}
                                        className="h-3.5 w-3.5 rounded border-border accent-primary"
                                    />
                                    <span className="flex-1">{w.label}</span>
                                    <span className={`h-2 w-2 rounded-full ${
                                        w.color === 'emerald' ? 'bg-emerald-500' :
                                        w.color === 'amber' ? 'bg-amber-500' :
                                        w.color === 'rose' ? 'bg-rose-500' :
                                        w.color === 'indigo' ? 'bg-indigo-500' :
                                        w.color === 'blue' ? 'bg-blue-500' :
                                        w.color === 'cyan' ? 'bg-cyan-500' :
                                        w.color === 'violet' ? 'bg-violet-500' :
                                        w.color === 'orange' ? 'bg-orange-500' :
                                        w.color === 'pink' ? 'bg-pink-500' :
                                        w.color === 'teal' ? 'bg-teal-500' :
                                        'bg-gray-500'
                                    }`} />
                                </label>
                            ))}
                        </div>

                        <div className="mt-4 flex justify-end">
                            <Button
                                size="sm"
                                className="h-8 text-xs"
                                onClick={() => setShowModal(false)}
                            >
                                Listo
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </Card>
    );
}
