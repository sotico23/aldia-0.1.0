import { Head, useForm } from '@inertiajs/react';
import { Building2, MapPin } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

interface Props {
    config: any;
}

export default function Emisor({ config }: Props) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('finanzas.sii.viewAny');
    const { data, setData, post, processing, errors } = useForm({
        rut: config?.rut || '',
        razon_social: config?.razon_social || '',
        giro: config?.giro || '',
        acteco: config?.acteco || '',
        direccion: config?.direccion || '',
        comuna: config?.comuna || '',
        ciudad: config?.ciudad || '',
        resolucion_numero: config?.resolucion_numero || '',
        resolucion_fecha: config?.resolucion_fecha || '',
    });

    const breadcrumbs = [
        { title: 'SII', href: '/sii' },
        { title: 'Configuración', href: '#' },
        { title: 'Datos del Emisor', href: '/sii/configuracion/emisor' },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/sii/configuracion/emisor', {
            onSuccess: () => toast.success('Datos del emisor actualizados correctamente'),
            onError: () => toast.error('Error al actualizar los datos'),
        });
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
            <Head title="Datos del Emisor - SII" />
            
            <div className="mx-auto max-w-5xl p-4 sm:p-6 md:p-8">
                <div className="mb-8 flex items-center gap-4">
                    <div className="rounded-2xl bg-indigo-500/10 p-3">
                        <Building2 className="h-8 w-8 text-indigo-600" />
                    </div>
                    <div>
                        <h1 className="text-3xl font-black tracking-tight">Datos del Emisor</h1>
                        <p className="text-muted-foreground">Información tributaria reglamentaria según el SII.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-8">
                    <Card className="border-none shadow-xl">
                        <CardHeader className="bg-indigo-500/5">
                            <CardTitle className="text-lg">Información Legal</CardTitle>
                            <CardDescription>Datos básicos de la empresa emisora.</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6">
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="rut">RUT Empresa</Label>
                                    <Input
                                        id="rut"
                                        value={data.rut}
                                        onChange={(e) => setData('rut', e.target.value)}
                                        placeholder="77.261.280-K"
                                    />
                                    {errors.rut && <p className="text-xs font-bold text-rose-500">{errors.rut}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="razon_social">Razón Social</Label>
                                    <Input
                                        id="razon_social"
                                        value={data.razon_social}
                                        onChange={(e) => setData('razon_social', e.target.value)}
                                        placeholder="Empresa Spa"
                                    />
                                    {errors.razon_social && <p className="text-xs font-bold text-rose-500">{errors.razon_social}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="giro">Giro Comercial</Label>
                                    <Input
                                        id="giro"
                                        value={data.giro}
                                        onChange={(e) => setData('giro', e.target.value)}
                                        placeholder="Servicios informáticos"
                                    />
                                    {errors.giro && <p className="text-xs font-bold text-rose-500">{errors.giro}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="acteco">Código Actividad Económica (ACTECO)</Label>
                                    <Input
                                        id="acteco"
                                        type="number"
                                        value={data.acteco}
                                        onChange={(e) => setData('acteco', e.target.value)}
                                        placeholder="620101"
                                    />
                                    {errors.acteco && <p className="text-xs font-bold text-rose-500">{errors.acteco}</p>}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-xl">
                        <CardHeader className="bg-amber-500/5">
                            <CardTitle className="text-lg">Ubicación y Resolución</CardTitle>
                            <CardDescription>Dirección tributaria y datos de la resolución del SII.</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6">
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="direccion">Dirección Tributaria</Label>
                                    <div className="relative">
                                        <MapPin className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="direccion"
                                            value={data.direccion}
                                            onChange={(e) => setData('direccion', e.target.value)}
                                            className="pl-10"
                                            placeholder="Avenida Siempre Viva 123"
                                        />
                                    </div>
                                    {errors.direccion && <p className="text-xs font-bold text-rose-500">{errors.direccion}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="comuna">Comuna</Label>
                                    <Input
                                        id="comuna"
                                        value={data.comuna}
                                        onChange={(e) => setData('comuna', e.target.value)}
                                        placeholder="Santiago"
                                    />
                                    {errors.comuna && <p className="text-xs font-bold text-rose-500">{errors.comuna}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="ciudad">Ciudad</Label>
                                    <Input
                                        id="ciudad"
                                        value={data.ciudad}
                                        onChange={(e) => setData('ciudad', e.target.value)}
                                        placeholder="Santiago"
                                    />
                                    {errors.ciudad && <p className="text-xs font-bold text-rose-500">{errors.ciudad}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="resolucion_numero">Número de Resolución</Label>
                                    <Input
                                        id="resolucion_numero"
                                        type="number"
                                        value={data.resolucion_numero}
                                        onChange={(e) => setData('resolucion_numero', e.target.value)}
                                        placeholder="0"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="resolucion_fecha">Fecha de Resolución</Label>
                                    <Input
                                        id="resolucion_fecha"
                                        type="date"
                                        value={data.resolucion_fecha}
                                        onChange={(e) => setData('resolucion_fecha', e.target.value)}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end pt-4">
                        <Button type="submit" disabled={processing} size="lg" className="px-12 font-black shadow-xl transition-all hover:scale-105 active:scale-95">
                            {processing ? 'Guardando...' : 'Guardar Información'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
