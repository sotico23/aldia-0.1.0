import { Head, router } from '@inertiajs/react';
import { LayoutGrid, List, Pencil, Plus, Trash2, Search, X } from 'lucide-react';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { formatCurrency } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Material {
    id?: string;
    nombre: string;
    cantidad: number;
    unidad: string;
    costo_unitario: number;
    costo_total: number;
}

interface Bom {
    id: number;
    nombre: string;
    producto_final: string | null;
    cantidad: number;
    materiales: Material[] | null;
    activo: boolean;
    notas: string | null;
    tipo: string;
    costo_total_materiales?: number;
}

interface PaginatedData {
    data: Bom[];
    links: { url: string | null; label: string; active: boolean }[];
    meta?: { current_page: number; from: number; last_page: number; path: string; per_page: number; to: number; total: number };
    from?: number;
    to?: number;
    total?: number;
}

const typeConfig: Record<string, { label: string; title: string; parent: string; child: string }> = {
    bom: { label: 'BOM', title: 'Listas de Materiales (BOM)', parent: 'Producto Final', child: 'Material' },
    recipe: { label: 'Receta', title: 'Recetas', parent: 'Plato / Producto', child: 'Ingrediente' },
    kit: { label: 'Kit', title: 'Kits y Paquetes', parent: 'Kit / Paquete', child: 'Componente' },
    formula: { label: 'Fórmula', title: 'Fórmulas', parent: 'Producto Base', child: 'Insumo' },
    custom: { label: 'Personalizado', title: 'Composiciones', parent: 'Producto', child: 'Componente' },
};

const tipoOptions = [
    { value: 'bom', label: 'BOM (Lista de Materiales)' },
    { value: 'recipe', label: 'Receta' },
    { value: 'kit', label: 'Kit / Paquete' },
    { value: 'formula', label: 'Fórmula' },
    { value: 'custom', label: 'Personalizado' },
];

const UNIDADES = ['unid', 'kg', 'gr', 'litro', 'ml', 'm', 'm²', 'paq', 'caja', 'docena', 'porción', 'pieza'];

const calcCostoTotal = (materiales: Material[]): number =>
    materiales.reduce((sum, m) => sum + (Number(m.cantidad) || 0) * (Number(m.costo_unitario) || 0), 0);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Composiciones', href: '/boms' },
];

