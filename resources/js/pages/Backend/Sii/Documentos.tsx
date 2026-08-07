import { Head, router } from '@inertiajs/react';
import {
    FileText,
    X,
    Download,
    Eye,
    Clock,
    AlertTriangle,
    CheckCircle2,
    Ban,
    ListOrdered
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import '@/components/ui/input';
import Pagination from '@/components/ui/Pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'SII', href: '/sii' },
    { title: 'Documentos Emitidos', href: '/sii/documentos' },
];

const TIPO_DOC_LABEL: Record<number, string> = {
    33: 'Factura Electrónica',
    34: 'Factura Exenta',
    39: 'Boleta Electrónica',
    41: 'Boleta Exenta',
    56: 'Nota de Débito',
    61: 'Nota de Crédito',
};

const ESTADO_BADGE: Record<string, { label: string; color: string }> = {
    borrador: { label: 'Borrador', color: 'bg-slate-100 text-slate-700' },
    pendiente: {
        label: 'Pendiente',
        color: 'bg-amber-100 text-amber-700',
    },
    aceptado: {
        label: 'Aceptado',
        color: 'bg-emerald-100 text-emerald-700',
    },
    rechazado: { label: 'Rechazado', color: 'bg-rose-100 text-rose-700' },
    anulado: { label: 'Anulado', color: 'bg-red-100 text-red-700' },
    enviado: {
        label: 'Enviado',
        color: 'bg-blue-100 text-blue-700',
    },
};

const ESTADO_SII_BADGE: Record<string, { label: string; icon: any }> = {
    '0': { label: 'Pendiente', icon: Clock },
    '1': { label: 'Aceptado', icon: CheckCircle2 },
    '2': { label: 'Rechazado', icon: Ban },
    '3': { label: 'Reparo', icon: AlertTriangle },
};

interface DteDocumento {
    id: number;
    tipo_documento: number;
    folio: number;
    rut_emisor: string;
    rut_receptor: string;
    razon_social_receptor: string;
    monto_neto: number;
    monto_iva: number;
    monto_total: number;
    estado: string;
    estado_sii: string;
    track_id: string | null;
    ambiente: string;
    created_at: string;
}

interface Props {
    documentos: { data: DteDocumento[]; links: any[]; meta: any };
    filtros: { estado?: string; tipo?: string };
}

