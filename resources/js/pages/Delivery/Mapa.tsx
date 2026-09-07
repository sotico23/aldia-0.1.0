import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Circle, GoogleMap, InfoWindow, Marker, useJsApiLoader } from '@react-google-maps/api';
import { Loader2, MapPin, Navigation } from 'lucide-react';
import echo from '@/echo';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

// ============================================================================
// ZONA DE REPARTIDORES - MAPA PWA DEL REPARTIDOR
// ============================================================================

type Pedido = {
    id: number;
    numero_pedido: string | null;
    estado: string;
    repartidor_id: number | null;
    total: number | null;
    metodo_pago: string | null;
    nombre_cliente: string | null;
    telefono_cliente: string | null;
    direccion_cliente: string | null;
    destino_lat: number | null;
    destino_lng: number | null;
    distancia_km: number | null;
    notas: string | null;
    hora_aceptado: string | null;
    hora_recogido: string | null;
    hora_entregado: string | null;
    created_at: string | null;
};

type Repartidor = {
    id: number;
    user_id: number;
    estado: string;
    lat: number | null;
    lng: number | null;
    radio_km: number;
    last_position_at: string | null;
};

type ConfigPool = {
    modo: string;
    pool_timeout_min: number | null;
    pool_reenvio_min: number | null;
    pool_reenvios_max: number | null;
};

type Props = {
    owner_id: number;
    repartidor: Repartidor;
    config: ConfigPool | null;
    pool: Pedido[];
    activos: Pedido[];
};

const MAP_CENTER = { lat: -33.4489, lng: -70.6693 };

