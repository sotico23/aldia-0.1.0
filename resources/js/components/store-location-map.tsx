import { usePage } from '@inertiajs/react';
import { GoogleMap, Marker, useLoadScript } from '@react-google-maps/api';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { ClientConfig } from '@/lib/client-config';

type Props = {
    latitude: string;
    longitude: string;
    address: string;
    reverseLookup: number | null;
    onChange: (
        latitude: string,
        longitude: string,
        address?: string,
        lookupId?: number,
    ) => void;
};

export default function StoreLocationMap(props: Props) {
    const [existingSdk] = useState(
        () =>
            typeof window !== 'undefined' &&
            typeof window.google?.maps?.Map === 'function',
    );
    const { client_config: clientConfig } = usePage<{
        client_config?: ClientConfig;
    }>().props;
    const apiKey = clientConfig?.google_maps_api_key ?? '';
    if (!apiKey) {
        return (
            <p
                className="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
                data-testid="store-map-unconfigured"
            >
                {clientConfig?.google_maps_api_key_conflict
                    ? 'La clave de Google Maps del navegador debe ser distinta de la clave del servidor. Configura una clave separada para Maps JavaScript API.'
                    : 'Configura la clave de Google Maps para el navegador en Configuración web para buscar direcciones. También puedes usar el GPS.'}
            </p>
        );
    }
    return (
        <MapsWithCredentials
            key={apiKey}
            {...props}
            apiKey={apiKey}
            existingSdk={existingSdk}
        />
    );
}

function MapsWithCredentials(
    props: Props & { apiKey: string; existingSdk: boolean },
) {
    const [authFailed, setAuthFailed] = useState(false);

    useEffect(() => {
        const browser = window as Window & { gm_authFailure?: () => void };
        const previous = browser.gm_authFailure;
        const onFailure = () => {
            setAuthFailed(true);
            previous?.();
        };
        browser.gm_authFailure = onFailure;

        return () => {
            if (browser.gm_authFailure === onFailure) {
                browser.gm_authFailure = previous;
            }
        };
    }, []);

    if (authFailed) {
        return (
            <p role="alert" className="text-sm text-destructive">
                Google Maps rechazó la clave del navegador. Revisa el dominio
                autorizado, Maps JavaScript API y la facturación.
            </p>
        );
    }

    return props.existingSdk ? (
        <MapPicker {...props} />
    ) : (
        <ConfiguredMap {...props} />
    );
}

function ConfiguredMap(props: Props & { apiKey: string }) {
    const { isLoaded, loadError } = useLoadScript({
        id: 'store-location-map-script',
        googleMapsApiKey: props.apiKey,
        nonce:
            typeof document !== 'undefined'
                ? document.querySelector<HTMLScriptElement>('script[nonce]')
                      ?.nonce
                : undefined,
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

function MapPicker({
    latitude,
    longitude,
    address,
    reverseLookup,
    onChange,
}: Props) {
    const [message, setMessage] = useState('');
    const onChangeRef = useRef(onChange);
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

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    useEffect(() => {
        if (!hasPoint || reverseLookup === null) {
            return;
        }

        let isCurrent = true;
        const latitudeValue = Number(latitude);
        const longitudeValue = Number(longitude);

        void new google.maps.Geocoder()
            .geocode({
                location: { lat: latitudeValue, lng: longitudeValue },
            })
            .then(({ results }) => {
                if (!isCurrent) {
                    return;
                }

                if (results.length === 0) {
                    setMessage(
                        'No encontramos una dirección para este punto. Puedes escribirla manualmente.',
                    );

                    return;
                }

                onChangeRef.current(
                    latitude,
                    longitude,
                    results[0].formatted_address,
                    reverseLookup,
                );
                setMessage(
                    'Dirección actualizada desde el punto seleccionado.',
                );
            })
            .catch(() => {
                if (isCurrent) {
                    setMessage(
                        'No se pudo identificar la dirección de este punto. Puedes escribirla manualmente.',
                    );
                }
            });

        return () => {
            isCurrent = false;
        };
    }, [hasPoint, latitude, longitude, reverseLookup]);

    function selectPoint(event: google.maps.MapMouseEvent) {
        if (event.latLng) {
            onChange(
                event.latLng.lat().toFixed(7),
                event.latLng.lng().toFixed(7),
            );
            setMessage('Punto de retiro seleccionado. Buscando la dirección…');
        }
    }

    return (
        <div className="space-y-3" data-testid="store-location-map">
            <AddressSearch
                key={address}
                address={address}
                onChange={(lat, lng, selectedAddress) => {
                    setMessage('');
                    onChange(lat, lng, selectedAddress);
                }}
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
