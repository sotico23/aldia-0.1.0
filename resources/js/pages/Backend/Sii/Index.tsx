import { Head, Link } from '@inertiajs/react';
import {
    Upload,
    FileCheck2,
    FileX2,
    AlertTriangle,
    ChevronRight,
    ListOrdered,
    List,
    LayoutGrid,
    RefreshCw,
    Hash,
    Settings,
} from 'lucide-react';
import { useState } from 'react';
import '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facturación', href: '/facturacion' },
    { title: 'SII – DTE', href: '/sii' },
];

interface CafFolio {
    id: number;
    tipo_documento: number;
    folio_desde: number;
    folio_hasta: number;
    folio_siguiente: number;
    fecha_vencimiento: string | null;
    agotado: boolean;
    ambiente: string;
}

interface DteDocumento {
    id: number;
    tipo_documento: number;
    folio: number;
    rut_receptor: string;
    razon_social_receptor: string;
    monto_total: number;
    estado: string;
    created_at: string;
}

const tipoDocLabel: Record<number, string> = {
    33: 'Factura Electrónica',
    34: 'Factura Exenta',
    39: 'Boleta Electrónica',
    56: 'Nota de Débito',
    61: 'Nota de Crédito',
};

const estadoDteColor: Record<string, string> = {
    borrador: 'bg-gray-100 text-gray-700',
    firmado: 'bg-blue-100 text-blue-700',
    enviado: 'bg-yellow-100 text-yellow-700',
    aceptado: 'bg-green-100 text-green-700',
    rechazado: 'bg-red-100 text-red-700',
    reparado: 'bg-purple-100 text-purple-700',
};

