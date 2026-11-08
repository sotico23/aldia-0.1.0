import { Head, useForm, router } from '@inertiajs/react';
import {
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Calendar,
    CheckCircle2,
    Clock,
    AlertCircle,
    Download,
    Upload,
    Hash,
    Archive,
    FileWarning,
    Layers,
    LayoutGrid,
    List,
} from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
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
import { Toaster } from '@/components/ui/sonner';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatDateCLP } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Lote {
    id: number;
    numero_lote: string;
    producto: string | null;
    cantidad: number;
    fecha_vencimiento: string | null;
    estado: string;
    notas: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/inventarios' },
    { title: 'Gestión de Lotes', href: '/lotes' },
];

const ESTADOS = [
    {
        value: 'activo',
        label: 'Activo',
        color: 'bg-green-500/10 text-green-600',
        icon: CheckCircle2,
    },
    {
        value: 'vencido',
        label: 'Vencido',
        color: 'bg-red-500/10 text-red-600',
        icon: FileWarning,
    },
    {
        value: 'agotado',
        label: 'Agotado',
        color: 'bg-orange-500/10 text-orange-600',
        icon: Archive,
    },
    {
        value: 'cuarentena',
        label: 'Cuarentena',
        color: 'bg-yellow-500/10 text-yellow-600',
        icon: Clock,
    },
    {
        value: 'rechazado',
        label: 'Rechazado',
        color: 'bg-slate-500/10 text-slate-600',
        icon: X,
    },
];

