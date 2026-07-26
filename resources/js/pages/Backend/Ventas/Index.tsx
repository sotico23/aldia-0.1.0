import { Head, useForm, router } from '@inertiajs/react';
import { CURRENCY_OPTIONS } from '@/lib/currencies';

import {
    Check,
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
    FileText,
    Wallet,
    DollarSign,
    TrendingUp,
    Clock,
    Shuffle,
    Warehouse,
    ChevronUp,
    ChevronDown,
    ArrowUpDown,
    MessageCircle,
} from 'lucide-react';
import { useState, useMemo, useRef } from 'react';
import { toast } from 'sonner';
import { useCountry } from '@/hooks/use-country';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    PieChart, Pie, Cell,
} from 'recharts';
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
import { WhatsAppButton } from '@/components/whatsapp-button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateCLP, formatRut, getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Cliente {
    id: number;
    nombre: string;
    telefono?: string;
}
interface Almacen {
    id: number;
    nombre: string;
}
interface Inventario {
    id: number;
    cantidad: number;
    almacen_id: number;
}
interface Producto {
    id: number;
    codigo: string;
    nombre: string;
    precio_venta: number;
    inventario?: Inventario;
    inventarios?: Inventario[];
    envase_retornable?: boolean;
    envase_producto_id?: number | null;
}
interface DetalleVenta {
    id: number;
    producto_id: number;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    producto?: Producto;
}
interface Venta {
    id: number;
    numero_factura: string;
    cliente_id: number;
    fecha: string;
    subtotal: number;
    iva: number;
    total: number;
    estado: 'pendiente' | 'pagada' | 'cancelada';
    notas: string | null;
    incluye_iva: boolean;
    tipo_descuento: 'monto' | 'porcentaje';
    valor_descuento: number;
    monto_descuento: number;
    almacen_id?: number;
    almacen_nombre?: string;
    cliente?: Cliente;
    detalle_ventas?: DetalleVenta[];
    almacenes_data?: { id: number; nombre: string; pivot: { cantidad_descontada: number } }[];
    currency?: string;
}

