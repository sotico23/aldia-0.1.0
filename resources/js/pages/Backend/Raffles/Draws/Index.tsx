import { Head, Link } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Gift,
    Users,
    Trophy,
    Play,
    CheckCircle,
    Search
} from 'lucide-react';
import { useState } from 'react';
import { useCountry } from '@/hooks/use-country';
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
import {
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { sanitize } from '@/lib/sanitize';

interface Raffle {
    id: number;
    title: string;
    slug: string;
    status: string;
    cover_image: string | null;
    participants_count: number;
    prizes_count: number;
    winners_count: number;
    start_date: string | null;
    end_date: string | null;
    created_at: string;
    winners: any[];
}

interface Props {
    raffles: {
        data: Raffle[];
        links: any[];
    };
}

const statusConfig: Record<string, { label: string; color: string }> = {
    draft: { label: 'Borrador', color: 'bg-zinc-500' },
    published: { label: 'Publicada', color: 'bg-blue-500' },
    active: { label: 'Activa', color: 'bg-green-500' },
    completed: { label: 'Completada', color: 'bg-purple-500' },
    cancelled: { label: 'Cancelada', color: 'bg-red-500' },
};

export default function DrawsIndex({ raffles }: Props) {
    const { code: countryCode, currency } = useCountry();
    const [search, setSearch] = useState('');
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');


    const filteredRaffles = raffles.data.filter((raffle) =>
        raffle.title.toLowerCase().includes(search.toLowerCase()),
    );

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString(currency.locale, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    };

    return (
        <AppLayout>
            <Head title="Sorteos - Rifas y Sorteos" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Sorteos
                        </h1>
                        <p className="text-muted-foreground">
                            Gestiona y realiza los sorteos de tus rifas
                        </p>
                    </div>
                </div>

                {/* Filtros */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="relative max-w-sm flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Buscar rifas..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Rifas Activas
                            </CardTitle>
                            <Gift className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {
                                    raffles.data.filter(
                                        (r) => r.status === 'active',
                                    ).length
                                }
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Participantes
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {raffles.data.reduce(
                                    (sum, r) => sum + r.participants_count,
                                    0,
                                )}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Sorteos Completados
                            </CardTitle>
                            <Trophy className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {
                                    raffles.data.filter(
                                        (r) => r.status === 'completed',
                                    ).length
                                }
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Rifas Disponibles para Sorteo</CardTitle>
                                <CardDescription>
                                    Rifas publicadas o activas listas para realizar el sorteo
                                </CardDescription>
                            </div>
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
                        </div>
                    </CardHeader>
                    <CardContent>
                        {filteredRaffles.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Gift className="mb-4 h-12 w-12 text-muted-foreground" />
                                <h3 className="text-lg font-semibold">
                                    No hay rifas disponibles
                                </h3>
                                <p className="text-muted-foreground">
                                    Crea una rifa para comenzar
                                </p>
                            </div>
                        ) : viewMode === 'table' ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Rifa</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead>Participantes</TableHead>
                                        <TableHead>Premios</TableHead>
                                        <TableHead>Ganadores</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead className="text-right">
                                            Acciones
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredRaffles.map((raffle) => (
                                        <TableRow key={raffle.id}>
                                            <TableCell className="font-medium">
                                                <div className="flex items-center gap-3">
                                                    {raffle.cover_image ? (
                                                        <img
                                                            src={
                                                                raffle.cover_image
                                                            }
                                                            alt={raffle.title}
                                                            className="h-10 w-10 rounded-lg object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                                            <Gift className="h-5 w-5 text-zinc-400" />
                                                        </div>
                                                    )}
                                                    <div>
                                                        <div className="font-medium">
                                                            {raffle.title}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            /{raffle.slug}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={
                                                        statusConfig[
                                                            raffle.status
                                                        ]?.color
                                                    }
                                                >
                                                    {statusConfig[raffle.status]
                                                        ?.label ||
                                                        raffle.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Users className="h-4 w-4 text-muted-foreground" />
                                                    <span>
                                                        {
                                                            raffle.participants_count
                                                        }
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Trophy className="h-4 w-4 text-muted-foreground" />
                                                    <span>
                                                        {raffle.prizes_count}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {raffle.winners_count > 0 ? (
                                                    <div className="flex items-center gap-1 text-green-600">
                                                        <CheckCircle className="h-4 w-4" />
                                                        <span>
                                                            {
                                                                raffle.winners_count
                                                            }
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className="text-sm">
                                                    {formatDate(
                                                        raffle.created_at,
                                                    )}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Link
                                                    href={`/raffles/${raffle.id}/draw-room`}
                                                >
                                                    <Button size="sm">
                                                        <Play className="mr-2 h-4 w-4" />
                                                        Ir al Sorteo
                                                    </Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {filteredRaffles.map((raffle) => (
                                    <Card key={raffle.id} className="overflow-hidden">
                                        <CardContent className="p-0">
                                            {raffle.cover_image ? (
                                                <div className="aspect-video w-full overflow-hidden">
                                                    <img src={raffle.cover_image} alt={raffle.title} className="h-full w-full object-cover" />
                                                </div>
                                            ) : (
                                                <div className="flex aspect-video w-full items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                                                    <Gift className="h-10 w-10 text-zinc-300" />
                                                </div>
                                            )}
                                            <div className="space-y-3 p-4">
                                                <div>
                                                    <h3 className="font-semibold">{raffle.title}</h3>
                                                    <p className="text-xs text-muted-foreground">/{raffle.slug}</p>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <Badge className={statusConfig[raffle.status]?.color}>
                                                        {statusConfig[raffle.status]?.label || raffle.status}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center justify-between text-sm text-muted-foreground">
                                                    <span className="flex items-center gap-1"><Users className="h-3 w-3" /> {raffle.participants_count}</span>
                                                    <span className="flex items-center gap-1"><Trophy className="h-3 w-3" /> {raffle.prizes_count}</span>
                                                    <span className="flex items-center gap-1">{raffle.winners_count > 0 ? <CheckCircle className="h-3 w-3 text-green-600" /> : null} {raffle.winners_count}</span>
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDate(raffle.created_at)}
                                                </div>
                                                <div className="flex justify-end">
                                                    <Link href={`/raffles/${raffle.id}/draw-room`}>
                                                        <Button size="sm">
                                                            <Play className="mr-2 h-4 w-4" />
                                                            Ir al Sorteo
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {raffles.links && raffles.links.length > 3 && (
                    <div className="flex justify-center">
                        <div className="flex gap-2">
                            {raffles.links.map((link: any, index: number) => (
                                <Link
                                    key={index}
                                    href={link.url || '#'}
                                    disabled={!link.url}
                                >
                                    <Button
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        dangerouslySetInnerHTML={{
                                            __html: sanitize(link.label),
                                        }}
                                    />
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