export default function Index({ boms }: { boms: PaginatedData }) {
    const { code: countryCode, currency } = useCountry();
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Bom | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('mrp.boms.create');
    const canEdit = hasPermission('mrp.boms.edit');
    const canDelete = hasPermission('mrp.boms.delete');

    const [formData, setFormData] = useState({
        nombre: '',
        tipo: 'bom' as string,
        producto_final: '',
        cantidad: 1,
        activo: true,
        notas: '',
        materiales: [] as Material[],
        margen: 30,
    });

    const [filtros, setFiltros] = useState({ busqueda: '', activo: '' });

    const currentType = typeConfig[formData.tipo] || typeConfig.bom;

    const handleNew = () => {
        setEditando(null);
        setFormData({
            nombre: '',
            tipo: 'bom',
            producto_final: '',
            cantidad: 1,
            activo: true,
            notas: '',
            materiales: [],
            margen: 30,
        });
        setIsOpen(true);
    };

    const handleEdit = (b: Bom) => {
        const mat = b.materiales || [];
        setEditando(b);
        setFormData({
            nombre: b.nombre,
            tipo: b.tipo || 'bom',
            producto_final: b.producto_final || '',
            cantidad: b.cantidad,
            activo: b.activo,
            notas: b.notas || '',
            materiales: mat.length > 0 ? mat : [],
            margen: 30,
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) router.delete(`/boms/${id}`);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...formData,
            cantidad: Number(formData.cantidad),
            materiales: formData.materiales.map((m) => ({
                ...m,
                cantidad: Number(m.cantidad),
                costo_unitario: Number(m.costo_unitario),
                costo_total: (Number(m.cantidad) || 0) * (Number(m.costo_unitario) || 0),
            })),
        };

        if (editando) {
            router.put(`/boms/${editando.id}`, payload, {
                onSuccess: () => { setIsOpen(false); setEditando(null); },
            });
        } else {
            router.post('/boms', payload, {
                onSuccess: () => { setIsOpen(false); },
            });
        }
    };

    const costoTotalMateriales = useMemo(() => calcCostoTotal(formData.materiales), [formData.materiales]);
    const precioSugerido = costoTotalMateriales * (1 + (formData.margen || 0) / 100);

    const addMaterial = () => {
        setFormData((prev) => ({
            ...prev,
            materiales: [
                ...prev.materiales,
                { id: crypto.randomUUID(), nombre: '', cantidad: 1, unidad: 'unid', costo_unitario: 0, costo_total: 0 },
            ],
        }));
    };

    const removeMaterial = (index: number) => {
        setFormData((prev) => ({
            ...prev,
            materiales: prev.materiales.filter((_, i) => i !== index),
        }));
    };

    const updateMaterial = (index: number, field: keyof Material, value: string | number) => {
        setFormData((prev) => {
            const materiales = [...prev.materiales];
            materiales[index] = { ...materiales[index], [field]: value };
            if (field === 'cantidad' || field === 'costo_unitario') {
                materiales[index].costo_total =
                    (Number(materiales[index].cantidad) || 0) * (Number(materiales[index].costo_unitario) || 0);
            }
            return { ...prev, materiales };
        });
    };

    const bomsFiltrados = useMemo(() => {
        return boms.data.filter((b) => {
            if (filtros.busqueda) {
                const busca = filtros.busqueda.toLowerCase();
                if (
                    !b.nombre.toLowerCase().includes(busca) &&
                    !(b.producto_final || '').toLowerCase().includes(busca)
                ) {
                    return false;
                }
            }
            if (filtros.activo !== '') {
                if (b.activo !== (filtros.activo === '1')) return false;
            }
            return true;
        });
    }, [boms.data, filtros]);

    const limpiarFiltros = () => setFiltros({ busqueda: '', activo: '' });

    return (
        <>
            <Head title="Composiciones" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-xl font-bold sm:text-2xl">
                                {currentType.title}
                            </h1>
                            <p className="text-xs text-muted-foreground sm:text-sm">
                                Gestión de composiciones de producto
                            </p>
                        </div>
                        {canCreate && (
                            <Button onClick={handleNew} className="w-full sm:w-auto">
                                <Plus className="mr-2 h-4 w-4" /> Nueva Composición
                            </Button>
                        )}
                    </div>

                    <Card>
                        <CardHeader className="p-4 sm:p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-sm sm:text-base">Composiciones</CardTitle>
                                    <CardDescription className="text-xs sm:text-sm">
                                        {bomsFiltrados.length} registros encontrados
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-1 rounded-lg border bg-muted/30 p-0.5">
                                    <button onClick={() => setViewMode('table')} className={`rounded-md p-1.5 transition-colors ${viewMode === 'table' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tabla"><List className="h-4 w-4" /></button>
                                    <button onClick={() => setViewMode('cards')} className={`rounded-md p-1.5 transition-colors ${viewMode === 'cards' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} title="Vista tarjetas"><LayoutGrid className="h-4 w-4" /></button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                            <div className="mb-4 flex flex-wrap gap-2 rounded-lg bg-muted/30 p-2 text-xs sm:p-3 sm:text-sm">
                                <div className="min-w-[160px] flex-1 sm:min-w-[200px]">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar por nombre o producto..."
                                            value={filtros.busqueda}
                                            onChange={(e) =>
                                                setFiltros({ ...filtros, busqueda: e.target.value })
                                            }
                                            className="h-9 pl-8 pr-8 text-xs sm:text-sm"
                                        />
                                    </div>
                                </div>
                                <select
                                    value={filtros.activo}
                                    onChange={(e) =>
                                        setFiltros({ ...filtros, activo: e.target.value })
                                    }
                                    className="flex h-9 rounded-md border bg-background px-3 py-1 text-xs sm:text-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="1">Activos</option>
                                    <option value="0">Inactivos</option>
                                </select>
                                <Button variant="outline" size="sm" className="h-9" onClick={limpiarFiltros}>
                                    <X className="mr-1 h-4 w-4" />
                                    Limpiar
                                </Button>
                            </div>

                            {viewMode === 'table' ? (
                                <>
                                    {bomsFiltrados.length === 0 ? (
                                        <p className="py-8 text-center text-xs text-muted-foreground sm:text-sm">
                                            No se encontraron composiciones con los filtros aplicados
                                        </p>
                                    ) : (
                                        <>
                                            <div className="hidden md:block">
                                                <table className="w-full text-xs sm:text-sm">
                                                    <thead>
                                                        <tr className="border-b">
                                                            <th className="py-2 pr-2 text-left font-medium">Nombre</th>
                                                            <th className="py-2 pr-2 text-left font-medium">Tipo</th>
                                                            <th className="py-2 pr-2 text-left font-medium">{currentType.parent}</th>
                                                            <th className="py-2 pr-2 text-right font-medium">Cant</th>
                                                            <th className="py-2 pr-2 text-right font-medium">Costo Mat.</th>
                                                            <th className="py-2 pr-2 text-center font-medium">Estado</th>
                                                            <th className="py-2 text-right font-medium">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {bomsFiltrados.map((b) => {
                                                            const cfg = typeConfig[b.tipo] || typeConfig.bom;
                                                            const costo = calcCostoTotal(b.materiales || []);
                                                            return (
                                                                <tr key={b.id} className="border-b transition-colors hover:bg-muted/30">
                                                                    <td className="py-2 pr-2 font-medium">{b.nombre}</td>
                                                                    <td className="py-2 pr-2">
                                                                        <Badge variant="outline" className="text-[10px]">
                                                                            {cfg.label}
                                                                        </Badge>
                                                                    </td>
                                                                    <td className="py-2 pr-2 text-muted-foreground">
                                                                        {b.producto_final || '-'}
                                                                    </td>
                                                                    <td className="py-2 pr-2 text-right">{b.cantidad}</td>
                                                                    <td className="py-2 pr-2 text-right font-medium">
                                                                        {costo > 0 ? formatCurrency(costo) : '-'}
                                                                    </td>
                                                                    <td className="py-2 pr-2 text-center">
                                                                        <Badge
                                                                            variant={b.activo ? 'default' : 'destructive'}
                                                                            className="text-[10px] px-1.5 py-0"
                                                                        >
                                                                            {b.activo ? 'Activo' : 'Inactivo'}
                                                                        </Badge>
                                                                    </td>
                                                                    <td className="py-2 text-right">
                                                                        <div className="flex justify-end gap-1">
                                                                            {canEdit && (
                                                                                <Button
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    className="h-8 w-8 p-0"
                                                                                    onClick={() => handleEdit(b)}
                                                                                >
                                                                                    <Pencil className="h-4 w-4" />
                                                                                </Button>
                                                                            )}
                                                                            {canDelete && (
                                                                                <Button
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                                                                    onClick={() => handleDelete(b.id)}
                                                                                >
                                                                                    <Trash2 className="h-4 w-4" />
                                                                                </Button>
                                                                            )}
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div className="flex flex-col gap-3 md:hidden">
                                                {bomsFiltrados.map((b) => {
                                                    const cfg = typeConfig[b.tipo] || typeConfig.bom;
                                                    const costo = calcCostoTotal(b.materiales || []);
                                                    return (
                                                        <div
                                                            key={b.id}
                                                            className="rounded-lg border bg-card p-3 text-xs shadow-sm"
                                                        >
                                                            <div className="mb-2 flex items-center justify-between">
                                                                <span className="font-semibold">{b.nombre}</span>
                                                                <Badge
                                                                    variant={b.activo ? 'default' : 'destructive'}
                                                                    className="text-[10px] px-1.5 py-0"
                                                                >
                                                                    {b.activo ? 'Activo' : 'Inactivo'}
                                                                </Badge>
                                                            </div>
                                                            <div className="space-y-1 text-muted-foreground">
                                                                <div className="flex justify-between">
                                                                    <span>Tipo:</span>
                                                                    <Badge variant="outline" className="text-[10px]">
                                                                        {cfg.label}
                                                                    </Badge>
                                                                </div>
                                                                <div className="flex justify-between">
                                                                    <span>{cfg.parent}:</span>
                                                                    <span>{b.producto_final || '-'}</span>
                                                                </div>
                                                                <div className="flex justify-between">
                                                                    <span>Cantidad:</span>
                                                                    <span className="font-medium">{b.cantidad}</span>
                                                                </div>
                                                                <div className="flex justify-between">
                                                                    <span>Costo materiales:</span>
                                                                    <span className="font-medium">
                                                                        {costo > 0 ? formatCurrency(costo) : '-'}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div className="mt-2 flex justify-end gap-1 border-t pt-2">
                                                                {canEdit && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="h-7 px-2 text-xs"
                                                                        onClick={() => handleEdit(b)}
                                                                    >
                                                                        <Pencil className="mr-1 h-3 w-3" />
                                                                        Editar
                                                                    </Button>
                                                                )}
                                                                {canDelete && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="h-7 px-2 text-xs text-destructive hover:text-destructive"
                                                                        onClick={() => handleDelete(b.id)}
                                                                    >
                                                                        <Trash2 className="mr-1 h-3 w-3" />
                                                                        Eliminar
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>

                                            <Pagination links={boms.links} meta={boms.meta} />
                                        </>
                                    )}
                                </>
                            ) : (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {bomsFiltrados.length === 0 ? (
                                        <p className="col-span-full py-8 text-center text-xs text-muted-foreground sm:text-sm">
                                            No se encontraron composiciones con los filtros aplicados
                                        </p>
                                    ) : (
                                        bomsFiltrados.map((b) => {
                                            const cfg = typeConfig[b.tipo] || typeConfig.bom;
                                            const costo = calcCostoTotal(b.materiales || []);
                                            return (
                                                <div key={b.id} className="rounded-lg border bg-card p-4 shadow-sm flex flex-col gap-3">
                                                    <div className="flex items-start justify-between">
                                                        <span className="font-semibold">{b.nombre}</span>
                                                        <Badge variant={b.activo ? 'default' : 'destructive'} className="text-[10px] px-1.5 py-0">
                                                            {b.activo ? 'Activo' : 'Inactivo'}
                                                        </Badge>
                                                    </div>
                                                    <div className="space-y-1.5 text-sm">
                                                        <div className="flex justify-between text-xs">
                                                            <span className="text-muted-foreground">Tipo:</span>
                                                            <Badge variant="outline" className="text-[10px]">{cfg.label}</Badge>
                                                        </div>
                                                        <div className="flex justify-between text-xs">
                                                            <span className="text-muted-foreground">{cfg.parent}:</span>
                                                            <span className="font-medium">{b.producto_final || '-'}</span>
                                                        </div>
                                                        <div className="flex justify-between text-xs">
                                                            <span className="text-muted-foreground">Cantidad:</span>
                                                            <span className="font-medium">{b.cantidad}</span>
                                                        </div>
                                                        <div className="flex justify-between text-xs">
                                                            <span className="text-muted-foreground">Costo Mat.:</span>
                                                            <span className="font-medium">{costo > 0 ? formatCurrency(costo) : '-'}</span>
                                                        </div>
                                                    </div>
                                                    <div className="flex justify-end gap-1 border-t pt-2 mt-auto">
                                                        {canEdit && (
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleEdit(b)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-destructive hover:text-destructive" onClick={() => handleDelete(b.id)}>
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

            </AppLayout>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="w-[95vw] max-w-2xl overflow-y-auto p-3 sm:p-6" style={{ maxHeight: '90vh' }}>
                    <DialogHeader className="mb-2 sm:mb-4">
                        <DialogTitle className="text-base sm:text-lg">
                            {editando ? 'Editar' : 'Nueva'} Composición
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Nombre *</Label>
                                <Input
                                    value={formData.nombre}
                                    onChange={(e) => setFormData({ ...formData, nombre: e.target.value })}
                                    required
                                    className="h-10 text-xs sm:text-sm"
                                    placeholder="Ej: Pizza Margherita"
                                />
                            </div>
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Tipo</Label>
                                <Select
                                    value={formData.tipo}
                                    onValueChange={(v) => setFormData({ ...formData, tipo: v })}
                                >
                                    <SelectTrigger className="h-10 text-xs sm:text-sm">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {tipoOptions.map((o) => (
                                            <SelectItem key={o.value} value={o.value} className="text-xs sm:text-sm">
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5 sm:space-y-2">
                            <Label className="text-xs sm:text-sm">{currentType.parent}</Label>
                            <Input
                                value={formData.producto_final}
                                onChange={(e) => setFormData({ ...formData, producto_final: e.target.value })}
                                className="h-10 text-xs sm:text-sm"
                                placeholder="Nombre del producto final"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5 sm:space-y-2">
                                <Label className="text-xs sm:text-sm">Cantidad</Label>
                                <Input
                                    type="number"
                                    min="1"
                                    value={formData.cantidad}
                                    onChange={(e) => setFormData({ ...formData, cantidad: parseInt(e.target.value) || 1 })}
                                    className="h-10 text-xs sm:text-sm"
                                />
                            </div>
                            <div className="flex items-end pb-1 sm:pb-2">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={formData.activo}
                                        onChange={(e) => setFormData({ ...formData, activo: e.target.checked })}
                                        className="h-4 w-4"
                                    />
                                    <span className="text-xs sm:text-sm">Activo</span>
                                </label>
                            </div>
                        </div>

                        <div className="space-y-2 rounded-lg border bg-muted/20 p-3 sm:p-4">
                            <div className="flex items-center justify-between">
                                <Label className="text-xs font-bold sm:text-sm">
                                    {currentType.child}es / Componentes
                                </Label>
                                <Button type="button" variant="outline" size="sm" onClick={addMaterial} className="h-8 text-xs">
                                    <Plus className="mr-1 h-3 w-3" /> Agregar {currentType.child.toLowerCase()}
                                </Button>
                            </div>

                            {formData.materiales.length === 0 && (
                                <p className="py-4 text-center text-xs text-muted-foreground">
                                    No hay {currentType.child.toLowerCase()}es agregados. Haz clic en "Agregar {currentType.child.toLowerCase()}" para comenzar.
                                </p>
                            )}

                            <div className="space-y-2">
                                {formData.materiales.map((mat, i) => (
                                    <div
                                        key={mat.id || i}
                                        className="flex flex-wrap items-end gap-2 rounded-lg border bg-background p-2 sm:flex-nowrap sm:p-3"
                                    >
                                        <div className="flex-1 space-y-1" style={{ minWidth: '120px' }}>
                                            <Label className="text-[10px] text-muted-foreground">Nombre</Label>
                                            <div className="flex gap-1">
                                                <Input
                                                    value={mat.nombre}
                                                    onChange={(e) => updateMaterial(i, 'nombre', e.target.value)}
                                                    placeholder="Nombre del componente"
                                                    className="h-8 text-xs"
                                                />

                                            </div>
                                        </div>
                                        <div className="w-16 space-y-1 sm:w-20">
                                            <Label className="text-[10px] text-muted-foreground">Cant</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={mat.cantidad}
                                                onChange={(e) => updateMaterial(i, 'cantidad', parseFloat(e.target.value) || 0)}
                                                className="h-8 text-xs text-center"
                                            />
                                        </div>
                                        <div className="w-20 space-y-1 sm:w-24">
                                            <Label className="text-[10px] text-muted-foreground">Unidad</Label>
                                            <Select
                                                value={mat.unidad}
                                                onValueChange={(v) => updateMaterial(i, 'unidad', v)}
                                            >
                                                <SelectTrigger className="h-8 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {UNIDADES.map((u) => (
                                                        <SelectItem key={u} value={u} className="text-xs">
                                                            {u}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="w-24 space-y-1 sm:w-28">
                                            <Label className="text-[10px] text-muted-foreground">Costo U.</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                value={mat.costo_unitario}
                                                onChange={(e) => updateMaterial(i, 'costo_unitario', parseFloat(e.target.value) || 0)}
                                                className="h-8 text-xs text-right"
                                            />
                                        </div>
                                        <div className="w-24 space-y-1 sm:w-28">
                                            <Label className="text-[10px] text-muted-foreground">Costo T.</Label>
                                            <div className="flex h-8 items-center justify-end rounded-md border bg-muted/30 px-2 text-xs font-medium">
                                                {formatCurrency(mat.costo_total)}
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 shrink-0 text-destructive"
                                            onClick={() => removeMaterial(i)}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>

                            {formData.materiales.length > 0 && (
                                <div className="border-t pt-3">
                                    <div className="space-y-1 text-xs sm:text-sm">
                                        <div className="flex justify-between font-medium">
                                            <span>Total {currentType.child.toLowerCase()}es:</span>
                                            <span>{formatCurrency(costoTotalMateriales)}</span>
                                        </div>
                                        <div className="flex items-center justify-between gap-4">
                                            <span>Margen (%):</span>
                                            <Input
                                                type="number"
                                                min="0"
                                                max="1000"
                                                value={formData.margen}
                                                onChange={(e) =>
                                                    setFormData({ ...formData, margen: parseInt(e.target.value) || 0 })
                                                }
                                                className="h-8 w-20 text-xs text-right"
                                            />
                                        </div>
                                        <div className="flex justify-between text-sm font-bold sm:text-base">
                                            <span>Precio sugerido:</span>
                                            <span className="text-primary">{formatCurrency(precioSugerido)}</span>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="space-y-1.5 sm:space-y-2">
                            <Label className="text-xs sm:text-sm">Notas</Label>
                            <textarea
                                value={formData.notas}
                                onChange={(e) => setFormData({ ...formData, notas: e.target.value })}
                                className="flex min-h-[60px] w-full rounded-md border bg-background px-3 py-2 text-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring sm:text-sm"
                                placeholder="Notas adicionales..."
                            />
                        </div>

                        <DialogFooter className="flex-col gap-2 sm:flex-row">
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)} className="w-full sm:w-auto">
                                Cancelar
                            </Button>
                            <Button type="submit" className="w-full sm:w-auto">
                                {editando ? 'Actualizar' : 'Guardar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
