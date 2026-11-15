import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import backendFixtures from './responsive-backend-fixtures.mjs';
import { connectBrowser, startFixtureServer } from './responsive-browser.mjs';

const { server, origin } = await startFixtureServer();
let browser;
try {
    browser = await connectBrowser(origin);
    const { send, evaluate, waitFor, click, escape } = browser;
    for (const width of [400, 1024, 1600]) {
        browser.exceptions.length = 0;
        await send('Emulation.setDeviceMetricsOverride', { width, height: 850, deviceScaleFactor: 1, mobile: false });
        await send('Page.navigate', { url: `${origin}/entregas/registro` });
        if (width === 1600) {
            await send('Page.navigate', { url: `${origin}/entregas` });
            await waitFor("location.pathname === '/entregas' && [...document.querySelectorAll('[role=tab]')].some(e => e.textContent.trim() === 'Entregas' && e.getAttribute('aria-selected') === 'true')", 'página de entregas');
            await evaluate("[...document.querySelectorAll('a')].find(e => e.textContent.trim() === 'Registro de entregas').click()");
            await waitFor("location.pathname === '/entregas/registro'", 'navegación desde el menú');
        }
        await waitFor('!!document.querySelector(\'[role="tablist"]\')', 'pestañas de entregas');
        assert.ok(await evaluate("[...document.querySelectorAll('[role=tab]')].some(e => e.textContent.includes('Registro de entregas') && e.getAttribute('aria-selected') === 'true')"), 'el enlace abre directamente el registro');
        if (width === 1600) {
            const nav = await evaluate("[...document.querySelectorAll('a')].filter(e => ['Entregas', 'Registro de entregas'].includes(e.textContent.trim())).map(e => ({label:e.textContent.trim(),path:new URL(e.href).pathname}))");
            assert.deepEqual(nav, [{label:'Entregas',path:'/entregas'},{label:'Registro de entregas',path:'/entregas/registro'}]);
        }
        await waitFor('!!document.querySelector(\'[data-testid="delivery-history"]\')', 'registro de entregas');
        assert.ok(await evaluate("document.querySelector('[data-testid=delivery-history]').textContent.includes('Repartidor de prueba')"));
        assert.ok(await evaluate("document.querySelector('[data-testid=delivery-history]').textContent.includes('Sin foto registrada')"));
        assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth'), `${width}px: sin desborde horizontal`);
        await click('button[aria-label="Ver foto de la entrega 79"]');
        await waitFor("!!document.querySelector('[role=dialog] img')?.naturalWidth", 'foto de evidencia');
        assert.ok(await evaluate("document.querySelector('[role=dialog]').textContent.includes('Repartidor de prueba')"));
        await escape();
        if (width === 1600) {
            const screenshot = await send('Page.captureScreenshot', { format: 'png' });
            await fs.mkdir(new URL('../storage/framework/testing/', import.meta.url), { recursive: true });
            await fs.writeFile(new URL('../storage/framework/testing/delivery-history.png', import.meta.url), Buffer.from(screenshot.data, 'base64'));
        }
        assert.equal(browser.exceptions.length, 0, browser.exceptions.join('\n'));
        console.log(`PASS historial, horarios y foto ${width}px`);
    }
    backendFixtures.find(fixture => fixture.path === '/entregas').props.delivery_history.data[0].driver_name = 'Actualizado desde APK';
    await new Promise(resolve => setTimeout(resolve, 11500));
    await waitFor("document.querySelector('[data-testid=delivery-history]')?.textContent.includes('Actualizado desde APK')", 'actualización automática desde API');
    console.log('PASS sincronización automática sin perder la pestaña seleccionada');
} finally {
    browser?.close();
    await new Promise(resolve => server.close(resolve));
}
