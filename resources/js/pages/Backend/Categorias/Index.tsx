import { Head, router, usePage } from '@inertiajs/react';
import {
    Check,
    Pencil,
    Plus,
    Trash2,
    Image as ImageIcon,
    X,
    Search,
    Eye,
    ShoppingBag,
    HelpCircle,
    LayoutGrid,
    List,
    Tags,
} from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { BulkActions } from '@/components/shared/BulkActions';
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';


function ActiveDropdown({
    activo,
    onChange,
}: {
    activo: boolean;
    onChange: (v: boolean) => void;
}) {
    return (
        <Select
            value={activo ? '1' : '0'}
            onValueChange={(v) => onChange(v === '1')}
        >
            <SelectTrigger
                className={`h-7 w-24 rounded-full border px-2.5 text-xs font-semibold shadow-sm transition-all focus:ring-0 focus:ring-offset-0 ${
                    activo
                        ? 'border-green-600 bg-green-500 text-white hover:bg-green-600'
                        : 'border-gray-500 bg-gray-500 text-white hover:bg-gray-600'
                }`}
            >
                <SelectValue>{activo ? 'Activo' : 'Inactivo'}</SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="1" className="text-xs">
                    <div className="flex items-center gap-2">
                        <div className="h-2 w-2 rounded-full bg-green-500" />
                        Activo
                    </div>
                </SelectItem>
                <SelectItem value="0" className="text-xs">
                    <div className="flex items-center gap-2">
                        <div className="h-2 w-2 rounded-full bg-gray-500" />
                        Inactivo
                    </div>
                </SelectItem>
            </SelectContent>
        </Select>
    );
}

interface Categoria {
    id: number;
    nombre: string;
    descripcion: string | null;
    tipo: 'producto' | 'cliente' | 'proveedor' | 'servicio';
    activo: boolean;
    imagen: string | null;
    mostrar_en_perfil: boolean;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categorias' },
];

