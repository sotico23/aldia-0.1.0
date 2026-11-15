import { router, useForm, usePage, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    check as checkEngine,
    configure,
    start as startSearch,
    cancel,
    importResults,
} from '@/actions/App/Http/Controllers/Backend/ProspectoScrapingController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Business = {
    id: string;
    name: string;
    phone: string;
    address: string;
    category: string;
    website: string | null;
    maps_url: string;
};
type Run = {
    id: number;
    query: string;
    location: string;
    max_results: number;
    status: string;
    error: string | null;
    results: Business[];
};
export type ScrapingState = {
    health: {
        ready: boolean;
        queue_ready: boolean;
        queue_connection?: string;
        queue_retry_after?: number;
        message: string;
        checks?: Record<string, { ready: boolean; message: string }>;
    };
    binaries: { node: string; chrome: string } | null;
    runs: Run[];
};
const statuses: Record<string, string> = {
    queued: 'En cola',
    running: 'Buscando negocios',
    completed: 'Completada',
    failed: 'No completada',
    blocked: 'Verificación requerida',
    cancelled: 'Cancelada',
};

export default function ScrapingTab({
    state,
    canImport,
    canConfigure,
}: {
    state?: ScrapingState;
    canImport: boolean;
    canConfigure: boolean;
}) {
    const health = state?.health;
    const verification = useForm({});
    const runs = state?.runs ?? [];
    const active = runs.some((run) =>
        ['queued', 'running'].includes(run.status),
    );
    const { start: startPolling, stop: stopPolling } = usePoll(
        5000,
        { only: ['scraping'] },
        { autoStart: false },
    );
    useEffect(() => {
        if (active) startPolling();
        else stopPolling();
        return stopPolling;
    }, [active, startPolling, stopPolling]);
    const [selectedRun, setSelectedRun] = useState<number | null>(null);
    const run = runs.find((run) => run.id === selectedRun) ?? runs[0];
    const search = useForm({
        query: runs[0]?.query ?? '',
        location: runs[0]?.location ?? '',
        max_results: runs[0]?.max_results ?? 10,
    });
    const engine = useForm({
        node_binary: state?.binaries?.node ?? 'node',
        chrome_binary: state?.binaries?.chrome ?? '',
        queue_connection: state?.health.queue_connection ?? 'database',
        queue_retry_after: state?.health.queue_retry_after ?? 300,
    });
    const { flash } = usePage<{
        flash?: { success?: string; warning?: string };
    }>().props;
    const blockingReason = !canImport
        ? 'No tienes permisos para buscar e importar prospectos.'
        : verification.processing
          ? 'Comprobando los requisitos del motor…'
          : !health
            ? 'Esperando la comprobación del motor.'
            : !health.ready || !health.queue_ready
              ? health.message
              : active
                ? 'Ya hay una búsqueda en curso. Espera a que termine o cancélala.'
                : null;
    const verifyEngine = () =>
        verification.post(checkEngine.url(), { preserveScroll: true });

    return (
        <section
            className="min-w-0 space-y-6"
            data-testid="prospectos-scraping"
        >
            <div className="rounded-xl border bg-card p-5">
                <h2 className="text-lg font-semibold">
                    Buscar negocios en Google Maps
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Motor propio, sin proveedor ni API Key. Busca por rubro y
                    zona; revisa los negocios antes de agregarlos al pipeline.
                </p>
                <p
                    className="mt-3 rounded-lg bg-muted p-3 text-sm"
                    data-testid="scraping-health"
                >
                    {health?.message ?? 'Cargando estado del motor…'}
                </p>
                {health?.checks && (
                    <ul
                        className="mt-3 space-y-2 text-sm"
                        data-testid="scraping-checks"
                    >
                        {Object.entries(health.checks).map(([key, check]) => (
                            <li key={key} className="rounded-lg border p-3">
                                <strong>
                                    {key === 'node'
                                        ? 'Node.js'
                                        : key === 'chrome'
                                          ? 'Navegador'
                                          : 'Cola'}
                                    : {check.ready ? 'Disponible' : 'Pendiente'}
                                </strong>
                                <p className="mt-1 text-muted-foreground">
                                    {check.message}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
                <Button
                    type="button"
                    variant="outline"
                    className="mt-3"
                    onClick={verifyEngine}
                    disabled={verification.processing || engine.processing}
                >
                    {verification.processing
                        ? 'Comprobando…'
                        : 'Comprobar estado'}
                </Button>
                {canConfigure && (
                    <details className="mt-4 rounded-lg border p-3">
                        <summary className="cursor-pointer font-medium">
                            Configuración del motor en el servidor
                        </summary>
                        <form
                            className="mt-4 space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                engine.patch(configure.url(), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <p className="text-sm text-muted-foreground">
                                Instala Node.js 22 o superior y Chrome/Chromium
                                en el servidor. El worker debe poder ejecutarlos
                                y tener acceso a Internet. Esta configuración se
                                comparte entre empresas y solo la modifica un
                                administrador.
                            </p>
                            <Label htmlFor="scraping-node">
                                Ejecutable de Node.js
                            </Label>
                            <Input
                                id="scraping-node"
                                value={engine.data.node_binary}
                                onChange={(event) =>
                                    engine.setData(
                                        'node_binary',
                                        event.target.value,
                                    )
                                }
                                placeholder="node"
                            />
                            <InputError message={engine.errors.node_binary} />
                            <Label htmlFor="scraping-chrome">
                                Ejecutable de Chrome / Chromium
                            </Label>
                            <Input
                                id="scraping-chrome"
                                value={engine.data.chrome_binary}
                                onChange={(event) =>
                                    engine.setData(
                                        'chrome_binary',
                                        event.target.value,
                                    )
                                }
                                placeholder="/usr/bin/chromium (vacío para detectar)"
                            />
                            <InputError message={engine.errors.chrome_binary} />
                            <Label htmlFor="scraping-queue">
                                Cola de trabajos (global)
                            </Label>
                            <select
                                id="scraping-queue"
                                className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                value={engine.data.queue_connection}
                                onChange={(event) =>
                                    engine.setData(
                                        'queue_connection',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="database">Base de datos</option>
                                <option value="redis">Redis</option>
                                <option value="sync">
                                    Sincrónica (no admite scraping)
                                </option>
                            </select>
                            <InputError
                                message={engine.errors.queue_connection}
                            />
                            <Label htmlFor="scraping-queue-retry-after">
                                Tiempo de reserva de la cola (segundos)
                            </Label>
                            <Input
                                id="scraping-queue-retry-after"
                                type="number"
                                min={300}
                                max={86400}
                                step={1}
                                value={engine.data.queue_retry_after}
                                onChange={(event) =>
                                    engine.setData(
                                        'queue_retry_after',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <InputError
                                message={engine.errors.queue_retry_after}
                            />
                            <p className="text-sm text-muted-foreground">
                                Usa al menos 300 segundos para que el trabajo
                                no se reserve de nuevo mientras el worker sigue
                                procesándolo. Puedes aumentarlo hasta 24 horas.
                                Mantén un worker dedicado para estas búsquedas.
                                Usa rutas de ejecutables sin argumentos.
                            </p>
                            <code className="block rounded-md bg-muted p-3 text-xs break-all">
                                php artisan queue:work --queue=prospecting
                                --timeout=240 --tries=1
                            </code>
                            <Button type="submit" disabled={engine.processing}>
                                Guardar configuración
                            </Button>
                        </form>
                    </details>
                )}
            </div>
            {(flash?.success || flash?.warning) && (
                <p
                    role="status"
                    className={
                        flash.warning
                            ? 'rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900'
                            : 'rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900'
                    }
                >
                    {flash.warning ?? flash.success}
                </p>
            )}
            <form
                className="grid gap-4 rounded-xl border bg-card p-5 md:grid-cols-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    search.post(startSearch.url(), {
                        preserveScroll: true,
                        onSuccess: () => setSelectedRun(null),
                    });
                }}
            >
                <div className="space-y-2">
                    <Label htmlFor="scraping-query">
                        Rubro o tipo de negocio
                    </Label>
                    <Input
                        id="scraping-query"
                        required
                        maxLength={150}
                        placeholder="Ej.: ferreterías, restaurantes, almacenes"
                        value={search.data.query}
                        onChange={(event) =>
                            search.setData('query', event.target.value)
                        }
                    />
                    <InputError message={search.errors.query} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="scraping-location">
                        Comuna, ciudad y país
                    </Label>
                    <Input
                        id="scraping-location"
                        required
                        maxLength={200}
                        placeholder="Ej.: Maipú, Santiago, Chile"
                        value={search.data.location}
                        onChange={(event) =>
                            search.setData('location', event.target.value)
                        }
                    />
                    <InputError message={search.errors.location} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="scraping-limit">Máximo de negocios</Label>
                    <Input
                        id="scraping-limit"
                        type="number"
                        required
                        min={1}
                        max={20}
                        value={search.data.max_results}
                        onChange={(event) =>
                            search.setData(
                                'max_results',
                                Number(event.target.value),
                            )
                        }
                    />
                    <InputError message={search.errors.max_results} />
                </div>
                <div className="space-y-2 md:col-span-3">
                    <Button
                        type="submit"
                        disabled={Boolean(blockingReason) || search.processing}
                        aria-describedby={
                            blockingReason
                                ? 'scraping-search-blocker'
                                : undefined
                        }
                    >
                        {search.processing ? 'Enviando…' : 'Buscar negocios'}
                    </Button>
                    {blockingReason && (
                        <div
                            id="scraping-search-blocker"
                            role="status"
                            data-testid="scraping-search-blocker"
                            className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
                        >
                            <p>{blockingReason}</p>
                            {!verification.processing && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="mt-2"
                                    onClick={verifyEngine}
                                >
                                    Volver a comprobar requisitos
                                </Button>
                            )}
                        </div>
                    )}
                    <p className="text-sm text-muted-foreground">
                        Se leen nombre, dirección, teléfono y sitio web
                        públicos, cuando están disponibles. Si Google solicita
                        una verificación, la búsqueda se detiene.
                    </p>
                </div>
            </form>
            {runs.length > 0 && (
                <div
                    className="flex flex-wrap gap-2"
                    aria-label="Búsquedas recientes"
                >
                    {runs.map((item) => (
                        <Button
                            key={item.id}
                            type="button"
                            variant={
                                run?.id === item.id ? 'default' : 'outline'
                            }
                            onClick={() => setSelectedRun(item.id)}
                        >
                            #{item.id} {item.query} ·{' '}
                            {statuses[item.status] ?? item.status}
                        </Button>
                    ))}
                </div>
            )}
            {run ? (
                <div className="min-w-0 rounded-xl border bg-card p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="font-semibold">
                                {run.query} · {run.location}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {statuses[run.status] ?? run.status} ·{' '}
                                {run.results.length} negocios encontrados
                            </p>
                        </div>
                        {canImport &&
                            ['queued', 'running'].includes(run.status) && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            cancel.url(run.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Cancelar búsqueda
                                </Button>
                            )}
                    </div>
                    {run.error && (
                        <p
                            role="alert"
                            className="mt-3 text-sm text-destructive"
                        >
                            {run.error}
                        </p>
                    )}
                    {run.status === 'queued' && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Esperando al worker. Si permanece en cola, pide al
                            administrador revisar el proceso de trabajos.
                        </p>
                    )}
                    {run.status === 'completed' && (
                        <Results key={run.id} run={run} canImport={canImport} />
                    )}
                </div>
            ) : (
                <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                    Todavía no hay búsquedas. Configura el motor y elige un
                    rubro y una zona.
                </p>
            )}
        </section>
    );
}

function Results({ run, canImport }: { run: Run; canImport: boolean }) {
    const form = useForm<{ ids: string[] }>({ ids: [] });
    if (!run.results.length)
        return (
            <p className="mt-4 text-sm text-muted-foreground">
                No se encontraron negocios con esos criterios. Prueba un rubro o
                una zona más específicos.
            </p>
        );
    return (
        <form
            className="mt-4 space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(importResults.url(run.id), {
                    preserveScroll: true,
                    onSuccess: () => form.reset(),
                });
            }}
        >
            <Label className="flex items-center gap-2">
                <input
                    type="checkbox"
                    checked={form.data.ids.length === run.results.length}
                    onChange={(event) =>
                        form.setData(
                            'ids',
                            event.target.checked
                                ? run.results.map((row) => row.id)
                                : [],
                        )
                    }
                />
                Seleccionar todos
            </Label>
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b">
                            <th className="p-2">Elegir</th>
                            <th className="p-2">Negocio</th>
                            <th className="p-2">Dirección</th>
                            <th className="p-2">Contacto</th>
                        </tr>
                    </thead>
                    <tbody>
                        {run.results.map((row) => (
                            <tr key={row.id} className="border-b">
                                <td className="p-2">
                                    <input
                                        aria-label={`Seleccionar ${row.name}`}
                                        type="checkbox"
                                        checked={form.data.ids.includes(row.id)}
                                        onChange={(event) =>
                                            form.setData(
                                                'ids',
                                                event.target.checked
                                                    ? [...form.data.ids, row.id]
                                                    : form.data.ids.filter(
                                                          (id) => id !== row.id,
                                                      ),
                                            )
                                        }
                                    />
                                </td>
                                <td className="min-w-40 p-2">
                                    <p className="font-medium">{row.name}</p>
                                    <p className="text-muted-foreground">
                                        {row.category}
                                    </p>
                                    <a
                                        className="text-primary underline"
                                        href={row.maps_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Ver en Google Maps
                                    </a>
                                </td>
                                <td className="min-w-44 p-2">
                                    {row.address || 'Sin dirección pública'}
                                </td>
                                <td className="min-w-36 p-2">
                                    <p>{row.phone || 'Sin teléfono público'}</p>
                                    {row.website && (
                                        <a
                                            className="text-primary underline"
                                            href={row.website}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Sitio web
                                        </a>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <InputError message={form.errors.ids} />
            <Button
                type="submit"
                disabled={
                    !canImport || !form.data.ids.length || form.processing
                }
            >
                Agregar {form.data.ids.length} a Prospectos
            </Button>
            <p className="text-sm text-muted-foreground">
                Se crearán con estado Nuevo y fuente Google Maps. Los negocios
                ya importados en esta empresa se omiten.
            </p>
        </form>
    );
}
