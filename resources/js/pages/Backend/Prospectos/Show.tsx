import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { WhatsAppButton } from '@/components/whatsapp-button';
import AppLayout from '@/layouts/app-layout';
import { formatDateCLP } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Prospecto {
    id: number;
    nombre: string;
    rut: string | null;
    email: string | null;
    telefono: string | null;
    empresa: string | null;
    cargo: string | null;
    direccion: string | null;
    comuna: string | null;
    region: string | null;
    estado: string;
    fuente: string | null;
    notas: string | null;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Prospectos', href: '/prospectos' },
    { title: 'Detalles', href: '/prospectos/:id' },
];

const estadoColores: Record<string, string> = {
    nuevo: 'bg-blue-500',
    contactado: 'bg-yellow-500',
    pendiente: 'bg-orange-500',
    perdido: 'bg-red-500',
    convertido: 'bg-green-500',
};

export default function Show({ prospecto }: { prospecto: Prospecto }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Prospecto: ${prospecto.nombre}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/prospectos">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">
                            {prospecto.nombre}
                        </h1>
                        <Badge
                            className={
                                estadoColores[prospecto.estado] || 'bg-gray-500'
                            }
                        >
                            {prospecto.estado}
                        </Badge>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información del Prospecto</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        RUT
                                    </p>
                                    <p className="font-medium">
                                        {prospecto.rut || 'No especificado'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Empresa
                                    </p>
                                    <p className="font-medium">
                                        {prospecto.empresa || 'No especificada'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Cargo
                                    </p>
                                    <p className="font-medium">
                                        {prospecto.cargo || 'No especificado'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Teléfono
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium">
                                            {prospecto.telefono ||
                                                'No especificado'}
                                        </p>
                                        {prospecto.telefono && (
                                            <WhatsAppButton
                                                phone={prospecto.telefono}
                                                nombre={prospecto.nombre}
                                            />
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Email
                                    </p>
                                    <p className="font-medium">
                                        {prospecto.email || 'No especificado'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Fuente
                                    </p>
                                    <p className="font-medium">
                                        {prospecto.fuente || 'No especificada'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Dirección
                                    </p>
                                    <p className="font-medium">
                                        {prospecto.direccion ? (
                                            <>
                                                {prospecto.direccion}
                                                {prospecto.comuna &&
                                                    `, ${prospecto.comuna}`}
                                                {prospecto.region &&
                                                    `, ${prospecto.region}`}
                                            </>
                                        ) : (
                                            'No especificada'
                                        )}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Fecha de creación
                                    </p>
                                    <p className="font-medium">
                                        {formatDateCLP(prospecto.created_at)}
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
                                {prospecto.notas || 'Sin notas'}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
