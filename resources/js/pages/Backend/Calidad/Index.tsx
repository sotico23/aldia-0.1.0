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
import { formatDateCLP, getLocalDateString } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Control {
    id: number;
    lote: string | null;
    producto: string | null;
    tipo: string | null;
    resultado: string | null;
    cantidad_muestra: number;
    cantidad_defectuosa: number;
    observaciones: string | null;
    fecha: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Control de Calidad', href: '/calidad' },
];

const resultados = ['aprobado', 'rechazado', 'pendiente'];


export default function Index({
    controles,
}: {
    controles: {
        data: Control[];
        links: any[];
        meta: { from: number; to: number; total: number };
    };
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Control | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('mrp.calidad.create');
    const canEdit = hasPermission('mrp.calidad.edit');
    const canDelete = hasPermission('mrp.calidad.delete');
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        lote: '',
        producto: '',
        tipo: '',
        resultado: 'pendiente',
        cantidad_muestra: 0,
        cantidad_defectuosa: 0,
        observaciones: '',
        fecha: '',
    });

    const [filtros, setFiltros] = useState({
        busqueda: '',
        resultado: '',
    });
    const controlesFiltrados = useMemo(() => {
        return controles.data.filter((c) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !(c.lote || '').toLowerCase().includes(busca) &&
                    !(c.producto || '').toLowerCase().includes(busca) &&
                    !(c.tipo || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.resultado && c.resultado !== filtros.resultado)
                return false;

            return true;
        });
    }, [controles.data, filtros]);

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            resultado: '',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando)
            put(`/calidad/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        else
            post('/calidad', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
    };

    const handleEdit = (c: Control) => {
        setEditando(c);
        setData({
            lote: c.lote || '',
            producto: c.producto || '',
            tipo: c.tipo || '',
            resultado: c.resultado || 'pendiente',
            cantidad_muestra: c.cantidad_muestra,
            cantidad_defectuosa: c.cantidad_defectuosa,
            observaciones: c.observaciones || '',
            fecha: c.fecha || '',
        });
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setData({
            lote: '',
            producto: '',
            tipo: '',
            resultado: 'pendiente',
            cantidad_muestra: 0,
            cantidad_defectuosa: 0,
            observaciones: '',
            fecha: getLocalDateString(),
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/calidad/${id}`);
    };

    const getResultadoBadge = (r: string | null) => {
        const colores: Record<string, string> = {
            aprobado: 'bg-green-500',
            rechazado: 'bg-red-500',
            pendiente: 'bg-yellow-500',
        };
        return (
            <Badge className={colores[r || ''] || 'bg-gray-500'}>
                {r || '-'}
            </Badge>
        );
    };

    return (
        <>
            <Head title="Control de Calidad" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-xl font-bold sm:text-2xl">
                                Control de Calidad
                            </h1>
                            <p className="text-xs text-muted-foreground sm:text-sm">
                                Gestión de calidad
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <BulkActions 
                                baseUrl="/calidad" 
                                modelName="Controles"
                                filters={{
                                    search: filtros.busqueda,
                                    resultado: filtros.resultado
                                }}
                            />
                            {canCreate && (
                                <Button onClick={handleNew} className="w-full sm:w-auto">
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo Control
                                </Button>
                            )}
                        </div>
                    </div>
                    <Card>
                        <CardHeader className="p-4 sm:p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-sm sm:text-base">Controles</CardTitle>
                                    <CardDescription className="text-xs sm:text-sm">
                                        {controlesFiltrados.length} registros encontrados
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-1 rounded-lg border bg-muted/30 p-0.5">
                                    <button onClick={() => setViewMode('table')} className={`rounded-md p-1.5 transition-colors ${viewMode === 'table' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tabla"><List className="h-4 w-4" /></button>
                                    <button onClick={() => setViewMode('cards')} className={`rounded-md p-1.5 transition-colors ${viewMode === 'cards' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tarjetas"><LayoutGrid className="h-4 w-4" /></button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-3 text-xs sm:text-sm">
                                <div className="min-w-[200px] flex-1">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar por lote, producto o tipo..."
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
                                <select
                                    value={filtros.resultado}
                                    onChange={(e) =>
                                        setFiltros({
                                            ...filtros,
                                            resultado: e.target.value,
                                        })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-3 py-1 min-w-[150px]"
                                >
                                    <option value="">Todos los resultados</option>
                                    {resultados.map((r) => (
                                        <option key={r} value={r}>
                                            {r.charAt(0).toUpperCase() + r.slice(1)}
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
                            <>
                                <div className="hidden md:block">
                                    <table className="w-full text-xs sm:text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-2 pr-2 text-left font-medium">Lote / Producto</th>
                                                <th className="py-2 pr-2 text-center font-medium">Muestra / Def.</th>
                                                <th className="py-2 pr-2 text-left font-medium">Tipo / Fecha</th>
                                                <th className="py-2 pr-2 text-center font-medium">Resultado</th>
                                                <th className="py-2 text-right font-medium">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {controlesFiltrados.map((c) => (
                                                <tr key={c.id} className="border-b transition-colors hover:bg-muted/30">
                                                    <td className="py-2 pr-2">
                                                        <div className="font-mono font-medium">{c.lote || '-'}</div>
                                                        <div className="text-[10px] text-muted-foreground truncate max-w-[150px]">{c.producto || ''}</div>
                                                    </td>
                                                    <td className="py-2 pr-2 text-center">
                                                        <div className="font-medium">{c.cantidad_muestra}</div>
                                                        <div className="text-[10px] text-destructive">{c.cantidad_defectuosa} defectuosos</div>
                                                    </td>
                                                    <td className="py-2 pr-2">
                                                        <div className="text-[10px] uppercase font-medium">{c.tipo || '-'}</div>
                                                        <div className="text-[10px] text-muted-foreground">{formatDateCLP(c.fecha)}</div>
                                                    </td>
                                                    <td className="py-2 pr-2 text-center">{getResultadoBadge(c.resultado)}</td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-1">
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
                                                    </td>
                                                </tr>
                                            ))}
                                            {controlesFiltrados.length === 0 && (
                                                <tr><td colSpan={5} className="py-8 text-center text-muted-foreground">No se encontraron registros con los filtros aplicados</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex flex-col gap-3 md:hidden">
                                    {controlesFiltrados.length === 0 ? (
                                        <p className="py-8 text-center text-xs text-muted-foreground">No se encontraron registros con los filtros aplicados</p>
                                    ) : (
                                        controlesFiltrados.map((c) => (
                                            <div key={c.id} className="rounded-lg border bg-card p-3 text-xs shadow-sm">
                                                <div className="mb-2 flex items-center justify-between">
                                                    <span className="font-mono font-semibold">{c.lote || '-'}</span>
                                                    {getResultadoBadge(c.resultado)}
                                                </div>
                                                <div className="space-y-1 text-muted-foreground">
                                                    <div className="flex justify-between">
                                                        <span>Producto:</span>
                                                        <span className="font-medium text-foreground">{c.producto || '-'}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span>Muestra / Def.:</span>
                                                        <span className="font-medium">{c.cantidad_muestra} / {c.cantidad_defectuosa}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span>Tipo:</span>
                                                        <span className="uppercase">{c.tipo || '-'}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span>Fecha:</span>
                                                        <span>{formatDateCLP(c.fecha)}</span>
                                                    </div>
                                                </div>
                                                <div className="mt-2 flex justify-end gap-1 border-t pt-2">
                                                    {canEdit && (
                                                        <Button variant="ghost" size="sm" className="h-7 px-2 text-xs" onClick={() => handleEdit(c)}>
                                                            <Pencil className="mr-1 h-3 w-3" /> Editar
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button variant="ghost" size="sm" className="h-7 px-2 text-xs text-destructive hover:text-destructive" onClick={() => handleDelete(c.id)}>
                                                            <Trash2 className="mr-1 h-3 w-3" /> Eliminar
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                                <Pagination
                                    links={controles.links}
                                    meta={controles.meta}
                                />
                            </>
                        ) : (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                {controlesFiltrados.length === 0 ? (
                                    <p className="col-span-full py-8 text-center text-xs text-muted-foreground">No se encontraron registros con los filtros aplicados</p>
                                ) : (
                                    controlesFiltrados.map((c) => (
                                        <div key={c.id} className="rounded-lg border bg-card p-4 shadow-sm flex flex-col gap-3">
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <div className="font-mono font-semibold">{c.lote || '-'}</div>
                                                    <div className="text-xs text-muted-foreground">{c.producto || '-'}</div>
                                                </div>
                                                {getResultadoBadge(c.resultado)}
                                            </div>
                                            <div className="space-y-1.5 text-sm">
                                                <div className="flex justify-between text-xs">
                                                    <span className="text-muted-foreground">Muestra / Def.:</span>
                                                    <span className="font-medium">{c.cantidad_muestra} / {c.cantidad_defectuosa}</span>
                                                </div>
                                                <div className="flex justify-between text-xs">
                                                    <span className="text-muted-foreground">Tipo:</span>
                                                    <span className="uppercase font-medium">{c.tipo || '-'}</span>
                                                </div>
                                                <div className="flex justify-between text-xs">
                                                    <span className="text-muted-foreground">Fecha:</span>
                                                    <span>{formatDateCLP(c.fecha)}</span>
                                                </div>
                                            </div>
                                            <div className="flex justify-end gap-1 border-t pt-2 mt-auto">
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
                                        </div>
                                    ))
                                )}
                            </div>
                        )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="w-[95vw] max-w-lg overflow-y-auto p-3 sm:p-6" style={{ maxHeight: '85vh' }}>
                    <DialogHeader className="mb-2 sm:mb-4">
                        <DialogTitle className="text-base sm:text-lg">
                            {editando ? 'Editar' : 'Nuevo'} Control
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Lote</Label>
                                <Input
                                    value={data.lote}
                                    onChange={(e) => setData('lote', e.target.value)}
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Producto</Label>
                                <div className="flex gap-2">
                                    <Input
                                        value={data.producto}
                                        onChange={(e) => setData('producto', e.target.value)}
                                        className="flex-1 h-10 text-xs sm:text-sm"
                                    />

                                </div>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Tipo</Label>
                                <Input
                                    value={data.tipo}
                                    onChange={(e) => setData('tipo', e.target.value)}
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Resultado</Label>
                                <select
                                    value={data.resultado}
                                    onChange={(e) => setData('resultado', e.target.value)}
                                    className="flex h-10 w-full rounded-md border bg-background px-3 py-2 text-xs sm:text-sm"
                                >
                                    {resultados.map((r) => (
                                        <option key={r} value={r}>{r}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Cantidad Muestra</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={data.cantidad_muestra}
                                    onChange={(e) => setData('cantidad_muestra', parseInt(e.target.value))}
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Defectuosos</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={data.cantidad_defectuosa}
                                    onChange={(e) => setData('cantidad_defectuosa', parseInt(e.target.value))}
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5 sm:space-y-2">
                            <Label className="text-xs sm:text-sm">Fecha</Label>
                            <Input
                                type="date"
                                value={data.fecha}
                                onChange={(e) => setData('fecha', e.target.value)}
                                className="h-10 text-xs sm:text-sm"
                            />
                        </div>
                        <DialogFooter className="flex-col gap-2 sm:flex-row">
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)} className="w-full sm:w-auto">
                                Cancelar
                            </Button>
                            <Button type="submit" className="w-full sm:w-auto">Guardar</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
