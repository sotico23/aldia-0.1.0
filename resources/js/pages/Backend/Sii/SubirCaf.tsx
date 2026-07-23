import { Head, useForm } from '@inertiajs/react';
import { Upload, FileText, AlertCircle, CheckCircle2 } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación', href: '/facturacion' },
    { title: 'SII – DTE', href: '/sii' },
    { title: 'Subir CAF', href: '/sii/caf/subir' },
];

export default function SubirCaf() {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('finanzas.sii.viewAny');
    const fileRef = useRef<HTMLInputElement>(null);

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { data, setData, post, processing, errors, wasSuccessful, reset } = useForm<{
        archivo_caf: File | null;
    }>({ archivo_caf: null });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setData('archivo_caf', file);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/sii/caf', {
            forceFormData: true,
            onSuccess: () => {
                reset();
                if (fileRef.current) fileRef.current.value = '';
            },
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
        <>
            <Head title="Subir CAF – SII" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="mx-auto max-w-2xl p-4 md:p-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Upload className="h-5 w-5" />
                                Subir Código de Autorización de Folios (CAF)
                            </CardTitle>
                            <CardDescription>
                                Descarga el archivo XML del CAF desde el portal web del SII
                                (maullin para certificación) y súbelo aquí para habilitarlo
                                en el sistema.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            {/* Instrucciones */}
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                <p className="mb-2 font-semibold">¿Cómo obtener el CAF?</p>
                                <ol className="list-inside list-decimal space-y-1">
                                    <li>
                                        Ingresa al portal:{' '}
                                        <a
                                            href="https://maullin.sii.cl"
                                            target="_blank"
                                            rel="noreferrer"
                                            className="underline"
                                        >
                                            maullin.sii.cl
                                        </a>{' '}
                                        (certificación)
                                    </li>
                                    <li>
                                        Ve a <strong>Boleta/Factura Electrónica → Obtención
                                        de Folios</strong>
                                    </li>
                                    <li>Selecciona el tipo de documento (ej. 33 – Factura)</li>
                                    <li>Descarga el archivo XML del CAF</li>
                                    <li>Súbelo en el formulario de abajo</li>
                                </ol>
                            </div>

                            {/* Formulario */}
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="archivo_caf">
                                        Archivo XML del CAF
                                    </Label>

                                    {/* Drop zone visual */}
                                    <label
                                        htmlFor="archivo_caf"
                                        className={`flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-10 transition-colors ${
                                            data.archivo_caf
                                                ? 'border-green-400 bg-green-50'
                                                : 'border-muted-foreground/30 hover:border-primary/50 hover:bg-muted/30'
                                        }`}
                                    >
                                        {data.archivo_caf ? (
                                            <>
                                                <CheckCircle2 className="h-10 w-10 text-green-500" />
                                                <div className="text-center">
                                                    <p className="font-semibold text-green-700">
                                                        {data.archivo_caf.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {(data.archivo_caf.size / 1024).toFixed(1)} KB
                                                    </p>
                                                </div>
                                            </>
                                        ) : (
                                            <>
                                                <FileText className="h-10 w-10 text-muted-foreground" />
                                                <div className="text-center">
                                                    <p className="text-sm font-medium">
                                                        Haz clic para seleccionar el archivo CAF
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Solo archivos .xml (máximo 512 KB)
                                                    </p>
                                                </div>
                                            </>
                                        )}
                                        <input
                                            ref={fileRef}
                                            id="archivo_caf"
                                            type="file"
                                            accept=".xml,text/xml"
                                            className="sr-only"
                                            onChange={handleFileChange}
                                        />
                                    </label>

                                    {errors.archivo_caf && (
                                        <div className="flex items-center gap-2 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                            <AlertCircle className="h-4 w-4 shrink-0" />
                                            {errors.archivo_caf}
                                        </div>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing || !data.archivo_caf}
                                    className="w-full"
                                >
                                    {processing ? (
                                        <>
                                            <Upload className="mr-2 h-4 w-4 animate-bounce" />
                                            Importando…
                                        </>
                                    ) : (
                                        <>
                                            <Upload className="mr-2 h-4 w-4" />
                                            Importar CAF
                                        </>
                                    )}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        </>
    );
}
