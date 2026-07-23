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
    DollarSign,
    Calendar,
    User,
    CreditCard,
    CheckCircle,
    Clock,
    AlertCircle,
    Banknote,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { useCountry } from '@/hooks/use-country';
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
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Prestamo {
    id: number;
    empleado_id: number;
    tipo: string;
    monto_total: number;
    monto_cuota: number;
    numero_cuotas: number;
    cuotas_pagadas: number;
    saldo_pendiente: number;
    fecha_inicio: string;
    fecha_fin: string;
    frecuencia: string;
    estado: string;
    motivo: string | null;
    notas: string | null;
    empleado?: { nombre: string; apellido: string; rut: string };
    cuotas?: Cuota[];
}

interface Cuota {
    id: number;
    numero_cuota: number;
    monto: number;
    fecha_vencimiento: string;
    fecha_pago: string | null;
    monto_pagado: number;
    estado: string;
    metodo_pago: string | null;
    aplicada_en_nomina: boolean;
    nomina_periodo: string | null;
}

interface Empleado {
    id: number;
    nombre: string;
    apellido: string;
    rut: string;
    salario: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Préstamos y Adelantos', href: '/prestamos' },
];

export default function Index({
    prestamos,
    empleados,
}: {
    prestamos: { data: Prestamo[]; links: any[]; meta?: any; total: number };
    empleados: Empleado[];
}) {
    const { hasPermission } = usePermissions();
    const { code: countryCode, currency } = useCountry();
    const canCreate = hasPermission('rrhh.prestamos.create');
    const canEdit = hasPermission('rrhh.prestamos.edit');
    const canDelete = hasPermission('rrhh.prestamos.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [isVerOpen, setIsVerOpen] = useState(false);
    const [isPagarOpen, setIsPagarOpen] = useState(false);
    const [isNominaOpen, setIsNominaOpen] = useState(false);
    const [editando, setEditando] = useState<Prestamo | null>(null);
    const [prestamoSeleccionado, setPrestamoSeleccionado] = useState<Prestamo | null>(null);
    const [cuotaSeleccionada, setCuotaSeleccionada] = useState<Cuota | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
        tipo: '',
    });

    const {
        data,
        setData,
        delete: destroy,
        reset,
    } = useForm({
        empleado_id: '',
        tipo: 'prestamo',
        monto_total: '',
        numero_cuotas: '',
        frecuencia: 'mensual',
        fecha_inicio: getLocalDateString(),
        motivo: '',
        notas: '',
    });

    const [pagoData, setPagoData] = useState({
        monto_pagado: 0,
        fecha_pago: getLocalDateString(),
        metodo_pago: 'efectivo',
        referencia_pago: '',
        notas: '',
    });

    const [nominaData, setNominaData] = useState({
        nomina_periodo: '',
    });

    const [isCuotasOpen, setIsCuotasOpen] = useState(false);
    const [prestamoCuotas, setPrestamoCuotas] = useState<Prestamo | null>(null);
    const [cuotasData, setCuotasData] = useState<{ numero_cuotas: number }>({
        numero_cuotas: 1,
    });

    const prestamosFiltrados = useMemo(() => {
        return (prestamos.data || []).filter((p) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                const empleadoMatch =
                    `${p.empleado?.nombre} ${p.empleado?.apellido}`.toLowerCase().includes(busca) || false;
                const rutMatch = p.empleado?.rut?.toLowerCase().includes(busca) || false;
                if (!empleadoMatch && !rutMatch) return false;
            }
            if (filtros.estado && p.estado !== filtros.estado) return false;
            if (filtros.tipo && p.tipo !== filtros.tipo) return false;
            return true;
        });
    }, [prestamos, filtros]);

    const limpiarFiltros = () => {
        setFiltros({ busqueda: '', estado: '', tipo: '' });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const cleanData = {
            ...data,
            monto_total: parseFloat(data.monto_total as string) || 0,
            numero_cuotas: parseInt(data.numero_cuotas as string) || 1,
        };

        if (editando) {
            router.put(`/prestamos/${editando.id}`, cleanData, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                },
            });
        } else {
            router.post('/prestamos', cleanData, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (p: Prestamo) => {
        setEditando(p);
        setData({
            empleado_id: p.empleado_id.toString(),
            tipo: p.tipo,
            monto_total: p.monto_total.toString(),
            numero_cuotas: p.numero_cuotas.toString(),
            frecuencia: p.frecuencia,
            fecha_inicio: p.fecha_inicio.split('T')[0],
            motivo: p.motivo || '',
            notas: p.notas || '',
        });
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setEditando(null);
        setIsOpen(true);
    };

    const handleVer = (p: Prestamo) => {
        setPrestamoSeleccionado(p);
        setIsVerOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar este préstamo?'))
            destroy(`/prestamos/${id}`);
    };

    const handlePagar = (cuota: Cuota) => {
        setCuotaSeleccionada(cuota);
        setPagoData({
            monto_pagado: cuota.monto,
            fecha_pago: getLocalDateString(),
            metodo_pago: 'efectivo',
            referencia_pago: '',
            notas: '',
        });
        setIsPagarOpen(true);
    };

    const handleAplicarNomina = (cuota: Cuota) => {
        setCuotaSeleccionada(cuota);
        const now = new Date();
        setNominaData({
            nomina_periodo: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`,
        });
        setIsNominaOpen(true);
    };

    const confirmarPago = () => {
        if (!cuotaSeleccionada) return;

        router.post(`/prestamo-cuotas/${cuotaSeleccionada.id}/pagar`, pagoData, {
            onSuccess: () => {
                setIsPagarOpen(false);
                setIsVerOpen(false);
            },
        });
    };

    const confirmarNomina = () => {
        if (!cuotaSeleccionada) return;

        router.post(`/prestamo-cuotas/${cuotaSeleccionada.id}/aplicar-nomina`, nominaData, {
            onSuccess: () => {
                setIsNominaOpen(false);
                setIsVerOpen(false);
            },
        });
    };

    const handleAgregarCuotas = (prestamo: Prestamo) => {
        setPrestamoCuotas(prestamo);
        setCuotasData({ numero_cuotas: 1 });
        setIsCuotasOpen(true);
    };

    const confirmarAgregarCuotas = () => {
        if (!prestamoCuotas) return;

        router.post(`/prestamos/${prestamoCuotas.id}/cuotas`, cuotasData, {
            onSuccess: () => {
                setIsCuotasOpen(false);
                setPrestamoCuotas(null);
            },
        });
    };

    const getEstadoBadge = (e: string) => {
        const colores: Record<string, string> = {
            activo: 'bg-blue-500',
            pagado: 'bg-green-500',
            cancelado: 'bg-red-500',
            pendiente: 'bg-yellow-500',
            pagada: 'bg-green-500',
            vencida: 'bg-red-500',
        };
        return (
            <Badge className={colores[e] || 'bg-gray-500'}>
                {e.toUpperCase()}
            </Badge>
        );
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat(currency.locale, {
            style: 'currency',
            currency: currency.code,
        }).format(amount);
    };

    return (
        <>
            <Head title="Préstamos y Adelantos" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 pb-24">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Préstamos y Adelantos de Sueldo
                            </h1>
                            <p className="text-muted-foreground">
                                Gestión de préstamos y adelantos a empleados
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canCreate && (
                                <Button onClick={handleNew} size="sm">
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                    Préstamo
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Resumen */}
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-blue-100 p-2">
                                        <CreditCard className="h-5 w-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Activos</p>
                                        <p className="text-lg font-black">
                                            {prestamosFiltrados.filter((p) => p.estado === 'activo').length}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-green-100 p-2">
                                        <CheckCircle className="h-5 w-5 text-green-600" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Pagados</p>
                                        <p className="text-lg font-black">
                                            {prestamosFiltrados.filter((p) => p.estado === 'pagado').length}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-amber-100 p-2">
                                        <DollarSign className="h-5 w-5 text-amber-600" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Saldo Pendiente</p>
                                        <p className="text-lg font-black">
                                            {formatCurrency(
                                                prestamosFiltrados.reduce((sum, p) => sum + (p.saldo_pendiente || 0), 0)
                                            )}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-purple-100 p-2">
                                        <Clock className="h-5 w-5 text-purple-600" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Total Préstamos</p>
                                        <p className="text-lg font-black">
                                            {formatCurrency(
                                                prestamosFiltrados.reduce((sum, p) => sum + (p.monto_total || 0), 0)
                                            )}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Préstamos y Adelantos</CardTitle>
                                    <CardDescription>
                                        {prestamosFiltrados.length} registros encontrados
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
                                            placeholder="Buscar por empleado o RUT..."
                                            value={filtros.busqueda}
                                            onChange={(e) =>
                                                setFiltros({ ...filtros, busqueda: e.target.value })
                                            }
                                            className="h-9 pl-8 pr-8"
                                        />
                                    </div>
                                </div>
                                <Select
                                    value={filtros.tipo}
                                    onValueChange={(val) =>
                                        setFiltros({ ...filtros, tipo: val === 'all' ? '' : val })
                                    }
                                >
                                    <SelectTrigger className="h-9 w-full bg-background sm:w-[150px]">
                                        <SelectValue placeholder="Tipo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Todos</SelectItem>
                                        <SelectItem value="prestamo">Préstamo</SelectItem>
                                        <SelectItem value="adelanto">Adelanto</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={filtros.estado}
                                    onValueChange={(val) =>
                                        setFiltros({ ...filtros, estado: val === 'all' ? '' : val })
                                    }
                                >
                                    <SelectTrigger className="h-9 w-full bg-background sm:w-[150px]">
                                        <SelectValue placeholder="Estado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Todos</SelectItem>
                                        <SelectItem value="activo">Activo</SelectItem>
                                        <SelectItem value="pagado">Pagado</SelectItem>
                                        <SelectItem value="cancelado">Cancelado</SelectItem>
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
                                                <th className="py-2 text-left font-medium">Empleado</th>
                                                <th className="py-2 text-left font-medium">Tipo</th>
                                                <th className="py-2 text-right font-medium">Monto</th>
                                                <th className="py-2 text-center font-medium">Cuotas</th>
                                                <th className="py-2 text-center font-medium">Progreso</th>
                                                <th className="py-2 text-center font-medium">Estado</th>
                                                <th className="py-2 text-right font-medium">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {prestamosFiltrados.map((p) => (
                                                <tr
                                                    key={p.id}
                                                    className="border-b transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="py-2">
                                                        <div className="font-bold">
                                                            {p.empleado?.nombre} {p.empleado?.apellido}
                                                        </div>
                                                        <div className="text-[10px] text-muted-foreground">
                                                            {p.empleado?.rut}
                                                        </div>
                                                    </td>
                                                    <td className="py-2">
                                                        <Badge variant="outline" className="text-xs">
                                                            {p.tipo === 'prestamo' ? 'Préstamo' : 'Adelanto'}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-2 text-right font-bold">
                                                        {formatCurrency(p.monto_total)}
                                                    </td>
                                                    <td className="py-2 text-center">
                                                        {p.cuotas_pagadas}/{p.numero_cuotas}
                                                    </td>
                                                    <td className="py-2 text-center">
                                                        <div className="mx-auto h-2 w-20 overflow-hidden rounded-full bg-gray-200">
                                                            <div
                                                                className="h-full bg-green-500"
                                                                style={{
                                                                    width: `${p.numero_cuotas > 0 ? (p.cuotas_pagadas / p.numero_cuotas) * 100 : 0}%`,
                                                                }}
                                                            />
                                                        </div>
                                                        <p className="mt-1 text-[10px] text-muted-foreground">
                                                            {p.numero_cuotas > 0
                                                                ? Math.round((p.cuotas_pagadas / p.numero_cuotas) * 100)
                                                                : 0}
                                                            %
                                                        </p>
                                                    </td>
                                                    <td className="py-2 text-center">
                                                        {getEstadoBadge(p.estado)}
                                                    </td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                                onClick={() => handleVer(p)}
                                                                title="Ver Detalles"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            {canEdit && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 w-8 p-0"
                                                                    onClick={() => handleEdit(p)}
                                                                    title="Editar"
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {canEdit && p.saldo_pendiente > 0 && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 w-8 p-0 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700"
                                                                    onClick={() => handleAgregarCuotas(p)}
                                                                    title="Pago Extra"
                                                                >
                                                                    <Plus className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {canDelete && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                                                    onClick={() => handleDelete(p.id)}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                            {prestamosFiltrados.length === 0 && (
                                                <tr>
                                                    <td colSpan={8} className="py-8 text-center text-muted-foreground">
                                                        No se encontraron préstamos.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                    {prestamos.links && (
                                        <Pagination links={prestamos.links} meta={prestamos.meta} />
                                    )}
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {prestamosFiltrados.map((p) => (
                                        <Card key={p.id} className="overflow-hidden">
                                            <CardContent className="space-y-3 p-4">
                                                <div className="flex items-center justify-between">
                                                    <Badge variant="outline" className="text-xs">
                                                        {p.tipo === 'prestamo' ? 'Préstamo' : 'Adelanto'}
                                                    </Badge>
                                                    {getEstadoBadge(p.estado)}
                                                </div>
                                                <div>
                                                    <p className="text-lg font-bold">
                                                        {p.empleado?.nombre} {p.empleado?.apellido}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {p.empleado?.rut}
                                                    </p>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2 text-sm">
                                                    <div>
                                                        <p className="text-[10px] text-muted-foreground">Monto Total</p>
                                                        <p className="font-bold">{formatCurrency(p.monto_total)}</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] text-muted-foreground">Cuota Mensual</p>
                                                        <p className="font-bold">{formatCurrency(p.monto_cuota)}</p>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span className="text-muted-foreground">Progreso</span>
                                                        <span className="font-bold">
                                                            {p.cuotas_pagadas}/{p.numero_cuotas}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-200">
                                                        <div
                                                            className="h-full bg-green-500"
                                                            style={{
                                                                width: `${p.numero_cuotas > 0 ? (p.cuotas_pagadas / p.numero_cuotas) * 100 : 0}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                                <div className="flex justify-end gap-1 border-t pt-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-8 w-8 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                        onClick={() => handleVer(p)}
                                                        title="Ver Detalles"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                    {canEdit && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0"
                                                            onClick={() => handleEdit(p)}
                                                            title="Editar"
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canEdit && p.saldo_pendiente > 0 && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700"
                                                            onClick={() => handleAgregarCuotas(p)}
                                                            title="Pago Extra"
                                                        >
                                                            <Plus className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                                            onClick={() => handleDelete(p.id)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                    {prestamosFiltrados.length === 0 && (
                                        <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                            <CreditCard className="mb-3 h-12 w-12 opacity-20" />
                                            <p>No se encontraron préstamos.</p>
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
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-w-lg">
                    <DialogHeader className="bg-gradient-to-r from-blue-600 to-indigo-700 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            {editando ? 'Modificar' : 'Nuevo'} Préstamo / Adelanto
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="px-4 py-4 md:px-6">
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Empleado *</Label>
                                                    <Select
                                        value={data.empleado_id}
                                        onValueChange={(val) => setData('empleado_id', val)}
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Seleccionar..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {empleados.map((e) => (
                                                <SelectItem key={e.id} value={e.id.toString()}>
                                                    {e.nombre} {e.apellido}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Tipo *</Label>
                                    <Select
                                        value={data.tipo}
                                        onValueChange={(val) => setData('tipo', val)}
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Tipo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="prestamo">Préstamo</SelectItem>
                                            <SelectItem value="adelanto">Adelanto de Sueldo</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Monto Total ($) *</Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={data.monto_total}
                                        onChange={(e) => setData('monto_total', e.target.value)}
                                        className="h-9"
                                        required
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Número de Cuotas *</Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={data.numero_cuotas}
                                        onChange={(e) => setData('numero_cuotas', e.target.value)}
                                        className="h-9"
                                        required
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Frecuencia *</Label>
                                    <Select
                                        value={data.frecuencia}
                                        onValueChange={(val) => setData('frecuencia', val)}
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Frecuencia" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="semanal">Semanal</SelectItem>
                                            <SelectItem value="quincenal">Quincenal</SelectItem>
                                            <SelectItem value="mensual">Mensual</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Fecha Inicio *</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_inicio}
                                        onChange={(e) => setData('fecha_inicio', e.target.value)}
                                        className="h-9"
                                        required
                                    />
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold uppercase">Motivo</Label>
                                <Input
                                    value={data.motivo}
                                    onChange={(e) => setData('motivo', e.target.value)}
                                    placeholder="Motivo del préstamo..."
                                    className="h-9"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold uppercase">Notas</Label>
                                <Input
                                    value={data.notas}
                                    onChange={(e) => setData('notas', e.target.value)}
                                    placeholder="Observaciones adicionales..."
                                    className="h-9"
                                />
                            </div>

                            {!editando && data.monto_total && data.numero_cuotas && (
                                <div className="rounded-lg bg-blue-50 p-3 text-sm">
                                    <p className="font-bold text-blue-800">
                                        Cuota estimada: {formatCurrency(
                                            parseFloat(data.monto_total as string) / parseInt(data.numero_cuotas as string)
                                        )}
                                    </p>
                                </div>
                            )}
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
                            <Button type="submit" className="rounded-full sm:px-6">
                                Guardar
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Ver Detalles */}
            <Dialog open={isVerOpen} onOpenChange={setIsVerOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none bg-background p-0 shadow-2xl md:max-w-3xl">
                    <DialogHeader className="bg-gradient-to-r from-blue-500/10 to-transparent p-6 pb-2">
                        <DialogTitle className="text-2xl font-black tracking-tight text-blue-700">
                            Detalles del Préstamo
                        </DialogTitle>
                    </DialogHeader>
                    {prestamoSeleccionado && (
                        <div className="max-h-[calc(90vh-140px)] overflow-y-auto p-6 pt-0">
                            <div className="grid gap-6">
                                {/* Info del préstamo */}
                                <div className="grid grid-cols-2 gap-4 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Empleado
                                        </p>
                                        <p className="font-bold">
                                            {prestamoSeleccionado.empleado?.nombre}{' '}
                                            {prestamoSeleccionado.empleado?.apellido}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {prestamoSeleccionado.empleado?.rut}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Tipo
                                        </p>
                                        <div className="mt-1">
                                            {getEstadoBadge(prestamoSeleccionado.estado)}
                                        </div>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Monto Total
                                        </p>
                                        <p className="text-lg font-black">
                                            {formatCurrency(prestamoSeleccionado.monto_total)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Saldo Pendiente
                                        </p>
                                        <p className="text-lg font-black text-amber-600">
                                            {formatCurrency(prestamoSeleccionado.saldo_pendiente)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Frecuencia
                                        </p>
                                        <p className="font-medium capitalize">
                                            {prestamoSeleccionado.frecuencia}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                            Fechas
                                        </p>
                                        <p className="text-xs">
                                            {prestamoSeleccionado.fecha_inicio?.split('T')[0]} al{' '}
                                            {prestamoSeleccionado.fecha_fin?.split('T')[0]}
                                        </p>
                                    </div>
                                </div>

                                {/* Cuotas */}
                                <div>
                                    <h4 className="mb-3 text-sm font-bold text-foreground">
                                        Cronograma de Cuotas
                                    </h4>
                                    {prestamoSeleccionado.cuotas &&
                                    prestamoSeleccionado.cuotas.length > 0 ? (
                                        <div className="overflow-x-auto rounded-xl border border-border/50">
                                            <table className="w-full text-xs">
                                                <thead>
                                                    <tr className="border-b bg-muted/50">
                                                        <th className="px-3 py-2 text-left font-bold">
                                                            Cuota
                                                        </th>
                                                        <th className="px-3 py-2 text-right font-bold">
                                                            Monto
                                                        </th>
                                                        <th className="px-3 py-2 text-center font-bold">
                                                            Vencimiento
                                                        </th>
                                                        <th className="px-3 py-2 text-center font-bold">
                                                            Estado
                                                        </th>
                                                        <th className="px-3 py-2 text-center font-bold">
                                                            Pago
                                                        </th>
                                                        <th className="px-3 py-2 text-right font-bold">
                                                            Acciones
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {prestamoSeleccionado.cuotas.map((cuota) => (
                                                        <tr key={cuota.id} className="border-b">
                                                            <td className="px-3 py-2 font-bold">
                                                                #{cuota.numero_cuota}
                                                            </td>
                                                            <td className="px-3 py-2 text-right font-bold">
                                                                {formatCurrency(cuota.monto)}
                                                            </td>
                                                            <td className="px-3 py-2 text-center">
                                                                {cuota.fecha_vencimiento?.split('T')[0]}
                                                            </td>
                                                            <td className="px-3 py-2 text-center">
                                                                {getEstadoBadge(cuota.estado)}
                                                            </td>
                                                            <td className="px-3 py-2 text-center text-[10px]">
                                                                {cuota.metodo_pago && (
                                                                    <span className="capitalize">
                                                                        {cuota.metodo_pago}
                                                                    </span>
                                                                )}
                                                                {cuota.aplicada_en_nomina && (
                                                                    <Badge className="ml-1 bg-purple-500 text-[8px]">
                                                                        Nómina
                                                                    </Badge>
                                                                )}
                                                            </td>
                                                            <td className="px-3 py-2 text-right">
                                                                {cuota.estado === 'pendiente' && (
                                                                    <div className="flex justify-end gap-1">
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            className="h-7 text-xs text-green-600"
                                                                            onClick={() => handlePagar(cuota)}
                                                                        >
                                                                            <DollarSign className="mr-1 h-3 w-3" />
                                                                            Pagar
                                                                        </Button>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            className="h-7 text-xs text-purple-600"
                                                                            onClick={() => handleAplicarNomina(cuota)}
                                                                        >
                                                                            <Banknote className="mr-1 h-3 w-3" />
                                                                            Nómina
                                                                        </Button>
                                                                    </div>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <div className="rounded-xl border border-dashed border-border/50 p-8 text-center text-muted-foreground">
                                            No hay cuotas registradas.
                                        </div>
                                    )}
                                </div>

                                {prestamoSeleccionado.motivo && (
                                    <div>
                                        <h4 className="mb-2 text-sm font-bold text-foreground">Motivo</h4>
                                        <p className="rounded-lg bg-muted/30 p-3 text-sm">
                                            {prestamoSeleccionado.motivo}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Modal Registrar Pago */}
            <Dialog open={isPagarOpen} onOpenChange={setIsPagarOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-w-md">
                    <DialogHeader className="bg-gradient-to-r from-green-600 to-emerald-700 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            Registrar Pago de Cuota
                        </DialogTitle>
                    </DialogHeader>
                    <div className="px-4 py-4 md:px-6">
                        {cuotaSeleccionada && (
                            <div className="space-y-4">
                                <div className="rounded-lg bg-green-50 p-3 text-center">
                                    <p className="text-xs text-muted-foreground">Cuota #{cuotaSeleccionada.numero_cuota}</p>
                                    <p className="text-2xl font-black text-green-700">
                                        {formatCurrency(cuotaSeleccionada.monto)}
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Monto a Pagar *</Label>
                                    <Input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={pagoData.monto_pagado}
                                        onChange={(e) =>
                                            setPagoData({ ...pagoData, monto_pagado: parseFloat(e.target.value) || 0 })
                                        }
                                        className="h-9"
                                        required
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Fecha de Pago *</Label>
                                    <Input
                                        type="date"
                                        value={pagoData.fecha_pago}
                                        onChange={(e) => setPagoData({ ...pagoData, fecha_pago: e.target.value })}
                                        className="h-9"
                                        required
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Método de Pago *</Label>
                                    <Select
                                        value={pagoData.metodo_pago}
                                        onValueChange={(val) => setPagoData({ ...pagoData, metodo_pago: val })}
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Método" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="efectivo">Efectivo</SelectItem>
                                            <SelectItem value="transferencia">Transferencia</SelectItem>
                                            <SelectItem value="nomina">Descuento Nómina</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Referencia</Label>
                                    <Input
                                        value={pagoData.referencia_pago}
                                        onChange={(e) => setPagoData({ ...pagoData, referencia_pago: e.target.value })}
                                        placeholder="Número de referencia..."
                                        className="h-9"
                                    />
                                </div>
                                <div className="flex justify-end gap-3 border-t pt-4">
                                    <Button
                                        variant="outline"
                                        onClick={() => setIsPagarOpen(false)}
                                        className="rounded-full"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        onClick={confirmarPago}
                                        className="gap-2 rounded-full bg-green-600 font-bold hover:bg-green-700"
                                    >
                                        <CheckCircle className="h-4 w-4" />
                                        Confirmar Pago
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Modal Aplicar en Nómina */}
            <Dialog open={isNominaOpen} onOpenChange={setIsNominaOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-w-md">
                    <DialogHeader className="bg-gradient-to-r from-purple-600 to-indigo-700 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            Aplicar Cuota en Nómina
                        </DialogTitle>
                    </DialogHeader>
                    <div className="px-4 py-4 md:px-6">
                        {cuotaSeleccionada && (
                            <div className="space-y-4">
                                <div className="rounded-lg bg-purple-50 p-3 text-center">
                                    <p className="text-xs text-muted-foreground">Cuota #{cuotaSeleccionada.numero_cuota}</p>
                                    <p className="text-2xl font-black text-purple-700">
                                        {formatCurrency(cuotaSeleccionada.monto)}
                                    </p>
                                </div>
                                <div className="rounded-lg bg-amber-50 p-3 text-sm">
                                    <p className="font-bold text-amber-800">
                                        Esta cuota se descontará automáticamente del próximo pago de nómina del empleado.
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Período de Nómina *</Label>
                                    <Input
                                        type="month"
                                        value={nominaData.nomina_periodo}
                                        onChange={(e) => setNominaData({ ...nominaData, nomina_periodo: e.target.value })}
                                        className="h-9"
                                        required
                                    />
                                </div>
                                <div className="flex justify-end gap-3 border-t pt-4">
                                    <Button
                                        variant="outline"
                                        onClick={() => setIsNominaOpen(false)}
                                        className="rounded-full"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        onClick={confirmarNomina}
                                        className="gap-2 rounded-full bg-purple-600 font-bold hover:bg-purple-700"
                                    >
                                        <Banknote className="h-4 w-4" />
                                        Aplicar en Nómina
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Modal Agregar Cuotas */}
            <Dialog open={isCuotasOpen} onOpenChange={setIsCuotasOpen}>
                <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl sm:max-w-md">
                    <DialogHeader className="bg-gradient-to-r from-emerald-600 to-teal-700 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            Pago Extra — Préstamo
                        </DialogTitle>
                    </DialogHeader>
                    <div className="px-4 py-4 md:px-6">
                        {prestamoCuotas && (
                            <div className="space-y-4">
                                <div className="rounded-lg bg-emerald-50 p-3 text-center">
                                    <p className="text-xs text-muted-foreground">
                                        {prestamoCuotas.empleado?.nombre} {prestamoCuotas.empleado?.apellido}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Progreso: {prestamoCuotas.cuotas_pagadas}/{prestamoCuotas.numero_cuotas} —{' '}
                                        Saldo: {formatCurrency(prestamoCuotas.saldo_pendiente)}
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">Número de Cuotas a Pagar *</Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        max="48"
                                        step="1"
                                        value={cuotasData.numero_cuotas}
                                        onChange={(e) =>
                                            setCuotasData({ ...cuotasData, numero_cuotas: parseInt(e.target.value) || 1 })
                                        }
                                        className="h-9"
                                        required
                                    />
                                </div>
                                {cuotasData.numero_cuotas > 0 && (
                                    <div className="rounded-lg bg-emerald-50 p-3 text-center">
                                        <p className="text-xs text-muted-foreground">Cuotas pendientes a pagar</p>
                                        <p className="text-xl font-black text-emerald-700">
                                            {Math.min(cuotasData.numero_cuotas, prestamoCuotas.numero_cuotas - prestamoCuotas.cuotas_pagadas)}
                                        </p>
                                    </div>
                                )}
                                <div className="flex justify-end gap-3 border-t pt-4">
                                    <Button
                                        variant="outline"
                                        onClick={() => setIsCuotasOpen(false)}
                                        className="rounded-full"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        onClick={confirmarAgregarCuotas}
                                        className="gap-2 rounded-full bg-emerald-600 font-bold hover:bg-emerald-700"
                                    >
                                        <CheckCircle className="h-4 w-4" />
                                        Pagar Cuotas
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