function generateRandomInvoice(): string {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = 'FAC-';
    for (let i = 0; i < 6; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ventas', href: '/ventas' },
];

export default function Index({
    ventas,
    clientes,
    productos,
    almacenes,
    resumenVentas,
    ventasPorMes,
    ventasPorSemana,
    ventasPorDia,
}: {
    ventas: {
        data: Venta[];
        links: any[];
        meta?: any;
        total: number;
    };
    clientes: Cliente[];
    productos: Producto[];
    almacenes: Almacen[];
    resumenVentas: {
        total_ventas: number;
        pagadas: number;
        pendientes: number;
        ingresos: number;
    };
    ventasPorMes: { mes: string; total: number }[];
    ventasPorSemana: { mes: string; total: number }[];
    ventasPorDia: { mes: string; total: number }[];
}) {
    const { code: countryCode, currency } = useCountry();

    const [isOpen, setIsOpen] = useState(false);
    const [clienteSearch, setClienteSearch] = useState('');
    const [editando, setEditando] = useState<Venta | null>(null);
    const [verModalOpen, setVerModalOpen] = useState(false);
    const [ventaSeleccionada, setVentaSeleccionada] = useState<Venta | null>(
        null,
    );
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [chartMode, setChartMode] = useState<'monthly' | 'weekly' | 'daily'>('monthly');
    const [filtros, setFiltros] = useState({
        busqueda: '',
        cliente_id: '',
        estado: '',
        fechaDesde: '',
        fechaHasta: '',
    });

    const [sortColumn, setSortColumn] = useState<string>('fecha');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);

    const handleSort = (column: string) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    const ventasFiltradas = useMemo(() => {
        const filtered = ventas.data.filter((v) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !v.numero_factura?.toLowerCase().includes(busca) &&
                    !v.cliente?.nombre?.toLowerCase().includes(busca) &&
                    !v.notas?.toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (
                filtros.cliente_id &&
                filtros.cliente_id !== 'all' &&
                v.cliente_id.toString() !== filtros.cliente_id
            )
                return false;
            if (
                filtros.estado &&
                filtros.estado !== 'all' &&
                v.estado !== filtros.estado
            )
                return false;
            const fechaStr = (f: string) => f?.includes('T') ? f.split('T')[0] : f;
            if (filtros.fechaDesde && fechaStr(v.fecha) < filtros.fechaDesde)
                return false;
            if (filtros.fechaHasta && fechaStr(v.fecha) > filtros.fechaHasta)
                return false;
            return true;
        });

        return filtered.sort((a, b) => {
            let valA: any = a[sortColumn as keyof typeof a];
            let valB: any = b[sortColumn as keyof typeof b];

            if (sortColumn === 'cliente') {
                valA = a.cliente?.nombre || '';
                valB = b.cliente?.nombre || '';
            }

            if (typeof valA === 'string' && typeof valB === 'string') {
                valA = valA.toLowerCase();
                valB = valB.toLowerCase();
            }

            if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
    }, [ventas, filtros, sortColumn, sortDirection]);

    const monthlyData = ventasPorMes;
    const weeklyData = ventasPorSemana;
    const dailyData = ventasPorDia;

    const chartData = chartMode === 'monthly' ? monthlyData : chartMode === 'weekly' ? weeklyData : dailyData;

    const estadoData = useMemo(() => {
        const canceladas = ventas.data.filter((v) => v.estado === 'cancelada').length;
        return [
            { name: 'Pagadas', value: resumenVentas.pagadas, color: '#22c55e' },
            { name: 'Pendientes', value: resumenVentas.pendientes, color: '#f59e0b' },
            { name: 'Canceladas', value: canceladas, color: '#ef4444' },
        ].filter((d) => d.value > 0);
    }, [ventas, resumenVentas]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            cliente_id: '',
            estado: '',
            fechaDesde: '',
            fechaHasta: '',
        });
    };

    const handleExportCsv = () => {
        const params = new URLSearchParams(filtros);
        window.location.href = `/ventas/export?${params.toString()}`;
    };

    const handleExportExcel = () => {
        const params = new URLSearchParams(filtros);
        window.location.href = `/ventas/export-excel?${params.toString()}`;
    };

    const handleImportCsv = (e: React.ChangeEvent<HTMLInputElement>, isExcel = false) => {
        const file = e.target.files?.[0];
        if (!file) return;

        router.post(isExcel ? '/ventas/import-excel' : '/ventas/import', {
            archivo: file,
        }, {
            forceFormData: true,
            onSuccess: (page) => {
                const flash = page.props.flash?.success;
                if (flash) {
                    toast.success(flash);
                } else {
                    toast.success('Importación completada');
                }
            },
            onError: (err) => {
                console.error(err);
                toast.error('Error al importar: ' + Object.values(err)[0]);
            },
        });
    };

    const { hasPermission, user } = usePermissions();
    const canCreate = hasPermission('ventas.ventas.create');
    const canEditGlobal = hasPermission('ventas.ventas.edit');
    const canDeleteGlobal = hasPermission('ventas.ventas.delete');

    const canEditVenta = (venta: Venta): boolean => {
        return canEditGlobal || venta.user_id === user?.id;
    };

    const canDeleteVenta = (venta: Venta): boolean => {
        return canDeleteGlobal || venta.user_id === user?.id;
    };
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        errors,
        transform,
    } = useForm({
        numero_factura: '',
        cliente_id: '' as string,
        cliente_tipo: 'existente' as 'existente' | 'generico',
        cliente_nombre: '',
        cliente_rut: '',
        cliente_telefono: '',
        cliente_direccion: '',
        fecha: getLocalDateString(),
        estado: 'pendiente' as 'pendiente' | 'pagada' | 'cancelada',
        notas: '',
        incluye_iva: true,
        tipo_descuento: 'monto' as 'monto' | 'porcentaje',
        valor_descuento: 0,
        productos: [] as {
            producto_id: string;
            cantidad: number;
            precio_unitario: number;
            modoGramos?: boolean;
        }[],
        almacen_ids: [] as number[],
        currency: currency.code,
    });

    const calcularTotales = useMemo(() => {
        let subtotal = 0;
        data.productos.forEach((p: any) => {
            const precio = p.precio_unitario || 0;
            subtotal += Math.round(p.cantidad * precio);

            const producto = productos.find(prod => prod.id === Number(p.producto_id));
            if (producto && (producto as any).envase_retornable && (producto as any).envase_producto_id) {
                const cantidadRetornada = p.cantidad_retornada ?? 0;
                const envasesPendientes = p.cantidad - cantidadRetornada;
                if (envasesPendientes > 0) {
                    const envaseProducto = productos.find(prod => prod.id === (producto as any).envase_producto_id);
                    if (envaseProducto) {
                        subtotal += Math.round(envasesPendientes * envaseProducto.precio_venta);
                    }
                }
            }
        });

        let montoDescuento = 0;
        if (data.tipo_descuento === 'porcentaje') {
            montoDescuento = Math.round(
                subtotal * (data.valor_descuento / 100),
            );
        } else {
            montoDescuento = Math.round(data.valor_descuento);
        }

        const baseImponible = Math.max(0, subtotal - montoDescuento);
        const iva = data.incluye_iva ? Math.round(baseImponible * 0.19) : 0;
        const total = baseImponible + iva;

        return { subtotal, montoDescuento, baseImponible, iva, total };
    }, [
        data.productos,
        data.tipo_descuento,
        data.valor_descuento,
        data.incluye_iva,
        productos,
    ]);

    const productosFiltrados = useMemo(() => {
        if (data.almacen_ids.length === 0) {
            return [];
        }
        return productos.filter((p) =>
            p.inventarios?.some(
                (inv) => data.almacen_ids.includes(inv.almacen_id),
            ),
        );
    }, [productos, data.almacen_ids]);

    const addProducto = () => {
        setData('productos' as any, [
            ...data.productos,
            { producto_id: '', cantidad: 1, precio_unitario: 0, cantidad_retornada: 1, modoGramos: false },
        ] as any);
    };

    const updateProducto = (
        index: number,
        field: string,
        value: string | number | boolean,
    ) => {
        const updated = [...data.productos];
        (updated[index] as any)[field] = value;

        if (field === 'producto_id') {
            const producto = productos.find((p) => p.id === Number(value));
            if (producto) {
                updated[index].precio_unitario = producto.precio_venta;
            }
        }

        if (field === 'cantidad') {
            updated[index].cantidad_retornada = value;
        }

        setData('productos', updated);
    };

    const removeProducto = (index: number) => {
        setData(
            'productos',
            data.productos.filter((_, i) => i !== index),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Start Local Validations
        if (data.cliente_tipo === 'existente' && !data.cliente_id) {
            toast.warning(
                'Por favor, seleccione un Cliente Existente o cambie a Cliente Genérico.',
            );
            return;
        }

        if (data.almacen_ids.length === 0) {
            toast.warning('Por favor, seleccione al menos un Almacén.');
            return;
        }

        if (data.cliente_tipo === 'generico' && !data.cliente_nombre) {
            toast.warning(
                'Por favor, ingrese el Nombre o Razón Social del Cliente Genérico.',
            );
            return;
        }

        if (data.productos.length === 0) {
            toast.warning('Por favor, agregue al menos un producto a la venta.');
            return;
        }

        const productsWithoutId = data.productos.filter((p) => !p.producto_id);
        if (productsWithoutId.length > 0) {
            toast.warning(
                'Por favor, seleccione un producto válido en cada fila o elimine las vacías.',
            );
            return;
        }

        // Validate stock before submitting across selected warehouses
        const stockWarnings: string[] = [];
        const selectedAlmacenIds = data.almacen_ids;

        data.productos.forEach((p: any) => {
            const producto = productos.find(
                (prod: any) => prod.id === Number(p.producto_id),
            );
            if (producto) {
                // Sum stock across all selected warehouses
                let stockDisponible = 0;
                const stockPorAlmacen: string[] = [];
                producto.inventarios?.forEach((inv: any) => {
                    if (selectedAlmacenIds.includes(inv.almacen_id)) {
                        stockDisponible += inv.cantidad || 0;
                        const almacen = almacenes.find(a => a.id === inv.almacen_id);
                        stockPorAlmacen.push(`${almacen?.nombre || '?'}: ${Math.round(inv.cantidad || 0)}`);
                    }
                });

                if (p.cantidad > stockDisponible) {
                    stockWarnings.push(
                        `• ${producto.nombre}: Stock ${stockPorAlmacen.join(', ')} (Total: ${Math.round(stockDisponible)}) < Solicitado ${p.cantidad}`,
                    );
                }
            }
        });

        if (stockWarnings.length > 0) {
            const continuar = confirm(
                `⚠️ STOCK INSUFICIENTE EN EL ALMACÉN SELECCIONADO\n\n${stockWarnings.join('\n')}\n\n¿Desea continuar con la venta de todas formas?`,
            );
            if (!continuar) {
                return; // Cancel submission
            }
        }

        const { subtotal, iva, total } = calcularTotales;

        const payloadTransform = (formData: typeof data) => ({
            ...formData,
            cliente_id:
                formData.cliente_tipo === 'existente'
                    ? formData.cliente_id
                        ? Number(formData.cliente_id)
                        : null
                    : null,
            subtotal,
            iva,
            total,
            productos: formData.productos.map((p: any) => ({
                producto_id: Number(p.producto_id),
                cantidad: p.cantidad,
                precio_unitario: p.precio_unitario,
                cantidad_retornada: p.cantidad_retornada,
            })),
            almacen_ids: formData.almacen_ids,
            currency: formData.currency,
        });

        transform(payloadTransform);

        if (editando) {
            put(`/ventas/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                },
                onError: (errors) => {
                    const msg = typeof errors === 'object'
                        ? Object.values(errors).flat().join('\n')
                        : 'Error al actualizar la venta';
                    toast.error(msg);
                },
            });
        } else {
            post('/ventas', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
                onError: (errors) => {
                    const msg = typeof errors === 'object'
                        ? Object.values(errors).flat().join('\n')
                        : 'Error al crear la venta';
                    toast.error(msg);
                },
            });
        }
    };

    const handleEdit = (venta: Venta) => {
        setEditando(venta);
        setData({
            numero_factura: venta.numero_factura,
            cliente_id: venta.cliente_id.toString(),
            fecha: venta.fecha ? venta.fecha.split('T')[0] : '',
            estado: venta.estado,
            notas: venta.notas || '',
            incluye_iva: (venta as any).incluye_iva ?? true,
            tipo_descuento: (venta as any).tipo_descuento ?? 'monto',
            valor_descuento: Number((venta as any).valor_descuento) || 0,
            productos:
                venta.detalle_ventas?.map((d) => ({
                    producto_id: d.producto_id.toString(),
                    cantidad: d.cantidad,
                    precio_unitario: d.precio_unitario,
                    cantidad_retornada: (d as any).cantidad_retornada ?? d.cantidad,
                })) || [],
            almacen_ids: (venta as any).almacen_ids || venta.almacen_id ? [venta.almacen_id] : [],
            currency: (venta as any).currency || currency.code,
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Está seguro de eliminar esta venta?'))
            destroy(`/ventas/${id}`);
    };

    const handleVer = (venta: Venta) => {
        setVentaSeleccionada(venta);
        setVerModalOpen(true);
    };

    const handleUpdateStatus = (venta: Venta, nuevoEstado: string) => {
        router.patch(
            `/ventas/${venta.id}/status`,
            { estado: nuevoEstado },
            {
                preserveScroll: true,
            },
        );
    };

    const handleClose = () => {
        setIsOpen(false);
        setEditando(null);
        setClienteSearch('');
        reset();
        setData('productos', []);
        setData('cliente_tipo', 'existente');
        setData('cliente_nombre', '');
        setData('cliente_rut', '');
        setData('cliente_telefono', '');
        setData('cliente_direccion', '');
    };

    const getEstadoBadge = (estado: string) => {
        const variants = {
            pendiente: 'secondary',
            pagada: 'default',
            cancelada: 'destructive',
        } as const;
        return (
            <Badge variant={variants[estado as keyof typeof variants]}>
                {estado}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ventas" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Ventas</h1>
                    <div className="flex gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" className="gap-2">
                                    <Download className="h-4 w-4" />
                                    Herramientas
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuItem
                                    onClick={handleExportCsv}
                                    className="gap-2"
                                >
                                    <Download className="h-4 w-4 text-green-500" />
                                    Exportar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={handleExportExcel}
                                    className="gap-2"
                                >
                                    <Download className="h-4 w-4 text-blue-500" />
                                    Exportar Excel
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => csvInputRef.current?.click()}
                                    className="gap-2 cursor-pointer"
                                >
                                    <Upload className="h-4 w-4 text-orange-500" />
                                    Importar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => excelInputRef.current?.click()}
                                    className="gap-2 cursor-pointer"
                                >
                                    <Upload className="h-4 w-4 text-purple-500" />
                                    Importar Excel
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        {canCreate && (
                            <Button
                                onClick={() => {
                                    setEditando(null);
                                    reset();
                                    setData('productos', []);
                                    setIsOpen(true);
                                }}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Nueva Venta
                            </Button>
                        )}
                    </div>
                </div>

                {/* Hidden file inputs for import (moved outside DropdownMenu) */}
                <input
                    type="file"
                    ref={csvInputRef}
                    className="hidden"
                    accept=".csv"
                    onChange={(e) => handleImportCsv(e, false)}
                />
                <input
                    type="file"
                    ref={excelInputRef}
                    className="hidden"
                    accept=".xlsx,.xls"
                    onChange={(e) => handleImportCsv(e, true)}
                />

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-sm font-medium">
                                    {chartMode === 'monthly' ? 'Ventas por Mes' : chartMode === 'weekly' ? 'Ventas por Semana' : 'Ventas por Día'}
                                </CardTitle>
                                <div className="flex items-center gap-1 rounded-lg border bg-muted/30 p-0.5">
                                    <button
                                        onClick={() => setChartMode('daily')}
                                        className={`rounded-md px-2 py-1 text-xs font-medium transition-colors ${chartMode === 'daily' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                    >
                                        Día
                                    </button>
                                    <button
                                        onClick={() => setChartMode('weekly')}
                                        className={`rounded-md px-2 py-1 text-xs font-medium transition-colors ${chartMode === 'weekly' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                    >
                                        Semana
                                    </button>
                                    <button
                                        onClick={() => setChartMode('monthly')}
                                        className={`rounded-md px-2 py-1 text-xs font-medium transition-colors ${chartMode === 'monthly' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                    >
                                        Mes
                                    </button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="h-48">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={chartData}>
                                        <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                        <XAxis dataKey="mes" tick={{ fontSize: 10 }} interval={0} angle={chartMode === 'weekly' ? -30 : -45} textAnchor="end" height={chartMode === 'monthly' ? 30 : 50} />
                                        <YAxis tick={{ fontSize: 11 }} />
                                        <Tooltip
                                            formatter={((value: any) => formatCurrency(value)) as any}
                                            contentStyle={{ fontSize: 12 }}
                                        />
                                        <Bar dataKey="total" fill="#6366f1" radius={[4, 4, 0, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Estado de Ventas</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-48 items-center justify-center">
                                <ResponsiveContainer width="100%" height="100%">
                                    <PieChart>
                                        <Pie
                                            data={estadoData}
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={50}
                                            outerRadius={70}
                                            paddingAngle={4}
                                            dataKey="value"
                                        >
                                            {estadoData.map((e, i) => (
                                                <Cell key={i} fill={e.color} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                    </PieChart>
                                </ResponsiveContainer>
                                <div className="ml-2 space-y-1 text-xs">
                                    {estadoData.map((e) => (
                                        <div key={e.name} className="flex items-center gap-2">
                                            <span className="inline-block h-2.5 w-2.5 rounded-full" style={{ backgroundColor: e.color }} />
                                            <span>{e.name}: {e.value}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Card className="border-l-4 border-l-indigo-500">
                        <CardHeader className="pb-1 sm:pb-2">
                            <CardTitle className="flex items-center gap-2 text-xs font-medium sm:text-sm">
                                <Wallet className="h-3.5 w-3.5 text-indigo-500 sm:h-4 sm:w-4" />
                                Total Ventas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold sm:text-2xl">{resumenVentas.total_ventas}</div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-emerald-500">
                        <CardHeader className="pb-1 sm:pb-2">
                            <CardTitle className="flex items-center gap-2 text-xs font-medium sm:text-sm">
                                <DollarSign className="h-3.5 w-3.5 text-emerald-500 sm:h-4 sm:w-4" />
                                Ingresos
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold text-emerald-600 sm:text-2xl">
                                {formatCurrency(resumenVentas.ingresos)}
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-blue-500">
                        <CardHeader className="pb-1 sm:pb-2">
                            <CardTitle className="flex items-center gap-2 text-xs font-medium sm:text-sm">
                                <TrendingUp className="h-3.5 w-3.5 text-blue-500 sm:h-4 sm:w-4" />
                                Pagadas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold text-blue-600 sm:text-2xl">
                                {resumenVentas.pagadas}
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-amber-500">
                        <CardHeader className="pb-1 sm:pb-2">
                            <CardTitle className="flex items-center gap-2 text-xs font-medium sm:text-sm">
                                <Clock className="h-3.5 w-3.5 text-amber-500 sm:h-4 sm:w-4" />
                                Pendientes
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold text-amber-600 sm:text-2xl">
                                {resumenVentas.pendientes}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Listado de Ventas</CardTitle>
                                <CardDescription>
                                    {ventasFiltradas.length} registros encontrados
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button variant={viewMode === 'table' ? 'default' : 'outline'} size="icon" onClick={() => setViewMode('table')}>
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button variant={viewMode === 'cards' ? 'default' : 'outline'} size="icon" onClick={() => setViewMode('cards')}>
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex flex-wrap gap-3 rounded-xl border bg-muted/50 p-4 shadow-sm">
                            <div className="min-w-[200px] flex-1">
                                <div className="group relative">
                                    <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                                    <Input
                                        placeholder="Buscar por factura, cliente o notas..."
                                        value={filtros.busqueda}
                                        onChange={(e) =>
                                            setFiltros({
                                                ...filtros,
                                                busqueda: e.target.value,
                                            })
                                        }
                                        className="h-10 border-muted-foreground/20 pl-9 pr-9 transition-all focus:border-primary"
                                    />
                                </div>
                            </div>

                            <Select
                                value={filtros.estado}
                                onValueChange={(v) =>
                                    setFiltros({
                                        ...filtros,
                                        estado: v,
                                    })
                                }
                            >
                                <SelectTrigger className="h-10 w-full border-muted-foreground/20 bg-background sm:w-[180px]">
                                    <SelectValue placeholder="Todos los estados" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos los estados
                                    </SelectItem>
                                    <SelectItem value="pendiente">
                                        Pendiente
                                    </SelectItem>
                                    <SelectItem value="pagada">
                                        Pagada
                                    </SelectItem>
                                    <SelectItem value="cancelada">
                                        Cancelada
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Select
                                value={filtros.cliente_id}
                                onValueChange={(v) =>
                                    setFiltros({
                                        ...filtros,
                                        cliente_id: v,
                                    })
                                }
                            >
                                <SelectTrigger className="h-10 w-full border-muted-foreground/20 bg-background sm:w-[200px]">
                                    <SelectValue placeholder="Todos los clientes" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos los clientes
                                    </SelectItem>
                                    {clientes.map((c) => (
                                        <SelectItem
                                            key={c.id}
                                            value={c.id.toString()}
                                        >
                                            {c.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Input
                                type="date"
                                placeholder="Desde"
                                value={filtros.fechaDesde}
                                onChange={(e) =>
                                    setFiltros({
                                        ...filtros,
                                        fechaDesde: e.target.value,
                                    })
                                }
                                className="h-10 w-full border-muted-foreground/20 sm:w-[150px]"
                            />
                            <Input
                                type="date"
                                placeholder="Hasta"
                                value={filtros.fechaHasta}
                                onChange={(e) =>
                                    setFiltros({
                                        ...filtros,
                                        fechaHasta: e.target.value,
                                    })
                                }
                                className="h-10 w-full border-muted-foreground/20 sm:w-[150px]"
                            />
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={limpiarFiltros}
                                className="h-10 w-10 border-muted-foreground/20 hover:border-destructive/30 hover:bg-destructive/10 hover:text-destructive"
                                title="Limpiar filtros"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                        {viewMode === 'table' ? (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="pb-3 text-left text-sm font-medium whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('numero_factura')}>
                                                <div className="flex items-center gap-1">Factura {sortColumn === 'numero_factura' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                            </th>
                                            <th className="pb-3 text-left text-sm font-medium whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('cliente')}>
                                                <div className="flex items-center gap-1">Cliente {sortColumn === 'cliente' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                            </th>
                                            <th className="pb-3 text-left text-sm font-medium whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('fecha')}>
                                                <div className="flex items-center gap-1">Fecha {sortColumn === 'fecha' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                            </th>
                                            <th className="pb-3 text-left text-sm font-medium whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('total')}>
                                                <div className="flex items-center gap-1">Total {sortColumn === 'total' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                            </th>
                                            <th className="pb-3 text-left text-sm font-medium whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('estado')}>
                                                <div className="flex items-center gap-1">Estado {sortColumn === 'estado' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                            </th>
                                            <th className="pb-3 text-left text-sm font-medium whitespace-nowrap">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ventasFiltradas.map((venta) => (
                                            <tr key={venta.id} className="border-b">
                                                <td className="py-3 font-medium">
                                                    {venta.numero_factura}
                                                </td>
                                                <td className="py-3">
                                                    <div className="flex items-center gap-1">
                                                        <span
                                                            className="block max-w-[150px] truncate"
                                                            title={
                                                                venta.cliente
                                                                    ?.nombre
                                                            }
                                                        >
                                                            {venta.cliente?.nombre}
                                                        </span>
                                                        {venta.cliente
                                                            ?.telefono && (
                                                                <WhatsAppButton
                                                                    phone={
                                                                        venta.cliente
                                                                            .telefono
                                                                    }
                                                                />
                                                            )}
                                                    </div>
                                                </td>
                                                <td className="py-3">
                                                    {formatDateCLP(venta.fecha)}
                                                </td>
                                                <td className="py-3 font-medium">
                                                    {formatCurrency(venta.total, venta.currency)}
                                                </td>
                                                <td className="py-3">
                                                    <Select
                                                        value={venta.estado}
                                                        onValueChange={(v) =>
                                                            handleUpdateStatus(
                                                                venta,
                                                                v,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="h-8 w-[120px] border-none bg-transparent p-0 hover:bg-transparent focus:ring-0">
                                                            <SelectValue>
                                                                {getEstadoBadge(
                                                                    venta.estado,
                                                                )}
                                                            </SelectValue>
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="pendiente">
                                                                Pendiente
                                                            </SelectItem>
                                                            <SelectItem value="pagada">
                                                                Pagada
                                                            </SelectItem>
                                                            <SelectItem value="cancelada">
                                                                Cancelada
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </td>
                                                <td className="py-3">
                                                    <div className="flex gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleVer(venta)
                                                            }
                                                            title="Ver detalles"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button
                                                                    variant="outline"
                                                                    size="icon"
                                                                    title="Descargar PDF"
                                                                    className="text-red-600 hover:bg-red-50 hover:text-red-700"
                                                                >
                                                                    <FileText className="h-4 w-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end" className="w-56">
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        window.open(
                                                                            `/ventas/${venta.id}/download`,
                                                                            '_blank',
                                                                        )
                                                                    }
                                                                >
                                                                    <FileText className="mr-2 h-4 w-4" />
                                                                    Formato SII (Formal)
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        window.open(
                                                                            `/ventas/${venta.id}/download-informal`,
                                                                            '_blank',
                                                                        )
                                                                    }
                                                                >
                                                                    <FileText className="mr-2 h-4 w-4" />
                                                                    Formato Simple (Informal)
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                        {canEditVenta(venta) && (
                                                            <Button
                                                                variant="outline"
                                                                size="icon"
                                                                onClick={() =>
                                                                    handleEdit(venta)
                                                                }
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDeleteVenta(venta) && (
                                                            <Button
                                                                variant="outline"
                                                                size="icon"
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        venta.id,
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
                                        {ventasFiltradas.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="py-4 text-center"
                                                >
                                                    {ventas.data.length === 0
                                                        ? 'No hay ventas registradas'
                                                        : 'No hay ventas que coincidan con los filtros'}
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {ventasFiltradas.length === 0 ? (
                                    <div className="col-span-full flex flex-col items-center py-12 text-center text-muted-foreground">
                                        <Wallet className="mb-4 h-12 w-12 opacity-20" />
                                        <p className="font-medium">No hay ventas que coincidan con los filtros</p>
                                    </div>
                                ) : ventasFiltradas.map((venta) => (
                                    <Card key={venta.id} className="overflow-hidden">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-center justify-between">
                                                <Badge variant="outline" className="font-mono text-xs">{venta.numero_factura}</Badge>
                                                {getEstadoBadge(venta.estado)}
                                            </div>
                                            <CardTitle className="text-sm font-bold">{venta.cliente?.nombre}</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2 pt-0">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Fecha</span>
                                                <span>{formatDateCLP(venta.fecha)}</span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Total</span>
                                                <span className="font-bold">{formatCurrency(venta.total, venta.currency)}</span>
                                            </div>
                                            {venta.almacen_nombre && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">Almacén</span>
                                                    <span>{venta.almacen_nombre}</span>
                                                </div>
                                            )}
                                            <div className="flex justify-end gap-1 border-t pt-2">
                                                <Button variant="outline" size="icon" onClick={() => handleVer(venta)} title="Ver detalles">
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="outline" size="icon" className="text-red-600 hover:bg-red-50 hover:text-red-700">
                                                            <FileText className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="w-56">
                                                        <DropdownMenuItem onClick={() => window.open(`/ventas/${venta.id}/download`, '_blank')}>
                                                            <FileText className="mr-2 h-4 w-4" /> Formato SII
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem onClick={() => window.open(`/ventas/${venta.id}/download-informal`, '_blank')}>
                                                            <FileText className="mr-2 h-4 w-4" /> Formato Simple
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                                {canEditVenta(venta) && (
                                                    <Button variant="outline" size="icon" onClick={() => handleEdit(venta)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDeleteVenta(venta) && (
                                                    <Button variant="outline" size="icon" onClick={() => handleDelete(venta.id)}>
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                        <Pagination
                            links={ventas.links}
                            meta={
                                ventas.meta || {
                                    from: (ventas as any).from,
                                    to: (ventas as any).to,
                                    total: ventas.total,
                                }
                            }
                        />
                    </CardContent>
                </Card>
                <Dialog
                    open={isOpen}
                    onOpenChange={(open) => !open && handleClose()}
                >
                    <DialogContent
                        className="max-w-[calc(100%-1rem)] overflow-hidden rounded-xl border-none p-0 shadow-2xl sm:max-w-lg md:max-w-2xl lg:max-w-4xl xl:max-w-5xl"
                        style={{ maxHeight: '95vh', height: 'auto' }}
                    >
                        <DialogHeader className="border-b bg-background p-4 pb-3 md:p-6 md:pb-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <DialogTitle className="text-lg font-black md:text-xl">
                                        {editando
                                            ? 'Editar Venta'
                                            : 'Nueva Venta'}
                                    </DialogTitle>
                                    <DialogDescription className="text-sm">
                                        Complete los datos de la venta
                                    </DialogDescription>
                                </div>
                            </div>
                        </DialogHeader>
                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col overflow-hidden"
                            style={{ maxHeight: 'calc(95vh - 140px)' }}
                        >
                            <div className="flex-1 overflow-y-auto px-4 py-3 md:px-6 md:py-4">
                                {Object.keys(errors).length > 0 && (
                                    <div className="mb-3 rounded-md bg-destructive/15 p-3 text-sm text-destructive">
                                        <p className="font-semibold">
                                            Por favor corrija los siguientes
                                            errores:
                                        </p>
                                        <ul className="list-inside list-disc">
                                            {Object.values(errors).map(
                                                (err, i) => (
                                                    <li key={i}>{err}</li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                                <div className="space-y-3 md:space-y-4">
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3 md:gap-4">
                                        <div className="grid gap-2">
                                            <Label>
                                                No. Factura
                                                <span className="text-red-500">
                                                    {' '}
                                                    *
                                                </span>
                                            </Label>
                                            <div className="flex gap-2">
                                                <Input
                                                    value={data.numero_factura}
                                                    onChange={(e) =>
                                                        setData(
                                                            'numero_factura',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={
                                                        !data.numero_factura
                                                            ? 'border-red-500 focus:border-red-500'
                                                            : ''
                                                    }
                                                    placeholder="Ej: FAC-001"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    onClick={() =>
                                                        setData(
                                                            'numero_factura',
                                                            generateRandomInvoice(),
                                                        )
                                                    }
                                                    title="Generar número aleatorio"
                                                    className="shrink-0"
                                                >
                                                    <Shuffle className="h-4 w-4" />
                                                </Button>
                                            </div>
                                            {!data.numero_factura && (
                                                <p className="text-xs text-red-500">
                                                    El número de factura es
                                                    requerido
                                                </p>
                                            )}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Cliente</Label>
                                            <div className="flex gap-2 text-xs">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setData(
                                                            'cliente_tipo',
                                                            'existente',
                                                        );
                                                        setData(
                                                            'cliente_id',
                                                            '',
                                                        );
                                                    }}
                                                    className={`flex-1 rounded-md px-3 py-2 text-center font-medium transition-colors ${data.cliente_tipo ===
                                                        'existente'
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                                        }`}
                                                >
                                                    Cliente Existente
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setData(
                                                            'cliente_tipo',
                                                            'generico',
                                                        );
                                                        setData(
                                                            'cliente_id',
                                                            '',
                                                        );
                                                    }}
                                                    className={`flex-1 rounded-md px-3 py-2 text-center font-medium transition-colors ${data.cliente_tipo ===
                                                        'generico'
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                                        }`}
                                                >
                                                    Cliente Genérico
                                                </button>
                                            </div>
                                        </div>

                                        {data.cliente_tipo === 'existente' ? (
                                            <div className="space-y-2">
                                                <Select
                                                    value={data.cliente_id}
                                                    onValueChange={(v) =>
                                                        setData('cliente_id', v)
                                                    }
                                                >
                                                    <SelectTrigger
                                                        className={
                                                            data.cliente_tipo ===
                                                                'existente' &&
                                                                !data.cliente_id
                                                                ? 'border-red-500'
                                                                : ''
                                                        }
                                                    >
                                                        <SelectValue placeholder="Seleccionar cliente" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <div className="sticky top-0 z-10 border-b bg-popover p-2">
                                                            <Input
                                                                autoFocus
                                                                value={clienteSearch}
                                                                onChange={(e) =>
                                                                    setClienteSearch(
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                onKeyDown={(e) =>
                                                                    e.stopPropagation()
                                                                }
                                                                placeholder="Buscar cliente..."
                                                                className="h-8 text-sm"
                                                            />
                                                        </div>
                                                        <div className="max-h-56 overflow-y-auto">
                                                            {clientes
                                                                .filter((c) =>
                                                                    c.nombre
                                                                        .toLowerCase()
                                                                        .includes(
                                                                            clienteSearch.toLowerCase(),
                                                                        ),
                                                                )
                                                                .map((c) => (
                                                                    <SelectItem
                                                                        key={c.id}
                                                                        value={c.id.toString()}
                                                                    >
                                                                        {c.nombre}
                                                                    </SelectItem>
                                                                ))}
                                                            {clientes.filter(
                                                                (c) =>
                                                                    c.nombre
                                                                        .toLowerCase()
                                                                        .includes(
                                                                            clienteSearch.toLowerCase(),
                                                                        ),
                                                            ).length === 0 && (
                                                                    <p className="px-2 py-3 text-center text-xs text-muted-foreground">
                                                                        Sin
                                                                        resultados
                                                                    </p>
                                                                )}
                                                        </div>
                                                    </SelectContent>
                                                </Select>
                                                {data.cliente_tipo ===
                                                    'existente' &&
                                                    !data.cliente_id && (
                                                        <p className="text-xs text-red-500">
                                                            Seleccione un
                                                            cliente
                                                        </p>
                                                    )}
                                            </div>
                                        ) : (
                                            <div className="space-y-3 rounded-xl border bg-muted/30 p-4">
                                                <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-3">
                                                    <div className="grid gap-1">
                                                        <Label className="text-[10px] text-muted-foreground uppercase">
                                                            Nombre/Razón Social
                                                            <span className="text-red-500">
                                                                {' '}
                                                                *
                                                            </span>
                                                        </Label>
                                                        <Input
                                                            value={
                                                                data.cliente_nombre
                                                            }
                                                            onChange={(e) =>
                                                                setData(
                                                                    'cliente_nombre',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Ej: Juan Pérez"
                                                            className={`h-9 ${data.cliente_tipo === 'generico' && !data.cliente_nombre ? 'border-red-500 focus:border-red-500' : ''}`}
                                                        />
                                                        {data.cliente_tipo ===
                                                            'generico' &&
                                                            !data.cliente_nombre && (
                                                                <p className="text-xs text-red-500">
                                                                    El nombre es
                                                                    requerido
                                                                </p>
                                                            )}
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Label className="text-[10px] text-muted-foreground uppercase">
                                                            RUT (opcional)
                                                        </Label>
                                                        <Input
                                                            value={
                                                                data.cliente_rut
                                                            }
                                                            onChange={(e) =>
                                                                setData(
                                                                    'cliente_rut',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            onBlur={(e) =>
                                                                setData(
                                                                    'cliente_rut',
                                                                    formatRut(e.target.value)
                                                                )
                                                            }
                                                            placeholder="12.345.678-9"
                                                            className="h-9"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-3">
                                                    <div className="grid gap-1">
                                                        <Label className="text-[10px] text-muted-foreground uppercase">
                                                            Teléfono
                                                        </Label>
                                                        <Input
                                                            value={
                                                                data.cliente_telefono
                                                            }
                                                            onChange={(e) =>
                                                                setData(
                                                                    'cliente_telefono',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="+56 9 1234 5678"
                                                            className="h-9"
                                                        />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Label className="text-[10px] text-muted-foreground uppercase">
                                                            Dirección
                                                        </Label>
                                                        <Input
                                                            value={
                                                                data.cliente_direccion
                                                            }
                                                            onChange={(e) =>
                                                                setData(
                                                                    'cliente_direccion',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Av. Principal 123"
                                                            className="h-9"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                        <div className="grid gap-2">
                                            <Label>
                                                Fecha
                                                <span className="text-red-500">
                                                    {' '}
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                type="date"
                                                value={data.fecha}
                                                onChange={(e) =>
                                                    setData(
                                                        'fecha',
                                                        e.target.value,
                                                    )
                                                }
                                                className={
                                                    !data.fecha
                                                        ? 'border-red-500 focus:border-red-500'
                                                        : ''
                                                }
                                            />
                                            {!data.fecha && (
                                                <p className="text-xs text-red-500">
                                                    La fecha es requerida
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Estado</Label>
                                        <Select
                                            value={data.estado}
                                            onValueChange={(v) =>
                                                setData(
                                                    'estado',
                                                    v as
                                                    | 'pendiente'
                                                    | 'pagada'
                                                    | 'cancelada',
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="pendiente">
                                                    Pendiente
                                                </SelectItem>
                                                <SelectItem value="pagada">
                                                    Pagada
                                                </SelectItem>
                                                <SelectItem value="cancelada">
                                                    Cancelada
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {/*                                 <div className="grid gap-2">
                                        <Label>Moneda</Label>
                                        <Select
                                            value={data.currency}
                                            onValueChange={(v) => setData('currency', v)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {CURRENCY_OPTIONS.map((c) => (
                                                    <SelectItem key={c.value} value={c.value}>
                                                        {c.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div> */}

                                    <div className="grid gap-2">
                                        <Label>
                                            Almacenes de Origen
                                            <span className="text-red-500">
                                                {' '}
                                                *
                                            </span>
                                        </Label>
                                        <div className={`rounded-lg border p-3 space-y-2 ${data.almacen_ids.length === 0 ? 'border-red-500' : ''}`}>
                                            {almacenes.map((a) => {
                                                const selected = data.almacen_ids.includes(a.id);
                                                return (
                                                    <label
                                                        key={a.id}
                                                        className={`flex items-center gap-3 rounded-md px-3 py-2 cursor-pointer transition-colors ${selected ? 'bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-muted/50'}`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={selected}
                                                            onChange={() => {
                                                                const next = selected
                                                                    ? data.almacen_ids.filter((id) => id !== a.id)
                                                                    : [...data.almacen_ids, a.id];
                                                                setData('almacen_ids', next);
                                                            }}
                                                            className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary/30"
                                                        />
                                                        <span className="text-sm font-medium">{a.nombre}</span>
                                                    </label>
                                                );
                                            })}
                                            {almacenes.length === 0 && (
                                                <p className="text-sm text-muted-foreground">No hay almacenes activos</p>
                                            )}
                                        </div>
                                        {data.almacen_ids.length === 0 && (
                                            <p className="text-xs text-red-500">
                                                Seleccione al menos un almacén
                                            </p>
                                        )}
                                    </div>

                                    <div className="overflow-x-auto rounded-lg border p-3 md:overflow-visible md:border-0 md:p-0">
                                        <div className="mb-4 flex items-center justify-between">
                                            <Label className="text-base font-semibold">
                                                Productos
                                            </Label>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={addProducto}
                                            >
                                                <Plus className="mr-1 h-4 w-4" />
                                                Agregar Producto
                                            </Button>
                                        </div>

                                        {data.productos.length === 0 ? (
                                            <p className="py-4 text-center text-sm text-gray-500">
                                                No hay productos agregados. Haga
                                                clic en "Agregar Producto"
                                            </p>
                                        ) : (
                                            <div className="space-y-3">
                                                {data.productos.length ===
                                                    0 && (
                                                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-center">
                                                            <p className="text-sm text-red-600">
                                                                Agregue al menos un
                                                                producto para
                                                                continuar
                                                            </p>
                                                        </div>
                                                    )}
                                                {data.productos.map(
                                                    (producto, index) => {
                                                        const prod =
                                                            productos.find(
                                                                (p) =>
                                                                    p.id ===
                                                                    Number(
                                                                        producto.producto_id,
                                                                    ),
                                                            );
                                                        const tieneError =
                                                            !producto.producto_id ||
                                                            !producto.cantidad ||
                                                            producto.cantidad <=
                                                            0;
                                                        const precio =
                                                            Math.round(
                                                                producto.precio_unitario ||
                                                                prod?.precio_venta ||
                                                                0,
                                                            );
                                                        const subtotalItem =
                                                            Math.round(
                                                                producto.cantidad *
                                                                precio,
                                                            );

                                                        return (
                                                            <div
                                                                key={index}
                                                                className={`grid grid-cols-1 items-end gap-3 rounded-md border border-dashed p-3 md:grid-cols-12 md:border-0 md:p-0 ${tieneError ? 'ring-2 ring-red-500' : ''}`}
                                                            >
                                                                <div className="md:col-span-5">
                                                                    <Label className="md:text-xs">
                                                                        Producto
                                                                    </Label>
                                                                    <Select
                                                                        value={
                                                                            producto.producto_id
                                                                        }
                                                                        onValueChange={(
                                                                            v,
                                                                        ) =>
                                                                            updateProducto(
                                                                                index,
                                                                                'producto_id',
                                                                                v,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            data.almacen_ids.length === 0
                                                                        }
                                                                    >
                                                                        <SelectTrigger>
                                                                            <SelectValue>
                                                                                {data.almacen_ids.length === 0
                                                                                    ? 'Seleccione almacén(es) primero'
                                                                                    : (productos.find(
                                                                                        (
                                                                                            p,
                                                                                        ) =>
                                                                                            p.id ===
                                                                                            Number(
                                                                                                producto.producto_id,
                                                                                            ),
                                                                                    )
                                                                                        ?.nombre ||
                                                                                        'Seleccionar')}
                                                                            </SelectValue>
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {productosFiltrados.map(
                                                                                (
                                                                                    p,
                                                                                ) => {
                                                                                    const stockItems = p.inventarios
                                                                                        ?.filter((inv) => data.almacen_ids.includes(inv.almacen_id)) || [];
                                                                                    const totalStock = stockItems.reduce((sum, inv) => sum + (inv.cantidad || 0), 0);
                                                                                    const stockStr = stockItems
                                                                                        .map((inv) => {
                                                                                            const alm = almacenes.find((a) => a.id === inv.almacen_id);
                                                                                            return `${alm?.nombre || '?'}: ${Math.round(inv.cantidad || 0)}`;
                                                                                        })
                                                                                        .join(' | ');
                                                                                    return (
                                                                                        <SelectItem
                                                                                            key={
                                                                                                p.id
                                                                                            }
                                                                                            value={p.id.toString()}
                                                                                        >
                                                                                            <span className="flex flex-col gap-0.5">
                                                                                                <span>
                                                                                                    {p.nombre}{' '}
                                                                                                    <span className="text-muted-foreground">
                                                                                                        ({p.codigo})
                                                                                                    </span>
                                                                                                    {totalStock <= 0 && (
                                                                                                        <span className="ml-1 text-[10px] font-bold text-red-500">
                                                                                                            {totalStock < 0 ? 'SIN STOCK' : 'SIN STOCK'}
                                                                                                        </span>
                                                                                                    )}
                                                                                                </span>
                                                                                                {stockStr && (
                                                                                                    <span className={`text-[10px] font-mono ${totalStock < 0 ? 'text-red-500' : totalStock === 0 ? 'text-orange-500' : 'text-muted-foreground'}`}>
                                                                                                        {stockStr}
                                                                                                    </span>
                                                                                                )}
                                                                                            </span>
                                                                                        </SelectItem>
                                                                                    );
                                                                                },
                                                                            )}
                                                                            {productosFiltrados.length === 0 && data.almacen_ids.length > 0 && (
                                                                                <div className="px-3 py-4 text-center text-sm text-muted-foreground">
                                                                                    No hay productos en los almacenes seleccionados
                                                                                </div>
                                                                            )}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                                <div className="grid grid-cols-1 gap-2 md:col-span-6 md:grid-cols-12 md:gap-2">
                                                                    <div className="md:col-span-4">
                                                                        <Label className="md:text-xs">
                                                                            Cant.
                                                                        </Label>
                                                                        <div className="flex items-center gap-1">
                                                                            <Input
                                                                                type="number"
                                                                                min="1"
                                                                                step="1"
                                                                                value={
                                                                                    producto.modoGramos
                                                                                        ? Math.round((producto.cantidad || 0) * 1000)
                                                                                        : (producto.cantidad || '')
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) => {
                                                                                    const raw = parseInt(e.target.value, 10) || 0;
                                                                                    const val = producto.modoGramos
                                                                                        ? Math.round(raw) / 1000
                                                                                        : raw;
                                                                                    updateProducto(
                                                                                        index,
                                                                                        'cantidad',
                                                                                        val,
                                                                                    );
                                                                                }}
                                                                            />
                                                                            <span className="text-[9px] font-bold text-muted-foreground whitespace-nowrap">
                                                                                {producto.modoGramos
                                                                                    ? ((prod as any)?.unidad_medida === 'lt' ? 'ml' : 'g')
                                                                                    : ((prod as any)?.unidad_medida === 'kg' ? 'kg' : (prod as any)?.unidad_medida === 'lt' ? 'L' : 'u')}
                                                                            </span>
                                                                            {prod && ((prod as any)?.unidad_medida === 'kg' || (prod as any)?.unidad_medida === 'lt') && (
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => updateProducto(index, 'modoGramos', !producto.modoGramos)}
                                                                                    className="h-6 px-1.5 rounded bg-muted text-[9px] font-bold hover:bg-muted/80 transition-colors"
                                                                                    title={producto.modoGramos ? 'Cambiar a unidad completa' : 'Cambiar a gramos/ml'}
                                                                                >
                                                                                    {producto.modoGramos
                                                                                        ? ((prod as any)?.unidad_medida === 'lt' ? 'L' : 'kg')
                                                                                        : ((prod as any)?.unidad_medida === 'lt' ? 'ml' : 'g')}
                                                                                </button>
                                                                            )}
                                                                        </div>
                                                                        {prod && (() => {
                                                                            const stockTotal = prod.inventarios
                                                                                ?.filter((inv) => data.almacen_ids.includes(inv.almacen_id))
                                                                                .reduce((sum, inv) => sum + (inv.cantidad || 0), 0) ?? 0;
                                                                            const solicitado = producto.cantidad || 0;
                                                                            const sinStock = stockTotal <= 0;
                                                                            const insuficiente = solicitado > stockTotal && stockTotal > 0;
                                                                            if (!sinStock && !insuficiente) return null;
                                                                            return (
                                                                                <span className={`mt-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold ${sinStock ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'}`}>
                                                                                    {sinStock ? 'Sin stock' : `Stock: ${Math.round(stockTotal)} (necesita ${solicitado})`}
                                                                                </span>
                                                                            );
                                                                        })()}
                                                                    </div>
                                                                    <div className="md:col-span-4">
                                                                        <Label className="md:text-xs">
                                                                            Precio
                                                                        </Label>
                                                                        <Input
                                                                            type="number"
                                                                            min="0"
                                                                            step="1"
                                                                            value={
                                                                                producto.precio_unitario
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateProducto(
                                                                                    index,
                                                                                    'precio_unitario',
                                                                                    parseFloat(
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    ) ||
                                                                                    0,
                                                                                )
                                                                            }
                                                                        />
                                                                    </div>
                                                                    <div className="md:col-span-4">
                                                                        <Label className="md:text-xs">
                                                                            Subt.
                                                                        </Label>
                                                                        <Input
                                                                            value={formatCurrency(
                                                                                subtotalItem,
                                                                            )}
                                                                            disabled
                                                                            className="h-9 bg-gray-50"
                                                                        />
                                                                        {prod &&
                                                                            ((
                                                                                prod as any
                                                                            )
                                                                                .medida_pesable ||
                                                                                (
                                                                                    prod as any
                                                                                )
                                                                                    .unidad_medida !==
                                                                                'unidad' ||
                                                                                (
                                                                                    prod as any
                                                                                )
                                                                                    .peso_base >
                                                                                0) && (
                                                                                <div className="mt-1 flex justify-between px-1 text-[10px] font-bold text-blue-600">
                                                                                    <span>
                                                                                        Total{' '}
                                                                                        {(
                                                                                            prod as any
                                                                                        )
                                                                                            .unidad_medida ===
                                                                                            'lt'
                                                                                            ? 'Litros'
                                                                                            : 'Kg'}

                                                                                        :
                                                                                    </span>
                                                                                    <span>
                                                                                        {(() => {
                                                                                            const contenido =
                                                                                                Number(
                                                                                                    (
                                                                                                        prod as any
                                                                                                    )
                                                                                                        .contenido_por_unidad,
                                                                                                ) ||
                                                                                                1;
                                                                                            const tara =
                                                                                                Number(
                                                                                                    (
                                                                                                        prod as any
                                                                                                    )
                                                                                                        .peso_base,
                                                                                                ) ||
                                                                                                0;
                                                                                            const total =
                                                                                                producto.cantidad *
                                                                                                contenido +
                                                                                                producto.cantidad *
                                                                                                tara;
                                                                                            return total.toFixed(
                                                                                                2,
                                                                                            );
                                                                                        })()}{' '}
                                                                                        {(
                                                                                            prod as any
                                                                                        )
                                                                                            .unidad_medida ===
                                                                                            'lt'
                                                                                            ? 'L'
                                                                                            : 'Kg'}
                                                                                    </span>
                                                                                </div>
                                                                            )}
                                                                    </div>
                                                                    {(prod as any)?.envase_retornable == true && (
                                                                        <div className="mt-2 flex items-center gap-3 rounded-md border border-amber-200 bg-amber-50 p-2 md:col-span-12">
                                                                            <Label className="text-xs font-bold text-amber-800 whitespace-nowrap">
                                                                                Envases retornados
                                                                            </Label>
                                                                            <div className="flex items-center gap-2">
                                                                                <input
                                                                                    type="number"
                                                                                    min={0}
                                                                                    max={producto.cantidad ?? 0}
                                                                                    className="h-8 w-20 rounded-md border border-amber-300 bg-white px-2 text-sm text-amber-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                                                                    placeholder="0"
                                                                                    value={(producto as any).cantidad_retornada ?? ''}
                                                                                    onChange={(e) =>
                                                                                        updateProducto(
                                                                                            index,
                                                                                            'cantidad_retornada',
                                                                                            (e.target.value === '' ? null as any : Number(e.target.value)) as any
                                                                                        )
                                                                                    }
                                                                                />
                                                                                <span className="text-xs text-amber-700">
                                                                                    de {producto.cantidad} vendidos
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                                <div className="flex justify-end md:col-span-1">
                                                                    <Button
                                                                        type="button"
                                                                        variant="destructive"
                                                                        size="icon"
                                                                        onClick={() =>
                                                                            removeProducto(
                                                                                index,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-2">
                                        <div className="flex items-center justify-between space-x-2">
                                            <div className="flex flex-col gap-1">
                                                <Label className="font-semibold text-gray-700">
                                                    Impuesto IVA (19%)
                                                </Label>
                                                <span className="text-[10px] text-gray-500">
                                                    ¿Incluye cálculo de
                                                    impuestos en el total?
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className={`text-[10px] font-bold uppercase ${data.incluye_iva ? 'text-green-600' : 'text-gray-400'}`}
                                                >
                                                    {data.incluye_iva
                                                        ? 'Activado'
                                                        : 'Desactivado'}
                                                </span>
                                                <input
                                                    type="checkbox"
                                                    className="h-5 w-10 cursor-pointer appearance-none rounded-full bg-gray-200 transition-colors checked:bg-indigo-600"
                                                    checked={data.incluye_iva}
                                                    onChange={(e) =>
                                                        setData(
                                                            'incluye_iva',
                                                            e.target.checked,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 gap-3">
                                            <Label className="font-semibold text-gray-700">
                                                Descuento Aplicado
                                            </Label>
                                            <div className="flex gap-2">
                                                <Select
                                                    value={data.tipo_descuento}
                                                    onValueChange={(v) =>
                                                        setData(
                                                            'tipo_descuento',
                                                            v as
                                                            | 'monto'
                                                            | 'porcentaje',
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="w-[110px]">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="monto">
                                                            Monto $
                                                        </SelectItem>
                                                        <SelectItem value="porcentaje">
                                                            Porc. %
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    value={data.valor_descuento}
                                                    onChange={(e) =>
                                                        setData(
                                                            'valor_descuento',
                                                            parseFloat(
                                                                e.target.value,
                                                            ) || 0,
                                                        )
                                                    }
                                                    className="flex-1"
                                                    placeholder={
                                                        data.tipo_descuento ===
                                                            'monto'
                                                            ? 'Ej: 5000'
                                                            : 'Ej: 10'
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="rounded-lg border bg-gray-50 p-4">
                                        <div className="flex justify-between text-sm">
                                            <span>Subtotal Bruto:</span>
                                            <span className="font-medium">
                                                {formatCurrency(
                                                    calcularTotales.subtotal,
                                                )}
                                            </span>
                                        </div>
                                        {calcularTotales.montoDescuento > 0 && (
                                            <div className="mt-1 flex justify-between text-sm text-red-600">
                                                <span>
                                                    Descuento (
                                                    {data.tipo_descuento ===
                                                        'porcentaje'
                                                        ? `${data.valor_descuento}%`
                                                        : 'Monto'}
                                                    ):
                                                </span>
                                                <span className="font-medium">
                                                    -
                                                    {formatCurrency(
                                                        calcularTotales.montoDescuento,
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                        <div className="mt-1 flex justify-between border-t border-dashed pt-1 text-sm">
                                            <span>Base Imponible:</span>
                                            <span className="font-medium">
                                                {formatCurrency(
                                                    calcularTotales.baseImponible,
                                                )}
                                            </span>
                                        </div>
                                        <div className="mt-1 flex justify-between text-sm">
                                            <span>IVA (19%):</span>
                                            <span
                                                className={`font-medium ${!data.incluye_iva ? 'text-gray-400 line-through' : ''}`}
                                            >
                                                {formatCurrency(
                                                    calcularTotales.iva,
                                                )}
                                            </span>
                                        </div>
                                        <div className="mt-2 flex justify-between border-t border-indigo-200 pt-2 text-base font-bold text-indigo-900">
                                            <span>Total Neto a Pagar:</span>
                                            <span className="text-xl">
                                                {formatCurrency(
                                                    calcularTotales.total,
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Notas / Descripción</Label>
                                        <textarea
                                            value={data.notas}
                                            onChange={(e) =>
                                                setData('notas', e.target.value)
                                            }
                                            className="min-h-[80px] w-full rounded-lg border bg-background p-3 text-sm"
                                            placeholder="Agregar notas o descripción adicional..."
                                        />
                                    </div>
                                </div>
                            </div>
                            <DialogFooter className="shrink-0 border-t bg-background p-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleClose}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        !data.numero_factura ||
                                        !data.fecha ||
                                        data.productos.length === 0 ||
                                        (data.cliente_tipo === 'existente' &&
                                            !data.cliente_id) ||
                                        (data.cliente_tipo === 'generico' &&
                                            !data.cliente_nombre) ||
                                        data.productos.some(
                                            (p: any) =>
                                                !p.producto_id ||
                                                !p.cantidad ||
                                                p.cantidad <= 0,
                                        )
                                    }
                                >
                                    <Check className="mr-2 h-4 w-4" />
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Modal de Vista Premium (Solo lectura) */}
                <Dialog open={verModalOpen} onOpenChange={setVerModalOpen}>
                    <DialogContent className="max-h-[90vh] max-w-[95vw] overflow-y-auto rounded-2xl border-none bg-white p-0 shadow-2xl md:max-w-3xl">
                        <DialogHeader className="relative overflow-hidden px-6 pt-10 pb-16 md:px-8 md:pt-10 md:pb-20">
                            <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-indigo-700 via-blue-800 to-indigo-950 opacity-100" />
                            <div className="absolute top-0 right-0 p-6 text-white opacity-20 md:p-8">
                                <Eye className="h-16 w-16 rotate-12 md:h-24 md:w-24" />
                            </div>

                            <div className="relative z-10 flex flex-col gap-1 text-white">
                                <Badge className="w-fit border-none bg-white/20 px-2 py-0.5 text-[8px] font-bold tracking-widest text-white uppercase md:px-3 md:py-1 md:text-[10px]">
                                    Detalle de Venta
                                </Badge>
                                <DialogTitle className="text-2xl font-black tracking-tight text-white md:text-3xl lg:text-4xl">
                                    Venta #{ventaSeleccionada?.numero_factura}
                                </DialogTitle>
                                <DialogDescription className="text-base font-medium text-blue-100/80 md:text-lg">
                                    Información administrativa y financiera del
                                    registro.
                                </DialogDescription>
                            </div>
                        </DialogHeader>

                        {ventaSeleccionada && (
                            <>
                                <div className="relative z-20 px-4 pb-6 md:px-8 md:pb-6">
                                    <div className="grid grid-cols-2 gap-2 md:grid-cols-3 md:gap-4 lg:grid-cols-5">
                                        {[
                                            {
                                                label: 'Cliente',
                                                val: ventaSeleccionada.cliente
                                                    ?.nombre,
                                                color: 'border-blue-200 bg-blue-50 text-blue-800',
                                            },
                                            {
                                                label: 'Bodega',
                                                val:
                                                    ventaSeleccionada.almacen_nombre ||
                                                    'Sin bodega',
                                                color: 'border-purple-200 bg-purple-50 text-purple-800',
                                            },
                                            {
                                                label: 'Fecha',
                                                val: formatDateCLP(
                                                    ventaSeleccionada.fecha,
                                                ),
                                                color: 'border-gray-200 bg-gray-50 text-gray-800',
                                            },
                                            {
                                                label: 'Estado',
                                                val: ventaSeleccionada.estado.toUpperCase(),
                                                color:
                                                    ventaSeleccionada.estado ===
                                                        'pagada'
                                                        ? 'border-green-200 bg-green-50 text-green-800'
                                                        : ventaSeleccionada.estado ===
                                                            'cancelada'
                                                            ? 'border-red-200 bg-red-50 text-red-800'
                                                            : 'border-amber-200 bg-amber-50 text-amber-800',
                                            },
                                            {
                                                label: 'Total',
                                                val: formatCurrency(
                                                    ventaSeleccionada.total,
                                                ),
                                                color: 'border-indigo-200 bg-indigo-50 text-indigo-800',
                                            },
                                        ].map((item, idx) => (
                                            <div
                                                key={idx}
                                                className={`rounded-lg border p-2 md:rounded-xl md:p-4 ${item.color}`}
                                            >
                                                <p className="mb-0.5 text-[8px] font-extrabold tracking-wider uppercase opacity-70 md:text-[10px]">
                                                    {item.label}
                                                </p>
                                                <p className="truncate text-xs font-semibold md:text-sm">
                                                    {item.val}
                                                </p>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                                        <div className="lg:col-span-2">
                                            <Card className="border-none bg-gray-50/50 shadow-sm">
                                                <CardHeader className="border-b border-gray-100 pb-2 md:pb-3">
                                                    <CardTitle className="text-sm font-bold text-gray-800 md:text-base">
                                                        Productos Detallados
                                                    </CardTitle>
                                                </CardHeader>
                                                <CardContent className="p-0">
                                                    <div className="w-full overflow-x-auto">
                                                        <table className="w-full text-xs md:text-sm">
                                                            <thead>
                                                                <tr className="border-b bg-gray-100/50 text-[9px] font-bold text-gray-500 uppercase md:text-[10px]">
                                                                    <th className="px-3 py-2 text-left md:px-5 md:py-3">
                                                                        Producto
                                                                    </th>
                                                                    <th className="px-3 py-2 text-center md:px-5 md:py-3">
                                                                        Cant.
                                                                    </th>
                                                                    <th className="px-3 py-2 text-right md:px-5 md:py-3">
                                                                        Unitario
                                                                    </th>
                                                                    <th className="px-3 py-2 text-right md:px-5 md:py-3">
                                                                        Subtotal
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody className="divide-y divide-gray-100 bg-white">
                                                                {ventaSeleccionada.detalle_ventas?.map(
                                                                    (
                                                                        detalle,
                                                                        i,
                                                                    ) => (
                                                                        <tr
                                                                            key={
                                                                                i
                                                                            }
                                                                            className="group transition-colors hover:bg-blue-50/30"
                                                                        >
                                                                            <td className="px-3 py-2 md:px-5 md:py-3">
                                                                                <span className="font-bold text-gray-700">
                                                                                    {detalle
                                                                                        .producto
                                                                                        ?.nombre ||
                                                                                        'Producto'}
                                                                                </span>
                                                                            </td>
                                                                            <td className="px-3 py-2 text-center md:px-5 md:py-3">
                                                                                {
                                                                                    detalle.cantidad
                                                                                }
                                                                            </td>
                                                                            <td className="px-3 py-2 text-right md:px-5 md:py-3">
                                                                                {formatCurrency(
                                                                                    detalle.precio_unitario,
                                                                                )}
                                                                            </td>
                                                                            <td className="px-3 py-2 text-right font-bold text-indigo-600 md:px-5 md:py-3">
                                                                                {formatCurrency(
                                                                                    detalle.subtotal,
                                                                                )}
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </CardContent>
                                            </Card>

                                            {ventaSeleccionada.notas && (
                                                <Card className="mt-4 border-none bg-amber-50/30 shadow-sm">
                                                    <CardHeader className="border-b border-amber-100 px-4 py-2 md:px-5 md:py-3">
                                                        <CardTitle className="text-[10px] font-black tracking-widest text-amber-700 uppercase md:text-xs">
                                                            Observaciones
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent className="p-4 text-xs leading-relaxed font-medium text-amber-900/70 italic md:text-sm">
                                                        {
                                                            ventaSeleccionada.notas
                                                        }
                                                    </CardContent>
                                                </Card>
                                            )}
                                        </div>

                                        <div className="flex flex-col gap-4">
                                            <Card className="border-none bg-indigo-950 text-white shadow-xl">
                                                <CardHeader className="pb-1 md:pb-2">
                                                    <CardTitle className="text-[10px] font-extrabold tracking-widest text-blue-300 uppercase opacity-80 md:text-xs">
                                                        Liquidación Final
                                                    </CardTitle>
                                                </CardHeader>
                                                <CardContent className="flex flex-col gap-2 md:gap-4">
                                                    <div className="flex items-center justify-between border-b border-white/10 pb-2 text-xs md:pb-3 md:text-sm">
                                                        <span className="font-medium text-blue-100/60">
                                                            Subtotal
                                                        </span>
                                                        <span className="font-mono text-blue-50">
                                                            {formatCurrency(
                                                                ventaSeleccionada.subtotal,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center justify-between border-b border-white/10 pb-2 text-xs md:pb-3 md:text-sm">
                                                        <span className="font-medium text-blue-100/60">
                                                            IVA (19%)
                                                        </span>
                                                        <span
                                                            className={`font-mono text-blue-50 ${!ventaSeleccionada.incluye_iva ? 'line-through opacity-50' : ''}`}
                                                        >
                                                            {formatCurrency(
                                                                ventaSeleccionada.iva,
                                                            )}
                                                        </span>
                                                    </div>
                                                    {ventaSeleccionada.monto_descuento >
                                                        0 && (
                                                            <div className="flex items-center justify-between border-b border-white/10 pb-2 text-xs md:pb-3 md:text-sm">
                                                                <span className="font-medium text-red-300">
                                                                    Descuento
                                                                </span>
                                                                <span className="font-mono text-red-100">
                                                                    -
                                                                    {formatCurrency(
                                                                        ventaSeleccionada.monto_descuento,
                                                                    )}
                                                                </span>
                                                            </div>
                                                        )}
                                                    <div className="py-2 text-center md:py-4">
                                                        <span className="text-[9px] font-black tracking-tighter text-white/40 uppercase md:text-[10px]">
                                                            Monto Total
                                                        </span>
                                                        <p className="text-2xl font-black tracking-tighter text-white md:text-3xl lg:text-4xl">
                                                            {formatCurrency(
                                                                ventaSeleccionada.total,
                                                            )}
                                                        </p>
                                                    </div>
                                                </CardContent>
                                            </Card>

                                            {/*              {ventaSeleccionada.almacenes_data && ventaSeleccionada.almacenes_data.length > 0 && (
                                                <Card className="border shadow-sm">
                                                    <CardHeader className="border-b bg-gray-50 py-2 md:py-3">
                                                        <CardTitle className="flex items-center gap-2 text-[10px] font-black tracking-widest text-gray-700 uppercase md:text-xs">
                                                            <Warehouse className="h-3 w-3 text-purple-500 md:h-4 md:w-4" />
                                                            Desglose por Almacén
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent className="p-0">
                                                        <div className="overflow-x-auto">
                                                            <table className="w-full text-xs md:text-sm">
                                                                <thead>
                                                                    <tr className="border-b bg-gray-50/50">
                                                                        <th className="px-3 py-2 text-left md:px-5 md:py-3">
                                                                            Almacén
                                                                        </th>
                                                                        <th className="px-3 py-2 text-right md:px-5 md:py-3">
                                                                            Cantidad
                                                                        </th>
                                                                        <th className="px-3 py-2 text-right md:px-5 md:py-3">
                                                                            % del Total
                                                                        </th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody className="divide-y divide-gray-100 bg-white">
                                                                    {ventaSeleccionada.almacenes_data.map((alm, i) => {
                                                                        const totalCantidad = ventaSeleccionada.almacenes_data!.reduce(
                                                                            (sum, a) => sum + Number(a.pivot.cantidad_descontada || 0),
                                                                            0,
                                                                        );
                                                                        const pct = totalCantidad > 0
                                                                            ? ((Number(alm.pivot.cantidad_descontada || 0) / totalCantidad) * 100)
                                                                            : 0;
                                                                        return (
                                                                            <tr key={alm.id} className="group transition-colors hover:bg-blue-50/30">
                                                                                <td className="px-3 py-2 md:px-5 md:py-3">
                                                                                    <span className="font-bold text-gray-700">
                                                                                        {alm.nombre}
                                                                                    </span>
                                                                                </td>
                                                                                <td className="px-3 py-2 text-right font-mono md:px-5 md:py-3">
                                                                                    {Number(alm.pivot.cantidad_descontada || 0).toFixed(3)}
                                                                                </td>
                                                                                <td className="px-3 py-2 text-right md:px-5 md:py-3">
                                                                                    <div className="flex items-center justify-end gap-2">
                                                                                        <div className="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200 md:w-24">
                                                                                            <div
                                                                                                className="h-full rounded-full bg-purple-500 transition-all"
                                                                                                style={{ width: `${pct}%` }}
                                                                                            />
                                                                                        </div>
                                                                                        <span className="font-mono text-xs font-semibold text-gray-600">
                                                                                            {pct.toFixed(1)}%
                                                                                        </span>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        );
                                                                    })}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            )} */}

                                            <Card className="border shadow-sm">
                                                <CardHeader className="border-b bg-gray-50 py-2 md:py-3">
                                                    <CardTitle className="text-[10px] font-bold text-gray-600 md:text-xs">
                                                        Soporte Cliente
                                                    </CardTitle>
                                                </CardHeader>
                                                <CardContent className="p-3 md:p-4">
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex-1 rounded-lg bg-gray-100 p-2">
                                                            <p className="mb-0.5 text-[9px] font-black text-gray-400 uppercase md:text-[10px]">
                                                                Nombre
                                                            </p>
                                                            <p className="truncate text-xs font-bold text-gray-700 md:text-sm">
                                                                {ventaSeleccionada.cliente?.nombre}
                                                            </p>
                                                        </div>

                                                        {/* Botón directo de WhatsApp con icono garantizado */}
                                                        {ventaSeleccionada.cliente?.telefono && (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                className="h-10 w-10 shrink-0 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm"
                                                                onClick={() => {
                                                                    const phone = ventaSeleccionada.cliente?.telefono?.replace(/\D/g, "");
                                                                    const mensaje = encodeURIComponent(
                                                                        `Hola ${ventaSeleccionada.cliente?.nombre || ""}, te contactamos respecto a tu pedido.`
                                                                    );
                                                                    window.open(`https://wa.me/${phone}?text=${mensaje}`, "_blank");
                                                                }}
                                                                title="Enviar mensaje por WhatsApp"
                                                            >
                                                                <MessageCircle className="h-5 w-5 fill-white stroke-none" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter className="mt-4 flex flex-col gap-2 border-t bg-gray-50 p-4 sm:flex-row md:p-6">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className="w-full font-bold shadow-sm transition-all hover:bg-indigo-50 hover:text-indigo-600 active:scale-95 sm:w-auto"
                                            >
                                                <FileText className="mr-2 h-4 w-4" />
                                                Descargar PDF
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="w-56">
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    ventaSeleccionada &&
                                                    window.open(
                                                        `/ventas/${ventaSeleccionada.id}/download`,
                                                        '_blank',
                                                    )
                                                }
                                            >
                                                <FileText className="mr-2 h-4 w-4" />
                                                Formato SII (Formal)
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    ventaSeleccionada &&
                                                    window.open(
                                                        `/ventas/${ventaSeleccionada.id}/download-informal`,
                                                        '_blank',
                                                    )
                                                }
                                            >
                                                <FileText className="mr-2 h-4 w-4" />
                                                Formato Simple (Informal)
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <Button
                                        variant="outline"
                                        onClick={() => setVerModalOpen(false)}
                                        className="w-full font-bold shadow-sm transition-all hover:bg-white hover:text-primary active:scale-95 sm:w-auto"
                                    >
                                        Cerrar
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