export default function Documentos({ documentos, filtros }: Props) {
    const { hasPermission } = usePermissions();
    const { code: countryCode, currency } = useCountry();
    const canAccess = hasPermission('finanzas.sii.viewAny');
    const [estadoFilter, setEstadoFilter] = useState(filtros.estado || '');
    const [tipoFilter, setTipoFilter] = useState(filtros.tipo || '');

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: Record<string, string> = {};
            if (estadoFilter && estadoFilter !== 'all') query.estado = estadoFilter;
            if (tipoFilter && tipoFilter !== 'all') query.tipo = tipoFilter;

            router.get('/sii/documentos', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 400);
        return () => clearTimeout(timer);
    }, [estadoFilter, tipoFilter]);

    const getEstadoBadge = (estado: string) => {
        const cfg = ESTADO_BADGE[estado] || {
            label: estado,
            color: 'bg-slate-100 text-slate-700',
        };

        return (
            <Badge
                variant="outline"
                className={`${cfg.color} rounded-full border-none text-[10px] font-black uppercase`}
            >
                {cfg.label}
            </Badge>
        );
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
            <Head title="Documentos Emitidos - SII" />

            <div className="mx-auto max-w-7xl p-4 sm:p-6 md:p-8">
                <div className="mb-8 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="rounded-2xl bg-primary/10 p-3">
                            <FileText className="h-8 w-8 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black tracking-tight">
                                Documentos Emitidos
                            </h1>
                            <p className="text-muted-foreground">
                                Historial de Documentos Tributarios Electrónicos
                                emitidos.
                            </p>
                        </div>
                    </div>
                </div>

                <Card className="border-none shadow-xl">
                    <CardHeader className="border-b pb-4">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <CardTitle className="flex items-center gap-2 text-sm font-black uppercase tracking-widest text-muted-foreground">
                                <ListOrdered className="h-4 w-4" />
                                Documentos
                            </CardTitle>

                            <div className="flex flex-col gap-3 md:flex-row md:items-center">
                                <Select
                                    value={tipoFilter}
                                    onValueChange={setTipoFilter}
                                >
                                    <SelectTrigger className="h-10 w-full min-w-[180px] rounded-xl border-none bg-muted/50 text-sm font-semibold md:w-[200px]">
                                        <SelectValue placeholder="Todos los tipos" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos los tipos
                                        </SelectItem>
                                        {Object.entries(TIPO_DOC_LABEL).map(
                                            ([key, label]) => (
                                                <SelectItem
                                                    key={key}
                                                    value={key}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>

                                <Select
                                    value={estadoFilter}
                                    onValueChange={setEstadoFilter}
                                >
                                    <SelectTrigger className="h-10 w-full min-w-[160px] rounded-xl border-none bg-muted/50 text-sm font-semibold md:w-[180px]">
                                        <SelectValue placeholder="Todos los estados" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos los estados
                                        </SelectItem>
                                        {Object.entries(ESTADO_BADGE).map(
                                            ([key, cfg]) => (
                                                <SelectItem
                                                    key={key}
                                                    value={key}
                                                >
                                                    {cfg.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>

                                {(estadoFilter || tipoFilter) && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-10 w-10 rounded-xl"
                                        onClick={() => {
                                            setEstadoFilter('');
                                            setTipoFilter('');
                                        }}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        {documentos.data.length === 0 ? (
                            <div className="flex flex-col items-center py-20 text-center">
                                <FileText className="mb-4 h-16 w-16 text-muted-foreground/30" />
                                <p className="text-lg font-bold text-muted-foreground">
                                    No hay documentos emitidos
                                </p>
                                <p className="text-sm text-muted-foreground/60">
                                    Los documentos generados aparecerán aquí.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b border-muted/50 text-left text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                            <th className="px-6 py-4">
                                                Tipo Doc.
                                            </th>
                                            <th className="px-6 py-4">
                                                Folio
                                            </th>
                                            <th className="px-6 py-4">
                                                Receptor
                                            </th>
                                            <th className="px-6 py-4 text-right">
                                                Monto
                                            </th>
                                            <th className="px-6 py-4 text-center">
                                                Estado
                                            </th>
                                            <th className="px-6 py-4 text-center">
                                                SII
                                            </th>
                                            <th className="px-6 py-4">
                                                Fecha
                                            </th>
                                            <th className="px-6 py-4 text-center">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {documentos.data.map((doc) => {
                                            const siiCfg =
                                                ESTADO_SII_BADGE[
                                                    doc.estado_sii
                                                ];

                                            return (
                                                <tr
                                                    key={doc.id}
                                                    className="border-b border-muted/20 transition-colors hover:bg-muted/20"
                                                >
                                                    <td className="px-6 py-4">
                                                        <span className="text-sm font-bold">
                                                            {TIPO_DOC_LABEL[
                                                                doc
                                                                    .tipo_documento
                                                            ] ||
                                                                `Tipo ${doc.tipo_documento}`}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <span className="text-lg font-black tabular-nums">
                                                            {doc.folio}
                                                        </span>
                                                    </td>
                                                    <td className="max-w-[200px] truncate px-6 py-4">
                                                        <p className="text-sm font-semibold">
                                                            {doc.razon_social_receptor ||
                                                                '—'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {doc.rut_receptor ||
                                                                '—'}
                                                        </p>
                                                    </td>
                                                    <td className="px-6 py-4 text-right">
                                                        <span className="font-black tabular-nums">
                                                            {formatCurrency(
                                                                doc.monto_total,
                                                            )}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        {getEstadoBadge(
                                                            doc.estado,
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        {siiCfg ? (
                                                            <div className="flex items-center justify-center gap-1">
                                                                <siiCfg.icon className="h-3.5 w-3.5 text-muted-foreground" />
                                                                <span className="text-xs font-semibold">
                                                                    {
                                                                        siiCfg.label
                                                                    }
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <span className="text-xs text-muted-foreground">
                                                            {new Date(
                                                                doc.created_at,
                                                            ).toLocaleDateString(
                                                                currency.locale,
                                                                {
                                                                    year: 'numeric',
                                                                    month: '2-digit',
                                                                    day: '2-digit',
                                                                    hour: '2-digit',
                                                                    minute: '2-digit',
                                                                },
                                                            )}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        <div className="flex items-center justify-center gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 rounded-full"
                                                                title="Ver detalle"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 rounded-full"
                                                                title="Descargar XML"
                                                            >
                                                                <Download className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="mt-6">
                    <Pagination
                        links={documentos.links}
                        meta={documentos.meta}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
