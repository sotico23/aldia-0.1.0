import { Head, Link } from '@inertiajs/react';
import { Ticket, Plus, AlertCircle, CheckCircle2, History, Info } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

interface Caf {
    id: number;
    tipo: number;
    desde: number;
    hasta: number;
    disponibles: number;
    created_at: string;
}

interface Props {
    cafs: Caf[];
}

const getTipoNombre = (tipo: number) => {
    switch(tipo) {
        case 33: return 'Factura Electrónica';
        case 34: return 'Factura Exenta';
        case 61: return 'Nota de Crédito';
        case 56: return 'Nota de Débito';
        case 52: return 'Guía de Despacho';
        case 39: return 'Boleta Electrónica';
        case 41: return 'Boleta Exenta';
        default: return `DTE ${tipo}`;
    }
};

export default function Folios({ cafs }: Props) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('finanzas.sii.viewAny');
    const breadcrumbs = [
        { title: 'SII', href: '/sii' },
        { title: 'Configuración', href: '#' },
        { title: 'Gestión de Folios', href: '/sii/configuracion/folios' },
    ];

    const totalDisponibles = cafs.reduce((acc, caf) => acc + (caf.disponibles || 0), 0);
    const lowStock = cafs.some(caf => caf.disponibles < 10);

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
            <Head title="Gestión de Folios (CAF) - SII" />
            
            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-4">
                        <div className="rounded-2xl bg-amber-500/10 p-3">
                            <Ticket className="h-8 w-8 text-amber-600" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black tracking-tight">Gestión de Folios (CAF)</h1>
                            <p className="text-muted-foreground">Administra tus autorizaciones de folios para cada tipo de documento.</p>
                        </div>
                    </div>
                    <Button asChild className="font-bold shadow-lg shadow-primary/20">
                        <Link href="/sii/caf/subir">
                            <Plus className="mr-2 h-4 w-4" />
                            Cargar Nuevo CAF
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                    <Card className="border-none bg-zinc-900 text-white shadow-2xl md:col-span-1">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-bold uppercase tracking-widest text-zinc-400">Total Disponible</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-5xl font-black">{totalDisponibles}</p>
                            <p className="text-xs mt-2 text-zinc-500">Folios autorizados en total</p>
                        </CardContent>
                    </Card>

                    <Card className={`border-none shadow-xl md:col-span-3 ${lowStock ? 'bg-rose-50' : 'bg-emerald-50'}`}>
                        <CardHeader className="pb-2">
                            <div className="flex items-center gap-2">
                                {lowStock ? <AlertCircle className="h-5 w-5 text-rose-500" /> : <CheckCircle2 className="h-5 w-5 text-emerald-500" />}
                                <CardTitle className={`text-sm font-bold uppercase tracking-widest ${lowStock ? 'text-rose-600' : 'text-emerald-600'}`}>
                                    Estado del Stock
                                </CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p className="text-md font-medium">
                                {lowStock 
                                    ? 'Atención: Tienes algunos tipos de documentos con menos de 10 folios disponibles.' 
                                    : 'Todo en orden: Cuentas con suficientes folios para tu operación diaria.'}
                            </p>
                            <div className="mt-4 flex gap-2">
                                <Badge variant={lowStock ? 'destructive' : 'default'} className="rounded-md">
                                    {lowStock ? 'Acción Requerida' : 'Operativo'}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="mt-8">
                    <div className="flex items-center gap-2 mb-4">
                        <History className="h-5 w-5 text-muted-foreground" />
                        <h2 className="text-xl font-black">Folios Autorizados pory Tipo</h2>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {cafs.length > 0 ? (
                            cafs.map((caf) => (
                                <Card key={caf.id} className="relative overflow-hidden border-none shadow-md transition-all hover:shadow-lg">
                                    <div className="absolute top-0 left-0 h-full w-1 bg-amber-500" />
                                    <CardHeader className="pb-2">
                                        <div className="flex justify-between items-start">
                                            <Badge variant="outline" className="bg-amber-50">DTE {caf.tipo}</Badge>
                                            <span className="text-[10px] text-muted-foreground">{new Date(caf.created_at).toLocaleDateString()}</span>
                                        </div>
                                        <CardTitle className="text-lg pt-1">{getTipoNombre(caf.tipo)}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex items-end justify-between">
                                            <div>
                                                <p className="text-xs text-muted-foreground">Rango: {caf.desde} al {caf.hasta}</p>
                                                <div className="mt-1 h-2 w-32 rounded-full bg-zinc-100 overflow-hidden">
                                                    <div 
                                                        className={`h-full ${caf.disponibles < 10 ? 'bg-rose-500' : 'bg-emerald-500'}`}
                                                        style={{ width: `${Math.min(100, (caf.disponibles / (caf.hasta - caf.desde + 1)) * 100)}%` }}
                                                    />
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className={`text-2xl font-black ${caf.disponibles < 10 ? 'text-rose-500' : 'text-zinc-900 group-hover:text-amber-600'}`}>
                                                    {caf.disponibles}
                                                </p>
                                                <p className="text-[10px] uppercase font-bold text-muted-foreground">Restantes</p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        ) : (
                            <div className="col-span-full py-12 text-center rounded-3xl border-2 border-dashed border-zinc-200">
                                <Info className="mx-auto h-12 w-12 text-zinc-300" />
                                <h3 className="mt-4 text-lg font-bold">No hay folios cargados</h3>
                                <p className="text-muted-foreground">Sube tu primer archivo CAF para comenzar a emitir.</p>
                                <Button asChild variant="outline" className="mt-6">
                                    <Link href="/sii/caf/subir">Cargar CAF</Link>
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
