import axios from 'axios';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    CheckCircle2,
    Loader2,
    Mail,
    Phone,
    RefreshCw,
    Save,
    ShieldAlert,
    User,
    X,
} from 'lucide-react';

type RepartidorSolicitud = {
    id: number;
    user_id: number;
    nombre: string;
    rut: string | null;
    email: string | null;
    telefono: string | null;
    fecha_registro: string | null;
    documentos: Record<string, unknown> | null;
    estado: string;
    approval_status: string;
    driver_type: string;
};

export default function RepartidorSolicitudes() {
    const [solicitudes, setSolicitudes] = useState<RepartidorSolicitud[]>([]);
    const [loading, setLoading] = useState(false);
    const [rejecting, setRejecting] = useState<number | null>(null);
    const [approving, setApproving] = useState<number | null>(null);
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [rejectMotivo, setRejectMotivo] = useState('');

    useEffect(() => {
        cargarSolicitudes();
    }, []);

    const cargarSolicitudes = async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/v1/admin/repartidores/solicitudes');
            setSolicitudes(res.data.data ?? []);
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al cargar solicitudes.');
        } finally {
            setLoading(false);
        }
    };

    const aprobar = async (id: number) => {
        setApproving(id);
        try {
            const res = await axios.post(`/api/v1/admin/repartidores/${id}/aprobar`);
            toast.success(res.data.message || 'Repartidor aprobado.');
            setSolicitudes((prev) => prev.filter((s) => s.id !== id));
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al aprobar.');
        } finally {
            setApproving(null);
        }
    };

    const rechazar = async (id: number, motivo: string) => {
        setRejecting(id);
        try {
            const res = await axios.post(`/api/v1/admin/repartidores/${id}/rechazar`, {
                motivo,
            });
            toast.success(res.data.message || 'Solicitud rechazada.');
            setSolicitudes((prev) => prev.filter((s) => s.id !== id));
            setRejectDialogOpen(false);
            setRejectId(null);
            setRejectMotivo('');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al rechazar.');
        } finally {
            setRejecting(null);
        }
    };

    const abrirDocumento = (url: string) => {
        window.open(url, '_blank');
    };

    const estadoBadge = (estado: string) => {
        const colores: Record<string, string> = {
            pending_approval: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-500',
            approved: 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300 border border-green-200 dark:border-green-500',
            rejected: 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300 border border-red-200 dark:border-red-500',
        };
        return (
            <span className={`px-2 py-0.5 rounded-full text-xs font-medium border ${colores[estado] || 'bg-muted text-muted-foreground'}`}>
                {estado.replace('_', ' ')}
            </span>
        );
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-bold">Solicitudes de Repartidores</h2>
                    <p className="text-sm text-muted-foreground">
                        Gestiona las solicitudes de repartidores freelancers pendientes de aprobación.
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={cargarSolicitudes}
                    disabled={loading}
                    className="gap-2"
                >
                    <Loader2 className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                    Refrescar
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Solicitudes Pendientes</CardTitle>
                    <CardDescription>
                        Repartidores esperando aprobación para comenzar a recibir entregas.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {loading && (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin" />
                        </div>
                    )}
                    {!loading && solicitudes.length === 0 && (
                        <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                            <ShieldAlert className="h-8 w-8 opacity-50" />
                            <p className="mt-2 text-sm">No hay solicitudes pendientes.</p>
                        </div>
                    )}
                    {!loading && solicitudes.length > 0 && (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[20%]">Nombre</TableHead>
                                    <TableHead className="w-[15%]">RUT / ID</TableHead>
                                    <TableHead className="w-[20%]">Email</TableHead>
                                    <TableHead className="w-[15%]">Teléfono</TableHead>
                                    <TableHead className="w-[15%]">Fecha Registro</TableHead>
                                    <TableHead className="w-[10%]">Documentos</TableHead>
                                    <TableHead className="w-[10%]">Estado</TableHead>
                                    <TableHead className="w-[15%] text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {solicitudes.map((s) => (
                                    <TableRow key={s.id}>
                                        <TableCell className="font-medium">
                                            <User className="mr-2 h-4 w-4 text-muted-foreground" />
                                            {s.nombre}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {s.rut ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            <Mail className="mr-1.5 h-3.5 w-3.5 text-muted-foreground" />
                                            {s.email ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            <Phone className="mr-1.5 h-3.5 w-3.5 text-muted-foreground" />
                                            {s.telefono ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-xs">
                                            {s.fecha_registro
                                                ? new Date(s.fecha_registro).toLocaleDateString('es-CL')
                                                : '-'}
                                        </TableCell>
                                        <TableCell>
                                            {s.documentos && Object.keys(s.documentos).length > 0 ? (
                                                <div className="flex gap-1">
                                                    {Object.entries(s.documentos).map(([key, valor]) => (
                                                        typeof valor === 'string' && valor.startsWith('http') ? (
                                                            <Button
                                                                key={key}
                                                                variant="outline"
                                                                size="sm"
                                    onClick={() => abrirDocumento(valor)}
                                                                className="h-7 px-2 text-xs gap-1"
                                                            >
                                                                {key.replace('_', ' ')}
                                                            </Button>
                                                        ) : null
                                                    ))}
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">Sin documentos</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {estadoBadge(s.approval_status)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex gap-1 justify-end">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => aprobar(s.id)}
                                                    disabled={approving === s.id || rejecting === s.id}
                                                    className="h-7 px-3 text-xs"
                                                >
                                                    {approving === s.id ? (
                                                        <Loader2 className="h-3 w-3 animate-spin" />
                                                    ) : (
                                                        <CheckCircle2 className="h-3 w-3" />
                                                    )}
                                                    Aprobar
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => {
                                                        setRejectId(s.id);
                                                        setRejectDialogOpen(true);
                                                    }}
                                                    disabled={rejecting === s.id || approving === s.id}
                                                    className="h-7 px-3 text-xs"
                                                >
                                                    {rejecting === s.id ? (
                                                        <Loader2 className="h-3 w-3 animate-spin" />
                                                    ) : (
                                                        <X className="h-3 w-3" />
                                                    )}
                                                    Rechazar
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Modal de Rechazo */}
            <Dialog open={rejectDialogOpen} onOpenChange={setRejectDialogOpen}>
                <DialogTrigger asChild>
                    <Button type="button">dummy</Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Rechazar Solicitud</DialogTitle>
                        <DialogDescription>
                            Ingresa un motivo opcional para el rechazo. El repartidor
                            recibirá una notificación con este mensaje.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Input
                            id="reject_motivo"
                            placeholder="Motivo del rechazo (opcional)"
                            value={rejectMotivo}
                            onChange={(e) => setRejectMotivo(e.target.value)}
                            className="w-full"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setRejectDialogOpen(false);
                                setRejectId(null);
                                setRejectMotivo('');
                            }}
                            className="h-9"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                if (rejectId) {
                                    rechazar(rejectId, rejectMotivo);
                                }
                            }}
                            disabled={rejecting !== null}
                            className="h-9 bg-red-500 text-white"
                        >
                            {rejecting !== null ? (
                                <Loader2 className="h-3 w-3 animate-spin" />
                            ) : (
                                <Save className="h-3 w-3" />
                            )}
                            Confirmar Rechazo
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
