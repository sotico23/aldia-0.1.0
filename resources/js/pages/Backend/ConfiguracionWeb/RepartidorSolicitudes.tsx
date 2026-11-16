import axios from 'axios';
import {
    CheckCircle2,
    Eye,
    FileText,
    Loader2,
    Mail,
    Image as ImageIcon,
    Phone,
    Save,
    ShieldAlert,
    User,
    X,
} from 'lucide-react';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import {
    aprobar as aprobarRoute,
    detalle as detalleRoute,
    rechazar as rechazarRoute,
    solicitudes as solicitudesRoute,
} from '@/routes/admin/repartidores';

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

type RepartidorReview = {
    id: number;
    solicitud: { tipo: string; estado: string; fecha: string | null };
    persona: { nombre: string | null; email: string | null; telefono: string | null; rut: string | null };
    vehiculo: { tipo: string | null; radio_km: number | null };
    empresa: { codigo: number; nombre: string } | null;
    independiente: boolean;
    antecedentes: { aceptados: boolean; fecha: string | null };
    documentos: Array<{
        tipo: string;
        label: string;
        nombre: string;
        url: string | null;
        mime_type: string | null;
        bytes: number | null;
        subido_at: string | null;
    }>;
};

function ReviewField({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0 rounded-lg border bg-muted/30 p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 break-words text-sm font-medium">{value || 'No informado'}</p>
        </div>
    );
}

function formatReviewDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('es-CL') : 'No registrado';
}

