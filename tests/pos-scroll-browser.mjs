import assert from 'node:assert/strict';
import { connectBrowser, startFixtureServer } from './responsive-browser.mjs';

const { server, origin } = await startFixtureServer();
let browser;
try {
    browser = await connectBrowser(origin);
    const { send, evaluate, waitFor } = browser;
    for (const width of [1920, 1440, 1024, 400]) {
        await send('Emulation.setDeviceMetricsOverride', {
            width, height: 800, deviceScaleFactor: 1, mobile: false,
        });
        await send('Page.navigate', { url: `${origin}/pos` });
        await waitFor('!!document.querySelector("[data-testid=pos-catalog]")', 'catálogo POS');
        await new Promise(resolve => setTimeout(resolve, 500));
        const result = await evaluate(`(() => {
            const catalog = document.querySelector('[data-testid=pos-catalog]');
            const cart = document.querySelector('[data-testid=pos-cart]');
            const footer = document.querySelector('[data-testid=pos-checkout]');
            const before = footer.getBoundingClientRect();
            catalog.scrollTop = catalog.scrollHeight;
            const after = footer.getBoundingClientRect();
            return {
                documentHeight: document.documentElement.scrollHeight,
                documentWidth: document.documentElement.scrollWidth,
                scrollable: catalog.scrollHeight > catalog.clientHeight,
                scrolled: catalog.scrollTop > 0,
                cartVisible: cart.getClientRects().length > 0,
                footerTop: after.top, footerBottom: after.bottom,
                footerMoved: before.top !== after.top,
            };
        })()`);
        assert.ok(result.documentHeight <= 801, `${width}px: página sin scroll vertical: ${JSON.stringify(result)}`);
        assert.ok(result.documentWidth <= width, `${width}px: sin desborde horizontal`);
        assert.ok(result.scrollable && result.scrolled, `${width}px: catálogo con scroll propio`);
        if (result.cartVisible) {
            assert.ok(!result.footerMoved, `${width}px: carrito independiente del catálogo`);
            assert.ok(result.footerTop >= 0 && result.footerBottom <= 800, `${width}px: totales y cobro visibles`);
        }
        assert.equal(browser.exceptions.length, 0, browser.exceptions.join('\n'));
        console.log(`PASS POS scroll independiente ${width}px`);
    }
} finally {
    browser?.close();
    await new Promise(resolve => server.close(resolve));
}
