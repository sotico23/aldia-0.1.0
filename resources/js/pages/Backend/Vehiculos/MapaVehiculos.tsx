import {
    GoogleMap,
    useJsApiLoader,
    Marker,
    InfoWindow,
} from '@react-google-maps/api';
import { Loader2, Navigation, Gauge, Clock } from 'lucide-react';
import { useState, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';

interface Vehiculo {
    id: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    lat: number | null;
    lng: number | null;
    velocidad: number;
    estado: string;
    ultima_actualizacion: string | null;
}

const containerStyle = {
    width: '100%',
    height: '100%',
    borderRadius: '1rem',
};

const center = {
    lat: -33.4489,
    lng: -70.6693,
};

export default function MapaVehiculos({
    vehiculos,
}: {
    vehiculos: Vehiculo[];
}) {
    const { isLoaded } = useJsApiLoader({
        id: 'google-map-script',
        googleMapsApiKey: import.meta.env.VITE_GOOGLE_MAPS_API_KEY,
    });

    const [selected, setSelected] = useState<Vehiculo | null>(null);

    const vehiculosConGPS = useMemo(
        () => vehiculos.filter((v) => v.lat !== null && v.lng !== null),
        [vehiculos],
    );

    const formatTime = (dateStr: string | null) => {
        if (!dateStr) return 'N/A';
        return new Date(dateStr).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    if (!isLoaded)
        return (
            <div className="flex h-[400px] items-center justify-center rounded-2xl border-2 border-dashed bg-muted/20 md:h-[600px]">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
                <span className="ml-3 font-medium">Cargando Mapas...</span>
            </div>
        );

    return (
        <Card className="overflow-hidden border-none bg-card/50 p-0 shadow-2xl ring-1 ring-border backdrop-blur-sm">
            <div className="h-[400px] md:h-[600px]">
            <GoogleMap
                mapContainerStyle={containerStyle}
                center={
                    vehiculosConGPS.length > 0
                        ? {
                              lat: vehiculosConGPS[0].lat!,
                              lng: vehiculosConGPS[0].lng!,
                          }
                        : center
                }
                zoom={12}
                options={{
                    styles: [
                        {
                            featureType: 'all',
                            elementType: 'labels.text.fill',
                            stylers: [{ color: '#ffffff' }],
                        },
                        {
                            featureType: 'all',
                            elementType: 'labels.text.stroke',
                            stylers: [{ color: '#000000' }, { lightness: 13 }],
                        },
                    ],
                    disableDefaultUI: false,
                    zoomControl: true,
                }}
            >
                {vehiculosConGPS.map((v) => (
                    <Marker
                        key={v.id}
                        position={{ lat: v.lat!, lng: v.lng! }}
                        onClick={() => setSelected(v)}
                        icon={{
                            path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
                            fillColor:
                                v.estado === 'en_ruta' ? '#3b82f6' : '#10b981',
                            fillOpacity: 1,
                            strokeWeight: 2,
                            strokeColor: '#ffffff',
                            scale: 1.5,
                            anchor: { x: 12, y: 22 } as any,
                        }}
                    />
                ))}

                {selected && (
                    <InfoWindow
                        position={{ lat: selected.lat!, lng: selected.lng! }}
                        onCloseClick={() => setSelected(null)}
                    >
                        <div className="min-w-[200px] p-2">
                            <div className="mb-2 flex items-center justify-between">
                                <Badge className="bg-primary font-black tracking-tighter text-primary-foreground uppercase">
                                    {selected.placa}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    className="text-[10px] uppercase"
                                >
                                    {selected.estado}
                                </Badge>
                            </div>
                            <h4 className="mb-3 text-sm font-bold text-foreground italic">
                                {selected.marca} {selected.modelo}
                            </h4>
                            <div className="space-y-2 border-t pt-2 text-xs">
                                <div className="flex items-center text-muted-foreground">
                                    <Gauge className="mr-2 h-3 w-3" />
                                    <span className="font-bold text-foreground">
                                        {selected.velocidad} km/h
                                    </span>
                                </div>
                                <div className="flex items-center text-muted-foreground">
                                    <Clock className="mr-2 h-3 w-3" />
                                    <span>
                                        Act:{' '}
                                        {formatTime(
                                            selected.ultima_actualizacion,
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center text-muted-foreground">
                                    <Navigation className="mr-2 h-3 w-3" />
                                    <span className="font-mono text-[9px]">
                                        {selected.lat?.toFixed(5)},{' '}
                                        {selected.lng?.toFixed(5)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </InfoWindow>
                )}
            </GoogleMap>
            </div>
        </Card>
    );
}
