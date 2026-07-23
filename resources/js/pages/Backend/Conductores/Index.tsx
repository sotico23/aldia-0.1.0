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
    User,
    IdCard,
    Phone,
    Mail,
    FileText,
    ShieldAlert,

    Download,
    Upload,
    FileSpreadsheet,
    FileJson,
} from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { FormInput } from '@/components/form-input';
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
import Pagination from '@/components/ui/Pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatRut } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const ESTADOS = [
    {
        value: 'activo',
        label: 'Activo',
        color: 'bg-green-500 border-green-600 text-white',
        hover: 'hover:bg-green-500 hover:text-white hover:border-green-500',
    },
    {
        value: 'inactivo',
        label: 'Inactivo',
        color: 'bg-gray-500 border-gray-500 text-white',
        hover: 'hover:bg-gray-500 hover:text-white hover:border-gray-500',
    },
    {
        value: 'licencia_vencida',
        label: 'Lic. Vencida',
        color: 'bg-red-500 border-red-500 text-white',
        hover: 'hover:bg-red-500 hover:text-white hover:border-red-500',
    },
] as const;

function StatusDropdown({
    value,
    onChange,
}: {
    value: string;
    onChange: (v: string) => void;
}) {
    const current = ESTADOS.find((e) => e.value === value) ?? ESTADOS[2];

    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger
                className={`h-7 w-32 rounded-md border px-2 text-xs font-semibold shadow-sm transition-all focus:ring-0 focus:ring-offset-0 ${current.color}`}
            >
                <SelectValue>{current.label}</SelectValue>
            </SelectTrigger>
            <SelectContent>
                {ESTADOS.map((e) => (
                    <SelectItem
                        key={e.value}
                        value={e.value}
                        className="text-xs"
                    >
                        <div className="flex items-center gap-2">
                            <div
                                className={`h-2 w-2 rounded-full ${e.value === 'activo' ? 'bg-green-500' : e.value === 'inactivo' ? 'bg-gray-500' : 'bg-red-500'}`}
                            />
                            {e.label}
                        </div>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

interface Empleado {
    id: number;
    nombre: string;
    apellido: string;
    rut: string | null;
    email: string | null;
    telefono: string | null;
}

interface Conductor {
    id: number;
    empleado_id: number | null;
    nombre: string;
    rut: string | null;
    licencia: string | null;
    telefono: string | null;
    email: string | null;
    estado: string;
    notas: string | null;
    lat: number | null;
    lng: number | null;
    ultima_actualizacion: string | null;
    estadisticas_ventas?: {
        diario: { productos: number; kilos: number; litros: number };
        mensual: { productos: number; kilos: number; litros: number };
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Conductores', href: '/conductores' },
];

export default function Index({
    conductores,
    empleados = [],
    filters = {},
}: {
    conductores: { data: Conductor[]; links: any[]; meta?: any; total: number };
    empleados: Empleado[];
    filters?: { search?: string; estado?: string };
}) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('flota.conductores.create');
    const canEdit = hasPermission('flota.conductores.edit');
    const canDelete = hasPermission('flota.conductores.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [isVerOpen, setIsVerOpen] = useState(false);
    const [editando, setEditando] = useState<Conductor | null>(null);
    const [conductorSeleccionado, setConductorSeleccionado] =
        useState<Conductor | null>(null);
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        errors,
        clearErrors,
    } = useForm({
        empleado_id: null as number | null,
        nombre: '',
        rut: '',
        licencia: '',
        telefono: '',
        email: '',
        estado: 'activo',
        notas: '',
    });

    const [filtros, setFiltros] = useState({
        search: filters.search || '',
        estado: filters.estado || '',
    });
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

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
        formData.append('archivo', file);

        router.post('/conductores/import', formData, {
            forceFormData: true,
            onSuccess: () => {
                e.target.value = '';
            },
        });
    };

    useEffect(() => {
        const timeoutId = setTimeout(() => {
            router.get('/conductores', filtros, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['conductores'],
            });
        }, 300);

        return () => clearTimeout(timeoutId);
    }, [filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            search: '',
            estado: '',
        });
    };

    const handleRutChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        // Auto convert to uppercase on change
        setData('rut', e.target.value.toUpperCase());
        if (errors.rut) {
            clearErrors('rut');
        }
    };

    const handleEmpleadoChange = (val: string) => {
        const empId = val === 'none' ? null : parseInt(val);
        setData('empleado_id', empId);

        if (empId) {
            const emp = empleados.find((e) => e.id === empId);
            if (emp) {
                setData((prev) => ({
                    ...prev,
                    empleado_id: empId,
                    nombre: `${emp.nombre} ${emp.apellido}`,
                    rut: emp.rut || prev.rut,
                    email: emp.email || prev.email,
                    telefono: emp.telefono || prev.telefono,
                }));
            }
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando)
            put(`/conductores/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                    clearErrors();
                },
            });
        else
            post('/conductores', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                    clearErrors();
                },
            });
    };

    const handleEdit = (c: Conductor) => {
        setEditando(c);
        clearErrors();
        setData({
            empleado_id: c.empleado_id || null,
            nombre: c.nombre,
            rut: c.rut || '',
            licencia: c.licencia || '',
            telefono: c.telefono || '',
            email: c.email || '',
            estado: c.estado,
            notas: c.notas || '',
        });
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        clearErrors();
        setData({
            empleado_id: null,
            nombre: '',
            rut: '',
            licencia: '',
            telefono: '',
            email: '',
            estado: 'activo',
            notas: '',
        });
        setEditando(null);
        setIsOpen(true);
    };

    const handleVer = (c: Conductor) => {
        setConductorSeleccionado(c);
        setIsVerOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/conductores/${id}`);
    };

    
        const estadoLabels: Record<string, string> = {
        activo: 'Activo',
        inactivo: 'Inactivo',
        licencia_vencida: 'Lic. Vencida',
    };

    const getEstadoBadge = (e: string) => {
        const colores: Record<string, string> = {
            activo: 'bg-green-500',
            inactivo: 'bg-gray-500',
            licencia_vencida: 'bg-red-500',
        };
        return (
            <Badge className={colores[e] || 'bg-gray-500'}>
                {estadoLabels[e] ?? e}
            </Badge>
        );
    };

    return (
        <>
            <Head title="Conductores" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Conductores</h1>
                            <p className="text-muted-foreground">
                                Personal de transporte
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {canCreate && (
                                <Button onClick={handleNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                    Conductor
                                </Button>
                            )}
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
                                            window.location.href =
                                                '/conductores/export'
                                        }
                                    >
                                        <FileJson className="mr-2 h-4 w-4" />
                                        Exportar JSON
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() =>
                                            window.location.href =
                                                '/conductores/export-excel'
                                        }
                                    >
                                        <FileSpreadsheet className="mr-2 h-4 w-4" />
                                        Exportar Excel
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Conductores</CardTitle>
                                    <CardDescription>
                                        {conductores.total} conductores encontrados
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-1 rounded-lg border p-0.5">
                                    <Button
                                        variant={viewMode === 'table' ? 'default' : 'ghost'}
                                        size="sm"
                                        className="h-8 w-8 p-0"
                                        onClick={() => setViewMode('table')}
                                    >
                                        <List className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'cards' ? 'default' : 'ghost'}
                                        size="sm"
                                        className="h-8 w-8 p-0"
                                        onClick={() => setViewMode('cards')}
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
                                            placeholder="Buscar por nombre o licencia..."
                                            value={filtros.search}
                                            onChange={(e) =>
                                                setFiltros({
                                                    ...filtros,
                                                    search: e.target.value,
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
                                    <SelectTrigger className="h-9 w-full bg-background sm:w-[150px]">
                                        <SelectValue placeholder="Estado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos
                                        </SelectItem>
                                        {[
                                            'activo',
                                            'inactivo',
                                            'licencia_vencida',
                                        ].map((e) => (
                                            <SelectItem key={e} value={e}>
                                                {e
                                                    .toUpperCase()
                                                    .replace('_', ' ')}
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
                            {viewMode === 'table' ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-xs sm:text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="py-2 text-left font-medium">
                                                Nombre / Licencia
                                            </th>
                                            <th className="py-2 text-left font-medium">
                                                Contacto
                                            </th>
                                            <th className="py-2 text-center font-medium">
                                                Estado
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {conductores.data.map(
                                            (c: Conductor) => (
                                                <tr
                                                    key={c.id}
                                                    className="border-b transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="py-2">
                                                        <div className="font-medium">
                                                            {c.nombre}
                                                        </div>
                                                        <div className="text-[10px] text-muted-foreground">
                                                            RUT: {c.rut || '-'}{' '}
                                                            | L:{' '}
                                                            {c.licencia || '-'}
                                                        </div>
                                                    </td>
                                                    <td className="py-2">
                                                        <div className="text-[10px]">
                                                            {c.telefono || '-'}
                                                        </div>
                                                        <div className="text-[10px] text-muted-foreground">
                                                            {c.email || '-'}
                                                        </div>
                                                    </td>
                                                    <td className="py-2 text-center">
                                                        <StatusDropdown
                                                            value={c.estado}
                                                            onChange={(val) =>
                                                                router.put(
                                                                    `/conductores/${c.id}`,
                                                                    {
                                                                        estado: val,
                                                                    },
                                                                    {
                                                                        preserveScroll: true,
                                                                        only: [
                                                                            'conductores',
                                                                        ],
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                                onClick={() =>
                                                                    handleVer(c)
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
                                                                        handleEdit(
                                                                            c,
                                                                        )
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
                                            ),
                                        )}
                                        {conductores.data.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron
                                                    conductores con los filtros
                                                    aplicados
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                                <Pagination
                                    links={conductores.links}
                                    meta={conductores.meta}
                                />
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {conductores.data.map((c: Conductor) => (
                                    <Card key={c.id} className="overflow-hidden">
                                        <CardContent className="space-y-3 p-4">
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                        <User className="h-5 w-5" />
                                                    </div>
                                                    <div>
                                                        <p className="font-bold">{c.nombre}</p>
                                                        <p className="text-[10px] text-muted-foreground">
                                                            RUT: {c.rut || '-'} | L: {c.licencia || '-'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <StatusDropdown
                                                    value={c.estado}
                                                    onChange={(val) =>
                                                        router.put(`/conductores/${c.id}`, { estado: val }, { preserveScroll: true, only: ['conductores'] })
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-1 text-sm">
                                                <p className="flex items-center gap-2 text-xs">
                                                    <Phone className="h-3 w-3 text-muted-foreground" />
                                                    {c.telefono || '-'}
                                                </p>
                                                <p className="flex items-center gap-2 text-xs">
                                                    <Mail className="h-3 w-3 text-muted-foreground" />
                                                    {c.email || '-'}
                                                </p>
                                            </div>
                                            <div className="flex justify-end gap-1 border-t pt-2">
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700" onClick={() => handleVer(c)} title="Ver Detalles">
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(c)} title="Editar">
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(c.id)}>
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                                {conductores.data.length === 0 && (
                                    <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                        <User className="mb-3 h-12 w-12 opacity-20" />
                                        <p>No se encontraron conductores con los filtros aplicados</p>
                                    </div>
                                )}
                            </div>
                        )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-w-[95vw] border-none p-0 shadow-2xl md:max-w-xl overflow-y-auto">
                    <DialogHeader className="bg-gradient-to-r from-primary/10 to-transparent p-6 pb-2">
                        <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                            {editando ? 'Editar' : 'Nuevo'} Conductor
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="p-6 pt-0">
                        <div className="grid gap-6 py-4">
                            <div className="space-y-2">
                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    Vincular con Empleado (Opcional)
                                </Label>
                                <Select
                                    value={data.empleado_id?.toString() || 'none'}
                                    onValueChange={handleEmpleadoChange}
                                >
                                    <SelectTrigger className="h-11 border-none bg-muted/30 focus-visible:ring-primary/30">
                                        <SelectValue placeholder="Seleccionar un empleado..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Ninguno (Crear independiente)
                                        </SelectItem>
                                        {empleados.map((emp) => (
                                            <SelectItem
                                                key={emp.id}
                                                value={emp.id.toString()}
                                            >
                                                {emp.nombre} {emp.apellido} (
                                                {emp.rut || 'Sin RUT'})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <FormInput
                                    label="Nombre Completo *"
                                    id="nombre"
                                    value={data.nombre}
                                    onChange={(e) =>
                                        setData('nombre', e.target.value)
                                    }
                                    error={errors.nombre}
                                    required
                                    placeholder="Ej: Juan Pérez"
                                />
                                 <FormInput
                                     label="RUT (Documento ID)"
                                     id="rut"
                                     value={data.rut || ''}
                                     onChange={handleRutChange}
                                     onBlur={(e) => setData('rut', formatRut(e.target.value.toUpperCase()))}
                                     error={errors.rut}
                                     placeholder="12.345.678-9"
                                     className="font-mono"
                                 />
                            </div>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <FormInput
                                    label="N° Licencia"
                                    id="licencia"
                                    value={data.licencia || ''}
                                    onChange={(e) =>
                                        setData('licencia', e.target.value)
                                    }
                                    error={errors.licencia}
                                    className="font-mono"
                                />
                                <FormInput
                                    label="Teléfono"
                                    id="telefono"
                                    value={data.telefono || ''}
                                    onChange={(e) =>
                                        setData('telefono', e.target.value)
                                    }
                                    error={errors.telefono}
                                    placeholder="+56 9..."
                                />
                            </div>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <FormInput
                                    label="Correo Electrónico"
                                    id="email"
                                    type="email"
                                    value={data.email || ''}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    error={errors.email}
                                />
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Estado Operativo
                                    </Label>
                                    <Select
                                        value={data.estado}
                                        onValueChange={(val) =>
                                            setData('estado', val)
                                        }
                                    >
                                        <SelectTrigger className="h-11 border-none bg-muted/30 focus-visible:ring-primary/30">
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[
                                                'activo',
                                                'inactivo',
                                                'licencia_vencida',
                                            ].map((e) => (
                                                <SelectItem key={e} value={e}>
                                                    {e
                                                        .toUpperCase()
                                                        .replace('_', ' ')}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <FormInput
                                label="Notas Internas"
                                id="notas"
                                value={data.notas || ''}
                                onChange={(e) =>
                                    setData('notas', e.target.value)
                                }
                                error={errors.notas}
                            />
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
                                    : 'Registrar Conductor'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* View Modal */}
            <Dialog open={isVerOpen} onOpenChange={setIsVerOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-hidden border-none bg-background p-0 shadow-2xl md:max-w-2xl">
                    {conductorSeleccionado && (
                        <>
                            <DialogHeader className="border-b bg-gradient-to-br from-primary/10 via-background to-transparent p-8">
                                <div className="flex items-center gap-6">
                                    <div className="flex h-20 w-20 items-center justify-center rounded-3xl border-2 border-primary/20 bg-primary/10 text-primary shadow-inner">
                                        <User className="h-10 w-10" />
                                    </div>
                                    <div>
                                        <DialogTitle className="flex items-center gap-3 text-3xl font-black tracking-tight">
                                            {conductorSeleccionado.nombre}
                                            {getEstadoBadge(
                                                conductorSeleccionado.estado,
                                            )}
                                        </DialogTitle>
                                        <div className="mt-2 flex gap-4">
                                            <div className="flex items-center gap-1.5 text-xs font-bold tracking-widest text-muted-foreground uppercase">
                                                <IdCard className="h-3.5 w-3.5" />
                                                {conductorSeleccionado.rut ||
                                                    'SIN RUT'}
                                            </div>
                                            <div className="flex items-center gap-1.5 border-l pl-4 text-xs font-bold tracking-widest text-muted-foreground uppercase">
                                                <FileText className="h-3.5 w-3.5" />
                                                L:{' '}
                                                {conductorSeleccionado.licencia ||
                                                    '---'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </DialogHeader>

                            <div className="max-h-[calc(90vh-160px)] overflow-y-auto">
                                <div className="grid grid-cols-1 gap-8 p-8 md:grid-cols-2">
                                    <div className="space-y-6">
                                        <div className="space-y-4">
                                            <h3 className="text-xs font-black tracking-[0.2em] text-primary/70 uppercase">
                                                Contacto Directo
                                            </h3>
                                            <div className="space-y-3">
                                                <div className="group flex items-center gap-4 rounded-2xl border border-border/50 bg-muted/30 p-4 transition-colors hover:bg-muted/50">
                                                    <div className="rounded-xl bg-white p-2.5 text-primary shadow-sm transition-transform group-hover:scale-110">
                                                        <Phone className="h-4 w-4" />
                                                    </div>
                                                    <div className="flex flex-1 items-center justify-between">
                                                        <div>
                                                            <p className="mb-1 text-[10px] leading-none font-black text-muted-foreground uppercase">
                                                                Celular / Red
                                                                Fija
                                                            </p>
                                                            <p className="text-sm font-bold">
                                                                {conductorSeleccionado.telefono ||
                                                                    'No disponible'}
                                                            </p>
                                                        </div>
                                                        {conductorSeleccionado.telefono && (
                                                            <WhatsAppButton
                                                                phone={
                                                                    conductorSeleccionado.telefono
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="group flex items-center gap-4 rounded-2xl border border-border/50 bg-muted/30 p-4 transition-colors hover:bg-muted/50">
                                                    <div className="rounded-xl bg-white p-2.5 text-primary shadow-sm transition-transform group-hover:scale-110">
                                                        <Mail className="h-4 w-4" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="mb-1 text-[10px] leading-none font-black text-muted-foreground uppercase">
                                                            Email Oficial
                                                        </p>
                                                        <p className="truncate text-sm font-bold">
                                                            {conductorSeleccionado.email ||
                                                                'Sin correo registrado'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="space-y-6">
                                        <div className="space-y-4">
                                            <h3 className="text-xs font-black tracking-[0.2em] text-primary/70 uppercase">
                                                Estado de Alertas
                                            </h3>
                                            <div className="flex flex-col items-center justify-center space-y-3 rounded-2xl border-2 border-primary/5 bg-primary/[0.03] p-6 text-center">
                                                {conductorSeleccionado.estado ===
                                                'licencia_vencida' ? (
                                                    <>
                                                        <div className="flex h-12 w-12 animate-pulse items-center justify-center rounded-full bg-destructive/10 text-destructive">
                                                            <ShieldAlert className="h-6 w-6" />
                                                        </div>
                                                        <div>
                                                            <p className="text-sm font-black text-destructive uppercase">
                                                                Licencia no
                                                                Vigente
                                                            </p>
                                                            <p className="mt-1 text-xs font-medium text-muted-foreground">
                                                                Requiere
                                                                actualización
                                                                inmediata antes
                                                                de asignar
                                                                rutas.
                                                            </p>
                                                        </div>
                                                    </>
                                                ) : (
                                                    <>
                                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-green-500/10 text-green-600">
                                                            <FileText className="h-6 w-6" />
                                                        </div>
                                                        <div>
                                                            <p className="text-sm font-black tracking-tight text-green-600 uppercase">
                                                                Habilitado para
                                                                Conducir
                                                            </p>
                                                            <p className="mt-1 text-xs font-medium text-muted-foreground">
                                                                Documentación al
                                                                día y estado
                                                                operativo
                                                                disponible.
                                                            </p>
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>



                                    <div className="border-t pt-6 md:col-span-2">
                                        <h3 className="mb-4 text-xs font-black tracking-[0.2em] text-primary/70 uppercase">
                                            Desempeño y Ventas Asociadas
                                        </h3>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div className="rounded-2xl border border-blue-100 bg-blue-50/50 p-5">
                                                <p className="mb-3 text-xs font-black tracking-widest text-blue-700 uppercase">
                                                    Rendimiento de Hoy
                                                </p>
                                                <div className="flex flex-col gap-2 text-sm font-semibold text-blue-900">
                                                    <div className="flex items-center justify-between rounded bg-white/60 p-2 shadow-sm">
                                                        <span>
                                                            Productos o
                                                            Cilindros:
                                                        </span>
                                                        <span className="font-black text-blue-700">
                                                            {conductorSeleccionado
                                                                .estadisticas_ventas
                                                                ?.diario
                                                                ?.productos ||
                                                                0}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center justify-between rounded bg-white/60 p-2 shadow-sm">
                                                        <span>
                                                            Total en Kilos:
                                                        </span>
                                                        <span className="font-black text-blue-700">
                                                            {(
                                                                conductorSeleccionado
                                                                    .estadisticas_ventas
                                                                    ?.diario
                                                                    ?.kilos || 0
                                                            ).toLocaleString(
                                                                currency.locale,
                                                            )}{' '}
                                                            Kg
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center justify-between rounded bg-white/60 p-2 shadow-sm">
                                                        <span>
                                                            Total en Litros:
                                                        </span>
                                                        <span className="font-black text-blue-700">
                                                            {(
                                                                conductorSeleccionado
                                                                    .estadisticas_ventas
                                                                    ?.diario
                                                                    ?.litros ||
                                                                0
                                                            ).toLocaleString(
                                                                currency.locale,
                                                            )}{' '}
                                                            L
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="rounded-2xl border border-green-100 bg-green-50/50 p-5">
                                                <p className="mb-3 text-xs font-black tracking-widest text-green-700 uppercase">
                                                    Rendimiento del Mes
                                                </p>
                                                <div className="flex flex-col gap-2 text-sm font-semibold text-green-900">
                                                    <div className="flex items-center justify-between rounded bg-white/60 p-2 shadow-sm">
                                                        <span>
                                                            Productos o
                                                            Cilindros:
                                                        </span>
                                                        <span className="font-black text-green-700">
                                                            {conductorSeleccionado
                                                                .estadisticas_ventas
                                                                ?.mensual
                                                                ?.productos ||
                                                                0}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center justify-between rounded bg-white/60 p-2 shadow-sm">
                                                        <span>
                                                            Total en Kilos:
                                                        </span>
                                                        <span className="font-black text-green-700">
                                                            {(
                                                                conductorSeleccionado
                                                                    .estadisticas_ventas
                                                                    ?.mensual
                                                                    ?.kilos || 0
                                                            ).toLocaleString(
                                                                currency.locale,
                                                            )}{' '}
                                                            Kg
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center justify-between rounded bg-white/60 p-2 shadow-sm">
                                                        <span>
                                                            Total en Litros:
                                                        </span>
                                                        <span className="font-black text-green-700">
                                                            {(
                                                                conductorSeleccionado
                                                                    .estadisticas_ventas
                                                                    ?.mensual
                                                                    ?.litros ||
                                                                0
                                                            ).toLocaleString(
                                                                currency.locale,
                                                            )}{' '}
                                                            L
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="border-t pt-6 md:col-span-2">
                                        <h3 className="mb-4 text-xs font-black tracking-[0.2em] text-primary/70 uppercase">
                                            Notas y Observaciones
                                        </h3>
                                        <div className="rounded-2xl border-2 border-dashed border-muted-foreground/20 bg-muted/20 p-5 text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground italic">
                                            {conductorSeleccionado.notas ||
                                                'No existen anotaciones especiales en el perfil de este conductor operativo.'}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter className="border-t bg-muted/5 p-6">
                                <Button
                                    onClick={() => setIsVerOpen(false)}
                                    className="w-full rounded-full bg-foreground px-10 font-black text-background shadow-lg shadow-foreground/10 transition-all hover:bg-foreground/90 active:scale-95 sm:w-auto"
                                >
                                    Cerrar Perfil
                                </Button>
                            </DialogFooter>
                        </>
                    )}
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
        </>
    );
}
