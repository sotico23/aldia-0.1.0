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
    const apiKey = clientConfig.google_maps_api_key ?? '';
    if (!apiKey) {
        return (
            <p
                className="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
                data-testid="store-map-unconfigured"
            >
                Configura la clave de Google Maps para el navegador en
                Configuración web para buscar direcciones. También puedes usar
                el GPS.
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
                No se pudo cargar Google Maps. Revisa la clave del navegador o
                usa el GPS.
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

    return (
        <div className="space-y-3" data-testid="store-location-map">
            <AddressSearch
                key={address}
                address={address}
                onChange={onChange}
            />
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

function AddressSearch({
    address,
    onChange,
}: Pick<Props, 'address' | 'onChange'>) {
    const [results, setResults] = useState<google.maps.GeocoderResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [message, setMessage] = useState('');

    async function searchAddress() {
        setSearching(true);
        setResults([]);
        setMessage('');

        try {
            const { results } = await new google.maps.Geocoder().geocode({
                address: address.trim(),
            });
            setResults(results);
            if (results.length === 0) {
                setMessage(
                    'No encontramos esa dirección. Agrega el número y la comuna.',
                );
            }
        } catch (error) {
            setMessage(
                String(error).includes('ZERO_RESULTS')
                    ? 'No encontramos esa dirección. Agrega el número y la comuna.'
                    : 'No se pudo buscar la dirección en Google Maps. Intenta nuevamente.',
            );
        } finally {
            setSearching(false);
        }
    }

    return (
        <div className="space-y-2">
            <Button
                type="button"
                variant="outline"
                disabled={!address.trim() || searching}
                onClick={searchAddress}
            >
                {searching ? 'Buscando…' : 'Buscar dirección en Google Maps'}
            </Button>
            {results.length > 0 && (
                <ul
                    aria-label="Direcciones de Google Maps"
                    className="divide-y rounded-lg border"
                >
                    {results.map((result) => (
                        <li key={result.place_id}>
                            <button
                                type="button"
                                className="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                                onClick={() => {
                                    const { location } = result.geometry;
                                    onChange(
                                        location.lat().toFixed(7),
                                        location.lng().toFixed(7),
                                        result.formatted_address,
                                    );
                                    setResults([]);
                                }}
                            >
                                {result.formatted_address}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {message && (
                <p role="alert" className="text-sm text-destructive">
                    {message}
                </p>
            )}
        </div>
    );
}
