import { Head, useForm, router } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    Eye,
    Users,
    Truck,
    Package,
    Weight,
    Droplets,
    Download,
    Upload,
    FileSpreadsheet,
    FileJson,
    Calendar,
    BarChart3,
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Miembro {
    id: number;
    name: string;
    email: string;
}

interface Empleado {
    id: number;
    name: string;
    email: string;
}

interface Conductor {
    id: number;
    nombre: string;
    rut: string;
    licencia: string;
    telefono: string;
}

interface GrupoTrabajo {
    id: number;
    nombre: string;
    descripcion: string | null;
    color: string;
    estado: string;
    miembros: Miembro[];
    conductores?: Conductor[];
    total_ventas?: number;
    cantidad_ventas?: number;
    total_kg?: number;
    total_l?: number;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Grupos de Trabajo', href: '/grupos-trabajo' },
];

export default function Index({
    grupos,
    empleados,
    conductores,
    puedeGestionar = false,
}: {
    grupos: GrupoTrabajo[];
    empleados: Empleado[];
    conductores: Conductor[];
    puedeGestionar?: boolean;
}) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('flota.grupos-trabajo.create');
    const canEdit = hasPermission('flota.grupos-trabajo.edit');
    const canDelete = hasPermission('flota.grupos-trabajo.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<GrupoTrabajo | null>(null);
    const [verOpen, setVerOpen] = useState(false);
    const [grupoVer, setGrupoVer] = useState<GrupoTrabajo | null>(null);
    const [busqueda, setBusqueda] = useState('');
    const [buscarConductor, setBuscarConductor] = useState('');
    const [buscarEmpleado, setBuscarEmpleado] = useState('');
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        errors,
    } = useForm({
        nombre: '',
        descripcion: '',
        color: '#3B82F6',
        estado: 'activo',
        miembros: [] as number[],
        conductores: [] as number[],
    });

    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);

    const handleImportCSV = () => {
        csvInputRef.current?.click();
    };

    const handleImportExcel = () => {
        excelInputRef.current?.click();
    };

    const handleFileChange = (
        e: React.ChangeEvent<HTMLInputElement>,
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        type: 'csv' | 'excel',
    ) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        router.post('/grupos-trabajo/importar', formData, {
            forceFormData: true,
            onSuccess: () => {
                e.target.value = '';
            },
        });
    };

    const gruposFiltrados = useMemo(() => {
        if (!busqueda) return grupos;
        const busca = busqueda.toLowerCase();
        return grupos.filter(
            (g) =>
                g.nombre.toLowerCase().includes(busca) ||
                (g.descripcion || '').toLowerCase().includes(busca),
        );
    }, [grupos, busqueda]);

    const conductoresFiltradosModal = useMemo(() => {
        if (!buscarConductor) return conductores;
        const b = buscarConductor.toLowerCase();
        return conductores.filter(
            (c) =>
                c.nombre.toLowerCase().includes(b) ||
                (c.rut && c.rut.toLowerCase().includes(b)),
        );
    }, [conductores, buscarConductor]);

    const empleadosFiltradosModal = useMemo(() => {
        if (!buscarEmpleado) return empleados;
        const b = buscarEmpleado.toLowerCase();
        return empleados.filter((e) => e.name.toLowerCase().includes(b));
    }, [empleados, buscarEmpleado]);

    const handleOpenNew = () => {
        reset();
        setData((prev) => ({ ...prev, miembros: [], conductores: [] }));
        setEditando(null);
        setIsOpen(true);
    };

    const handleOpenEdit = (grupo: GrupoTrabajo) => {
        setData({
            nombre: grupo.nombre,
            descripcion: grupo.descripcion || '',
            color: grupo.color,
            estado: grupo.estado,
            miembros: grupo.miembros?.map((m) => m.id) || [],
            conductores: grupo.conductores?.map((c) => c.id) || [],
        });
        setEditando(grupo);
        setIsOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/grupos-trabajo/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                    setEditando(null);
                },
            });
        } else {
            post('/grupos-trabajo', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Estás seguro de eliminar este grupo?')) {
            destroy(`/grupos-trabajo/${id}`);
        }
    };

    const toggleMiembro = (id: number) => {
        const current = data.miembros || [];
        if (current.includes(id)) {
            setData(
                'miembros',
                current.filter((m) => m !== id),
            );
        } else {
            setData('miembros', [...current, id]);
        }
    };

    const toggleConductor = (id: number) => {
        const current = data.conductores || [];
        if (current.includes(id)) {
            setData(
                'conductores',
                current.filter((m) => m !== id),
            );
        } else {
            setData('conductores', [...current, id]);
        }
    };

    const getEstadoBadge = (estado: string) => {
        return estado === 'activo' ? (
            <Badge className="bg-green-100 text-green-700 hover:bg-green-100">
                Activo
            </Badge>
        ) : (
            <Badge className="bg-gray-100 text-gray-700 hover:bg-gray-100">
                Inactivo
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Grupos de Trabajo" />
            <div className="flex flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold md:text-3xl">
                            {puedeGestionar
                                ? 'Grupos de Trabajo'
                                : 'Mis Grupos de Trabajo'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {puedeGestionar
                                ? 'Administra los equipos de trabajo'
                                : 'Los grupos de trabajo a los que perteneces'}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.get('/grupos-trabajo/rendimiento')}
                            className="gap-2"
                        >
                            <BarChart3 className="h-4 w-4" />
                            Rendimiento
                        </Button>
                        {puedeGestionar && canCreate && (
                            <>
                                <Button onClick={handleOpenNew} className="gap-2">
                                    <Plus className="h-4 w-4" />
                                    Nuevo Grupo
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="h-9 gap-2 rounded-xl border-muted-foreground/10 font-bold"
                                        >
                                            <Download className="h-4 w-4 text-primary" />
                                            <span>Herramientas</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="w-48"
                                    >
                                        <DropdownMenuItem onClick={handleImportCSV}>
                                            <Upload className="mr-2 h-4 w-4" />
                                            Importar CSV
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={handleImportExcel}
                                        >
                                            <FileSpreadsheet className="mr-2 h-4 w-4" />
                                            Importar Excel
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onClick={() =>
                                                router.get(
                                                    '/grupos-trabajo/exportar?format=json',
                                                )
                                            }
                                        >
                                            <FileJson className="mr-2 h-4 w-4" />
                                            Exportar JSON
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() =>
                                                router.get(
                                                    '/grupos-trabajo/exportar?format=excel',
                                                )
                                            }
                                        >
                                            <FileSpreadsheet className="mr-2 h-4 w-4" />
                                            Exportar Excel
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </>
                        )}
                    </div>
                </div>

                <Card className="overflow-hidden border-none shadow-xl shadow-foreground/5">
                    <CardHeader className="bg-gradient-to-r from-primary/5 to-transparent pb-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Users className="h-5 w-5 text-primary" />
                                <CardTitle>Equipos de Trabajo</CardTitle>
                            </div>
                            <div className="flex items-center gap-2">
                                <CardDescription className="hidden sm:block">
                                    {grupos.length} grupos
                                </CardDescription>
                                <div className="flex items-center gap-1 rounded-lg border bg-muted/30 p-0.5">
                                    <button
                                        onClick={() => setViewMode('table')}
                                        className={`rounded-md p-1.5 transition-colors ${
                                            viewMode === 'table'
                                                ? 'bg-white text-primary shadow-sm'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                        title="Vista tabla"
                                    >
                                        <List className="h-4 w-4" />
                                    </button>
                                    <button
                                        onClick={() => setViewMode('cards')}
                                        className={`rounded-md p-1.5 transition-colors ${
                                            viewMode === 'cards'
                                                ? 'bg-white text-primary shadow-sm'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                        title="Vista tarjetas"
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="flex flex-col gap-4 border-b border-muted/30 bg-muted/20 p-4 md:flex-row md:items-center">
                            <div className="relative flex-1">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Buscar grupos por nombre o descripción..."
                                    value={busqueda}
                                    onChange={(e) => setBusqueda(e.target.value)}
                                    className="h-10 border-none bg-background/50 pl-10 focus-visible:ring-primary/20"
                                />
                            </div>
                        </div>

                        {viewMode === 'table' ? (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                <th className="px-4 py-4 text-left">Nombre</th>
                                                <th className="px-4 py-4 text-left">Descripción</th>
                                                <th className="px-4 py-4 text-center">Estado</th>
                                                <th className="px-4 py-4 text-center">Miembros</th>
                                                <th className="px-4 py-4 text-right">Ventas</th>
                                                <th className="px-4 py-4 text-right">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-muted/50">
                                            {gruposFiltrados.length === 0 ? (
                                                <tr>
                                                    <td colSpan={6} className="py-16 text-center">
                                                        <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                                            <Users className="h-10 w-10 opacity-20" />
                                                            <p className="font-medium">No se encontraron grupos</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ) : (
                                                gruposFiltrados.map((grupo) => (
                                                    <tr key={grupo.id} className="group transition-colors hover:bg-muted/30">
                                                        <td className="px-4 py-4">
                                                            <div className="flex items-center gap-3">
                                                                <div
                                                                    className="flex h-10 w-10 items-center justify-center rounded-xl text-white shadow-sm"
                                                                    style={{ backgroundColor: grupo.color || '#3b82f6' }}
                                                                >
                                                                    <Users className="h-5 w-5" />
                                                                </div>
                                                                <div>
                                                                    <div className="text-sm font-bold tracking-tight">{grupo.nombre}</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="max-w-[200px] truncate px-4 py-4 text-sm text-muted-foreground">
                                                            {grupo.descripcion || '-'}
                                                        </td>
                                                        <td className="px-4 py-4 text-center">{getEstadoBadge(grupo.estado)}</td>
                                                        <td className="px-4 py-4 text-center">
                                                            <div className="flex items-center justify-center gap-1.5">
                                                                <Users className="h-3.5 w-3.5 text-muted-foreground" />
                                                                <span className="text-xs font-medium">
                                                                    {grupo.miembros?.length || 0}
                                                                </span>
                                                                {grupo.conductores?.length ? (
                                                                    <>
                                                                        <Truck className="ml-1 h-3.5 w-3.5 text-muted-foreground" />
                                                                        <span className="text-xs font-medium">{grupo.conductores.length}</span>
                                                                    </>
                                                                ) : null}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-4 text-right font-mono text-sm font-bold">
                                                             {new Intl.NumberFormat(currency.locale, { style: 'currency', currency: currency.code }).format(grupo.total_ventas || 0)}
                                                        </td>
                                                        <td className="px-4 py-4 text-right">
                                                            <div className="flex justify-end gap-1">
                                                                <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:bg-muted" onClick={() => { setGrupoVer(grupo); setVerOpen(true); }}>
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                                {puedeGestionar && canEdit && (
                                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-primary hover:bg-primary/10" onClick={() => handleOpenEdit(grupo)}>
                                                                        <Pencil className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                                {puedeGestionar && canDelete && (
                                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:bg-destructive/10" onClick={() => handleDelete(grupo.id)}>
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        ) : (
                            <>
                                {gruposFiltrados.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center py-16 text-center">
                                        <Users className="mb-4 h-14 w-14 text-muted-foreground/30" />
                                        <h3 className="text-lg font-semibold">
                                            {puedeGestionar
                                                ? 'No hay grupos de trabajo'
                                                : 'No perteneces a ningún grupo'}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {puedeGestionar
                                                ? 'Crea tu primer grupo de trabajo'
                                                : 'Contacta a tu administrador'}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
                                        {gruposFiltrados.map((grupo) => (
                                            <div
                                                key={grupo.id}
                                                className="group relative overflow-hidden rounded-xl border bg-card transition-all hover:shadow-lg"
                                            >
                                                <div
                                                    className="relative flex h-32 items-end p-5"
                                                    style={{ backgroundColor: grupo.color || '#3b82f6' }}
                                                >
                                                    <div className="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent" />
                                                    <div className="relative z-10">
                                                        <Badge className="mb-2 w-fit border-none bg-white/20 px-2 py-0.5 text-[9px] font-bold tracking-widest text-white uppercase hover:bg-white/30">
                                                            Grupo
                                                        </Badge>
                                                        <h3 className="text-lg font-black text-white">{grupo.nombre}</h3>
                                                    </div>
                                                    <div className="absolute top-3 right-3 z-10">
                                                        {getEstadoBadge(grupo.estado)}
                                                    </div>
                                                </div>
                                                <div className="space-y-3 p-4">
                                                    {grupo.descripcion && (
                                                        <p className="text-xs text-muted-foreground line-clamp-2">{grupo.descripcion}</p>
                                                    )}
                                                    {(grupo.miembros?.length || grupo.conductores?.length) ? (
                                                        <div className="flex flex-wrap gap-1">
                                                            {grupo.miembros?.slice(0, 3).map((m) => (
                                                                <Badge key={m.id} variant="secondary" className="text-[10px]">{m.name}</Badge>
                                                            ))}
                                                            {grupo.miembros && grupo.miembros.length > 3 && (
                                                                <Badge variant="outline" className="text-[10px]">+{grupo.miembros.length - 3}</Badge>
                                                            )}
                                                            {grupo.conductores?.slice(0, 2).map((c) => (
                                                                <Badge key={c.id} variant="secondary" className="bg-blue-50 text-[10px] text-blue-700">
                                                                    <Truck className="mr-0.5 h-2.5 w-2.5" />{c.nombre}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-[10px] italic text-muted-foreground">Sin miembros asignados</p>
                                                    )}
                                                    <div className="grid grid-cols-2 gap-2 rounded-lg bg-muted/30 p-3">
                                                        <div>
                                                            <p className="text-[9px] font-bold text-muted-foreground uppercase">Ventas</p>
                                                             <p className="truncate text-xs font-bold">{new Intl.NumberFormat(currency.locale, { style: 'currency', currency: currency.code }).format(grupo.total_ventas || 0)}</p>
                                                             <p className="text-[9px] text-muted-foreground">{grupo.cantidad_ventas || 0} pedidos</p>
                                                         </div>
                                                         <div className="text-right">
                                                             <p className="text-[9px] font-bold text-muted-foreground uppercase">Producción</p>
                                                             <p className="text-xs font-bold text-orange-600">{(grupo.total_kg || 0).toLocaleString(currency.locale)} Kg</p>
                                                             <p className="text-[9px] font-medium text-blue-600">{(grupo.total_l || 0).toLocaleString(currency.locale)} L</p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-1 pt-1">
                                                        <Button variant="ghost" size="sm" className="h-8 px-2 text-xs" onClick={() => { setGrupoVer(grupo); setVerOpen(true); }}>
                                                            <Eye className="mr-1 h-3.5 w-3.5" /> Ver
                                                        </Button>
                                                        {puedeGestionar && canEdit && (
                                                            <Button variant="ghost" size="sm" className="h-8 px-2 text-xs" onClick={() => handleOpenEdit(grupo)}>
                                                                <Pencil className="mr-1 h-3.5 w-3.5" /> Editar
                                                            </Button>
                                                        )}
                                                        {puedeGestionar && canDelete && (
                                                            <Button variant="ghost" size="sm" className="h-8 px-2 text-xs text-destructive hover:text-destructive" onClick={() => handleDelete(grupo.id)}>
                                                                <Trash2 className="mr-1 h-3.5 w-3.5" /> Eliminar
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none shadow-2xl md:max-w-2xl">
                    <DialogHeader className="border-b bg-gradient-to-r from-primary/5 to-transparent px-6 pt-6 pb-4">
                        <DialogTitle className="text-xl font-black tracking-tight text-primary">
                            {editando
                                ? 'Editar Grupo de Trabajo'
                                : 'Nuevo Grupo de Trabajo'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="px-6 py-5">
                        <div className="space-y-5">
                            <div className="space-y-2">
                                <Label htmlFor="nombre">Nombre</Label>
                                <Input
                                    id="nombre"
                                    value={data.nombre}
                                    onChange={(e) =>
                                        setData('nombre', e.target.value)
                                    }
                                    placeholder="Ej: Equipo de entregas"
                                />
                                {errors.nombre && (
                                    <p className="text-sm text-red-500">
                                        {errors.nombre}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="descripcion">Descripción</Label>
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
                                    }
                                    placeholder="Descripción opcional"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="color">Color</Label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="color"
                                        id="color"
                                        value={data.color}
                                        onChange={(e) =>
                                            setData('color', e.target.value)
                                        }
                                        className="h-10 w-14 cursor-pointer rounded border"
                                    />
                                    <Input
                                        value={data.color}
                                        onChange={(e) =>
                                            setData('color', e.target.value)
                                        }
                                        className="flex-1"
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="estado">Estado</Label>
                                <select
                                    id="estado"
                                    value={data.estado}
                                    onChange={(e) =>
                                        setData('estado', e.target.value)
                                    }
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label>Conductores</Label>
                                    <Input
                                        placeholder="Buscar conductor..."
                                        value={buscarConductor}
                                        onChange={(e) =>
                                            setBuscarConductor(e.target.value)
                                        }
                                        className="h-7 w-48 text-xs"
                                    />
                                </div>
                                <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
                                    {conductoresFiltradosModal.length === 0 ? (
                                        <p className="p-2 text-sm text-muted-foreground">
                                            No hay conductores disponibles o
                                            coincidentes
                                        </p>
                                    ) : (
                                        conductoresFiltradosModal.map(
                                            (conductor) => (
                                                <label
                                                    key={conductor.id}
                                                    className="flex cursor-pointer items-center gap-2 rounded p-1 hover:bg-accent"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            data.conductores?.includes(
                                                                conductor.id,
                                                            ) || false
                                                        }
                                                        onChange={() =>
                                                            toggleConductor(
                                                                conductor.id,
                                                            )
                                                        }
                                                        className="h-4 w-4 rounded border-gray-300"
                                                    />
                                                    <Truck className="h-4 w-4 text-muted-foreground" />
                                                    <span className="text-sm">
                                                        {conductor.nombre} -{' '}
                                                        {conductor.rut}
                                                    </span>
                                                </label>
                                            ),
                                        )
                                    )}
                                </div>
                                {errors.conductores && (
                                    <p className="mt-1 text-sm text-red-500">
                                        Error en conductores:{' '}
                                        {errors.conductores}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label>Empleados</Label>
                                    <Input
                                        placeholder="Buscar empleado..."
                                        value={buscarEmpleado}
                                        onChange={(e) =>
                                            setBuscarEmpleado(e.target.value)
                                        }
                                        className="h-7 w-48 text-xs"
                                    />
                                </div>
                                <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
                                    {empleadosFiltradosModal.length === 0 ? (
                                        <p className="p-2 text-sm text-muted-foreground">
                                            No hay empleados disponibles o
                                            coincidentes
                                        </p>
                                    ) : (
                                        empleadosFiltradosModal.map(
                                            (empleado) => (
                                                <label
                                                    key={empleado.id}
                                                    className="flex cursor-pointer items-center gap-2 rounded p-1 hover:bg-accent"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            data.miembros?.includes(
                                                                empleado.id,
                                                            ) || false
                                                        }
                                                        onChange={() =>
                                                            toggleMiembro(
                                                                empleado.id,
                                                            )
                                                        }
                                                        className="h-4 w-4 rounded border-gray-300"
                                                    />
                                                    <Users className="h-4 w-4 text-muted-foreground" />
                                                    <span className="text-sm">
                                                        {empleado.name}
                                                    </span>
                                                </label>
                                            ),
                                        )
                                    )}
                                </div>
                                {errors.miembros && (
                                    <p className="mt-1 text-sm text-red-500">
                                        Error en empleados: {errors.miembros}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="flex justify-end gap-3 border-t pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full"
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" className="rounded-full">
                                {editando ? 'Guardar' : 'Crear'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <input
                ref={csvInputRef}
                type="file"
                accept=".csv"
                className="hidden"
                onChange={(e) => handleFileChange(e, 'csv')}
            />
            <input
                ref={excelInputRef}
                type="file"
                accept=".xlsx,.xls"
                className="hidden"
                onChange={(e) => handleFileChange(e, 'excel')}
            />

            {/* Modal de Ver Grupo */}
            <Dialog open={verOpen} onOpenChange={setVerOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-3xl">
                    {grupoVer && (
                        <>
                            <DialogHeader className="relative overflow-visible bg-gradient-to-r from-primary/10 to-transparent p-6 pb-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div
                                            className="flex h-12 w-12 items-center justify-center rounded-2xl text-white"
                                            style={{
                                                backgroundColor:
                                                    grupoVer.color || '#3b82f6',
                                            }}
                                        >
                                            <Users className="h-6 w-6" />
                                        </div>
                                        <div>
                                            <DialogTitle className="text-2xl font-black tracking-tight text-foreground">
                                                {grupoVer.nombre}
                                            </DialogTitle>
                                            <p className="text-sm text-muted-foreground">
                                                Grupo de Trabajo
                                            </p>
                                        </div>
                                    </div>
                                    <Badge
                                        className={`${
                                            grupoVer.estado === 'activo'
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-800'
                                        } border-none`}
                                    >
                                        {grupoVer.estado === 'activo'
                                            ? 'Activo'
                                            : 'Inactivo'}
                                    </Badge>
                                </div>
                            </DialogHeader>

                            <div className="space-y-6 p-6">
                                {grupoVer.descripcion && (
                                    <div className="rounded-2xl border border-muted/50 bg-muted/30 p-4">
                                        <div className="mb-1 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                            Descripción
                                        </div>
                                        <p className="text-sm text-foreground">
                                            {grupoVer.descripcion}
                                        </p>
                                    </div>
                                )}

                                {/* Miembros */}
                                <div className="space-y-3">
                                    <h3 className="flex items-center gap-2 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        <Users className="h-4 w-4" />
                                        Miembros (
                                        {grupoVer.miembros?.length || 0})
                                    </h3>
                                    <div className="flex flex-wrap gap-2">
                                        {grupoVer.miembros?.length ? (
                                            grupoVer.miembros.map((m) => (
                                                <Badge
                                                    key={m.id}
                                                    variant="outline"
                                                    className="px-3 py-1"
                                                >
                                                    {m.name}
                                                </Badge>
                                            ))
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                Sin miembros asignados
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Conductores */}
                                {grupoVer.conductores &&
                                    grupoVer.conductores.length > 0 && (
                                        <div className="space-y-3">
                                            <h3 className="flex items-center gap-2 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                <Truck className="h-4 w-4" />
                                                Conductores (
                                                {grupoVer.conductores.length})
                                            </h3>
                                            <div className="flex flex-wrap gap-2">
                                                {grupoVer.conductores.map(
                                                    (c) => (
                                                        <Badge
                                                            key={c.id}
                                                            variant="outline"
                                                            className="px-3 py-1"
                                                        >
                                                            {c.nombre}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}

                                {/* Métricas */}
                                {(grupoVer.total_ventas ||
                                    grupoVer.cantidad_ventas ||
                                    grupoVer.total_kg ||
                                    grupoVer.total_l) && (
                                    <div className="space-y-3">
                                        <h3 className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                            Métricas
                                        </h3>
                                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                            {grupoVer.cantidad_ventas !==
                                                undefined && (
                                                <div className="rounded-2xl border border-muted/50 bg-muted/30 p-4 text-center">
                                                    <div className="mb-1 text-xs font-bold text-muted-foreground uppercase">
                                                        Ventas
                                                    </div>
                                                    <div className="text-xl font-black text-foreground">
                                                        {
                                                            grupoVer.cantidad_ventas
                                                        }
                                                    </div>
                                                </div>
                                            )}
                                            {grupoVer.total_ventas !==
                                                undefined && (
                                                <div className="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 text-center">
                                                    <div className="mb-1 text-xs font-bold text-blue-600 uppercase">
                                                        Total
                                                    </div>
                                                    <div className="text-xl font-black text-foreground">
                                                        $
                                                        {grupoVer.total_ventas?.toLocaleString()}
                                                    </div>
                                                </div>
                                            )}
                                            {grupoVer.total_kg !==
                                                undefined && (
                                                <div className="rounded-2xl border border-green-100 bg-green-50/50 p-4 text-center">
                                                    <div className="mb-1 text-xs font-bold text-green-600 uppercase">
                                                        Kg
                                                    </div>
                                                    <div className="text-xl font-black text-foreground">
                                                        {grupoVer.total_kg?.toFixed(
                                                            1,
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                            {grupoVer.total_l !== undefined && (
                                                <div className="rounded-2xl border border-purple-100 bg-purple-50/50 p-4 text-center">
                                                    <div className="mb-1 text-xs font-bold text-purple-600 uppercase">
                                                        Litros
                                                    </div>
                                                    <div className="text-xl font-black text-foreground">
                                                        {grupoVer.total_l?.toFixed(
                                                            1,
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Fecha de creación */}
                                <div className="flex items-center justify-between rounded-2xl border border-muted/50 bg-muted/30 p-4">
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-xs font-bold text-muted-foreground uppercase">
                                            Creado el
                                        </span>
                                    </div>
                                    <span className="font-medium text-foreground">
                                         {new Date(
                                             grupoVer.created_at,
                                         ).toLocaleDateString(currency.locale)}
                                     </span>
                                </div>
                            </div>

                            <DialogFooter className="p-6 pt-0">
                                <Button
                                    variant="outline"
                                    onClick={() => setVerOpen(false)}
                                    className="rounded-full px-8 font-bold"
                                >
                                    Cerrar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
