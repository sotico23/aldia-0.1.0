import { Head, router, useForm } from '@inertiajs/react';
import { FileSpreadsheet, Download, Upload } from 'lucide-react';
import { Pencil, Plus, Trash2, Search, X, Eye, LayoutGrid, List, MapPin, Phone, Warehouse } from 'lucide-react';
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
    DialogTitle
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
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Empleado {
    id: number;
    nombre: string;
    apellido: string;
    cargo: string | null;
}

interface Almacen {
    id: number;
    nombre: string;
    codigo: string;
    direccion: string | null;
    telefono: string | null;
    responsable: string | null;
    capacidad: number | null;
    tipo: string;
    activo: boolean;
    notas: string | null;
    empleados?: Empleado[];
    imagenes?: string[];
    video?: string;
}

interface AlmacenFormData {
    nombre: string;
    codigo: string;
    direccion: string;
    telefono: string;
    responsable_id: string | number;
    capacidad: number;
    tipo: string;
    activo: boolean;
    notas: string;
    imagenes: File[];
    video: File | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacenes', href: '/almacenes' },
];

const tipos = ['principal', 'secundario', 'tienda'];

export default function Index({
    almacenes,
    empleados,
}: {
    almacenes: {
        data: Almacen[];
        links: any[];
        meta?: any;
        total: number;
    };
    empleados: Empleado[];
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('inventario.almacenes.create');
    const canEdit = hasPermission('inventario.almacenes.edit');
    const canDelete = hasPermission('inventario.almacenes.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [editando, setEditando] = useState<Almacen | null>(null);
    const [viendo, setViendo] = useState<Almacen | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const {
        data,
        setData,
        delete: destroy,
        reset,
    } = useForm<AlmacenFormData>({
        nombre: '',
        codigo: '',
        direccion: '',
        telefono: '',
        responsable_id: '' as string | number,
        capacidad: 0,
        tipo: 'principal',
        activo: true,
        notas: '',
        imagenes: [],
        video: null,
    });

    const [filtros, setFiltros] = useState({
        busqueda: '',
        tipo: '',
        activo: '',
    });

    const [previewImagenes, setPreviewImagenes] = useState<string[]>([]);
    const [previewVideo, setPreviewVideo] = useState<string | null>(null);
    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);

    const almacenesFiltrados = useMemo(() => {
        return almacenes.data.filter((a) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !a.nombre.toLowerCase().includes(busca) &&
                    !a.codigo.toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.tipo && a.tipo !== filtros.tipo) return false;
            if (filtros.activo !== '') {
                const isActivo = filtros.activo === '1';
                if (a.activo !== isActivo) return false;
            }
            return true;
        });
    }, [almacenes, filtros]);

    const limpiarFiltros = () => {
        setFiltros({ busqueda: '', tipo: '', activo: '' });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Crear FormData manualmente para archivos
        const formData = new FormData();
        formData.append('nombre', data.nombre);
        formData.append('codigo', data.codigo);
        formData.append('direccion', data.direccion);
        formData.append('telefono', data.telefono);
        formData.append('capacidad', data.capacidad.toString());
        formData.append('tipo', data.tipo);
        formData.append('activo', data.activo ? '1' : '0');
        formData.append('notas', data.notas);
        if (data.responsable_id) {
            formData.append('responsable_id', data.responsable_id.toString());
        }

        // Agregar imágenes (solo archivos File válidos)
        const imagenesValidas = data.imagenes.filter(
            (img) => img && img instanceof File,
        );
        if (imagenesValidas.length > 0) {
            imagenesValidas.forEach((img) => {
                formData.append('imagenes[]', img);
            });
        }

        // Agregar video (solo si es archivo nuevo)
        if (data.video && data.video instanceof File) {
            formData.append('video', data.video);
        }

        if (editando) {
            formData.append('_method', 'PUT');
            router.post(`/almacenes/${editando.id}`, formData, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                    setData('imagenes', []);
                    setData('video', null);
                    alert('Almacén actualizado correctamente');
                },
                onError: (errors: any) => {
                    if (errors.codigo) {
                        alert('Error: ' + errors.codigo);
                    } else {
                        alert('Error al actualizar');
                    }
                },
            });
        } else {
            router.post('/almacenes', formData, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                    setData('imagenes', []);
                    setData('video', null);
                    alert('Almacén creado correctamente');
                },
                onError: (errors: any) => {
                    if (errors.codigo) {
                        alert('Error: ' + errors.codigo);
                    } else {
                        alert('Error al crear');
                    }
                },
            });
        }
    };

    const handleEdit = (almacen: Almacen) => {
        setEditando(almacen);
        let respId: string | number = '';
        if (almacen.empleados && almacen.empleados.length > 0) {
            respId = almacen.empleados[0].id;
        }
        setData({
            nombre: almacen.nombre,
            codigo: almacen.codigo,
            direccion: almacen.direccion || '',
            telefono: almacen.telefono || '',
            responsable_id: respId,
            capacidad: almacen.capacidad || 0,
            tipo: almacen.tipo,
            activo: Boolean(almacen.activo),
            notas: almacen.notas || '',
            imagenes: [],
            video: null,
        });
        // Cargar previews de imágenes existentes
        if (almacen.imagenes && almacen.imagenes.length > 0) {
            setPreviewImagenes(almacen.imagenes);
        } else {
            setPreviewImagenes(['', '', '']);
        }
        // Cargar preview de video existente
        setPreviewVideo(almacen.video || null);
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setData({
            nombre: '',
            codigo: '',
            direccion: '',
            telefono: '',
            responsable_id: '',
            capacidad: 0,
            tipo: 'principal',
            activo: true,
            notas: '',
            imagenes: [],
            video: null,
        });
        setPreviewImagenes(['', '', '']);
        setPreviewVideo(null);
        setEditando(null);
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar almacén?')) {
            destroy(`/almacenes/${id}`);
        }
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleExportCsv = () => {
        window.location.href = '/almacenes/export';
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleExportExcel = () => {
        window.location.href = '/almacenes/export-excel';
    };

    const handleImportCsv = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        e.target.value = '';

        const formData = new FormData();
        formData.append('archivo', file);

        router.post('/almacenes/import', formData, {
            onSuccess: () => {
                alert('Importación CSV completada');
            },
            onError: (err) => {
                console.error(err);
                alert('Error al importar CSV: ' + Object.values(err)[0]);
            },
        });
    };

    const handleImportExcel = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        e.target.value = '';

        const formData = new FormData();
        formData.append('archivo', file);

        router.post('/almacenes/import-excel', formData, {
            onSuccess: () => {
                alert('Importación Excel completada');
            },
            onError: (err) => {
                console.error(err);
                alert('Error al importar Excel: ' + Object.values(err)[0]);
            },
        });
    };

    return (
        <>
            <Head title="Almacenes" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Almacenes</h1>
                            <p className="text-muted-foreground">
                                Gestión de almacenes
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 md:justify-end">
                            <div className="flex items-center gap-2">
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
                                        <DropdownMenuItem onSelect={(e) => { e.preventDefault(); csvInputRef.current?.click(); }}>
                                            <Upload className="mr-2 h-4 w-4" />
                                            Importar CSV
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onSelect={(e) => { e.preventDefault(); excelInputRef.current?.click(); }}>
                                            <FileSpreadsheet className="mr-2 h-4 w-4" />
                                            Importar Excel
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onClick={() =>
                                                (window.location.href =
                                                    '/almacenes/export')
                                            }
                                        >
                                            <FileSpreadsheet className="mr-2 h-4 w-4" />
                                            Exportar CSV
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() =>
                                                (window.location.href =
                                                    '/almacenes/export-excel')
                                            }
                                        >
                                            <FileSpreadsheet className="mr-2 h-4 w-4" />
                                            Exportar Excel
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                {canCreate && (
                                    <Button onClick={handleNew}>
                                        <Plus className="mr-2 h-4 w-4" /> Nuevo
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Almacenes</CardTitle>
                                    <CardDescription>
                                        {almacenes.total} registros
                                    </CardDescription>
                                </div>
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
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar..."
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
                                <select
                                    value={filtros.tipo}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            tipo: e.target.value,
                                        })
                                    }
                                    className="flex h-9 min-w-[120px] rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Todos los tipos</option>
                                    {tipos.map((t) => (
                                        <option key={t} value={t}>
                                            {t.charAt(0).toUpperCase() +
                                                t.slice(1)}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    value={filtros.activo}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            activo: e.target.value,
                                        })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Todos</option>
                                    <option value="1">Activos</option>
                                    <option value="0">Inactivos</option>
                                </select>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9"
                                    onClick={limpiarFiltros}
                                >
                                    <X className="mr-1 h-4 w-4" /> Limpiar
                                </Button>
                            </div>
                            {viewMode === 'table' ? (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-xs sm:text-sm">
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="w-16 pb-3 text-left font-medium">
                                                        Imagen
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Nombre
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Código
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Tipo
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Estado
                                                    </th>
                                                    <th className="pb-3 text-right font-medium">
                                                        Acciones
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {almacenesFiltrados.map((a) => (
                                                    <tr
                                                        key={a.id}
                                                        className="border-b transition-colors hover:bg-muted/30"
                                                    >
                                                        <td className="px-4 py-2">
                                                            {a.imagenes &&
                                                            a.imagenes.length > 0 ? (
                                                                <img
                                                                    src={a.imagenes[0]}
                                                                    alt={a.nombre}
                                                                    className="h-10 w-10 rounded-lg border object-cover"
                                                                />
                                                            ) : (
                                                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                                    <Warehouse className="h-5 w-5" />
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-2 font-medium">
                                                            {a.nombre}
                                                        </td>
                                                        <td className="px-4 py-2 font-mono text-muted-foreground">
                                                            {a.codigo}
                                                        </td>
                                                        <td className="px-4 py-2 capitalize">
                                                            {a.tipo}
                                                        </td>
                                                        <td className="px-4 py-2">
                                                            <Badge
                                                                variant={
                                                                    a.activo
                                                                        ? 'default'
                                                                        : 'destructive'
                                                                }
                                                                className="px-1.5 py-0 text-[10px]"
                                                            >
                                                                {a.activo
                                                                    ? 'Activo'
                                                                    : 'Inactivo'}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-4 py-2 text-right">
                                                            <div className="flex justify-end gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 w-8 p-0"
                                                                    onClick={() => {
                                                                        setViendo(a);
                                                                        setIsViewOpen(
                                                                            true,
                                                                        );
                                                                    }}
                                                                >
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                                {canEdit && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="h-8 w-8 p-0"
                                                                        onClick={() =>
                                                                            handleEdit(a)
                                                                        }
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
                                                                                a.id,
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
                                                {almacenesFiltrados.length === 0 && (
                                                    <tr>
                                                        <td
                                                            colSpan={5}
                                                            className="py-8 text-center text-muted-foreground"
                                                        >
                                                            Sin resultados
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <Pagination
                                        links={almacenes.links}
                                        meta={
                                            almacenes.meta || {
                                                from: (almacenes as any).from,
                                                to: (almacenes as any).to,
                                                total: almacenes.total,
                                            }
                                        }
                                    />
                                </>
                            ) : (
                                <>
                                    {almacenesFiltrados.length === 0 ? (
                                        <div className="py-12 text-center text-muted-foreground">
                                            <Warehouse className="mx-auto mb-3 h-12 w-12 opacity-30" />
                                            <p>Sin resultados</p>
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            {almacenesFiltrados.map((a) => (
                                                <div
                                                    key={a.id}
                                                    className="group relative overflow-hidden rounded-xl border bg-card transition-all hover:shadow-lg"
                                                >
                                                    <div className="relative h-36 overflow-hidden bg-muted">
                                                        {a.imagenes && a.imagenes.length > 0 ? (
                                                            <img
                                                                src={a.imagenes[0]}
                                                                alt={a.nombre}
                                                                className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                            />
                                                        ) : (
                                                            <div className="flex h-full items-center justify-center">
                                                                <Warehouse className="h-12 w-12 text-muted-foreground/30" />
                                                            </div>
                                                        )}
                                                        <div className="absolute top-2 right-2">
                                                            <Badge
                                                                variant={a.activo ? 'default' : 'destructive'}
                                                                className="px-2 py-0.5 text-[10px]"
                                                            >
                                                                {a.activo ? 'Activo' : 'Inactivo'}
                                                            </Badge>
                                                        </div>
                                                        <div className="absolute top-2 left-2">
                                                            <Badge variant="secondary" className="px-2 py-0.5 text-[10px] capitalize">
                                                                {a.tipo}
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                    <div className="space-y-2 p-4">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div>
                                                                <h3 className="font-semibold leading-tight">{a.nombre}</h3>
                                                                <p className="font-mono text-xs text-muted-foreground">{a.codigo}</p>
                                                            </div>
                                                        </div>
                                                        <div className="space-y-1 text-xs text-muted-foreground">
                                                            {a.direccion && (
                                                                <div className="flex items-center gap-1.5">
                                                                    <MapPin className="h-3.5 w-3.5 shrink-0" />
                                                                    <span className="truncate">{a.direccion}</span>
                                                                </div>
                                                            )}
                                                            {a.telefono && (
                                                                <div className="flex items-center gap-1.5">
                                                                    <Phone className="h-3.5 w-3.5 shrink-0" />
                                                                    <span>{a.telefono}</span>
                                                                </div>
                                                            )}
                                                            {a.capacidad && a.capacidad > 0 && (
                                                                <div className="flex items-center gap-1.5">
                                                                    <Warehouse className="h-3.5 w-3.5 shrink-0" />
                                                                    <span>Capacidad: {a.capacidad} und</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-1 pt-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 px-2 text-xs"
                                                                onClick={() => {
                                                                    setViendo(a);
                                                                    setIsViewOpen(true);
                                                                }}
                                                            >
                                                                <Eye className="mr-1 h-3.5 w-3.5" /> Ver
                                                            </Button>
                                                            {canEdit && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 px-2 text-xs"
                                                                    onClick={() => handleEdit(a)}
                                                                >
                                                                    <Pencil className="mr-1 h-3.5 w-3.5" /> Editar
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 px-2 text-xs text-destructive hover:text-destructive"
                                                                    onClick={() => handleDelete(a.id)}
                                                                >
                                                                    <Trash2 className="mr-1 h-3.5 w-3.5" /> Eliminar
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    <Pagination
                                        links={almacenes.links}
                                        meta={
                                            almacenes.meta || {
                                                from: (almacenes as any).from,
                                                to: (almacenes as any).to,
                                                total: almacenes.total,
                                            }
                                        }
                                    />
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>

            {/* Hidden file inputs for imports — outside dropdown to avoid focus loss */}
            <input
                ref={csvInputRef}
                type="file"
                accept=".csv,.txt"
                onChange={handleImportCsv}
                className="hidden"
            />
            <input
                ref={excelInputRef}
                type="file"
                accept=".xlsx,.xls"
                onChange={handleImportExcel}
                className="hidden"
            />

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none shadow-2xl md:max-w-2xl">
                    <DialogHeader className="border-b bg-gradient-to-r from-primary/5 to-transparent px-6 pt-6 pb-4">
                        <DialogTitle className="text-xl font-black tracking-tight text-primary">
                            {editando ? 'Editar Almacén' : 'Nuevo Almacén'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="px-4 py-4">
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-3">
                                <div className="col-span-1 space-y-1">
                                    <Label className="text-xs">Nombre</Label>
                                    <Input
                                        className="h-8 text-sm"
                                        value={data.nombre}
                                        onChange={(e) =>
                                            setData('nombre', e.target.value)
                                        }
                                        required
                                    />
                                </div>
                                <div className="col-span-1 space-y-1">
                                    <Label className="text-xs">Código</Label>
                                    <Input
                                        className="h-8 text-sm"
                                        value={data.codigo}
                                        onChange={(e) =>
                                            setData('codigo', e.target.value)
                                        }
                                        required
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-3">
                                <div className="col-span-1 space-y-1">
                                    <Label className="text-xs">Tipo</Label>
                                    <select
                                        value={data.tipo}
                                        onChange={(e) =>
                                            setData('tipo', e.target.value)
                                        }
                                        className="flex h-8 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                    >
                                        {tipos.map((t) => (
                                            <option key={t} value={t}>
                                                {t.charAt(0).toUpperCase() +
                                                    t.slice(1)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="col-span-1 space-y-1">
                                    <Label className="text-xs">Teléfono</Label>
                                    <Input
                                        className="h-8 text-sm"
                                        value={data.telefono}
                                        onChange={(e) =>
                                            setData('telefono', e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">Dirección</Label>
                                <Input
                                    className="h-8 text-sm"
                                    value={data.direccion}
                                    onChange={(e) =>
                                        setData('direccion', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-3">
                                <div className="col-span-1 space-y-1">
                                    <Label className="text-xs">Capacidad</Label>
                                    <Input
                                        type="number"
                                        className="h-8 text-sm"
                                        value={data.capacidad}
                                        onChange={(e) =>
                                            setData(
                                                'capacidad',
                                                parseInt(e.target.value) || 0,
                                            )
                                        }
                                    />
                                </div>
                                <div className="col-span-1 space-y-1">
                                    <Label className="text-xs">
                                        Responsable
                                    </Label>
                                    <select
                                        value={data.responsable_id}
                                        onChange={(e) =>
                                            setData(
                                                'responsable_id',
                                                e.target.value,
                                            )
                                        }
                                        className="flex h-8 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                    >
                                        <option value="">Sin asignar</option>
                                        {empleados.map((emp) => (
                                            <option key={emp.id} value={emp.id}>
                                                {emp.nombre} {emp.apellido}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">Notas</Label>
                                <Input
                                    className="h-8 text-sm"
                                    value={data.notas}
                                    onChange={(e) =>
                                        setData('notas', e.target.value)
                                    }
                                />
                            </div>

                            {/* Imágenes */}
                            <div className="space-y-2">
                                <Label className="text-xs">
                                    Imágenes (máx 3)
                                </Label>
                                <div className="flex gap-2">
                                    {[0, 1, 2].map((idx) => (
                                        <label
                                            key={idx}
                                            className="relative flex h-16 w-16 cursor-pointer items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-muted-foreground/25 bg-muted/20 transition-colors hover:border-primary/50"
                                        >
                                            <input
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={(e) => {
                                                    const file =
                                                        e.target.files?.[0];
                                                    if (file) {
                                                        const newImagenes = [
                                                            ...data.imagenes,
                                                        ];
                                                        newImagenes[idx] = file;
                                                        setData(
                                                            'imagenes',
                                                            newImagenes,
                                                        );
                                                        const reader =
                                                            new FileReader();
                                                        reader.onload = (
                                                            event,
                                                        ) => {
                                                            const newPreviews =
                                                                [
                                                                    ...previewImagenes,
                                                                ];
                                                            newPreviews[idx] =
                                                                event.target
                                                                    ?.result as string;
                                                            setPreviewImagenes(
                                                                newPreviews,
                                                            );
                                                        };
                                                        reader.readAsDataURL(
                                                            file,
                                                        );
                                                    }
                                                }}
                                            />
                                            {previewImagenes[idx] ? (
                                                <img
                                                    src={previewImagenes[idx]}
                                                    alt={`Preview ${idx + 1}`}
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    + IMG
                                                </span>
                                            )}
                                        </label>
                                    ))}
                                </div>
                                {previewImagenes.some((p) => p) && (
                                    <button
                                        type="button"
                                        className="text-xs text-red-500 hover:underline"
                                        onClick={() => {
                                            setPreviewImagenes(['', '', '']);
                                            setData('imagenes', []);
                                        }}
                                    >
                                        Limpiar imágenes
                                    </button>
                                )}
                            </div>

                            {/* Video con Preview */}
                            <div className="space-y-1">
                                <Label className="text-xs">
                                    Video (máx 50MB)
                                </Label>
                                {previewVideo ? (
                                    <div className="relative rounded-lg border bg-muted/30 p-2">
                                        <video
                                            controls
                                            className="h-24 w-full object-contain"
                                            src={previewVideo}
                                        />
                                        <button
                                            type="button"
                                            className="absolute top-2 right-2 rounded-full bg-red-500 px-2 py-1 text-xs text-white"
                                            onClick={() => {
                                                setPreviewVideo(null);
                                                setData('video', null);
                                            }}
                                        >
                                            X
                                        </button>
                                    </div>
                                ) : (
                                    <label className="flex h-12 cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 bg-muted/20 transition-colors hover:border-primary/50">
                                        <input
                                            type="file"
                                            accept="video/*"
                                            className="hidden"
                                            onChange={(e) => {
                                                const file =
                                                    e.target.files?.[0];
                                                if (file) {
                                                    if (
                                                        file.size >
                                                        50 * 1024 * 1024
                                                    ) {
                                                        alert(
                                                            'El video debe ser menor a 50MB',
                                                        );
                                                        return;
                                                    }
                                                    setData('video', file);
                                                    const reader =
                                                        new FileReader();
                                                    reader.onload = (event) => {
                                                        setPreviewVideo(
                                                            event.target
                                                                ?.result as string,
                                                        );
                                                    };
                                                    reader.readAsDataURL(file);
                                                }
                                            }}
                                        />
                                        <span className="text-xs text-muted-foreground">
                                            Subir video
                                        </span>
                                    </label>
                                )}
                            </div>

                            <div className="flex items-center gap-2 pt-2">
                                <input
                                    type="checkbox"
                                    id="activo"
                                    checked={data.activo}
                                    onChange={(e) =>
                                        setData('activo', e.target.checked)
                                    }
                                    className="h-4 w-4"
                                />
                                <Label
                                    htmlFor="activo"
                                    className="cursor-pointer text-xs font-normal"
                                >
                                    Activo
                                </Label>
                            </div>
                        </div>
                        <div className="flex justify-end gap-3 border-t pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setIsOpen(false);
                                    setPreviewImagenes(['', '', '']);
                                    setPreviewVideo(null);
                                }}
                                className="rounded-full"
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" className="rounded-full">
                                Guardar
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
                <DialogContent className="max-h-[85vh] max-w-[90vw] overflow-y-auto border-none bg-white p-0 shadow-xl md:max-w-2xl">
                    <DialogHeader className="relative overflow-hidden px-4 py-4 md:px-6 md:py-5">
                        <div className="absolute inset-0 bg-gradient-to-br from-amber-600 via-orange-700 to-yellow-900 opacity-90" />
                        <div className="absolute top-0 right-0 p-3 text-white opacity-15 md:p-4">
                            <Eye className="h-12 w-12 rotate-12 md:h-16 md:w-16" />
                        </div>
                        <div className="relative z-10 flex items-center justify-between pr-8 text-white">
                            <div>
                                <Badge className="mb-1 w-fit border-none bg-white/20 px-2 py-0.5 text-[9px] font-bold tracking-widest text-white uppercase">
                                    Almacén
                                </Badge>
                                <DialogTitle className="text-lg font-black tracking-tight text-white uppercase md:text-xl">
                                    {viendo?.nombre}
                                </DialogTitle>
                            </div>
                            <div
                                className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${viendo?.activo ? 'bg-green-500' : 'bg-red-500'}`}
                            >
                                {viendo?.activo ? 'Activo' : 'Inactivo'}
                            </div>
                        </div>
                    </DialogHeader>
                    {viendo && (
                        <div className="relative z-20 -mt-3 mb-3 flex max-h-[calc(85vh-100px)] flex-col gap-3 overflow-y-auto px-4 md:-mt-4 md:mb-4 md:gap-4 md:px-6">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-2">
                                    <p className="text-[8px] font-bold text-amber-600 uppercase">
                                        Código
                                    </p>
                                    <p className="truncate text-xs font-semibold text-amber-800 uppercase">
                                        {viendo.codigo}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-orange-200 bg-orange-50 p-2">
                                    <p className="text-[8px] font-bold text-orange-600 uppercase">
                                        Tipo
                                    </p>
                                    <p className="truncate text-xs font-semibold text-orange-800 uppercase">
                                        {viendo.tipo}
                                    </p>
                                </div>
                                <div
                                    className={`rounded-lg border p-2 ${viendo.activo ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'}`}
                                >
                                    <p
                                        className={`text-[8px] font-bold uppercase ${viendo.activo ? 'text-green-600' : 'text-red-600'}`}
                                    >
                                        Estado
                                    </p>
                                    <p
                                        className={`truncate text-xs font-semibold uppercase ${viendo.activo ? 'text-green-800' : 'text-red-800'}`}
                                    >
                                        {viendo.activo ? 'Activo' : 'Inactivo'}
                                    </p>
                                </div>
                            </div>
                            <Card className="border-none bg-gray-50/50 shadow-sm">
                                <CardHeader className="border-b border-gray-100 pb-2">
                                    <CardTitle className="text-sm font-bold text-gray-800">
                                        Información
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-2 p-3">
                                    {viendo.direccion && (
                                        <div className="col-span-2">
                                            <Label className="text-[9px] font-bold text-muted-foreground uppercase">
                                                Dirección
                                            </Label>
                                            <p className="text-xs">
                                                {viendo.direccion}
                                            </p>
                                        </div>
                                    )}
                                    {viendo.telefono && (
                                        <div>
                                            <Label className="text-[9px] font-bold text-muted-foreground uppercase">
                                                Teléfono
                                            </Label>
                                            <a
                                                href={`https://wa.me/${viendo.telefono.replace(/\D/g, '')}?text=Hola,+me+comunico+desde+${encodeURIComponent(viendo.nombre)}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="flex items-center gap-1 text-xs text-green-600 hover:text-green-700 hover:underline"
                                            >
                                                <svg
                                                    className="h-3 w-3"
                                                    viewBox="0 0 24 24"
                                                    fill="currentColor"
                                                >
                                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.162-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.149-.149.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.371-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                                </svg>
                                                {viendo.telefono}
                                            </a>
                                        </div>
                                    )}
                                    {viendo.capacidad &&
                                        viendo.capacidad > 0 && (
                                            <div>
                                                <Label className="text-[9px] font-bold text-muted-foreground uppercase">
                                                    Capacidad
                                                </Label>
                                                <p className="text-xs">
                                                    {viendo.capacidad} und
                                                </p>
                                            </div>
                                        )}
                                    {viendo.empleados?.length ? (
                                        <div className="col-span-2">
                                            <Label className="text-[9px] font-bold text-muted-foreground uppercase">
                                                Responsable(s)
                                            </Label>
                                            <p className="text-xs">
                                                {viendo.empleados
                                                    .map(
                                                        (e) =>
                                                            `${e.nombre} ${e.apellido}`,
                                                    )
                                                    .join(', ')}
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="col-span-2">
                                            <Label className="text-[9px] font-bold text-muted-foreground uppercase">
                                                Notas
                                            </Label>
                                            <p className="font-medium">
                                                {viendo.notas || '-'}
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Imágenes */}
                            {viendo.imagenes && viendo.imagenes.length > 0 && (
                                <Card className="border-none bg-gray-50/50 shadow-sm">
                                    <CardHeader className="border-b border-gray-100 pb-2">
                                        <CardTitle className="text-xs font-bold text-gray-800">
                                            Imágenes ({viendo.imagenes.length})
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-2">
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                            {viendo.imagenes.map((img, idx) => (
                                                <div
                                                    key={idx}
                                                    className="relative aspect-square overflow-hidden rounded border bg-muted"
                                                >
                                                    <img
                                                        src={img}
                                                        alt={`Img ${idx + 1}`}
                                                        className="h-full w-full object-cover"
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Video */}
                            {viendo.video && (
                                <Card className="border-none bg-gray-50/50 shadow-sm">
                                    <CardHeader className="border-b border-gray-100 pb-2">
                                        <CardTitle className="text-xs font-bold text-gray-800">
                                            Video
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-2">
                                        <video
                                            controls
                                            className="w-full rounded border"
                                            src={viendo.video}
                                        >
                                            Tu navegador no soporta el elemento
                                            de video.
                                        </video>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}
                    <DialogFooter className="border-t bg-gray-50 p-3">
                        <Button onClick={() => setIsViewOpen(false)}>
                            Cerrar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
