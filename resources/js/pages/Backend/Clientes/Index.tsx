
import { Head, useForm, router } from '@inertiajs/react';
import {
    Check,
    Pencil,
    Trash2,
    Search,
    X,
    Users,
    UserPlus,
    IdCard,
    Phone,
    Mail,
    MapPin,
    Eye,
    ShieldCheck,
    MessageSquare,
    LayoutGrid,
    List,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { BulkActions } from '@/components/shared/BulkActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
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
import { WhatsAppButton } from '@/components/whatsapp-button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Categoria {
    id: number;
    nombre: string;
}

interface Cliente {
    id: number;
    nombre: string;
    nit: string | null;
    rut: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    ciudad: string | null;
    region: string | null;
    comuna: string | null;
    giro: string | null;
    contacto: string | null;
    telefono_contacto: string | null;
    categoria_id: number | null;
    activo: boolean;
    notas: string | null;
    categoria?: Categoria;
    created_at: string;
    user_id: number | null;
    tiene_acceso: boolean;
    imagen?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Clientes', href: '/clientes' },
];

export default function Index({
    clientes,
    categorias,
    filters,
}: {
    clientes: {
        data: Cliente[];
        links: any[];
        from?: number;
        to?: number;
        total?: number;
        meta?: any;
    };
    categorias: Categoria[];
    filters: {
        search?: string;
        categoria_id?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('comercial.clientes.create');
    const canEdit = hasPermission('comercial.clientes.edit');
    const canDelete = hasPermission('comercial.clientes.delete');
    const [previewCliente, setPreviewCliente] = useState<string | null>(null);
    const [isOpen, setIsOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [editando, setEditando] = useState<Cliente | null>(null);
    const [viendo, setViendo] = useState<Cliente | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [categoriaFilter, setCategoriaFilter] = useState(
        filters.categoria_id || 'all',
    );

    const formatRut = (value: string): string => {
        // Remove all non-numeric/K characters
        const cleaned = value.replace(/[^k0-9]/gi, '');

        // If empty, return empty string
        if (!cleaned) return '';

        // Separate the verifier digit (last character) from the rest
        const verifier = cleaned.slice(-1);
        const numbers = cleaned.slice(0, -1);

        // Add dots as thousand separators to the numbers part
        const formattedNumbers = numbers.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        // Return formatted RUT with verifier
        return formattedNumbers ? `${formattedNumbers}-${verifier}` : `${verifier}`;
    };

const {
        data,
        setData,
        delete: destroy,
        reset,
        processing,
        errors,
    } = useForm({
        nombre: '',
        nit: '',
        rut: '',
        telefono: '',
        email: '',
        direccion: '',
        ciudad: '',
        region: '',
        comuna: '',
        giro: '',
        contacto: '',
        telefono_contacto: '',
        categoria_id: '' as string,
        activo: true,
        notas: '',
        crear_usuario: false,
        password: '',
        password_confirmation: '',
        imagen: null as File | null,
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (categoriaFilter !== 'all') query.categoria_id = categoriaFilter;

            router.get('/clientes', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);

        return () => clearTimeout(timer);
    }, [searchTerm, categoriaFilter]);

    const limpiarFiltros = () => {
        setSearchTerm('');
        setCategoriaFilter('all');
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Format NIT and RUT fields before submission to ensure proper validation
        const formattedData = {
            ...data,
            nit: data.nit ? formatRut(data.nit) : data.nit,
            rut: data.rut ? formatRut(data.rut) : data.rut,
        };

        const formData = new FormData();
        Object.entries(formattedData).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                if (typeof value === 'boolean') {
                    formData.append(key, value ? '1' : '0');
                } else if (value instanceof File) {
                    formData.append(key, value);
                } else {
                    formData.append(key, String(value));
                }
            }
        });

        if (editando) {
            formData.append('_method', 'PUT');
            router.post(`/clientes/${editando.id}`, formData, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                    setData('imagen', null);
                    alert('Cliente actualizado correctamente');
                },
                onError: (errors: any) => {
                    alert('Error al actualizar: ' + JSON.stringify(errors));
                },
            });
        } else {
            router.post('/clientes', formData, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                    setData('imagen', null);
                    alert('Cliente creado correctamente');
                },
                onError: (errors: any) => {
                    alert('Error al crear: ' + JSON.stringify(errors));
                },
            });
        }
    };

    const handleEdit = (cliente: Cliente) => {
        setEditando(cliente);
        setData({
            nombre: cliente.nombre,
            nit: cliente.nit || '',
            rut: cliente.rut || '',
            telefono: cliente.telefono || '',
            email: cliente.email || '',
            direccion: cliente.direccion || '',
            ciudad: cliente.ciudad || '',
            region: cliente.region || '',
            comuna: cliente.comuna || '',
            giro: cliente.giro || '',
            contacto: cliente.contacto || '',
            telefono_contacto: cliente.telefono_contacto || '',
            categoria_id: cliente.categoria_id?.toString() || '',
            activo: cliente.activo,
            notas: cliente.notas || '',
            crear_usuario: cliente.tiene_acceso || false,
            password: '',
            password_confirmation: '',
            imagen: null,
        });
        setIsOpen(true);
    };

    const handleView = (cliente: Cliente) => {
        setViendo(cliente);
        setIsViewOpen(true);
    };

    const handleNew = () => {
        setEditando(null);
        reset();
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Está seguro de eliminar este cliente?')) {
            destroy(`/clientes/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestión de Cartera de Clientes" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header Section */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight text-foreground">
                            Clientes
                        </h1>
                        <p className="text-sm font-medium text-muted-foreground">
                            Administración centralizada de prospectos y clientes
                            recurrentes
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <BulkActions
                            baseUrl="/clientes"
                            filters={{
                                search: searchTerm,
                                categoria_id: categoriaFilter,
                            }}
                            modelName="Clientes"
                        />

                        {canCreate && (
                            <Button
                                onClick={handleNew}
                                className="h-9 rounded-full bg-primary px-5 font-bold shadow-lg shadow-primary/20 transition-all hover:bg-primary/90"
                            >
                                <UserPlus className="mr-2 h-4 w-4" /> Registrar
                                Cliente
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6">
                    <Card className="overflow-hidden border-none shadow-xl shadow-foreground/5">
                        <CardHeader className="bg-gradient-to-r from-muted/50 to-transparent pb-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Users className="h-5 w-5 text-primary" />
                                    <CardTitle>
                                        Base de Datos Principal
                                    </CardTitle>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="text-xs font-bold tracking-widest text-muted-foreground uppercase">
                                        {clientes.total} Registros
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
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {/* Filters Bar */}
                            <div className="flex flex-col gap-4 border-b border-muted/30 bg-muted/20 p-4 md:flex-row md:items-center">
                                <div className="relative flex-1">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Buscar por nombre, RUT, email o teléfono..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="h-10 border-none bg-background/50 pl-10 focus-visible:ring-primary/20"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Select
                                        value={categoriaFilter}
                                        onValueChange={setCategoriaFilter}
                                    >
                                        <SelectTrigger className="h-10 w-full border-none bg-background/50 sm:w-[200px]">
                                            <SelectValue placeholder="Categoría" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todas las categorías
                                            </SelectItem>
                                            {categorias.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={c.id.toString()}
                                                >
                                                    {c.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="h-10 w-10 border-none bg-background/50"
                                        onClick={limpiarFiltros}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            {viewMode === 'table' ? (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                    <th className="px-6 py-4 text-left">
                                                        Cliente / Identificación
                                                    </th>
                                                    <th className="px-6 py-4 text-left">
                                                        Contacto
                                                    </th>
                                                    <th className="px-6 py-4 text-left">
                                                        Categoría
                                                    </th>
                                                    <th className="px-6 py-4 text-left">
                                                        Acceso
                                                    </th>
                                                    <th className="px-6 py-4 text-left">
                                                        Estado
                                                    </th>
                                                    <th className="px-6 py-4 text-right">
                                                        Acciones
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-muted/50">
                                                {clientes.data.map((c) => (
                                                    <tr
                                                        key={c.id}
                                                        className="group transition-colors hover:bg-muted/30"
                                                    >
                                                        <td className="px-6 py-4">
                                                            <div className="flex items-center gap-3">
                                                                {c.imagen ? (
                                                                    <img
                                                                        src={c.imagen}
                                                                        alt={c.nombre}
                                                                        className="h-10 w-10 rounded-full object-cover"
                                                                    />
                                                                ) : (
                                                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
                                                                        <Users className="h-5 w-5 text-muted-foreground" />
                                                                    </div>
                                                                )}
                                                                <div>
                                                                    <div className="text-sm font-bold tracking-tight">
                                                                        {c.nombre}
                                                                    </div>
                                                                    <div className="flex items-center gap-1.5 font-mono text-[10px] leading-none text-muted-foreground">
                                                                        <IdCard className="h-2.5 w-2.5" />
                                                                        {c.rut ||
                                                                            c.nit ||
                                                                            'Sin RUT'}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="space-y-1">
                                                                <div className="flex items-center gap-1.5 text-xs font-medium text-foreground">
                                                                    <Phone className="h-3 w-3 text-muted-foreground" />
                                                                    {c.telefono ||
                                                                        'N/A'}
                                                                    {c.telefono && (
                                                                        <WhatsAppButton
                                                                            phone={
                                                                                c.telefono
                                                                            }
                                                                            nombre={
                                                                                c.nombre
                                                                            }
                                                                        />
                                                                    )}
                                                                </div>
                                                                <div className="flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                    <Mail className="h-2.5 w-2.5" />
                                                                    {c.email || 'N/A'}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <Badge
                                                                variant="outline"
                                                                className="border-primary/20 bg-primary/5 text-[10px] font-bold text-primary"
                                                            >
                                                                {c.categoria?.nombre ||
                                                                    'General'}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            {c.tiene_acceso ? (
                                                                <Badge className="flex w-fit items-center gap-1 rounded-full border border-blue-200 bg-blue-500/10 px-2 py-0.5 text-[9px] font-black text-blue-600 uppercase">
                                                                    <ShieldCheck className="h-2.5 w-2.5" />{' '}
                                                                    Habilitado
                                                                </Badge>
                                                            ) : (
                                                                <span className="text-[10px] font-medium text-muted-foreground/50">
                                                                    Sin acceso
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            {c.activo ? (
                                                                <Badge className="rounded-full border border-green-200 bg-green-500/10 px-2 py-0.5 text-[10px] font-black text-green-600 uppercase">
                                                                    Activo
                                                                </Badge>
                                                            ) : (
                                                                <Badge className="rounded-full border border-gray-200 bg-gray-500/10 px-2 py-0.5 text-[10px] font-black text-gray-500 uppercase">
                                                                    Inactivo
                                                                </Badge>
                                                            )}
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <div className="flex justify-end gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8 text-muted-foreground hover:bg-muted"
                                                                    onClick={() =>
                                                                        handleView(c)
                                                                    }
                                                                >
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                                {canEdit && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 text-primary hover:bg-primary/10"
                                                                        onClick={() =>
                                                                            handleEdit(c)
                                                                        }
                                                                    >
                                                                        <Pencil className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                                {canDelete && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-8 w-8 text-destructive hover:bg-destructive/10"
                                                                        onClick={() =>
                                                                            handleDelete(
                                                                                c.id,
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
                                                {clientes.data.length === 0 && (
                                                    <tr>
                                                        <td
                                                            colSpan={6}
                                                            className="py-20 text-center"
                                                        >
                                                            <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                                                <Users className="h-10 w-10 opacity-20" />
                                                                <p className="font-medium">
                                                                    No se encontraron
                                                                    clientes
                                                                </p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="border-t border-muted/50 p-4">
                                        <Pagination
                                            links={clientes.links}
                                            meta={clientes.meta || clientes}
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    {clientes.data.length === 0 ? (
                                        <div className="py-12 text-center text-muted-foreground">
                                            <Users className="mx-auto mb-3 h-12 w-12 opacity-30" />
                                            <p>No se encontraron clientes.</p>
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                            {clientes.data.map((c) => (
                                                <div
                                                    key={c.id}
                                                    className="group relative overflow-hidden rounded-xl border bg-card transition-all hover:shadow-lg"
                                                >
                                                    <div className="relative h-36 overflow-hidden bg-muted">
                                                        {c.imagen ? (
                                                            <img
                                                                src={c.imagen}
                                                                alt={c.nombre}
                                                                className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                            />
                                                        ) : (
                                                            <div className="flex h-full items-center justify-center">
                                                                <Users className="h-12 w-12 text-muted-foreground/30" />
                                                            </div>
                                                        )}
                                                        <div className="absolute top-2 right-2">
                                                            {c.activo ? (
                                                                <Badge className="border-green-200 bg-green-500/10 px-2 py-0.5 text-[10px] font-black text-green-600 uppercase">
                                                                    Activo
                                                                </Badge>
                                                            ) : (
                                                                <Badge className="border-gray-200 bg-gray-500/10 px-2 py-0.5 text-[10px] font-black text-gray-500 uppercase">
                                                                    Inactivo
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        {c.tiene_acceso && (
                                                            <div className="absolute top-2 left-2">
                                                                <Badge variant="secondary" className="flex items-center gap-1 px-2 py-0.5 text-[9px] font-black text-blue-600 uppercase">
                                                                    <ShieldCheck className="h-2.5 w-2.5" />
                                                                    Acceso
                                                                </Badge>
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="space-y-2 p-4">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div>
                                                                <h3 className="font-semibold leading-tight">{c.nombre}</h3>
                                                                <p className="font-mono text-xs text-muted-foreground">{c.rut || c.nit || 'Sin RUT'}</p>
                                                            </div>
                                                        </div>
                                                        <div className="space-y-1 text-xs text-muted-foreground">
                                                            <div className="flex items-center gap-1.5">
                                                                <Mail className="h-3.5 w-3.5 shrink-0" />
                                                                <span className="truncate">{c.email || 'N/A'}</span>
                                                            </div>
                                                            <div className="flex items-center gap-1.5">
                                                                <Phone className="h-3.5 w-3.5 shrink-0" />
                                                                <span>{c.telefono || 'N/A'}</span>
                                                            </div>
                                                            {c.categoria && (
                                                                <div className="flex items-center gap-1.5">
                                                                    <Badge variant="outline" className="border-primary/20 bg-primary/5 text-[10px] font-bold text-primary">
                                                                        {c.categoria.nombre}
                                                                    </Badge>
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-1 pt-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 px-2 text-xs"
                                                                onClick={() => handleView(c)}
                                                            >
                                                                <Eye className="mr-1 h-3.5 w-3.5" /> Ver
                                                            </Button>
                                                            {canEdit && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 px-2 text-xs"
                                                                    onClick={() => handleEdit(c)}
                                                                >
                                                                    <Pencil className="mr-1 h-3.5 w-3.5" /> Editar
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 px-2 text-xs text-destructive hover:text-destructive"
                                                                    onClick={() => handleDelete(c.id)}
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
                                    <div className="border-t border-muted/50 p-4">
                                        <Pagination
                                            links={clientes.links}
                                            meta={clientes.meta || clientes}
                                        />
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Create/Edit dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[95vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-2xl md:max-w-4xl">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-6 pb-2 text-left">
                        <div className="mb-1 flex items-center gap-2">
                            <UserPlus className="h-5 w-5 text-primary" />
                            <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                Módulo CRM
                            </span>
                        </div>
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                            {editando
                                ? 'Actualizar Ficha de Cliente'
                                : 'Registro de Nuevo de Cliente'}
                        </DialogTitle>
                    </DialogHeader>

                    <form
                        onSubmit={handleSubmit}
                        className="max-h-[80vh] overflow-y-auto p-6 pt-2"
                    >
                        <div className="grid gap-6 py-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Nombre / Razón Social *
                                    </Label>
                                    <Input
                                        value={data.nombre}
                                        onChange={(e) =>
                                            setData('nombre', e.target.value)
                                        }
                                        required
                                        className="h-11 border-none bg-muted/30 font-bold"
                                    />
                                    {errors.nombre && (
                                        <p className="text-[10px] font-bold text-destructive">
                                            {errors.nombre}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        NIT
                                    </Label>
                                    <Input
                                        value={data.nit}
                                        onChange={(e) =>
                                            setData('nit', e.target.value)
                                        }
                                        onBlur={(e) =>
                                            setData('nit', formatRut(e.target.value))
                                        }
                                        maxLength={12} // Limita la escritura al formato largo legal (XX.XXX.XXX-X)
                                        placeholder="12.345.678-9"
                                        className="h-11 border-none bg-muted/30 font-black"
                                    />
                                </div>

                                 <div className="space-y-2">
                                     <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                         RUT / Identificación Tributaria
                                     </Label>
                                     <Input
                                         value={data.rut}
                                         onChange={(e) =>
                                             setData('rut', e.target.value)
                                         }
                                         onBlur={(e) =>
                                             setData('rut', formatRut(e.target.value))
                                         }
                                         maxLength={12} // Limita la escritura al formato largo legal (XX.XXX.XXX-X)
                                         placeholder="12.345.678-9"
                                     />
                                 </div>
                             </div>

                             <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                 <div className="space-y-2">
                                     <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                         Correo Electrónico
                                     </Label>
                                     <Input
                                         value={data.email}
                                         onChange={(e) =>
                                             setData('email', e.target.value)
                                         }
                                         type="email"
                                         className="h-11 border-none bg-muted/30"
                                     />
                                 </div>
                                 <div className="space-y-2">
                                     <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                         Teléfono Principal
                                     </Label>
                                     <Input
                                         value={data.telefono}
                                         onChange={(e) =>
                                             setData('telefono', e.target.value)
                                         }
                                         className="h-11 border-none bg-muted/30"
                                     />
                                 </div>
                                 <div className="space-y-2">
                                     <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                         Categoría de Cliente
                                     </Label>
                                    <Select
                                        value={data.categoria_id}
                                        onValueChange={(v) =>
                                            setData('categoria_id', v)
                                        }
                                    >
                                        <SelectTrigger className="h-11 border-none bg-muted/30 font-bold">
                                            <SelectValue placeholder="Seleccione..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categorias.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={c.id.toString()}
                                                >
                                                    {c.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 border-t pt-4 md:grid-cols-2">
                                <div className="space-y-4">
                                    <div className="mb-2 flex items-center gap-2">
                                        <MapPin className="h-4 w-4 text-primary" />
                                        <span className="text-[10px] font-black tracking-widest uppercase">
                                            Localización
                                        </span>
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
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
                                            className="h-11 border-none bg-muted/30"
                                        />
                                    </div>
                                    <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-3">
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                Comuna
                                            </Label>
                                            <Input
                                                value={data.comuna}
                                                onChange={(e) =>
                                                    setData(
                                                        'comuna',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 border-none bg-muted/30"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                Ciudad
                                            </Label>
                                            <Input
                                                value={data.ciudad}
                                                onChange={(e) =>
                                                    setData(
                                                        'ciudad',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 border-none bg-muted/30"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="space-y-4">


                                    <div className="space-y-4">
                                        <div className="mb-2 flex items-center gap-2">
                                            <ShieldCheck className="h-4 w-4 text-primary" />
                                            <span className="text-[10px] font-black tracking-widest uppercase">
                                                Seguridad y Acceso
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                Habilitar acceso a la plataforma
                                            </Label>
                                            <input
                                                type="checkbox"
                                                checked={data.crear_usuario}
                                                onChange={(e) => setData('crear_usuario', e.target.checked)}
                                                className="h-4 w-4 text-primary rounded border-gray-300 focus:ring-primary"
                                            />
                                        </div>
                                    </div>
                                    {data.crear_usuario && (
                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                    Contraseña
                                                </Label>
                                                <PasswordInput
                                                    value={data.password}
                                                    onChange={(e) =>
                                                        setData(
                                                            'password',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder={
                                                        editando
                                                            ? 'Nueva contraseña'
                                                            : 'Mínimo 8 caracteres'
                                                    }
                                                    className="h-10 border-none bg-muted/30"
                                                />
                                                {errors.password && (
                                                    <p className="text-[10px] font-bold text-destructive">
                                                        {errors.password}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                    Confirmar Contraseña
                                                </Label>
                                                <PasswordInput
                                                    value={
                                                        data.password_confirmation
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'password_confirmation',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Repita la contraseña"
                                                    className="h-10 border-none bg-muted/30"
                                                />
                                            </div>
                                        </div>
                                    )}
                                    <div className="flex items-center justify-between rounded-2xl bg-muted/30 p-4">
                                        <Label className="text-xs font-black">
                                            Estado de la cuenta
                                        </Label>
                                        <Select
                                            value={data.activo ? '1' : '0'}
                                            onValueChange={(v) =>
                                                setData('activo', v === '1')
                                            }
                                        >
                                            <SelectTrigger className="h-8 w-28 rounded-full border-none bg-background text-[10px] font-black uppercase">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1">
                                                    Activo
                                                </SelectItem>
                                                <SelectItem value="0">
                                                    Inactivo
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-2 border-t pt-4">
                                <Label className="flex items-center gap-2 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    <MessageSquare className="h-4 w-4" /> Notas
                                    Internas y Observaciones
                                </Label>
                                <textarea
                                    value={data.notas || ''}
                                    onChange={(e) =>
                                        setData('notas', e.target.value)
                                    }
                                    className="flex min-h-[100px] w-full rounded-xl border-none bg-muted/30 px-4 py-3 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                    placeholder="Acuerdos comerciales, límites de crédito, conducta de pago..."
                                />
                            </div>
                        </div>

                        <DialogFooter className="gap-2 border-t pt-6">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setIsOpen(false)}
                                className="font-bold"
                            >
                                Cerrar
                            </Button>
                        </DialogFooter>

                        <div className="px-6 pb-4">
                            <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                Foto del Cliente
                            </Label>
                            <div className="mt-2 flex items-center gap-4">
                                <label className="flex h-20 w-20 cursor-pointer items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-muted-foreground/25 bg-muted/20 transition-colors hover:border-primary/50">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                         onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (file) {
                                                setData('imagen', file);
                                                const reader = new FileReader();
                                                reader.onloadend = () => setPreviewCliente(reader.result as string);
                                                reader.readAsDataURL(file);
                                            }
                                        }}
                                    />
                                    {previewCliente ? (
                                        <img
                                            src={previewCliente}
                                            alt="Preview"
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            + Foto
                                        </span>
                                    )}
                                </label>
                                {data.imagen && (
                                    <button
                                        type="button"
                                        className="text-xs text-red-500 hover:underline"
                                        onClick={() => setData('imagen', null)}
                                    >
                                        Eliminar
                                    </button>
                                )}
                            </div>
                        </div>

                        <DialogFooter className="px-6 pt-0 pb-6">
                            <Button
                                type="button"
                                onClick={() => setIsOpen(false)}
                                className="font-bold"
                            >
                                Cerrar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-full bg-primary px-12 font-bold shadow-lg shadow-primary/20 hover:bg-primary/90"
                            >
                                <Check className="mr-2 h-4 w-4" />{' '}
                                {editando
                                    ? 'Actualizar Ficha'
                                    : 'Dar de Alta Cliente'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* View dialog */}
            <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
                <DialogContent className="max-h-[85vh] max-w-[90vw] overflow-y-auto border-none p-0 shadow-xl md:max-w-2xl">
                    {viendo && (
                        <>
                            <DialogHeader className="relative overflow-hidden bg-gradient-to-br from-indigo-800 to-blue-900 px-4 py-4 md:px-6 md:py-5">
                                <div className="absolute top-0 right-0 p-3 opacity-15 md:p-4">
                                    {viendo.imagen ? (
                                        <img
                                            src={viendo.imagen}
                                            alt={viendo.nombre}
                                            className="h-16 w-16 rounded-full object-cover md:h-20 md:w-20"
                                        />
                                    ) : (
                                        <Users className="h-12 w-12 rotate-12 text-white md:h-16 md:w-16" />
                                    )}
                                </div>
                                <div className="relative z-10 flex items-center justify-between pr-16">
                                    <div>
                                        <Badge className="mb-1 border-white/30 bg-white/20 px-2 py-0.5 text-[8px] font-bold tracking-widest text-white uppercase md:px-3 md:text-[10px]">
                                            Cliente
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
                                    <div className="col-span-2 row-span-2 space-y-1 rounded-2xl bg-muted/30 p-4">
                                        <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Foto
                                        </span>
                                        <div className="flex items-center justify-center py-2">
                                            {viendo.imagen ? (
                                                <img
                                                    src={viendo.imagen}
                                                    alt={viendo.nombre}
                                                    className="h-24 w-24 rounded-2xl border-2 border-primary/20 object-cover shadow-md md:h-32 md:w-32"
                                                />
                                            ) : (
                                                <div className="flex h-24 w-24 items-center justify-center rounded-2xl bg-muted md:h-32 md:w-32">
                                                    <Users className="h-12 w-12 text-muted-foreground" />
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <div className="space-y-1 rounded-2xl bg-muted/30 p-3">
                                        <span className="text-[8px] font-black tracking-widest text-muted-foreground uppercase">
                                            RUT
                                        </span>
                                        <p className="font-mono text-sm font-black">
                                            {viendo.rut || viendo.nit || '---'}
                                        </p>
                                    </div>
                                    <div className="space-y-1 rounded-2xl bg-muted/30 p-3">
                                        <span className="text-[8px] font-black tracking-widest text-muted-foreground uppercase">
                                            Categoría
                                        </span>
                                        <Badge className="block w-fit border-none bg-primary/10 text-[10px] font-bold text-primary">
                                            {viendo.categoria?.nombre ||
                                                'General'}
                                        </Badge>
                                    </div>
                                    <div className="space-y-1 rounded-2xl bg-muted/30 p-3">
                                        <span className="text-[8px] font-black tracking-widest text-muted-foreground uppercase">
                                            Antigüedad
                                        </span>
                                        <p className="font-mono text-sm font-black">
                                            {new Date(
                                                viendo.created_at,
                                            ).toLocaleDateString()}
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
                                                <div className="flex items-center gap-1.5">
                                                    <span className="text-xs font-bold">
                                                        {viendo.telefono ||
                                                            '---'}
                                                    </span>
                                                    {viendo.telefono && (
                                                        <WhatsAppButton
                                                            phone={
                                                                viendo.telefono
                                                            }
                                                            nombre={
                                                                viendo.nombre
                                                            }
                                                        />
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between border-b border-muted py-1.5">
                                                <span className="text-[10px] font-medium text-muted-foreground">
                                                    Email:
                                                </span>
                                                <span className="text-xs font-bold text-primary">
                                                    {viendo.email || '---'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="space-y-3">
                                        <h3 className="flex items-center gap-2 text-[10px] font-black tracking-widest text-primary uppercase">
                                            <MapPin className="h-3 w-3" />{' '}
                                            Ubicación
                                        </h3>
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between border-b border-muted py-1.5">
                                                <span className="text-[10px] font-medium text-muted-foreground">
                                                    Comuna:
                                                </span>
                                                <span className="text-xs font-bold">
                                                    {viendo.comuna || '---'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between border-b border-muted py-1.5">
                                                <span className="text-[10px] font-medium text-muted-foreground">
                                                    Ciudad:
                                                </span>
                                                <span className="text-xs font-bold">
                                                    {viendo.ciudad || '---'}
                                                </span>
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
                                                "{viendo.notas}"
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
                                <Button
                                    onClick={() => {
                                        setIsViewOpen(false);
                                        handleEdit(viendo);
                                    }}
                                    className="rounded-full bg-primary px-8 font-black"
                                >
                                    Editar Cliente
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
