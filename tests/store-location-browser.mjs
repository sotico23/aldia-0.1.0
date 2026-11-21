import assert from 'node:assert/strict';
import backendFixtures from './responsive-backend-fixtures.mjs';
import { connectBrowser, startFixtureServer } from './responsive-browser.mjs';

backendFixtures.push({
    path: '/settings/public-profile',
    component: 'settings/public-profile',
    props: {
        client_config: { google_maps_api_key: '' },
        publicProfile: {
            id: 7,
            title: 'Comercio de retiro',
            slug: 'comercio-retiro',
            is_active: true,
            pickup_address: 'Comercio 123',
            pickup_lat: -33.45,
            pickup_lng: -70.66,
        },
    },
});
const { server, origin, writeRequests } = await startFixtureServer();
let browser;
try {
    browser = await connectBrowser(origin);
    const { send, evaluate, waitFor } = browser;
    for (const width of [400, 1280]) {
        browser.exceptions.length = 0;
        await send('Emulation.setDeviceMetricsOverride', {
            width,
            height: 850,
            deviceScaleFactor: 1,
            mobile: false,
        });
        await send('Page.navigate', {
            url: `${origin}/settings/public-profile`,
        });
        await waitFor(
            '!!document.querySelector("#pickup_address")',
            'ubicación de tienda',
        );
        assert.equal(
            await evaluate('document.querySelector("#pickup_address").value'),
            'Comercio 123',
        );
        assert.equal(
            await evaluate(
                'document.querySelectorAll("#pickup_lat, #pickup_lng").length',
            ),
            0,
        );
        assert.ok(
            await evaluate(
                'document.querySelector("[data-testid=store-map-readiness]").textContent.includes("Datos completos")',
            ),
        );
        assert.ok(
            await evaluate(
                '!!document.querySelector("[data-testid=store-map-unconfigured]")',
            ),
            'sin clave el GPS sigue disponible',
        );
        assert.ok(
            await evaluate(
                'document.documentElement.scrollWidth <= innerWidth',
            ),
            `${width}px sin desbordes`,
        );
        await evaluate(`navigator.geolocation.getCurrentPosition = (_, error) => error({code: 1});
            [...document.querySelectorAll('button')].find(button => button.textContent.includes('Usar mi ubicación')).click()`);
        await waitFor(
            'document.querySelector("[role=status]").textContent.includes("Autoriza el acceso")',
            'permiso denegado',
        );
        await evaluate(`navigator.geolocation.getCurrentPosition = success => success({coords: {latitude: -33.456789, longitude: -70.654321}});
            [...document.querySelectorAll('button')].find(button => button.textContent.includes('Usar mi ubicación')).click()`);
        await waitFor(
            'document.querySelector("[role=status]").textContent.includes("Ubicación obtenida")',
            'GPS obtenido',
        );
        await evaluate(`const field = document.querySelector('#pickup_address');
            Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(field, 'Nueva calle');
            field.dispatchEvent(new Event('input', {bubbles: true}));`);
        await waitFor(
            'document.querySelector("[data-testid=store-map-readiness]").textContent.includes("Datos completos")',
            'GPS con dirección ingresada manualmente',
        );
        await evaluate(`document.querySelector('[role=switch]').click()`);
        await waitFor(
            'document.querySelector("[data-testid=store-map-readiness]").textContent.includes("desactivada")',
            'tienda oculta en mapa',
        );
        assert.equal(
            browser.exceptions.length,
            0,
            browser.exceptions.join('\n'),
        );
        console.log(
            `PASS ubicación guardada, permiso denegado y GPS ${width}px`,
        );
    }
    backendFixtures.find(
        (fixture) => fixture.path === '/settings/public-profile',
    ).props.client_config = { google_maps_api_key: 'test-browser-key' };
    await send('Page.addScriptToEvaluateOnNewDocument', {
        source: `
        const events = {
            addListener(instance, name, callback) { (instance.listeners ??= {})[name] = callback; return {instance, name}; },
            removeListener(listener) { delete listener.instance.listeners[listener.name]; },
            clearInstanceListeners(instance) { instance.listeners = {}; },
            trigger(instance, name, value) { instance.listeners?.[name]?.(value); }
        };
        function sdkObject() {
            return new Proxy({
                listeners: {}, mapTypes: {set() {}},
                setCenter(center) { this.center = center; },
                setPosition(position) { this.position = position; },
            }, { get(target, property) {
                if (property in target) return target[property];
                return () => {};
            }});
        }
        window.google = {maps: {version: 'test', event: events,
            Map: function() { return window.testStoreMap = sdkObject(); },
            Marker: function() { return window.testStoreMarker = sdkObject(); },
            Geocoder: function() { this.geocode = async ({ location }) => {
                if (location) return {results: [{formatted_address: "Punto " + location.lat}]};
                if (window.testGeocodingFails) throw new Error('REQUEST_DENIED');
                return {results: [
                    {place_id: 'first', formatted_address: 'Primera calle 123', geometry: {location: {lat: () => -33.41, lng: () => -70.61}}},
                    {place_id: 'second', formatted_address: 'Segunda calle 789', geometry: {location: {lat: () => -33.47, lng: () => -70.68}}},
                ]};
            }; }
        }};
    `,
    });
    await send('Page.navigate', { url: `${origin}/settings/public-profile` });
    await waitFor(
        '!!document.querySelector("[data-testid=store-location-map]") && !!window.testStoreMarker',
        'mapa configurado',
    );
    assert.equal(
        await evaluate('document.querySelector("#pickup_address").value'),
        'Comercio 123',
    );
    await evaluate(
        `google.maps.event.trigger(window.testStoreMap, 'click', {latLng: {lat: () => -33.48, lng: () => -70.69}})`,
    );
    await waitFor(
        'document.querySelector("#pickup_address").value === "Punto -33.48"',
        'geocodificar clic',
    );
    await evaluate(
        `google.maps.event.trigger(window.testStoreMarker, 'dragend', {latLng: {lat: () => -33.49, lng: () => -70.70}})`,
    );
    await waitFor(
        'document.querySelector("#pickup_address").value === "Punto -33.49"',
        'geocodificar arrastre',
    );
    await evaluate(`navigator.geolocation.getCurrentPosition = success => success({coords: {latitude: -33.456789, longitude: -70.654321}});
        [...document.querySelectorAll('button')].find(button => button.textContent.includes('Usar mi ubicación')).click()`);
    await waitFor(
        'document.querySelector("#pickup_address").value === "Punto -33.456789"',
        'geocodificar GPS',
    );
    assert.deepEqual(await evaluate('window.testStoreMap.center'), {
        lat: -33.456789,
        lng: -70.654321,
    });
    assert.deepEqual(await evaluate('window.testStoreMarker.position'), {
        lat: -33.456789,
        lng: -70.654321,
    });
    await evaluate(
        `[...document.querySelectorAll('button')].find(button => button.textContent.includes('Buscar dirección en Google Maps')).click()`,
    );
    await waitFor(
        'document.querySelectorAll("[data-testid=store-location-map] ul button").length === 2',
        'resultados de Google Maps',
    );
    assert.equal(
        await evaluate('document.querySelector("#pickup_address").value'),
        'Punto -33.456789',
    );
    await evaluate(
        'document.querySelectorAll("[data-testid=store-location-map] ul button")[1].click()',
    );
    await waitFor(
        'document.querySelector("#pickup_address").value === "Segunda calle 789"',
        'elegir segundo resultado',
    );
    await evaluate(`window.testGeocodingFails = true;
        [...document.querySelectorAll('button')].find(button => button.textContent.includes('Buscar dirección en Google Maps')).click()`);
    await waitFor(
        'document.querySelector("[data-testid=store-location-map] [role=alert]")?.textContent.includes("No se pudo buscar")',
        'error visible',
    );
    assert.equal(
        await evaluate('document.querySelector("#pickup_address").value'),
        'Segunda calle 789',
    );
    await evaluate('window.gm_authFailure()');
    await waitFor(
        'document.querySelector("main").textContent.includes("Google Maps rechaz")',
        'clave rechazada',
    );
    assert.equal(browser.exceptions.length, 0, browser.exceptions.join('\n'));
    console.log(
        'PASS selección en mapa y búsqueda de dirección con SDK simulado',
    );
    assert.equal(
        writeRequests.length,
        0,
        'elegir una ubicacion no publica cambios sin guardar',
    );
    const request = new Promise((resolve) =>
        server.once('request', (request) => {
            let body = '';
            request.on('data', (chunk) => {
                body += chunk;
            });
            request.on('end', () => resolve(body));
        }),
    );
    await evaluate(
        '[...document.querySelectorAll("form button")].find(button => button.textContent.includes("Guardar Cambios")).click()',
    );
    const { pickup_address, pickup_lat, pickup_lng } = JSON.parse(
        await request,
    );
    assert.deepEqual(
        [pickup_address, pickup_lat, pickup_lng],
        ['Segunda calle 789', '-33.4700000', '-70.6800000'],
    );
} finally {
    browser?.close();
    await new Promise((resolve) => server.close(resolve));
}
