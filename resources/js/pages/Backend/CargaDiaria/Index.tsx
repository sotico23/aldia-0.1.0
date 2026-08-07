import { Head, useForm } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Eye,
    Download,
    Upload,
    FileSpreadsheet,
    FileJson,
    RefreshCw,
    DollarSign,
    ArrowLeftRight,
    Truck,
    User,
    Package,
    PackageX,
    PackageMinus,
    Receipt,
} from 'lucide-react';
import { useState, useMemo, useRef } from 'react';
import '@/components/form-input';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface CargaDiaria {
    id: number;
    vehiculo_id: number;
    conductor_id: number;
    fecha: string;
    estado: string;
    ventas_totales: number;
    devoluciones_totales: number;
    notas: string | null;
    vehiculo?: { placa: string; marca: string; modelo: string };
    conductor?: { nombre: string };
    productos?: {
        id: number;
        producto_id: number;
        cantidad_bordo: number;
        cantidad_vendida: number;
        cantidad_devuelta: number;
        producto?: { nombre: string };
    }[];
}

interface Vehiculo {
    id: number;
    marca: string;
    modelo: string;
    placa: string;
}

interface Conductor {
    id: number;
    nombre: string;
}

interface Producto {
    id: number;
    nombre: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cargas Diarias', href: '/cargas-diarias' },
];

