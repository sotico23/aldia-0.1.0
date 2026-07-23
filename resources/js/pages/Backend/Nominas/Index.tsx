import { Head, useForm } from '@inertiajs/react';
import { LayoutGrid, List, Pencil, Plus, Trash2, Search, X } from 'lucide-react';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Nomina {
    id: number;
    periodo: string | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    total_bruto: number;
    total_deducciones: number;
    total_neto: number;
    estado: string;
    notas: string | null;
    detalles?: any[] | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Nómina', href: '/nominas' },
];

const estados = ['borrador', 'procesada', 'pagada'];

export default function Index({ nominas }: { nominas: { data: Nomina[]; links: any[]; from?: number; to?: number; total?: number; meta?: any } }) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Nomina | null>(null);
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        periodo: '',
        fecha_inicio: '',
        fecha_fin: '',
        total_bruto: 0,
        total_deducciones: 0,
        total_neto: 0,
        estado: 'borrador',
        notas: '',
        detalles: [] as any[],
    });

    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('rrhh.nominas.create');
    const canEdit = hasPermission('rrhh.nominas.edit');
    const canDelete = hasPermission('rrhh.nominas.delete');

    const [filtros, setFiltros] = useState({
        busqueda: '',
        estado: '',
    });

    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const nominasFiltradas = useMemo(() => {
        return nominas.data.filter((n) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !(n.periodo || '').toLowerCase().includes(busca) &&
                    !(n.notas || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.estado && n.estado !== filtros.estado) return false;

            return true;
        });
    }, [nominas.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            estado: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando)
            put(`/nominas/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        else
            post('/nominas', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
    };

    const handleEdit = (n: Nomina) => {
        setEditando(n);
        setData({
            periodo: n.periodo || '',
            fecha_inicio: n.fecha_inicio || '',
            fecha_fin: n.fecha_fin || '',
            total_bruto: n.total_bruto,
            total_deducciones: n.total_deducciones,
            total_neto: n.total_neto,
            estado: n.estado,
            notas: n.notas || '',
            detalles: n.detalles || [],
        });
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setData({
            periodo: '',
            fecha_inicio: '',
            fecha_fin: '',
            total_bruto: 0,
            total_deducciones: 0,
            total_neto: 0,
            estado: 'borrador',
            notas: '',
            detalles: [],
        });
        setEditando(null);
        setIsOpen(true);
    };

    const calcularNominas = async () => {
        if (!data.periodo) {
            alert('Ingrese el período primero (ej: 2026-05)');
            return;
        }
        if ((data.fecha_inicio && !data.fecha_fin) || (!data.fecha_inicio && data.fecha_fin)) {
            alert('Debe especificar ambas fechas (inicio y fin) o ninguna para calcular el mes completo.');
            return;
        }
        try {
            let url = `/nominas/calcular?periodo=${data.periodo}`;
            if (data.fecha_inicio && data.fecha_fin) {
                url += `&fecha_inicio=${data.fecha_inicio}&fecha_fin=${data.fecha_fin}`;
            }
            const res = await fetch(url);
            const calculos = await res.json();
            const sumBruto = calculos.reduce((acc: number, curr: any) => acc + curr.sueldo_proporcional, 0);
            setData(prev => ({
                ...prev,
                detalles: calculos,
                total_bruto: sumBruto,
                total_neto: sumBruto - prev.total_deducciones
            }));
        } catch (error) {
            console.error('Error calculando:', error);
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/nominas/${id}`);
    };

    const getEstadoBadge = (e: string) => {
        const colores: Record<string, string> = {
            borrador: 'bg-gray-500',
            procesada: 'bg-blue-500',
            pagada: 'bg-green-500',
        };
        return <Badge className={colores[e] || 'bg-gray-500'}>{e}</Badge>;
    };

    return (
        <>
            <Head title="Nómina" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Nómina</h1>
                            <p className="text-muted-foreground">
                                Gestión de nóminas
                            </p>
                        </div>
                        <div className="flex gap-2 items-center">
                            <BulkActions
                                baseUrl="/nominas"
                                modelName="Nóminas"
                            />
                            {canCreate && (
                                <Button onClick={handleNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nueva Nómina
                                </Button>
                            )}
                        </div>
                    </div>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Nóminas</CardTitle>
                                <CardDescription>
                                    {nominasFiltradas.length} períodos encontrados
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-1 rounded-lg bg-muted/30 p-1">
                                <Button
                                    variant={viewMode === 'table' ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => setViewMode('table')}
                                    className="h-8 w-8 p-0"
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant={viewMode === 'cards' ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => setViewMode('cards')}
                                    className="h-8 w-8 p-0"
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar por período o notas..."
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
                                    className="flex h-9 rounded-md border bg-background px-3 py-1 min-w-[150px]"
                                >
                                    <option value="">Todos los estados</option>
                                    {estados.map((est) => (
                                        <option key={est} value={est}>
                                            {est.charAt(0).toUpperCase() + est.slice(1)}
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
                                            <th className="py-2 text-left font-medium">
                                                Período
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Bruto
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Deducciones
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Neto
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
                                        {nominasFiltradas.map((n) => (
                                            <tr
                                                key={n.id}
                                                className="border-b transition-colors hover:bg-muted/30"
                                            >
                                                <td className="py-2">
                                                    <div className="font-medium">
                                                        {n.periodo || '-'}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground truncate max-w-[150px]">
                                                        {n.notas || ''}
                                                    </div>
                                                </td>
                                                <td className="py-2 text-right text-muted-foreground">
                                                    {formatCurrency(n.total_bruto)}
                                                </td>
                                                <td className="py-2 text-right text-destructive/70">
                                                    {formatCurrency(n.total_deducciones)}
                                                </td>
                                                <td className="py-2 text-right font-bold text-primary">
                                                    {formatCurrency(n.total_neto)}
                                                </td>
                                                <td className="py-2 text-center">
                                                    {getEstadoBadge(
                                                        n.estado,
                                                    )}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        {canEdit && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 w-8 p-0"
                                                                onClick={() =>
                                                                    handleEdit(n)
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
                                                                        n.id,
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
                                        {nominasFiltradas.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="py-8 text-center text-muted-foreground"
                                                >
                                                    No se encontraron períodos con los filtros aplicados
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                                <Pagination links={nominas.links} meta={nominas.meta || nominas} />
                            </div>
                            ) : (
                            <div>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {nominasFiltradas.map((n) => (
                                        <Card key={n.id} className="overflow-hidden">
                                            <CardHeader className="pb-2">
                                                <div className="flex items-start justify-between">
                                                    <CardTitle className="text-base">{n.periodo || '-'}</CardTitle>
                                                    {getEstadoBadge(n.estado)}
                                                </div>
                                                {n.notas && (
                                                    <CardDescription className="truncate text-xs">{n.notas}</CardDescription>
                                                )}
                                            </CardHeader>
                                            <CardContent>
                                                <div className="grid grid-cols-3 gap-2 text-sm">
                                                    <div>
                                                        <p className="text-[10px] text-muted-foreground">Bruto</p>
                                                        <p className="font-medium text-muted-foreground">{formatCurrency(n.total_bruto)}</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] text-muted-foreground">Deducciones</p>
                                                        <p className="font-medium text-destructive/70">{formatCurrency(n.total_deducciones)}</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] text-muted-foreground">Neto</p>
                                                        <p className="font-bold text-primary">{formatCurrency(n.total_neto)}</p>
                                                    </div>
                                                </div>
                                                <div className="mt-3 flex justify-end gap-1 border-t pt-2">
                                                    {canEdit && (
                                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(n)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(n.id)}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                                {nominasFiltradas.length === 0 && (
                                    <div className="flex flex-col items-center justify-center py-12 text-center">
                                        <div className="mb-4 rounded-full bg-muted/30 p-4">
                                            <LayoutGrid className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                        <p className="text-muted-foreground">No se encontraron períodos con los filtros aplicados</p>
                                    </div>
                                )}
                                <Pagination links={nominas.links} meta={nominas.meta || nominas} />
                            </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="mx-4 sm:mx-auto max-w-full sm:max-w-lg md:max-w-2xl lg:max-w-4xl p-4 sm:p-6 max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nueva'} Nómina
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="flex items-end gap-2">
                                <div className="space-y-2 flex-1">
                                    <Label>Período *</Label>
                                    <Input
                                        value={data.periodo}
                                        onChange={(e) =>
                                            setData('periodo', e.target.value)
                                        }
                                        placeholder="2026-02"
                                        required
                                    />
                                </div>
                                <Button type="button" onClick={calcularNominas} variant="secondary">
                                    Calcular Asistencia
                                </Button>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Fecha Inicio</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_inicio}
                                        onChange={(e) =>
                                            setData(
                                                'fecha_inicio',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Fecha Fin</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_fin}
                                        onChange={(e) =>
                                            setData('fecha_fin', e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label>Bruto</Label>
                                    <Input
                                        type="number"
                                        step="1"
                                        value={data.total_bruto}
                                        onChange={(e) =>
                                            setData(
                                                'total_bruto',
                                                parseInt(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Deducciones</Label>
                                    <Input
                                        type="number"
                                        step="1"
                                        value={data.total_deducciones}
                                        onChange={(e) =>
                                            setData(
                                                'total_deducciones',
                                                parseInt(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Neto</Label>
                                    <Input
                                        type="number"
                                        step="1"
                                        value={data.total_neto}
                                        onChange={(e) =>
                                            setData(
                                                'total_neto',
                                                parseInt(e.target.value),
                                            )
                                        }
                                    />
                                </div>
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

                            {/* Detalles de Empleados */}
                            {data.detalles && data.detalles.length > 0 && (
                                <div className="mt-4 border rounded-md overflow-hidden">
                                    <div className="bg-muted px-4 py-2 font-semibold text-sm">Desglose de Empleados</div>
                                    <div className="overflow-x-auto max-h-[300px] overflow-y-auto">
                                        <table className="w-full text-xs sm:text-sm">
                                            <thead className="bg-muted/50 sticky top-0">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">Empleado</th>
                                                    <th className="px-3 py-2 text-center">Horario</th>
                                                    <th className="px-3 py-2 text-right">Sueldo Base</th>
                                                    <th className="px-3 py-2 text-center">Días Asist.</th>
                                                    <th className="px-3 py-2 text-right">Sueldo Prop.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {data.detalles.map((det: any, index: number) => (
                                                    <tr key={det.empleado_id} className="border-t hover:bg-muted/20">
                                                        <td className="px-3 py-2">
                                                            <div className="font-medium">{det.nombre} {det.apellido}</div>
                                                            <div className="text-muted-foreground text-[10px]">{det.rut}</div>
                                                        </td>
                                                        <td className="px-3 py-2 text-center">
                                                            {det.hora_entrada || det.hora_salida ? (
                                                                <span className="text-muted-foreground text-[10px] whitespace-nowrap">
                                                                    {det.hora_entrada && det.hora_salida
                                                                        ? `${det.hora_entrada} - ${det.hora_salida}`
                                                                        : det.hora_entrada || det.hora_salida}
                                                                </span>
                                                            ) : (
                                                                <span className="text-muted-foreground/40">--</span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-muted-foreground">
                                                            {formatCurrency(det.sueldo_pactado)}
                                                        </td>
                                                        <td className="px-3 py-2 text-center font-medium text-primary">
                                                            {det.dias_asistidos}
                                                        </td>
                                                        <td className="px-3 py-2 text-right">
                                                            <Input
                                                                type="number"
                                                                className="h-8 w-28 ml-auto text-right"
                                                                value={det.sueldo_proporcional}
                                                                onChange={(e) => {
                                                                    const val = parseInt(e.target.value) || 0;
                                                                    const newDetalles = [...data.detalles];
                                                                    newDetalles[index].sueldo_proporcional = val;
                                                                    const newBruto = newDetalles.reduce((acc, curr) => acc + curr.sueldo_proporcional, 0);
                                                                    setData(prev => ({
                                                                        ...prev,
                                                                        detalles: newDetalles,
                                                                        total_bruto: newBruto,
                                                                        total_neto: newBruto - prev.total_deducciones
                                                                    }));
                                                                }}
                                                            />
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}
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
