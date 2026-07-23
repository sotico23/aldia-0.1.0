import { Head, useForm } from '@inertiajs/react';
import { LayoutGrid, List, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Configuracion {
    id: number;
    clave: string;
    valor: string | null;
    tipo: string | null;
    descripcion: string | null;
    categoria: string | null;
    editable: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Configuración', href: '/configuracion' },
];

const tipos = ['string', 'integer', 'boolean', 'array', 'json'];
const categorias = [
    'general',
    'email',
    'pago',
    'impuesto',
    'notificacion',
    'otro',
];

export default function Index({
    configuraciones,
}: {
    configuraciones: Configuracion[];
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('admin.configuracion.create');
    const canEdit = hasPermission('admin.configuracion.edit');
    const canDelete = hasPermission('admin.configuracion.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Configuracion | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        reset,
    } = useForm({
        clave: '',
        valor: '',
        tipo: 'string',
        descripcion: '',
        categoria: 'general',
        editable: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/configuracion/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/configuracion', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (c: Configuracion) => {
        setEditando(c);
        setData({
            clave: c.clave,
            valor: c.valor || '',
            tipo: c.tipo || 'string',
            descripcion: c.descripcion || '',
            categoria: c.categoria || 'general',
            editable: c.editable,
        });
        setIsOpen(true);
    };
    const handleNew = () => {
        reset();
        setData({
            clave: '',
            valor: '',
            tipo: 'string',
            descripcion: '',
            categoria: 'general',
            editable: true,
        });
        setEditando(null);
        setIsOpen(true);
    };
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar?')) destroy(`/configuracion/${id}`);
    };

    return (
        <>
            <Head title="Configuración" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Configuración
                            </h1>
                            <p className="text-muted-foreground">
                                Parámetros del sistema
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
                                    <CardTitle>Configuraciones</CardTitle>
                                    <CardDescription>
                                        {configuraciones.length} registros
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-1 rounded-lg border p-0.5">
                                    <Button
                                        variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('table')}
                                        className="h-8 w-8 p-0"
                                    >
                                        <List className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('cards')}
                                        className="h-8 w-8 p-0"
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {viewMode === 'table' ? (
                                configuraciones.length === 0 ? (
                                    <p className="py-8 text-center text-muted-foreground">
                                        No hay configuraciones
                                    </p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="py-2 text-left">
                                                        Clave
                                                    </th>
                                                    <th className="py-2 text-left">
                                                        Valor
                                                    </th>
                                                    <th className="py-2 text-left">
                                                        Categoría
                                                    </th>
                                                    <th className="py-2 text-center">
                                                        Editable
                                                    </th>
                                                    <th className="py-2 text-right">
                                                        Acciones
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {configuraciones.map((c) => (
                                                    <tr
                                                        key={c.id}
                                                        className="border-b"
                                                    >
                                                        <td className="py-2 font-mono text-sm">
                                                            {c.clave}
                                                        </td>
                                                        <td className="py-2">
                                                            {c.valor || '-'}
                                                        </td>
                                                        <td className="py-2">
                                                            {c.categoria || '-'}
                                                        </td>
                                                        <td className="py-2 text-center">
                                                            <Badge
                                                                className={
                                                                    c.editable
                                                                        ? 'bg-green-500'
                                                                        : 'bg-gray-500'
                                                                }
                                                            >
                                                                {c.editable
                                                                    ? 'Sí'
                                                                    : 'No'}
                                                            </Badge>
                                                        </td>
                                                        <td className="py-2 text-right">
                                                            {canEdit && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
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
                                                                    size="icon"
                                                                    onClick={() =>
                                                                        handleDelete(
                                                                            c.id,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )
                            ) : (
                                configuraciones.length === 0 ? (
                                    <p className="py-8 text-center text-muted-foreground">
                                        No hay configuraciones
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {configuraciones.map((c) => (
                                            <div key={c.id} className="rounded-lg border p-4">
                                                <div className="mb-2 flex items-start justify-between">
                                                    <div>
                                                        <p className="font-mono text-sm font-bold">{c.clave}</p>
                                                        <p className="text-xs text-muted-foreground">{c.categoria || '-'}</p>
                                                    </div>
                                                    <Badge className={c.editable ? 'bg-green-500' : 'bg-gray-500'}>
                                                        {c.editable ? 'Sí' : 'No'}
                                                    </Badge>
                                                </div>
                                                <p className="mb-3 text-sm">{c.valor || '-'}</p>
                                                <div className="flex justify-end gap-1">
                                                    {canEdit && (
                                                        <Button variant="ghost" size="icon" onClick={() => handleEdit(c)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button variant="ghost" size="icon" onClick={() => handleDelete(c.id)}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nueva'} Configuración
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Clave *</Label>
                                    <Input
                                        value={data.clave}
                                        onChange={(e) =>
                                            setData('clave', e.target.value)
                                        }
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Tipo</Label>
                                    <select
                                        value={data.tipo}
                                        onChange={(e) =>
                                            setData('tipo', e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        {tipos.map((t) => (
                                            <option key={t} value={t}>
                                                {t}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Valor</Label>
                                <Input
                                    value={data.valor}
                                    onChange={(e) =>
                                        setData('valor', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Categoría</Label>
                                    <select
                                        value={data.categoria}
                                        onChange={(e) =>
                                            setData('categoria', e.target.value)
                                        }
                                        className="flex h-10 w-full rounded-md border bg-background px-3 py-2"
                                    >
                                        {categorias.map((c) => (
                                            <option key={c} value={c}>
                                                {c}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="flex items-center gap-2 pt-6">
                                    <input
                                        type="checkbox"
                                        checked={data.editable}
                                        onChange={(e) =>
                                            setData(
                                                'editable',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <Label>Editable</Label>
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
