import { Head, useForm } from '@inertiajs/react';
import { Globe, Server, Rocket, AlertTriangle, ShieldCheck, ChevronRight } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

interface Props {
    config: any;
}

export default function Ambiente({ config }: Props) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('finanzas.sii.viewAny');
    const { data, setData, post, processing } = useForm({
        ambiente: config?.ambiente || 'certificacion',
    });

    const breadcrumbs = [
        { title: 'SII', href: '/sii' },
        { title: 'Configuración', href: '#' },
        { title: 'Ambiente de Trabajo', href: '/sii/configuracion/ambiente' },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/sii/configuracion/ambiente', {
            onSuccess: () => toast.success('Ambiente de trabajo actualizado'),
            onError: () => toast.error('Error al cambiar el ambiente'),
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
            <Head title="Ambiente de Trabajo - SII" />
            
            <div className="mx-auto max-w-4xl p-4 sm:p-6 md:p-8">
                <div className="mb-8 flex items-center gap-4">
                    <div className="rounded-2xl bg-violet-500/10 p-3">
                        <Globe className="h-8 w-8 text-violet-600" />
                    </div>
                    <div>
                        <h1 className="text-3xl font-black tracking-tight">Ambiente de Trabajo</h1>
                        <p className="text-muted-foreground">Alterna entre pruebas de certificación y producción oficial.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-8">
                    <Card className="border-none shadow-2xl overflow-hidden">
                        <CardHeader className="bg-gradient-to-r from-violet-500/10 to-transparent">
                            <CardTitle>Selecciona el entorno</CardTitle>
                            <CardDescription>Esta configuración afecta a todos los documentos emitidos desde este momento.</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-8">
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <Label>Ambiente Activo</Label>
                                    <Select value={data.ambiente} onValueChange={(v: string) => setData('ambiente', v)}>
                                        <SelectTrigger className="w-full h-14 text-lg font-bold">
                                            <SelectValue placeholder="Seleccionar ambiente" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="certificacion">
                                                <div className="flex items-center gap-2">
                                                    <Server className="h-4 w-4 text-violet-600" />
                                                    <span>Certificación (Pruebas)</span>
                                                </div>
                                            </SelectItem>
                                            <SelectItem value="produccion">
                                                <div className="flex items-center gap-2">
                                                    <Rocket className="h-4 w-4 text-emerald-600" />
                                                    <span>Producción (Oficial)</span>
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className={`p-6 rounded-2xl border-2 transition-all ${data.ambiente === 'certificacion' ? 'border-violet-600 bg-violet-50/50' : 'border-zinc-100 bg-zinc-50 opacity-60'}`}>
                                        <div className="flex items-center gap-3 mb-4">
                                            <Server className="h-5 w-5 text-violet-600" />
                                            <span className="text-lg font-black uppercase tracking-tighter">Certificación</span>
                                        </div>
                                        <p className="text-xs text-muted-foreground leading-relaxed">
                                            Entorno de pruebas (maullin.sii.cl). Los documentos no tienen validez legal tributaria.
                                        </p>
                                    </div>

                                    <div className={`p-6 rounded-2xl border-2 transition-all ${data.ambiente === 'produccion' ? 'border-emerald-600 bg-emerald-50/50' : 'border-zinc-100 bg-zinc-50 opacity-60'}`}>
                                        <div className="flex items-center gap-3 mb-4">
                                            <Rocket className="h-5 w-5 text-emerald-600" />
                                            <span className="text-lg font-black uppercase tracking-tighter">Producción</span>
                                        </div>
                                        <p className="text-xs text-muted-foreground leading-relaxed">
                                            Entorno oficial del SII. Los documentos emitidos se informan legalmente.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {data.ambiente === 'produccion' && (
                                <div className="mt-8 rounded-2xl border-2 border-dashed border-amber-200 bg-amber-50/50 p-6 flex items-start gap-4">
                                    <AlertTriangle className="h-6 w-6 text-amber-600 shrink-0 mt-1" />
                                    <div>
                                        <h4 className="text-sm font-black text-amber-900 uppercase">Advertencia de Producción</h4>
                                        <p className="text-xs text-amber-800/80 mt-1">
                                            Cambiar a producción significa que tus facturas llegarán directamente al SII. Asegúrate de que el certificado digital y los CAF que cargues sean válidos para este ambiente.
                                        </p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex justify-between items-center bg-zinc-50 rounded-3xl p-4 border border-zinc-100 italic">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <ShieldCheck className="h-4 w-4" />
                            <span>Cambio instantáneo y auditado</span>
                        </div>
                        <Button type="submit" disabled={processing} className="px-8 font-bold shadow-xl">
                            {processing ? 'Actualizando...' : 'Confirmar Cambio de Ambiente'}
                            <ChevronRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
