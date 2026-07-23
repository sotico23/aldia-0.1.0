import { Head, useForm } from '@inertiajs/react';
import {
    CheckCircle,
    Clock,
    LayoutGrid,
    List,
    Plus,
    RefreshCw,
    Server,
    XCircle
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Site {
    id: number;
    name: string;
    url: string;
    is_active: boolean;
    last_status: string | null;
    last_check_at: string | null;
    last_response_time: number | null;
    check_interval: number;
    uptime_30d: number;
    avg_response_time: number;
    incidents_count: number;
}

interface Stats {
    total_sites: number;
    sites_up: number;
    sites_down: number;
    avg_uptime: number;
    avg_response_time: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Monitoreo Uptime', href: '/uptime' },
];

export default function UptimeIndex({
    sites,
    stats,
}: {
    sites: Site[];
    stats: Stats;
}) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('uptime.monitores.viewAny');
    const [isOpen, setIsOpen] = useState(false);
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [selectedSite, setSelectedSite] = useState<Site | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const {
        data,
        setData,
        post,
        reset,
    } = useForm({
        name: '',
        url: '',
        method: 'GET',
        expected_status: '200',
        expected_string: '',
        type: 'http',
        check_interval: '5',
        timeout: '30',
        response_time_threshold: '1000',
        ssl_expiry_check: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/uptime', {
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
        });
    };

    const handleNew = () => {
        reset();
        setData({
            name: '',
            url: '',
            method: 'GET',
            expected_status: '200',
            expected_string: '',
            type: 'http',
            check_interval: '5',
            timeout: '30',
            response_time_threshold: '1000',
            ssl_expiry_check: false,
        });
        setSelectedSite(null);
        setIsOpen(true);
    };

    const getStatusBadge = (status: string | null) => {
        switch (status) {
            case 'up':
                return <Badge className="bg-green-500">Operativo</Badge>;
            case 'down':
                return <Badge className="bg-red-500">Caído</Badge>;
            case 'slow':
                return <Badge className="bg-yellow-500">Lento</Badge>;
            default:
                return <Badge variant="secondary">Sin datos</Badge>;
        }
    };

    const getUptimeColor = (uptime: number) => {
        if (uptime >= 99.9) return 'text-green-600';
        if (uptime >= 99) return 'text-yellow-600';
        return 'text-red-600';
    };

    const checkNow = (siteId: number) => {
        post(`/uptime/${siteId}/check`);
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
            <Head title="Monitoreo Uptime" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Monitoreo Uptime
                            </h1>
                            <p className="text-muted-foreground">
                                Monitorea la disponibilidad de tus sitios web
                            </p>
                        </div>
                        <Button onClick={handleNew}>
                            <Plus className="mr-2 h-4 w-4" /> Agregar Sitio
                        </Button>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Total Sitios
                                </CardTitle>
                                <Server className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {stats.total_sites}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Sitios Operativos
                                </CardTitle>
                                <CheckCircle className="h-4 w-4 text-green-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {stats.sites_up}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Sitios Caídos
                                </CardTitle>
                                <XCircle className="h-4 w-4 text-red-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">
                                    {stats.sites_down}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Uptime Promedio
                                </CardTitle>
                                <Clock className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div
                                    className={`text-2xl font-bold ${getUptimeColor(stats.avg_uptime)}`}
                                >
                                    {stats.avg_uptime}%
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Respuesta: {stats.avg_response_time}ms
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Sitios Monitoreados</CardTitle>
                                    <CardDescription>
                                        {sites.length} sitios configurados
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button variant={viewMode === 'table' ? 'default' : 'outline'} size="icon" onClick={() => setViewMode('table')}>
                                        <List className="h-4 w-4" />
                                    </Button>
                                    <Button variant={viewMode === 'cards' ? 'default' : 'outline'} size="icon" onClick={() => setViewMode('cards')}>
                                        <LayoutGrid className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {sites.length === 0 && viewMode === 'table' ? (
                                <div className="py-12 text-center">
                                    <Server className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-semibold">
                                        No hay sitios configurados
                                    </h3>
                                    <p className="mb-4 text-muted-foreground">
                                        Agrega tu primer sitio para comenzar el
                                        monitoreo.
                                    </p>
                                    <Button onClick={handleNew}>
                                        Agregar Sitio
                                    </Button>
                                </div>
                            ) : viewMode === 'table' ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs sm:text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-2 text-left font-medium">
                                                    Nombre
                                                </th>
                                                <th className="py-2 text-left font-medium">
                                                    Estado
                                                </th>
                                                <th className="py-2 text-left font-medium">
                                                    Último Chequeo
                                                </th>
                                                <th className="py-2 text-left font-medium">
                                                    Respuesta
                                                </th>
                                                <th className="py-2 text-left font-medium">
                                                    Uptime 30d
                                                </th>
                                                <th className="py-2 text-left font-medium">
                                                    Intervalo
                                                </th>
                                                <th className="py-2 text-right font-medium">
                                                    Acciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {sites.map((site) => (
                                                <tr
                                                    key={site.id}
                                                    className="border-b hover:bg-muted/50"
                                                >
                                                    <td className="py-2">
                                                        <div className="font-medium">
                                                            {site.name}
                                                        </div>
                                                        <div className="max-w-[200px] truncate text-xs text-muted-foreground">
                                                            {site.url}
                                                        </div>
                                                        {site.incidents_count >
                                                            0 && (
                                                             <span className="text-xs text-red-600">
                                                                 (
                                                                 {
                                                                     site.incidents_count
                                                                 }{' '}
                                                                 incidentes)
                                                             </span>
                                                         )}
                                                    </td>
                                                    <td className="py-2">
                                                        {getStatusBadge(
                                                            site.last_status,
                                                        )}
                                                    </td>
                                                    <td className="py-2">
                                                        {site.last_check_at ? (
                                                            <span className="text-xs">
                                                                {new Date(
                                                                    site.last_check_at,
                                                                ).toLocaleString()}
                                                            </span>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">
                                                                Nunca
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-2 text-xs">
                                                        {site.last_response_time
                                                            ? `${site.last_response_time}ms`
                                                            : '-'}
                                                    </td>
                                                    <td
                                                        className={`py-2 font-medium ${getUptimeColor(site.uptime_30d)}`}
                                                    >
                                                        {site.uptime_30d}%
                                                    </td>
                                                    <td className="py-2 text-xs">
                                                        {site.check_interval}{' '}
                                                        min
                                                    </td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    checkNow(
                                                                        site.id,
                                                                    )
                                                                }
                                                                title="Verificar ahora"
                                                            >
                                                                <RefreshCw className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {sites.length === 0 ? (
                                        <div className="col-span-full py-12 text-center">
                                            <Server className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                                            <h3 className="mb-2 text-lg font-semibold">No hay sitios configurados</h3>
                                            <Button onClick={handleNew}>Agregar Sitio</Button>
                                        </div>
                                    ) : sites.map((site) => (
                                        <Card key={site.id} className="overflow-hidden">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-center justify-between">
                                                    <CardTitle className="text-sm font-bold">{site.name}</CardTitle>
                                                    {getStatusBadge(site.last_status)}
                                                </div>
                                                <CardDescription className="truncate text-xs">{site.url}</CardDescription>
                                            </CardHeader>
                                            <CardContent className="space-y-2 pt-0">
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">Uptime 30d</span>
                                                    <span className={`font-medium ${getUptimeColor(site.uptime_30d)}`}>{site.uptime_30d}%</span>
                                                </div>
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">Respuesta</span>
                                                    <span>{site.last_response_time ? `${site.last_response_time}ms` : '-'}</span>
                                                </div>
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">Último chequeo</span>
                                                    <span className="text-xs">{site.last_check_at ? new Date(site.last_check_at).toLocaleString() : 'Nunca'}</span>
                                                </div>
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">Intervalo</span>
                                                    <span>{site.check_interval} min</span>
                                                </div>
                                                {site.incidents_count > 0 && (
                                                    <div className="text-xs text-red-600 font-medium">{site.incidents_count} incidentes</div>
                                                )}
                                                <div className="flex justify-end gap-1 border-t pt-2">
                                                    <Button variant="ghost" size="icon" onClick={() => checkNow(site.id)} title="Verificar ahora">
                                                        <RefreshCw className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="w-full sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Agregar Sitio a Monitorear</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Nombre del Sitio *</Label>
                                    <Input
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="Mi Sitio Web"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>URL *</Label>
                                    <Input
                                        value={data.url}
                                        onChange={(e) =>
                                            setData('url', e.target.value)
                                        }
                                        placeholder="https://misitio.com"
                                        type="url"
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <Label>Método</Label>
                                    <select
                                        value={data.method}
                                        onChange={(e) =>
                                            setData('method', e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        <option value="GET">GET</option>
                                        <option value="POST">POST</option>
                                        <option value="HEAD">HEAD</option>
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Código Esperado</Label>
                                    <Input
                                        value={data.expected_status}
                                        onChange={(e) =>
                                            setData(
                                                'expected_status',
                                                e.target.value,
                                            )
                                        }
                                        type="number"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Intervalo</Label>
                                    <select
                                        value={data.check_interval}
                                        onChange={(e) =>
                                            setData(
                                                'check_interval',
                                                e.target.value,
                                            )
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        <option value="1">1 min</option>
                                        <option value="5">5 min</option>
                                        <option value="10">10 min</option>
                                        <option value="15">15 min</option>
                                        <option value="30">30 min</option>
                                        <option value="60">1 hora</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Timeout (seg)</Label>
                                    <Input
                                        value={data.timeout}
                                        onChange={(e) =>
                                            setData('timeout', e.target.value)
                                        }
                                        type="number"
                                        min="5"
                                        max="120"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Umbral Respuesta (ms)</Label>
                                    <Input
                                        value={data.response_time_threshold}
                                        onChange={(e) =>
                                            setData(
                                                'response_time_threshold',
                                                e.target.value,
                                            )
                                        }
                                        type="number"
                                        min="100"
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Texto Esperado (opcional)</Label>
                                <Input
                                    value={data.expected_string}
                                    onChange={(e) =>
                                        setData(
                                            'expected_string',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Dejar vacío para cualquier respuesta 200"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="ssl_expiry_check"
                                    checked={data.ssl_expiry_check}
                                    onChange={(e) =>
                                        setData(
                                            'ssl_expiry_check',
                                            e.target.checked,
                                        )
                                    }
                                />
                                <Label htmlFor="ssl_expiry_check">
                                    Verificar expiración SSL
                                </Label>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit">Crear</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
