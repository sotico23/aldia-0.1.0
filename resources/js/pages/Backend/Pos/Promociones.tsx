import { Head, useForm, router } from '@inertiajs/react';
import {
    Tag,
    Plus,
    Calendar,
    Percent,
    Zap,
    Pencil,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch-pos';
import { useCountry } from '@/hooks/use-country';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Promocion {
    id: number;
    nombre: string;
    tipo: 'porcentaje' | 'precio_fijo' | 'combo_2x1';
    valor: number;
    descripcion: string | null;
    skus: string[] | null;
    categoria_id: number | null;
    categoria?: { id: number; nombre: string } | null;
    compra_minima: number | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    activa: boolean;
    created_at: string;
}

interface Categoria {
    id: number;
    nombre: string;
}

interface Props {
    promociones: Promocion[];
    categorias: Categoria[];
    flash?: { success?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Caja POS', href: '/pos' },
    { title: 'Promociones CRM', href: '/pos/promociones' },
];

const tipoLabels: Record<string, string> = {
    porcentaje: '% Descuento',
    precio_fijo: 'Precio Fijo',
    combo_2x1: '2x1',
};

const tipoColors: Record<string, string> = {
    porcentaje: 'border-emerald-200 bg-emerald-50/20',
    precio_fijo: 'border-blue-200 bg-blue-50/20',
    combo_2x1: 'border-amber-200 bg-amber-50/20',
};

export default function Promociones({ promociones, categorias, flash }: Props) {
    const { code: countryCode, currency } = useCountry();
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('ventas.pos.viewAny');
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Promocion | null>(null);
    const [skuInput, setSkuInput] = useState('');

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
        processing,
    } = useForm({
        nombre: '',
        tipo: 'porcentaje' as 'porcentaje' | 'precio_fijo' | 'combo_2x1',
        valor: '',
        descripcion: '',
        skus: [] as string[],
        categoria_id: '' as number | '',
        compra_minima: '',
        fecha_inicio: '',
        fecha_fin: '',
        activa: true,
    });

    const handleOpenNew = () => {
        reset();
        setSkuInput('');
        setData({
            nombre: '',
            tipo: 'porcentaje',
            valor: '',
            descripcion: '',
            skus: [],
            categoria_id: '',
            compra_minima: '',
            fecha_inicio: '',
            fecha_fin: '',
            activa: true,
        });
        setEditando(null);
        setIsOpen(true);
    };

    const handleEdit = (p: Promocion) => {
        setEditando(p);
        setSkuInput('');
        setData({
            nombre: p.nombre,
            tipo: p.tipo,
            valor: String(p.valor),
            descripcion: p.descripcion || '',
            skus: p.skus || [],
            categoria_id: p.categoria_id || '',
            compra_minima: p.compra_minima ? String(p.compra_minima) : '',
            fecha_inicio: p.fecha_inicio ? p.fecha_inicio.split(' ')[0] : '',
            fecha_fin: p.fecha_fin ? p.fecha_fin.split(' ')[0] : '',
            activa: p.activa,
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar esta promoción?')) {
            destroy(`/pos/promociones/${id}`);
        }
    };

    const handleToggle = (promocion: Promocion) => {
        router.patch(`/pos/promociones/${promocion.id}/toggle`);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/pos/promociones/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/pos/promociones', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const addSku = () => {
        const sku = skuInput.trim();
        if (sku && !data.skus.includes(sku)) {
            setData('skus', [...data.skus, sku]);
        }
        setSkuInput('');
    };

    const removeSku = (sku: string) => {
        setData(
            'skus',
            data.skus.filter((s) => s !== sku),
        );
    };

    const formatTipoValor = (p: Promocion) => {
        if (p.tipo === 'porcentaje') {
            return `${p.valor}% Directo`;
        }
        if (p.tipo === 'precio_fijo') {
            return `$${Number(p.valor).toLocaleString(currency.locale)}`;
        }
        return '2x1';
    };

    const getTipoIcon = (tipo: string) => {
        if (tipo === 'porcentaje') return <Percent className="mr-1 h-3 w-3" />;
        if (tipo === 'precio_fijo')
            return <Zap className="mr-1 h-3 w-3 text-blue-500" />;
        return <Tag className="mr-1 inline-block h-3 w-3" />;
    };

    const getTipoBadgeColor = (tipo: string) => {
        if (tipo === 'porcentaje') return 'bg-emerald-500';
        if (tipo === 'precio_fijo') return 'bg-blue-500';
        return 'bg-amber-500';
    };

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return 'Sin fecha de caducidad';
        const d = new Date(dateStr);
        return d.toLocaleDateString(currency.locale, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
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
            <Head title="Promociones POS" />

            {flash?.success && (
                <div className="mx-4 mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}

            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Módulo de Promociones
                        </h1>
                        <p className="text-muted-foreground">
                            Crea reglas, combos y descuentos 100% automáticos
                            para la Caja
                        </p>
                    </div>
                    <Button onClick={handleOpenNew}>
                        <Plus className="mr-2 h-4 w-4" /> Nueva Regla
                    </Button>
                </div>

                {promociones.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <Tag className="mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">
                                Sin promociones
                            </h3>
                            <p className="mb-4 text-muted-foreground">
                                Crea tu primera promoción para empezar
                            </p>
                            <Button onClick={handleOpenNew}>
                                <Plus className="mr-2 h-4 w-4" /> Nueva Regla
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {promociones.map((p) => (
                            <Card
                                key={p.id}
                                className={`${p.activa ? tipoColors[p.tipo] : 'border-dashed opacity-60 grayscale'} transition-all hover:grayscale-0`}
                            >
                                <CardHeader className="pb-2">
                                    <div className="flex items-start justify-between">
                                        <div className="flex gap-2">
                                            <Badge
                                                className={getTipoBadgeColor(
                                                    p.tipo,
                                                )}
                                            >
                                                {p.activa
                                                    ? 'Activa'
                                                    : 'Inactiva'}
                                            </Badge>
                                            <Badge variant="outline">
                                                {tipoLabels[p.tipo]}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <button
                                                onClick={() => handleEdit(p)}
                                                className="rounded-md p-1 transition-colors hover:bg-white/50"
                                                title="Editar"
                                            >
                                                <Pencil className="h-4 w-4 text-muted-foreground" />
                                            </button>
                                            <button
                                                onClick={() =>
                                                    handleDelete(p.id)
                                                }
                                                className="rounded-md p-1 transition-colors hover:bg-white/50"
                                                title="Eliminar"
                                            >
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </button>
                                        </div>
                                    </div>
                                    <CardTitle className="mt-2 flex flex-col gap-1 text-xl">
                                        {p.nombre}
                                        <span className="flex items-center text-xs font-normal text-muted-foreground">
                                            <Calendar className="mr-1 h-3 w-3" />{' '}
                                            {formatDate(p.fecha_fin)}
                                        </span>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4 pt-2">
                                        <div className="flex justify-between border-b pb-2 text-sm">
                                            <span className="text-muted-foreground">
                                                Tipo Descuento:
                                            </span>
                                            <span className="flex items-center font-semibold">
                                                {getTipoIcon(p.tipo)}{' '}
                                                {formatTipoValor(p)}
                                            </span>
                                        </div>
                                        {p.categoria && (
                                            <div className="flex justify-between border-b pb-2 text-sm">
                                                <span className="text-muted-foreground">
                                                    Aplica a:
                                                </span>
                                                <span className="font-semibold">
                                                    {p.categoria.nombre}
                                                </span>
                                            </div>
                                        )}
                                        {p.skus && p.skus.length > 0 && (
                                            <div className="flex justify-between border-b pb-2 text-sm">
                                                <span className="text-muted-foreground">
                                                    SKUs:
                                                </span>
                                                <span className="font-semibold underline">
                                                    {p.skus.join(' + ')}
                                                </span>
                                            </div>
                                        )}
                                        {p.compra_minima && (
                                            <div className="flex justify-between border-b pb-2 text-sm">
                                                <span className="text-muted-foreground">
                                                    Condición:
                                                </span>
                                <span className="font-semibold">
                                                     Mín. compra $
                                                    {Number(
                                                        p.compra_minima,
                                                    ).toLocaleString(currency.locale)}
                                                </span>
                                            </div>
                                        )}
                                        {p.descripcion && (
                                            <p className="text-xs text-muted-foreground italic">
                                                {p.descripcion}
                                            </p>
                                        )}
                                        <div className="flex items-center justify-between pt-2">
                                            <span className="text-sm text-muted-foreground">
                                                Activar/Desactivar
                                            </span>
                                            <Switch
                                                checked={p.activa}
                                                onCheckedChange={() =>
                                                    handleToggle(p)
                                                }
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="w-full sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nueva'} Promoción
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="space-y-2">
                                <Label>Nombre *</Label>
                                <Input
                                    value={data.nombre}
                                    onChange={(e) =>
                                        setData('nombre', e.target.value)
                                    }
                                    placeholder="Ej: Black Friday"
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div className="space-y-2">
                                    <Label>Tipo de descuento *</Label>
                                    <select
                                        value={data.tipo}
                                        onChange={(e) =>
                                            setData(
                                                'tipo',
                                                e.target.value as any,
                                            )
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        <option value="porcentaje">
                                            % Descuento
                                        </option>
                                        <option value="precio_fijo">
                                            Precio Fijo
                                        </option>
                                        <option value="combo_2x1">
                                            2x1 (Compra 2, Paga 1)
                                        </option>
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>
                                        {data.tipo === 'porcentaje'
                                            ? 'Porcentaje (%) *'
                                            : data.tipo === 'precio_fijo'
                                              ? 'Precio ($) *'
                                              : 'Valor (0 para 2x1)'}
                                    </Label>
                                    <Input
                                        type="number"
                                        step="any"
                                        min="0"
                                        value={data.valor}
                                        onChange={(e) =>
                                            setData('valor', e.target.value)
                                        }
                                        required
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
                                    placeholder="Describe la promoción..."
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div className="space-y-2">
                                    <Label>Categoría</Label>
                                    <select
                                        value={data.categoria_id}
                                        onChange={(e) =>
                                            setData(
                                                'categoria_id',
                                                e.target.value
                                                    ? parseInt(e.target.value)
                                                    : ('' as any),
                                            )
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        <option value="">Toda la tienda</option>
                                        {categorias.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.nombre}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Compra mínima ($)</Label>
                                    <Input
                                        type="number"
                                        step="any"
                                        min="0"
                                        value={data.compra_minima}
                                        onChange={(e) =>
                                            setData(
                                                'compra_minima',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ej: 10000"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div className="space-y-2">
                                    <Label>Fecha inicio</Label>
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
                                    <Label>Fecha fin</Label>
                                    <Input
                                        type="date"
                                        value={data.fecha_fin}
                                        onChange={(e) =>
                                            setData('fecha_fin', e.target.value)
                                        }
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>SKUs (Productos específicos)</Label>
                                <div className="flex gap-2">
                                    <Input
                                        value={skuInput}
                                        onChange={(e) =>
                                            setSkuInput(e.target.value)
                                        }
                                        placeholder="Ej: TCL-01"
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addSku();
                                            }
                                        }}
                                        className="flex-1"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={addSku}
                                    >
                                        Agregar
                                    </Button>
                                </div>
                                {data.skus.length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {data.skus.map((sku) => (
                                            <Badge
                                                key={sku}
                                                variant="outline"
                                                className="flex items-center gap-1"
                                            >
                                                {sku}
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeSku(sku)
                                                    }
                                                    className="ml-1 hover:text-red-500"
                                                >
                                                    <X className="h-3 w-3" />
                                                </button>
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="flex items-center justify-between">
                                <Label>Activa</Label>
                                <Switch
                                    checked={data.activa}
                                    onCheckedChange={(v) =>
                                        setData('activa', v)
                                    }
                                />
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
                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? 'Guardando...'
                                    : editando
                                      ? 'Actualizar'
                                      : 'Crear'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
