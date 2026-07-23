import { Head, Link } from '@inertiajs/react';
import { Check, Crown, Sparkles, Shield, CreditCard, Info } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Plan {
    nombre: string;
    precio: string;
    periodo: string;
    descripcion: string;
    popular: boolean;
    caracteristicas: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Planes', href: '/planes' },
];

export default function PlanesIndex({ planes }: { planes: Plan[] }) {
    const getPlanStyles = (plan: Plan) => {
        if (plan.popular) {
            return {
                borderColor: 'border-amber-400',
                cardBg: 'bg-white',
                shadow: 'shadow-xl shadow-amber-200/30',
                badge: 'bg-gradient-to-r from-amber-500 to-orange-500 text-white',
                button: 'bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600',
                priceColor: 'text-amber-600',
            };
        }
        if (plan.nombre === 'Gratuito') {
            return {
                borderColor: 'border-gray-200',
                cardBg: 'bg-white',
                shadow: 'shadow-sm',
                badge: 'bg-gray-100 text-gray-600',
                button: 'bg-gray-800 hover:bg-gray-700',
                priceColor: 'text-gray-800',
            };
        }
        if (plan.nombre === 'Corporativo') {
            return {
                borderColor: 'border-purple-200',
                cardBg: 'bg-white',
                shadow: 'shadow-md',
                badge: 'bg-purple-100 text-purple-700',
                button: 'bg-purple-600 hover:bg-purple-700',
                priceColor: 'text-purple-600',
            };
        }
        return {
            borderColor: 'border-gray-200',
            cardBg: 'bg-white',
            shadow: 'shadow-sm hover:shadow-md',
            badge: 'bg-gray-100 text-gray-600',
            button: 'bg-blue-600 hover:bg-blue-700',
            priceColor: 'text-blue-600',
        };
    };

    return (
        <>
            <Head title="Planes" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-6 p-4 md:p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-black">Planes y Precios</h1>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Card className="border-blue-200 bg-blue-50/50">
                            <CardHeader className="pb-2">
                                <div className="flex items-center gap-2">
                                    <Sparkles className="h-5 w-5 text-blue-600" />
                                    <CardTitle className="text-sm font-bold text-blue-700">Prueba Activa</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-blue-600/80">
                                    Estás disfrutando acceso completo a todas las funcionalidades.
                                    Cuando finalice tu prueba, elige un plan para continuar.
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="border-emerald-200 bg-emerald-50/50">
                            <CardHeader className="pb-2">
                                <div className="flex items-center gap-2">
                                    <Shield className="h-5 w-5 text-emerald-600" />
                                    <CardTitle className="text-sm font-bold text-emerald-700">Sin Compromiso</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-emerald-600/80">
                                    Puedes cambiar de plan en cualquier momento. Todos los planes incluyen
                                    soporte técnico y actualizaciones gratuitas.
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-wrap justify-center gap-6">
                        {planes.map((plan, index) => {
                            const styles = getPlanStyles(plan);
                            return (
                                <div
                                    key={index}
                                    className={`relative w-full sm:max-w-[280px] rounded-2xl border-2 ${styles.borderColor} ${styles.cardBg} ${styles.shadow} p-6 transition-all`}
                                >
                                    {plan.popular && (
                                        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                            <Badge className="bg-gradient-to-r from-amber-500 to-orange-500 text-white border-0 px-4 py-1 text-xs font-bold">
                                                <Crown className="h-3 w-3 mr-1" />
                                                Más popular
                                            </Badge>
                                        </div>
                                    )}

                                    <div className="mb-4 text-center">
                                        <h3 className="text-lg font-bold text-foreground">{plan.nombre}</h3>
                                        <p className="mt-1 text-sm text-muted-foreground">{plan.descripcion}</p>
                                    </div>

                                    <div className="mb-6 text-center">
                                        <span className={`text-4xl font-extrabold ${styles.priceColor}`}>
                                            {plan.precio}
                                        </span>
                                        <span className="text-muted-foreground text-sm ml-1">{plan.periodo}</span>
                                    </div>

                                    <ul className="mb-6 space-y-3">
                                        {plan.caracteristicas.map((caract, i) => (
                                            <li key={i} className="flex items-start gap-2 text-sm text-muted-foreground">
                                                <Check className="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" />
                                                {caract}
                                            </li>
                                        ))}
                                    </ul>

                                    {plan.nombre === 'Corporativo' ? (
                                        <Button className="w-full" asChild>
                                            <Link href="/mensajes">
                                                <Info className="h-4 w-4 mr-1" />
                                                Contactar ventas
                                            </Link>
                                        </Button>
                                    ) : plan.nombre === 'Gratuito' ? (
                                        <p className="text-center text-xs text-muted-foreground">
                                            Plan actual
                                        </p>
                                    ) : (
                                        <Button className={`w-full ${styles.button}`}>
                                            <CreditCard className="h-4 w-4 mr-1" />
                                            Suscribirse
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <div className="text-center">
                        <p className="text-xs text-muted-foreground">
                            ¿Necesitas ayuda para elegir un plan?{' '}
                            <Link href="/mensajes" className="text-primary hover:underline font-medium">
                                Contáctanos
                            </Link>
                        </p>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
