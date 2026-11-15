import assert from 'node:assert/strict';
import backendFixtures from './responsive-backend-fixtures.mjs';
import { connectBrowser, startFixtureServer } from './responsive-browser.mjs';

backendFixtures.push({ path: '/settings/public-profile', component: 'settings/public-profile', props: {
    client_config: { google_maps_api_key: '' },
    publicProfile: { id: 7, title: 'Comercio de retiro', slug: 'comercio-retiro', is_active: true,
        pickup_address: 'Comercio 123', pickup_lat: -33.45, pickup_lng: -70.66 },
} });
const { server, origin, writeRequests } = await startFixtureServer();
let browser;
try {
    browser = await connectBrowser(origin);
    const { send, evaluate, waitFor } = browser;
    for (const width of [400, 1280]) {
        browser.exceptions.length = 0;
        await send('Emulation.setDeviceMetricsOverride', { width, height: 850, deviceScaleFactor: 1, mobile: false });
        await send('Page.navigate', { url: `${origin}/settings/public-profile` });
        await waitFor('!!document.querySelector("#pickup_address")', 'ubicación de tienda');
        assert.equal(await evaluate('document.querySelector("#pickup_address").value'), 'Comercio 123');
        assert.equal(await evaluate('document.querySelector("#pickup_lat").value'), '-33.45');
        assert.ok(await evaluate('document.querySelector("[data-testid=store-map-readiness]").textContent.includes("Datos completos")'));
        assert.ok(await evaluate('!!document.querySelector("[data-testid=store-map-unconfigured]")'), 'sin clave el GPS y las coordenadas siguen disponibles');
        assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth'), `${width}px sin desbordes`);
        await evaluate(`navigator.geolocation.getCurrentPosition = (_, error) => error({code: 1});
            [...document.querySelectorAll('button')].find(button => button.textContent.includes('Usar mi ubicación')).click()`);
        await waitFor('document.querySelector("[role=status]").textContent.includes("Autoriza el acceso")', 'permiso denegado');
        await evaluate(`navigator.geolocation.getCurrentPosition = success => success({coords: {latitude: -33.456789, longitude: -70.654321}});
            [...document.querySelectorAll('button')].find(button => button.textContent.includes('Usar mi ubicación')).click()`);
        await waitFor('document.querySelector("#pickup_lat").value === "-33.4567890"', 'coordenadas obtenidas');
        assert.equal(await evaluate('document.querySelector("#pickup_lng").value'), '-70.6543210');
        assert.ok(await evaluate('document.querySelector("[role=status]").textContent.includes("Guarda los cambios")'));
        await evaluate(`const field = document.querySelector('#pickup_lat');
            Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(field, '');
            field.dispatchEvent(new Event('input', {bubbles: true}));`);
        await waitFor('document.querySelector("[data-testid=store-map-readiness]").textContent.includes("completa la dirección")', 'aviso de ubicación incompleta');
        await evaluate(`document.querySelector('[role=switch]').click()`);
        await waitFor('document.querySelector("[data-testid=store-map-readiness]").textContent.includes("desactivada")', 'tienda oculta en mapa');
        assert.equal(browser.exceptions.length, 0, browser.exceptions.join('\n'));
        console.log(`PASS ubicación guardada, permiso denegado y GPS ${width}px`);
    }
    backendFixtures.find(fixture => fixture.path === '/settings/public-profile').props.client_config = { google_maps_api_key: 'test-browser-key' };
    await send('Page.addScriptToEvaluateOnNewDocument', { source: `
        const events = {
            addListener(instance, name, callback) { (instance.listeners ??= {})[name] = callback; return {instance, name}; },
            removeListener(listener) { delete listener.instance.listeners[listener.name]; },
            clearInstanceListeners(instance) { instance.listeners = {}; },
            trigger(instance, name, value) { instance.listeners?.[name]?.(value); }
        };
        function sdkObject() {
            return new Proxy({listeners: {}, mapTypes: {set() {}}}, { get(target, property) {
                if (property in target) return target[property];
                return () => {};
            }});
        }
        window.google = {maps: {version: 'test', event: events,
            Map: function() { return window.testStoreMap = sdkObject(); },
            Marker: function() { return window.testStoreMarker = sdkObject(); },
            Geocoder: function() { this.geocode = async () => {
                if (window.testGeocodingFails) throw new Error('REQUEST_DENIED');
                return {results: [{formatted_address: 'Dirección encontrada 789', geometry: {location: {lat: () => -33.47, lng: () => -70.68}}}]};
            }; }
        }};
    ` });
    await send('Page.navigate', { url: `${origin}/settings/public-profile` });
    await waitFor('!!document.querySelector("[data-testid=store-location-map]") && !!window.testStoreMarker', 'mapa configurado');
    await evaluate(`google.maps.event.trigger(window.testStoreMap, 'click', {latLng: {lat: () => -33.48, lng: () => -70.69}})`);
    await waitFor('document.querySelector("#pickup_lat").value === "-33.4800000"', 'seleccionar punto en mapa');
    await evaluate(`google.maps.event.trigger(window.testStoreMarker, 'dragend', {latLng: {lat: () => -33.49, lng: () => -70.70}})`);
    await waitFor('document.querySelector("#pickup_lng").value === "-70.7000000"', 'arrastrar punto de retiro');
    await evaluate(`[...document.querySelectorAll('button')].find(button => button.textContent.includes('Buscar dirección en el mapa')).click()`);
    await waitFor('document.querySelector("#pickup_address").value === "Dirección encontrada 789"', 'buscar dirección');
    assert.equal(await evaluate('document.querySelector("#pickup_lat").value'), '-33.4700000');
    assert.equal(await evaluate('document.querySelector("#pickup_lng").value'), '-70.6800000');
    await evaluate(`window.testGeocodingFails = true;
        [...document.querySelectorAll('button')].find(button => button.textContent.includes('Buscar dirección en el mapa')).click()`);
    await waitFor('document.querySelector("[data-testid=store-location-map]").textContent.includes("No se pudo buscar la dirección")', 'error de geocodificación visible');
    assert.equal(await evaluate('document.querySelector("#pickup_lat").value'), '-33.4700000', 'fallar la búsqueda conserva la ubicación seleccionada');
    assert.equal(browser.exceptions.length, 0, browser.exceptions.join('\n'));
    console.log('PASS selección en mapa, arrastre y búsqueda de dirección con SDK simulado');
    assert.equal(writeRequests.length, 0, 'obtener GPS no publica cambios sin guardar');
} finally {
    browser?.close();
    await new Promise(resolve => server.close(resolve));
}