export default function MapaRepartidor({ owner_id, repartidor, config, pool, activos }: Props) {
    const { isLoaded } = useJsApiLoader({
        id: 'repartidor-map',
        googleMapsApiKey: import.meta.env.VITE_GOOGLE_MAPS_API_KEY,
    });

    const [posicion, setPosicion] = useState<{ lat: number | null; lng: number | null }>({
        lat: repartidor.lat,
        lng: repartidor.lng,
    });
    const [gpsActivo, setGpsActivo] = useState(false);
    const [seleccionado, setSeleccionado] = useState<Pedido | null>(null);
    const [enviandoId, setEnviandoId] = useState<number | null>(null);
    const ultimoEnvio = useRef(0);

    const enviarUbicacion = useCallback((lat: number, lng: number) => {
        const ahora = Date.now();

        if (ahora - ultimoEnvio.current < 10000) {
            return;
        }

        ultimoEnvio.current = ahora;
        router.post(
            '/repartidor/ubicacion',
            { lat, lng },
            { preserveScroll: true, preserveState: true },
        );
    }, []);

    useEffect(() => {
        if (!navigator.geolocation) {
            return;
        }

        const watchId = navigator.geolocation.watchPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;

                setPosicion({ lat, lng });
                setGpsActivo(true);
                enviarUbicacion(lat, lng);
            },
            () => {
                setGpsActivo(false);
            },
            { enableHighAccuracy: true, maximumAge: 15000, timeout: 30000 },
        );

        return () => navigator.geolocation.clearWatch(watchId);
    }, [enviarUbicacion]);

    useEffect(() => {
        if (!owner_id) {
            return;
        }

        const subChannel = `delivery.${owner_id}.orders`;
        const leaveChannel = `private-delivery.${owner_id}.orders`;

        const handler = () => {
            router.reload({ only: ['pool', 'activos', 'repartidor'] });
        };

        echo.private(subChannel).listen('DeliveryOrderPoolUpdated', handler);

        return () => {
            echo.leaveChannel(leaveChannel);
        };
    }, [owner_id]);

    const accion = (ruta: string, pedidoId: number) => {
        setEnviandoId(pedidoId);

        router.post(
            ruta,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['pool', 'activos', 'repartidor'],
                onFinish: () => setEnviandoId(null),
            },
        );
    };

    const alternarDisponibilidad = () => {
        const estado = repartidor.estado === 'disponible' ? 'ocupado' : 'disponible';

        router.post(
            '/repartidor/disponibilidad',
            { estado },
            { preserveScroll: true, preserveState: true, only: ['repartidor', 'pool', 'activos'] },
        );
    };

    const miPosicion = posicion.lat !== null && posicion.lng !== null;
    const poolFiltrado = pool.filter((pedido) => pedido.estado === 'preparando' && !pedido.repartidor_id);

    return (
        <AppLayout breadcrumbs={[{ title: 'Mapa de Reparto', href: '/repartidor/mapa' }]}>
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Mapa de Reparto</h1>
                        <p className="text-sm text-muted-foreground">
                            {config?.modo === 'manual' ? 'Modo manual: los administradores te asignan los repartos.' : 'Toca un pedido para ver sus acciones.'}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {!gpsActivo && <Badge variant="secondary">GPS apagado</Badge>}
                        <Badge variant={repartidor.estado === 'disponible' ? 'default' : 'secondary'}>
                            {repartidor.estado === 'disponible' ? 'Disponible' : 'Ocupado'}
                        </Badge>
                        <Button variant={repartidor.estado === 'disponible' ? 'outline' : 'default'} size="sm" onClick={alternarDisponibilidad}>
                            {repartidor.estado === 'disponible' ? 'Pausar' : 'Disponible'}
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="overflow-hidden lg:col-span-2">
                        <CardContent className="p-0">
                            <div className="h-[460px] w-full">
                                {!isLoaded ? (
                                    <div className="flex h-full items-center justify-center rounded-xl border-2 border-dashed bg-muted/20">
                                        <Loader2 className="h-8 w-8 animate-spin text-primary" />
                                        <span className="ml-3 font-medium">Cargando Mapas...</span>
                                    </div>
                                ) : (
                                    <GoogleMap
                                        mapContainerStyle={{ width: '100%', height: '100%', borderRadius: '1rem' }}
                                        center={miPosicion ? { lat: posicion.lat!, lng: posicion.lng! } : MAP_CENTER}
                                        zoom={13}
                                        options={{ disableDefaultUI: false, zoomControl: true }}
                                    >
                                        {miPosicion && (
                                            <>
                                                <Circle
                                                    center={{ lat: posicion.lat!, lng: posicion.lng! }}
                                                    radius={repartidor.radio_km * 1000}
                                                    options={{
                                                        fillColor: '#3b82f6',
                                                        fillOpacity: 0.1,
                                                        strokeColor: '#3b82f6',
                                                        strokeOpacity: 0.6,
                                                        strokeWeight: 2,
                                                    }}
                                                />
                                                <Marker
                                                    position={{ lat: posicion.lat!, lng: posicion.lng! }}
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
                                            </>
                                        )}

                                        {poolFiltrado.map((pedido) =>
                                            pedido.destino_lat !== null && pedido.destino_lng !== null ? (
                                                <Marker
                                                    key={pedido.id}
                                                    position={{ lat: pedido.destino_lat, lng: pedido.destino_lng }}
                                                    onClick={() => setSeleccionado(pedido)}
                                                    icon={{
                                                        path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
                                                        fillColor: '#10b981',
                                                        fillOpacity: 1,
                                                        strokeWeight: 2,
                                                        strokeColor: '#ffffff',
                                                        scale: 1.3,
                                                        anchor: { x: 12, y: 22 } as any,
                                                    }}
                                                />
                                            ) : null,
                                        )}

                                        {activos.map((pedido) =>
                                            pedido.destino_lat !== null && pedido.destino_lng !== null ? (
                                                <Marker
                                                    key={pedido.id}
                                                    position={{ lat: pedido.destino_lat, lng: pedido.destino_lng }}
                                                    onClick={() => setSeleccionado(pedido)}
                                                    icon={{
                                                        path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
                                                        fillColor: '#f59e0b',
                                                        fillOpacity: 1,
                                                        strokeWeight: 2,
                                                        strokeColor: '#ffffff',
                                                        scale: 1.3,
                                                        anchor: { x: 12, y: 22 } as any,
                                                    }}
                                                />
                                            ) : null,
                                        )}

                                        {seleccionado && seleccionado.destino_lat !== null && seleccionado.destino_lng !== null && (
                                            <InfoWindow
                                                position={{ lat: seleccionado.destino_lat, lng: seleccionado.destino_lng }}
                                                onCloseClick={() => setSeleccionado(null)}
                                            >
                                                <div className="min-w-[220px] p-1">
                                                    <div className="mb-1 flex items-center justify-between">
                                                        <span className="font-bold text-gray-900">
                                                            {seleccionado.numero_pedido ?? `Pedido #${seleccionado.id}`}
                                                        </span>
                                                        <Badge variant="outline" className="text-[10px] normal-case">
                                                            {seleccionado.estado}
                                                        </Badge>
                                                    </div>
                                                    {seleccionado.nombre_cliente && (
                                                        <p className="text-xs text-gray-700">{seleccionado.nombre_cliente}</p>
                                                    )}
                                                    {seleccionado.direccion_cliente && (
                                                        <p className="text-[11px] text-gray-500">{seleccionado.direccion_cliente}</p>
                                                    )}
                                                    {seleccionado.distancia_km !== null && (
                                                        <p className="mt-1 text-[11px] text-gray-500">A {seleccionado.distancia_km} km de ti</p>
                                                    )}
                                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                                        {seleccionado.estado === 'preparando' && !seleccionado.repartidor_id && (
                                                            <Button
                                                                size="sm"
                                                                className="h-7 px-2 text-xs"
                                                                disabled={enviandoId === seleccionado.id}
                                                                onClick={() => accion(`/repartidor/pedidos/${seleccionado.id}/aceptar`, seleccionado.id)}
                                                            >
                                                                Aceptar reparto
                                                            </Button>
                                                        )}
                                                        {seleccionado.repartidor_id && seleccionado.estado === 'preparando' && (
                                                            <>
                                                                <Button
                                                                    size="sm"
                                                                    className="h-7 px-2 text-xs"
                                                                    disabled={enviandoId === seleccionado.id}
                                                                    onClick={() => accion(`/repartidor/pedidos/${seleccionado.id}/recoger`, seleccionado.id)}
                                                                >
                                                                    Recoger
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="destructive"
                                                                    className="h-7 px-2 text-xs"
                                                                    disabled={enviandoId === seleccionado.id}
                                                                    onClick={() => accion(`/repartidor/pedidos/${seleccionado.id}/rechazar`, seleccionado.id)}
                                                                >
                                                                    Rechazar
                                                                </Button>
                                                            </>
                                                        )}
                                                        {seleccionado.estado === 'enviado' && (
                                                            <Button
                                                                size="sm"
                                                                className="h-7 px-2 text-xs"
                                                                disabled={enviandoId === seleccionado.id}
                                                                onClick={() => accion(`/repartidor/pedidos/${seleccionado.id}/entregar`, seleccionado.id)}
                                                            >
                                                                Marcar entregado
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            </InfoWindow>
                                        )}
                                    </GoogleMap>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Pedidos disponibles ({poolFiltrado.length})</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {poolFiltrado.length === 0 && (
                                    <p className="py-4 text-center text-sm text-muted-foreground">
                                        No hay pedidos dentro de tu radio ({repartidor.radio_km} km).
                                    </p>
                                )}
                                {poolFiltrado.map((pedido) => (
                                    <div key={pedido.id} className="flex items-center justify-between rounded-lg border p-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold">
                                                {pedido.numero_pedido ?? `Pedido #${pedido.id}`}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">{pedido.direccion_cliente ?? pedido.nombre_cliente}</p>
                                            {pedido.distancia_km !== null && (
                                                <p className="text-xs text-muted-foreground">{pedido.distancia_km} km</p>
                                            )}
                                        </div>
                                        <Button
                                            size="sm"
                                            className="ml-2"
                                            disabled={enviandoId === pedido.id}
                                            onClick={() => accion(`/repartidor/pedidos/${pedido.id}/aceptar`, pedido.id)}
                                        >
                                            Aceptar
                                        </Button>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Mis repartos ({activos.length})</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {activos.length === 0 && (
                                    <p className="py-4 text-center text-sm text-muted-foreground">Aún no tienes repartos en curso.</p>
                                )}
                                {activos.map((pedido) => (
                                    <div key={pedido.id} className="rounded-lg border p-3">
                                        <div className="flex items-center justify-between">
                                            <p className="text-sm font-semibold">{pedido.numero_pedido ?? `Pedido #${pedido.id}`}</p>
                                            <Badge variant="outline" className="normal-case">
                                                {pedido.estado}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 truncate text-xs text-muted-foreground">{pedido.direccion_cliente ?? pedido.nombre_cliente}</p>
                                        <div className="mt-2 flex gap-1.5">
                                            {pedido.estado === 'preparando' && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        className="h-7 px-2 text-xs"
                                                        disabled={enviandoId === pedido.id}
                                                        onClick={() => accion(`/repartidor/pedidos/${pedido.id}/recoger`, pedido.id)}
                                                    >
                                                        Recoger
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        className="h-7 px-2 text-xs"
                                                        disabled={enviandoId === pedido.id}
                                                        onClick={() => accion(`/repartidor/pedidos/${pedido.id}/rechazar`, pedido.id)}
                                                    >
                                                        Rechazar
                                                    </Button>
                                                </>
                                            )}
                                            {pedido.estado === 'enviado' && (
                                                <Button
                                                    size="sm"
                                                    className="h-7 px-2 text-xs"
                                                    disabled={enviandoId === pedido.id}
                                                    onClick={() => accion(`/repartidor/pedidos/${pedido.id}/entregar`, pedido.id)}
                                                >
                                                    Marcar entregado
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <div className="flex items-center gap-2 px-1 text-xs text-muted-foreground">
                            <Navigation className="h-3.5 w-3.5" />
                            <span>
                                Última posición: {posicion.lat?.toFixed(5)}, {posicion.lng?.toFixed(5)}
                            </span>
                            <MapPin className="ml-2 h-3.5 w-3.5" />
                            <span>{repartidor.estado === 'disponible' ? 'Disponible para recibir repartos' : 'Ocupado'}</span>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}