export default function Index({
    categorias,
    tiendaSlug,
    filters = {},
}: {
    categorias: {
        data: Categoria[];
        links: any[];
        meta: { from: number; to: number; total: number };
    };
    tiendaSlug?: string | null;
    filters?: {
        search?: string;
        tipo?: string;
    };
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [editando, setEditando] = useState<Categoria | null>(null);
    const [viendo, setViendo] = useState<Categoria | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [imagenPreview, setImagenPreview] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    
    // Auth permissions setup
    const { auth } = usePage().props as any;
    const userPermissions: string[] = auth?.user?.permissions || [];
    const hasAll = userPermissions.includes('*');
    const canCreate = hasAll || userPermissions.includes('comercial.categorias.create');
    const canEdit = hasAll || userPermissions.includes('comercial.categorias.edit');
    const canDelete = hasAll || userPermissions.includes('comercial.categorias.delete');

    const [formData, setFormData] = useState({
        nombre: '',
        descripcion: '',
        tipo: 'producto' as 'producto' | 'cliente' | 'proveedor' | 'servicio',
        activo: true,
        imagen: null as File | null,
        mostrar_en_perfil: true,
    });
    const [imagenError, setImagenError] = useState<string | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [tipoFilter, setTipoFilter] = useState(filters.tipo || 'all');

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (tipoFilter !== 'all') query.tipo = tipoFilter;

            router.get('/categorias', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, tipoFilter]);

    const limpiarFiltros = () => {
        setSearchTerm('');
        setTipoFilter('all');
    };

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            const allowedTypes = [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/svg+xml',
                'image/webp',
            ];
            if (!allowedTypes.includes(file.type)) {
                setImagenError(
                    'Archivo de imagen no compatible. Formatos aceptados: JPG, JPEG, PNG, GIF, SVG, WebP',
                );
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                setImagenError('El archivo excede el tamaño máximo de 5MB');
                return;
            }
            setImagenError(null);
            setFormData({ ...formData, imagen: file });
            const reader = new FileReader();
            reader.onloadend = () => {
                setImagenPreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const removeImage = () => {
        setFormData({ ...formData, imagen: null });
        setImagenPreview(null);
        setImagenError(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const submitData = {
            _method: editando ? 'PUT' : 'POST',
            nombre: formData.nombre,
            descripcion: formData.descripcion,
            tipo: formData.tipo,
            activo: formData.activo,
            imagen: formData.imagen,
            mostrar_en_perfil: formData.mostrar_en_perfil,
        };

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                setEditando(null);
                setFormData({
                    nombre: '',
                    descripcion: '',
                    tipo: 'producto',
                    activo: true,
                    imagen: null,
                    mostrar_en_perfil: true,
                });
                setImagenPreview(null);
                setServerError(null);
            },
            onError: (errors: any) => {
                const errorMessages = Object.values(errors).flat();
                setServerError(errorMessages.join(' '));
            },
        };

        if (editando) {
            router.post(
                `/categorias/${editando.id}`,
                submitData as any,
                options,
            );
        } else {
            router.post('/categorias', submitData as any, options);
        }
    };

    const handleEdit = (categoria: Categoria) => {
        setEditando(categoria);
        setFormData({
            nombre: categoria.nombre,
            descripcion: categoria.descripcion || '',
            tipo: categoria.tipo,
            activo: categoria.activo,
            imagen: null,
            mostrar_en_perfil: (categoria as any).mostrar_en_perfil ?? true,
        });
        setImagenPreview(
            categoria.imagen ? `/storage/${categoria.imagen}` : null,
        );
        setImagenError(null);
        setServerError(null);
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Está seguro de eliminar esta categoría?')) {
            router.delete(`/categorias/${id}`);
        }
    };

    const openNew = () => {
        setEditando(null);
        setFormData({
            nombre: '',
            descripcion: '',
            tipo: 'producto',
            activo: true,
            imagen: null,
            mostrar_en_perfil: true,
        });
        setImagenPreview(null);
        setImagenError(null);
        setServerError(null);
        setIsOpen(true);
    };

    const toggleActivo = (categoria: Categoria) => {
        router.post(
            `/categorias/${categoria.id}`,
            {
                _method: 'PUT',
                nombre: categoria.nombre,
                descripcion: categoria.descripcion || '',
                tipo: categoria.tipo,
                activo: !categoria.activo,
                mostrar_en_perfil: categoria.mostrar_en_perfil,
            },
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorías" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Categorías</h1>
                    <div className="flex items-center gap-2">
                        <BulkActions
                            baseUrl="/categorias"
                            filters={{ search: searchTerm, tipo: tipoFilter }}
                            modelName="Categorías"
                        />
                        {canCreate && (
                            <Button onClick={openNew}>
                                <Plus className="mr-2 h-4 w-4" />
                                Nueva Categoría
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Listado de Categorías</CardTitle>
                                <CardDescription>
                                    {(categorias as any).total ??
                                        categorias.meta?.total ??
                                        0}{' '}
                                    categorías encontradas
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
                        <div className="mb-4 flex flex-wrap gap-3 rounded-xl border bg-muted/50 p-4 shadow-sm">
                            <div className="min-w-[200px] flex-1">
                                <div className="group relative">
                                    <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                                    <Input
                                        placeholder="Buscar por nombre o descripción..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="h-10 border-muted-foreground/20 pl-9 transition-all focus:border-primary"
                                    />
                                </div>
                            </div>

                            <Select
                                value={tipoFilter}
                                onValueChange={setTipoFilter}
                            >
                                <SelectTrigger className="h-10 min-w-[140px] border-muted-foreground/20 bg-background md:w-[180px]">
                                    <SelectValue placeholder="Todos los tipos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos los tipos
                                    </SelectItem>
                                    <SelectItem value="producto">
                                        Producto
                                    </SelectItem>
                                    <SelectItem value="cliente">
                                        Cliente
                                    </SelectItem>
                                    <SelectItem value="proveedor">
                                        Proveedor
                                    </SelectItem>
                                    <SelectItem value="servicio">
                                        Servicio
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Select value="all" disabled>
                                <SelectTrigger className="h-10 w-full border-muted-foreground/20 bg-muted/30 sm:w-[180px]">
                                    <SelectValue placeholder="Todos los estados" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos los estados
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Button
                                variant="outline"
                                size="sm"
                                className="h-10 border-muted-foreground/20 px-4 hover:border-destructive/30 hover:bg-destructive/10 hover:text-destructive"
                                onClick={limpiarFiltros}
                            >
                                <X className="mr-2 h-4 w-4" />
                                Limpiar Filtros
                            </Button>
                        </div>
                            {viewMode === 'table' ? (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="pb-3 text-left text-sm font-medium">
                                                        Imagen
                                                    </th>
                                                    <th className="pb-3 text-left text-sm font-medium">
                                                        Nombre
                                                    </th>
                                                    <th className="pb-3 text-left text-sm font-medium">
                                                        Tipo
                                                    </th>
                                                    <th className="pb-3 text-left text-sm font-medium">
                                                        Descripción
                                                    </th>
                                                    <th className="pb-3 text-sm font-medium">
                                                        Estado
                                                    </th>
                                                    <th className="pb-3 text-right text-sm font-medium">
                                                        Acciones
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {categorias.data.map(
                                                    (categoria: Categoria) => (
                                                        <tr
                                                            key={categoria.id}
                                                            className="border-b transition-colors hover:bg-muted/30"
                                                        >
                                                            <td className="px-4 py-2 text-center sm:text-left">
                                                                {categoria.imagen ? (
                                                                    <img
                                                                        src={`/storage/${categoria.imagen}`}
                                                                        alt={
                                                                            categoria.nombre
                                                                        }
                                                                        className="mx-auto h-8 w-8 rounded object-cover sm:mx-0"
                                                                    />
                                                                ) : (
                                                                    <div className="mx-auto flex h-8 w-8 items-center justify-center rounded bg-muted sm:mx-0">
                                                                        <ImageIcon className="h-4 w-4 text-muted-foreground" />
                                                                    </div>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-2 font-medium">
                                                                {categoria.nombre}
                                                            </td>
                                                            <td className="px-4 py-2 text-sm capitalize">
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-[10px] font-bold uppercase"
                                                                >
                                                                    {categoria.tipo}
                                                                </Badge>
                                                            </td>
                                                            <td className="hidden px-4 py-2 text-sm md:table-cell">
                                                                {categoria.descripcion ||
                                                                    '-'}
                                                            </td>
                                                            <td className="px-4 py-2">
                                                                <ActiveDropdown
                                                                    activo={
                                                                        categoria.activo
                                                                    }
                                                                    onChange={(val) => {
                                                                        if (
                                                                            val !==
                                                                            categoria.activo
                                                                        ) {
                                                                            toggleActivo(
                                                                                categoria,
                                                                            );
                                                                        }
                                                                    }}
                                                                />
                                                            </td>
                                                            <td className="px-4 py-2 text-right">
                                                                <div className="flex justify-end gap-1">
                                                                    {categoria.mostrar_en_perfil &&
                                                                        tiendaSlug && (
                                                                            <a
                                                                                href={`/tienda/${tiendaSlug}/categoria/${categoria.id}`}
                                                                                target="_blank"
                                                                                rel="noopener noreferrer"
                                                                                className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                                                                                title="Ver catálogo público"
                                                                            >
                                                                                <ShoppingBag className="h-3.5 w-3.5" />
                                                                                Ver Catálogo
                                                                            </a>
                                                                        )}
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="h-8 w-8 p-0"
                                                                        onClick={() => {
                                                                            setViendo(
                                                                                categoria,
                                                                            );
                                                                            setIsViewOpen(
                                                                                true,
                                                                            );
                                                                        }}
                                                                        title="Ver detalles"
                                                                    >
                                                                        <Eye className="h-4 w-4" />
                                                                    </Button>
                                                                    {canEdit && (
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            className="h-8 w-8 p-0"
                                                                            onClick={() =>
                                                                                handleEdit(
                                                                                    categoria,
                                                                                )
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
                                                                                    categoria.id,
                                                                                )
                                                                            }
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                                {categorias.data.length === 0 && (
                                                    <tr>
                                                        <td
                                                            colSpan={6}
                                                            className="py-8 text-center text-muted-foreground"
                                                        >
                                                            No se encontraron categorías con
                                                            los filtros aplicados
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <Pagination
                                        links={categorias.links}
                                        meta={categorias.meta || (categorias as any)}
                                    />
                                </>
                            ) : (
                                <>
                                    {categorias.data.length === 0 ? (
                                        <div className="py-12 text-center text-muted-foreground">
                                            <Tags className="mx-auto mb-3 h-12 w-12 opacity-30" />
                                            <p>No se encontraron categorías.</p>
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            {categorias.data.map((categoria: Categoria) => (
                                                <div
                                                    key={categoria.id}
                                                    className="group relative overflow-hidden rounded-xl border bg-card transition-all hover:shadow-lg"
                                                >
                                                    <div className="relative h-36 overflow-hidden bg-muted">
                                                        {categoria.imagen ? (
                                                            <img
                                                                src={`/storage/${categoria.imagen}`}
                                                                alt={categoria.nombre}
                                                                className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                            />
                                                        ) : (
                                                            <div className="flex h-full items-center justify-center">
                                                                <ImageIcon className="h-12 w-12 text-muted-foreground/30" />
                                                            </div>
                                                        )}
                                                        <div className="absolute top-2 right-2">
                                                            <ActiveDropdown
                                                                activo={categoria.activo}
                                                                onChange={(val) => {
                                                                    if (val !== categoria.activo) {
                                                                        toggleActivo(categoria);
                                                                    }
                                                                }}
                                                            />
                                                        </div>
                                                        <div className="absolute top-2 left-2">
                                                            <Badge variant="secondary" className="px-2 py-0.5 text-[10px] capitalize">
                                                                {categoria.tipo}
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                    <div className="space-y-2 p-4">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div>
                                                                <h3 className="font-semibold leading-tight">{categoria.nombre}</h3>
                                                                <p className="text-xs text-muted-foreground line-clamp-2">
                                                                    {categoria.descripcion || 'Sin descripción'}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-1 pt-1">
                                                            {categoria.mostrar_en_perfil && tiendaSlug && (
                                                                <a
                                                                    href={`/tienda/${tiendaSlug}/categoria/${categoria.id}`}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="inline-flex items-center gap-1 rounded-lg bg-primary px-2 py-1 text-[10px] font-semibold text-primary-foreground"
                                                                >
                                                                    <ShoppingBag className="h-3 w-3" />
                                                                    Catálogo
                                                                </a>
                                                            )}
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 px-2 text-xs"
                                                                onClick={() => {
                                                                    setViendo(categoria);
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
                                                                    onClick={() => handleEdit(categoria)}
                                                                >
                                                                    <Pencil className="mr-1 h-3.5 w-3.5" /> Editar
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 px-2 text-xs text-destructive hover:text-destructive"
                                                                    onClick={() => handleDelete(categoria.id)}
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
                                        links={categorias.links}
                                        meta={categorias.meta || (categorias as any)}
                                    />
                                </>
                            )}
                    </CardContent>
                </Card>

                <Dialog open={isOpen} onOpenChange={setIsOpen}>
                    <DialogContent className="max-h-[95vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-lg">
                        <DialogHeader className="sticky top-0 z-10 bg-background p-4 pb-4 backdrop-blur-sm md:p-6">
                            <DialogTitle className="text-xl font-black md:text-2xl">
                                {editando
                                    ? 'Editar Categoría'
                                    : 'Nueva Categoría'}
                            </DialogTitle>
                            <DialogDescription className="text-sm">
                                {editando
                                    ? 'Modifique los datos de la categoría'
                                    : 'Ingrese los datos de la nueva categoría'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="max-h-[calc(95vh-180px)] overflow-y-auto p-4 md:p-6">
                            <form onSubmit={handleSubmit}>
                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="nombre">Nombre</Label>
                                        <Input
                                            id="nombre"
                                            value={formData.nombre}
                                            onChange={(e) =>
                                                setFormData({
                                                    ...formData,
                                                    nombre: e.target.value,
                                                })
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="descripcion">
                                            Descripción
                                        </Label>
                                        <Input
                                            id="descripcion"
                                            value={formData.descripcion}
                                            onChange={(e) =>
                                                setFormData({
                                                    ...formData,
                                                    descripcion: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="tipo">Tipo</Label>
                                        <Select
                                            value={formData.tipo}
                                            onValueChange={(v: string) =>
                                                setFormData({
                                                    ...formData,
            tipo: v as
                | 'producto'
                | 'cliente'
                | 'proveedor'
                | 'servicio',
                                                })
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                    <SelectItem value="producto">
                                        Producto
                                    </SelectItem>
                                    <SelectItem value="cliente">
                                        Cliente
                                    </SelectItem>
                                    <SelectItem value="proveedor">
                                        Proveedor
                                    </SelectItem>
                                    <SelectItem value="servicio">
                                        Servicio
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id="activo"
                                            checked={formData.activo}
                                            onChange={(e) =>
                                                setFormData({
                                                    ...formData,
                                                    activo: e.target.checked,
                                                })
                                            }
                                            className="h-4 w-4"
                                        />
                                        <Label
                                            htmlFor="activo"
                                            className="flex items-center gap-1"
                                        >
                                            Categoría activa
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <HelpCircle className="h-3.5 w-3.5 cursor-help text-slate-400" />
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p className="max-w-xs">
                                                            Si se desactiva, la
                                                            categoría y sus
                                                            productos asociados
                                                            no serán visibles en
                                                            el sistema ni
                                                            marketplace.
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </Label>
                                    </div>
                                    <div className="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3">
                                        <input
                                            type="checkbox"
                                            id="mostrar-en-perfil"
                                            checked={formData.mostrar_en_perfil}
                                            onChange={(e) =>
                                                setFormData({
                                                    ...formData,
                                                    mostrar_en_perfil:
                                                        e.target.checked,
                                                })
                                            }
                                            className="h-4 w-4"
                                        />
                                        <Label
                                            htmlFor="mostrar-en-perfil"
                                            className="flex items-center gap-1 font-medium text-blue-800"
                                        >
                                            Mostrar en perfil público de tienda
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <HelpCircle className="h-3.5 w-3.5 cursor-help text-blue-400" />
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p className="max-w-xs border-none bg-white font-normal text-slate-900">
                                                            Determina si esta
                                                            categoría aparece en
                                                            el catálogo público
                                                            que ven tus clientes
                                                            en el marketplace.
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </Label>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Imagen</Label>
                                        {imagenError && (
                                            <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                                {imagenError}
                                            </div>
                                        )}
                                        {serverError && (
                                            <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                                {serverError}
                                            </div>
                                        )}
                                        <div className="flex items-center gap-4">
                                            <div className="flex-1">
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/svg+xml,image/webp"
                                                    onChange={handleImageChange}
                                                    className="hidden"
                                                    id="imagen-upload"
                                                />
                                                <Label
                                                    htmlFor="imagen-upload"
                                                    className="flex min-h-[250px] w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-muted-foreground/20 bg-muted/30 transition-all hover:bg-muted/50"
                                                >
                                                    {imagenPreview ? (
                                                        <img
                                                            src={imagenPreview}
                                                            alt="Preview"
                                                            className="max-h-[400px] w-full object-contain"
                                                        />
                                                    ) : (
                                                        <div className="flex flex-col items-center gap-3 text-muted-foreground">
                                                            <div className="rounded-full bg-background p-4 shadow-sm">
                                                                <ImageIcon className="h-10 w-10 text-primary/60" />
                                                            </div>
                                                            <div className="text-center">
                                                                <p className="text-sm font-bold">
                                                                    Haga clic
                                                                    para subir
                                                                    una imagen
                                                                </p>
                                                                <p className="text-xs opacity-70">
                                                                    PNG, JPG,
                                                                    WebP hasta
                                                                    5MB
                                                                </p>
                                                            </div>
                                                        </div>
                                                    )}
                                                </Label>
                                            </div>
                                            {(imagenPreview ||
                                                formData.imagen) && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    onClick={removeImage}
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter className="sticky bottom-0 mt-4 bg-background pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIsOpen(false)}
                                    >
                                        Cancelar
                                    </Button>
                                    <Button type="submit">
                                        <Check className="mr-2 h-4 w-4" />
                                        Guardar
                                    </Button>
                                </DialogFooter>
                            </form>
                        </div>
                    </DialogContent>
                </Dialog>

                {/* Modal de Vista Premium */}
                <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
                    <DialogContent className="max-h-[90vh] max-w-3xl overflow-hidden border-none bg-white p-0 shadow-2xl">
                        <DialogHeader className="relative overflow-hidden px-8 pt-10 pb-20">
                            {/* Fondo decorativo con gradiente */}
                            <div className="absolute inset-0 bg-gradient-to-br from-purple-700 via-violet-800 to-indigo-950 opacity-100" />
                            <div className="absolute top-0 right-0 p-8 text-white opacity-20">
                                <Eye className="h-24 w-24 rotate-12" />
                            </div>

                            <div className="relative z-10 flex flex-col gap-1 text-white">
                                <Badge className="w-fit border-none bg-white/20 px-3 py-1 text-[10px] font-bold tracking-widest text-white uppercase hover:bg-white/30">
                                    Detalle de Categoría
                                </Badge>
                                <DialogTitle className="text-3xl font-black tracking-tight text-white uppercase sm:text-4xl">
                                    {viendo?.nombre}
                                </DialogTitle>
                                <DialogDescription className="text-lg font-medium text-purple-100/80">
                                    Clasificación y configuración de
                                    visualización.
                                </DialogDescription>
                            </div>
                        </DialogHeader>

                        {viendo && (
                            <div className="relative z-20 -mt-10 mb-6 flex max-h-[calc(90vh-180px)] flex-col gap-6 overflow-y-auto px-8">
                                {/* Dashboard de estados rápidos */}
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    {[
                                        {
                                            label: 'Tipo',
                                            val: viendo.tipo,
                                            color: 'border-purple-200 bg-purple-50 text-purple-800',
                                        },
                                        {
                                            label: 'Estado',
                                            val: viendo.activo
                                                ? 'ACTIVO'
                                                : 'INACTIVO',
                                            color: viendo.activo
                                                ? 'border-green-200 bg-green-50 text-green-800'
                                                : 'border-red-200 bg-red-50 text-red-800',
                                        },
                                        {
                                            label: 'Perfil Público',
                                            val: viendo.mostrar_en_perfil
                                                ? 'SÍ'
                                                : 'NO',
                                            color: viendo.mostrar_en_perfil
                                                ? 'border-blue-200 bg-blue-50 text-blue-800'
                                                : 'border-gray-200 bg-gray-50 text-gray-800',
                                        },
                                    ].map((item, idx) => (
                                        <div
                                            key={idx}
                                            className={`group rounded-xl border p-4 transition-all duration-300 hover:shadow-md ${item.color}`}
                                        >
                                            <p className="mb-1 text-[10px] font-extrabold tracking-wider uppercase opacity-70">
                                                {item.label}
                                            </p>
                                            <p className="truncate text-sm font-semibold uppercase">
                                                {item.val}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                <div className="grid grid-cols-1 items-start gap-8 lg:grid-cols-2">
                                    {/* Imagen de Categoría */}
                                    <div className="flex flex-col gap-3">
                                        <Label className="ml-1 text-[10px] font-bold text-muted-foreground uppercase">
                                            Imagen Representativa
                                        </Label>
                                        <div className="group relative flex aspect-video items-center justify-center overflow-hidden rounded-2xl border bg-gray-50 shadow-sm">
                                            {viendo.imagen ? (
                                                <img
                                                    src={`/storage/${viendo.imagen}`}
                                                    alt={viendo.nombre}
                                                    className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105"
                                                />
                                            ) : (
                                                <div className="flex flex-col items-center gap-2 text-muted-foreground opacity-40">
                                                    <ImageIcon className="h-12 w-12" />
                                                    <span className="text-[10px] font-bold tracking-widest uppercase">
                                                        Sin Imagen
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Descripción y Detalles */}
                                    <div className="flex flex-col gap-6">
                                        <Card className="border-none bg-gray-50/50 shadow-sm">
                                            <CardHeader className="border-b border-gray-100 pb-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="rounded-md bg-purple-100 p-1.5 text-purple-700">
                                                        <Plus className="h-4 w-4" />
                                                    </div>
                                                    <CardTitle className="text-base font-bold text-gray-800">
                                                        Descripción
                                                    </CardTitle>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="p-5 text-sm leading-relaxed font-medium text-gray-600 italic">
                                                {viendo.descripcion ||
                                                    'Esta categoría no cuenta con una descripción detallada en el sistema.'}
                                            </CardContent>
                                        </Card>

                                        <div className="flex items-start gap-3 rounded-xl border border-amber-100 bg-amber-50/50 p-4">
                                            <div className="mt-1 rounded-full bg-amber-200 p-1">
                                                <Check className="h-3 w-3 text-amber-700" />
                                            </div>
                                            <div className="space-y-1">
                                                <p className="text-xs font-bold text-amber-900">
                                                    Configuración de
                                                    Visualización
                                                </p>
                                                <p className="text-[10px] leading-relaxed font-medium text-amber-800/70">
                                                    {viendo.mostrar_en_perfil
                                                        ? 'Esta categoría será visible en el catálogo público de la tienda.'
                                                        : 'Esta categoría se mantiene para uso administrativo interno solamente.'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        <DialogFooter className="mt-4 border-t bg-gray-50 p-6">
                            <div className="flex w-full flex-wrap items-center justify-between gap-3">
                                {viendo?.mostrar_en_perfil && tiendaSlug && (
                                    <a
                                        href={`/tienda/${tiendaSlug}/categoria/${viendo.id}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none dark:focus:ring-offset-slate-950"
                                    >
                                        <ShoppingBag className="h-4 w-4" />
                                        Ver Catálogo
                                    </a>
                                )}
                                <Button
                                    variant="outline"
                                    onClick={() => setIsViewOpen(false)}
                                    className="border-gray-200 px-10 font-bold shadow-sm transition-all hover:bg-white hover:text-primary active:scale-95"
                                >
                                    Cerrar Detalle
                                </Button>
                            </div>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
