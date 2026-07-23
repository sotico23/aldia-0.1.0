import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { WhatsAppButton } from '@/components/whatsapp-button';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateCLP } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Cliente {
    id: number;
    nombre: string;
    telefono: string | null;
    email: string | null;
}

interface Oportunidad {
    id: number;
    nombre: string;
    cliente_id: number;
    valor: number;
    etapa: string;
    probabilidad: number;
    fecha_cierre_estimada: string | null;
    descripcion: string | null;
    cliente?: Cliente;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Oportunidades', href: '/oportunidades' },
    { title: 'Detalles', href: '/oportunidades/:id' },
];

const etapasMap: Record<string, string> = {
    prospecting: 'Prospección',
    qualification: 'Calificación',
    proposal: 'Propuesta',
    negotiation: 'Negociación',
    closed_won: 'Cerrada Ganada',
    closed_lost: 'Cerrada Perdida',
};

export default function Show({ oportunidad }: { oportunidad: Oportunidad }) {
    const colores: Record<string, string> = {
        prospecting: 'bg-blue-500',
        qualification: 'bg-yellow-500',
        proposal: 'bg-purple-500',
        negotiation: 'bg-orange-500',
        closed_won: 'bg-green-500',
        closed_lost: 'bg-red-500',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Oportunidad: ${oportunidad.nombre}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/oportunidades">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">
                            {oportunidad.nombre}
                        </h1>
                        <Badge
                            className={
                                colores[oportunidad.etapa] || 'bg-gray-500'
                            }
                        >
                            {etapasMap[oportunidad.etapa] || oportunidad.etapa}
                        </Badge>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card className="col-span-2">
                        <CardHeader>
                            <CardTitle>Información de la Oportunidad</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Cliente
                                    </p>
                                    <p className="font-medium">
                                        {oportunidad.cliente?.nombre ||
                                            'Sin cliente'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Teléfono
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium">
                                            {oportunidad.cliente?.telefono ||
                                                'No especificado'}
                                        </p>
                                        {oportunidad.cliente?.telefono && (
                                            <WhatsAppButton
                                                phone={
                                                    oportunidad.cliente.telefono
                                                }
                                                nombre={
                                                    oportunidad.cliente.nombre
                                                }
                                            />
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Valor Estimado
                                    </p>
                                    <p className="font-medium text-green-600">
                                        {formatCurrency(oportunidad.valor)}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Probabilidad
                                    </p>
                                    <p className="font-medium">
                                        {oportunidad.probabilidad}%
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Fecha de Cierre Estimada
                                    </p>
                                    <p className="font-medium">
                                        {oportunidad.fecha_cierre_estimada
                                            ? formatDateCLP(
                                                  oportunidad.fecha_cierre_estimada,
                                              )
                                            : 'No especificada'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Fecha de Creación
                                    </p>
                                    <p className="font-medium">
                                        {formatDateCLP(oportunidad.created_at)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Notas</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="whitespace-pre-wrap">
                                {oportunidad.descripcion || 'Sin notas'}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