export default function Index({
    lotes,
    filters,
}: {
    lotes: { data: Lote[]; links: any[]; meta: any };
    filters: {
        search?: string;
        estado?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('inventario.lotes.create');
    const canEdit = hasPermission('inventario.lotes.edit');
    const canDelete = hasPermission('inventario.lotes.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Lote | null>(null);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [estadoFilter, setEstadoFilter] = useState(filters.estado || 'all');
    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        processing,
    } = useForm({
        numero_lote: '',
        producto: '',
        cantidad: 0,
        fecha_vencimiento: '',
        estado: 'activo',
        notas: '',
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (estadoFilter !== 'all') query.estado = estadoFilter;

            router.get('/lotes', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, estadoFilter]);

    const handleExport = (type: 'csv' | 'excel') => {
        const baseUrl =
            type === 'csv' ? '/lotes/export' : '/lotes/export-excel';
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (estadoFilter !== 'all') params.append('estado', estadoFilter);
        window.location.href = `${baseUrl}?${params.toString()}`;
    };

    const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            const formData = new FormData();
            formData.append('archivo', file);
            router.post('/lotes/import', formData, {
                onSuccess: () => {
                    if (csvInputRef.current) csvInputRef.current.value = '';
                    if (excelInputRef.current) excelInputRef.current.value = '';
                    toast.success('Lotes importados correctamente');
                },
            });
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/lotes/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                    toast.success('Lote actualizado');
                },
            });
        } else {
            post('/lotes', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                    toast.success('Lote registrado');
                },
            });
        }
    };

    const handleEdit = (l: Lote) => {
        setEditando(l);
        setData({
            numero_lote: l.numero_lote,
            producto: l.producto || '',
            cantidad: l.cantidad,
            fecha_vencimiento: l.fecha_vencimiento
                ? l.fecha_vencimiento.substring(0, 10)
                : '',
            estado: l.estado,
            notas: l.notas || '',
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Desea eliminar este lote?')) {
            destroy(`/lotes/${id}`, {
                onSuccess: () => toast.success('Lote eliminado'),
            });
        }
    };

    const getEstadoConfig = (val: string) => {
        return (
            ESTADOS.find((e) => e.value === val) || {
                label: val,
                color: 'bg-gray-500/10 text-gray-600',
                icon: AlertCircle,
            }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestión de Lotes" />
            <Toaster position="bottom-right" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="mb-1 flex items-center gap-2">
                            <Layers className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Batch & Inventory Control
                            </span>
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Gestión de Lotes
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Control de trazabilidad, vencimientos y estados de
                            inventario por lotes
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <input
                            type="file"
                            ref={csvInputRef}
                            className="hidden"
                            accept=".csv"
                            onChange={(e) => handleImport(e)}
                        />
                        <input
                            type="file"
                            ref={excelInputRef}
                            className="hidden"
                            accept=".xlsx,.xls"
                            onChange={(e) => handleImport(e)}
                        />

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9 gap-2 rounded-xl px-3"
                                >
                                    <Download className="h-4 w-4 text-primary" />{' '}
                                    Herramientas
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                className="w-56 rounded-xl border-none p-2 shadow-2xl"
                            >
                                <DropdownMenuItem
                                    onClick={() => csvInputRef.current?.click()}
                                    className="rounded-lg py-3"
                                >
                                    <Upload className="mr-2 h-4 w-4 text-emerald-500" />{' '}
                                    Importar Lotes
                                </DropdownMenuItem>
                                <hr className="my-2" />
                                <DropdownMenuItem
                                    onClick={() => handleExport('csv')}
                                    className="rounded-lg py-3"
                                >
                                    <Download className="mr-2 h-4 w-4 text-green-500" />{' '}
                                    Exportar a CSV
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        {canCreate && (
                            <Button
                                onClick={() => {
                                    setEditando(null);
                                    reset();
                                    setIsOpen(true);
                                }}
                                className="h-9 rounded-full bg-primary px-5 font-bold shadow-lg shadow-primary/20 transition-all hover:bg-primary/90"
                            >
                                <Plus className="mr-2 h-4 w-4" /> Registrar Lote
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6">
                    {/* Filters Bar */}
                    <div className="flex flex-col gap-4 rounded-3xl border border-muted/50 bg-muted/40 p-4 md:flex-row md:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Buscar por número de lote o producto..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="h-11 rounded-2xl border-none bg-background pr-12 pl-12 shadow-sm focus-visible:ring-primary/20"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Select
                                value={estadoFilter}
                                onValueChange={setEstadoFilter}
                            >
                                <SelectTrigger className="h-11 w-full rounded-2xl border-none bg-background font-bold shadow-sm sm:w-[180px]">
                                    <SelectValue placeholder="Estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos los estados
                                    </SelectItem>
                                    {ESTADOS.map((e) => (
                                        <SelectItem
                                            key={e.value}
                                            value={e.value}
                                        >
                                            {e.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-11 w-11 rounded-2xl border-none bg-background text-muted-foreground shadow-sm"
                                onClick={() => {
                                    setSearchTerm('');
                                    setEstadoFilter('all');
                                }}
                            >
                                <X className="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    <Card className="overflow-hidden rounded-[32px] border-none shadow-xl shadow-foreground/5">
                        <CardHeader className="border-b pb-4">
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <Layers className="h-5 w-5 text-primary" />
                                    Listado de Lotes
                                </CardTitle>
                                <div className="flex items-center gap-1 rounded-lg border bg-background/50 p-0.5">
                                    <Button
                                        variant={
                                            viewMode === 'table'
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        size="sm"
                                        onClick={() => setViewMode('table')}
                                        className="h-7 w-7 p-0"
                                    >
                                        <List className="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant={
                                            viewMode === 'cards'
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        size="sm"
                                        onClick={() => setViewMode('cards')}
                                        className="h-7 w-7 p-0"
                                    >
                                        <LayoutGrid className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {viewMode === 'table' ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/5 text-[11px] font-black tracking-wider text-muted-foreground uppercase">
                                                <th className="px-6 py-4 text-left">
                                                    N° Lote / Batch
                                                </th>
                                                <th className="px-6 py-4 text-left">
                                                    Producto / SKU
                                                </th>
                                                <th className="px-6 py-4 text-center">
                                                    Estado
                                                </th>
                                                <th className="px-6 py-4 text-center">
                                                    Cantidad
                                                </th>
                                                <th className="px-6 py-4 text-center">
                                                    Vencimiento
                                                </th>
                                                <th className="px-6 py-4 text-right">
                                                    Acciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-muted/50 font-medium">
                                            {lotes.data.map((l) => {
                                                const config = getEstadoConfig(
                                                    l.estado,
                                                );
                                                return (
                                                    <tr
                                                        key={l.id}
                                                        className="group transition-colors hover:bg-muted/30"
                                                    >
                                                        <td className="px-6 py-4">
                                                            <div className="flex items-center gap-2">
                                                                <div className="rounded-xl border bg-background p-2 text-primary shadow-sm transition-transform group-hover:scale-110">
                                                                    <Hash className="h-4 w-4" />
                                                                </div>
                                                                <span className="font-black text-foreground">
                                                                    {
                                                                        l.numero_lote
                                                                    }
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex flex-col">
                                                                <span className="font-bold text-foreground">
                                                                    {l.producto ||
                                                                        'N/A'}
                                                                </span>
                                                                <span className="text-[10px] font-black tracking-tight text-muted-foreground uppercase">
                                                                    Stock
                                                                    Reference
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-center">
                                                            <Badge
                                                                variant="outline"
                                                                className={`${config.color} mx-auto flex w-fit items-center gap-1.5 rounded-full border-none px-2.5 py-1 text-[9px] font-black uppercase`}
                                                            >
                                                                <config.icon className="h-3 w-3" />
                                                                {config.label}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-6 py-4 text-center">
                                                            <span className="font-black text-foreground">
                                                                {l.cantidad}{' '}
                                                                units
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4 text-center">
                                                            <div className="flex flex-col items-center">
                                                                <span className="text-xs font-bold">
                                                                    {l.fecha_vencimiento
                                                                        ? formatDateCLP(
                                                                              l.fecha_vencimiento,
                                                                          )
                                                                        : 'Indefinido'}
                                                                </span>
                                                                {l.fecha_vencimiento &&
                                                                    new Date(
                                                                        l.fecha_vencimiento,
                                                                    ) <
                                                                        new Date() && (
                                                                        <span className="text-[8px] font-black tracking-tighter text-red-500 uppercase">
                                                                            Expirado
                                                                        </span>
                                                                    )}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <div className="flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                                {canEdit && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 rounded-xl text-primary hover:bg-primary/10"
                                                                        onClick={() =>
                                                                            handleEdit(
                                                                                l,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Pencil className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                                {canDelete && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 rounded-xl text-destructive hover:bg-destructive/10"
                                                                        onClick={() =>
                                                                            handleDelete(
                                                                                l.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {lotes.data.map((l) => {
                                        const config = getEstadoConfig(
                                            l.estado,
                                        );
                                        return (
                                            <Card
                                                key={l.id}
                                                className="overflow-hidden border-none shadow-md transition-all hover:shadow-lg"
                                            >
                                                <CardContent className="p-4">
                                                    <div className="mb-3 flex items-center gap-3">
                                                        <div className="rounded-xl border bg-background p-2 text-primary shadow-sm">
                                                            <Hash className="h-4 w-4" />
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <div className="truncate text-sm font-bold text-foreground">
                                                                {l.numero_lote}
                                                            </div>
                                                            <span className="text-[10px] font-black tracking-tight text-muted-foreground uppercase">
                                                                {l.producto ||
                                                                    'N/A'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="mb-2 flex items-center justify-between">
                                                        <Badge
                                                            variant="outline"
                                                            className={`${config.color} flex items-center gap-1.5 rounded-full border-none px-2.5 py-1 text-[9px] font-black uppercase`}
                                                        >
                                                            <config.icon className="h-3 w-3" />
                                                            {config.label}
                                                        </Badge>
                                                        <span className="font-black text-foreground">
                                                            {l.cantidad} units
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center justify-between border-t border-muted/20 pt-2">
                                                        <span className="text-xs text-muted-foreground">
                                                            {l.fecha_vencimiento
                                                                ? formatDateCLP(
                                                                      l.fecha_vencimiento,
                                                                  )
                                                                : 'Indefinido'}
                                                        </span>
                                                        <div className="flex gap-1">
                                                            {canEdit && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-7 w-7 rounded-xl text-primary hover:bg-primary/10"
                                                                    onClick={() =>
                                                                        handleEdit(
                                                                            l,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="h-3.5 w-3.5" />
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-7 w-7 rounded-xl text-destructive hover:bg-destructive/10"
                                                                    onClick={() =>
                                                                        handleDelete(
                                                                            l.id,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        );
                                    })}
                                    {lotes.data.length === 0 && (
                                        <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                            <Layers className="mb-2 h-12 w-12" />
                                            <p className="font-bold">
                                                No se encontraron lotes
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}
                            <div className="border-t border-muted/50 bg-muted/5 p-6">
                                <Pagination
                                    links={lotes.links}
                                    meta={lotes.meta}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-w-[95vw] overflow-y-auto rounded-[40px] border-none p-0 shadow-2xl md:max-w-3xl">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-8 pb-4 text-left">
                        <div className="mb-1 flex items-center gap-2">
                            <Layers className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Traceability Control Unit
                            </span>
                        </div>
                        <DialogTitle className="text-3xl font-black tracking-tight text-primary">
                            {editando
                                ? 'Editar Lote de Producción'
                                : 'Registrar Nuevo Lote'}
                        </DialogTitle>
                        <DialogDescription className="font-medium text-muted-foreground">
                            Asigne parámetros de identificación y control de
                            calidad al lote.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="p-8 pt-2">
                        <div className="grid grid-cols-1 gap-8 py-4 md:grid-cols-2">
                            <div className="space-y-6">
                                <h3 className="flex items-center gap-2 text-[11px] font-black tracking-widest text-primary uppercase">
                                    <Hash className="h-4 w-4" /> Identificación
                                </h3>
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Número de Lote *
                                        </Label>
                                        <Input
                                            value={data.numero_lote}
                                            onChange={(e) =>
                                                setData(
                                                    'numero_lote',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                            className="h-12 rounded-2xl border-none bg-muted/30 font-bold"
                                            placeholder="Ej: LOT-2024-001"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Producto / Descripción
                                        </Label>
                                        <Input
                                            value={data.producto}
                                            onChange={(e) =>
                                                setData(
                                                    'producto',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-12 rounded-2xl border-none bg-muted/30 font-bold"
                                            placeholder="Nombre o SKU del producto"
                                        />
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Cantidad Inicial
                                            </Label>
                                            <Input
                                                type="number"
                                                value={data.cantidad}
                                                onChange={(e) =>
                                                    setData(
                                                        'cantidad',
                                                        parseInt(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                required
                                                className="h-12 rounded-2xl border-none bg-muted/30 text-center font-bold"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Estado
                                            </Label>
                                            <Select
                                                value={data.estado}
                                                onValueChange={(v: any) =>
                                                    setData('estado', v)
                                                }
                                            >
                                                <SelectTrigger className="h-12 rounded-2xl border-2 border-none border-primary/20 bg-muted/10 font-bold">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {ESTADOS.map((e) => (
                                                        <SelectItem
                                                            key={e.value}
                                                            value={e.value}
                                                        >
                                                            {e.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-6">
                                <h3 className="flex items-center gap-2 text-[11px] font-black tracking-widest text-primary uppercase">
                                    <Calendar className="h-4 w-4" /> Control de
                                    Tiempo
                                </h3>
                                <div className="space-y-4 rounded-[40px] border-2 border-dashed border-primary/20 bg-primary/5 p-8">
                                    <div className="space-y-2 text-center">
                                        <Label className="text-xs font-black text-muted-foreground uppercase">
                                            Fecha de Vencimiento (Opcional)
                                        </Label>
                                        <Input
                                            type="date"
                                            value={data.fecha_vencimiento}
                                            onChange={(e) =>
                                                setData(
                                                    'fecha_vencimiento',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-2 h-12 rounded-2xl border-none bg-background text-center font-black shadow-sm focus:ring-primary/20"
                                        />
                                        <p className="mt-3 px-4 text-[9px] font-medium text-muted-foreground">
                                            El sistema alertará automáticamente
                                            cuando el lote se encuentre próximo
                                            a expirar.
                                        </p>
                                    </div>
                                    <div className="mt-4 space-y-2 border-t border-primary/10 pt-4">
                                        <Label className="text-xs font-black text-muted-foreground uppercase">
                                            Notas Internas / Control Calidad
                                        </Label>
                                        <textarea
                                            value={data.notas}
                                            onChange={(e) =>
                                                setData('notas', e.target.value)
                                            }
                                            className="mt-2 flex min-h-[120px] w-full rounded-[24px] border-none bg-background px-5 py-4 text-sm font-medium shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                            placeholder="Detalles de inspección, procedencia, etc."
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="mt-8 gap-2 border-t pt-6 font-black uppercase">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full px-8"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-full bg-primary px-12 shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <CheckCircle2 className="mr-2 h-4 w-4" />{' '}
                                {editando
                                    ? 'Actualizar Batch'
                                    : 'Registrar Ingreso'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