export default function Index({
    cargas,
    vehiculos,
    conductores,
    productos,
}: {
    cargas: { data: CargaDiaria[]; links: any[]; meta?: any; total: number };
    vehiculos: Vehiculo[];
    conductores: Conductor[];
    productos: Producto[];
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('flota.cargas.create');
    const canEdit = hasPermission('flota.cargas.edit');
    const canDelete = hasPermission('flota.cargas.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [isVerOpen, setIsVerOpen] = useState(false);
    const [isRenovarOpen, setIsRenovarOpen] = useState(false);
    const [isRecargarOpen, setIsRecargarOpen] = useState(false);
    const [editando, setEditando] = useState<CargaDiaria | null>(null);
    const [cargaSeleccionada, setCargaSeleccionada] =
        useState<CargaDiaria | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const [renovarData, setRenovarData] = useState<{
        ventas_totales: number;
        devoluciones_totales: number;
        productos: {
            producto_id: number;
            producto_nombre: string;
            cantidad_bordo: number;
            cantidad_vendida: number;
            cantidad_devuelta: number;
            renovar: boolean;
        }[];
    }>({ ventas_totales: 0, devoluciones_totales: 0, productos: [] });

    const [recargarData, setRecargarData] = useState<{
        notas: string;
        ventas_totales: number;
        devoluciones_totales: number;
        crear_nueva_carga: boolean;
        productos: {
            producto_id: number;
            producto_nombre: string;
            cantidad_bordo: number;
            cantidad_llena: number;
            cantidad_vacia: number;
            cantidad_faltante: number;
            cantidad_defectuosa: number;
            cantidad_vendida: number;
            cantidad_devuelta: number;
        }[];
    }>({
        notas: '',
        ventas_totales: 0,
        devoluciones_totales: 0,
        crear_nueva_carga: true,
        productos: [],
    });

    const [productosCarga, setProductosCarga] = useState<
        { producto_id: number; cantidad: number }[]
    >([]);

    const {
        data,
        setData,
        delete: destroy,
        reset,
    } = useForm({
        vehiculo_id: '',
        conductor_id: '',
        fecha: getLocalDateString(),
        estado: 'pendiente',
        notas: '',
        productos: [] as { producto_id: number; cantidad: number }[],
    });

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
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

        router.post('/cargas-diarias/importar', formData, {
            forceFormData: true,
            onSuccess: () => {
                e.target.value = '';
            },
        });
    };

    const cargasFiltradas = useMemo(() => {
        return (cargas.data || []).filter((c) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                const vehiculoMatch =
                    c.vehiculo?.placa.toLowerCase().includes(busca) || false;
                const conductorMatch =
                    c.conductor?.nombre.toLowerCase().includes(busca) || false;
                if (!vehiculoMatch && !conductorMatch) {
                    return false;
                }
            }
            if (filtros.estado && c.estado !== filtros.estado) return false;
            return true;
        });
    }, [cargas, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const handleAddProducto = () => {
        setProductosCarga([...productosCarga, { producto_id: 0, cantidad: 1 }]);
    };

    const handleRemoveProducto = (index: number) => {
        const newList = [...productosCarga];
        newList.splice(index, 1);
        setProductosCarga(newList);
    };

    const handleProductoChange = (index: number, field: string, value: any) => {
        const newList = [...productosCarga];
        newList[index] = { ...newList[index], [field]: value };
        setProductosCarga(newList);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const cleanData = {
            ...data,
            productos: productosCarga.filter(
                (p) => p.producto_id > 0 && p.cantidad > 0,
            ),
        };

        if (editando) {
            import('@inertiajs/react').then(({ router }) => {
                router.put(`/cargas-diarias/${editando.id}`, cleanData, {
                    onSuccess: () => {
                        setIsOpen(false);
                        setEditando(null);
                        reset();
                    },
                });
            });
        } else {
            import('@inertiajs/react').then(({ router }) => {
                router.post('/cargas-diarias', cleanData, {
                    onSuccess: () => {
                        setIsOpen(false);
                        reset();
                        setProductosCarga([]);
                    },
                });
            });
        }
    };

    const handleEdit = (c: CargaDiaria) => {
        setEditando(c);
        setData({
            vehiculo_id: c.vehiculo_id.toString(),
            conductor_id: c.conductor_id.toString(),
            fecha: c.fecha.split('T')[0],
            estado: c.estado,
            notas: c.notas || '',
            productos: [],
        });
        // Cargar productos existentes para edición
        const productosExistentes =
            c.productos?.map((p) => ({
                producto_id: p.producto_id,
                cantidad: p.cantidad_bordo || 0,
            })) || [];
        setProductosCarga(productosExistentes);
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setEditando(null);
        setProductosCarga([]);
        setIsOpen(true);
    };

    const totalAsignado = productosCarga.reduce(
        (sum, p) => sum + (p.cantidad || 0),
        0,
    );

    const handleRenovar = (c: CargaDiaria) => {
        const productosRenovar =
            c.productos?.map((p) => ({
                producto_id: p.producto_id,
                producto_nombre: p.producto?.nombre || '',
                cantidad_bordo: p.cantidad_bordo || 0,
                cantidad_vendida: p.cantidad_vendida || 0,
                cantidad_devuelta: p.cantidad_devuelta || 0,
                renovar: true,
            })) || [];

        setRenovarData({
            ventas_totales: c.ventas_totales || 0,
            devoluciones_totales: c.devoluciones_totales || 0,
            productos: productosRenovar,
        });
        setCargaSeleccionada(c);
        setIsRenovarOpen(true);
    };

    const handleConfirmarRenovacion = () => {
        if (!cargaSeleccionada) return;

        const productos = renovarData.productos.filter(
            (p) => p.cantidad_bordo > 0,
        );

        router.post(
            `/cargas-diarias/${cargaSeleccionada.id}/renovar`,
            {
                ventas_totales: renovarData.ventas_totales,
                devoluciones_totales: renovarData.devoluciones_totales,
                productos: productos.map((p) => ({
                    producto_id: p.producto_id,
                    cantidad_bordo: p.cantidad_bordo,
                    cantidad_vendida: p.cantidad_vendida,
                    cantidad_devuelta: p.cantidad_devuelta,
                    renovar: p.renovar,
                })),
            },
            {
                onSuccess: () => {
                    setIsRenovarOpen(false);
                    setIsVerOpen(false);
                },
            },
        );
    };

    const handleVer = (c: CargaDiaria) => {
        setCargaSeleccionada(c);
        setIsVerOpen(true);
    };

    const handleRecargar = (c: CargaDiaria) => {
        const productosRecargar =
            c.productos?.map((p) => ({
                producto_id: p.producto_id,
                producto_nombre: p.producto?.nombre || '',
                cantidad_bordo: p.cantidad_bordo || 0,
                cantidad_llena: 0,
                cantidad_vacia: 0,
                cantidad_faltante: 0,
                cantidad_defectuosa: 0,
                cantidad_vendida: 0,
                cantidad_devuelta: 0,
            })) || [];

        setRecargarData({
            notas: '',
            ventas_totales: 0,
            devoluciones_totales: 0,
            crear_nueva_carga: true,
            productos: productosRecargar,
        });
        setCargaSeleccionada(c);
        setIsRecargarOpen(true);
    };

    const handleConfirmarRecarga = () => {
        if (!cargaSeleccionada) return;

        router.post(
            `/cargas-diarias/${cargaSeleccionada.id}/recargar`,
            {
                notas: recargarData.notas,
                ventas_totales: recargarData.ventas_totales,
                devoluciones_totales: recargarData.devoluciones_totales,
                crear_nueva_carga: recargarData.crear_nueva_carga,
                productos: recargarData.productos.map((p) => ({
                    producto_id: p.producto_id,
                    cantidad_bordo: p.cantidad_bordo,
                    cantidad_llena: p.cantidad_llena,
                    cantidad_vacia: p.cantidad_vacia,
                    cantidad_faltante: p.cantidad_faltante,
                    cantidad_defectuosa: p.cantidad_defectuosa,
                    cantidad_vendida: p.cantidad_vendida,
                    cantidad_devuelta: p.cantidad_devuelta,
                })),
            },
            {
                onSuccess: () => {
                    setIsRecargarOpen(false);
                    setIsVerOpen(false);
                },
            },
        );
    };

    const totalLlenos = recargarData.productos.reduce(
        (sum, p) => sum + (p.cantidad_llena || 0),
        0,
    );

    const totalVacios = recargarData.productos.reduce(
        (sum, p) => sum + (p.cantidad_vacia || 0),
        0,
    );

    const totalFaltantes = recargarData.productos.reduce(
        (sum, p) => sum + (p.cantidad_faltante || 0),
        0,
    );

    const totalDefectuosos = recargarData.productos.reduce(
        (sum, p) => sum + (p.cantidad_defectuosa || 0),
        0,
    );

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar esta carga diaria?'))
            destroy(`/cargas-diarias/${id}`);
    };

    const getEstadoBadge = (e: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            en_ruta: 'bg-blue-500',
            cerrado: 'bg-green-500',
        };
        return (
            <Badge className={colores[e] || 'bg-gray-500'}>
                {e.toUpperCase().replace('_', ' ')}
            </Badge>
        );
    };

    return (
        <>
            <Head title="Cargas Diarias (En Ruta)" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 pb-24">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Cargas Diarias
                            </h1>
                            <p className="text-muted-foreground">
                                Asignación de inventario a vehículos
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canCreate && (
                                <Button onClick={handleNew} size="sm">
                                    <Plus className="mr-2 h-4 w-4" /> Nueva
                                    Asignación
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
                                            router.get(
                                                '/cargas-diarias/exportar?format=json',
                                            )
                                        }
                                    >
                                        <FileJson className="mr-2 h-4 w-4" />
                                        Exportar JSON
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() =>
                                            router.get(
                                                '/cargas-diarias/exportar?format=excel',
                                            )
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
                                    <CardTitle>Rutas y Vehículos</CardTitle>
                                    <CardDescription>
                                        {cargasFiltradas.length} cargas registradas
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
                                            placeholder="Buscar por placa o conductor..."
                                            value={filtros.busqueda}
                                            onChange={(e) =>
                                                setFiltros({
                                                    ...filtros,
                                                    busqueda: e.target.value,
                                                })
                                            }
                                            className="h-9 pl-8 pr-8"
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
                                        <SelectValue placeholder="Estado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos
                                        </SelectItem>
                                        <SelectItem value="pendiente">
                                            PENDIENTE
                                        </SelectItem>
                                        <SelectItem value="en_ruta">
                                            EN RUTA
                                        </SelectItem>
                                        <SelectItem value="cerrado">
                                            CERRADO
                                        </SelectItem>
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
                                                Fecha
                                            </th>
                                            <th className="py-2 text-left font-medium">
                                                Vehículo
                                            </th>
                                            <th className="py-2 text-left font-medium">
                                                Conductor
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
                                        {cargasFiltradas.map((c) => (
                                            <tr
                                                key={c.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-2 font-bold">
                                                    {c.fecha
                                                        ? c.fecha.split('T')[0]
                                                        : ''}
                                                </td>
                                                <td className="py-2">
                                                    <div className="font-bold uppercase">
                                                        {c.vehiculo?.placa ||
                                                            '-'}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {c.vehiculo?.marca}
                                                    </div>
                                                </td>
                                                <td className="py-2">
                                                    {c.conductor?.nombre || '-'}
                                                </td>
                                                <td className="py-2 text-center">
                                                    {getEstadoBadge(c.estado)}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        {c.estado === 'en_ruta' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700"
                                                                onClick={() =>
                                                                    handleRecargar(c)
                                                                }
                                                                title="Recargar / Registrar Llenos/Vacíos"
                                                            >
                                                                <RefreshCw className="h-4 w-4" />
                                                            </Button>
                                                        )}
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
                                                                    handleEdit(c)
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
                                        ))}
                                        {cargasFiltradas.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron cargas.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                                {cargas.links && (
                                    <Pagination
                                        links={cargas.links}
                                        meta={cargas.meta}
                                    />
                                )}
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {cargasFiltradas.map((c) => (
                                    <Card key={c.id} className="overflow-hidden">
                                        <CardContent className="space-y-3 p-4">
                                            <div className="flex items-center justify-between">
                                                <Badge variant="outline" className="text-xs">
                                                    {c.fecha ? c.fecha.split('T')[0] : ''}
                                                </Badge>
                                                {getEstadoBadge(c.estado)}
                                            </div>
                                            <div>
                                                <p className="text-lg font-bold uppercase">{c.vehiculo?.placa || '-'}</p>
                                                <p className="text-xs text-muted-foreground">{c.vehiculo?.marca} {c.vehiculo?.modelo}</p>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm">
                                                <User className="h-4 w-4 text-muted-foreground" />
                                                <span>{c.conductor?.nombre || '-'}</span>
                                            </div>
                                            <div className="flex justify-end gap-1 border-t pt-2">
                                                {c.estado === 'en_ruta' && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700" onClick={() => handleRecargar(c)} title="Recargar">
                                                        <RefreshCw className="h-4 w-4" />
                                                    </Button>
                                                )}
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
                                {cargasFiltradas.length === 0 && (
                                    <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                        <Truck className="mb-3 h-12 w-12 opacity-20" />
                                        <p>No se encontraron cargas.</p>
                                    </div>
                                )}
                            </div>
                        )}
                        </CardContent>
                    </Card>
                </div>

            </AppLayout>

            {/* Modal Crear/Editar */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-w-2xl">
                    <DialogHeader className="bg-gradient-to-r from-blue-600 to-indigo-700 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            {editando ? 'Modificar' : 'Nueva'} Carga Diaria /
                            Ruta
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="px-4 py-4 md:px-6">
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Fecha *
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.fecha}
                                        onChange={(e) =>
                                            setData('fecha', e.target.value)
                                        }
                                        className="h-9"
                                        required
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Estado
                                    </Label>
                                    <Select
                                        value={data.estado}
                                        onValueChange={(val) =>
                                            setData('estado', val)
                                        }
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Estado" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="pendiente">
                                                Pendiente
                                            </SelectItem>
                                            <SelectItem value="en_ruta">
                                                En Ruta
                                            </SelectItem>
                                            <SelectItem value="cerrado">
                                                Cerrado
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Vehículo *
                                    </Label>
                                    <Select
                                        value={data.vehiculo_id}
                                        onValueChange={(val) =>
                                            setData('vehiculo_id', val)
                                        }
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Seleccionar..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {vehiculos.map((v) => (
                                                <SelectItem
                                                    key={v.id}
                                                    value={v.id.toString()}
                                                >
                                                    {v.placa} - {v.marca}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Conductor *
                                    </Label>
                                    <Select
                                        value={data.conductor_id}
                                        onValueChange={(val) =>
                                            setData('conductor_id', val)
                                        }
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Seleccionar..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {conductores.map((c) => (
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
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold uppercase">
                                    Notas
                                </Label>
                                <Input
                                    value={data.notas}
                                    onChange={(e) =>
                                        setData('notas', e.target.value)
                                    }
                                    placeholder="Observaciones adicionales..."
                                    className="h-9"
                                />
                            </div>

                            <div className="space-y-2 border-t pt-4">
                                <div>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <Label className="text-xs font-bold uppercase">
                                                Carga de Productos al Vehículo
                                            </Label>
                                            <p className="text-[10px] text-muted-foreground">
                                                Productos que irán a bordo al
                                                salir el vehículo
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleAddProducto}
                                            className="h-7 text-xs"
                                        >
                                            <Plus className="mr-1 h-3 w-3" />
                                            Agregar
                                        </Button>
                                    </div>
                                </div>
                                {productosCarga.length === 0 ? (
                                    <div className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                                        Sin productos asignados. Haga clic en
                                        "Agregar" para seleccionar productos que
                                        irán a bordo.
                                    </div>
                                ) : (
                                    <div className="max-h-48 space-y-2 overflow-y-auto">
                                        {productosCarga.map((pc, idx) => (
                                            <div
                                                key={idx}
                                                className="flex flex-col gap-2 rounded-lg border p-2 sm:flex-row sm:items-center sm:gap-2"
                                            >
                                                <Select
                                                    value={
                                                        pc.producto_id > 0
                                                            ? pc.producto_id.toString()
                                                            : ''
                                                    }
                                                    onValueChange={(val) =>
                                                        handleProductoChange(
                                                            idx,
                                                            'producto_id',
                                                            parseInt(val),
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="h-8 text-xs sm:flex-1">
                                                        <SelectValue placeholder="Seleccionar producto" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {productos.map((p) => (
                                                            <SelectItem
                                                                key={p.id}
                                                                value={p.id.toString()}
                                                            >
                                                                {p.nombre}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <div className="flex items-center gap-1">
                                                    <div className="space-y-0.5">
                                                        <Input
                                                            type="number"
                                                            min="1"
                                                            className="h-8 w-20 text-xs"
                                                            placeholder="Cant."
                                                            value={pc.cantidad}
                                                            onChange={(e) =>
                                                                handleProductoChange(
                                                                    idx,
                                                                    'cantidad',
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0,
                                                                )
                                                            }
                                                        />
                                                        <p className="text-[9px] text-muted-foreground text-center">
                                                            a bordo
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 shrink-0 text-red-500 hover:text-red-600"
                                                        onClick={() =>
                                                            handleRemoveProducto(
                                                                idx,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {productosCarga.length > 0 && (
                                    <div className="flex items-center justify-between rounded-lg bg-primary/5 px-4 py-2 text-sm">
                                        <span className="font-bold text-muted-foreground uppercase">
                                            Total Unidades a Bordo
                                        </span>
                                        <span className="text-lg font-black text-primary">
                                            {totalAsignado}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                        <div className="mt-4 flex flex-col gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full sm:px-4"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                className="rounded-full sm:px-6"
                            >
                                Guardar
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Ver Detalles */}
            <Dialog open={isVerOpen} onOpenChange={setIsVerOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none bg-background p-0 shadow-2xl md:max-w-2xl">
                    <DialogHeader className="flex flex-row items-center justify-between bg-gradient-to-r from-blue-500/10 to-transparent p-6 pb-2">
                        <DialogTitle className="text-2xl font-black tracking-tight text-blue-700">
                            Detalles de la Carga Diaria
                        </DialogTitle>
                        <div className="flex gap-2">
                            {cargaSeleccionada?.estado === 'en_ruta' && (
                                <Button
                                    onClick={() =>
                                        cargaSeleccionada &&
                                        handleRecargar(cargaSeleccionada)
                                    }
                                    className="gap-2 rounded-full bg-emerald-600 font-bold hover:bg-emerald-700"
                                    size="sm"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    Recargar
                                </Button>
                            )}
                            {cargaSeleccionada?.estado === 'en_ruta' && (
                                <Button
                                    onClick={() =>
                                        cargaSeleccionada &&
                                        handleRenovar(cargaSeleccionada)
                                    }
                                    variant="outline"
                                    className="gap-2 rounded-full font-bold"
                                    size="sm"
                                >
                                    <ArrowLeftRight className="h-4 w-4" />
                                    Renovar
                                </Button>
                            )}
                        </div>
                    </DialogHeader>
                    {cargaSeleccionada && (
                        <div className="max-h-[calc(90vh-140px)] overflow-y-auto p-6 pt-0">
                            <div className="grid gap-6">
                                {/* Info Base */}
                                <div className="grid grid-cols-2 gap-4 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Fecha
                                        </p>
                                        <p className="font-bold">
                                            {cargaSeleccionada.fecha
                                                ? cargaSeleccionada.fecha.split(
                                                      'T',
                                                  )[0]
                                                : ''}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Estado
                                        </p>
                                        <div className="mt-1">
                                            {getEstadoBadge(
                                                cargaSeleccionada.estado,
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Vehículo
                                        </p>
                                        <p className="font-bold uppercase">
                                            {cargaSeleccionada.vehiculo?.placa}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {cargaSeleccionada.vehiculo?.marca}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Conductor asignado
                                        </p>
                                        <p className="font-medium">
                                            {
                                                cargaSeleccionada.conductor
                                                    ?.nombre
                                            }
                                        </p>
                                    </div>
                                </div>

                                {/* Productos */}
                                <div>
                                    <h4 className="mb-3 text-sm font-bold text-foreground">
                                        Inventario Cargado
                                    </h4>
                                    {cargaSeleccionada.productos &&
                                    cargaSeleccionada.productos.length > 0 ? (
                                        <div className="space-y-3 rounded-xl border border-border/50 bg-muted/20 p-4">
                                            {cargaSeleccionada.productos.map(
                                                (p) => {
                                                    const resto =
                                                        p.cantidad_bordo -
                                                        (p.cantidad_vendida ||
                                                            0) -
                                                        (p.cantidad_devuelta ||
                                                            0);
                                                    return (
                                                        <div
                                                            key={p.id}
                                                            className="rounded border bg-white p-3"
                                                        >
                                                            <div className="flex items-center justify-between">
                                                                <p className="text-sm font-bold">
                                                                    {
                                                                        p
                                                                            .producto
                                                                            ?.nombre
                                                                    }
                                                                </p>
                                                                <div className="text-right">
                                                                    <p className="text-[10px] text-muted-foreground">
                                                                        A bordo
                                                                    </p>
                                                                    <p className="text-lg font-black">
                                                                        {
                                                                            p.cantidad_bordo
                                                                        }
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            {(p.cantidad_vendida !==
                                                                    0 ||
                                                                p.cantidad_devuelta !==
                                                                    0) && (
                                                                <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2 border-t pt-2 text-center text-xs">
                                                                    <div>
                                                                        <p className="text-muted-foreground">
                                                                            Vendido
                                                                        </p>
                                                                        <p className="font-bold text-emerald-600">
                                                                            {p.cantidad_vendida ||
                                                                                0}
                                                                        </p>
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-muted-foreground">
                                                                            Devuelto
                                                                        </p>
                                                                        <p className="font-bold text-amber-600">
                                                                            {p.cantidad_devuelta ||
                                                                                0}
                                                                        </p>
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-muted-foreground">
                                                                            {resto >
                                                                            0
                                                                                ? 'Pendiente'
                                                                                : resto <
                                                                                     0
                                                                                  ? 'Sobrante'
                                                                                  : 'Cuadra'}
                                                                        </p>
                                                                        <p
                                                                            className={`font-bold ${
                                                                                resto >
                                                                                0
                                                                                    ? 'text-blue-600'
                                                                                    : resto <
                                                                                          0
                                                                                      ? 'text-red-500'
                                                                                      : 'text-green-600'
                                                                            }`}
                                                                        >
                                                                            {resto ===
                                                                            0
                                                                                ? '✓'
                                                                                : resto > 0
                                                                                  ? `-${resto}`
                                                                                  : `+${Math.abs(resto)}`}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    );
                                                },
                                            )}
                                        </div>
                                    ) : (
                                        <div className="rounded-xl border border-dashed border-border/50 p-8 text-center text-muted-foreground">
                                            No hay productos registrados en esta
                                            carga.
                                        </div>
                                    )}
                                </div>

                                {cargaSeleccionada.notas && (
                                    <div>
                                        <h4 className="mb-2 text-sm font-bold text-foreground">
                                            Notas Adicionales
                                        </h4>
                                        <p className="rounded-lg bg-muted/30 p-3 text-sm">
                                            {cargaSeleccionada.notas}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Modal Renovar Carga */}
            <Dialog open={isRenovarOpen} onOpenChange={setIsRenovarOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none bg-background p-0 shadow-2xl md:max-w-3xl">
                    <DialogHeader className="bg-gradient-to-r from-emerald-500/10 to-transparent p-6 pb-2">
                        <DialogTitle className="flex items-center gap-2 text-2xl font-black tracking-tight text-emerald-700">
                            <RefreshCw className="h-6 w-6" />
                            Renovar Carga
                        </DialogTitle>
                        <p className="text-sm text-muted-foreground">
                            Confirma las ventas y devoluciones para cerrar la
                            carga actual y crear una nueva.
                        </p>
                    </DialogHeader>

                    {cargaSeleccionada && (
                        <div className="space-y-6 p-6">
                            <div className="grid grid-cols-2 gap-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                                <div>
                                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                        Vehículo
                                    </p>
                                    <p className="font-bold uppercase">
                                        {cargaSeleccionada.vehiculo?.placa}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                        Conductor
                                    </p>
                                    <p className="font-medium">
                                        {
                                            cargaSeleccionada.conductor
                                                ?.nombre
                                        }
                                    </p>
                                </div>
                            </div>

                            {/* Productos - registrar ventas, devoluciones y renovación */}
                            <div>
                                <h4 className="mb-3 text-sm font-bold text-foreground">
                                    Liquidación de Productos
                                </h4>
                                <p className="mb-3 text-[11px] text-muted-foreground">
                                    Registre lo vendido y lo devuelto
                                    físicamente por el conductor. La diferencia
                                    se muestra como no justificada.
                                </p>
                                <div className="space-y-4">
                                    {renovarData.productos.map((prod, idx) => {
                                        const resto =
                                            prod.cantidad_bordo -
                                            prod.cantidad_vendida -
                                            prod.cantidad_devuelta;
                                        return (
                                            <div
                                                key={prod.producto_id}
                                                className="rounded-xl border border-border/50 bg-muted/20 p-4"
                                            >
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-bold">
                                                        {prod.producto_nombre}
                                                    </p>
                                                    <span className="text-xs font-bold text-muted-foreground">
                                                        Cargó al salir:{' '}
                                                        {prod.cantidad_bordo}
                                                    </span>
                                                </div>
                                                <div className="grid grid-cols-2 gap-3">
                                                    <div className="space-y-1">
                                                        <Label className="text-[10px] font-black uppercase text-muted-foreground">
                                                            Vendido en ruta
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={
                                                                prod.cantidad_vendida
                                                            }
                                                            onChange={(e) => {
                                                                const newProductos =
                                                                    [
                                                                        ...renovarData.productos,
                                                                    ];
                                                                const vendido =
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0;
                                                                newProductos[
                                                                    idx
                                                                ].cantidad_vendida =
                                                                    Math.max(
                                                                        0,
                                                                        vendido,
                                                                    );
                                                                setRenovarData({
                                                                    ...renovarData,
                                                                    productos:
                                                                        newProductos,
                                                                });
                                                            }}
                                                            className="h-9 text-center font-bold"
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label className="text-[10px] font-black uppercase text-muted-foreground">
                                                            Devuelto físico
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={
                                                                prod.cantidad_devuelta
                                                            }
                                                            onChange={(e) => {
                                                                const newProductos =
                                                                    [
                                                                        ...renovarData.productos,
                                                                    ];
                                                                const devuelto =
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0;
                                                                newProductos[
                                                                    idx
                                                                ].cantidad_devuelta =
                                                                    Math.max(
                                                                        0,
                                                                        devuelto,
                                                                    );
                                                                setRenovarData({
                                                                    ...renovarData,
                                                                    productos:
                                                                        newProductos,
                                                                });
                                                            }}
                                                            className="h-9 text-center font-bold"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="mt-3 flex items-center justify-between border-t pt-3">
                                                    <div className="flex items-center gap-3">
                                                        <label className="flex items-center gap-2">
                                                            <input
                                                                type="checkbox"
                                                                checked={
                                                                    prod.renovar
                                                                }
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    const newProductos =
                                                                        [
                                                                            ...renovarData.productos,
                                                                        ];
                                                                    newProductos[
                                                                        idx
                                                                    ].renovar =
                                                                        e.target
                                                                            .checked;
                                                                    setRenovarData(
                                                                        {
                                                                            ...renovarData,
                                                                            productos:
                                                                                newProductos,
                                                                        },
                                                                    );
                                                                }}
                                                                className="h-4 w-4 rounded border-gray-300 text-emerald-600"
                                                            />
                                                            <span className="text-xs font-bold text-muted-foreground">
                                                                Incluir en nueva
                                                                carga
                                                            </span>
                                                        </label>
                                                        {resto > 0 && (
                                                            <span className="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                                                                Renueva: {resto}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-2 text-xs">
                                                        {resto > 0 ? (
                                                            <span className="font-bold text-emerald-600">
                                                                Diferencia:{' '}
                                                                {resto}
                                                            </span>
                                                        ) : resto < 0 ? (
                                                            <span className="font-bold text-amber-600">
                                                                Sobrante:{' '}
                                                                {Math.abs(
                                                                    resto,
                                                                )}
                                                            </span>
                                                        ) : (
                                                            <span className="font-bold text-green-600">
                                                                Cuadra perfecto
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Totales y dinero */}
                            <div className="grid grid-cols-2 gap-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                                <div className="space-y-1">
                                    <Label className="text-xs font-bold uppercase text-muted-foreground">
                                        Total Ventas ($)
                                    </Label>
                                    <div className="relative">
                                        <DollarSign className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-500" />
                                        <Input
                                            type="number"
                                            min="0"
                                            value={renovarData.ventas_totales}
                                            onChange={(e) =>
                                                setRenovarData({
                                                    ...renovarData,
                                                    ventas_totales:
                                                        parseFloat(
                                                            e.target.value,
                                                        ) || 0,
                                                })
                                            }
                                            className="h-10 rounded-xl bg-white pl-10 text-center font-black shadow-sm"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs font-bold uppercase text-muted-foreground">
                                        Total Devoluciones ($)
                                    </Label>
                                    <div className="relative">
                                        <ArrowLeftRight className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-amber-500" />
                                        <Input
                                            type="number"
                                            min="0"
                                            value={
                                                renovarData.devoluciones_totales
                                            }
                                            onChange={(e) =>
                                                setRenovarData({
                                                    ...renovarData,
                                                    devoluciones_totales:
                                                        parseFloat(
                                                            e.target.value,
                                                        ) || 0,
                                                })
                                            }
                                            className="h-10 rounded-xl bg-white pl-10 text-center font-black shadow-sm"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 border-t pt-4">
                                <Button
                                    variant="outline"
                                    onClick={() => setIsRenovarOpen(false)}
                                    className="rounded-full"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    onClick={handleConfirmarRenovacion}
                                    className="gap-2 rounded-full bg-emerald-600 font-bold hover:bg-emerald-700"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    Confirmar y Renovar
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Modal Recargar / Registrar Llenos/Vacíos/Faltantes */}
            <Dialog open={isRecargarOpen} onOpenChange={setIsRecargarOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none bg-background p-0 shadow-2xl md:max-w-4xl">
                    <DialogHeader className="bg-gradient-to-r from-emerald-500/10 to-blue-500/10 p-6 pb-2">
                        <DialogTitle className="flex items-center gap-2 text-2xl font-black tracking-tight text-emerald-700">
                            <RefreshCw className="h-6 w-6" />
                            Recarga de Vehículo
                        </DialogTitle>
                        <p className="text-sm text-muted-foreground">
                            Registre los productos llenos, vacíos y faltantes
                            que trae el vehículo de vuelta.
                        </p>
                    </DialogHeader>

                    {cargaSeleccionada && (
                        <div className="space-y-6 p-6">
                            {/* Info del vehículo */}
                            <div className="grid grid-cols-2 gap-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                                <div>
                                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                        Vehículo
                                    </p>
                                    <p className="font-bold uppercase">
                                        {cargaSeleccionada.vehiculo?.placa}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                        Conductor
                                    </p>
                                    <p className="font-medium">
                                        {cargaSeleccionada.conductor?.nombre}
                                    </p>
                                </div>
                            </div>

                            {/* Resumen */}
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-center">
                                    <Package className="mx-auto mb-1 h-6 w-6 text-green-600" />
                                    <p className="text-2xl font-black text-green-700">
                                        {totalLlenos}
                                    </p>
                                    <p className="text-xs font-bold text-green-600">
                                        LLENOS
                                    </p>
                                </div>
                                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-center">
                                    <PackageX className="mx-auto mb-1 h-6 w-6 text-amber-600" />
                                    <p className="text-2xl font-black text-amber-700">
                                        {totalVacios}
                                    </p>
                                    <p className="text-xs font-bold text-amber-600">
                                        VACÍOS
                                    </p>
                                </div>
                                <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-center">
                                    <PackageMinus className="mx-auto mb-1 h-6 w-6 text-red-600" />
                                    <p className="text-2xl font-black text-red-700">
                                        {totalFaltantes}
                                    </p>
                                    <p className="text-xs font-bold text-red-600">
                                        FALTANTES
                                    </p>
                                </div>
                                <div className="rounded-xl border border-purple-200 bg-purple-50 p-4 text-center">
                                    <PackageX className="mx-auto mb-1 h-6 w-6 text-purple-600" />
                                    <p className="text-2xl font-black text-purple-700">
                                        {totalDefectuosos}
                                    </p>
                                    <p className="text-xs font-bold text-purple-600">
                                        DEFECTUOSOS
                                    </p>
                                </div>
                            </div>

                            {/* Productos */}
                            <div>
                                <h4 className="mb-3 text-sm font-bold text-foreground">
                                    Detalle por Producto
                                </h4>
                                <p className="mb-3 text-[11px] text-muted-foreground">
                                    Ingrese las cantidades que trae el vehículo
                                    de vuelta por cada producto.
                                </p>
                                <div className="space-y-4">
                                    {recargarData.productos.map((prod, idx) => {
                                        const totalDevuelto =
                                            prod.cantidad_llena +
                                            prod.cantidad_vacia +
                                            prod.cantidad_faltante;
                                        const diferencia =
                                            prod.cantidad_bordo - totalDevuelto;
                                        return (
                                            <div
                                                key={prod.producto_id}
                                                className="rounded-xl border border-border/50 bg-muted/20 p-4"
                                            >
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-bold">
                                                        {prod.producto_nombre}
                                                    </p>
                                                    <span className="text-xs font-bold text-muted-foreground">
                                                        Salió con:{' '}
                                                        {prod.cantidad_bordo}
                                                    </span>
                                                </div>
                                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                                                    <div className="space-y-1">
                                                        <Label className="text-[10px] font-black uppercase text-green-600">
                                                            Llenos
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={
                                                                prod.cantidad_llena
                                                            }
                                                            onChange={(e) => {
                                                                const newProductos =
                                                                    [
                                                                        ...recargarData.productos,
                                                                    ];
                                                                newProductos[
                                                                    idx
                                                                ].cantidad_llena =
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0;
                                                                setRecargarData({
                                                                    ...recargarData,
                                                                    productos:
                                                                        newProductos,
                                                                });
                                                            }}
                                                            className="h-9 text-center font-bold"
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label className="text-[10px] font-black uppercase text-amber-600">
                                                            Vacíos
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={
                                                                prod.cantidad_vacia
                                                            }
                                                            onChange={(e) => {
                                                                const newProductos =
                                                                    [
                                                                        ...recargarData.productos,
                                                                    ];
                                                                newProductos[
                                                                    idx
                                                                ].cantidad_vacia =
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0;
                                                                setRecargarData({
                                                                    ...recargarData,
                                                                    productos:
                                                                        newProductos,
                                                                });
                                                            }}
                                                            className="h-9 text-center font-bold"
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label className="text-[10px] font-black uppercase text-red-600">
                                                            Faltantes
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={
                                                                prod.cantidad_faltante
                                                            }
                                                            onChange={(e) => {
                                                                const newProductos =
                                                                    [
                                                                        ...recargarData.productos,
                                                                    ];
                                                                newProductos[
                                                                    idx
                                                                ].cantidad_faltante =
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0;
                                                                setRecargarData({
                                                                    ...recargarData,
                                                                    productos:
                                                                        newProductos,
                                                                });
                                                            }}
                                                            className="h-9 text-center font-bold"
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label className="text-[10px] font-black uppercase text-purple-600">
                                                            Defectuosos
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={
                                                                prod.cantidad_defectuosa
                                                            }
                                                            onChange={(e) => {
                                                                const newProductos =
                                                                    [
                                                                        ...recargarData.productos,
                                                                    ];
                                                                newProductos[
                                                                    idx
                                                                ].cantidad_defectuosa =
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0;
                                                                setRecargarData({
                                                                    ...recargarData,
                                                                    productos:
                                                                        newProductos,
                                                                });
                                                            }}
                                                            className="h-9 text-center font-bold"
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label className="text-[10px] font-black uppercase text-muted-foreground">
                                                            Vendidos
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={
                                                                prod.cantidad_vendida
                                                            }
                                                            onChange={(e) => {
                                                                const newProductos =
                                                                    [
                                                                        ...recargarData.productos,
                                                                    ];
                                                                newProductos[
                                                                    idx
                                                                ].cantidad_vendida =
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0;
                                                                setRecargarData({
                                                                    ...recargarData,
                                                                    productos:
                                                                        newProductos,
                                                                });
                                                            }}
                                                            className="h-9 text-center font-bold"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="mt-3 flex items-center justify-between border-t pt-3 text-xs">
                                                    <span className="text-muted-foreground">
                                                        Devueltos:{' '}
                                                        {totalDevuelto}
                                                    </span>
                                                    {diferencia > 0 ? (
                                                        <span className="font-bold text-blue-600">
                                                            Pendiente: {diferencia}
                                                        </span>
                                                    ) : diferencia < 0 ? (
                                                        <span className="font-bold text-amber-600">
                                                            Sobrante:{' '}
                                                            {Math.abs(diferencia)}
                                                        </span>
                                                    ) : (
                                                        <span className="font-bold text-green-600">
                                                            ✓ Cuadra
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Totales y dinero */}
                            <div className="grid grid-cols-2 gap-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                                <div className="space-y-1">
                                    <Label className="text-xs font-bold uppercase text-muted-foreground">
                                        Total Ventas ($)
                                    </Label>
                                    <div className="relative">
                                        <DollarSign className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-500" />
                                        <Input
                                            type="number"
                                            min="0"
                                            value={recargarData.ventas_totales}
                                            onChange={(e) =>
                                                setRecargarData({
                                                    ...recargarData,
                                                    ventas_totales:
                                                        parseFloat(
                                                            e.target.value,
                                                        ) || 0,
                                                })
                                            }
                                            className="h-10 rounded-xl bg-white pl-10 text-center font-black shadow-sm"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs font-bold uppercase text-muted-foreground">
                                        Total Devoluciones ($)
                                    </Label>
                                    <div className="relative">
                                        <ArrowLeftRight className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-amber-500" />
                                        <Input
                                            type="number"
                                            min="0"
                                            value={
                                                recargarData.devoluciones_totales
                                            }
                                            onChange={(e) =>
                                                setRecargarData({
                                                    ...recargarData,
                                                    devoluciones_totales:
                                                        parseFloat(
                                                            e.target.value,
                                                        ) || 0,
                                                })
                                            }
                                            className="h-10 rounded-xl bg-white pl-10 text-center font-black shadow-sm"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Notas y opción crear nueva carga */}
                            <div className="space-y-3">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Notas de la Recarga
                                    </Label>
                                    <Input
                                        value={recargarData.notas}
                                        onChange={(e) =>
                                            setRecargarData({
                                                ...recargarData,
                                                notas: e.target.value,
                                            })
                                        }
                                        placeholder="Observaciones de la recarga..."
                                        className="h-9"
                                    />
                                </div>
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={recargarData.crear_nueva_carga}
                                        onChange={(e) =>
                                            setRecargarData({
                                                ...recargarData,
                                                crear_nueva_carga:
                                                    e.target.checked,
                                            })
                                        }
                                        className="h-4 w-4 rounded border-gray-300 text-emerald-600"
                                    />
                                    <span className="text-sm font-bold text-muted-foreground">
                                        Crear nueva carga con los productos
                                        llenos
                                    </span>
                                </label>
                            </div>

                            <div className="flex justify-end gap-3 border-t pt-4">
                                <Button
                                    variant="outline"
                                    onClick={() => setIsRecargarOpen(false)}
                                    className="rounded-full"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    onClick={handleConfirmarRecarga}
                                    className="gap-2 rounded-full bg-emerald-600 font-bold hover:bg-emerald-700"
                                >
                                    <Receipt className="h-4 w-4" />
                                    Confirmar Recarga
                                </Button>
                            </div>
                        </div>
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
