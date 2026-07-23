import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Empleado {
    id: number;
    nombre: string;
    apellido: string;
}

interface Almacen {
    id: number;
    nombre: string;
    codigo: string;
    direccion: string | null;
    telefono: string | null;
    capacidad: number | null;
    tipo: string;
    activo: boolean;
    notas: string | null;
    empleados?: Empleado[];
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Almacenes', href: '/almacenes' },
    { title: 'Detalles', href: '/almacenes/:id' },
];

export default function Show({ almacen }: { almacen: Almacen }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Almacén: ${almacen.nombre}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/almacenes">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">{almacen.nombre}</h1>
                        <Badge
                            variant={almacen.activo ? 'default' : 'secondary'}
                        >
                            {almacen.activo ? 'Activo' : 'Inactivo'}
                        </Badge>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información del Almacén</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Código
                                    </p>
                                    <p className="font-medium">
                                        {almacen.codigo}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Tipo
                                    </p>
                                    <p className="font-medium">
                                        {almacen.tipo}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Dirección
                                    </p>
                                    <p className="font-medium">
                                        {almacen.direccion || 'No especificada'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Teléfono
                                    </p>
                                    <p className="font-medium">
                                        {almacen.telefono || 'No especificado'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Capacidad
                                    </p>
                                    <p className="font-medium">
                                        {almacen.capacidad || 'Ilimitada'}
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
                                {almacen.notas || 'Sin notas'}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