export default function SiiIndex({
    cafs,
    documentos,
    ambiente,
}: {
    cafs: CafFolio[];
    documentos: DteDocumento[];
    ambiente: string;
}) {
    const { hasPermission } = usePermissions();
    const { code: countryCode, currency } = useCountry();
    const canAccess = hasPermission('finanzas.sii.viewAny');
    const cafVigentes = cafs.filter((c) => !c.agotado);
    const cafAgotados = cafs.filter((c) => c.agotado);

    const foliosDisponibles = cafVigentes.reduce(
        (acc, c) => acc + (c.folio_hasta - c.folio_siguiente + 1),
        0,
    );
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

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
            <Head title="SII – Facturación Electrónica" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-6 p-4 md:p-6">
                    {/* Header */}
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                SII – Facturación Electrónica
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                Ambiente:{' '}
                                <span
                                    className={
                                        ambiente === 'produccion'
                                            ? 'font-semibold text-green-600'
                                            : 'font-semibold text-amber-600'
                                    }
                                >
                                    {ambiente === 'produccion'
                                        ? '🟢 Producción'
                                        : '🟡 Certificación (pruebas)'}
                                </span>
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link href="/sii/configuracion">
                                <Button variant="outline" size="sm">
                                    <Settings className="mr-2 h-4 w-4" />
                                    Configuración
                                </Button>
                            </Link>
                            <Link href="/sii/documentos">
                                <Button variant="outline" size="sm">
                                    <ListOrdered className="mr-2 h-4 w-4" />
                                    Ver DTEs
                                </Button>
                            </Link>
                            <Link href="/sii/caf/subir">
                                <Button size="sm">
                                    <Upload className="mr-2 h-4 w-4" />
                                    Subir CAF
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Resumen stats */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            label="CAFs Vigentes"
                            value={cafVigentes.length}
                            icon={<FileCheck2 className="h-5 w-5 text-green-500" />}
                            sub="con folios disponibles"
                        />
                        <StatCard
                            label="Folios Disponibles"
                            value={foliosDisponibles}
                            icon={<Hash className="h-5 w-5 text-blue-500" />}
                            sub="en todos los CAFs activos"
                        />
                        <StatCard
                            label="CAFs Agotados"
                            value={cafAgotados.length}
                            icon={<FileX2 className="h-5 w-5 text-red-400" />}
                            sub="sin folios restantes"
                        />
                        <StatCard
                            label="Docs. Emitidos"
                            value={documentos.length}
                            icon={<RefreshCw className="h-5 w-5 text-purple-500" />}
                            sub="últimos registros"
                        />
                    </div>

                    {/* Tabla CAFs */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Códigos de Autorización de Folios (CAF)</CardTitle>
                                    <CardDescription>
                                        {cafs.length === 0
                                            ? 'Aún no has subido ningún CAF.'
                                            : `${cafs.length} CAF(s) registrados`}
                                    </CardDescription>
                                </div>
                                {cafs.length > 0 && (
                                    <div className="flex items-center gap-1 rounded-lg border p-1">
                                        <Button
                                            variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                            size="sm"
                                            onClick={() => setViewMode('table')}
                                        >
                                            <List className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                            size="sm"
                                            onClick={() => setViewMode('cards')}
                                        >
                                            <LayoutGrid className="h-4 w-4" />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {cafs.length === 0 ? (
                                <div className="flex flex-col items-center gap-3 py-12 text-center text-muted-foreground">
                                    <AlertTriangle className="h-10 w-10 text-amber-400" />
                                    <p className="text-sm">
                                        Sin CAF no puedes emitir DTEs. Descarga el archivo XML
                                        desde el portal del SII y súbelo aquí.
                                    </p>
                                    <Link href="/sii/caf/subir">
                                        <Button size="sm">
                                            <Upload className="mr-2 h-4 w-4" />
                                            Subir primer CAF
                                        </Button>
                                    </Link>
                                </div>
                            ) : viewMode === 'table' ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="px-3 py-2 font-medium">Tipo Doc.</th>
                                                <th className="px-3 py-2 font-medium">Desde</th>
                                                <th className="px-3 py-2 font-medium">Hasta</th>
                                                <th className="px-3 py-2 font-medium">Siguiente</th>
                                                <th className="px-3 py-2 font-medium">Vencimiento</th>
                                                <th className="px-3 py-2 font-medium">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {cafs.map((caf) => (
                                                <tr
                                                    key={caf.id}
                                                    className="border-b transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="px-3 py-2 font-medium">
                                                        {tipoDocLabel[caf.tipo_documento] ??
                                                            `Tipo ${caf.tipo_documento}`}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono">
                                                        {caf.folio_desde}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono">
                                                        {caf.folio_hasta}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono font-semibold text-blue-600">
                                                        {caf.folio_siguiente}
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {caf.fecha_vencimiento ?? '—'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {caf.agotado ? (
                                                            <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-600">
                                                                Agotado
                                                            </span>
                                                        ) : (
                                                            <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-600">
                                                                Vigente
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {cafs.map((caf) => (
                                        <Card key={caf.id}>
                                            <CardContent className="p-4">
                                                <div className="mb-2 flex items-center justify-between">
                                                    <h3 className="font-semibold text-sm">
                                                        {tipoDocLabel[caf.tipo_documento] ?? `Tipo ${caf.tipo_documento}`}
                                                    </h3>
                                                    {caf.agotado ? (
                                                        <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-600">Agotado</span>
                                                    ) : (
                                                        <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-600">Vigente</span>
                                                    )}
                                                </div>
                                                <div className="space-y-1 text-sm">
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Desde</span>
                                                        <span className="font-mono">{caf.folio_desde}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Hasta</span>
                                                        <span className="font-mono">{caf.folio_hasta}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Siguiente</span>
                                                        <span className="font-mono font-semibold text-blue-600">{caf.folio_siguiente}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Vencimiento</span>
                                                        <span>{caf.fecha_vencimiento ?? '—'}</span>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Documentos recientes */}
                    {documentos.length > 0 && (
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>DTEs Recientes</CardTitle>
                                    <CardDescription>Últimos 20 documentos emitidos</CardDescription>
                                </div>
                                <Link href="/sii/documentos">
                                    <Button variant="ghost" size="sm">
                                        Ver todos
                                        <ChevronRight className="ml-1 h-4 w-4" />
                                    </Button>
                                </Link>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="px-3 py-2 font-medium">Tipo</th>
                                                <th className="px-3 py-2 font-medium">Folio</th>
                                                <th className="px-3 py-2 font-medium">Receptor</th>
                                                <th className="px-3 py-2 text-right font-medium">
                                                    Total
                                                </th>
                                                <th className="px-3 py-2 font-medium">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {documentos.map((doc) => (
                                                <tr
                                                    key={doc.id}
                                                    className="border-b transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="px-3 py-2">
                                                        {tipoDocLabel[doc.tipo_documento] ??
                                                            `Tipo ${doc.tipo_documento}`}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono">
                                                        {doc.folio}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <div className="font-medium">
                                                            {doc.razon_social_receptor}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {doc.rut_receptor}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-bold text-green-600">
                                                        ${doc.monto_total.toLocaleString(currency.locale)}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <span
                                                            className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${estadoDteColor[doc.estado] ?? 'bg-gray-100 text-gray-700'}`}
                                                        >
                                                            {doc.estado}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </AppLayout>
        </>
    );
}

function StatCard({
    label,
    value,
    icon,
    sub,
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
    sub: string;
}) {
    const { currency } = useCountry();

    return (
        <Card>
            <CardContent className="pt-5">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    {icon}
                </div>
                <p className="mt-2 text-3xl font-bold">{value.toLocaleString(currency.locale)}</p>
                <p className="mt-1 text-xs text-muted-foreground">{sub}</p>
            </CardContent>
        </Card>
    );
}
