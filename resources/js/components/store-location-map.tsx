import { GoogleMap, Marker, useLoadScript } from '@react-google-maps/api';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { clientConfig } from '@/lib/client-config';

type Props = {
    latitude: string;
    longitude: string;
    address: string;
    onChange: (latitude: string, longitude: string, address?: string) => void;
};

export default function StoreLocationMap(props: Props) {
    const [existingSdk] = useState(
        () =>
            typeof window !== 'undefined' &&
            typeof window.google?.maps?.Map === 'function',
    );
    const apiKey =
        clientConfig.google_maps_api_key ??
        import.meta.env.VITE_GOOGLE_MAPS_API_KEY ??
        '';
    if (!apiKey) {
        return (
            <p
                className="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
                data-testid="store-map-unconfigured"
            >
                Puedes guardar el punto de retiro con el GPS o las coordenadas.
                Para seleccionar en el mapa y buscar direcciones, configura
                Google Maps API Key (frontend) en Configuración web → Servidor.
                Flutter usa las coordenadas guardadas y no requiere esa clave.
            </p>
        );
    }
    return existingSdk ? (
        <MapPicker {...props} />
    ) : (
        <ConfiguredMap {...props} apiKey={apiKey} />
    );
}

function ConfiguredMap(props: Props & { apiKey: string }) {
    const { isLoaded, loadError } = useLoadScript({
        id: 'store-location-map-script',
        googleMapsApiKey: props.apiKey,
    });
    if (loadError)
        return (
            <p role="alert" className="text-sm text-destructive">
                No se pudo cargar el mapa. Puedes usar el GPS o ingresar las
                coordenadas.
            </p>
        );
    if (!isLoaded)
        return (
            <div
                className="h-72 animate-pulse rounded-lg bg-muted"
                aria-label="Cargando mapa"
            />
        );
    return <MapPicker {...props} />;
}

function MapPicker({ latitude, longitude, address, onChange }: Props) {
    const [message, setMessage] = useState('');
    const [searching, setSearching] = useState(false);
    const hasPoint =
        latitude.trim() !== '' &&
        longitude.trim() !== '' &&
        Number.isFinite(Number(latitude)) &&
        Number.isFinite(Number(longitude)) &&
        Math.abs(Number(latitude)) <= 90 &&
        Math.abs(Number(longitude)) <= 180;
    const point = hasPoint
        ? { lat: Number(latitude), lng: Number(longitude) }
        : null;

    function selectPoint(event: google.maps.MapMouseEvent) {
        if (event.latLng) {
            onChange(
                event.latLng.lat().toFixed(7),
                event.latLng.lng().toFixed(7),
            );
            setMessage(
                'Punto de retiro seleccionado. Revisa la dirección y guarda los cambios.',
            );
        }
    }

    async function searchAddress() {
        setSearching(true);
        setMessage('Buscando dirección…');
        try {
            const result = await new google.maps.Geocoder().geocode({
                address: address.trim(),
            });
            const match = result.results[0];
            if (!match) {
                setMessage(
                    'No se encontró la dirección. Agrega comuna y ciudad o marca el punto en el mapa.',
                );
                return;
            }
            onChange(
                match.geometry.location.lat().toFixed(7),
                match.geometry.location.lng().toFixed(7),
                match.formatted_address,
            );
            setMessage(
                'Dirección encontrada. Ajusta el marcador a la entrada del comercio y guarda los cambios.',
            );
        } catch {
            setMessage(
                'No se pudo buscar la dirección. Marca el punto en el mapa o usa el GPS. Revisa que Geocoding API esté habilitada para la clave del navegador.',
            );
        } finally {
            setSearching(false);
        }
    }

    return (
        <div className="space-y-3" data-testid="store-location-map">
            <Button
                type="button"
                variant="outline"
                disabled={!address.trim() || searching}
                onClick={searchAddress}
            >
                {searching ? 'Buscando…' : 'Buscar dirección en el mapa'}
            </Button>
            <p className="text-sm text-muted-foreground">
                Haz clic en la entrada del comercio o arrastra el marcador hasta
                el lugar de retiro.
            </p>
            <div className="overflow-hidden rounded-lg border">
                <GoogleMap
                    mapContainerStyle={{ width: '100%', height: '300px' }}
                    center={point ?? { lat: -33.4489, lng: -70.6693 }}
                    zoom={point ? 17 : 5}
                    onClick={selectPoint}
                    options={{
                        streetViewControl: false,
                        mapTypeControl: false,
                        clickableIcons: false,
                    }}
                >
                    {point && (
                        <Marker
                            position={point}
                            draggable
                            onDragEnd={selectPoint}
                            title="Punto de retiro del comercio"
                        />
                    )}
                </GoogleMap>
            </div>
            <p aria-live="polite" className="text-sm text-muted-foreground">
                {message}
            </p>
        </div>
    );
}
