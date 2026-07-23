import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { WhatsAppButton } from '@/components/whatsapp-button';
import AppLayout from '@/layouts/app-layout';
import { formatDateCLP } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Categoria {
    id: number;
    nombre: string;
}

interface Cliente {
    id: number;
    nombre: string;
    nit: string | null;
    rut: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    ciudad: string | null;
    region: string | null;
    comuna: string | null;
    giro: string | null;
    contacto: string | null;
    telefono_contacto: string | null;
    categoria_id: number | null;
    activo: boolean;
    notas: string | null;
    categoria?: Categoria;
    created_at: string;
    updated_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clientes', href: '/clientes' },
    { title: 'Detalles', href: '/clientes/:id' },
];

export default function Show({ cliente }: { cliente: Cliente }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Cliente: ${cliente.nombre}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/clientes">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">{cliente.nombre}</h1>
                        <Badge
                            variant={cliente.activo ? 'default' : 'destructive'}
                        >
                            {cliente.activo ? 'Activo' : 'Inactivo'}
                        </Badge>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card className="col-span-2">
                        <CardHeader>
                            <CardTitle>Información del Cliente</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        RUT
                                    </p>
                                    <p className="font-medium">
                                        {cliente.rut ||
                                            cliente.nit ||
                                            'No especificado'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Categoría
                                    </p>
                                    <p className="font-medium">
                                        {cliente.categoria?.nombre ||
                                            'Sin categoría'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Teléfono
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium">
                                            {cliente.telefono ||
                                                'No especificado'}
                                        </p>
                                        {cliente.telefono && (
                                            <WhatsAppButton
                                                phone={cliente.telefono}
                                                nombre={cliente.nombre}
                                            />
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Email
                                    </p>
                                    <p className="font-medium">
                                        {cliente.email || 'No especificado'}
                                    </p>
                                </div>
                                <div className="space-y-1 md:col-span-2">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Dirección
                                    </p>
                                    <p className="font-medium">
                                        {cliente.direccion ? (
                                            <>
                                                {cliente.direccion}
                                                {cliente.comuna &&
                                                    `, ${cliente.comuna}`}
                                                {cliente.ciudad &&
                                                    `, ${cliente.ciudad}`}
                                            </>
                                        ) : (
                                            'No especificada'
                                        )}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Giro
                                    </p>
                                    <p className="font-medium">
                                        {cliente.giro || 'No especificado'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Fecha de creación
                                    </p>
                                    <p className="font-medium">
                                        {formatDateCLP(cliente.created_at)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Persona de Contacto</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Nombre
                                    </p>
                                    <p className="font-medium">
                                        {cliente.contacto || 'No especificado'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Teléfono
                                    </p>
                                    <p className="font-medium">
                                        {cliente.telefono_contacto ||
                                            'No especificado'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {cliente.notas && (
                        <Card className="col-span-1 lg:col-span-3">
                            <CardHeader>
                                <CardTitle>Notas</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="whitespace-pre-wrap">
                                    {cliente.notas}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
