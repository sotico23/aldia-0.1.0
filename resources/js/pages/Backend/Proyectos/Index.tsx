import { Head, useForm, router } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Eye,
    Briefcase,
    Calendar,
    TrendingUp,
    Coins,
    ClipboardList,
    Download,
    Upload,
} from 'lucide-react';
import { useState, useMemo, useRef } from 'react';
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
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Proyecto {
    id: number;
    nombre: string;
    descripcion: string | null;
    cliente: string | null;
    responsable: string | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    presupuesto: number;
    gasto_real: number;
    progreso: number;
    estado: string;
    notas: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Proyectos', href: '/proyectos' },
];

const estados = ['planeacion', 'activo', 'en_pausa', 'completado', 'cancelado'];

export default function Index({
    proyectos,
}: {
    proyectos: {
        data: Proyecto[];
        links: any[];
        from?: number;
        to?: number;
        total?: number;
        meta?: any;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('proyectos.proyectos.create');
    const canEdit = hasPermission('proyectos.proyectos.edit');
    const canDelete = hasPermission('proyectos.proyectos.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [isVerOpen, setIsVerOpen] = useState(false);
    const [editando, setEditando] = useState<Proyecto | null>(null);
    const [proyectoSeleccionado, setProyectoSeleccionado] =
        useState<Proyecto | null>(null);
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        nombre: '',
        descripcion: '',
        cliente: '',
        responsable: '',
        fecha_inicio: '',
        fecha_fin: '',
        presupuesto: 0,
        gasto_real: 0,
        progreso: 0,
        estado: 'planeacion',
        notas: '',
    });

    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });

    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);

    const proyectosFiltrados = useMemo(() => {
        return proyectos.data.filter((p) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !p.nombre.toLowerCase().includes(busca) &&
                    !(p.cliente || '').toLowerCase().includes(busca) &&
                    !(p.responsable || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && p.estado !== filtros.estado) return false;

            return true;
        });
    }, [proyectos.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando)
            put(`/proyectos/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        else
            post('/proyectos', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
    };

    const handleEdit = (proj: Proyecto) => {
        setEditando(proj);
        setData({
            nombre: proj.nombre,
            descripcion: proj.descripcion || '',
            cliente: proj.cliente || '',
            responsable: proj.responsable || '',
            fecha_inicio: proj.fecha_inicio || '',
            fecha_fin: proj.fecha_fin || '',
            presupuesto: proj.presupuesto,
            gasto_real: proj.gasto_real,
            progreso: proj.progreso,
            estado: proj.estado,
            notas: proj.notas || '',
        });
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setData({
            nombre: '',
            descripcion: '',
            cliente: '',
            responsable: '',
            fecha_inicio: '',
            fecha_fin: '',
            presupuesto: 0,
            gasto_real: 0,
            progreso: 0,
            estado: 'planeacion',
            notas: '',
        });
        setEditando(null);
        setIsOpen(true);
    };

    const handleVer = (proj: Proyecto) => {
        setProyectoSeleccionado(proj);
        setIsVerOpen(true);
    };

    const handleExportCsv = () => {
        window.location.href = '/proyectos/export';
    };

    const handleExportExcel = () => {
        window.location.href = '/proyectos/export-excel';
    };

    const handleImportCsv = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('archivo', file);
        router.post('/proyectos/import', formData, {
            onSuccess: () => alert('Importación completada'),
            onError: (err) => alert('Error: ' + Object.values(err)[0]),
        });
    };

    const handleImportExcel = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('archivo', file);
        router.post('/proyectos/import-excel', formData, {
            onSuccess: () => alert('Importación completada'),
            onError: (err) => alert('Error: ' + Object.values(err)[0]),
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/proyectos/${id}`);
    };

    const getEstadoBadge = (e: string) => {
        const colores: Record<string, string> = {
            planeacion: 'bg-gray-500',
            activo: 'bg-blue-500',
            en_pausa: 'bg-yellow-500',
            completado: 'bg-green-500',
            cancelado: 'bg-red-500',
        };
        return <Badge className={colores[e] || 'bg-gray-500'}>{e}</Badge>;
    };

    return (
        <>
            <Head title="Proyectos" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Proyectos</h1>
                            <p className="text-muted-foreground">
                                Gestión de proyectos
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <input
                                type="file"
                                ref={csvInputRef}
                                className="hidden"
                                accept=".csv"
                                onChange={(e) => handleImportCsv(e)}
                            />
                            <input
                                type="file"
                                ref={excelInputRef}
                                className="hidden"
                                accept=".xlsx,.xls"
                                onChange={(e) => handleImportExcel(e)}
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
                                        onClick={() =>
                                            csvInputRef.current?.click()
                                        }
                                        className="rounded-lg py-3"
                                    >
                                        <Upload className="mr-2 h-4 w-4 text-blue-500" />{' '}
                                        Importar CSV
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() =>
                                            excelInputRef.current?.click()
                                        }
                                        className="rounded-lg py-3"
                                    >
                                        <Upload className="mr-2 h-4 w-4 text-indigo-500" />{' '}
                                        Importar Excel
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={handleExportCsv}
                                        className="rounded-lg py-3"
                                    >
                                        <Download className="mr-2 h-4 w-4 text-green-500" />{' '}
                                        Exportar CSV
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={handleExportExcel}
                                        className="rounded-lg py-3"
                                    >
                                        <Download className="mr-2 h-4 w-4 text-emerald-500" />{' '}
                                        Exportar Excel
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            {canCreate && (
                                <Button onClick={handleNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo Proyecto
                                </Button>
                            )}
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Proyectos</CardTitle>
                                    <CardDescription>
                                        {proyectosFiltrados.length} proyectos
                                        encontrados
                                    </CardDescription>
                                </div>
                                <div className="flex gap-1 rounded-lg bg-muted p-1">
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
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar por nombre, cliente o resp..."
                                            value={filtros.busqueda}
                                            onChange={(e) =>
                                                setFiltros({
                                                    ...filtros,
                                                    busqueda: e.target.value,
                                                })
                                            }
                                            className="h-9 pl-8"
                                        />
                                    </div>
                                </div>
                                <Select
                                    value={filtros.estado}
                                    onValueChange={(val) =>
                                        setFiltros({
                                            ...filtros,
                                            estado: val === 'all' ? '' : val,
                                        })
                                    }
                                >
                                    <SelectTrigger className="h-9 w-full bg-background sm:w-[180px]">
                                        <SelectValue placeholder="Todos los estados" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos los estados
                                        </SelectItem>
                                        {estados.map((e) => (
                                            <SelectItem key={e} value={e}>
                                                {e
                                                    .replace('_', ' ')
                                                    .toUpperCase()}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9"
                                    onClick={limpiarFiltros}
                                >
                                    <X className="mr-1 h-4 w-4" />
                                    Limpiar
                                </Button>
                            </div>
                            <div className="overflow-x-auto">
                                {viewMode === 'table' ? (
                                    <table className="w-full text-xs sm:text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-3 text-left font-medium">
                                                    Nombre / Cliente
                                                </th>
                                                <th className="py-3 text-right font-medium">
                                                    Presupuesto
                                                </th>
                                                <th className="py-3 text-center font-medium">
                                                    Progreso
                                                </th>
                                                <th className="py-3 text-center font-medium">
                                                    Estado
                                                </th>
                                                <th className="py-3 text-right font-medium">
                                                    Acciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {proyectosFiltrados.map((p) => (
                                                <tr
                                                    key={p.id}
                                                    className="border-b transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="py-3">
                                                        <div className="font-medium">
                                                            {p.nombre}
                                                        </div>
                                                        <div className="text-[10px] text-muted-foreground">
                                                            {p.cliente ||
                                                                'Sin cliente'}
                                                        </div>
                                                    </td>
                                                    <td className="py-3 text-right font-medium">
                                                        {formatCurrency(
                                                            p.presupuesto,
                                                        )}
                                                    </td>
                                                    <td className="py-3 text-center">
                                                        <div className="flex flex-col items-center gap-1">
                                                            <div className="h-1.5 w-16 overflow-hidden rounded-full bg-muted">
                                                                <div
                                                                    className="h-full bg-primary transition-all"
                                                                    style={{
                                                                        width: `${p.progreso}%`,
                                                                    }}
                                                                />
                                                            </div>
                                                            <span className="text-[10px]">
                                                                {p.progreso}%
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 text-center">
                                                        {getEstadoBadge(p.estado)}
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                                onClick={() =>
                                                                    handleVer(p)
                                                                }
                                                                title="Ver Detalles"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            {canEdit && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 w-8 p-0"
                                                                    onClick={() =>
                                                                        handleEdit(p)
                                                                    }
                                                                    title="Editar"
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                                                    onClick={() =>
                                                                        handleDelete(
                                                                            p.id,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                            {proyectosFiltrados.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={5}
                                                        className="py-8 text-center text-muted-foreground"
                                                    >
                                                        No se encontraron proyectos
                                                        con los filtros aplicados
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                ) : (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        {proyectosFiltrados.map((p) => (
                                            <Card key={p.id} className="overflow-hidden">
                                                <CardHeader className="pb-3">
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                            <Briefcase className="h-5 w-5 text-primary" />
                                                        </div>
                                                        {getEstadoBadge(p.estado)}
                                                    </div>
                                                    <CardTitle className="mt-2 text-sm">{p.nombre}</CardTitle>
                                                    <CardDescription className="text-xs">
                                                        {p.cliente || 'Sin cliente'} {p.responsable ? `· ${p.responsable}` : ''}
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent>
                                                    <div className="space-y-2">
                                                        <div className="flex items-center justify-between text-xs">
                                                            <span className="text-muted-foreground">Presupuesto</span>
                                                            <span className="font-medium">{formatCurrency(p.presupuesto)}</span>
                                                        </div>
                                                        <div className="flex items-center justify-between text-xs">
                                                            <span className="text-muted-foreground">Progreso</span>
                                                            <span className="font-medium">{p.progreso}%</span>
                                                        </div>
                                                        <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                            <div className="h-full bg-primary transition-all" style={{ width: `${p.progreso}%` }} />
                                                        </div>
                                                    </div>
                                                    <div className="mt-3 flex justify-end gap-1 border-t pt-2">
                                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50" onClick={() => handleVer(p)} title="Ver Detalles">
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {canEdit && (
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(p)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(p.id)}>
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                        {proyectosFiltrados.length === 0 && (
                                            <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                                <LayoutGrid className="mb-2 h-8 w-8" />
                                                <p className="text-sm">No se encontraron proyectos con los filtros aplicados</p>
                                            </div>
                                        )}
                                    </div>
                                )}
                                <Pagination
                                    links={proyectos.links}
                                    meta={proyectos.meta || proyectos}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-[700px]">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-6 pb-2">
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                            {editando ? 'Editar' : 'Nuevo'} Proyecto
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="p-6 pt-0">
                        <div className="grid gap-6 py-4">
                            <div className="space-y-2">
                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    Nombre del Proyecto *
                                </Label>
                                <Input
                                    value={data.nombre}
                                    onChange={(e) =>
                                        setData('nombre', e.target.value)
                                    }
                                    required
                                    placeholder="Ej: Planta Solar Atacama"
                                    className="h-11 border-none bg-muted/30 text-lg font-bold focus-visible:ring-primary/30"
                                />
                            </div>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Cliente
                                    </Label>
                                    <Input
                                        value={data.cliente || ''}
                                        onChange={(e) =>
                                            setData('cliente', e.target.value)
                                        }
                                        placeholder="Nombre de la empresa o cliente"
                                        className="h-11 border-none bg-muted/30 focus-visible:ring-primary/30"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Responsable
                                    </Label>
                                    <Input
                                        value={data.responsable || ''}
                                        onChange={(e) =>
                                            setData(
                                                'responsable',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Project Manager"
                                        className="h-11 border-none bg-muted/30 focus-visible:ring-primary/30"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Fecha Inicio
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_inicio || ''}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_inicio',
                                                e.target.value,
                                            )
                                        }
                                        className="h-11 border-none bg-muted/30 focus-visible:ring-primary/30"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Fecha Fin
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_fin || ''}
                                        onChange={(e) =>
                                            setData('fecha_fin', e.target.value)
                                        }
                                        className="h-11 border-none bg-muted/30 focus-visible:ring-primary/30"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-6">
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Presupuesto
                                    </Label>
                                    <Input
                                        type="number"
                                        step="1"
                                        value={data.presupuesto}
                                        onChange={(e) =>
                                            setData(
                                                'presupuesto',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        className="h-11 border-none bg-muted/30 font-bold focus-visible:ring-primary/30"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Gasto Real
                                    </Label>
                                    <Input
                                        type="number"
                                        step="1"
                                        value={data.gasto_real}
                                        onChange={(e) =>
                                            setData(
                                                'gasto_real',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        className="h-11 border-none bg-muted/30 font-bold focus-visible:ring-primary/30"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Progreso %
                                    </Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        max="100"
                                        value={data.progreso}
                                        onChange={(e) =>
                                            setData(
                                                'progreso',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        className="h-11 border-none bg-muted/30 font-bold focus-visible:ring-primary/30"
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    Estado
                                </Label>
                                <Select
                                    value={data.estado}
                                    onValueChange={(val) =>
                                        setData('estado', val)
                                    }
                                >
                                    <SelectTrigger className="h-11 border-none bg-muted/30 focus-visible:ring-primary/30">
                                        <SelectValue placeholder="Seleccionar estado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {estados.map((e) => (
                                            <SelectItem key={e} value={e}>
                                                {e
                                                    .replace('_', ' ')
                                                    .toUpperCase()}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    Notas y Observaciones
                                </Label>
                                <Input
                                    value={data.notas || ''}
                                    onChange={(e) =>
                                        setData('notas', e.target.value)
                                    }
                                    className="h-11 border-none bg-muted/30 focus-visible:ring-primary/30"
                                />
                            </div>
                        </div>
                        <DialogFooter className="gap-2 border-t pt-6 sm:gap-0">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                                className="w-full font-bold sm:w-auto"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                className="w-full rounded-full bg-primary px-8 font-bold shadow-lg shadow-primary/20 hover:bg-primary/90 sm:w-auto"
                            >
                                {editando
                                    ? 'Guardar Cambios'
                                    : 'Crear Proyecto'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal de Vista Detallada */}
            <Dialog open={isVerOpen} onOpenChange={setIsVerOpen}>
                <DialogContent className="max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-3xl">
                    {proyectoSeleccionado && (
                        <>
                            <DialogHeader className="border-b border-primary/10 bg-gradient-to-br from-primary/15 via-primary/5 to-transparent p-8">
                                <div className="flex flex-col justify-between gap-6 md:flex-row md:items-center">
                                    <div className="flex items-center gap-5">
                                        <div className="-rotate-3 transform rounded-2xl bg-primary p-4 text-white shadow-xl shadow-primary/20 transition-transform duration-300 hover:rotate-0">
                                            <Briefcase className="h-8 w-8" />
                                        </div>
                                        <div>
                                            <DialogTitle className="flex items-baseline gap-3 text-3xl font-black tracking-tight">
                                                {proyectoSeleccionado.nombre}
                                            </DialogTitle>
                                            <div className="mt-1 flex items-center gap-2">
                                                {getEstadoBadge(
                                                    proyectoSeleccionado.estado,
                                                )}
                                                <span className="text-xs font-bold tracking-widest text-muted-foreground uppercase">
                                                    • ID #
                                                    {proyectoSeleccionado.id}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex flex-row items-center gap-2 rounded-2xl border border-white/20 bg-white/50 px-4 py-2 backdrop-blur-sm md:flex-col md:items-end">
                                        <p className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                            Progreso Total
                                        </p>
                                        <div className="flex items-center gap-3">
                                            <span className="text-2xl font-black text-primary">
                                                {proyectoSeleccionado.progreso}%
                                            </span>
                                            <div className="h-3 w-24 overflow-hidden rounded-full border border-primary/10 bg-primary/10">
                                                <div
                                                    className="h-full bg-primary shadow-[0_0_10px_rgba(var(--primary),0.5)] transition-all duration-1000"
                                                    style={{
                                                        width: `${proyectoSeleccionado.progreso}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </DialogHeader>

                            <div className="max-h-[70vh] overflow-y-auto bg-background p-0">
                                <div className="grid grid-cols-1 divide-y border-b md:grid-cols-3 md:divide-x md:divide-y-0">
                                    <div className="space-y-2 bg-muted/5 p-6">
                                        <div className="flex items-center gap-2 text-primary/60">
                                            <Calendar className="h-4 w-4" />
                                            <span className="text-[10px] font-black tracking-widest uppercase">
                                                Cronograma
                                            </span>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-xs font-bold text-muted-foreground">
                                                Inicio:{' '}
                                                <span className="text-foreground">
                                                    {proyectoSeleccionado.fecha_inicio ||
                                                        'TBD'}
                                                </span>
                                            </p>
                                            <p className="text-xs font-bold text-muted-foreground">
                                                Fin:{' '}
                                                <span className="text-foreground">
                                                    {proyectoSeleccionado.fecha_fin ||
                                                        'TBD'}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div className="space-y-2 p-6">
                                        <div className="flex items-center gap-2 text-primary/60">
                                            <Coins className="h-4 w-4" />
                                            <span className="text-[10px] font-black tracking-widest uppercase">
                                                Finanzas
                                            </span>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-xs font-bold text-muted-foreground">
                                                Presp:{' '}
                                                <span className="text-foreground">
                                                    {formatCurrency(
                                                        proyectoSeleccionado.presupuesto,
                                                    )}
                                                </span>
                                            </p>
                                            <p className="text-xs font-bold text-muted-foreground">
                                                Gasto:{' '}
                                                <span className="text-destructive">
                                                    {formatCurrency(
                                                        proyectoSeleccionado.gasto_real,
                                                    )}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div className="space-y-2 bg-muted/5 p-6">
                                        <div className="flex items-center gap-2 text-primary/60">
                                            <TrendingUp className="h-4 w-4" />
                                            <span className="text-[10px] font-black tracking-widest uppercase">
                                                Stakeholders
                                            </span>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-xs font-bold text-muted-foreground">
                                                Cliente:{' '}
                                                <span className="block truncate text-foreground">
                                                    {proyectoSeleccionado.cliente ||
                                                        'Independiente'}
                                                </span>
                                            </p>
                                            <p className="text-xs font-bold text-muted-foreground">
                                                Líder:{' '}
                                                <span className="block truncate text-foreground">
                                                    {proyectoSeleccionado.responsable ||
                                                        'No asignado'}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-8 p-8">
                                    <div className="space-y-3">
                                        <div className="flex items-center gap-2">
                                            <ClipboardList className="h-4 w-4 text-primary" />
                                            <h3 className="text-xs font-black tracking-[0.2em] text-primary/80 uppercase">
                                                Descripción del Alcance
                                            </h3>
                                        </div>
                                        <div className="relative rounded-2xl border border-dashed border-muted-foreground/30 bg-muted/20 p-5">
                                            <p className="text-sm leading-relaxed text-foreground/80 italic">
                                                {proyectoSeleccionado.descripcion ||
                                                    'No se ha proporcionado una descripción detallada para este proyecto operativo.'}
                                            </p>
                                            <div className="absolute -right-2 -bottom-2 rounded-full border bg-background p-2 shadow-sm">
                                                <div className="h-1 w-4 rounded-full bg-primary/20" />
                                            </div>
                                        </div>
                                    </div>

                                    {proyectoSeleccionado.notas && (
                                        <div className="space-y-3">
                                            <h3 className="text-xs font-black tracking-[0.2em] text-primary/80 uppercase">
                                                Notas Adicionales
                                            </h3>
                                            <p className="border-l-2 border-primary/20 pl-4 text-sm leading-relaxed text-muted-foreground">
                                                {proyectoSeleccionado.notas}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <DialogFooter className="flex flex-col items-center gap-4 border-t bg-muted/10 p-6 md:flex-row">
                                <div className="flex-1 text-xs font-medium text-muted-foreground italic">
                                    * Última sincronización de estado realizada
                                    hace escasos minutos.
                                </div>
                                <Button
                                    onClick={() => setIsVerOpen(false)}
                                    className="w-full transform rounded-full bg-foreground px-10 font-black text-background shadow-2xl transition-all hover:scale-105 hover:bg-foreground/90 active:scale-95 md:w-auto"
                                >
                                    Cerrar Expediente
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
