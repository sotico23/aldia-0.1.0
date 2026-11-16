import assert from 'node:assert/strict';
import { connectBrowser, startFixtureServer } from './responsive-browser.mjs';

const { server, origin } = await startFixtureServer();
let browser;

try {
    browser = await connectBrowser(origin);
    const { send, waitFor, click, evaluate } = browser;

    for (const width of [320, 400, 768, 1280]) {
        browser.exceptions.length = 0;
        await send('Emulation.setDeviceMetricsOverride', {
            width,
            height: 800,
            deviceScaleFactor: 1,
            mobile: width <= 400,
        });
        await send('Page.navigate', {
            url: `${origin}/repartidor-solicitudes-review`,
        });
        await waitFor(
            '!!document.querySelector("[aria-label*=Revisar]")',
            'lista de solicitudes',
        );
        assert.ok(
            await evaluate('document.documentElement.scrollWidth <= innerWidth'),
            `${width}px sin desborde de página`,
        );

        await click('[aria-label="Revisar solicitud de Repartidor de prueba"]');
        await waitFor(
            'document.querySelector("[role=dialog]")?.innerText.includes("Documentos y fotos")',
            'modal de revisión',
        );
        assert.ok(
            await evaluate(`(() => { const box = document.querySelector('[role="dialog"]').getBoundingClientRect(); return box.left >= 0 && box.right <= innerWidth; })()`),
            `${width}px modal dentro del ancho`,
        );
        assert.ok(
            await evaluate('document.querySelector("[role=dialog]").innerText.includes("Autorización para verificar antecedentes")'),
            `${width}px muestra consentimiento`,
        );
        assert.ok(
            await evaluate('document.querySelector("[role=dialog] img")?.naturalWidth > 0'),
            `${width}px vista previa de foto cargada`,
        );
        assert.equal(browser.exceptions.length, 0, browser.exceptions.join('\n'));
    }

    console.log('PASS revisión de solicitud y foto en 320, 400, 768 y 1280 px');
} finally {
    browser?.close();
    await new Promise((resolve) => server.close(resolve));
}
