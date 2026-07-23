import { Link } from '@inertiajs/react';
import { TrendingUp, ShoppingCart, Clock, BarChart, Calendar, Users, Truck, Package, CreditCard, Receipt } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';

interface KpiWidget {
    key: string;
    label: string;
    icon: string;
    color: string;
    value: number | string;
    subValue: string;
    href: string;
    format: 'currency' | 'number' | 'percent';
}

interface KpiWidgetGridProps {
    widgets: KpiWidget[];
}

const iconMap: Record<string, React.ElementType> = {
    'trending-up': TrendingUp,
    'shopping-cart': ShoppingCart,
    'clock': Clock,
    'bar-chart': BarChart,
    'calendar': Calendar,
    'users': Users,
    'truck': Truck,
    'package': Package,
    'credit-card': CreditCard,
    'receipt': Receipt,
};

const colorMap: Record<string, { card: string; icon: string; value: string }> = {
    emerald: { card: 'bg-emerald-500/8 border-emerald-500/20', icon: 'text-emerald-500 bg-emerald-500/10', value: 'text-emerald-600 dark:text-emerald-400' },
    amber: { card: 'bg-amber-500/8 border-amber-500/20', icon: 'text-amber-500 bg-amber-500/10', value: 'text-amber-600 dark:text-amber-400' },
    rose: { card: 'bg-rose-500/8 border-rose-500/20', icon: 'text-rose-500 bg-rose-500/10', value: 'text-rose-600 dark:text-rose-400' },
    indigo: { card: 'bg-indigo-500/8 border-indigo-500/20', icon: 'text-indigo-500 bg-indigo-500/10', value: 'text-indigo-600 dark:text-indigo-400' },
    blue: { card: 'bg-blue-500/8 border-blue-500/20', icon: 'text-blue-500 bg-blue-500/10', value: 'text-blue-600 dark:text-blue-400' },
    cyan: { card: 'bg-cyan-500/8 border-cyan-500/20', icon: 'text-cyan-500 bg-cyan-500/10', value: 'text-cyan-600 dark:text-cyan-400' },
    violet: { card: 'bg-violet-500/8 border-violet-500/20', icon: 'text-violet-500 bg-violet-500/10', value: 'text-violet-600 dark:text-violet-400' },
    orange: { card: 'bg-orange-500/8 border-orange-500/20', icon: 'text-orange-500 bg-orange-500/10', value: 'text-orange-600 dark:text-orange-400' },
    pink: { card: 'bg-pink-500/8 border-pink-500/20', icon: 'text-pink-500 bg-pink-500/10', value: 'text-pink-600 dark:text-pink-400' },
    teal: { card: 'bg-teal-500/8 border-teal-500/20', icon: 'text-teal-500 bg-teal-500/10', value: 'text-teal-600 dark:text-teal-400' },
};

function formatValue(value: number | string, format: KpiWidget['format'], locale: string = 'es-CL'): string {
    if (format === 'percent') return String(value);
    if (format === 'currency') return '$' + Number(value).toLocaleString(locale);
    return String(value);
}

export default function KpiWidgetGrid({ widgets }: KpiWidgetGridProps) {
    const { code: countryCode, currency } = useCountry();
    if (widgets.length === 0) return null;

    return (
        <Card className="h-full border-0 shadow-none">
            <CardHeader className="px-4 pt-3 pb-0">
                <CardTitle className="text-xs font-bold">Indicadores Clave</CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-3 pt-3">
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
                    {widgets.map(widget => {
                        const Icon = iconMap[widget.icon];
                        const colors = colorMap[widget.color] ?? colorMap.indigo;

                        return (
                            <Link
                                key={widget.key}
                                href={widget.href}
                                className={`group/kpi relative flex flex-col gap-1 overflow-hidden rounded-xl border p-3 shadow-xs transition-all duration-300 hover:shadow-lg hover:-translate-y-1 active:scale-[0.98] ${colors.card}`}
                            >
                                <div className="pointer-events-none absolute -inset-x-2 -inset-y-1 bg-gradient-to-b from-white/[0.04] to-transparent opacity-0 transition-opacity duration-300 group-hover/kpi:opacity-100" />
                                <div className="flex items-center justify-between relative z-[1]">
                                    {Icon && (
                                        <span className={`rounded-lg p-1.5 transition-all duration-300 ${colors.icon} group-hover/kpi:scale-110`}>
                                            <Icon className="h-3.5 w-3.5" />
                                        </span>
                                    )}
                                </div>
                                <span className={`relative z-[1] text-sm font-black leading-tight ${colors.value} group-hover/kpi:brightness-125 transition-all duration-300`}>
                                    {formatValue(widget.value, widget.format, currency.locale)}
                                </span>
                                <span className="relative z-[1] text-[10px] font-medium text-muted-foreground leading-tight">
                                    {widget.label}
                                </span>
                                <span className="relative z-[1] text-[8px] text-muted-foreground/60 leading-tight">
                                    {widget.subValue}
                                </span>
                            </Link>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}
