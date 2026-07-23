import { Eye } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

type ColorScheme = 'blue' | 'purple' | 'green' | 'amber' | 'orange' | 'rose' | 'teal' | 'slate';

const colorSchemes: Record<ColorScheme, string> = {
    blue: 'from-blue-700 via-indigo-800 to-blue-950',
    purple: 'from-purple-700 via-violet-800 to-indigo-950',
    green: 'from-green-700 via-emerald-800 to-teal-950',
    amber: 'from-amber-700 via-orange-800 to-yellow-950',
    orange: 'from-orange-700 via-red-800 to-yellow-950',
    rose: 'from-rose-700 via-pink-800 to-red-950',
    teal: 'from-teal-700 via-cyan-800 to-blue-950',
    slate: 'from-slate-700 via-gray-800 to-zinc-950',
};

interface QuickStat {
    label: string;
    val: string;
    colorScheme: ColorScheme;
}

interface ModalShowProps<T> {
    isOpen: boolean;
    setIsOpen: (open: boolean) => void;
    item: T | null;
    title: string;
    badgeLabel?: string;
    description?: string;
    colorScheme?: ColorScheme;
    quickStats?: QuickStat[];
    children?: React.ReactNode;
    onClose?: () => void;
}

export function ModalShow<T extends Record<string, any>>({
    isOpen,
    setIsOpen,
    item,
    title,
    badgeLabel = 'Detalle',
    description,
    colorScheme = 'blue',
    quickStats,
    children,
    onClose,
}: ModalShowProps<T>) {
    if (!item) return null;

    const gradient = colorSchemes[colorScheme];
    const titleField = item.nombre || item.titulo || item.nombre_completo || title;

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogContent className="max-h-[80vh] max-w-2xl overflow-hidden border-none bg-white p-0 shadow-xl">
                <DialogHeader className="relative overflow-hidden px-8 pt-10 pb-20">
                    <div className={`absolute inset-0 bg-gradient-to-br ${gradient} opacity-100`} />
                    <div className="absolute top-0 right-0 p-8 text-white opacity-20">
                        <Eye className="h-24 w-24 rotate-12" />
                    </div>
                    <div className="relative z-10 flex flex-col gap-1 text-white">
                        <Badge className="w-fit border-none bg-white/20 px-3 py-1 text-[10px] font-bold tracking-widest text-white uppercase hover:bg-white/30">
                            {badgeLabel}
                        </Badge>
                        <DialogTitle className="text-3xl font-black tracking-tight text-white uppercase sm:text-4xl">
                            {titleField}
                        </DialogTitle>
                        {description && (
                            <DialogDescription className="text-lg font-medium text-white/80">
                                {description}
                            </DialogDescription>
                        )}
                    </div>
                </DialogHeader>

                <div className="relative z-20 -mt-10 mb-6 flex max-h-[calc(80vh)] flex-col gap-6 overflow-y-auto px-8 pb-4">
                    {quickStats && quickStats.length > 0 && (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {quickStats.map((stat, idx) => (
                                <div
                                    key={idx}
                                    className={`rounded-xl border p-4 transition-all duration-300 hover:shadow-md ${
                                        stat.colorScheme === 'blue'
                                            ? 'border-blue-200 bg-blue-50 text-blue-800'
                                            : stat.colorScheme === 'purple'
                                            ? 'border-purple-200 bg-purple-50 text-purple-800'
                                            : stat.colorScheme === 'green'
                                            ? 'border-green-200 bg-green-50 text-green-800'
                                            : stat.colorScheme === 'amber'
                                            ? 'border-amber-200 bg-amber-50 text-amber-800'
                                            : stat.colorScheme === 'orange'
                                            ? 'border-orange-200 bg-orange-50 text-orange-800'
                                            : stat.colorScheme === 'rose'
                                            ? 'border-rose-200 bg-rose-50 text-rose-800'
                                            : stat.colorScheme === 'teal'
                                            ? 'border-teal-200 bg-teal-50 text-teal-800'
                                            : 'border-slate-200 bg-slate-50 text-slate-800'
                                    }`}
                                >
                                    <p className="mb-1 text-[10px] font-extrabold tracking-wider uppercase opacity-70">
                                        {stat.label}
                                    </p>
                                    <p className="truncate text-sm font-semibold uppercase">
                                        {stat.val}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                    {children}
                </div>

                <DialogFooter className="bg-gray-50 border-t p-4">
                    <Button onClick={onClose || (() => setIsOpen(false))}>
                        Cerrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export { colorSchemes };
export type { ColorScheme };