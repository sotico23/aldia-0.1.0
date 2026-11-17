import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, Loader2, Play, Terminal, XCircle } from 'lucide-react';
import { useState } from 'react';
import {
    execute,
    index,
} from '@/actions/App/Http/Controllers/Backend/MaintenanceController';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/Backend/WebSettingController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';

type Command = {
    key: string;
    label: string;
    description: string;
    group: string;
    confirm: boolean;
};
type Result = {
    success: boolean;
    command: string;
    output: string;
    exit_code: number;
    executed_at: string;
};
type Props = {
    commands: Command[];
    status: {
        routes_cached: boolean;
        config_cached: boolean;
        road_alerts_available: boolean;
    };
};

export default function Maintenance({ commands, status }: Props) {
    const [pending, setPending] = useState<Command | null>(null);
    const [running, setRunning] = useState<string | null>(null);
    const [result, setResult] = useState<Result | null>(null);
    const [error, setError] = useState<string | null>(null);
    const groups = [...new Set(commands.map((command) => command.group))];

    async function run(command: Command) {
        if (running) return;
        setPending(null);
        setRunning(command.key);
        setResult(null);
        setError(null);
        try {
            const response = await axios.post<Result>(execute.url(), {
                command: command.key,
                confirmed: true,
            });
            setResult(response.data);
            router.reload({ only: ['status'] });
        } catch (exception) {
            setError(
                axios.isAxiosError(exception)
                    ? (exception.response?.data?.message ??
                          'No se recibió respuesta. Comprueba el estado antes de repetir la tarea.')
                    : 'No se pudo ejecutar la tarea.',
            );
        } finally {
            setRunning(null);
        }
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Configuración Web', href: settingsIndex.url() },
                { title: 'Mantenimiento', href: index.url() },
            ]}
        >
            <Head title="Mantenimiento del servidor" />
            <div className="mx-auto flex w-full max-w-6xl min-w-0 flex-col gap-4 p-3 [overflow-wrap:anywhere] sm:gap-6 sm:p-6">
                <div className="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 flex-1">
                        <h1 className="text-xl font-bold tracking-tight sm:text-2xl">
                            Mantenimiento del servidor
                        </h1>
                        <p className="mt-1 text-muted-foreground">
                            Ejecuta tareas de Artisan y consulta su resultado
                            desde el panel.
                        </p>
                    </div>
                    <Button
                        className="h-auto min-h-10 w-full shrink-0 whitespace-normal sm:w-auto"
                        variant="outline"
                        asChild
                    >
                        <Link href={settingsIndex()}>
                            Volver a Configuración Web
                        </Link>
                    </Button>
                </div>
                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Estado actual</CardTitle>
                        <CardDescription>
                            Las tareas utilizan el código que ya está instalado.
                            Para incorporar funciones nuevas primero hay que
                            desplegar su actualización.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid min-w-0 grid-cols-1 gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
                        <p>
                            Rutas:{' '}
                            <strong>
                                {status.routes_cached
                                    ? 'Compiladas'
                                    : 'Sin caché'}
                            </strong>
                        </p>
                        <p>
                            Configuración:{' '}
                            <strong>
                                {status.config_cached
                                    ? 'Compilada'
                                    : 'Sin caché'}
                            </strong>
                        </p>
                        <p>
                            Alertas de la APK:{' '}
                            <strong>
                                {status.road_alerts_available
                                    ? 'Ruta disponible'
                                    : 'Falta desplegar la ruta'}
                            </strong>
                        </p>
                    </CardContent>
                </Card>
                {running && (
                    <p
                        role="status"
                        className="flex min-w-0 items-start gap-2 rounded-lg border p-4 text-sm"
                    >
                        <Loader2 className="mt-0.5 size-4 shrink-0 animate-spin" />
                        <span className="min-w-0">
                            Ejecutando {running}. Espera el resultado antes de
                            iniciar otra tarea.
                        </span>
                    </p>
                )}
                {error && (
                    <p
                        role="alert"
                        className="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}
                {result && (
                    <Card className="min-w-0" aria-live="polite">
                        <CardHeader>
                            <CardTitle className="flex items-start gap-2 leading-snug">
                                {result.success ? (
                                    <CheckCircle2 className="size-5 shrink-0 text-emerald-600" />
                                ) : (
                                    <XCircle className="size-5 shrink-0 text-destructive" />
                                )}
                                {result.success
                                    ? 'Tarea completada'
                                    : 'Tarea finalizada con error'}
                            </CardTitle>
                            <CardDescription>
                                {result.command} · Código de salida{' '}
                                {result.exit_code} ·{' '}
                                {new Date(result.executed_at).toLocaleString()}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="min-w-0">
                            <pre className="max-h-80 w-full max-w-full min-w-0 overflow-auto rounded-md bg-muted p-4 font-mono text-xs break-all whitespace-pre-wrap">
                                {result.output || 'La tarea no produjo salida.'}
                            </pre>
                        </CardContent>
                    </Card>
                )}
                {groups.map((group) => (
                    <section
                        key={group}
                        className="flex min-w-0 flex-col gap-3"
                    >
                        <h2 className="text-lg font-semibold">{group}</h2>
                        <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
                            {commands
                                .filter((command) => command.group === group)
                                .map((command) => (
                                    <Card
                                        key={command.key}
                                        className="flex min-w-0 flex-col"
                                    >
                                        <CardHeader className="min-w-0 flex-1">
                                            <CardTitle className="text-base leading-snug">
                                                {command.label}
                                            </CardTitle>
                                            <CardDescription>
                                                {command.description}
                                            </CardDescription>
                                            <code className="flex min-w-0 items-start gap-2 pt-2 text-xs text-muted-foreground">
                                                <Terminal className="size-3.5 shrink-0" />
                                                {command.key}
                                            </code>
                                        </CardHeader>
                                        <CardContent>
                                            <Button
                                                className="w-full"
                                                variant="outline"
                                                disabled={running !== null}
                                                onClick={() =>
                                                    command.confirm
                                                        ? setPending(command)
                                                        : void run(command)
                                                }
                                            >
                                                {running === command.key ? (
                                                    <Loader2 className="size-4 animate-spin" />
                                                ) : (
                                                    <Play className="size-4" />
                                                )}
                                                Ejecutar
                                            </Button>
                                        </CardContent>
                                    </Card>
                                ))}
                        </div>
                    </section>
                ))}
                <p className="text-xs text-muted-foreground">
                    Acceso exclusivo para administradores de la plataforma. Cada
                    ejecución registra el usuario, el comando y el resultado en
                    el servidor.
                </p>
            </div>
            <Dialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] min-w-0 gap-5 overflow-y-auto p-5 [overflow-wrap:anywhere] sm:max-w-lg sm:p-6 md:max-w-lg lg:max-w-lg xl:max-w-lg">
                    <DialogHeader className="min-w-0 pr-8 text-left">
                        <DialogTitle className="leading-snug">
                            {pending?.label}
                        </DialogTitle>
                        <DialogDescription>
                            {pending?.description}
                        </DialogDescription>
                    </DialogHeader>
                    <p className="text-sm">
                        Esta tarea se ejecutará en el servidor actual y puede
                        afectar a los usuarios conectados.
                    </p>
                    <DialogFooter className="min-w-0 gap-2">
                        <Button
                            className="h-auto min-h-10 w-full whitespace-normal sm:w-auto"
                            variant="outline"
                            onClick={() => setPending(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            className="h-auto min-h-10 w-full whitespace-normal sm:w-auto"
                            onClick={() => pending && void run(pending)}
                        >
                            Confirmar ejecución
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
