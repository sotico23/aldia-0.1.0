import { router, useForm } from '@inertiajs/react';
import {
    Circle,
    DrawingManager,
    GoogleMap,
    InfoWindow,
    Marker,
    useJsApiLoader,
} from '@react-google-maps/api';
import { MapPin, Pencil, PlusCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import echo from '@/echo';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const MAP_CENTER = { lat: -33.4489, lng: -70.6693 };

const motivoLabel: Record<string, string> = {
    auto_asignado: 'Automática',
    asignado_manual: 'Manual',
    aceptado_pool: 'Aceptó del pool',
};

const formatoFecha = (fecha: string | null) =>
    fecha
        ? new Date(fecha).toLocaleString('es-CL', {
              day: '2-digit',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

type Zona = {
    id: number;
    name: string;
    lat: number;
    lng: number;
    radio_km: number;
    capacidad_max: number;
    activa: boolean;
    uso?: {
        id: number;
        name: string;
        lat: number;
        lng: number;
        radio_km: number;
        capacidad_max: number;
        activa: boolean;
        pedidos_en_zona: number;
        pedidos_activos: number;
        uso_percent: number;
    } | null;
};

type RepartidorMarker = {
    id: number;
    nombre: string | null;
    estado: string;
    lat: number;
    lng: number;
    radio_km: number;
    last_position_at: string | null;
};

type Rendimiento = {
    dias: number;
    asignaciones: number;
    por_motivo: Record<string, number>;
    entregadas: number;
    en_curso: number;
    tiempo_medio_pool_min: number;
    en_pool_sin_repartidor: number;
    por_dia: { fecha: string; asignaciones: number }[];
    por_repartidor: {
        user_id: number;
        nombre: string | null;
        asignaciones: number;
        entregadas: number;
        en_curso: number;
    }[];
};

type Historial = {
    id: number;
    pedido: string | null;
    repartidor: string | null;
    zona: string | null;
    asignador: string | null;
    motivo: string;
    fecha: string | null;
};

type Props = {
    owner_id: number;
    zonas: Zona[];
    repartidores: RepartidorMarker[];
    resumen: {
        zonas: Record<string, unknown>[];
        total_pedidos_activos: number;
        total_repartidores: number;
        repartidores_disponibles: number;
    };
    rendimiento: Rendimiento;
    historial: Historial[];
};

export default function Index({
    owner_id,
    zonas: zonasProp,
    repartidores: repartidoresProp,
    resumen,
    rendimiento,
    historial,
}: Props) {
    const { isLoaded } = useJsApiLoader({
        id: 'zonas-reparto-map',
        googleMapsApiKey: import.meta.env.VITE_GOOGLE_MAPS_API_KEY ?? '',
        libraries: ['drawing'],
    });

    const [zonas, setZonas] = useState<Zona[]>(zonasProp);
    const [repartidores, setRepartidores] =
        useState<RepartidorMarker[]>(repartidoresProp);
    const [modalOpen, setModalOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [drawMode, setDrawMode] = useState(false);
    const [modoDibujo, setModoDibujo] = useState<'nueva' | 'editar' | null>(
        null,
    );
    const [repartidorSeleccionado, setRepartidorSeleccionado] =
        useState<RepartidorMarker | null>(null);
    const [zonaAEliminar, setZonaAEliminar] = useState<Zona | null>(null);

    const {
        data,
        setData,
        post,
        put,
        reset,
        processing,
        errors,
    } = useForm({
        name: '',
        lat: '',
        lng: '',
        radio_km: '',
        capacidad_max: '10',
        activa: true,
    });

    useEffect(() => {
        if (!owner_id) return;

        const channel = `delivery.${owner_id}.positions`;
        const leaveChannel = `private-delivery.${owner_id}.positions`;

        const handler = (event: {
            repartidor_id: number;
            lat: number;
            lng: number;
        }) => {
            setRepartidores((prev) =>
                prev.map((repartidor) =>
                    repartidor.id === event.repartidor_id
                        ? { ...repartidor, lat: event.lat, lng: event.lng }
                        : repartidor,
                ),
            );
        };

        echo.private(channel).listen('DeliveryPositionUpdated', handler);

        return () => {
            echo.leaveChannel(leaveChannel);
        };
    }, [owner_id]);

    useEffect(() => {
        setZonas(zonasProp);
    }, [zonasProp]);

    useEffect(() => {
        setRepartidores(repartidoresProp);
    }, [repartidoresProp]);

    const abrirNueva = () => {
        reset();
        setEditingId(null);
        setModoDibujo('nueva');
        setDrawMode(true);
    };

    const abrirEditar = (zona: Zona) => {
        setData({
            name: zona.name,
            lat: zona.lat.toFixed(6),
            lng: zona.lng.toFixed(6),
            radio_km: zona.radio_km.toFixed(2),
            capacidad_max: String(zona.capacidad_max),
            activa: zona.activa,
        });
        setEditingId(zona.id);
        setModoDibujo('editar');
        setDrawMode(true);
    };

    const ajustarEnMapa = () => {
        setDrawMode(true);
    };

    const onCircleComplete = (circle: google.maps.Circle) => {
        const center = circle.getCenter();
        circle.setMap(null);
        if (!center) {
            setDrawMode(false);
            return;
        }

        const radioKm = Math.max(0.1, Math.min(500, circle.getRadius() / 1000));

        setData((prev) => ({
            ...prev,
            lat: center.lat().toFixed(6),
            lng: center.lng().toFixed(6),
            radio_km: radioKm.toFixed(2),
        }));
        setDrawMode(false);
        if (modoDibujo === 'nueva') {
            setModalOpen(true);
        }
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
            put(`/zonas-reparto/${editingId}`, options);
        } else {
            post('/zonas-reparto', options);
        }
    };

    const confirmarEliminar = () => {
        if (!zonaAEliminar) return;

        router.delete(`/zonas-reparto/${zonaAEliminar.id}`, {
            preserveScroll: true,
            onSuccess: () => setZonaAEliminar(null),
        });
    };

    const mapCenter = zonas[0]
        ? { lat: zonas[0].lat, lng: zonas[0].lng }
        : MAP_CENTER;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Zonas de Reparto', href: '/zonas-reparto' },
            ]}
        >
            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">
                            Zonas de Reparto
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Define las zonas, su capacidad y monitorea a los
                            repartidores en tiempo real
                        </p>
                    </div>
                    <Button onClick={abrirNueva} className="w-full sm:w-auto">
                        <PlusCircle className="mr-2 h-4 w-4" />
                        Nueva zona
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Zonas definidas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">{zonas.length}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Pedidos en circuito
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {resumen.total_pedidos_activos}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Repartidores
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {resumen.total_repartidores}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Disponibles ahora
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold text-emerald-500">
                                {resumen.repartidores_disponibles}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card className="overflow-hidden">
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle>Mapa de zonas y repartidores</CardTitle>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={abrirNueva}
                            disabled={drawMode}
                        >
                            <Pencil className="mr-2 h-4 w-4" />
                            Dibujar zona
                        </Button>
                    </CardHeader>
                    <CardContent className="p-0">
                        {drawMode && (
                            <div className="bg-primary/10 px-4 py-2 text-sm font-medium text-primary">
                                Dibuja un círculo sobre el mapa para definir la
                                zona (radio entre 100 m y 500 km).
                            </div>
                        )}
                        <div className="h-[400px] w-full sm:h-[480px]">
                            {isLoaded &&
                            import.meta.env.VITE_GOOGLE_MAPS_API_KEY ? (
                                <GoogleMap
                                    mapContainerStyle={{
                                        width: '100%',
                                        height: '100%',
                                    }}
                                    center={mapCenter}
                                    zoom={13}
                                    options={{
                                        disableDefaultUI: false,
                                        zoomControl: true,
                                    }}
                                >
                                    {zonas.map((zona) => (
                                        <Circle
                                            key={zona.id}
                                            center={{
                                                lat: zona.lat,
                                                lng: zona.lng,
                                            }}
                                            radius={zona.radio_km * 1000}
                                            options={{
                                                fillColor: zona.activa
                                                    ? '#10b981'
                                                    : '#64748b',
                                                fillOpacity: 0.15,
                                                strokeColor: zona.activa
                                                    ? '#10b981'
                                                    : '#64748b',
                                                strokeOpacity: 0.6,
                                                strokeWeight: 2,
                                            }}
                                        />
                                    ))}
                                    {repartidores.map((repartidor) => (
                                        <Marker
                                            key={repartidor.id}
                                            position={{
                                                lat: repartidor.lat,
                                                lng: repartidor.lng,
                                            }}
                                            onClick={() =>
                                                setRepartidorSeleccionado(
                                                    repartidor,
                                                )
                                            }
                                            icon={{
                                                path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
                                                fillColor: '#3b82f6',
                                                fillOpacity: 1,
                                                strokeWeight: 2,
                                                strokeColor: '#ffffff',
                                                scale: 1.5,
                                                anchor: { x: 12, y: 22 } as any,
                                            }}
                                        />
                                    ))}
                                    {repartidorSeleccionado && (
                                        <InfoWindow
                                            position={{
                                                lat: repartidorSeleccionado.lat,
                                                lng: repartidorSeleccionado.lng,
                                            }}
                                            onCloseClick={() =>
                                                setRepartidorSeleccionado(null)
                                            }
                                        >
                                            <div className="min-w-[200px] p-1">
                                                <p className="font-bold text-gray-900">
                                                    {repartidorSeleccionado.nombre ??
                                                        'Sin nombre'}
                                                </p>
                                                <p className="mt-1 text-xs text-gray-600">
                                                    Estado:{' '}
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[10px] normal-case"
                                                    >
                                                        {
                                                            repartidorSeleccionado.estado
                                                        }
                                                    </Badge>
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    Radio:{' '}
                                                    {
                                                        repartidorSeleccionado.radio_km
                                                    }{' '}
                                                    km
                                                </p>
                                            </div>
                                        </InfoWindow>
                                    )}
                                    <DrawingManager
                                        drawingMode={
                                            drawMode
                                                ? (window as any).google.maps
                                                      .drawing.OverlayType
                                                      .CIRCLE
                                                : null
                                        }
                                        onCircleComplete={onCircleComplete}
                                        options={{
                                            drawingControl: true,
                                            drawingControlOptions: {
                                                position: (window as any).google
                                                    .maps.ControlPosition
                                                    .TOP_CENTER,
                                                drawingModes: [
                                                    (window as any).google.maps
                                                        .drawing.OverlayType
                                                        .CIRCLE,
                                                ],
                                            },
                                            circleOptions: {
                                                fillColor: '#10b981',
                                                fillOpacity: 0.15,
                                                strokeColor: '#10b981',
                                                strokeOpacity: 0.6,
                                                strokeWeight: 2,
                                            },
                                        }}
                                    />
                                </GoogleMap>
                            ) : (
                                <div className="flex h-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed bg-muted/20 px-6 text-center">
                                    <MapPin className="h-8 w-8 text-muted-foreground" />
                                    <p className="font-medium">
                                        Mapa no disponible
                                    </p>
                                    <p className="max-w-sm text-sm text-muted-foreground">
                                        Configura{' '}
                                        <code className="rounded bg-muted px-1">
                                            VITE_GOOGLE_MAPS_API_KEY
                                        </code>{' '}
                                        en tu{' '}
                                        <code className="rounded bg-muted px-1">
                                            .env
                                        </code>{' '}
                                        para ver zonas y repartidores en el
                                        mapa.
                                    </p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Listado de zonas</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3 sm:hidden">
                            {zonas.length === 0 && (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    Aún no hay zonas definidas. Usa «Dibujar
                                    zona» sobre el mapa para crear la primera.
                                </p>
                            )}
                            {zonas.map((zona) => {
                                const usoPercent =
                                    zona.uso?.uso_percent ?? 0;

                                return (
                                    <div
                                        key={zona.id}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="flex min-w-0 items-center gap-2 font-medium">
                                                <MapPin className="h-4 w-4 shrink-0 text-primary" />
                                                <span className="truncate">
                                                    {zona.name}
                                                </span>
                                            </p>
                                            <Badge
                                                variant={
                                                    zona.activa
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                                className="shrink-0"
                                            >
                                                {zona.activa
                                                    ? 'Activa'
                                                    : 'Inactiva'}
                                            </Badge>
                                        </div>
                                        <dl className="mt-3 grid grid-cols-2 gap-2 text-sm">
                                            <div className="rounded-md bg-muted/50 p-2">
                                                <dt className="text-xs text-muted-foreground">
                                                    Radio
                                                </dt>
                                                <dd className="font-medium">
                                                    {zona.radio_km} km
                                                </dd>
                                            </div>
                                            <div className="rounded-md bg-muted/50 p-2">
                                                <dt className="text-xs text-muted-foreground">
                                                    Capacidad
                                                </dt>
                                                <dd className="font-medium">
                                                    {zona.capacidad_max}
                                                </dd>
                                            </div>
                                            <div className="col-span-2 rounded-md bg-muted/50 p-2">
                                                <dt className="text-xs text-muted-foreground">
                                                    Uso
                                                </dt>
                                                <dd className="flex items-center gap-2">
                                                    <Progress
                                                        value={usoPercent}
                                                        className="h-2 flex-1"
                                                    />
                                                    <span className="text-xs text-muted-foreground">
                                                        {usoPercent}%
                                                    </span>
                                                </dd>
                                            </div>
                                        </dl>
                                        <div className="mt-3 flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="flex-1"
                                                onClick={() =>
                                                    abrirEditar(zona)
                                                }
                                            >
                                                Editar
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                className="flex-1"
                                                onClick={() =>
                                                    setZonaAEliminar(zona)
                                                }
                                            >
                                                Eliminar
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="hidden sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Zona</TableHead>
                                        <TableHead>Radio</TableHead>
                                        <TableHead>Capacidad</TableHead>
                                        <TableHead>Uso</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead className="text-right">
                                            Acciones
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {zonas.map((zona) => {
                                        const usoPercent =
                                            zona.uso?.uso_percent ?? 0;

                                        return (
                                            <TableRow key={zona.id}>
                                                <TableCell className="font-medium">
                                                    <span className="inline-flex items-center gap-2">
                                                        <MapPin className="h-4 w-4 text-primary" />
                                                        {zona.name}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    {zona.radio_km} km
                                                </TableCell>
                                                <TableCell>
                                                    {zona.capacidad_max}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex w-40 items-center gap-2">
                                                        <Progress
                                                            value={usoPercent}
                                                            className="h-2"
                                                        />
                                                        <span className="text-xs text-muted-foreground">
                                                            {usoPercent}%
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            zona.activa
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {zona.activa
                                                            ? 'Activa'
                                                            : 'Inactiva'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            abrirEditar(zona)
                                                        }
                                                    >
                                                        Editar
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        className="ml-2"
                                                        onClick={() =>
                                                            setZonaAEliminar(
                                                                zona,
                                                            )
                                                        }
                                                    >
                                                        Eliminar
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    {zonas.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-8 text-center text-sm text-muted-foreground"
                                            >
                                                Aún no hay zonas definidas. Usa
                                                «Dibujar zona» sobre el mapa
                                                para crear la primera.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Rendimiento del reparto ({rendimiento.dias} días)
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Métricas sobre el histórico de asignaciones:
                            entregas, tiempos de respuesta y saturación actual
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                            <div className="rounded-lg border p-4">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Asignaciones
                                </p>
                                <p className="mt-1 text-2xl font-bold">
                                    {rendimiento.asignaciones}
                                </p>
                            </div>
                            <div className="rounded-lg border p-4">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Entregadas
                                </p>
                                <p className="mt-1 text-2xl font-bold text-emerald-500">
                                    {rendimiento.entregadas}
                                </p>
                            </div>
                            <div className="rounded-lg border p-4">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Tiempo medio en pool
                                </p>
                                <p className="mt-1 text-2xl font-bold">
                                    {rendimiento.tiempo_medio_pool_min} min
                                </p>
                            </div>
                            <div className="rounded-lg border p-4">
                                <p className="text-xs font-medium text-muted-foreground">
                                    En curso ahora
                                </p>
                                <p className="mt-1 text-2xl font-bold">
                                    {rendimiento.en_curso}
                                </p>
                            </div>
                            <div className="rounded-lg border p-4">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Sin repartidor (pool)
                                </p>
                                <p
                                    className={`mt-1 text-2xl font-bold ${
                                        rendimiento.en_pool_sin_repartidor > 0
                                            ? 'text-red-500'
                                            : ''
                                    }`}
                                >
                                    {rendimiento.en_pool_sin_repartidor}
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <div>
                                <p className="mb-2 text-sm font-medium">
                                    Asignaciones por día (últimos 14)
                                </p>
                                <div className="h-48 w-full">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <BarChart data={rendimiento.por_dia}>
                                            <XAxis
                                                dataKey="fecha"
                                                tick={{ fontSize: 11 }}
                                                interval="preserveStartEnd"
                                            />
                                            <YAxis
                                                allowDecimals={false}
                                                width={30}
                                                tick={{ fontSize: 11 }}
                                            />
                                            <Tooltip
                                                cursor={{
                                                    fill: 'hsl(var(--muted))',
                                                }}
                                            />
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                stroke="hsl(var(--border))"
                                            />
                                            <Bar
                                                dataKey="asignaciones"
                                                fill="#6366f1"
                                                radius={[4, 4, 0, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                                {rendimiento.por_dia.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Sin asignaciones en el período.
                                    </p>
                                )}
                            </div>

                            <div>
                                <p className="mb-2 text-sm font-medium">
                                    Por repartidor
                                </p>
                                <div className="space-y-2 sm:hidden">
                                    {rendimiento.por_repartidor.length ===
                                        0 && (
                                        <p className="py-4 text-center text-sm text-muted-foreground">
                                            Aún no hay asignaciones
                                            registradas en el período.
                                        </p>
                                    )}
                                    {rendimiento.por_repartidor.map(
                                        (repartidor) => (
                                            <div
                                                key={repartidor.user_id}
                                                className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                            >
                                                <p className="min-w-0 truncate font-medium">
                                                    {repartidor.nombre ??
                                                        'Sin nombre'}
                                                </p>
                                                <div className="flex shrink-0 items-center gap-3 text-sm text-muted-foreground">
                                                    <span>
                                                        <strong className="text-foreground">
                                                            {
                                                                repartidor.asignaciones
                                                            }
                                                        </strong>{' '}
                                                        asig.
                                                    </span>
                                                    <span className="text-emerald-600">
                                                        <strong>
                                                            {
                                                                repartidor.entregadas
                                                            }
                                                        </strong>{' '}
                                                        entr.
                                                    </span>
                                                    <span>
                                                        <strong className="text-foreground">
                                                            {
                                                                repartidor.en_curso
                                                            }
                                                        </strong>{' '}
                                                        curso
                                                    </span>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                                <div className="hidden sm:block">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    Repartidor
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Asignaciones
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Entregadas
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    En curso
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {rendimiento.por_repartidor.map(
                                                (repartidor) => (
                                                    <TableRow
                                                        key={
                                                            repartidor.user_id
                                                        }
                                                    >
                                                        <TableCell className="font-medium">
                                                            {repartidor.nombre ??
                                                                'Sin nombre'}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            {
                                                                repartidor.asignaciones
                                                            }
                                                        </TableCell>
                                                        <TableCell className="text-right text-emerald-600">
                                                            {repartidor.entregadas}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            {repartidor.en_curso}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                            {rendimiento.por_repartidor
                                                .length === 0 && (
                                                <TableRow>
                                                    <TableCell
                                                        colSpan={4}
                                                        className="py-6 text-center text-sm text-muted-foreground"
                                                    >
                                                        Aún no hay asignaciones
                                                        registradas en el
                                                        período.
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Historial de asignaciones</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3 sm:hidden">
                            {historial.length === 0 && (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    Aún no hay asignaciones registradas. El
                                    historial se llena con asignaciones
                                    automáticas, manuales y aceptaciones del
                                    pool.
                                </p>
                            )}
                            {historial.map((item) => (
                                <div
                                    key={item.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <p className="min-w-0 truncate font-medium">
                                            {item.pedido ?? '—'}
                                        </p>
                                        <Badge
                                            variant={
                                                item.motivo === 'aceptado_pool'
                                                    ? 'secondary'
                                                    : 'default'
                                            }
                                            className="shrink-0"
                                        >
                                            {motivoLabel[item.motivo] ??
                                                item.motivo}
                                        </Badge>
                                    </div>
                                    <dl className="mt-3 space-y-1.5 text-sm">
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-muted-foreground">
                                                Repartidor
                                            </dt>
                                            <dd className="text-right">
                                                {item.repartidor ?? '—'}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-muted-foreground">
                                                Zona
                                            </dt>
                                            <dd className="text-right">
                                                {item.zona ?? '—'}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-muted-foreground">
                                                Asignó
                                            </dt>
                                            <dd className="text-right">
                                                {item.asignador ?? '—'}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-muted-foreground">
                                                Fecha
                                            </dt>
                                            <dd className="text-right">
                                                {formatoFecha(item.fecha)}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            ))}
                        </div>
                        <div className="hidden sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Pedido</TableHead>
                                        <TableHead>Repartidor</TableHead>
                                        <TableHead>Zona</TableHead>
                                        <TableHead>Asignación</TableHead>
                                        <TableHead>Asignó</TableHead>
                                        <TableHead>Fecha</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {historial.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.pedido ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {item.repartidor ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {item.zona ? (
                                                    <Badge variant="outline">
                                                        {item.zona}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        item.motivo ===
                                                        'aceptado_pool'
                                                            ? 'secondary'
                                                            : 'default'
                                                    }
                                                >
                                                    {motivoLabel[
                                                        item.motivo
                                                    ] ?? item.motivo}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {item.asignador ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatoFecha(item.fecha)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {historial.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-8 text-center text-sm text-muted-foreground"
                                            >
                                                Aún no hay asignaciones
                                                registradas. El historial se
                                                llena con asignaciones
                                                automáticas, manuales y
                                                aceptaciones del pool.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                {editingId ? 'Editar zona' : 'Nueva zona'}
                            </DialogTitle>
                        </DialogHeader>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="Ej: Centro, Norte, Sur..."
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="lat">Latitud</Label>
                                    <Input id="lat" value={data.lat} readOnly />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="lng">Longitud</Label>
                                    <Input id="lng" value={data.lng} readOnly />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="radio">Radio (km)</Label>
                                    <Input
                                        id="radio"
                                        value={data.radio_km}
                                        readOnly
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="capacidad">
                                    Capacidad máxima (pedidos simultáneos)
                                </Label>
                                <Input
                                    id="capacidad"
                                    type="number"
                                    min={1}
                                    max={999}
                                    value={data.capacidad_max}
                                    onChange={(e) =>
                                        setData('capacidad_max', e.target.value)
                                    }
                                />
                                {errors.capacidad_max && (
                                    <p className="text-sm text-destructive">
                                        {errors.capacidad_max}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>Zona activa</Label>
                                    <p className="text-xs text-muted-foreground">
                                        Las zonas inactivas no reciben
                                        asignaciones.
                                    </p>
                                </div>
                                <Switch
                                    checked={data.activa}
                                    onCheckedChange={(v: boolean) =>
                                        setData('activa', v)
                                    }
                                />
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={ajustarEnMapa}
                                    disabled={drawMode}
                                >
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Ajustar círculo en el mapa
                                </Button>
                                {data.lat && (
                                    <span className="text-xs text-muted-foreground">
                                        Centro: {data.lat}, {data.lng} ·{' '}
                                        {data.radio_km} km
                                    </span>
                                )}
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
                                        processing || !data.lat || !data.lng
                                    }
                                >
                                    {editingId
                                        ? 'Guardar cambios'
                                        : 'Crear zona'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <ConfirmDialog
                    open={zonaAEliminar !== null}
                    onOpenChange={(open) => {
                        if (!open) setZonaAEliminar(null);
                    }}
                    title="Eliminar zona"
                    description={`¿Seguro que deseas eliminar la zona "${zonaAEliminar?.name ?? ''}"? Esta acción no se puede deshacer.`}
                    confirmLabel="Eliminar"
                    cancelLabel="Cancelar"
                    destructive
                    processing={processing}
                    onConfirm={confirmarEliminar}
                />
            </div>
        </AppLayout>
    );
}