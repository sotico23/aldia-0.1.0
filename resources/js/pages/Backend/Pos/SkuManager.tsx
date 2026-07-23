import { Head, useForm } from '@inertiajs/react';
import { Barcode, Plus, QrCode, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Producto {
    id: number;
    nombre: string;
    codigo: string | null;
}

interface VarianteValor {
    id: number;
    valor: string;
    codigo: string | null;
    variante: { id: number; nombre: string };
}

interface Variante {
    id: number;
    nombre: string;
    tipo: string;
    valores: { id: number; valor: string; codigo: string | null }[];
}

interface Sku {
    id: number;
    sku: string;
    precio_venta: string | null;
    precio_compra: string | null;
    stock: string;
    stock_minimo: string;
    activo: boolean;
    producto: { id: number; nombre: string; codigo: string | null };
    valores: {
        id: number;
        varianteValor: VarianteValor;
    }[];
}

interface Props {
    skus: Sku[];
    productos: Producto[];
    variantes: Variante[];
    flash?: { success?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Caja POS', href: '/pos' },
    { title: 'SKUs', href: '/pos/skus' },
];

export default function SkuManager({ skus, productos, variantes, flash }: Props) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('ventas.variantes.viewAny');
    const [isOpen, setIsOpen] = useState(false);

    const formatNumber = (value: number | string | null) => {
        if (value === null || value === undefined) return '0';
        const num = typeof value === 'string' ? parseFloat(value) : value;
        return Number.isInteger(num) ? num.toString() : num.toFixed(3);
    };

    const { data, setData, post, delete: destroy, reset, processing } = useForm({
        producto_id: '' as number | '',
        sku: '',
        precio_venta: '',
        precio_compra: '',
        stock: '0',
        stock_minimo: '0',
        variantes: [] as number[],
    });

    const handleOpenNew = () => {
        reset();
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar este SKU?')) {
            destroy(`/pos/skus/${id}`);
        }
    };

    const getSelectedProductVariantes = () => {
        return variantes;
    };

    const toggleVarianteValor = (valorId: number) => {
        const current = data.variantes;
        if (current.includes(valorId)) {
            setData('variantes', current.filter((id) => id !== valorId));
        } else {
            setData('variantes', [...current, valorId]);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/pos/skus', {
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
        });
    };

    if (!canAccess) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SKUs" />

            {flash?.success && (
                <div className="mx-4 mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}

            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Administrar SKUs</h1>
                        <p className="text-muted-foreground">
                            Gestiona los SKUs de productos con variantes
                        </p>
                    </div>
                    <Button onClick={handleOpenNew} disabled={productos.length === 0}>
                        <Plus className="mr-2 h-4 w-4" /> Nuevo SKU
                    </Button>
                </div>

                {skus.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <QrCode className="mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">Sin SKUs</h3>
                            <p className="mb-4 text-muted-foreground">Crea SKUs para productos con variantes</p>
                            <Button onClick={handleOpenNew} disabled={productos.length === 0}>
                                <Plus className="mr-2 h-4 w-4" /> Nuevo SKU
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-slate-50 border-b">
                                    <th className="px-4 py-3 text-left font-semibold text-slate-600">SKU</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-600">Producto</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-600">Variantes</th>
                                    <th className="px-4 py-3 text-right font-semibold text-slate-600">P. Venta</th>
                                    <th className="px-4 py-3 text-right font-semibold text-slate-600">P. Compra</th>
                                    <th className="px-4 py-3 text-right font-semibold text-slate-600">Stock</th>
                                    <th className="px-4 py-3 text-right font-semibold text-slate-600">Mínimo</th>
                                    <th className="px-4 py-3 text-center font-semibold text-slate-600">Estado</th>
                                    <th className="px-4 py-3 text-center font-semibold text-slate-600">Acción</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {skus.map((sku) => (
                                    <tr key={sku.id} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-4 py-3 font-medium">
                                            <div className="flex items-center gap-1.5">
                                                <Barcode className="h-3.5 w-3.5 text-slate-400" />
                                                {sku.sku}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">{sku.producto.nombre}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {sku.valores.map((v) => (
                                                    <Badge key={v.id} variant="secondary" className="text-xs">
                                                        {v.varianteValor.variante.nombre}: {v.varianteValor.valor}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">{formatCurrency(sku.precio_venta)}</td>
                                        <td className="px-4 py-3 text-right">{formatCurrency(sku.precio_compra)}</td>
                                        <td className="px-4 py-3 text-right font-medium">{formatNumber(sku.stock)}</td>
                                        <td className="px-4 py-3 text-right text-muted-foreground">{formatNumber(sku.stock_minimo)}</td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge className={sku.activo ? 'bg-emerald-500' : 'bg-slate-400'}>
                                                {sku.activo ? 'Activo' : 'Inactivo'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <button onClick={() => handleDelete(sku.id)} className="rounded-md p-1.5 transition-colors hover:bg-red-50" title="Eliminar">
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader className="px-6 pt-6">
                        <DialogTitle>Nuevo SKU</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-4 px-6 pb-6">
                            <div className="space-y-2">
                                <Label>Producto *</Label>
                                <select
                                    value={data.producto_id}
                                    onChange={(e) => setData('producto_id', e.target.value ? parseInt(e.target.value) : ('' as any))}
                                    className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    required
                                >
                                    <option value="">Seleccionar producto</option>
                                    {productos.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.nombre} {p.codigo ? `(${p.codigo})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-2">
                                <Label>Código SKU *</Label>
                                <Input
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                    placeholder="Ej: TCL-01-NEGRO-M"
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Precio Venta</Label>
                                    <Input
                                        type="number"
                                        step="any"
                                        min="0"
                                        value={data.precio_venta}
                                        onChange={(e) => setData('precio_venta', e.target.value)}
                                        placeholder="0"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Precio Compra</Label>
                                    <Input
                                        type="number"
                                        step="any"
                                        min="0"
                                        value={data.precio_compra}
                                        onChange={(e) => setData('precio_compra', e.target.value)}
                                        placeholder="0"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Stock</Label>
                                    <Input
                                        type="number"
                                        step="any"
                                        min="0"
                                        value={data.stock}
                                        onChange={(e) => setData('stock', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Stock Mínimo</Label>
                                    <Input
                                        type="number"
                                        step="any"
                                        min="0"
                                        value={data.stock_minimo}
                                        onChange={(e) => setData('stock_minimo', e.target.value)}
                                    />
                                </div>
                            </div>

                            {data.producto_id && getSelectedProductVariantes().length > 0 && (
                                <div className="space-y-2">
                                    <Label>Variantes *</Label>
                                    <p className="text-xs text-muted-foreground">Selecciona un valor por cada variante</p>
                                    <div className="space-y-3 rounded-lg border p-3">
                                        {getSelectedProductVariantes().map((variante) => (
                                            <div key={variante.id}>
                                                <p className="mb-1 text-xs font-semibold text-slate-600">{variante.nombre}</p>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {variante.valores.map((v) => (
                                                        <Badge
                                                            key={v.id}
                                                            variant={data.variantes.includes(v.id) ? 'default' : 'outline'}
                                                            className="cursor-pointer transition-colors"
                                                            onClick={() => toggleVarianteValor(v.id)}
                                                        >
                                                            {v.valor}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                        <DialogFooter className="px-6 pb-6">
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Guardando...' : 'Crear SKU'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
