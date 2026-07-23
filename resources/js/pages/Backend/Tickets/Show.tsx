import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDateCLP } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Cliente {
    id: number;
    nombre: string;
}

interface Producto {
    id: number;
    nombre: string;
}

interface Ticket {
    id: number;
    titulo: string;
    descripcion: string | null;
    cliente_id: number | null;
    producto_id: number | null;
    prioridad: string;
    estado: string;
    categoria: string | null;
    asignado_a: string | null;
    cliente?: Cliente;
    producto?: Producto;
    created_at: string;
    updated_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets', href: '/tickets' },
    { title: 'Detalles', href: '/tickets/:id' },
];

export default function Show({ ticket }: { ticket: Ticket }) {
    const prioridadColores: Record<string, string> = {
        baja: 'bg-green-500',
        media: 'bg-yellow-500',
        alta: 'bg-orange-500',
        critica: 'bg-red-500',
    };

    const estadoColores: Record<string, string> = {
        abierto: 'bg-blue-500',
        en_proceso: 'bg-yellow-500',
        resuelto: 'bg-green-500',
        cerrado: 'bg-gray-500',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ticket: ${ticket.titulo}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/tickets">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">{ticket.titulo}</h1>
                        <Badge
                            className={
                                prioridadColores[ticket.prioridad] ||
                                'bg-gray-500'
                            }
                        >
                            {ticket.prioridad}
                        </Badge>
                        <Badge
                            className={
                                estadoColores[ticket.estado] || 'bg-gray-500'
                            }
                        >
                            {ticket.estado}
                        </Badge>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información del Ticket</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Cliente
                                    </p>
                                    <p className="font-medium">
                                        {ticket.cliente?.nombre ||
                                            'Sin cliente'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Producto
                                    </p>
                                    <p className="font-medium">
                                        {ticket.producto?.nombre ||
                                            'Sin producto'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Categoría
                                    </p>
                                    <p className="font-medium">
                                        {ticket.categoria || 'Sin categoría'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Asignado a
                                    </p>
                                    <p className="font-medium">
                                        {ticket.asignado_a || 'Sin asignar'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Fecha de creación
                                    </p>
                                    <p className="font-medium">
                                        {formatDateCLP(ticket.created_at)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Descripción</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="whitespace-pre-wrap">
                                {ticket.descripcion || 'Sin descripción'}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
