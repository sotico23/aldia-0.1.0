import { Head, Link, router } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    ShoppingBag,
    Eye,
    Calendar,
    User,
    DollarSign,
    MessageCircle,
    Download,
    FileSpreadsheet,
    FileText,
    ChevronDown,
    Filter,
    X
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCountry } from '@/hooks/use-country';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pedidos Recibidos',
        href: '/pedidos-recibidos',
    },
];

interface PedidoItem {
    id: number;
    nombre_producto: string;
    cantidad: number;
    subtotal: number;
}

interface Pedido {
    id: number;
    numero_pedido: string;
    cliente: { name: string } | null;
    nombre_cliente: string;
    estado: string;
    total: number;
    created_at: string;
    items: PedidoItem[];
    conversacion?: { id: number };
}

interface Filtros {
    search?: string;
    estado?: string;
    fechaDesde?: string;
    fechaHasta?: string;
}

interface Props {
    pedidos: {
        data: Pedido[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filtros: Filtros;
}

export default function Index({ pedidos, filtros }: Props) {
    const { code: countryCode, currency } = useCountry();

    const [search, setSearch] = useState(filtros.search || '');
    const [estado, setEstado] = useState(filtros.estado || 'all');
    const [fechaDesde, setFechaDesde] = useState(filtros.fechaDesde || '');
    const [fechaHasta, setFechaHasta] = useState(filtros.fechaHasta || '');
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    // Filtros instantáneos con debounce de 500ms
    useEffect(() => {
        const timer = setTimeout(() => {
            const params: Record<string, string> = {};
            if (search) params.search = search;
            if (estado !== 'all') params.estado = estado;
            if (fechaDesde) params.fechaDesde = fechaDesde;
            if (fechaHasta) params.fechaHasta = fechaHasta;

            router.get('/pedidos-recibidos', params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);

        return () => clearTimeout(timer);
    }, [search, estado, fechaDesde, fechaHasta]);

    const clearFilters = () => {
        setSearch('');
        setEstado('all');
        setFechaDesde('');
        setFechaHasta('');
        router.get(
            '/pedidos-recibidos',
            {},
            { preserveState: true, replace: true },
        );
    };

    const hasActiveFilters =
        search || estado !== 'all' || fechaDesde || fechaHasta;

    const getEstadoColor = (estado: string) => {
        switch (estado) {
            case 'pendiente':
                return 'bg-yellow-100 text-yellow-800';
            case 'confirmado':
                return 'bg-blue-100 text-blue-800';
            case 'preparando':
                return 'bg-orange-100 text-orange-800';
            case 'enviado':
                return 'bg-purple-100 text-purple-800';
            case 'entregado':
                return 'bg-green-100 text-green-800';
            case 'cancelado':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString(currency.locale, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pedidos Recibidos" />
            <div className="flex h-full flex-col p-8">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="inline-block border-b-2 border-indigo-500 pb-2 text-2xl font-bold tracking-tight text-gray-900">
                            Pedidos Recibidos
                        </h1>
                        <p className="mt-2 text-sm text-gray-500">
                            Administra las compras realizadas por clientes desde
                            tu tienda pública.
                        </p>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" className="gap-2">
                                <Download className="h-4 w-4" />
                                Exportar
                                <ChevronDown className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <Link
                                    href={`/pedidos-recibidos/export?format=csv&${new URLSearchParams(filtros as any).toString()}`}
                                    className="cursor-pointer"
                                >
                                    <FileText className="mr-2 h-4 w-4" />
                                    Exportar CSV
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link
                                    href={`/pedidos-recibidos/export?format=excel&${new URLSearchParams(filtros as any).toString()}`}
                                    className="cursor-pointer"
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar Excel
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* Filtros */}
                <div className="mb-4 rounded-lg border bg-white p-4">
                    <div className="mb-4 flex items-center gap-2">
                        <Filter className="h-4 w-4 text-gray-500" />
                        <span className="text-sm font-medium text-gray-700">
                            Filtros
                        </span>
                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                                className="ml-auto h-auto p-1 text-xs text-red-500"
                            >
                                Limpiar
                            </Button>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-gray-600">
                                Buscar
                            </label>
                            <div className="flex gap-2">
                                <Input
                                    placeholder="N° pedido o cliente..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="h-9"
                                />
                            </div>
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-gray-600">
                                Estado
                            </label>
                            <Select value={estado} onValueChange={setEstado}>
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="pendiente">
                                        Pendiente
                                    </SelectItem>
                                    <SelectItem value="confirmado">
                                        Confirmado
                                    </SelectItem>
                                    <SelectItem value="preparando">
                                        Preparando
                                    </SelectItem>
                                    <SelectItem value="enviado">
                                        Enviado
                                    </SelectItem>
                                    <SelectItem value="entregado">
                                        Entregado
                                    </SelectItem>
                                    <SelectItem value="cancelado">
                                        Cancelado
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-gray-600">
                                Desde
                            </label>
                            <Input
                                type="date"
                                value={fechaDesde}
                                onChange={(e) => setFechaDesde(e.target.value)}
                                className="h-9"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-gray-600">
                                Hasta
                            </label>
                            <div className="flex gap-2">
                                <Input
                                    type="date"
                                    value={fechaHasta}
                                    onChange={(e) =>
                                        setFechaHasta(e.target.value)
                                    }
                                    className="h-9"
                                />
                                {hasActiveFilters && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={clearFilters}
                                        className="h-9 text-red-500"
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                )}
                                {hasActiveFilters && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={clearFilters}
                                        className="h-9 text-red-500"
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h3 className="text-lg font-semibold text-gray-900">
                            {pedidos.total} pedido(s)
                        </h3>
                        <div className="flex items-center gap-1 rounded-lg bg-gray-100 p-1">
                            <Button
                                variant={viewMode === 'table' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setViewMode('table')}
                                className="h-8 w-8 p-0"
                            >
                                <List className="h-4 w-4" />
                            </Button>
                            <Button
                                variant={viewMode === 'cards' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setViewMode('cards')}
                                className="h-8 w-8 p-0"
                            >
                                <LayoutGrid className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    {pedidos.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center p-12 text-center">
                            <div className="mb-4 rounded-full bg-indigo-50 p-4">
                                <ShoppingBag className="h-8 w-8 text-indigo-500" />
                            </div>
                            <h3 className="mb-1 text-lg font-medium text-gray-900">
                                No hay pedidos entrantes
                            </h3>
                            <p className="max-w-sm text-gray-500">
                                No has recibido pedidos a través de tu perfil
                                público aún.
                            </p>
                        </div>
                    ) : (
                        <>
                        {viewMode === 'table' ? (
                        <>
                            <Table>
                                <TableHeader className="bg-gray-50/50">
                                    <TableRow>
                                        <TableHead className="font-semibold text-gray-700">
                                            Pedido
                                        </TableHead>
                                        <TableHead className="font-semibold text-gray-700">
                                            Fecha
                                        </TableHead>
                                        <TableHead className="font-semibold text-gray-700">
                                            Cliente
                                        </TableHead>
                                        <TableHead className="text-right font-semibold text-gray-700">
                                            Total
                                        </TableHead>
                                        <TableHead className="font-semibold text-gray-700">
                                            Estado
                                        </TableHead>
                                        <TableHead className="text-right font-medium font-semibold text-gray-700">
                                            Acciones
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pedidos.data.map((pedido) => (
                                        <TableRow
                                            key={pedido.id}
                                            className="transition-colors hover:bg-gray-50"
                                        >
                                            <TableCell className="font-medium">
                                                <div className="flex items-center space-x-2">
                                                    <ShoppingBag className="h-4 w-4 text-indigo-500" />
                                                    <span className="max-w-[120px] truncate font-semibold text-indigo-700">
                                                        #{pedido.numero_pedido}
                                                    </span>
                                                </div>
                                                <div className="mt-1 ml-6 text-xs text-gray-500">
                                                    {pedido.items.length}{' '}
                                                    producto(s)
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center space-x-2 text-sm text-gray-600">
                                                    <Calendar className="h-4 w-4" />
                                                    <span>
                                                        {formatDate(
                                                            pedido.created_at,
                                                        )}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center space-x-2 text-sm text-gray-700">
                                                    <User className="h-4 w-4" />
                                                    <span>
                                                        {pedido.cliente?.name ||
                                                            pedido.nombre_cliente}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right font-medium text-gray-900">
                                                <div className="flex items-center justify-end space-x-1">
                                                    <DollarSign className="h-4 w-4 text-green-600" />
                                                    <span>
                                                        {formatCurrency(
                                                            pedido.total,
                                                        )}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={`shrink-0 border-0 capitalize ${getEstadoColor(pedido.estado)}`}
                                                >
                                                    {pedido.estado}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {pedido.conversacion && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                            className="hover:bg-green-50 hover:text-green-600"
                                                        >
                                                            <Link
                                                                href={`/conversaciones-pedidos/${pedido.conversacion.id}/chat`}
                                                            >
                                                                <MessageCircle className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                        className="hover:bg-indigo-50 hover:text-indigo-600"
                                                    >
                                                        <Link
                                                            href={`/pedidos-recibidos/${pedido.id}`}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {/* Paginación */}
                            {pedidos.last_page > 1 && (
                                <div className="flex items-center justify-between border-t px-4 py-3">
                                    <div className="text-sm text-gray-500">
                                        Mostrando {pedidos.from} a {pedidos.to}{' '}
                                        de {pedidos.total} pedidos
                                    </div>
                                    <div className="flex gap-1">
                                        {pedidos.links.map((link, index) => {
                                            if (link.label === '...')
                                                return null;
                                            if (!link.url) {
                                                return (
                                                    <span
                                                        key={index}
                                                        className="px-3 py-1 text-gray-400"
                                                    >
                                                        ...
                                                    </span>
                                                );
                                            }
                                            return (
                                                <Button
                                                    key={index}
                                                    variant={
                                                        link.active
                                                            ? 'default'
                                                            : 'ghost'
                                                    }
                                                    size="sm"
                                                    className={
                                                        link.active
                                                            ? 'bg-indigo-600'
                                                            : ''
                                                    }
                                                    asChild
                                                >
                                                    <Link href={link.url}>
                                                        {link.label}
                                                    </Link>
                                                </Button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </>
                        ) : (
                        <>
                            <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                {pedidos.data.map((pedido) => (
                                    <div key={pedido.id} className="rounded-lg border bg-white p-4 shadow-sm transition-shadow hover:shadow-md">
                                        <div className="mb-2 flex items-center justify-between">
                                            <div className="flex items-center space-x-2">
                                                <ShoppingBag className="h-5 w-5 text-indigo-500" />
                                                <span className="font-semibold text-indigo-700">#{pedido.numero_pedido}</span>
                                            </div>
                                            <Badge variant="outline" className={`shrink-0 border-0 capitalize ${getEstadoColor(pedido.estado)}`}>
                                                {pedido.estado}
                                            </Badge>
                                        </div>
                                        <div className="mt-2 space-y-1.5 text-sm text-gray-600">
                                            <div className="flex items-center space-x-2">
                                                <User className="h-3.5 w-3.5" />
                                                <span>{pedido.cliente?.name || pedido.nombre_cliente}</span>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Calendar className="h-3.5 w-3.5" />
                                                <span>{formatDate(pedido.created_at)}</span>
                                            </div>
                                            <div className="flex items-center justify-between pt-1">
                                                <span className="text-xs text-gray-500">{pedido.items.length} producto(s)</span>
                                                <span className="font-semibold text-green-600">{formatCurrency(pedido.total)}</span>
                                            </div>
                                        </div>
                                        <div className="mt-3 flex justify-end gap-2 border-t pt-2">
                                            {pedido.conversacion && (
                                                <Button variant="ghost" size="sm" asChild className="h-8 w-8 p-0 hover:bg-green-50 hover:text-green-600">
                                                    <Link href={`/conversaciones-pedidos/${pedido.conversacion.id}/chat`}>
                                                        <MessageCircle className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            <Button variant="ghost" size="sm" asChild className="h-8 w-8 p-0 hover:bg-indigo-50 hover:text-indigo-600">
                                                <Link href={`/pedidos-recibidos/${pedido.id}`}>
                                                    <Eye className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {pedidos.last_page > 1 && (
                                <div className="flex items-center justify-between border-t px-4 py-3">
                                    <div className="text-sm text-gray-500">
                                        Mostrando {pedidos.from} a {pedidos.to} de {pedidos.total} pedidos
                                    </div>
                                    <div className="flex gap-1">
                                        {pedidos.links.map((link, index) => {
                                            if (link.label === '...') return null;
                                            if (!link.url) {
                                                return <span key={index} className="px-3 py-1 text-gray-400">...</span>;
                                            }
                                            return (
                                                <Button key={index} variant={link.active ? 'default' : 'ghost'} size="sm" className={link.active ? 'bg-indigo-600' : ''} asChild>
                                                    <Link href={link.url}>{link.label}</Link>
                                                </Button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </>
                        )}
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
