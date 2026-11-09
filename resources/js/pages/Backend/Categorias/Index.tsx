import { Head, router, usePage } from '@inertiajs/react';
import {
    Briefcase,
    Check,
    Eye,
    HelpCircle,
    Image as ImageIcon,
    LayoutGrid,
    Layers3,
    List,
    Package,
    Pencil,
    Plus,
    Search,
    ShoppingBag,
    Store,
    Tags,
    Trash2,
    Truck,
    Users,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Categoria {
    id: number;
    nombre: string;
    descripcion: string | null;
    tipo: string;
    activo: boolean;
    imagen: string | null;
    mostrar_en_perfil: boolean;
    public_profile_id: number | null;
    created_at: string;
}

interface GrupoCategoria {
    tipo: string;
    label: string;
    total: number;
    activas: number;
    marketplace: number;
    data: Categoria[];
}

interface TipoOption {
    value: string;
    label: string;
}

interface ResumenCategorias {
    total: number;
    activas: number;
    marketplace: number;
    tipos: number;
}

interface FormState {
    nombre: string;
    descripcion: string;
    tipo: string;
    activo: boolean;
    imagen: File | null;
    mostrar_en_perfil: boolean;
}

const TIPOS_FALLBACK: TipoOption[] = [
    { value: 'producto', label: 'Producto' },
    { value: 'cliente', label: 'Cliente' },
    { value: 'proveedor', label: 'Proveedor' },
    { value: 'servicio', label: 'Servicio' },
];

const FORM_INICIAL: FormState = {
    nombre: '',
    descripcion: '',
    tipo: 'producto',
    activo: true,
    imagen: null,
    mostrar_en_perfil: true,
};

const TIPO_META: Record<
    string,
    { icon: LucideIcon; accent: string; soft: string; ring: string }
> = {
    producto: {
        icon: Package,
        accent: 'text-emerald-700',
        soft: 'bg-emerald-50',
        ring: 'ring-emerald-100',
    },
    cliente: {
        icon: Users,
        accent: 'text-blue-700',
        soft: 'bg-blue-50',
        ring: 'ring-blue-100',
    },
    proveedor: {
        icon: Truck,
        accent: 'text-amber-700',
        soft: 'bg-amber-50',
        ring: 'ring-amber-100',
    },
    servicio: {
        icon: Briefcase,
        accent: 'text-violet-700',
        soft: 'bg-violet-50',
        ring: 'ring-violet-100',
    },
};

const TIPO_META_FALLBACK = {
    icon: Tags,
    accent: 'text-slate-700',
    soft: 'bg-slate-50',
    ring: 'ring-slate-200',
};

function getTipoMeta(tipo: string) {
    return TIPO_META[tipo] ?? TIPO_META_FALLBACK;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categorias' },
];

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

export default function Index({
    categoriasPorTipo = [],
    tiposDisponibles = [],
    resumen,
    tiendaSlug,
    filters = {},
}: {
    categoriasPorTipo: GrupoCategoria[];
    tiposDisponibles: TipoOption[];
    resumen?: ResumenCategorias;
    tiendaSlug?: string | null;
    filters?: {
        search?: string;
        tipo?: string;
        marketplace?: string;
    };
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [editando, setEditando] = useState<Categoria | null>(null);
    const [viendo, setViendo] = useState<Categoria | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [imagenPreview, setImagenPreview] = useState<string | null>(null);
    const [imagenError, setImagenError] = useState<string | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const isFirstRender = useRef(true);

    const { auth } = usePage().props as any;
    const userPermissions: string[] = auth?.user?.permissions || [];
    const hasAll = userPermissions.includes('*');
    const canCreate =
        hasAll || userPermissions.includes('comercial.categorias.create');
    const canEdit =
        hasAll || userPermissions.includes('comercial.categorias.edit');
    const canDelete =
        hasAll || userPermissions.includes('comercial.categorias.delete');

    const [formData, setFormData] = useState<FormState>(FORM_INICIAL);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [tipoFilter, setTipoFilter] = useState(filters.tipo || 'all');
    const [marketplaceFilter, setMarketplaceFilter] = useState(
        filters.marketplace || 'all',
    );

    const tiposParaSelect =
        tiposDisponibles.length > 0 ? tiposDisponibles : TIPOS_FALLBACK;

    const totales = resumen ?? {
        total: 0,
        activas: 0,
        marketplace: 0,
        tipos: categoriasPorTipo.length,
    };

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = setTimeout(() => {
            const query: Record<string, string> = {};

            if (searchTerm) {
                query.search = searchTerm;
            }
            if (tipoFilter !== 'all') {
                query.tipo = tipoFilter;
            }
            if (marketplaceFilter !== 'all') {
                query.marketplace = marketplaceFilter;
            }

            router.get('/categorias', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 450);

        return () => clearTimeout(timer);
    }, [searchTerm, tipoFilter, marketplaceFilter]);

    const limpiarFiltros = () => {
        setSearchTerm('');
        setTipoFilter('all');
        setMarketplaceFilter('all');
    };

    const resetForm = () => {
        setFormData(FORM_INICIAL);
        setImagenPreview(null);
        setImagenError(null);
        setServerError(null);
    };

    const openNew = (tipo = 'producto') => {
        setEditando(null);
        resetForm();
        setFormData({ ...FORM_INICIAL, tipo });
        setIsOpen(true);
    };

    const openView = (categoria: Categoria) => {
        setViendo(categoria);
        setIsViewOpen(true);
    };

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (!file) {
            return;
        }

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
        setFormData((prev) => ({ ...prev, imagen: file }));

        const reader = new FileReader();
        reader.onloadend = () => {
            setImagenPreview(reader.result as string);
        };
        reader.readAsDataURL(file);
    };

    const removeImage = () => {
        setFormData((prev) => ({ ...prev, imagen: null }));
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
            onStart: () => setIsSubmitting(true),
            onFinish: () => setIsSubmitting(false),
            onSuccess: () => {
                setIsOpen(false);
                setEditando(null);
                resetForm();
            },
            onError: (errors: Record<string, string>) => {
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
            mostrar_en_perfil: categoria.mostrar_en_perfil ?? true,
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

    const renderMarketplaceBadge = (categoria: Categoria) => {
        const publicada =
            categoria.mostrar_en_perfil && categoria.public_profile_id !== null;

        return publicada ? (
            <Badge className="border-transparent bg-emerald-100 text-[10px] font-bold text-emerald-700 uppercase">
                <Store className="h-3 w-3" />
                Publicada
            </Badge>
        ) : (
            <Badge
                variant="outline"
                className="text-[10px] font-bold text-muted-foreground uppercase"
            >
                Interna
            </Badge>
        );
    };

    const renderActions = (categoria: Categoria, compact = false) => {
        const publicada =
            categoria.mostrar_en_perfil && categoria.public_profile_id !== null;

        return (
            <div className="flex flex-wrap items-center justify-end gap-1">
                {publicada && tiendaSlug && (
                    <a
                        href={`/tienda/${tiendaSlug}/categoria/${categoria.id}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-primary px-2.5 text-xs font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                        title="Ver catálogo público"
                        aria-label="Ver catálogo público"
                    >
                        <ShoppingBag className="h-3.5 w-3.5" />
                        {!compact && <span>Catálogo</span>}
                    </a>
                )}
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 w-8 p-0"
                    onClick={() => openView(categoria)}
                    title="Ver detalles"
                    aria-label="Ver detalles"
                >
                    <Eye className="h-4 w-4" />
                </Button>
                {canEdit && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0"
                        onClick={() => handleEdit(categoria)}
                        title="Editar"
                        aria-label="Editar categoría"
                    >
                        <Pencil className="h-4 w-4" />
                    </Button>
                )}
                {canDelete && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                        onClick={() => handleDelete(categoria.id)}
                        title="Eliminar"
                        aria-label="Eliminar categoría"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                )}
            </div>
        );
    };

    const renderDescription = (categoria: Categoria, lines = 1) => {
        if (!categoria.descripcion) {
            return (
                <span className="text-xs text-muted-foreground italic">
                    Sin descripción
                </span>
            );
        }

        return (
            <div className="flex min-w-0 items-center gap-1.5">
                <p
                    className={`${lines === 1 ? 'line-clamp-1' : 'line-clamp-2'} min-w-0 text-sm text-muted-foreground`}
                >
                    {categoria.descripcion}
                </p>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            onClick={() => openView(categoria)}
                            className="shrink-0 rounded p-0.5 text-muted-foreground transition-colors hover:text-primary focus:ring-1 focus:ring-primary focus:outline-none"
                            aria-label="Ver descripción completa"
                        >
                            <Eye className="h-3.5 w-3.5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent className="max-w-sm">
                        <p className="line-clamp-6 text-xs">
                            {categoria.descripcion}
                        </p>
                    </TooltipContent>
                </Tooltip>
            </div>
        );
    };

    const renderMobileCard = (categoria: Categoria) => (
        <div
            key={categoria.id}
            className="rounded-xl border bg-card p-3 shadow-sm transition-shadow hover:shadow-md"
        >
            <div className="flex items-start gap-3">
                <div className="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-muted">
                    {categoria.imagen ? (
                        <img
                            src={`/storage/${categoria.imagen}`}
                            alt={categoria.nombre}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center">
                            <ImageIcon className="h-5 w-5 text-muted-foreground" />
                        </div>
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-bold">
                                {categoria.nombre}
                            </p>
                            <div className="mt-0.5">
                                {renderDescription(categoria, 2)}
                            </div>
                        </div>
                        <ActiveDropdown
                            activo={categoria.activo}
                            onChange={(val) => {
                                if (val !== categoria.activo) {
                                    toggleActivo(categoria);
                                }
                            }}
                        />
                    </div>
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                        {renderMarketplaceBadge(categoria)}
                        {renderActions(categoria, true)}
                    </div>
                </div>
            </div>
        </div>
    );

    const renderGridCard = (categoria: Categoria) => (
        <div
            key={categoria.id}
            className="group flex flex-col overflow-hidden rounded-xl border bg-card shadow-sm transition-all hover:shadow-lg"
        >
            <div className="relative h-32 overflow-hidden bg-muted sm:h-36">
                {categoria.imagen ? (
                    <img
                        src={`/storage/${categoria.imagen}`}
                        alt={categoria.nombre}
                        className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex h-full items-center justify-center">
                        <ImageIcon className="h-10 w-10 text-muted-foreground/30" />
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
            </div>
            <div className="flex flex-1 flex-col gap-2 p-3 sm:p-4">
                <div className="flex items-start justify-between gap-2">
                    <h3 className="line-clamp-1 text-sm font-bold">
                        {categoria.nombre}
                    </h3>
                    {renderMarketplaceBadge(categoria)}
                </div>
                <p className="line-clamp-2 min-h-8 text-xs text-muted-foreground">
                    {categoria.descripcion || 'Sin descripción'}
                </p>
                <div className="mt-auto flex items-center justify-between gap-2 pt-2">
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1 text-xs"
                        onClick={() => openView(categoria)}
                    >
                        <Eye className="h-3.5 w-3.5" /> Ver detalle
                    </Button>
                    {renderActions(categoria, true)}
                </div>
            </div>
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorías" />
            <TooltipProvider delayDuration={200}>
                <div className="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4 lg:p-6">
                    {/* Header */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-black tracking-tight sm:text-3xl">
                                Categorías
                            </h1>
                            <p className="text-xs text-muted-foreground sm:text-sm">
                                Paneles independientes por tipo, con control de
                                publicación en el marketplace.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <BulkActions
                                baseUrl="/categorias"
                                filters={{
                                    search: searchTerm,
                                    tipo: tipoFilter,
                                    marketplace: marketplaceFilter,
                                }}
                                modelName="Categorías"
                            />
                            {canCreate && (
                                <Button
                                    onClick={() => openNew()}
                                    className="h-9 flex-1 rounded-full px-4 font-bold shadow-sm sm:flex-none"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Nueva Categoría
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Resumen */}
                    <div className="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
                        <Card className="border-none bg-gradient-to-br from-slate-50 to-white shadow-sm ring-1 ring-slate-100">
                            <CardContent className="flex items-center gap-3 p-3 sm:p-4">
                                <div className="rounded-xl bg-slate-100 p-2 text-slate-700">
                                    <Layers3 className="h-4 w-4 sm:h-5 sm:w-5" />
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Total
                                    </p>
                                    <p className="text-lg font-black sm:text-xl">
                                        {totales.total}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="border-none bg-gradient-to-br from-emerald-50 to-white shadow-sm ring-1 ring-emerald-100">
                            <CardContent className="flex items-center gap-3 p-3 sm:p-4">
                                <div className="rounded-xl bg-emerald-100 p-2 text-emerald-700">
                                    <Store className="h-4 w-4 sm:h-5 sm:w-5" />
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Marketplace
                                    </p>
                                    <p className="text-lg font-black sm:text-xl">
                                        {totales.marketplace}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="border-none bg-gradient-to-br from-blue-50 to-white shadow-sm ring-1 ring-blue-100">
                            <CardContent className="flex items-center gap-3 p-3 sm:p-4">
                                <div className="rounded-xl bg-blue-100 p-2 text-blue-700">
                                    <Check className="h-4 w-4 sm:h-5 sm:w-5" />
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Activas
                                    </p>
                                    <p className="text-lg font-black sm:text-xl">
                                        {totales.activas}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="border-none bg-gradient-to-br from-violet-50 to-white shadow-sm ring-1 ring-violet-100">
                            <CardContent className="flex items-center gap-3 p-3 sm:p-4">
                                <div className="rounded-xl bg-violet-100 p-2 text-violet-700">
                                    <Tags className="h-4 w-4 sm:h-5 sm:w-5" />
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Tipos
                                    </p>
                                    <p className="text-lg font-black sm:text-xl">
                                        {totales.tipos}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Filtros */}
                    <Card className="border-none shadow-sm">
                        <CardContent className="p-3 sm:p-4">
                            <div className="grid gap-2 sm:grid-cols-2 sm:gap-3 xl:grid-cols-[minmax(0,1fr)_180px_200px_auto]">
                                <div className="group relative sm:col-span-2 xl:col-span-1">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground transition-colors group-focus-within:text-primary" />
                                    <Input
                                        placeholder="Buscar por nombre o descripción..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="h-10 border-muted-foreground/20 pl-9"
                                    />
                                </div>

                                <Select
                                    value={tipoFilter}
                                    onValueChange={setTipoFilter}
                                >
                                    <SelectTrigger className="h-10 border-muted-foreground/20">
                                        <SelectValue placeholder="Todos los tipos" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos los tipos
                                        </SelectItem>
                                        {tiposParaSelect.map((tipo) => (
                                            <SelectItem
                                                key={tipo.value}
                                                value={tipo.value}
                                            >
                                                {tipo.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Select
                                    value={marketplaceFilter}
                                    onValueChange={setMarketplaceFilter}
                                >
                                    <SelectTrigger className="h-10 border-muted-foreground/20">
                                        <SelectValue placeholder="Marketplace" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todas las categorías
                                        </SelectItem>
                                        <SelectItem value="1">
                                            Publicadas en marketplace
                                        </SelectItem>
                                        <SelectItem value="0">
                                            Solo uso interno
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <Button
                                    variant="outline"
                                    className="h-10 border-muted-foreground/20 px-4 hover:border-destructive/30 hover:bg-destructive/10 hover:text-destructive"
                                    onClick={limpiarFiltros}
                                >
                                    <X className="mr-2 h-4 w-4" />
                                    Limpiar
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Encabezado de paneles + vista */}
                    <div className="flex items-center justify-between gap-2">
                        <div>
                            <h2 className="text-sm font-black tracking-wide uppercase sm:text-base">
                                Paneles por tipo
                            </h2>
                            <p className="text-[11px] text-muted-foreground">
                                {categoriasPorTipo.length} panel(es) con
                                categorías encontradas
                            </p>
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
                                aria-label="Vista tabla"
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
                                aria-label="Vista tarjetas"
                            >
                                <LayoutGrid className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {/* Paneles por tipo */}
                    {categoriasPorTipo.length === 0 ? (
                        <Card className="border-dashed shadow-none">
                            <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                                <Tags className="h-12 w-12 text-muted-foreground/30" />
                                <div>
                                    <p className="font-bold">
                                        No se encontraron categorías
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Ajusta los filtros o crea una nueva
                                        categoría.
                                    </p>
                                </div>
                                {canCreate && (
                                    <Button onClick={() => openNew()}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Nueva Categoría
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    ) : (
                        categoriasPorTipo.map((grupo) => {
                            const meta = getTipoMeta(grupo.tipo);
                            const Icon = meta.icon;

                            return (
                                <Card
                                    key={grupo.tipo}
                                    className={`overflow-hidden border-none shadow-sm ring-1 ${meta.ring}`}
                                >
                                    <CardHeader
                                        className={`${meta.soft} border-b border-black/5 p-3 sm:p-4`}
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="flex items-center gap-3">
                                                <div
                                                    className={`rounded-xl bg-white p-2.5 shadow-sm ${meta.accent}`}
                                                >
                                                    <Icon className="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <CardTitle className="flex flex-wrap items-center gap-2 text-base font-black sm:text-lg">
                                                        {grupo.label}
                                                        <Badge
                                                            variant="outline"
                                                            className="bg-white/70 text-[10px]"
                                                        >
                                                            {grupo.total}{' '}
                                                            categoría(s)
                                                        </Badge>
                                                    </CardTitle>
                                                    <CardDescription className="text-xs">
                                                        Panel de control
                                                        independiente para
                                                        categorías de tipo{' '}
                                                        {grupo.label.toLowerCase()}
                                                        .
                                                    </CardDescription>
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <Badge
                                                    variant="outline"
                                                    className="bg-white/70 text-[10px]"
                                                >
                                                    <Check className="h-3 w-3 text-blue-600" />
                                                    {grupo.activas} activas
                                                </Badge>
                                                <Badge
                                                    variant="outline"
                                                    className="bg-white/70 text-[10px]"
                                                >
                                                    <Store className="h-3 w-3 text-emerald-600" />
                                                    {grupo.marketplace} en
                                                    marketplace
                                                </Badge>
                                                {canCreate && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-8 gap-1 bg-white text-xs"
                                                        onClick={() =>
                                                            openNew(grupo.tipo)
                                                        }
                                                    >
                                                        <Plus className="h-3.5 w-3.5" />
                                                        Nueva
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="p-0 sm:p-3">
                                        {viewMode === 'table' ? (
                                            <>
                                                {/* Tabla en desktop */}
                                                <div className="hidden overflow-x-auto md:block">
                                                    <table className="w-full min-w-[820px] text-sm">
                                                        <thead>
                                                            <tr className="border-b text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                                                <th className="px-3 py-2 text-left">
                                                                    Imagen
                                                                </th>
                                                                <th className="px-3 py-2 text-left">
                                                                    Nombre
                                                                </th>
                                                                <th className="px-3 py-2 text-left">
                                                                    Descripción
                                                                </th>
                                                                <th className="px-3 py-2 text-left">
                                                                    Estado
                                                                </th>
                                                                <th className="px-3 py-2 text-left">
                                                                    Marketplace
                                                                </th>
                                                                <th className="px-3 py-2 text-right">
                                                                    Acciones
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {grupo.data.map(
                                                                (categoria) => (
                                                                    <tr
                                                                        key={
                                                                            categoria.id
                                                                        }
                                                                        className="border-b transition-colors last:border-0 hover:bg-muted/30"
                                                                    >
                                                                        <td className="px-3 py-2">
                                                                            <div className="h-9 w-9 overflow-hidden rounded-lg bg-muted">
                                                                                {categoria.imagen ? (
                                                                                    <img
                                                                                        src={`/storage/${categoria.imagen}`}
                                                                                        alt={
                                                                                            categoria.nombre
                                                                                        }
                                                                                        className="h-full w-full object-cover"
                                                                                    />
                                                                                ) : (
                                                                                    <div className="flex h-full w-full items-center justify-center">
                                                                                        <ImageIcon className="h-4 w-4 text-muted-foreground" />
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="max-w-[180px] px-3 py-2">
                                                                            <p className="truncate font-semibold">
                                                                                {
                                                                                    categoria.nombre
                                                                                }
                                                                            </p>
                                                                            <p className="text-[10px] text-muted-foreground">
                                                                                #
                                                                                {
                                                                                    categoria.id
                                                                                }
                                                                            </p>
                                                                        </td>
                                                                        <td className="max-w-[320px] px-3 py-2">
                                                                            {renderDescription(
                                                                                categoria,
                                                                            )}
                                                                        </td>
                                                                        <td className="px-3 py-2">
                                                                            <ActiveDropdown
                                                                                activo={
                                                                                    categoria.activo
                                                                                }
                                                                                onChange={(
                                                                                    val,
                                                                                ) => {
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
                                                                        <td className="px-3 py-2">
                                                                            {renderMarketplaceBadge(
                                                                                categoria,
                                                                            )}
                                                                        </td>
                                                                        <td className="px-3 py-2">
                                                                            {renderActions(
                                                                                categoria,
                                                                            )}
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                            )}
                                                        </tbody>
                                                    </table>
                                                </div>

                                                {/* Tarjetas apiladas en móvil */}
                                                <div className="grid gap-2 p-2 sm:p-3 md:hidden">
                                                    {grupo.data.map(
                                                        renderMobileCard,
                                                    )}
                                                </div>
                                            </>
                                        ) : (
                                            <div className="grid gap-3 p-2 sm:grid-cols-2 sm:p-3 xl:grid-cols-3">
                                                {grupo.data.map(renderGridCard)}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })
                    )}
                </div>

                {/* Modal Crear / Editar */}
                <Dialog open={isOpen} onOpenChange={setIsOpen}>
                    <DialogContent className="max-h-[92vh] w-[95vw] max-w-lg overflow-hidden border-none p-0 shadow-2xl sm:max-w-lg">
                        <DialogHeader className="sticky top-0 z-10 border-b bg-background p-4 sm:p-6">
                            <DialogTitle className="text-lg font-black sm:text-2xl">
                                {editando
                                    ? 'Editar Categoría'
                                    : 'Nueva Categoría'}
                            </DialogTitle>
                            <DialogDescription className="text-xs sm:text-sm">
                                {editando
                                    ? 'Modifique los datos de la categoría.'
                                    : 'Ingrese los datos de la nueva categoría.'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="max-h-[calc(92vh-170px)] overflow-y-auto p-4 sm:p-6">
                            <form onSubmit={handleSubmit}>
                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="nombre">Nombre</Label>
                                        <Input
                                            id="nombre"
                                            value={formData.nombre}
                                            onChange={(e) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    nombre: e.target.value,
                                                }))
                                            }
                                            required
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="descripcion">
                                            Descripción
                                        </Label>
                                        <Textarea
                                            id="descripcion"
                                            value={formData.descripcion}
                                            onChange={(e) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    descripcion: e.target.value,
                                                }))
                                            }
                                            rows={3}
                                            placeholder="Descripción breve de la categoría..."
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="tipo">Tipo</Label>
                                        <Select
                                            value={formData.tipo}
                                            onValueChange={(v) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    tipo: v,
                                                }))
                                            }
                                        >
                                            <SelectTrigger id="tipo">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {tiposParaSelect.map((tipo) => (
                                                    <SelectItem
                                                        key={tipo.value}
                                                        value={tipo.value}
                                                    >
                                                        {tipo.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Visibilidad</Label>
                                        <div className="grid gap-2">
                                            <label className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/30">
                                                <input
                                                    type="checkbox"
                                                    id="activo"
                                                    checked={formData.activo}
                                                    onChange={(e) =>
                                                        setFormData((prev) => ({
                                                            ...prev,
                                                            activo: e.target
                                                                .checked,
                                                        }))
                                                    }
                                                    className="mt-0.5 h-4 w-4"
                                                />
                                                <div>
                                                    <span className="flex items-center gap-1 text-sm font-semibold">
                                                        Categoría activa
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <HelpCircle className="h-3.5 w-3.5 cursor-help text-slate-400" />
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p className="max-w-xs">
                                                                        Si se
                                                                        desactiva,
                                                                        la
                                                                        categoría
                                                                        y sus
                                                                        productos
                                                                        asociados
                                                                        no serán
                                                                        visibles
                                                                        en el
                                                                        sistema
                                                                        ni
                                                                        marketplace.
                                                                    </p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </span>
                                                    <p className="text-xs text-muted-foreground">
                                                        Controla el uso interno
                                                        de la categoría.
                                                    </p>
                                                </div>
                                            </label>

                                            <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 transition-colors hover:bg-emerald-100/60">
                                                <input
                                                    type="checkbox"
                                                    id="mostrar-en-perfil"
                                                    checked={
                                                        formData.mostrar_en_perfil
                                                    }
                                                    onChange={(e) =>
                                                        setFormData((prev) => ({
                                                            ...prev,
                                                            mostrar_en_perfil:
                                                                e.target
                                                                    .checked,
                                                        }))
                                                    }
                                                    className="mt-0.5 h-4 w-4"
                                                />
                                                <div>
                                                    <span className="flex items-center gap-1 text-sm font-semibold text-emerald-800">
                                                        Publicar en marketplace
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <HelpCircle className="h-3.5 w-3.5 cursor-help text-emerald-500" />
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p className="max-w-xs">
                                                                        Determina
                                                                        si esta
                                                                        categoría
                                                                        se
                                                                        agrega
                                                                        automáticamente
                                                                        al
                                                                        catálogo
                                                                        público
                                                                        del
                                                                        marketplace.
                                                                    </p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </span>
                                                    <p className="text-xs text-emerald-700/80">
                                                        Aparece en el catálogo
                                                        público de tu tienda.
                                                    </p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Imagen</Label>
                                        {imagenError && (
                                            <div className="rounded-md bg-destructive/10 p-3 text-xs text-destructive">
                                                {imagenError}
                                            </div>
                                        )}
                                        {serverError && (
                                            <div className="rounded-md bg-destructive/10 p-3 text-xs text-destructive">
                                                {serverError}
                                            </div>
                                        )}
                                        <div className="flex items-start gap-2">
                                            <div className="min-w-0 flex-1">
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
                                                    className="flex min-h-[160px] w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-muted-foreground/20 bg-muted/30 transition-all hover:bg-muted/50 sm:min-h-[200px]"
                                                >
                                                    {imagenPreview ? (
                                                        <img
                                                            src={imagenPreview}
                                                            alt="Preview"
                                                            className="max-h-[260px] w-full object-contain"
                                                        />
                                                    ) : (
                                                        <div className="flex flex-col items-center gap-2 p-4 text-center text-muted-foreground">
                                                            <div className="rounded-full bg-background p-3 shadow-sm">
                                                                <ImageIcon className="h-8 w-8 text-primary/60" />
                                                            </div>
                                                            <div>
                                                                <p className="text-sm font-bold">
                                                                    Toca para
                                                                    subir una
                                                                    imagen
                                                                </p>
                                                                <p className="text-[11px] opacity-70">
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
                                                    className="shrink-0"
                                                    onClick={removeImage}
                                                    aria-label="Quitar imagen"
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter className="sticky bottom-0 mt-4 flex flex-col-reverse gap-2 border-t bg-background pt-4 sm:flex-row sm:justify-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full sm:w-auto"
                                        onClick={() => setIsOpen(false)}
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        type="submit"
                                        className="w-full sm:w-auto"
                                        disabled={isSubmitting}
                                    >
                                        <Check className="mr-2 h-4 w-4" />
                                        {isSubmitting
                                            ? 'Guardando...'
                                            : 'Guardar'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </div>
                    </DialogContent>
                </Dialog>

                {/* Modal de detalle */}
                <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
                    <DialogContent className="max-h-[92vh] w-[95vw] max-w-3xl overflow-hidden border-none bg-white p-0 shadow-2xl">
                        <DialogHeader className="relative overflow-hidden px-4 pt-8 pb-14 sm:px-8 sm:pt-10 sm:pb-20">
                            <div className="absolute inset-0 bg-gradient-to-br from-purple-700 via-violet-800 to-emerald-950" />
                            <div className="absolute top-0 right-0 p-4 text-white opacity-20 sm:p-8">
                                <Eye className="h-16 w-16 rotate-12 sm:h-24 sm:w-24" />
                            </div>

                            <div className="relative z-10 flex flex-col gap-1 text-white">
                                <Badge className="w-fit border-none bg-white/20 px-3 py-1 text-[10px] font-bold tracking-widest text-white uppercase hover:bg-white/30">
                                    Detalle de Categoría
                                </Badge>
                                <DialogTitle className="text-xl font-black tracking-tight break-words text-white uppercase sm:text-3xl">
                                    {viendo?.nombre}
                                </DialogTitle>
                                <DialogDescription className="text-xs font-medium text-purple-100/80 sm:text-base">
                                    Clasificación y configuración de
                                    visualización.
                                </DialogDescription>
                            </div>
                        </DialogHeader>

                        {viendo && (
                            <div className="relative z-20 -mt-8 mb-4 flex max-h-[calc(92vh-180px)] flex-col gap-4 overflow-y-auto px-4 sm:-mt-10 sm:mb-6 sm:gap-6 sm:px-8">
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:gap-3">
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
                                            label: 'Marketplace',
                                            val:
                                                viendo.mostrar_en_perfil &&
                                                viendo.public_profile_id !==
                                                    null
                                                    ? 'PUBLICADA'
                                                    : 'INTERNA',
                                            color:
                                                viendo.mostrar_en_perfil &&
                                                viendo.public_profile_id !==
                                                    null
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                    : 'border-gray-200 bg-gray-50 text-gray-800',
                                        },
                                    ].map((item) => (
                                        <div
                                            key={item.label}
                                            className={`rounded-xl border p-3 sm:p-4 ${item.color}`}
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

                                <div className="grid grid-cols-1 items-start gap-4 sm:gap-6 lg:grid-cols-2">
                                    <div className="flex flex-col gap-2">
                                        <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                            Imagen Representativa
                                        </Label>
                                        <div className="flex aspect-video items-center justify-center overflow-hidden rounded-xl border bg-gray-50 shadow-sm sm:rounded-2xl">
                                            {viendo.imagen ? (
                                                <img
                                                    src={`/storage/${viendo.imagen}`}
                                                    alt={viendo.nombre}
                                                    className="h-full w-full object-contain"
                                                />
                                            ) : (
                                                <div className="flex flex-col items-center gap-2 text-muted-foreground opacity-40">
                                                    <ImageIcon className="h-10 w-10 sm:h-12 sm:w-12" />
                                                    <span className="text-[10px] font-bold tracking-widest uppercase">
                                                        Sin Imagen
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-4 sm:gap-6">
                                        <Card className="border-none bg-gray-50/50 shadow-sm">
                                            <CardHeader className="border-b border-gray-100 pb-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="rounded-md bg-purple-100 p-1.5 text-purple-700">
                                                        <Tags className="h-4 w-4" />
                                                    </div>
                                                    <CardTitle className="text-sm font-bold text-gray-800 sm:text-base">
                                                        Descripción completa
                                                    </CardTitle>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="p-4 text-sm leading-relaxed font-medium break-words text-gray-600 italic sm:p-5">
                                                {viendo.descripcion ||
                                                    'Esta categoría no cuenta con una descripción detallada en el sistema.'}
                                            </CardContent>
                                        </Card>

                                        <div className="flex items-start gap-3 rounded-xl border border-amber-100 bg-amber-50/50 p-3 sm:p-4">
                                            <div className="mt-1 rounded-full bg-amber-200 p-1">
                                                <Check className="h-3 w-3 text-amber-700" />
                                            </div>
                                            <div className="space-y-1">
                                                <p className="text-xs font-bold text-amber-900">
                                                    Configuración de
                                                    Visualización
                                                </p>
                                                <p className="text-[10px] leading-relaxed font-medium text-amber-800/70 sm:text-xs">
                                                    {viendo.mostrar_en_perfil
                                                        ? 'Esta categoría se agrega automáticamente al catálogo público del marketplace.'
                                                        : 'Esta categoría se mantiene para uso administrativo interno solamente.'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        <DialogFooter className="border-t bg-gray-50 p-4 sm:p-6">
                            <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                                {viendo?.mostrar_en_perfil &&
                                    viendo.public_profile_id !== null &&
                                    tiendaSlug && (
                                        <a
                                            href={`/tienda/${tiendaSlug}/categoria/${viendo.id}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                                        >
                                            <ShoppingBag className="h-4 w-4" />
                                            Ver Catálogo
                                        </a>
                                    )}
                                <Button
                                    variant="outline"
                                    onClick={() => setIsViewOpen(false)}
                                    className="w-full border-gray-200 font-bold shadow-sm transition-all hover:bg-white hover:text-primary active:scale-95 sm:w-auto sm:px-10"
                                >
                                    Cerrar Detalle
                                </Button>
                            </div>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </TooltipProvider>
        </AppLayout>
    );
}
