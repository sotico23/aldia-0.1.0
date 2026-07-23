import { Head, useForm } from '@inertiajs/react';
import { Layers, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface VarianteValor {
    id: number;
    valor: string;
    codigo: string | null;
}

interface Variante {
    id: number;
    nombre: string;
    tipo: string;
    activo: boolean;
    valores: VarianteValor[];
}

interface Props {
    variantes: Variante[];
    flash?: { success?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Caja POS', href: '/pos' },
    { title: 'Variantes / SKUs', href: '/pos/variantes' },
];

export default function Variantes({ variantes, flash }: Props) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('ventas.variantes.viewAny');
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Variante | null>(null);
    const [valorInput, setValorInput] = useState('');

    const { data, setData, post, put, delete: destroy, reset, processing } = useForm({
        nombre: '',
        tipo: '',
        valores: [] as string[],
    });

    const handleOpenNew = () => {
        reset();
        setValorInput('');
        setEditando(null);
        setIsOpen(true);
    };

    const handleEdit = (v: Variante) => {
        setEditando(v);
        setValorInput('');
        setData({
            nombre: v.nombre,
            tipo: v.tipo,
            valores: v.valores.map((vl) => vl.valor),
        });
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar esta variante? También se eliminarán todos sus valores.')) {
            destroy(`/pos/variantes/${id}`);
        }
    };

    const addValor = () => {
        const val = valorInput.trim();
        if (val && !data.valores.includes(val)) {
            setData('valores', [...data.valores, val]);
        }
        setValorInput('');
    };

    const removeValor = (val: string) => {
        setData('valores', data.valores.filter((v) => v !== val));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/pos/variantes/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/pos/variantes', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
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
            <Head title="Variantes / SKUs" />

            {flash?.success && (
                <div className="mx-4 mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}

            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Variantes / SKUs</h1>
                        <p className="text-muted-foreground">
                            Administra variantes de productos como talla, color, sabor, etc.
                        </p>
                    </div>
                    <Button onClick={handleOpenNew}>
                        <Plus className="mr-2 h-4 w-4" /> Nueva Variante
                    </Button>
                </div>

                {variantes.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <Layers className="mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">Sin variantes</h3>
                            <p className="mb-4 text-muted-foreground">Crea tu primera variante para empezar</p>
                            <Button onClick={handleOpenNew}>
                                <Plus className="mr-2 h-4 w-4" /> Nueva Variante
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {variantes.map((v) => (
                            <Card key={v.id} className="border-slate-200 transition-all hover:shadow-md">
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-2">
                                            <Layers className="h-5 w-5 text-indigo-500" />
                                            <div>
                                                <CardTitle className="text-lg">{v.nombre}</CardTitle>
                                                <Badge variant="outline" className="mt-1 text-xs">{v.tipo}</Badge>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <button onClick={() => handleEdit(v)} className="rounded-md p-1 transition-colors hover:bg-slate-100" title="Editar">
                                                <Pencil className="h-4 w-4 text-muted-foreground" />
                                            </button>
                                            <button onClick={() => handleDelete(v.id)} className="rounded-md p-1 transition-colors hover:bg-red-50" title="Eliminar">
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-1.5">
                                        {v.valores.map((vl) => (
                                            <Badge key={vl.id} variant="secondary" className="text-xs">
                                                {vl.valor}
                                                {vl.codigo && <span className="ml-1 text-muted-foreground">({vl.codigo})</span>}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader className="px-6 pt-6">
                        <DialogTitle>{editando ? 'Editar' : 'Nueva'} Variante</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-4 px-6 pb-6">
                            <div className="space-y-2">
                                <Label>Nombre *</Label>
                                <Input
                                    value={data.nombre}
                                    onChange={(e) => setData('nombre', e.target.value)}
                                    placeholder="Ej: Talla, Color, Sabor"
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Tipo *</Label>
                                <select
                                    value={data.tipo}
                                    onChange={(e) => setData('tipo', e.target.value)}
                                    className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    required
                                >
                                    <option value="">Seleccionar tipo</option>
                                    <option value="texto">Texto</option>
                                    <option value="color">Color</option>
                                    <option value="numero">Número</option>
                                </select>
                            </div>

                            <div className="space-y-2">
                                <Label>Valores *</Label>
                                <div className="flex gap-2">
                                    <Input
                                        value={valorInput}
                                        onChange={(e) => setValorInput(e.target.value)}
                                        placeholder="Ej: S, M, L"
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addValor();
                                            }
                                        }}
                                        className="flex-1"
                                    />
                                    <Button type="button" variant="outline" onClick={addValor}>
                                        Agregar
                                    </Button>
                                </div>
                                {data.valores.length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {data.valores.map((val) => (
                                            <Badge key={val} variant="outline" className="flex items-center gap-1">
                                                {val}
                                                <button type="button" onClick={() => removeValor(val)} className="ml-1 hover:text-red-500">
                                                    <X className="h-3 w-3" />
                                                </button>
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                        <DialogFooter className="px-6 pb-6">
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Guardando...' : editando ? 'Actualizar' : 'Crear'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
