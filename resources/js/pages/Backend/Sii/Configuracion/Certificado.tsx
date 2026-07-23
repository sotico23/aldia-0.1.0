import { Head, useForm } from '@inertiajs/react';
import {
    Shield,
    Key,
    Calendar,
    AlertTriangle,
    CheckCircle2,
    XCircle,
    Loader2,
    FileCheck
} from 'lucide-react';
import { useState } from 'react';
import { useCountry } from '@/hooks/use-country';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
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
import { PasswordInput } from '@/components/ui/password-input';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

interface Props {
    config: any;
}

export default function Certificado({ config }: Props) {
    const { hasPermission } = usePermissions();
    const { code: countryCode, currency } = useCountry();
    const canAccess = hasPermission('finanzas.sii.viewAny');
    const { data, setData, post, processing, errors } = useForm({
        certificado: null as File | null,
        password: '',
    });

    const [testState, setTestState] = useState<{
        status: 'idle' | 'testing' | 'success' | 'error';
        result?: {
            vencimiento?: string;
            subject?: Record<string, string>;
            issuer?: Record<string, string>;
            validFrom?: string;
            serialNumber?: string;
        };
        error?: string;
    }>({ status: 'idle' });

    const breadcrumbs = [
        { title: 'SII', href: '/sii' },
        { title: 'Configuración', href: '#' },
        {
            title: 'Certificado Digital',
            href: '/sii/configuracion/certificado',
        },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/sii/configuracion/certificado');
    };

    const handleProbar = async () => {
        if (!data.certificado || !data.password) {
            toast.error('Selecciona un archivo y escribe la contraseña.');
            return;
        }

        setTestState({ status: 'testing' });

        const formData = new FormData();
        formData.append('certificado', data.certificado);
        formData.append('password', data.password);

        try {
            const res = await fetch('/sii/configuracion/certificado/probar', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
                },
                body: formData,
            });

            const json = await res.json();

            if (json.success) {
                setTestState({ status: 'success', result: json });
                toast.success('Certificado validado correctamente.');
            } else {
                setTestState({ status: 'error', error: json.error });
                toast.error(json.error);
            }
        } catch {
            setTestState({
                status: 'error',
                error: 'Error de conexión al validar el certificado.',
            });
            toast.error('Error de conexión al validar el certificado.');
        }
    };

    const formatSubject = (subject?: Record<string, string>) => {
        if (!subject) return '';
        const parts: string[] = [];
        if (subject.CN) parts.push(subject.CN);
        if (subject.O) parts.push(subject.O);
        if (subject.OU) parts.push(subject.OU);
        return parts.join(' — ');
    };

    const isExpiringSoon =
        (testState.status === 'success'
            ? testState.result?.vencimiento
            : config?.certificado_vencimiento) &&
        new Date(
            testState.status === 'success'
                ? testState.result!.vencimiento!
                : config.certificado_vencimiento,
        ).getTime() -
            new Date().getTime() <
            30 * 24 * 60 * 60 * 1000;

    const displayedVencimiento =
        testState.status === 'success'
            ? testState.result?.vencimiento
            : config?.certificado_vencimiento;

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
            <Head title="Certificado Digital - SII" />

            <div className="mx-auto max-w-5xl px-4 py-8">
                <div className="mb-8 flex items-center gap-4">
                    <div className="rounded-2xl bg-primary/10 p-3">
                        <Shield className="h-8 w-8 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-3xl font-black tracking-tight">
                            Certificado Digital
                        </h1>
                        <p className="text-muted-foreground">
                            Gestiona tu firma electrónica para la emisión de
                            DTEs.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="border-none shadow-xl">
                        <CardHeader className="border-b pb-4">
                            <CardTitle className="flex items-center gap-2">
                                <Key className="h-5 w-5 text-primary" />
                                {config?.certificado_path
                                    ? 'Renovar Certificado'
                                    : 'Subir Certificado'}
                            </CardTitle>
                            <CardDescription>
                                {config?.certificado_path
                                    ? 'Sube un nuevo archivo para reemplazar el actual'
                                    : 'Sube tu archivo .pfx o .p12 proporcionado por el SII'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <form onSubmit={handleSubmit} className="space-y-5">
                                <div className="space-y-2">
                                    <Label htmlFor="certificado">
                                        Archivo del Certificado
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="certificado"
                                            type="file"
                                            accept=".pfx,.p12"
                                            onChange={(e) =>
                                                setData(
                                                    'certificado',
                                                    e.target.files?.[0] || null,
                                                )
                                            }
                                            className="h-12 cursor-pointer file:mr-4 file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20"
                                        />
                                    </div>
                                    {errors.certificado && (
                                        <p className="text-xs font-bold text-rose-500">
                                            {errors.certificado}
                                        </p>
                                    )}
                                    <p className="text-[10px] text-muted-foreground">
                                        Formatos aceptados: .pfx, .p12
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password">
                                        Contraseña del Certificado
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        placeholder="Ingresa la contraseña"
                                    />
                                    {errors.password && (
                                        <p className="text-xs font-bold text-rose-500">
                                            {errors.password}
                                        </p>
                                    )}
                                    <p className="text-[10px] text-muted-foreground italic">
                                        La contraseña se almacena de forma
                                        encriptada.
                                    </p>
                                </div>

                                <div className="flex flex-col gap-3">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={
                                            !data.certificado ||
                                            !data.password ||
                                            testState.status === 'testing'
                                        }
                                        onClick={handleProbar}
                                        className="h-11 w-full font-semibold"
                                    >
                                        {testState.status === 'testing' ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Probando...
                                            </>
                                        ) : (
                                            <>
                                                <FileCheck className="mr-2 h-4 w-4" />
                                                Probar Certificado
                                            </>
                                        )}
                                    </Button>

                                    {testState.status === 'success' && (
                                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                                            <div className="mb-2 flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                                                <CheckCircle2 className="h-4 w-4" />
                                                <span className="text-xs font-black uppercase">
                                                    Certificado Válido
                                                </span>
                                            </div>
                                            <div className="space-y-1 text-xs">
                                                <p className="text-muted-foreground">
                                                    <span className="font-bold">
                                                        Emisor:{' '}
                                                    </span>
                                                    {formatSubject(
                                                        testState.result
                                                            ?.issuer,
                                                    )}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    <span className="font-bold">
                                                        Sujeto:{' '}
                                                    </span>
                                                    {formatSubject(
                                                        testState.result
                                                            ?.subject,
                                                    )}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    <span className="font-bold">
                                                        Vence:{' '}
                                                    </span>
                                                    {testState.result
                                                        ?.vencimiento
                                                        ? new Date(
                                                              testState.result
                                                                  .vencimiento,
                                                          ).toLocaleDateString(
                                                              currency.locale,
                                                              {
                                                                  year: 'numeric',
                                                                  month: 'long',
                                                                  day: 'numeric',
                                                              },
                                                          )
                                                        : 'No detectado'}
                                                </p>
                                            </div>
                                        </div>
                                    )}

                                    {testState.status === 'error' && (
                                        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-950/30">
                                            <div className="mb-1 flex items-center gap-2 text-rose-600 dark:text-rose-400">
                                                <XCircle className="h-4 w-4" />
                                                <span className="text-xs font-black uppercase">
                                                    Certificado Inválido
                                                </span>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {testState.error}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        testState.status === 'testing'
                                    }
                                    className="h-11 w-full font-semibold"
                                >
                                    {processing
                                        ? 'Procesando...'
                                        : config?.certificado_path
                                          ? 'Actualizar Certificado'
                                          : 'Subir Certificado'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card className="border-none bg-primary/5 shadow-xl">
                            <CardHeader>
                                <CardTitle className="text-sm font-black tracking-widest text-primary uppercase">
                                    Estado Actual
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {testState.status === 'success' ? (
                                    <>
                                        <div className="flex items-center gap-3">
                                            <FileCheck className="h-5 w-5 text-emerald-500" />
                                            <div>
                                                <p className="text-sm font-bold">
                                                    Certificado Probado
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    El archivo es válido. Puedes
                                                    subirlo.
                                                </p>
                                            </div>
                                        </div>

                                        <div className="rounded-2xl border border-white/20 bg-white/40 p-4 dark:bg-zinc-800/40">
                                            <div className="mb-2 flex items-center gap-2">
                                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                                <span className="text-xs font-bold text-muted-foreground uppercase">
                                                    Vencimiento
                                                </span>
                                            </div>
                                            <p
                                                className={`text-xl font-black ${isExpiringSoon ? 'text-rose-500' : ''}`}
                                            >
                                                {testState.result?.vencimiento
                                                    ? new Date(
                                                          testState.result
                                                              .vencimiento,
                                                      ).toLocaleDateString(
                                                          currency.locale,
                                                          {
                                                              year: 'numeric',
                                                              month: 'long',
                                                              day: 'numeric',
                                                          },
                                                      )
                                                    : 'No detectado'}
                                            </p>
                                            {isExpiringSoon && (
                                                <Badge
                                                    variant="destructive"
                                                    className="mt-2 animate-pulse text-[10px]"
                                                >
                                                    Próximo a vencer
                                                </Badge>
                                            )}
                                        </div>
                                    </>
                                ) : config?.certificado_path ? (
                                    <>
                                        <div className="flex items-center gap-3">
                                            <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                                            <div>
                                                <p className="text-sm font-bold">
                                                    Certificado Cargado
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Tu firma está lista.
                                                </p>
                                            </div>
                                        </div>

                                        <div className="rounded-2xl border border-white/20 bg-white/40 p-4 dark:bg-zinc-800/40">
                                            <div className="mb-2 flex items-center gap-2">
                                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                                <span className="text-xs font-bold text-muted-foreground uppercase">
                                                    Vencimiento
                                                </span>
                                            </div>
                                            <p
                                                className={`text-xl font-black ${isExpiringSoon ? 'text-rose-500' : ''}`}
                                            >
                                                {displayedVencimiento
                                                    ? new Date(
                                                          displayedVencimiento,
                                                      ).toLocaleDateString(
                                                          currency.locale,
                                                          {
                                                              year: 'numeric',
                                                              month: 'long',
                                                              day: 'numeric',
                                                          },
                                                      )
                                                    : 'No detectado'}
                                            </p>
                                            {isExpiringSoon && (
                                                <Badge
                                                    variant="destructive"
                                                    className="mt-2 animate-pulse text-[10px]"
                                                >
                                                    Próximo a vencer
                                                </Badge>
                                            )}
                                        </div>
                                    </>
                                ) : (
                                    <div className="flex flex-col items-center py-6 text-center">
                                        <AlertTriangle className="mb-2 h-12 w-12 text-amber-500" />
                                        <p className="text-sm font-bold">
                                            Sin Certificado
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Debes subir un certificado para
                                            emitir documentos.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
