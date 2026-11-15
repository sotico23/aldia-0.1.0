import assert from 'node:assert/strict';
import test from 'node:test';
import { businessDetails, mapIdentity, pageState, withBrowser } from '../resources/js/scraping/google-maps.mjs';

test('identifica un negocio por su ficha y rechaza dominios externos', () => {
    assert.equal(mapIdentity('https://www.google.com/maps/place/Local/data=!4m2!3m1!1s0x123%3A0x456'), '0x123:0x456');
    assert.equal(mapIdentity('https://www.google.com/maps/place/Local?cid=12345'), '12345');
    assert.equal(mapIdentity('https://evil.example/maps/place/Local/data=!1sotro'), null);
    assert.equal(mapIdentity('javascript:alert(1)'), null);
});

test('Chromium lee datos publicos del DOM y reconoce una verificacion sin eludirla', { skip: !process.env.SCRAPER_TEST_CHROME }, async () => {
    await withBrowser(process.env.SCRAPER_TEST_CHROME, async page => {
        const html = '<h1>Panadería de prueba</h1><button data-item-id="address"><span class="Io6YTe">Calle 123, Santiago</span></button><button data-item-id="phone:tel:+56912345678">Teléfono</button><a data-item-id="authority" href="https://panaderia.example/">Web</a><button class="DkEaL">Panadería</button>';
        await page.evaluate(`document.body.innerHTML = ${JSON.stringify(html)}`);
        assert.deepEqual(await page.evaluate(`(${businessDetails.toString()})()`), {
            name: 'Panadería de prueba', address: 'Calle 123, Santiago', phone: '+56912345678', category: 'Panadería', website: 'https://panaderia.example/',
        });
        await page.evaluate(`document.body.innerHTML = '<p>Nuestros sistemas han detectado tráfico inusual</p>'`);
        assert.equal((await pageState(page)).blocked, true);
    });
});
