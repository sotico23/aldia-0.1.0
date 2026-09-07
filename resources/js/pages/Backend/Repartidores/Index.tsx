import { useForm } from '@inertiajs/react';
import { Pencil, PlusCircle, UserRound } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';

// ============================================================================
// ZONA DE REPARTIDORES - ADMIN DE REPARTIDORES (CRUD ligero + capacidad)
// ============================================================================

type Repartidor = {
    id: number;
    user_id: number;
    nombre: string | null;
    email: string | null;
    estado: string;
    radio_km: number;
    capacidad_max: number;
    pedidos_activos: number;
    lat: number | null;
    lng: number | null;
    last_position_at: string | null;
};

type Candidato = {
    id: number;
    name: string;
    email: string;
};

type Props = {
    owner_id: number;
    repartidores: Repartidor[];
    candidatos: Candidato[];
};

const estadoBadge: Record<string, { label: string; className: string }> = {
    disponible: {
        label: 'Disponible',
        className: 'bg-emerald-100 text-emerald-700',
    },
    ocupado: { label: 'Ocupado', className: 'bg-red-100 text-red-700' },
    offline: { label: 'Offline', className: 'bg-gray-100 text-gray-600' },
};

const formatoFecha = (fecha: string | null): string =>
    fecha
        ? new Date(fecha).toLocaleString('es-CL', {
              day: '2-digit',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

const formVacio = {
    user_id: '',
    estado: 'disponible',
    radio_km: '10',
    capacidad_max: '5',
};

export default function RepartidoresIndex({ repartidores, candidatos }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const { data, setData, post, put, reset, processing, errors } =
        useForm(formVacio);

    const abrirNueva = () => {
        reset();
        setModalOpen(true);
    };

    const abrirEditar = (repartidor: Repartidor) => {
        setData({
            user_id: String(repartidor.user_id),
            estado: repartidor.estado,
            radio_km: repartidor.radio_km.toFixed(2),
            capacidad_max: String(repartidor.capacidad_max),
        });
        setEditingId(repartidor.id);
        setModalOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                setEditingId(null);
            },
        };

        if (editingId) {
            put(`/repartidores/${editingId}`, options);
        } else {
            post('/repartidores', options);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Repartidores', href: '/repartidores' }]}
        >
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">
                            Repartidores
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Gestiona a los conductores, su radio de cobertura y
                            capacidad de repartos
                        </p>
                    </div>
                    <Button
                        onClick={abrirNueva}
                        disabled={candidatos.length === 0}
                    >
                        <PlusCircle className="mr-2 h-4 w-4" />
                        Nuevo repartidor
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {repartidores.length}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Disponibles
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold text-emerald-500">
                                {
                                    repartidores.filter(
                                        (r) => r.estado === 'disponible',
                                    ).length
                                }
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Candidatos sin vincular
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {candidatos.length}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Lista de repartidores</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Repartidor</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Radio</TableHead>
                                    <TableHead>Capacidad</TableHead>
                                    <TableHead>Última posición</TableHead>
                                    <TableHead>Actualización</TableHead>
                                    <TableHead className="text-right">
                                        Acciones
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {repartidores.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            Aún no hay repartidores. Crea el
                                            primero con el botón «Nuevo
                                            repartidor».
                                        </TableCell>
                                    </TableRow>
                                )}
                                {repartidores.map((repartidor) => {
                                    const badge = estadoBadge[
                                        repartidor.estado
                                    ] ?? {
                                        label: repartidor.estado,
                                        className: 'bg-gray-100 text-gray-600',
                                    };
                                    const posicion =
                                        repartidor.lat !== null &&
                                        repartidor.lng !== null
                                            ? `${repartidor.lat.toFixed(4)}, ${repartidor.lng.toFixed(4)}`
                                            : '—';

                                    return (
                                        <TableRow key={repartidor.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="flex size-9 items-center justify-center rounded-full bg-muted">
                                                        <UserRound className="size-4 text-muted-foreground" />
                                                    </div>
                                                    <div>
                                                        <p className="font-medium">
                                                            {repartidor.nombre ??
                                                                'Sin nombre'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {repartidor.email}
                                                        </p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={badge.className}
                                                >
                                                    {badge.label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {repartidor.radio_km.toFixed(1)}{' '}
                                                km
                                            </TableCell>
                                            <TableCell>
                                                {repartidor.pedidos_activos}/
                                                {repartidor.capacidad_max}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {posicion}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatoFecha(
                                                    repartidor.last_position_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        abrirEditar(repartidor)
                                                    }
                                                >
                                                    <Pencil className="mr-1 h-4 w-4" />
                                                    Editar
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingId
                                ? 'Editar repartidor'
                                : 'Nuevo repartidor'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        {!editingId && (
                            <div className="space-y-2">
                                <Label htmlFor="user_id">Usuario</Label>
                                <Select
                                    value={data.user_id}
                                    onValueChange={(value) =>
                                        setData('user_id', value)
                                    }
                                >
                                    <SelectTrigger id="user_id">
                                        <SelectValue placeholder="Selecciona un usuario" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {candidatos.map((candidato) => (
                                            <SelectItem
                                                key={candidato.id}
                                                value={String(candidato.id)}
                                            >
                                                {candidato.name} —{' '}
                                                {candidato.email}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.user_id && (
                                    <p className="text-sm text-red-600">
                                        {errors.user_id}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="estado">Estado</Label>
                            <Select
                                value={data.estado}
                                onValueChange={(value) =>
                                    setData('estado', value)
                                }
                            >
                                <SelectTrigger id="estado">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="disponible">
                                        Disponible
                                    </SelectItem>
                                    <SelectItem value="ocupado">
                                        Ocupado
                                    </SelectItem>
                                    <SelectItem value="offline">
                                        Offline
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.estado && (
                                <p className="text-sm text-red-600">
                                    {errors.estado}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="radio_km">
                                    Radio de cobertura (km)
                                </Label>
                                <Input
                                    id="radio_km"
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    max="500"
                                    value={data.radio_km}
                                    onChange={(e) =>
                                        setData('radio_km', e.target.value)
                                    }
                                />
                                {errors.radio_km && (
                                    <p className="text-sm text-red-600">
                                        {errors.radio_km}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="capacidad_max">
                                    Capacidad máxima
                                </Label>
                                <Input
                                    id="capacidad_max"
                                    type="number"
                                    min="1"
                                    max="999"
                                    value={data.capacidad_max}
                                    onChange={(e) =>
                                        setData('capacidad_max', e.target.value)
                                    }
                                />
                                {errors.capacidad_max && (
                                    <p className="text-sm text-red-600">
                                        {errors.capacidad_max}
                                    </p>
                                )}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setModalOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    processing || (!editingId && !data.user_id)
                                }
                            >
                                {processing
                                    ? 'Guardando...'
                                    : editingId
                                      ? 'Guardar cambios'
                                      : 'Crear repartidor'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
