import { Head, router } from '@inertiajs/react';
import {
    CheckCircle,
    XCircle,
    Plus,
    FileText,
    ClipboardCheck,
    AlertCircle,
    Building2,
    Calendar,
    Eye,
    ClipboardList,
    Download,
    Upload,
    FileSpreadsheet,
    LayoutGrid,
    List,
    Trash2,
} from 'lucide-react';
import { useRef, useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface User {
    id: number;
    name: string;
}

interface Almacen {
    id: number;
    nombre: string;
}

interface Cierre {
    id: number;
    closure_date: string;
    type: 'BODEGA' | 'GENERAL';
    status: 'ABIERTO' | 'CERRADO' | 'AUDITADO';
    total_products: number;
    opening_stock: number;
    closing_stock: number;
    expected_stock: number;
    difference: number;
    observations: string | null;
    closed_at: string | null;
    created_at: string;
    user?: User;
    almacen?: Almacen;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventario', href: '/inventarios' },
    { title: 'Cierre de Inventario', href: '/inventario-cierre' },
];

export default function CierreIndex({
    cierres,
    almacenes,
    filters,
}: {
    cierres: {
        data: Cierre[];
        links: any[];
        total: number;
    };
    almacenes: Almacen[];
    filters: {
        status?: string;
        almacen_id?: string;
        difference?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('inventario.inventarios.viewAny');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [almacenFilter, setAlmacenFilter] = useState(
        filters.almacen_id || 'all',
    );
    const [differenceFilter, setDifferenceFilter] = useState(
        filters.difference === '1' ? 'yes' : 'all',
    );
    const [activeCard, setActiveCard] = useState<string | null>(null);
    const [highlightedIds, setHighlightedIds] = useState<Set<number>>(new Set());
    const tableRef = useRef<HTMLDivElement>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedCierre, setSelectedCierre] = useState<Cierre | null>(null);
    const [isEditing, setIsEditing] = useState(false);
    const [editData, setEditData] = useState({
        observations: '',
        expected_stock: 0,
    });
    const [isSaving, setIsSaving] = useState(false);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [sortColumn, setSortColumn] = useState<string>('closure_date');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

    const handleCardClick = (card: string) => {
        if (activeCard === card) {
            setActiveCard(null);
            setStatusFilter('all');
            setDifferenceFilter('all');
            setHighlightedIds(new Set());
            return;
        }
        setActiveCard(card);
        setDifferenceFilter('all');
        setHighlightedIds(new Set());

        if (card === 'pendientes') {
            setStatusFilter('ABIERTO');
        } else if (card === 'auditados') {
            setStatusFilter('AUDITADO');
        } else if (card === 'con_diferencia') {
            setStatusFilter('all');
            setDifferenceFilter('yes');
        }

        setTimeout(() => {
            tableRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    };

    useEffect(() => {
        if (!activeCard || cierres.data.length === 0) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setHighlightedIds(new Set());
            return;
        }
        const ids = cierres.data
            .filter((c) => {
                if (activeCard === 'pendientes') return c.status === 'ABIERTO';
                if (activeCard === 'auditados') return c.status === 'AUDITADO';
                if (activeCard === 'con_diferencia') return Number(c.difference) !== 0;
                return false;
            })
            .map((c) => c.id);
        setHighlightedIds(new Set(ids));
    }, [activeCard, cierres.data]);

    const handleViewCierre = (cierre: Cierre) => {
        setSelectedCierre(cierre);
        setEditData({
            observations: cierre.observations || '',
            expected_stock: Number(cierre.expected_stock || 0),
        });
        setIsEditing(false);
        setIsModalOpen(true);
    };

    const handleSort = (column: string) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    const handleSaveEdit = () => {
        if (!selectedCierre) return;
        setIsSaving(true);
        router.patch(
            `/inventario-cierre/${selectedCierre.id}`,
            {
                observations: editData.observations,
                expected_stock: editData.expected_stock,
            },
            {
                onFinish: () => {
                    setIsSaving(false);
                    setIsEditing(false);
                    setIsModalOpen(false);
                },
            },
        );
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (statusFilter && statusFilter !== 'all')
                query.status = statusFilter;
            if (almacenFilter && almacenFilter !== 'all')
                query.almacen_id = almacenFilter;
            if (differenceFilter === 'yes') query.difference = '1';
            if (sortColumn) query.sort_by = sortColumn;
            if (sortDirection) query.sort_direction = sortDirection;
            router.get('/inventario-cierre', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [statusFilter, almacenFilter, differenceFilter, sortColumn, sortDirection]);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'ABIERTO':
                return (
                    <Badge className="bg-orange-500 text-white">Abierto</Badge>
                );
            case 'CERRADO':
                return (
                    <Badge className="bg-blue-500 text-white">Cerrado</Badge>
                );
            case 'AUDITADO':
                return (
                    <Badge className="bg-green-500 text-white">Auditado</Badge>
                );
            default:
                return <Badge>{status}</Badge>;
        }
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
            <Head title="Cierre de Inventario" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Cierre de Inventario
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Registro y auditoría de cierres diarios de
                            inventario
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-10 gap-2 rounded-xl border-muted-foreground/10 font-bold"
                                >
                                    <Download className="h-4 w-4 text-primary" />
                                    <span>Herramientas</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem
                                    onClick={() =>
                                        (window.location.href =
                                            '/inventario-cierre/export')
                                    }
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() =>
                                        (window.location.href =
                                            '/inventario-cierre/export-excel')
                                    }
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar Excel
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem>
                                    <label className="flex cursor-pointer items-center">
                                        <Upload className="mr-2 h-4 w-4" />
                                        Importar CSV
                                        <input
                                            type="file"
                                            accept=".csv"
                                            onChange={() => {}}
                                            className="absolute inset-0 cursor-pointer opacity-0"
                                        />
                                    </label>
                                </DropdownMenuItem>
                                <DropdownMenuItem>
                                    <label className="flex cursor-pointer items-center">
                                        <FileSpreadsheet className="mr-2 h-4 w-4" />
                                        Importar Excel
                                        <input
                                            type="file"
                                            accept=".xlsx,.xls"
                                            onChange={() => {}}
                                            className="absolute inset-0 cursor-pointer opacity-0"
                                        />
                                    </label>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button
                            onClick={() =>
                                router.get('/inventario-cierre/create')
                            }
                            className="h-10 rounded-xl bg-primary px-6 font-bold shadow-lg shadow-primary/20"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Nuevo Cierre
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card className="border-none shadow-lg">
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="rounded-full bg-blue-500/10 p-2">
                                    <ClipboardCheck className="h-5 w-5 text-blue-500" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Total Cierres
                                    </p>
                                    <p className="text-2xl font-black">
                                        {cierres.total}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <button type="button" onClick={() => handleCardClick('pendientes')} className="text-left">
                        <Card className={cn('border-none shadow-lg transition-all hover:shadow-xl cursor-pointer', activeCard === 'pendientes' && 'ring-2 ring-orange-500 shadow-xl shadow-orange-500/10')}>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-orange-500/10 p-2">
                                        <AlertCircle className="h-5 w-5 text-orange-500" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">
                                            Pendientes
                                        </p>
                                        <p className="text-2xl font-black">
                                            {cierres.data.filter((c) => c.status === 'ABIERTO').length}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </button>

                    <button type="button" onClick={() => handleCardClick('auditados')} className="text-left">
                        <Card className={cn('border-none shadow-lg transition-all hover:shadow-xl cursor-pointer', activeCard === 'auditados' && 'ring-2 ring-green-500 shadow-xl shadow-green-500/10')}>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-green-500/10 p-2">
                                        <CheckCircle className="h-5 w-5 text-green-500" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">
                                            Auditados
                                        </p>
                                        <p className="text-2xl font-black">
                                            {cierres.data.filter((c) => c.status === 'AUDITADO').length}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </button>

                    <button type="button" onClick={() => handleCardClick('con_diferencia')} className="text-left">
                        <Card className={cn('border-none shadow-lg transition-all hover:shadow-xl cursor-pointer', activeCard === 'con_diferencia' && 'ring-2 ring-red-500 shadow-xl shadow-red-500/10')}>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-red-500/10 p-2">
                                        <XCircle className="h-5 w-5 text-red-500" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">
                                            Con Diferencia
                                        </p>
                                        <p className="text-2xl font-black">
                                            {cierres.data.filter((c) => Number(c.difference) !== 0).length}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </button>
                </div>

                <Card ref={tableRef} className="border-none shadow-xl">
                    <CardHeader className="border-b pb-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-primary" />
                                Historial de Cierres
                            </CardTitle>
                            <div className="flex items-center gap-2">
                                <div className="flex items-center gap-1 rounded-lg border bg-background/50 p-0.5">
                                    <Button
                                        variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('table')}
                                        className="h-7 w-7 p-0"
                                    >
                                        <List className="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('cards')}
                                        className="h-7 w-7 p-0"
                                    >
                                        <LayoutGrid className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                                <div className="flex gap-2">
                                    <Select
                                        value={statusFilter || 'all'}
                                        onValueChange={setStatusFilter}
                                    >
                                        <SelectTrigger className="h-9 w-full sm:w-[140px]">
                                            <SelectValue placeholder="Estado" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Todos</SelectItem>
                                            <SelectItem value="abierto">Abiertos</SelectItem>
                                            <SelectItem value="cerrado">Cerrados</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        value={almacenFilter || 'all'}
                                        onValueChange={setAlmacenFilter}
                                    >
                                        <SelectTrigger className="h-9 w-full sm:w-[180px]">
                                            <SelectValue placeholder="Bodega" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todas
                                            </SelectItem>
                                            {almacenes.map((a) => (
                                                <SelectItem
                                                    key={a.id}
                                                    value={a.id.toString()}
                                                >
                                                    {a.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {viewMode === 'table' ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
<thead>
                                    <tr className="border-b bg-muted/5 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 text-left">
                                            <button
                                                onClick={() => handleSort('closure_date')}
                                                className="flex items-center gap-1.5 w-full text-left cursor-pointer select-none hover:bg-muted/10 px-2 py-1 rounded transition-colors"
                                            >
                                                <span>Fecha</span>
                                                {sortColumn === 'closure_date' && (
                                                    <span className="inline-flex text-muted-foreground">
                                                        {sortDirection === 'asc' ? '▲' : '▼'}
                                                    </span>
                                                )}
                                            </button>
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            <button
                                                onClick={() => handleSort('type')}
                                                className="flex items-center gap-1.5 w-full text-left cursor-pointer select-none hover:bg-muted/10 px-2 py-1 rounded transition-colors"
                                            >
                                                <span>Tipo</span>
                                                {sortColumn === 'type' && (
                                                    <span className="inline-flex text-muted-foreground">
                                                        {sortDirection === 'asc' ? '▲' : '▼'}
                                                    </span>
                                                )}
                                            </button>
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            <button
                                                onClick={() => handleSort('almacen_nombre')}
                                                className="flex items-center gap-1.5 w-full text-left cursor-pointer select-none hover:bg-muted/10 px-2 py-1 rounded transition-colors"
                                            >
                                                <span>Bodega</span>
                                                {sortColumn === 'almacen_nombre' && (
                                                    <span className="inline-flex text-muted-foreground">
                                                        {sortDirection === 'asc' ? '▲' : '▼'}
                                                    </span>
                                                )}
                                            </button>
                                        </th>
                                        <th className="px-4 py-3 text-center">
                                            <button
                                                onClick={() => handleSort('total_products')}
                                                className="flex items-center gap-1.5 justify-center w-full cursor-pointer select-none hover:bg-muted/10 px-2 py-1 rounded transition-colors"
                                            >
                                                <span>Productos</span>
                                                {sortColumn === 'total_products' && (
                                                    <span className="inline-flex text-muted-foreground">
                                                        {sortDirection === 'asc' ? '▲' : '▼'}
                                                    </span>
                                                )}
                                            </button>
                                        </th>
                                        <th className="px-4 py-3 text-center">
                                            <button
                                                onClick={() => handleSort('closing_stock')}
                                                className="flex items-center gap-1.5 justify-center w-full cursor-pointer select-none hover:bg-muted/10 px-2 py-1 rounded transition-colors"
                                            >
                                                <span>Stock Actual</span>
                                                {sortColumn === 'closing_stock' && (
                                                    <span className="inline-flex text-muted-foreground">
                                                        {sortDirection === 'asc' ? '▲' : '▼'}
                                                    </span>
                                                )}
                                            </button>
                                        </th>
                                        <th className="px-4 py-3 text-center">
                                            <button
                                                onClick={() => handleSort('expected_stock')}
                                                className="flex items-center gap-1.5 justify-center w-full cursor-pointer select-none hover:bg-muted/10 px-2 py-1 rounded transition-colors"
                                            >
                                                <span>Esperado</span>
                                                {sortColumn === 'expected_stock' && (
                                                    <span className="inline-flex text-muted-foreground">
                                                        {sortDirection === 'asc' ? '▲' : '▼'}
                                                    </span>
                                                )}
                                            </button>
                                        </th>
                                        <th className="px-4 py-3 text-center">
                                            <button
                                                onClick={() => handleSort('difference')}
                                                className="flex items-center gap-1.5 justify-center w-full cursor-pointer select-none hover:bg-muted/10 px-2 py-1 rounded transition-colors"
                                            >
                                                <span>Diferencia</span>
                                                {sortColumn === 'difference' && (
                                                    <span className="inline-flex text-muted-foreground">
                                                        {sortDirection === 'asc' ? '▲' : '▼'}
                                                    </span>
                                                )}
                                            </button>
                                        </th>
                                        <th className="px-4 py-3 text-center">
                                            <button
                                                onClick={() => handleSort('status')}
                                                className="flex items-center gap-1.5 justify-center w-full cursor-pointer select-none hover:bg-muted/10 px-2 py-1 rounded transition-colors"
                                            >
                                                <span>Estado</span>
                                                {sortColumn === 'status' && (
                                                    <span className="inline-flex text-muted-foreground">
                                                        {sortDirection === 'asc' ? '▲' : '▼'}
                                                    </span>
                                                )}
                                            </button>
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-muted/50">
                                    {cierres.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No hay cierres registrados
                                            </td>
                                        </tr>
                                    ) : (
                                        cierres.data.map((cierre) => (
                                            <tr
                                                key={cierre.id}
                                                className={cn(
                                                    'transition-all duration-300 hover:bg-muted/30',
                                                    highlightedIds.has(cierre.id) && 'ring-1 ring-inset ring-primary/30 bg-primary/5 shadow-sm',
                                                )}
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <Calendar className="h-4 w-4 text-muted-foreground" />
                                                        <span className="font-medium">
                                                            {new Date(
                                                                cierre.closure_date,
                                                            ).toLocaleDateString(
                                                                'es-ES',
                                                            )}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            cierre.type ===
                                                            'GENERAL'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {cierre.type}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <Building2 className="h-4 w-4 text-muted-foreground" />
                                                        <span>
                                                            {cierre.almacen
                                                                ?.nombre ||
                                                                'General'}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-center font-mono">
                                                    {cierre.total_products}
                                                </td>
                                                <td className="px-4 py-3 text-center font-mono">
                                                    {Number(
                                                        cierre.closing_stock ??
                                                            cierre.total_stock ??
                                                            0,
                                                    ).toLocaleString()}
                                                </td>
                                                <td className="px-4 py-3 text-center font-mono">
                                                    {Number(
                                                        cierre.expected_stock ||
                                                            0,
                                                    ).toLocaleString()}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={`font-mono font-bold ${Number(cierre.difference || 0) === 0 ? 'text-green-500' : 'text-red-500'}`}
                                                    >
                                                        {Number(
                                                            cierre.difference ||
                                                                0,
                                                        ) > 0
                                                            ? '+'
                                                            : ''}
                                                        {Number(
                                                            cierre.difference ||
                                                                0,
                                                        ).toLocaleString()}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    {getStatusBadge(
                                                        cierre.status,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                            onClick={() =>
                                                                handleViewCierre(
                                                                    cierre,
                                                                )
                                                            }
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {cierre.status ===
                                                            'CERRADO' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 text-green-600 hover:bg-green-50"
                                                                onClick={() =>
                                                                    router.patch(
                                                                        `/inventario-cierre/${cierre.id}/audit`,
                                                                    )
                                                                }
                                                            >
                                                                <ClipboardList className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 text-red-600 hover:bg-red-50"
                                                            onClick={() => {
                                                                if (confirm('¿Está seguro de eliminar este cierre? Esta acción no se puede deshacer.')) {
                                                                    router.delete(`/inventario-cierre/${cierre.id}`);
                                                                }
                                                            }}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                        ) : (
                        <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                            {cierres.data.map((cierre) => (
                                <Card key={cierre.id} className={cn('overflow-hidden border-none shadow-md transition-all hover:shadow-lg', highlightedIds.has(cierre.id) && 'ring-2 ring-primary/30 bg-primary/5')}>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between mb-3">
                                            <div className="flex items-center gap-2">
                                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                                <span className="font-bold">{new Date(cierre.closure_date).toLocaleDateString('es-ES')}</span>
                                            </div>
                                            <Badge variant={cierre.type === 'GENERAL' ? 'default' : 'secondary'}>{cierre.type}</Badge>
                                        </div>
                                        <div className="flex items-center gap-2 mb-2 text-sm">
                                            <Building2 className="h-4 w-4 text-muted-foreground" />
                                            <span>{cierre.almacen?.nombre || 'General'}</span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2 mb-3">
                                            <div className="rounded-lg bg-muted/20 p-2 text-center">
                                                <p className="text-[9px] font-black text-muted-foreground uppercase">Stock</p>
                                                <p className="font-mono font-bold">{Number(cierre.closing_stock ?? cierre.total_stock ?? 0).toLocaleString()}</p>
                                            </div>
                                            <div className="rounded-lg bg-muted/20 p-2 text-center">
                                                <p className="text-[9px] font-black text-muted-foreground uppercase">Esperado</p>
                                                <p className="font-mono font-bold">{Number(cierre.expected_stock || 0).toLocaleString()}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between pt-2 border-t border-muted/20">
                                            <span className={`font-mono font-bold text-lg ${Number(cierre.difference || 0) === 0 ? 'text-green-500' : 'text-red-500'}`}>
                                                {Number(cierre.difference || 0) > 0 ? '+' : ''}{Number(cierre.difference || 0).toLocaleString()}
                                            </span>
                                            <div className="flex items-center gap-2">
                                                {getStatusBadge(cierre.status)}
                                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => handleViewCierre(cierre)}>
                                                    <Eye className="h-3.5 w-3.5" />
                                                </Button>
                                                {cierre.status === 'CERRADO' && (
                                                    <Button variant="ghost" size="icon" className="h-7 w-7 text-green-600 hover:bg-green-50" onClick={() => router.patch(`/inventario-cierre/${cierre.id}/audit`)}>
                                                        <ClipboardList className="h-3.5 w-3.5" />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                            {cierres.data.length === 0 && (
                                <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                    <FileText className="mb-2 h-12 w-12" />
                                    <p className="font-bold">No hay cierres registrados</p>
                                </div>
                            )}
                        </div>
                        )}
                        <div className="border-t p-4">
                            <Pagination links={cierres.links} />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Modal de Detalle/edición de Cierre */}
            <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                <DialogContent className="m-4 max-h-[90vh] w-[95vw] max-w-2xl overflow-y-auto p-4 sm:m-auto sm:p-6">
                    <DialogHeader className="mb-4">
                        <DialogTitle className="text-lg sm:text-xl">
                            Detalle del Cierre - {selectedCierre?.closure_date}
                        </DialogTitle>
                    </DialogHeader>
                    {selectedCierre && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                                <div className="rounded-lg bg-muted/50 p-3 sm:p-4">
                                    <div className="text-sm text-muted-foreground">
                                        Estado
                                    </div>
                                    <div className="font-bold">
                                        {getStatusBadge(selectedCierre.status)}
                                    </div>
                                </div>
                                <div className="rounded-lg bg-muted/50 p-3 sm:p-4">
                                    <div className="text-sm text-muted-foreground">
                                        Tipo
                                    </div>
                                    <div className="font-bold">
                                        {selectedCierre.type === 'BODEGA'
                                            ? selectedCierre.almacen?.nombre ||
                                              'Bodega'
                                            : 'General'}
                                    </div>
                                </div>
                            </div>
                            <div className="rounded-lg bg-muted/50 p-3 sm:p-4">
                                <div className="mb-2 flex justify-between">
                                    <span className="text-sm text-muted-foreground">
                                        Productos
                                    </span>
                                    <span className="font-mono font-bold">
                                        {selectedCierre.total_products} unidades
                                    </span>
                                </div>
                                <div className="mb-2 flex justify-between">
                                    <span className="text-sm text-muted-foreground">
                                        Stock Inicio
                                    </span>
                                    <span className="font-mono font-bold">
                                        {Number(
                                            selectedCierre.opening_stock || 0,
                                        ).toLocaleString()}
                                    </span>
                                </div>
                                <div className="mb-2 flex justify-between">
                                    <span className="text-sm text-muted-foreground">
                                        Stock Cierre
                                    </span>
                                    <span className="font-mono font-bold">
                                        {Number(
                                            selectedCierre.closing_stock || 0,
                                        ).toLocaleString()}
                                    </span>
                                </div>
                                <div className="mb-2 flex justify-between">
                                    <span className="text-sm text-muted-foreground">
                                        Stock Esperado
                                    </span>
                                    {isEditing ? (
                                        <Input
                                            type="number"
                                            className="w-full text-right sm:w-32"
                                            value={editData.expected_stock}
                                            onChange={(e) =>
                                                setEditData({
                                                    ...editData,
                                                    expected_stock:
                                                        parseFloat(
                                                            e.target.value,
                                                        ) || 0,
                                                })
                                            }
                                        />
                                    ) : (
                                        <span className="font-mono font-bold">
                                            {Number(
                                                selectedCierre.expected_stock ||
                                                    0,
                                            ).toLocaleString()}
                                        </span>
                                    )}
                                </div>
                                <div className="flex justify-between border-t pt-2">
                                    <span className="text-sm font-medium">
                                        Diferencia
                                    </span>
                                    <span
                                        className={`font-mono text-lg font-bold ${Number(selectedCierre.difference || 0) === 0 ? 'text-green-500' : 'text-red-500'}`}
                                    >
                                        {Number(
                                            selectedCierre.difference || 0,
                                        ) === 0
                                            ? 'Sin diferencias'
                                            : `${Number(selectedCierre.difference || 0) > 0 ? '+' : ''}${Number(selectedCierre.difference || 0).toLocaleString()} unidades`}
                                    </span>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label className="text-sm font-medium">
                                    Observaciones
                                </Label>
                                {isEditing ? (
                                    <Input
                                        value={editData.observations}
                                        onChange={(e) =>
                                            setEditData({
                                                ...editData,
                                                observations: e.target.value,
                                            })
                                        }
                                        placeholder="Agregar observaciones..."
                                    />
                                ) : (
                                    <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                        {selectedCierre.observations ||
                                            'Sin observaciones'}
                                    </div>
                                )}
                            </div>
                            <div className="flex flex-col justify-between gap-2 sm:flex-row">
                                {isEditing ? (
                                    <>
                                        <Button
                                            variant="outline"
                                            onClick={() => setIsEditing(false)}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            onClick={handleSaveEdit}
                                            disabled={isSaving}
                                        >
                                            {isSaving
                                                ? 'Guardando...'
                                                : 'Guardar Cambios'}
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setIsModalOpen(false)
                                            }
                                        >
                                            Cerrar
                                        </Button>
                                        <Button
                                            onClick={() => setIsEditing(true)}
                                        >
                                            Editar
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