function formatDocumentSize(bytes: number | null): string {
    if (bytes === null) return 'Tamaño no disponible';
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function RepartidorSolicitudes() {
    const { hasRole } = usePermissions();
    const isMaster = hasRole('Master');
    const [solicitudes, setSolicitudes] = useState<RepartidorSolicitud[]>([]);
    const [loading, setLoading] = useState(false);
    const [rejecting, setRejecting] = useState<number | null>(null);
    const [approving, setApproving] = useState<number | null>(null);
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [rejectMotivo, setRejectMotivo] = useState('');
    const [reviewOpen, setReviewOpen] = useState(false);
    const [reviewLoading, setReviewLoading] = useState(false);
    const [review, setReview] = useState<RepartidorReview | null>(null);
    const [reviewError, setReviewError] = useState<string | null>(null);

    useEffect(() => {
        cargarSolicitudes();
    }, []);

    async function cargarSolicitudes(): Promise<void> {
        setLoading(true);
        try {
            const res = await axios.get(solicitudesRoute.url());
            setSolicitudes(res.data.data ?? []);
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al cargar solicitudes.');
        } finally {
            setLoading(false);
        }
    }

    const aprobar = async (id: number) => {
        setApproving(id);
        try {
            const res = await axios.post(aprobarRoute(id).url);
            toast.success(res.data.message || 'Repartidor aprobado.');
            setSolicitudes((prev) => prev.filter((s) => s.id !== id));
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al aprobar.');
        } finally {
            setApproving(null);
        }
    };

    const revisar = async (id: number) => {
        setReviewOpen(true);
        setReview(null);
        setReviewError(null);
        setReviewLoading(true);
        try {
            const res = await axios.get(detalleRoute(id).url);
            setReview(res.data.data);
        } catch (error: any) {
            const message = error.response?.data?.message || 'No se pudieron cargar los datos de la solicitud.';
            setReviewError(message);
            toast.error(message);
        } finally {
            setReviewLoading(false);
        }
    };

    const rechazar = async (id: number, motivo: string) => {
        setRejecting(id);
        try {
            const res = await axios.post(rechazarRoute(id).url, {
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
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <h2 className="text-xl font-bold">Solicitudes de Repartidores</h2>
                    <p className="text-sm text-muted-foreground">
                        Gestiona las solicitudes de repartidores freelancers pendientes de aprobación.
                    </p>
                    {!isMaster && (
                        <p className="mt-1 text-sm text-amber-700">
                            Solo el rol Master puede aprobar solicitudes.
                        </p>
                    )}
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={cargarSolicitudes}
                    disabled={loading}
                    className="w-full gap-2 sm:w-auto"
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
                        <div className="w-full max-w-full overflow-x-auto">
                        <Table className="min-w-[920px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[20%]">Nombre</TableHead>
                                    <TableHead className="w-[15%]">RUT / ID</TableHead>
                                    <TableHead className="w-[20%]">Email</TableHead>
                                    <TableHead className="w-[15%]">Teléfono</TableHead>
                                    <TableHead className="w-[15%]">Fecha Registro</TableHead>
                                    <TableHead className="w-[10%]">Archivos</TableHead>
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
                                            <span className="text-xs text-muted-foreground">
                                                {Object.keys(s.documentos ?? {}).length} archivo(s)
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {estadoBadge(s.approval_status)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex gap-1 justify-end">
                                                {isMaster && <Button
                                                    variant="outline"
                                                    size="sm"
                                                    aria-label={`Revisar solicitud de ${s.nombre}`}
                                                    onClick={() => revisar(s.id)}
                                                    disabled={approving === s.id || rejecting === s.id}
                                                    className="h-7 gap-1 px-2 text-xs"
                                                >
                                                    <Eye className="h-3 w-3" />
                                                    Revisar
                                                </Button>}
                                                {isMaster && <Button
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
                                                </Button>}
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
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Modal de Rechazo */}
            <Dialog open={rejectDialogOpen} onOpenChange={setRejectDialogOpen}>
                <DialogContent className="w-[calc(100vw-1rem)] sm:w-full">
                    <DialogHeader>
                        <DialogTitle>Rechazar Solicitud</DialogTitle>
                        <DialogDescription>
                            Ingresa un motivo opcional para el rechazo. El repartidor
                            recibirá una notificación con este mensaje.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="flex-col-reverse sm:flex-row">
                        <Input
                            id="reject_motivo"
                            placeholder="Motivo del rechazo (opcional)"
                            value={rejectMotivo}
                            onChange={(e) => setRejectMotivo(e.target.value)}
                            className="w-full sm:flex-1"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setRejectDialogOpen(false);
                                setRejectId(null);
                                setRejectMotivo('');
                            }}
                            className="h-9 w-full sm:w-auto"
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
                            className="h-9 w-full bg-red-500 text-white sm:w-auto"
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

            <Dialog open={reviewOpen} onOpenChange={setReviewOpen}>
                <DialogContent className="max-h-[90dvh] w-[calc(100vw-1rem)] max-w-4xl overflow-y-auto sm:w-full">
                    <DialogHeader>
                        <DialogTitle>Revisión de solicitud de repartidor</DialogTitle>
                        <DialogDescription>
                            Revisa los datos enviados y los archivos antes de tomar una decisión.
                        </DialogDescription>
                    </DialogHeader>
                    {reviewLoading && (
                        <div className="flex min-h-40 items-center justify-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="h-5 w-5 animate-spin" />
                            Cargando datos de la solicitud…
                        </div>
                    )}
                    {reviewError && (
                        <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                            {reviewError}
                        </p>
                    )}
                    {review && (
                        <div className="space-y-5">
                            <section className="space-y-3">
                                <h3 className="font-semibold">Datos personales</h3>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    <ReviewField label="Nombre completo" value={review.persona.nombre ?? ''} />
                                    <ReviewField label="RUT / identificación" value={review.persona.rut ?? ''} />
                                    <ReviewField label="Correo electrónico" value={review.persona.email ?? ''} />
                                    <ReviewField label="Teléfono" value={review.persona.telefono ?? ''} />
                                    <ReviewField label="Fecha de solicitud" value={formatReviewDate(review.solicitud.fecha)} />
                                    <ReviewField label="Estado" value={review.solicitud.estado.replaceAll('_', ' ')} />
                                </div>
                            </section>

                            <section className="space-y-3">
                                <h3 className="font-semibold">Vehículo y cobertura</h3>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <ReviewField label="Tipo de vehículo" value={review.vehiculo.tipo ?? ''} />
                                    <ReviewField label="Radio de reparto" value={review.vehiculo.radio_km !== null ? `${review.vehiculo.radio_km} km` : ''} />
                                </div>
                            </section>

                            <section className="space-y-3">
                                <h3 className="font-semibold">Empresa y antecedentes</h3>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <ReviewField
                                        label="Vinculación"
                                        value={review.empresa ? `${review.empresa.nombre} (código ${review.empresa.codigo})` : 'Independiente, sin empresa'}
                                    />
                                    <ReviewField
                                        label="Autorización para verificar antecedentes"
                                        value={review.antecedentes.aceptados ? `Aceptada · ${formatReviewDate(review.antecedentes.fecha)}` : 'Sin registro de aceptación'}
                                    />
                                </div>
                            </section>

                            <section className="space-y-3">
                                <div>
                                    <h3 className="font-semibold">Documentos y fotos</h3>
                                    <p className="text-sm text-muted-foreground">
                                        La contraseña no se muestra ni se almacena en texto plano.
                                    </p>
                                </div>
                                {review.documentos.length === 0 ? (
                                    <div className="rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground">
                                        No se recibieron documentos.
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {review.documentos.map((documento) => {
                                            const esImagen = documento.mime_type?.startsWith('image/')
                                                || /\.(jpe?g|png|webp)$/i.test(documento.nombre);
                                            return (
                                                <article key={`${documento.tipo}-${documento.nombre}`} className="min-w-0 overflow-hidden rounded-lg border">
                                                    <div className="flex items-start gap-2 border-b p-3">
                                                        {esImagen ? <ImageIcon className="mt-0.5 h-4 w-4 shrink-0" /> : <FileText className="mt-0.5 h-4 w-4 shrink-0" />}
                                                        <div className="min-w-0">
                                                            <p className="break-words text-sm font-medium">{documento.label}</p>
                                                            <p className="break-all text-xs text-muted-foreground">{documento.nombre}</p>
                                                        </div>
                                                    </div>
                                                    {documento.url && esImagen && (
                                                        <a href={documento.url} target="_blank" rel="noopener noreferrer" className="flex h-48 items-center justify-center bg-muted/30 p-2">
                                                            <img src={documento.url} alt={documento.label} className="max-h-full max-w-full object-contain" />
                                                        </a>
                                                    )}
                                                    <div className="flex flex-wrap items-center justify-between gap-2 p-3 text-xs text-muted-foreground">
                                                        <span>{formatDocumentSize(documento.bytes)}</span>
                                                        <span>{documento.subido_at ? formatReviewDate(documento.subido_at) : 'Fecha no disponible'}</span>
                                                        {documento.url && !esImagen && (
                                                            <Button type="button" variant="outline" size="sm" className="w-full sm:w-auto" onClick={() => abrirDocumento(documento.url!)}>
                                                                Abrir documento
                                                            </Button>
                                                        )}
                                                        {!documento.url && <span className="text-amber-700">Archivo no disponible</span>}
                                                    </div>
                                                </article>
                                            );
                                        })}
                                    </div>
                                )}
                            </section>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
