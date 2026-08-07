import { Head } from '@inertiajs/react';
import { CheckCircle2, Clock, AlertCircle, Calendar, User } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent
} from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Tarea {
    id: number;
    titulo: string;
    descripcion: string | null;
    estado: string;
    prioridad: string;
    fecha_limite: string | null;
    usuario: {
        id: number;
        name: string;
    };
    empleado: {
        id: number;
        name: string;
    } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Lista de Pendientes', href: '/lista-pendientes' },
];

export default function Index({ pendientes }: { pendientes: Tarea[] }) {
    const { code: countryCode, currency } = useCountry();
    const getPrioridadBadge = (prioridad: string) => {
        switch (prioridad) {
            case 'alta':
                return (
                    <Badge className="bg-red-100 text-red-700 hover:bg-red-100">
                        Alta
                    </Badge>
                );
            case 'media':
                return (
                    <Badge className="bg-amber-100 text-amber-700 hover:bg-amber-100">
                        Media
                    </Badge>
                );
            case 'baja':
                return (
                    <Badge className="bg-green-100 text-green-700 hover:bg-green-100">
                        Baja
                    </Badge>
                );
            default:
                return <Badge>{prioridad}</Badge>;
        }
    };

    const getEstadoBadge = (estado: string) => {
        switch (estado) {
            case 'completada':
                return (
                    <Badge className="bg-green-100 text-green-700 hover:bg-green-100">
                        <CheckCircle2 className="mr-1 h-3 w-3" />
                        Completada
                    </Badge>
                );
            case 'en_progreso':
                return (
                    <Badge className="bg-blue-100 text-blue-700 hover:bg-blue-100">
                        <Clock className="mr-1 h-3 w-3" />
                        En Progreso
                    </Badge>
                );
            case 'pendiente':
                return (
                    <Badge className="bg-amber-100 text-amber-700 hover:bg-amber-100">
                        <AlertCircle className="mr-1 h-3 w-3" />
                        Pendiente
                    </Badge>
                );
            default:
                return <Badge>{estado}</Badge>;
        }
    };

    const estaVencida = (fechaLimite: string | null) => {
        if (!fechaLimite) return false;
        return new Date(fechaLimite) < new Date();
    };

    const pendientesOrdenados = [...pendientes].sort((a, b) => {
        if (a.estado === 'completada' && b.estado !== 'completada') return 1;
        if (a.estado !== 'completada' && b.estado === 'completada') return -1;
        if (a.prioridad === 'alta' && b.prioridad !== 'alta') return -1;
        if (a.prioridad !== 'alta' && b.prioridad === 'alta') return 1;
        return 0;
    });

    const stats = {
        total: pendientes.length,
        pendientes: pendientes.filter((p) => p.estado === 'pendiente').length,
        enProgreso: pendientes.filter((p) => p.estado === 'en_progreso').length,
        completadas: pendientes.filter((p) => p.estado === 'completada').length,
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Lista de Pendientes" />
            <div className="mx-auto max-w-[1600px] p-6">
                <div className="mb-6">
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        Lista de Pendientes
                    </h1>
                    <p className="text-muted-foreground">
                        Todas tus tareas y asignaciones
                    </p>
                </div>

                <div className="mb-6 grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Total
                                    </p>
                                    <p className="text-2xl font-bold">
                                        {stats.total}
                                    </p>
                                </div>
                                <Calendar className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Pendientes
                                    </p>
                                    <p className="text-2xl font-bold text-amber-600">
                                        {stats.pendientes}
                                    </p>
                                </div>
                                <AlertCircle className="h-8 w-8 text-amber-500" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        En Progreso
                                    </p>
                                    <p className="text-2xl font-bold text-blue-600">
                                        {stats.enProgreso}
                                    </p>
                                </div>
                                <Clock className="h-8 w-8 text-blue-500" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Completadas
                                    </p>
                                    <p className="text-2xl font-bold text-green-600">
                                        {stats.completadas}
                                    </p>
                                </div>
                                <CheckCircle2 className="h-8 w-8 text-green-500" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-3">
                    {pendientesOrdenados.map((tarea) => (
                        <Card
                            key={tarea.id}
                            className={`${tarea.estado === 'completada' ? 'opacity-60' : ''}`}
                        >
                            <CardContent className="flex items-center justify-between py-4">
                                <div className="flex flex-1 items-center gap-4">
                                    <div className="flex flex-col gap-1">
                                        <div className="flex items-center gap-2">
                                            <h3
                                                className={`font-semibold ${
                                                    tarea.estado ===
                                                    'completada'
                                                        ? 'text-muted-foreground line-through'
                                                        : ''
                                                }`}
                                            >
                                                {tarea.titulo}
                                            </h3>
                                            {getPrioridadBadge(tarea.prioridad)}
                                            {getEstadoBadge(tarea.estado)}
                                        </div>
                                        {tarea.descripcion && (
                                            <p className="text-sm text-muted-foreground">
                                                {tarea.descripcion}
                                            </p>
                                        )}
                                        <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                            {tarea.fecha_limite && (
                                                <span
                                                    className={`flex items-center gap-1 ${
                                                        estaVencida(
                                                            tarea.fecha_limite,
                                                        ) &&
                                                        tarea.estado !==
                                                            'completada'
                                                            ? 'text-red-600'
                                                            : ''
                                                    }`}
                                                >
                                                    <Calendar className="h-3 w-3" />
                                                    {new Date(
                                                        tarea.fecha_limite,
                                                    ).toLocaleDateString(
                                                        currency.locale,
                                                    )}
                                                </span>
                                            )}
                                            <span className="flex items-center gap-1">
                                                <User className="h-3 w-3" />
                                                {tarea.empleado?.name ||
                                                    tarea.usuario.name}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {pendientes.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <CheckCircle2 className="mb-4 h-12 w-12 text-green-500" />
                        <h3 className="text-lg font-semibold">¡Todo al día!</h3>
                        <p className="text-muted-foreground">
                            No tienes pendientes
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
