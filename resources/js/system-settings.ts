import { Loader } from '@googlemaps/js-api-loader';

let loadedKey: string | undefined;

document.addEventListener('click', async (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const button = event.target.closest<HTMLButtonElement>(
        '[data-google-maps-test]',
    );
    const form = button?.form;
    const status = form?.querySelector('[data-google-maps-status]');
    const input = form?.querySelector<HTMLInputElement>(
        '#setting-VITE_GOOGLE_MAPS_API_KEY',
    );

    if (!button || !status || button.disabled) {
        return;
    }

    const apiKey = input?.value.trim() || button.dataset.googleMapsTest?.trim();

    if (!apiKey) {
        status.textContent = 'Ingresa una clave de Google Maps para probarla.';
        return;
    }

    if (loadedKey && loadedKey !== apiKey) {
        status.textContent =
            'Para probar otra clave, guarda la configuración y recarga la página.';
        return;
    }

    button.disabled = true;
    status.textContent = 'Probando Google Maps…';

    try {
        loadedKey = apiKey;
        const loader = new Loader({
            apiKey,
            version: 'weekly',
            nonce: document.querySelector<HTMLScriptElement>('script[nonce]')
                ?.nonce,
        });
        const { Geocoder } = await loader.importLibrary('geocoding');
        await new Geocoder().geocode({ address: 'Santiago, Chile' });
        status.textContent = 'Google Maps aceptó la clave para este sitio.';
    } catch {
        status.textContent =
            'No se pudo conectar a Google Maps. Revisa la clave, el dominio permitido y las APIs habilitadas.';
    } finally {
        button.disabled = false;
    }
});
