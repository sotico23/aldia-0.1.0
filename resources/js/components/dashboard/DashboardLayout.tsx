import { router } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import React, { useState } from 'react';
import { saveConfig } from '@/actions/App/Http/Controllers/Backend/DashboardController';

export interface DashboardWidget {
    key: string;
    title: string;
    defaultW: number;
    component: React.ReactNode;
}

interface DashboardLayoutProps {
    widgets: DashboardWidget[];
    savedLayout?: any;
}

function getColSpanClass(span: number) {
    switch (span) {
        case 12: return 'lg:col-span-12';
        case 6: return 'lg:col-span-6';
        case 4: return 'lg:col-span-4';
        case 8: return 'lg:col-span-8';
        case 3: return 'lg:col-span-3';
        default: return 'lg:col-span-12';
    }
}

export default function DashboardLayout({ widgets, savedLayout }: DashboardLayoutProps) {
    const [editing, setEditing] = useState(false);
    
    // Initialize from saved database state or default to all visible
    const [visibleKeys, setVisibleKeys] = useState<string[]>(() => {
        if (savedLayout && Array.isArray(savedLayout.visibleKeys)) {
            return savedLayout.visibleKeys;
        }
        return widgets.map(w => w.key);
    });

    const handleSaveAndExit = () => {
        router.post(
            saveConfig().url,
            { layout: { visibleKeys }, mode: 'grid' },
            { preserveScroll: true, preserveState: true }
        );
        setEditing(false);
    };

    const toggleWidget = (key: string) => {
        setVisibleKeys(prev =>
            prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key]
        );
    };

    // Keep the widgets in their original defined order, but only show visible ones
    const visibleWidgets = widgets.filter(w => visibleKeys.includes(w.key));

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h2 className="text-[11px] font-black tracking-widest text-muted-foreground/60 uppercase">
                    Dashboard Personalizado
                </h2>
                {editing ? (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setEditing(false)}
                            className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-bold shadow-xs transition-colors text-muted-foreground hover:bg-accent/60"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={handleSaveAndExit}
                            className="flex items-center gap-1.5 rounded-lg bg-primary text-primary-foreground px-4 py-1.5 text-[11px] font-bold shadow-sm transition-colors hover:brightness-110"
                        >
                            Guardar Layout
                        </button>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-[11px] font-bold shadow-xs transition-colors text-muted-foreground hover:bg-accent/60"
                    >
                        <Settings2 className="h-3.5 w-3.5" />
                        Personalizar Dashboard
                    </button>
                )}
            </div>

            {editing && (
                <div className="flex flex-wrap gap-2 rounded-xl border border-border bg-card p-4 shadow-sm animate-in fade-in slide-in-from-top-2">
                    <p className="w-full text-xs text-muted-foreground mb-1 font-semibold">
                        Selecciona los módulos que deseas ver en tu pantalla principal.
                    </p>
                    {widgets.map(w => (
                        <label
                            key={w.key}
                            className="flex cursor-pointer items-center gap-2 rounded-lg border border-border/50 px-3 py-1.5 text-xs font-medium transition-colors hover:bg-accent/60 has-checked:bg-primary/10 has-checked:border-primary/40 select-none"
                        >
                            <input
                                type="checkbox"
                                checked={visibleKeys.includes(w.key)}
                                onChange={() => toggleWidget(w.key)}
                                className="sr-only"
                            />
                            <span className={`h-2 w-2 rounded-full transition-all ${visibleKeys.includes(w.key) ? 'bg-primary scale-110' : 'bg-muted-foreground/30 scale-90'}`} />
                            {w.title}
                        </label>
                    ))}
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-2">
                {visibleWidgets.map((w) => (
                    <div
                        key={w.key}
                        className={`col-span-1 ${getColSpanClass(w.defaultW)} transition-all duration-300`}
                    >
                        {w.component}
                    </div>
                ))}
            </div>
        </div>
    );
}
