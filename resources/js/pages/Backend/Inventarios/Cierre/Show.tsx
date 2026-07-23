import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Printer,
    CheckCircle,
    XCircle,
    Building2,
    Calendar,
    User,
    FileText,
    ClipboardCheck
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface UserData {
    id: number;
    name: string;
}

interface Almacen {
    id: number;
    nombre: string;
}

interface Cierre {
    id: number;
    closure_date: string;
    type: 'BODEGA' | 'GENERAL';
    status: 'ABIERTO' | 'CERRADO' | 'AUDITADO';
    total_products: number;
    opening_stock: number;
    closing_stock: number;
    expected_stock: number;
    difference: number;
    observations: string | null;
    closed_at: string | null;
    created_at: string;
    user?: UserData;
    almacen?: Almacen;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventario', href: '/inventarios' },
    { title: 'Cierre de Inventario', href: '/inventario-cierre' },
    { title: 'Detalle del Cierre', href: '#' },
];

export default function CierreShow({ cierre }: { cierre: Cierre }) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('inventario.inventarios.viewAny');
    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'ABIERTO':
                return (
                    <Badge className="bg-orange-500 text-white">Abierto</Badge>
                );
            case 'CERRADO':
                return (
                    <Badge className="bg-blue-500 text-white">Cerrado</Badge>
                );
            case 'AUDITADO':
                return (
                    <Badge className="bg-green-500 text-white">Auditado</Badge>
                );
            default:
                return <Badge>{status}</Badge>;
        }
    };

    if (!canAccess) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Cierre de Inventario - ${cierre.closure_date}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => router.get('/inventario-cierre')}
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                        <div>
                            <h1 className="text-3xl font-black tracking-tight text-foreground">
                                Detalle del Cierre
                            </h1>
                            <p className="text-sm font-medium text-muted-foreground">
                                Cierre del{' '}
                                {new Date(
                                    cierre.closure_date,
                                ).toLocaleDateString('es-ES')}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer className="mr-2 h-4 w-4" />
                            Imprimir
                        </Button>
                        {cierre.status === 'CERRADO' && (
                            <Button
                                className="bg-green-600 hover:bg-green-700"
                                onClick={() =>
                                    router.patch(
                                        `/inventario-cierre/${cierre.id}/audit`,
                                    )
                                }
                            >
                                <CheckCircle className="mr-2 h-4 w-4" />
                                Auditar
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-4">
                    <div className="space-y-6 lg:col-span-3">
                        <Card className="border-none shadow-xl">
                            <CardHeader className="border-b pb-4">
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5 text-primary" />
                                    Información del Cierre
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-6">
                                <div className="grid grid-cols-2 gap-6 md:grid-cols-4">
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold text-muted-foreground uppercase">
                                            Fecha
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <span className="font-medium">
                                                {new Date(
                                                    cierre.closure_date,
                                                ).toLocaleDateString('es-ES')}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold text-muted-foreground uppercase">
                                            Tipo
                                        </p>
                                        <Badge variant="secondary">
                                            {cierre.type}
                                        </Badge>
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold text-muted-foreground uppercase">
                                            Estado
                                        </p>
                                        {getStatusBadge(cierre.status)}
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold text-muted-foreground uppercase">
                                            Registrado por
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <User className="h-4 w-4 text-muted-foreground" />
                                            <span className="font-medium">
                                                {cierre.user?.name}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold text-muted-foreground uppercase">
                                            Bodega
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <Building2 className="h-4 w-4 text-muted-foreground" />
                                            <span className="font-medium">
                                                {cierre.almacen?.nombre ||
                                                    'General'}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs font-bold text-muted-foreground uppercase">
                                            Total Productos
                                        </p>
                                        <p className="text-2xl font-black">
                                            {cierre.total_products}
                                        </p>
                                    </div>
                                    {cierre.closed_at && (
                                        <div className="space-y-2">
                                            <p className="text-xs font-bold text-muted-foreground uppercase">
                                                Cerrado/Auditado
                                            </p>
                                            <span className="text-sm">
                                                {new Date(
                                                    cierre.closed_at,
                                                ).toLocaleString('es-ES')}
                                            </span>
                                        </div>
                                    )}
                                </div>

                                {cierre.observations && (
                                    <div className="mt-6 rounded-lg bg-muted/50 p-4">
                                        <p className="mb-2 text-xs font-bold text-muted-foreground uppercase">
                                            Observaciones
                                        </p>
                                        <p className="text-sm">
                                            {cierre.observations}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card className="border-none shadow-xl">
                            <CardHeader className="border-b pb-4">
                                <CardTitle className="flex items-center gap-2">
                                    <ClipboardCheck className="h-5 w-5 text-primary" />
                                    Resumen
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 p-4">
                                <div className="rounded-lg bg-muted/50 p-4">
                                    <div className="mb-2 flex justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Productos
                                        </span>
                                        <span className="font-mono font-bold">
                                            {cierre.total_products} unidades
                                        </span>
                                    </div>
                                    <div className="mb-2 flex justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Stock Inicio
                                        </span>
                                        <span className="font-mono font-bold">
                                            {Number(
                                                cierre.opening_stock || 0,
                                            ).toLocaleString()}
                                        </span>
                                    </div>
                                    <div className="mb-2 flex justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Stock Cierre
                                        </span>
                                        <span className="font-mono font-bold">
                                            {Number(
                                                cierre.closing_stock || 0,
                                            ).toLocaleString()}
                                        </span>
                                    </div>
                                    <div className="mb-2 flex justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Stock Esperado
                                        </span>
                                        <span className="font-mono font-bold">
                                            {Number(
                                                cierre.expected_stock || 0,
                                            ).toLocaleString()}
                                        </span>
                                    </div>
                                    <div className="mt-2 border-t pt-2">
                                        <div className="flex justify-between">
                                            <span className="text-sm font-medium">
                                                Diferencia
                                            </span>
                                            <span
                                                className={`font-mono text-lg font-bold ${Number(cierre.difference || 0) === 0 ? 'text-green-500' : 'text-red-500'}`}
                                            >
                                                {Number(
                                                    cierre.difference || 0,
                                                ) > 0
                                                    ? '+'
                                                    : ''}
                                                {Number(
                                                    cierre.difference || 0,
                                                ).toLocaleString()}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    className={`flex items-center gap-3 rounded-lg p-4 ${Number(cierre.difference || 0) === 0 ? 'bg-green-500/10' : 'bg-red-500/10'}`}
                                >
                                    {Number(cierre.difference || 0) === 0 ? (
                                        <>
                                            <CheckCircle className="h-8 w-8 text-green-500" />
                                            <div>
                                                <p className="font-bold text-green-600">
                                                    Inventario Cuadrado
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Sin diferencias
                                                </p>
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <XCircle className="h-8 w-8 text-red-500" />
                                            <div>
                                                <p className="font-bold text-red-600">
                                                    Diferencia Detectada
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {Number(
                                                        cierre.difference || 0,
                                                    ) > 0
                                                        ? `Sobran ${Number(cierre.difference || 0).toLocaleString()} unidades`
                                                        : `Faltan ${Math.abs(Number(cierre.difference || 0)).toLocaleString()} unidades`}
                                                </p>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
