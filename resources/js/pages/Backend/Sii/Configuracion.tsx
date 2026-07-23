import { Head, useForm } from '@inertiajs/react';
import { Save, AlertCircle, Building2, MapPin, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación', href: '/facturacion' },
    { title: 'SII – DTE', href: '/sii' },
    { title: 'Configuración Emisor', href: '/sii/configuracion' },
];

interface ConfigSii {
    rut: string;
    razon_social: string;
    giro: string;
    acteco: number;
    direccion: string;
    comuna: string;
    ciudad: string;
    resolucion_numero: number | null;
    resolucion_fecha: string | null;
    ambiente: string;
    email_dte: string | null;
    telefono: string | null;
}

export default function ConfiguracionEmisor({ config }: { config: ConfigSii | null }) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('finanzas.sii.viewAny');
    const { data, setData, post, processing, errors } = useForm({
        rut: config?.rut || '',
        razon_social: config?.razon_social || '',
        giro: config?.giro || '',
        acteco: config?.acteco || 0,
        direccion: config?.direccion || '',
        comuna: config?.comuna || '',
        ciudad: config?.ciudad || '',
        resolucion_numero: config?.resolucion_numero || null,
        resolucion_fecha: config?.resolucion_fecha || '',
        ambiente: config?.ambiente || 'certificacion',
        email_dte: config?.email_dte || '',
        telefono: config?.telefono || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/sii/configuracion');
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
        <>
            <Head title="Configuración Emisor – SII" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="mx-auto max-w-4xl p-4 md:p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Datos de Empresa */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="h-5 w-5" />
                                    Datos Legales del Emisor
                                </CardTitle>
                                <CardDescription>
                                    Información que aparecerá en el encabezado de cada DTE emitido.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="rut">RUT Empresa</Label>
                                    <Input
                                        id="rut"
                                        placeholder="Ej: 76.123.456-7"
                                        value={data.rut}
                                        onChange={(e) => setData('rut', e.target.value)}
                                    />
                                    {errors.rut && <p className="text-xs text-red-500">{errors.rut}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="razon_social">Razón Social</Label>
                                    <Input
                                        id="razon_social"
                                        value={data.razon_social}
                                        onChange={(e) => setData('razon_social', e.target.value)}
                                    />
                                    {errors.razon_social && <p className="text-xs text-red-500">{errors.razon_social}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="giro">Giro</Label>
                                    <Input
                                        id="giro"
                                        value={data.giro}
                                        onChange={(e) => setData('giro', e.target.value)}
                                    />
                                    {errors.giro && <p className="text-xs text-red-500">{errors.giro}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="acteco">Código Actividad (ACTECO)</Label>
                                    <Input
                                        id="acteco"
                                        type="number"
                                        value={data.acteco}
                                        onChange={(e) => setData('acteco', parseInt(e.target.value))}
                                    />
                                    {errors.acteco && <p className="text-xs text-red-500">{errors.acteco}</p>}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Ubicación */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MapPin className="h-5 w-5" />
                                    Dirección Casa Matriz
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="sm:col-span-1 space-y-2">
                                    <Label htmlFor="direccion">Dirección</Label>
                                    <Input
                                        id="direccion"
                                        value={data.direccion}
                                        onChange={(e) => setData('direccion', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="comuna">Comuna</Label>
                                    <Input
                                        id="comuna"
                                        value={data.comuna}
                                        onChange={(e) => setData('comuna', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="ciudad">Ciudad</Label>
                                    <Input
                                        id="ciudad"
                                        value={data.ciudad}
                                        onChange={(e) => setData('ciudad', e.target.value)}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Resolución */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    Resolución SII
                                </CardTitle>
                                <CardDescription>
                                    Datos de la resolución que autoriza el software.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="res_num">N° Resolución</Label>
                                    <Input
                                        id="res_num"
                                        type="number"
                                        value={data.resolucion_numero || ''}
                                        onChange={(e) => setData('resolucion_numero', e.target.value ? parseInt(e.target.value) : null)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="res_fch">Fecha Resolución</Label>
                                    <Input
                                        id="res_fch"
                                        type="date"
                                        value={data.resolucion_fecha || ''}
                                        onChange={(e) => setData('resolucion_fecha', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="ambiente">Ambiente DTE</Label>
                                    <Select
                                        value={data.ambiente}
                                        onValueChange={(v) => setData('ambiente', v)}
                                    >
                                        <SelectTrigger id="ambiente">
                                            <SelectValue placeholder="Selecciona ambiente" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="certificacion">Certificación (Pruebas)</SelectItem>
                                            <SelectItem value="produccion">Producción (Real)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <div className="flex gap-3">
                                <AlertCircle className="h-5 w-5 text-amber-500" />
                                <p className="text-sm text-amber-800">
                                    Asegúrate de que el RUT y ACTECO coincidan exactamente con lo registrado en el portal del SII.
                                </p>
                            </div>
                            <Button type="submit" disabled={processing} className="min-w-[150px]">
                                <Save className="mr-2 h-4 w-4" />
                                {processing ? 'Guardando...' : 'Guardar Cambios'}
                            </Button>
                        </div>
                    </form>
                </div>
            </AppLayout>
        </>
    );
}
