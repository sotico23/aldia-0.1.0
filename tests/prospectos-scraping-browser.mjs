import assert from 'node:assert/strict';
import backendFixtures from './responsive-backend-fixtures.mjs';
import { connectBrowser, startFixtureServer } from './responsive-browser.mjs';

const fixture = backendFixtures.find(fixture => fixture.path === '/prospectos');
const resultId = 'a'.repeat(64);
const state = () => ({
    health: { ready: true, queue_ready: true, queue_connection: 'database', queue_retry_after: 420, message: 'Motor disponible. Las búsquedas se procesan en segundo plano.' },
    binaries: { node: 'node', chrome: '/usr/bin/chromium' },
    runs: [{ id: 3, query: 'Panaderías', location: 'Santiago, Chile', max_results: 10, status: 'completed', error: null,
        results: [{ id: resultId, name: 'Negocio de prueba', address: 'Calle 123, Santiago', phone: '+56912345678', category: 'Panadería', website: 'https://example.test', maps_url: 'https://www.google.com/maps/place/test' }] }],
});
fixture.props.scraping = state();
const { server, origin, writeRequests, readRequests } = await startFixtureServer();
let browser;
try {
    browser = await connectBrowser(origin);
    const { send, evaluate, waitFor, click, fill } = browser;
    for (const width of [400, 1280]) {
        fixture.props.scraping = state();
        fixture.props.scraping.health = { ...fixture.props.scraping.health, ready: false, message: 'No se encontró Chrome/Chromium en el servidor.', checks: {
            node: { ready: true, message: 'Node.js 26 disponible.' }, chrome: { ready: false, message: 'No se encontró Chrome/Chromium en el servidor.' }, queue: { ready: true, message: 'Cola configurada.' },
        } };
        browser.exceptions.length = 0;
        await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
        await send('Page.navigate', { url: `${origin}/prospectos` });
        await waitFor('!!document.querySelector("[role=tablist]")', 'pestañas de prospectos');
        await click('[role=tablist] [role=tab]:last-child');
        await waitFor('!!document.querySelector("[data-testid=prospectos-scraping]")', 'configuración de scraping');
        assert.ok(await evaluate(`[...document.querySelectorAll('button')].find(button => button.textContent === 'Buscar negocios').disabled`));
        assert.ok(await evaluate(`document.querySelector('[data-testid=scraping-search-blocker]').textContent.includes('Chrome/Chromium')`));
        await evaluate(`[...document.querySelectorAll('button')].find(button => button.textContent === 'Comprobar estado').click()`);
        await waitFor(`[...document.querySelectorAll('button')].some(button => button.textContent === 'Comprobando…' && button.disabled)`, 'progreso de comprobación');
        await waitFor(`![...document.querySelectorAll('button')].find(button => button.textContent === 'Buscar negocios').disabled`, 'búsqueda habilitada tras comprobar requisitos');
        assert.equal(writeRequests.findLast(request => request.path.endsWith('/comprobar')).method, 'POST');
        assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth'), `${width}px sin desborde`);
        assert.equal(await evaluate('document.querySelector("#scraping-query").value'), 'Panaderías');
        await evaluate('document.querySelector("[data-testid=prospectos-scraping] summary").click()');
        assert.equal(await evaluate('document.querySelector("#scraping-node").value'), 'node');
        assert.equal(await evaluate('document.querySelector("#scraping-queue").value'), 'database');
        assert.equal(await evaluate('document.querySelector("#scraping-queue-retry-after").value'), '420');
        await fill('#scraping-queue-retry-after', 600);
        assert.equal(await evaluate('document.querySelector("#scraping-queue-retry-after").value'), '600');
        await click('details button[type="submit"]');
        assert.equal(writeRequests.findLast(request => request.path.endsWith('/configuracion')).body.queue_retry_after, 600);
        await waitFor(`!!document.querySelector('[role=tab][data-state=active]')?.textContent.includes('Google Maps') && document.querySelector('#scraping-node') !== null`, 'conservar pestaña');
        await evaluate(`document.querySelector('input[aria-label="Seleccionar Negocio de prueba"]').click()`);
        await evaluate(`[...document.querySelectorAll('button')].find(button => button.textContent.startsWith('Agregar 1 a Prospectos')).click()`);
        await waitFor(`!!document.querySelector('input[aria-label="Seleccionar Negocio de prueba"]') && !document.querySelector('input[aria-label="Seleccionar Negocio de prueba"]').checked`, 'importación completada');
        assert.deepEqual(writeRequests.findLast(request => request.path.endsWith('/importar')).body.ids, [resultId]);
        await evaluate(`[...document.querySelectorAll('button')].find(button => button.textContent === 'Buscar negocios').click()`);
        await waitFor('document.querySelector("[data-testid=prospectos-scraping]").textContent.includes("Esperando al worker")', 'búsqueda en cola');
        assert.equal(writeRequests.findLast(request => request.path.endsWith('/buscar')).body.max_results, 10);
        await evaluate(`[...document.querySelectorAll('button')].find(button => button.textContent === 'Cancelar búsqueda').click()`);
        await waitFor('document.querySelector("[data-testid=prospectos-scraping]").textContent.includes("Cancelada")', 'cancelación');
        await click('[role=tablist] [role=tab]:first-child');
        await click('[aria-label="Filtrar por fuente"]');
        await click('[data-testid="source-option-google_maps"]');
        await new Promise(resolve => setTimeout(resolve, 650));
        assert.equal(readRequests.findLast(request => request.path === '/prospectos').query.fuente, 'google_maps');
        assert.equal(browser.exceptions.length, 0, browser.exceptions.join('\n'));
        console.log(`PASS configuración, selección, importación, búsqueda y cancelación ${width}px`);
    }
} finally {
    browser?.close();
    await new Promise(resolve => server.close(resolve));
}
