import { Head, useForm } from '@inertiajs/react';
import { LayoutGrid, List, Pencil, Plus, Trash2, Search, X } from 'lucide-react';
import { useState } from 'react';
import { useMemo } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateCLP, getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Tesoreria {
    id: number;
    tipo: string;
    monto: number;
    descripcion: string | null;
    cuenta_bancaria_id: number | null;
    fecha: string | null;
    referencia: string | null;
    categoria: string | null;
    estado: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tesorería', href: '/tesoreria' },
];

const estados = ['pendiente', 'aprobado', 'rechazado', 'cancelado'];
const tipos = ['ingreso', 'egreso', 'transferencia'];

export default function Index({ tesorerias }: { tesorerias: { data: Tesoreria[]; links: any[]; from?: number; to?: number; total?: number; meta?: any } }) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Tesoreria | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        tipo: 'ingreso',
        monto: 0,
        descripcion: '',
        cuenta_bancaria_id: '' as string,
        fecha: '',
        referencia: '',
        categoria: '',
        estado: 'pendiente',
    });

    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('finanzas.tesoreria.create');
    const canEdit = hasPermission('finanzas.tesoreria.edit');
    const canDelete = hasPermission('finanzas.tesoreria.delete');

    const [filtros, setFiltros] = useState({
        busqueda: '',
        tipo: '',
        estado: '',
    });

    const tesoreriasFiltradas = useMemo(() => {
        return tesorerias.data.filter((t) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !(t.descripcion || '').toLowerCase().includes(busca) &&
                    !(t.referencia || '').toLowerCase().includes(busca) &&
                    !(t.categoria || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.tipo && t.tipo !== filtros.tipo) return false;
            if (filtros.estado && t.estado !== filtros.estado) return false;

            return true;
        });
    }, [tesorerias.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            tipo: '',
            estado: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/tesoreria/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/tesoreria', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (t: Tesoreria) => {
        setEditando(t);
        setData({
            tipo: t.tipo,
            monto: t.monto,
            descripcion: t.descripcion || '',
            cuenta_bancaria_id: String(t.cuenta_bancaria_id || ''),
            fecha: t.fecha || '',
            referencia: t.referencia || '',
            categoria: t.categoria || '',
            estado: t.estado,
        });
        setIsOpen(true);
    };
    const handleNew = () => {
        reset();
        setData({
            tipo: 'ingreso',
            monto: 0,
            descripcion: '',
            cuenta_bancaria_id: '',
            fecha: getLocalDateString(),
            referencia: '',
            categoria: '',
            estado: 'pendiente',
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/tesoreria/${id}`);
    };
    const getEstadoBadge = (estado: string) => {
        const colores: Record<string, string> = {
            pendiente: 'bg-yellow-500',
            aprobado: 'bg-green-500',
            rechazado: 'bg-red-500',
            cancelado: 'bg-gray-500',
        };
        return (
            <Badge className={colores[estado] || 'bg-gray-500'}>{estado}</Badge>
        );
    };

    return (
        <>
            <Head title="Tesorería" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Tesorería</h1>
                            <p className="text-muted-foreground">
                                Gestión de tesorería
                            </p>
                        </div>
                        {canCreate && (
                            <Button onClick={handleNew}>
                                <Plus className="mr-2 h-4 w-4" /> Nuevo
                            </Button>
                        )}
                    </div>
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Movimientos de Tesorería</CardTitle>
                                    <CardDescription>
                                        {tesoreriasFiltradas.length} registros
                                        encontrados
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
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar descripción, ref o cat..."
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
                                    className="flex h-9 rounded-md border bg-background px-3 py-1 min-w-[130px]"
                                >
                                    <option value="">Todos los tipos</option>
                                    {tipos.map((t) => (
                                        <option key={t} value={t}>
                                            {t.toUpperCase()}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    value={filtros.estado}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            estado: e.target.value,
                                        })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-3 py-1 min-w-[130px]"
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
                                                Tipo / Categoría
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Monto
                                            </th>
                                            <th className="py-3 text-left font-medium">
                                                Fecha / Referencia
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
                                        {tesoreriasFiltradas.map((t) => (
                                            <tr
                                                key={t.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-3">
                                                    <div className="font-medium uppercase">
                                                        {t.tipo}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground truncate max-w-[150px]">
                                                        {t.categoria ||
                                                            'Sin cat.'}
                                                    </div>
                                                </td>
                                                <td
                                                    className={`py-3 text-right font-bold ${t.tipo === 'egreso'
                                                        ? 'text-red-600'
                                                        : 'text-green-600'
                                                        }`}
                                                >
                                                    {t.tipo === 'egreso'
                                                        ? '-'
                                                        : '+'}
                                                    {formatCurrency(
                                                        t.monto,
                                                    )}
                                                </td>
                                                <td className="py-3">
                                                    <div className="text-[10px] font-medium">
                                                        {formatDateCLP(
                                                            t.fecha,
                                                        )}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground truncate max-w-[150px]">
                                                        {t.referencia ||
                                                            '-'}
                                                    </div>
                                                </td>
                                                <td className="py-3 text-center">
                                                    {getEstadoBadge(
                                                        t.estado,
                                                    )}
                                                </td>
                                                <td className="py-3 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0"
                                                                onClick={() =>
                                                                    handleEdit(t)
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
                                                                        t.id,
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
                                        {tesoreriasFiltradas.length ===
                                            0 && (
                                                <tr>
                                                    <td
                                                        colSpan={5}
                                                        className="py-8 text-center text-muted-foreground"
                                                    >
                                                        No se encontraron
                                                        movimientos con los
                                                        filtros aplicados
                                                    </td>
                                                </tr>
                                            )}
                                    </tbody>
                                </table>
                                <Pagination links={tesorerias.links} meta={tesorerias.meta || tesorerias} />
                            </div>
                            ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {tesoreriasFiltradas.length === 0 ? (
                                    <div className="col-span-full flex flex-col items-center py-12 text-center text-muted-foreground">
                                        <Search className="mb-4 h-12 w-12 opacity-20" />
                                        <p className="font-medium">No se encontraron movimientos</p>
                                    </div>
                                ) : tesoreriasFiltradas.map((t) => (
                                    <Card key={t.id} className="overflow-hidden">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-center justify-between">
                                                <Badge variant="outline" className="uppercase text-xs">{t.tipo}</Badge>
                                                {getEstadoBadge(t.estado)}
                                            </div>
                                            <CardTitle className={`text-xl font-bold ${t.tipo === 'egreso' ? 'text-red-600' : 'text-green-600'}`}>
                                                {t.tipo === 'egreso' ? '-' : '+'}{formatCurrency(t.monto)}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2 pt-0">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Categoría</span>
                                                <span className="font-medium">{t.categoria || 'Sin cat.'}</span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Fecha</span>
                                                <span>{formatDateCLP(t.fecha)}</span>
                                            </div>
                                            {t.referencia && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">Referencia</span>
                                                    <span className="truncate max-w-[150px]">{t.referencia}</span>
                                                </div>
                                            )}
                                            <div className="flex justify-end gap-1 border-t pt-2">
                                                {canEdit && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(t)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(t.id)}>
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-lg md:max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nuevo'} Movimiento
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Tipo *</Label>
                                    <select
                                        value={data.tipo}
                                        onChange={(e) =>
                                            setData('tipo', e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                        required
                                    >
                                        {tipos.map((t) => (
                                            <option key={t} value={t}>
                                                {t}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Monto *</Label>
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
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Fecha</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha}
                                        onChange={(e) =>
                                            setData('fecha', e.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Categoría</Label>
                                    <Input
                                        value={data.categoria}
                                        onChange={(e) =>
                                            setData('categoria', e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Descripción</Label>
                                <Input
                                    value={data.descripcion}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Referencia</Label>
                                <Input
                                    value={data.referencia}
                                    onChange={(e) =>
                                        setData('referencia', e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Estado</Label>
                                <select
                                    value={data.estado}
                                    onChange={(e) =>
                                        setData('estado', e.target.value)
                                    }
                                    className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                >
                                    {estados.map((e) => (
                                        <option key={e} value={e}>
                                            {e}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit">Guardar</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
