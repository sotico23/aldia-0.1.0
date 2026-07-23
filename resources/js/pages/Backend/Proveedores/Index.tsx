import { Head, useForm, router } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Building2,
    Mail,
    Phone,
    MapPin,
    User,
    ShieldCheck,
    Download,
    Upload,
    CheckCircle2,
    Eye,
    Store,
    Tag,
    Globe,
    Briefcase,
    MessageSquare
} from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { toast } from 'sonner';
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
import { PasswordInput } from '@/components/ui/password-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toaster } from '@/components/ui/sonner';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatRut } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Categoria {
    id: number;
    nombre: string;
}

interface Proveedor {
    id: number;
    nombre: string;
    nit: string | null;
    email: string | null;
    telefono: string | null;
    direccion: string | null;
    activo: boolean;
    notas: string | null;
    categoria_id: number | null;
    categoria?: Categoria;
    nombre_empresa: string | null;
    contacto_principal: string | null;
    sitio_web: string | null;
    terminos_pago: string | null;
    tiene_acceso: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/inventarios' },
    { title: 'Gestión de Proveedores', href: '/proveedors' },
];

export default function Index({
    proveedors,
    categorias,
    filters,
}: {
    proveedors: { data: Proveedor[]; links: any[]; meta: any };
    categorias: Categoria[];
    filters: {
        search?: string;
        categoria_id?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('inventario.proveedores.create');
    const canEdit = hasPermission('inventario.proveedores.edit');
    const canDelete = hasPermission('inventario.proveedores.delete');

    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Proveedor | null>(null);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [categoriaFilter, setCategoriaFilter] = useState(
        filters.categoria_id || 'all',
    );
    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [viendo, setViendo] = useState<Proveedor | null>(null);
    const {
        data,
        setData,
        delete: destroy,
        reset,
        processing,
    } = useForm({
        nombre: '',
        nit: '',
        email: '',
        telefono: '',
        direccion: '',
        activo: true,
        notas: '',
        categoria_id: '' as string | number,
        nombre_empresa: '',
        contacto_principal: '',
        sitio_web: '',
        terminos_pago: '',
        crear_usuario: false,
        password: '',
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (categoriaFilter !== 'all') query.categoria_id = categoriaFilter;

            router.get('/proveedors', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, categoriaFilter]);

    const handleExport = (type: 'csv' | 'excel') => {
        const baseUrl =
            type === 'csv' ? '/proveedors/export' : '/proveedors/export-excel';
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (categoriaFilter !== 'all')
            params.append('categoria_id', categoriaFilter);
        window.location.href = `${baseUrl}?${params.toString()}`;
    };

    const handleImport = (
        e: React.ChangeEvent<HTMLInputElement>,
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        type: 'csv' | 'excel',
    ) => {
        const file = e.target.files?.[0];
        if (file) {
            const formData = new FormData();
            formData.append('archivo', file);
            router.post('/proveedors/import', formData, {
                onSuccess: () => {
                    if (csvInputRef.current) csvInputRef.current.value = '';
                    if (excelInputRef.current) excelInputRef.current.value = '';
                    toast.success('Proveedores importados');
                },
            });
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const formattedData = {
            ...data,
            nit: data.nit ? formatRut(data.nit) : data.nit,
        };

        const formData = new FormData();
        Object.entries(formattedData).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                if (typeof value === 'boolean') {
                    formData.append(key, value ? '1' : '0');
                } else {
                    formData.append(key, String(value));
                }
            }
        });

        if (editando) {
            formData.append('_method', 'PUT');
            router.post(`/proveedors/${editando.id}`, formData, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                    toast.success('Proveedor actualizado');
                },
                onError: (errs) => {
                    toast.error('Error de validación: ' + Object.values(errs).join(', '));
                },
            });
        } else {
            router.post('/proveedors', formData, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                    toast.success('Proveedor registrado');
                },
                onError: (errs) => {
                    toast.error('Error de validación: ' + Object.values(errs).join(', '));
                },
            });
        }
    };

    const handleEdit = (p: Proveedor) => {
        setEditando(p);
        setData({
            nombre: p.nombre,
            nit: p.nit || '',
            email: p.email || '',
            telefono: p.telefono || '',
            direccion: p.direccion || '',
            activo: p.activo,
            notas: p.notas || '',
            categoria_id: p.categoria_id || '',
            nombre_empresa: p.nombre_empresa || '',
            contacto_principal: p.contacto_principal || '',
            sitio_web: p.sitio_web || '',
            terminos_pago: p.terminos_pago || '',
            crear_usuario: p.tiene_acceso,
            password: '',
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Desea eliminar este proveedor?')) {
            destroy(`/proveedors/${id}`, {
                onSuccess: () => toast.success('Proveedor eliminado'),
            });
        }
    };

    const handleView = (proveedor: Proveedor) => {
        setViendo(proveedor);
        setIsViewOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestión de Proveedores" />
            <Toaster position="bottom-right" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="mb-1 flex items-center gap-2">
                            <Store className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Supply Chain Division
                            </span>
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Proveedores
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Administre su red de suministros y relaciones
                            comerciales
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <div className="flex gap-1 rounded-xl bg-muted/30 p-1">
                            <Button
                                variant={viewMode === 'cards' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setViewMode('cards')}
                                className="h-8 rounded-lg"
                            >
                                <LayoutGrid className="h-4 w-4" />
                            </Button>
                            <Button
                                variant={viewMode === 'table' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setViewMode('table')}
                                className="h-8 rounded-lg"
                            >
                                <List className="h-4 w-4" />
                            </Button>
                        </div>
                        <input
                            type="file"
                            ref={csvInputRef}
                            className="hidden"
                            accept=".csv"
                            onChange={(e) => handleImport(e, 'csv')}
                        />
                        <input
                            type="file"
                            ref={excelInputRef}
                            className="hidden"
                            accept=".xlsx,.xls"
                            onChange={(e) => handleImport(e, 'excel')}
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
                                    <Upload className="mr-2 h-4 w-4 text-blue-500" />{' '}
                                    Importar Proveedores
                                </DropdownMenuItem>
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
                                <Plus className="mr-2 h-4 w-4" /> Nuevo Proveedor
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
                                placeholder="Buscar por nombre, RUT, o email..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="h-11 rounded-2xl border-none bg-background pl-12 shadow-sm focus-visible:ring-primary/20"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Select
                                value={categoriaFilter}
                                onValueChange={setCategoriaFilter}
                            >
                                <SelectTrigger className="h-11 w-full rounded-2xl border-none bg-background font-bold shadow-sm md:w-[200px]">
                                    <SelectValue placeholder="Categoría" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todas las categorías
                                    </SelectItem>
                                    {categorias.map((c) => (
                                        <SelectItem
                                            key={c.id}
                                            value={String(c.id)}
                                        >
                                            {c.nombre}
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
                                    setCategoriaFilter('all');
                                }}
                            >
                                <X className="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    {viewMode === 'cards' ? (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                            {proveedors.data.map((p) => (
                                <Card
                                    key={p.id}
                                    className="group overflow-hidden rounded-3xl border-none shadow-xl shadow-foreground/5 transition-all duration-300 hover:ring-2 hover:ring-primary/20"
                                >
                                    <CardHeader className="pb-4">
                                        <div className="mb-2 flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1 flex-wrap flex gap-1">
                                                <Badge
                                                    variant="outline"
                                                    className={`shrink-0 ${p.activo ? 'bg-green-500/10 text-green-600' : 'bg-red-500/10 text-red-600'} rounded-full border-none px-2 py-0.5 text-[9px] font-black uppercase`}
                                                >
                                                    {p.activo
                                                        ? 'Activo'
                                                        : 'Inactivo'}
                                                </Badge>
                                                {p.categoria && (
                                                    <Badge
                                                        variant="outline"
                                                        className="shrink-0 rounded-full border-none bg-primary/10 px-2 py-0.5 text-[9px] font-black text-primary uppercase"
                                                    >
                                                        {p.categoria.nombre}
                                                    </Badge>
                                                )}
                                                {p.tiene_acceso && (
                                                    <Badge
                                                        variant="outline"
                                                        className="shrink-0 rounded-full border-none bg-blue-500/10 px-2 py-0.5 text-[9px] font-black text-blue-600 uppercase"
                                                    >
                                                        <ShieldCheck className="mr-0.5 h-2.5 w-2.5" />
                                                        Portal
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="flex shrink-0 gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 rounded-full text-muted-foreground hover:text-foreground"
                                                    onClick={() => handleView(p)}
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                                {canEdit && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 rounded-full text-primary hover:bg-primary/10"
                                                    onClick={() => handleEdit(p)}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            )}
                                                {canDelete && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 rounded-full text-destructive hover:bg-destructive/10"
                                                    onClick={() =>
                                                        handleDelete(p.id)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                            </div>
                                        </div>
                                        <CardTitle className="truncate text-xl font-black transition-colors group-hover:text-primary">
                                            {p.nombre}
                                        </CardTitle>
                                        <CardDescription className="flex items-center gap-1.5 font-bold">
                                            <Tag className="h-3 w-3 shrink-0" />
                                            <span className="truncate">
                                                {p.nit || 'Sin RUT'}
                                            </span>
                                            {p.nombre_empresa && (
                                                <span className="hidden truncate text-muted-foreground/50 sm:inline">
                                                    · {p.nombre_empresa}
                                                </span>
                                            )}
                                        </CardDescription>
                                    </CardHeader>

                                    <CardContent className="space-y-6">
                                        <div className="space-y-3">
                                            <div className="group/item flex items-center gap-3 rounded-2xl bg-muted/30 p-3 text-sm font-medium text-muted-foreground">
                                                <div className="rounded-xl bg-background p-2 text-primary shadow-sm transition-transform group-hover/item:scale-110">
                                                    <Mail className="h-4 w-4" />
                                                </div>
                                                <span className="min-w-0 truncate">
                                                    {p.email || 'No registrado'}
                                                </span>
                                            </div>
                                            <div className="group/item flex items-center gap-3 rounded-2xl bg-muted/30 p-3 text-sm font-medium text-muted-foreground">
                                                <div className="rounded-xl bg-background p-2 text-green-600 shadow-sm transition-transform group-hover/item:scale-110">
                                                    <Phone className="h-4 w-4" />
                                                </div>
                                                <span className="min-w-0 truncate">
                                                    {p.telefono || 'No registrado'}
                                                </span>
                                                {p.telefono && (
                                                    <WhatsAppButton
                                                        phone={p.telefono}
                                                        nombre={p.nombre}
                                                        className="shrink-0"
                                                    />
                                                )}
                                            </div>
                                            <div className="group/item flex items-center gap-3 rounded-2xl bg-muted/30 p-3 text-sm font-medium text-muted-foreground">
                                                <div className="rounded-xl bg-background p-2 text-orange-600 shadow-sm transition-transform group-hover/item:scale-110">
                                                    <MapPin className="h-4 w-4" />
                                                </div>
                                                <span className="min-w-0 truncate">
                                                    {p.direccion || 'No registrada'}
                                                </span>
                                            </div>
                                            {p.sitio_web && (
                                                <a
                                                    href={p.sitio_web}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="group/item flex items-center gap-3 rounded-2xl bg-muted/30 p-3 text-sm font-medium text-muted-foreground hover:bg-primary/10 hover:text-primary transition-colors"
                                                >
                                                    <div className="rounded-xl bg-background p-2 text-purple-600 shadow-sm transition-transform group-hover/item:scale-110">
                                                        <Globe className="h-4 w-4" />
                                                    </div>
                                                    <span className="min-w-0 truncate underline underline-offset-2">
                                                        {p.sitio_web}
                                                    </span>
                                                </a>
                                            )}
                                        </div>

                                        <div className="flex items-center justify-between border-t border-muted/50 pt-4">
                                            <div className="flex items-center gap-2 min-w-0">
                                                <User className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                <span className="truncate text-[10px] font-black tracking-tight text-muted-foreground uppercase">
                                                    {p.contacto_principal ||
                                                        'Sin contacto'}
                                                </span>
                                            </div>
                                            {p.terminos_pago && (
                                                <Badge
                                                    variant="outline"
                                                    className="shrink-0 rounded-full border-none bg-primary/5 px-2 py-0.5 text-[8px] font-black text-primary uppercase"
                                                >
                                                    {p.terminos_pago}
                                                </Badge>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-3xl border border-muted bg-background shadow-sm">
                            <table className="w-full text-xs sm:text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 text-left">Proveedor</th>
                                        <th className="px-4 py-3 text-left">RUT / Contacto</th>
                                        <th className="px-4 py-3 text-left">Categoría</th>
                                        <th className="px-4 py-3 text-center">Estado</th>
                                        <th className="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-muted/50">
                                    {proveedors.data.map((p) => (
                                        <tr key={p.id} className="group transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <div className="font-medium">{p.nombre}</div>
                                                {p.nombre_empresa && (
                                                    <div className="text-[10px] text-muted-foreground">{p.nombre_empresa}</div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-xs">{p.nit || 'Sin RUT'}</div>
                                                <div className="text-[10px] text-muted-foreground">{p.email || 'Sin email'}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {p.categoria ? (
                                                    <Badge variant="outline" className="rounded-full border-none bg-primary/10 px-2 py-0.5 text-[9px] font-black text-primary uppercase">
                                                        {p.categoria.nombre}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">General</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge variant="outline" className={`${p.activo ? 'bg-green-500/10 text-green-600' : 'bg-red-500/10 text-red-600'} rounded-full border-none px-2 py-0.5 text-[9px] font-black uppercase`}>
                                                    {p.activo ? 'Activo' : 'Inactivo'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground" onClick={() => handleView(p)}>
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                    {canEdit && (
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-primary hover:bg-primary/10" onClick={() => handleEdit(p)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive hover:bg-destructive/10" onClick={() => handleDelete(p.id)}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {proveedors.data.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="py-8 text-center text-muted-foreground">
                                                No se encontraron proveedores con los filtros aplicados
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="rounded-3xl border border-muted bg-background p-4 shadow-sm">
                        <Pagination
                            links={proveedors.links}
                            meta={proveedors.meta}
                        />
                    </div>
                </div>
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="flex max-h-[90vh] flex-col gap-0 overflow-y-auto rounded-[40px] border-none p-0 shadow-2xl">
                    <DialogHeader className="shrink-0 bg-gradient-to-r from-primary/10 to-transparent p-4 pb-2 text-left sm:p-6 sm:pb-3">
                        <div className="mb-1 flex items-center gap-2">
                            <Briefcase className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Partnership Enrollment
                            </span>
                        </div>
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary sm:text-3xl">
                            {editando
                                ? 'Actualizar Proveedor'
                                : 'Registrar Nuevo Proveedor'}
                        </DialogTitle>
                        <DialogDescription className="font-medium text-muted-foreground">
                            Configure los detalles comerciales y fiscales del
                            socio de suministro.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={handleSubmit}
                        className="flex min-h-0 flex-1 flex-col overflow-hidden"
                    >
                        <div className="flex-1 overflow-y-auto p-4 sm:p-6">
                            <div className="grid grid-cols-1 gap-4 py-3 md:grid-cols-2 md:gap-6">
                            <div className="space-y-4">
                                <h3 className="flex items-center gap-2 text-[11px] font-black tracking-widest text-primary uppercase">
                                    <Store className="h-4 w-4" /> Información
                                    Comercial
                                </h3>
                                <div className="space-y-3">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Nombre Comercial / Razon Social *
                                        </Label>
                                        <Input
                                            value={data.nombre}
                                            onChange={(e) =>
                                                setData(
                                                    'nombre',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                            className="h-10 rounded-2xl border-none bg-muted/30 font-bold"
                                            placeholder="Ej: Importaciones Globales S.A."
                                        />
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            RUT / NIT *
                                        </Label>
                                        <Input
                                            value={data.nit}
                                            onChange={(e) => {
                                                const raw = e.target.value;
                                                const prev = data.nit;
                                                if (raw.length > prev.length) {
                                                    setData('nit', formatRut(raw));
                                                } else {
                                                    setData('nit', raw);
                                                }
                                            }}
                                            className="h-10 rounded-2xl border-none bg-muted/30 font-bold"
                                            placeholder="12.345.678-9"
                                        />
                                    </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Categoría
                                            </Label>
                                            <Select
                                                value={String(
                                                    data.categoria_id,
                                                )}
                                                onValueChange={(v) =>
                                                    setData('categoria_id', v)
                                                }
                                            >
                                                <SelectTrigger className="h-10 rounded-2xl border-none bg-muted/30 font-bold">
                                                    <SelectValue placeholder="Seleccione..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {categorias.map((c) => (
                                                        <SelectItem
                                                            key={c.id}
                                                            value={String(c.id)}
                                                        >
                                                            {c.nombre}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Email de Contacto
                                            </Label>
                                            <Input
                                                type="email"
                                                value={data.email}
                                                onChange={(e) =>
                                                    setData(
                                                        'email',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 rounded-2xl border-none bg-muted/30 font-bold"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Teléfono
                                            </Label>
                                            <Input
                                                value={data.telefono}
                                                onChange={(e) =>
                                                    setData(
                                                        'telefono',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 rounded-2xl border-none bg-muted/30 font-bold"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Dirección
                                        </Label>
                                        <Input
                                            value={data.direccion}
                                            onChange={(e) =>
                                                setData(
                                                    'direccion',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-10 rounded-2xl border-none bg-muted/30 font-bold"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <h3 className="flex items-center gap-2 text-[11px] font-black tracking-widest text-primary uppercase">
                                    <Globe className="h-4 w-4" /> Detalles
                                    Operativos
                                </h3>
                                <div className="space-y-3 rounded-3xl border-2 border-dashed border-primary/20 bg-primary/5 p-4 sm:p-6">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-bold text-muted-foreground uppercase">
                                            Contacto Principal
                                        </Label>
                                        <Input
                                            value={data.contacto_principal}
                                            onChange={(e) =>
                                                setData(
                                                    'contacto_principal',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-10 rounded-xl border-none bg-background font-bold shadow-sm"
                                            placeholder="Nombre de la persona"
                                        />
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Términos de Pago
                                            </Label>
                                            <Input
                                                value={data.terminos_pago}
                                                onChange={(e) =>
                                                    setData(
                                                        'terminos_pago',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 rounded-xl border-none bg-background font-bold shadow-sm"
                                                placeholder="Ej: 30 días"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                                Estado
                                            </Label>
                                            <div className="flex h-10 items-center justify-center rounded-xl border bg-background">
                                                <Label className="flex cursor-pointer items-center gap-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.activo}
                                                        onChange={(e) =>
                                                            setData(
                                                                'activo',
                                                                e.target
                                                                    .checked,
                                                            )
                                                        }
                                                        className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                    />
                                                    <span className="text-xs font-black uppercase">
                                                        {data.activo
                                                            ? 'Activo'
                                                            : 'Inactivo'}
                                                    </span>
                                                </Label>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="border-t border-primary/10 pt-3">
                                        <Label className="flex cursor-pointer items-center gap-2 mb-3">
                                            <input
                                                type="checkbox"
                                                checked={data.crear_usuario}
                                                onChange={(e) =>
                                                    setData(
                                                        'crear_usuario',
                                                        e.target.checked,
                                                    )
                                                }
                                                className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                            />
                                            <span className="text-xs font-black uppercase">
                                                Acceso a Portal de Proveedor
                                            </span>
                                        </Label>

                                        {data.crear_usuario && (
                                            <div className="animate-in space-y-3 duration-300 fade-in slide-in-from-top-2">
                                                <div className="space-y-1.5">
                                                    <Label className="text-[10px] font-black text-muted-foreground uppercase">
                                                        Contraseña (Mín. 8
                                                        caracteres)
                                                    </Label>
                                                    <PasswordInput
                                                        value={data.password}
                                                        onChange={(e) =>
                                                            setData(
                                                                'password',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-10 rounded-xl border-none bg-background font-bold shadow-sm"
                                                        placeholder="Dejar en blanco para default"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 space-y-1.5 border-t pt-4">
                            <Label className="text-xs font-bold text-muted-foreground uppercase">
                                Notas Internas
                            </Label>
                            <textarea
                                value={data.notas}
                                onChange={(e) =>
                                    setData('notas', e.target.value)
                                }
                                className="flex min-h-[80px] w-full rounded-2xl border-none bg-muted/30 px-4 py-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                placeholder="Historial, acuerdos especiales, etc."
                            />
                        </div>
                        </div>

                        <DialogFooter className="shrink-0 gap-2 border-t px-4 py-4 font-black uppercase sm:px-6">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full px-6"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-full bg-primary px-10 shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <CheckCircle2 className="mr-2 h-4 w-4" />{' '}
                                {editando
                                    ? 'Sincronizar Datos'
                                    : 'Registrar Socio'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
                <DialogContent className="max-h-[85vh] max-w-[90vw] overflow-y-auto border-none p-0 shadow-xl md:max-w-2xl">
                    {viendo && (
                        <>
                            <DialogHeader className="relative overflow-hidden bg-gradient-to-br from-indigo-800 to-blue-900 px-4 py-4 md:px-6 md:py-5">
                                <div className="absolute top-0 right-0 p-3 opacity-15 md:p-4">
                                    <Building2 className="h-12 w-12 rotate-12 text-white md:h-16 md:w-16" />
                                </div>
                                <div className="relative z-10 flex items-center justify-between pr-16">
                                    <div>
                                        <Badge className="mb-1 border-white/30 bg-white/20 px-2 py-0.5 text-[8px] font-bold tracking-widest text-white uppercase md:px-3 md:text-[10px]">
                                            Proveedor
                                        </Badge>
                                        <DialogTitle className="text-xl font-black tracking-tight text-white md:text-2xl">
                                            {viendo.nombre}
                                        </DialogTitle>
                                    </div>
                                    <Badge
                                        className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${viendo.activo ? 'bg-green-500' : 'bg-red-500'}`}
                                    >
                                        {viendo.activo ? 'Activo' : 'Inactivo'}
                                    </Badge>
                                </div>
                            </DialogHeader>
                            <div className="space-y-4 px-4 py-4 md:px-6 md:py-6">
                                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                    <div className="space-y-1 rounded-2xl bg-muted/30 p-3">
                                        <span className="text-[8px] font-black tracking-widest text-muted-foreground uppercase">
                                            RUT / NIT
                                        </span>
                                        <p className="font-mono text-sm font-black">
                                            {viendo.nit || '---'}
                                        </p>
                                    </div>
                                    <div className="space-y-1 rounded-2xl bg-muted/30 p-3">
                                        <span className="text-[8px] font-black tracking-widest text-muted-foreground uppercase">
                                            Categoría
                                        </span>
                                        <Badge className="block w-fit border-none bg-primary/10 text-[10px] font-bold text-primary">
                                            {viendo.categoria?.nombre || 'General'}
                                        </Badge>
                                    </div>
                                    <div className="space-y-1 rounded-2xl bg-muted/30 p-3">
                                        <span className="text-[8px] font-black tracking-widest text-muted-foreground uppercase">
                                            Contacto Principal
                                        </span>
                                        <p className="text-sm font-black">
                                            {viendo.contacto_principal || '---'}
                                        </p>
                                    </div>
                                    <div className="space-y-1 rounded-2xl bg-muted/30 p-3">
                                        <span className="text-[8px] font-black tracking-widest text-muted-foreground uppercase">
                                            Términos de Pago
                                        </span>
                                        <p className="text-sm font-black">
                                            {viendo.terminos_pago || '---'}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-3">
                                        <h3 className="flex items-center gap-2 text-[10px] font-black tracking-widest text-primary uppercase">
                                            <Phone className="h-3 w-3" />{' '}
                                            Contacto
                                        </h3>
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between border-b border-muted py-1.5">
                                                <span className="text-[10px] font-medium text-muted-foreground">
                                                    Teléfono:
                                                </span>
                                                <span className="flex items-center gap-1.5 text-xs font-bold">
                                                    {viendo.telefono || '---'}
                                                    {viendo.telefono && (
                                                        <WhatsAppButton
                                                            phone={viendo.telefono}
                                                            nombre={viendo.nombre}
                                                        />
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between border-b border-muted py-1.5">
                                                <span className="text-[10px] font-medium text-muted-foreground">
                                                    Email:
                                                </span>
                                                <span className="text-xs font-bold text-primary">
                                                    {viendo.email || '---'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between border-b border-muted py-1.5">
                                                <span className="text-[10px] font-medium text-muted-foreground">
                                                    Sitio Web:
                                                </span>
                                                <span className="text-xs font-bold">
                                                    {viendo.sitio_web || '---'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="space-y-3">
                                        <h3 className="flex items-center gap-2 text-[10px] font-black tracking-widest text-primary uppercase">
                                            <MapPin className="h-3 w-3" />{' '}
                                            Acceso Portal
                                        </h3>
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between border-b border-muted py-1.5">
                                                <span className="text-[10px] font-medium text-muted-foreground">
                                                    Estado:
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className={`rounded-full border-none px-2 py-0.5 text-[9px] font-bold uppercase ${
                                                        viendo.tiene_acceso
                                                            ? 'bg-blue-500/10 text-blue-600'
                                                            : 'bg-gray-500/10 text-gray-500'
                                                    }`}
                                                >
                                                    {viendo.tiene_acceso
                                                        ? 'Habilitado'
                                                        : 'Deshabilitado'}
                                                </Badge>
                                            </div>
                                            <div className="flex flex-col gap-1 border-b border-muted py-1.5">
                                                <span className="text-[10px] font-medium text-muted-foreground">
                                                    Dirección:
                                                </span>
                                                <span className="text-xs font-bold">
                                                    {viendo.direccion || '---'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {viendo.notas && (
                                    <div className="flex gap-3 rounded-2xl border border-amber-100 bg-amber-50 p-4">
                                        <MessageSquare className="mt-0.5 h-4 w-4 shrink-0 text-amber-400" />
                                        <div className="space-y-1">
                                            <span className="text-[9px] font-black tracking-widest text-amber-600 uppercase">
                                                Notas
                                            </span>
                                            <p className="text-xs leading-relaxed text-amber-900 italic">
                                                &ldquo;{viendo.notas}&rdquo;
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </div>
                            <DialogFooter className="border-t bg-muted/10 p-4">
                                <Button
                                    variant="outline"
                                    onClick={() => setIsViewOpen(false)}
                                    className="rounded-full px-6 font-bold"
                                >
                                    Cerrar
                                </Button>
                                {canEdit && (
                                    <Button
                                        onClick={() => {
                                            setIsViewOpen(false);
                                            handleEdit(viendo);
                                        }}
                                        className="rounded-full bg-primary px-8 font-black"
                                    >
                                        Editar Proveedor
                                    </Button>
                                )}
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
