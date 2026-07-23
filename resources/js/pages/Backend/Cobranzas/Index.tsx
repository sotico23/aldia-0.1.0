import { Head, useForm } from '@inertiajs/react';
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
} from 'lucide-react';
import { useState } from 'react';
import { useMemo } from 'react';
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
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ModalShow } from '@/components/ui/ModalShow';
import Pagination from '@/components/ui/Pagination';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatDateCLP, formatCurrency, getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Cobranza {
    id: number;
    factura_id: number | null;
    cliente_id: number | null;
    monto: number;
    fecha_pago: string | null;
    metodo_pago: string | null;
    referencia: string | null;
    estado: string;
    notas: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranzas', href: '/cobranzas' },
];

const estados = ['pendiente', 'pagada', 'vencida', 'cancelada'];
const metodos = ['efectivo', 'transferencia', 'cheque', 'tarjeta', 'deposito'];

export default function Index({
    cobranzas,
}: {
    cobranzas: {
        data: Cobranza[];
        links: any[];
        meta: { from: number; to: number; total: number };
    };
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Cobranza | null>(null);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [viewing, setViewing] = useState<Cobranza | null>(null);
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('finanzas.cobranzas.create');
    const canEdit = hasPermission('finanzas.cobranzas.edit');
    const canDelete = hasPermission('finanzas.cobranzas.delete');
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        factura_id: '' as string,
        cliente_id: '' as string,
        monto: 0,
        fecha_pago: '',
        metodo_pago: '',
        referencia: '',
        estado: 'pendiente',
        notas: '',
    });

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const cobranzasFiltradas = useMemo(() => {
        return cobranzas.data.filter((c: Cobranza) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !(c.referencia || '').toLowerCase().includes(busca) &&
                    !(c.notas || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && c.estado !== filtros.estado) return false;

            return true;
        });
    }, [cobranzas.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const handleView = (c: Cobranza) => {
        setViewing(c);
        setIsViewOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/cobranzas/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/cobranzas', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (c: Cobranza) => {
        setEditando(c);
        setData({
            factura_id: String(c.factura_id || ''),
            cliente_id: String(c.cliente_id || ''),
            monto: c.monto,
            fecha_pago: c.fecha_pago || '',
            metodo_pago: c.metodo_pago || '',
            referencia: c.referencia || '',
            estado: c.estado,
            notas: c.notas || '',
        });
        setIsOpen(true);
    };
    const handleNew = () => {
        reset();
        setData({
            factura_id: '',
            cliente_id: '',
            monto: 0,
            fecha_pago: getLocalDateString(),
            metodo_pago: 'efectivo',
            referencia: '',
            estado: 'pendiente',
            notas: '',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/cobranzas/${id}`);
    };
    const getEstadoBadge = (estado: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            pagada: 'bg-green-500',
            vencida: 'bg-red-500',
            cancelada: 'bg-gray-500',
        };
        return (
            <Badge className={colores[estado] || 'bg-gray-500'}>{estado}</Badge>
        );
    };

    return (
        <>
            <Head title="Cobranzas" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Cobranzas</h1>
                            <p className="text-muted-foreground">
                                Gestión de cobranzas
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canCreate && (
                                <Button onClick={handleNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                </Button>
                            )}
                            <BulkActions
                                baseUrl="/cobranzas"
                                filters={{}}
                                modelName="Cobranzas"
                            />
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Cobranzas</CardTitle>
                                    <CardDescription>
                                        {cobranzasFiltradas.length} registros
                                        encontrados
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
                                            placeholder="Buscar por referencia o notas..."
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
                                    value={filtros.estado}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            estado: e.target.value,
                                        })
                                    }
                                    className="flex h-9 min-w-[150px] rounded-md border bg-background px-3 py-1"
                                >
                                    <option value="">Todos los estados</option>
                                    {estados.map((e) => (
                                        <option key={e} value={e}>
                                            {e.toUpperCase()}
                                        </option>
                                    ))}
                                </select>
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
                                            <th className="py-3 text-left font-medium">
                                                Referencia / Notas
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Monto
                                            </th>
                                            <th className="py-3 text-left font-medium">
                                                Fecha / Método
                                            </th>
                                            <th className="py-3 text-center font-medium">
                                                Estado
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cobranzasFiltradas.map((c) => (
                                            <tr
                                                key={c.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-3">
                                                    <div className="font-medium">
                                                        {c.referencia ||
                                                            'Sin ref.'}
                                                    </div>
                                                    <div className="max-w-[200px] truncate text-[10px] text-muted-foreground">
                                                        {c.notas}
                                                    </div>
                                                </td>
                                                <td className="py-3 text-right font-bold text-green-600">
                                                    {formatCurrency(c.monto)}
                                                </td>
                                                <td className="py-3">
                                                    <div className="text-[10px] font-medium">
                                                        {formatDateCLP(
                                                            c.fecha_pago,
                                                        )}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground uppercase">
                                                        {c.metodo_pago}
                                                    </div>
                                                </td>
                                                <td className="py-3 text-center">
                                                    {getEstadoBadge(c.estado)}
                                                </td>
                                                <td className="py-3 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0"
                                                            onClick={() =>
                                                                handleView(c)
                                                            }
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
                                        {cobranzasFiltradas.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron cobranzas
                                                    con los filtros aplicados
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                                <Pagination
                                    links={cobranzas.links}
                                    meta={cobranzas.meta}
                                />
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {cobranzasFiltradas.map((c) => (
                                    <Card key={c.id} className="overflow-hidden">
                                        <CardContent className="space-y-3 p-4">
                                            <div className="flex items-center justify-between">
                                                <Badge variant="outline" className="text-xs">
                                                    {c.referencia || 'Sin ref.'}
                                                </Badge>
                                                {getEstadoBadge(c.estado)}
                                            </div>
                                            <p className="text-xl font-bold text-green-600">
                                                {formatCurrency(c.monto)}
                                            </p>
                                            <div className="space-y-1 text-sm">
                                                <p className="font-medium">{formatDateCLP(c.fecha_pago)}</p>
                                                <p className="text-xs uppercase text-muted-foreground">{c.metodo_pago}</p>
                                                {c.notas && <p className="truncate text-xs text-muted-foreground">{c.notas}</p>}
                                            </div>
                                            <div className="flex justify-end gap-1 border-t pt-2">
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleView(c)}>
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(c)}>
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
                                {cobranzasFiltradas.length === 0 && (
                                    <div className="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground">
                                        <DollarSign className="mb-3 h-12 w-12 opacity-20" />
                                        <p>No se encontraron cobranzas con los filtros aplicados</p>
                                    </div>
                                )}
                            </div>
                        )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-h-[85vh] max-w-[95vw] overflow-y-auto border-none p-0 shadow-xl md:max-w-lg">
                    <DialogHeader className="bg-gradient-to-r from-green-600 to-emerald-700 px-4 py-3 text-white md:px-6 md:py-4">
                        <DialogTitle className="text-lg font-black tracking-tight md:text-xl">
                            {editando ? 'Editar' : 'Nueva'} Cobranza
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="px-4 py-4 md:px-6">
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Monto *
                                    </Label>
                                    <Input
                                        type="number"
                                        step="1"
                                        value={data.monto}
                                        onChange={(e) =>
                                            setData(
                                                'monto',
                                                parseInt(e.target.value),
                                            )
                                        }
                                        required
                                        className="h-9"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Fecha Pago
                                    </Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_pago}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_pago',
                                                e.target.value,
                                            )
                                        }
                                        className="h-9"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Método
                                    </Label>
                                    <select
                                        value={data.metodo_pago}
                                        onChange={(e) =>
                                            setData(
                                                'metodo_pago',
                                                e.target.value,
                                            )
                                        }
                                        className="flex h-9 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                    >
                                        {metodos.map((m) => (
                                            <option key={m} value={m}>
                                                {m}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-bold uppercase">
                                        Estado
                                    </Label>
                                    <select
                                        value={data.estado}
                                        onChange={(e) =>
                                            setData('estado', e.target.value)
                                        }
                                        className="flex h-9 w-full rounded-md border bg-background px-2 py-1 text-sm"
                                    >
                                        {estados.map((e) => (
                                            <option key={e} value={e}>
                                                {e}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs font-bold uppercase">
                                    Referencia
                                </Label>
                                <Input
                                    value={data.referencia}
                                    onChange={(e) =>
                                        setData('referencia', e.target.value)
                                    }
                                    className="h-9"
                                />
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
                                    className="h-9"
                                />
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end gap-2 border-t pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                                className="rounded-full px-4"
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" className="rounded-full px-6">
                                Guardar
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <ModalShow
                isOpen={isViewOpen}
                setIsOpen={setIsViewOpen}
                item={viewing}
                title="Cobranza"
                badgeLabel="Detalle de Cobranza"
                colorScheme="green"
                quickStats={[
                    {
                        label: 'Estado',
                        val: viewing?.estado?.toUpperCase() || 'PENDIENTE',
                        colorScheme:
                            viewing?.estado === 'pagada' ? 'green' : 'rose',
                    },
                    {
                        label: 'Método',
                        val: viewing?.metodo_pago?.toUpperCase() || '---',
                        colorScheme: 'blue',
                    },
                    {
                        label: 'Fecha',
                        val: viewing?.fecha_pago
                            ? formatDateCLP(viewing.fecha_pago)
                            : '---',
                        colorScheme: 'purple',
                    },
                ]}
            >
                <Card className="border-none bg-gray-50/50 shadow-sm">
                    <CardHeader className="border-b border-gray-100 pb-3">
                        <CardTitle className="text-base font-bold text-gray-800">
                            Detalles
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 p-5">
                        <div className="col-span-2">
                            <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                Referencia
                            </Label>
                            <p className="font-medium">
                                {viewing?.referencia || 'Sin referencia'}
                            </p>
                        </div>
                        <div className="col-span-2">
                            <Label className="text-[10px] font-bold text-muted-foreground uppercase">
                                Notas
                            </Label>
                            <p className="font-medium">
                                {viewing?.notas || 'Sin notas'}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </ModalShow>
        </>
    );
}
