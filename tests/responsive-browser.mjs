import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import backendFixtures from './responsive-backend-fixtures.mjs';

const root = fileURLToPath(new URL('..', import.meta.url));
const buildDirectory = path.join(root, 'public/build');
const fixtureDirectory =
    process.env.RESPONSIVE_FIXTURE_DIR ??
    path.join(os.tmpdir(), 'redcliente-responsive-fixtures');
const viewportWidths = [320, 400, 768, 1024, 1280];
const pause = (milliseconds) =>
    new Promise((resolve) => setTimeout(resolve, milliseconds));
const escapeHtml = (value) =>
    value
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
const fixtureUser = {
    id: 101,
    name: 'Usuario de prueba',
    email: 'responsive@example.test',
    permissions: [],
    roles: [],
    public_profile: null,
    profile_photo_url: '/fixture-image.svg',
    recent_notifications: [],
    unread_notifications: 0,
    unread_messages: 0,
    pending_orders: 0,
    is_trial_active: false,
    is_trial_expired: false,
};
const settings = {
    app_name: 'RedCliente',
    app_logo: null,
    app_title: 'Prueba responsive',
    app_description: 'Contenido de prueba',
    app_keywords: '',
};
const sharedProps = {
    auth: { user: fixtureUser },
    flash: {},
    errors: {},
    sidebarOpen: true,
    web_settings: settings,
    client_config: {
        app_name: 'RedCliente',
        reverb: { key: '', host: '127.0.0.1', port: 0, scheme: 'http' },
    },
};
const publicProps = {
    ...sharedProps,
    auth: { user: null },
    canRegister: true,
    nav: {
        app_brand_visible: true,
        quienes_somos_visible: true,
        feedback_visible: true,
        fundacion_visible: true,
    },
    hero: {
        titulo: 'Gestiona tu negocio como un experto',
        subtitulo:
            'Una plataforma para gestionar inventario, ventas y facturación desde cualquier pantalla.',
        boton_principal: 'Comenzar gratis',
        boton_secundario: 'Ver demo',
        badge: '¡Nuevo! IA integrada para predicción de inventario',
    },
    caracteristicas: [],
    planes: [],
    promociones: [],
    cta: {
        titulo: 'Comienza hoy',
        descripcion: 'Contenido de prueba',
        boton: 'Crear cuenta gratis',
    },
    title: 'Información para nuestra comunidad y sus emprendimientos',
    subtitle: 'Una plataforma accesible desde cualquier dispositivo.',
    content: '<p>Contenido informativo de prueba.</p>',
    nav_extra: {},
    image: '/fixture-image.svg',
};

export async function startFixtureServer() {
    const manifest = JSON.parse(
        await fs.readFile(path.join(buildDirectory, 'manifest.json'), 'utf8'),
    );
    const appEntry = manifest['resources/js/app.tsx'];
    assert.ok(
        appEntry?.file,
        'Primero ejecuta npm run build para generar el manifest de Vite.',
    );
    const styleFiles = new Set();
    const visited = new Set();
    function collectStyles(key) {
        if (visited.has(key)) return;
        visited.add(key);
        const entry = manifest[key];
        if (!entry) return;
        for (const css of entry.css ?? []) styleFiles.add(css);
        if (entry.file.endsWith('.css')) styleFiles.add(entry.file);
        for (const imported of entry.imports ?? []) collectStyles(imported);
    }
    collectStyles('resources/js/app.tsx');
    collectStyles('resources/css/app.css');
    function inertiaDocument(component, props, url, asJson = false) {
        const page = {
            component,
            props,
            url,
            version: null,
            clearHistory: false,
            encryptHistory: false,
        };
        if (asJson) return JSON.stringify(page);
        return `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="responsive-fixture">${[...styleFiles].map((file) => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="app" data-page="${escapeHtml(JSON.stringify(page))}"></div><script type="module" src="/build/${appEntry.file}"></script></body></html>`;
    }
    const writeRequests = [];
    const server = http.createServer(async (request, response) => {
        try {
            const url = new URL(request.url, 'http://127.0.0.1');
            if (request.method !== 'GET' && request.method !== 'HEAD') {
                writeRequests.push({ method: request.method, path: url.pathname });
                if (request.method === 'POST' && url.pathname === '/paises') {
                    let body = '';
                    for await (const chunk of request) body += chunk;
                    const country = JSON.parse(body);
                    const countries = backendFixtures.find(fixture => fixture.path === '/configuracion-web').props.countries;
                    countries.push({ ...country, id: 100 });
                    response.writeHead(303, {Location: '/configuracion-web'}).end();
                    return;
                }
                response.writeHead(405).end();
                return;
            }
            if (url.pathname.startsWith('/build/')) {
                const file = path.resolve(
                    buildDirectory,
                    decodeURIComponent(url.pathname.slice('/build/'.length)),
                );
                if (!file.startsWith(buildDirectory + path.sep)) {
                    response.writeHead(403).end();
                    return;
                }
                const extension = path.extname(file);
                const types = {
                    '.js': 'text/javascript',
                    '.css': 'text/css',
                    '.woff2': 'font/woff2',
                    '.svg': 'image/svg+xml',
                };
                if (!types[extension]) {
                    response.writeHead(404).end();
                    return;
                }
                response.writeHead(200, {
                    'Content-Type': types[extension],
                    'Cache-Control': 'no-store',
                });
                response.end(await fs.readFile(file));
                return;
            }
            if (
                url.pathname === '/vendor/livewire/livewire.js' ||
                /^\/livewire[^/]*\/livewire(?:\.min)?\.js$/.test(url.pathname)
            ) {
                response.writeHead(200, { 'Content-Type': 'text/javascript' });
                response.end(
                    await fs.readFile(
                        path.join(
                            root,
                            'vendor/livewire/livewire/dist/livewire.js',
                        ),
                    ),
                );
                return;
            }
            if (url.pathname === '/mensajes') {
                response.writeHead(200, { 'Content-Type': 'application/json' });
                response.end(
                    JSON.stringify({ conversaciones: [], total_sin_leer: 0 }),
                );
                return;
            }
            if (
                url.pathname === '/fixture-image.svg' ||
                /^\/entregas\/\d+\/evidencia$/.test(url.pathname) ||
                url.pathname.startsWith('/storage/')
            ) {
                response.writeHead(200, { 'Content-Type': 'image/svg+xml' });
                response.end(
                    '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="600" height="400" fill="#d1fae5"/></svg>',
                );
                return;
            }
            if (url.pathname === '/favicon.ico') {
                response.writeHead(204).end();
                return;
            }
            if (url.pathname === '/store') {
                const html = await fs.readFile(
                    path.join(fixtureDirectory, 'store.html'),
                    'utf8',
                );
                response.writeHead(200, {
                    'Content-Type': 'text/html; charset=utf-8',
                });
                response.end(
                    html.replace(
                        /https?:\/\/[^/"'\s]+\/(build\/|vendor\/livewire\/|livewire[^/]*\/)/g,
                        '/$1',
                    ),
                );
                return;
            }
            const components = {
                '/': 'WelcomeNew',
                '/public': 'WelcomeNew',
                '/quienes-somos': 'Frontend/QuienesSomos',
                '/feedback': 'Frontend/Feedback',
                '/fundacion': 'Frontend/Fundacion',
            };
            let component = components[url.pathname];
            let props = publicProps;
            if (url.pathname === '/marketplace') {
                component = 'marketplace/index';
                props = {
                    ...sharedProps,
                    profiles: {
                        data: [
                            {
                                id: 1,
                                title: 'Tienda de prueba',
                                description: 'Productos para nuestra comunidad',
                                slug: 'tienda-prueba',
                                user: fixtureUser,
                            },
                        ],
                        current_page: 1,
                        last_page: 1,
                        prev_page_url: null,
                        next_page_url: null,
                        total: 1,
                    },
                };
            }
            const backendFixture = backendFixtures.find((fixture) => fixture.path === url.pathname);
            if (backendFixture) {
                component = backendFixture.component;
                props = {
                    ...sharedProps,
                    auth: { user: { ...fixtureUser, permissions: ['*'] } },
                    ...backendFixture.props,
                };
            }
            if (!component) {
                response.writeHead(404).end();
                return;
            }
            response.writeHead(200, {
                'Content-Type': request.headers['x-inertia'] ? 'application/json' : 'text/html; charset=utf-8',
                'Cache-Control': 'no-store',
                ...(request.headers['x-inertia'] ? {'X-Inertia': 'true'} : {}),
            });
            response.end(inertiaDocument(component, props, url.pathname, !!request.headers['x-inertia']));
        } catch (error) {
            response.writeHead(500, { 'Content-Type': 'text/plain' });
            response.end(error.message);
        }
    });
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    return { server, writeRequests, origin: `http://127.0.0.1:${server.address().port}` };
}

export async function connectBrowser(origin) {
    const targets = await (
        await fetch(
            process.env.RESPONSIVE_CDP_URL ?? 'http://127.0.0.1:9437/json/list',
        )
    ).json();
    const target = targets.find((entry) => entry.type === 'page');
    assert.ok(
        target,
        'Se necesita Chrome headless con remote-debugging-port=9437.',
    );
    const socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.onopen = resolve;
        socket.onerror = reject;
    });
    let commandId = 0;
    const commands = new Map();
    const exceptions = [];
    function send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = ++commandId;
            const timeout = setTimeout(() => {
                commands.delete(id);
                reject(new Error(`CDP timeout: ${method}`));
            }, 20000);
            commands.set(id, { resolve, reject, timeout });
            socket.send(JSON.stringify({ id, method, params }));
        });
    }
    socket.onmessage = async (event) => {
        const message = JSON.parse(event.data);
        if (commands.has(message.id)) {
            const command = commands.get(message.id);
            commands.delete(message.id);
            clearTimeout(command.timeout);
            message.error
                ? command.reject(new Error(message.error.message))
                : command.resolve(message.result);
        } else if (message.method === 'Runtime.exceptionThrown') {
            exceptions.push(
                message.params.exceptionDetails.exception?.description ??
                    message.params.exceptionDetails.text,
            );
        } else if (message.method === 'Fetch.requestPaused') {
            const { requestId, request } = message.params;
            if (
                request.url.startsWith(origin + '/') ||
                request.url.startsWith('data:')
            ) {
                await send('Fetch.continueRequest', { requestId });
            } else {
                await send('Fetch.fulfillRequest', {
                    requestId,
                    responseCode: 204,
                    body: '',
                });
            }
        }
    };
    await send('Page.enable');
    await send('Runtime.enable');
    await send('Fetch.enable', { patterns: [{ urlPattern: 'http*' }] });
    const evaluate = async (expression) => {
        const result = await send('Runtime.evaluate', {
            expression,
            returnByValue: true,
            awaitPromise: true,
        });
        assert.ok(
            !result.exceptionDetails,
            result.exceptionDetails?.exception?.description ??
                result.exceptionDetails?.text,
        );
        return result.result.value;
    };
    async function waitFor(expression, description) {
        for (let attempt = 0; attempt < 60; attempt++) {
            if (await evaluate(expression)) return;
            await pause(100);
        }
        assert.fail(`No se cumplió: ${description}`);
    }
    async function click(selector) {
        const rect = await evaluate(
            `(()=>{const e=[...document.querySelectorAll(${JSON.stringify(selector)})].find(node=>node.getClientRects().length&&node.getBoundingClientRect().width);if(!e)return null;e.scrollIntoView({block:'center'});const r=e.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2,width:r.width,height:r.height}})()`,
        );
        assert.ok(rect?.width && rect?.height, `Control visible: ${selector}`);
        await send('Input.dispatchMouseEvent', {
            type: 'mousePressed',
            x: rect.x,
            y: rect.y,
            button: 'left',
            clickCount: 1,
        });
        await send('Input.dispatchMouseEvent', {
            type: 'mouseReleased',
            x: rect.x,
            y: rect.y,
            button: 'left',
            clickCount: 1,
        });
        await pause(100);
    }
    const escape = async () => {
        await send('Input.dispatchKeyEvent', {
            type: 'keyDown',
            key: 'Escape',
            code: 'Escape',
            windowsVirtualKeyCode: 27,
        });
        await send('Input.dispatchKeyEvent', {
            type: 'keyUp',
            key: 'Escape',
            code: 'Escape',
            windowsVirtualKeyCode: 27,
        });
        await pause(100);
    };
    return {
        send,
        evaluate,
        waitFor,
        click,
        escape,
        exceptions,
        close: () => socket.close(),
    };
}

export async function runResponsiveChecks() {
    const { server, origin, writeRequests } = await startFixtureServer();
    let browser;
    let checks = 0;
    const check = (condition, message) => {
        assert.ok(condition, message);
        checks++;
    };
    try {
        browser = await connectBrowser(origin);
        const { send, evaluate, waitFor, click, escape } = browser;
        const setWidth = async (width) => {
            await send('Emulation.setDeviceMetricsOverride', {
                width,
                height: 800,
                deviceScaleFactor: 1,
                mobile: true,
            });
            await pause(200);
        };
        const navigate = async (route, ready) => {
            browser.exceptions.length = 0;
            await send('Page.navigate', { url: origin + route });
            await waitFor(
                `location.origin === ${JSON.stringify(origin)} && location.pathname === ${JSON.stringify(route)} && (${ready})`,
                `render de ${route}`,
            );
            await pause(150);
        };
        const assertNoOverflow = async (label) => {
            const bounds = await evaluate(
                '({viewport:innerWidth,document:document.documentElement.scrollWidth})',
            );
            check(
                bounds.document <= bounds.viewport + 1,
                `${label}: desbordamiento ${JSON.stringify(bounds)}`,
            );
        };
        const assertControlsUnclipped = async (label) => {
            const clipped = await evaluate(`(()=>{
                const failures=[];
                for(const element of document.querySelectorAll('button,input,textarea,[role=combobox]')){
                    const rect=element.getBoundingClientRect();
                    if(!element.getClientRects().length||rect.width<1||rect.height<1||getComputedStyle(element).opacity==='0')continue;
                    let ancestor=element.parentElement;
                    const ancestors=[];
                    while(ancestor&&ancestor!==document.body){ancestors.push(ancestor);ancestor=ancestor.parentElement}
                    if(ancestors.some(e=>['auto','scroll'].includes(getComputedStyle(e).overflowX)))continue;
                    for(const parent of ancestors){
                        if(!['hidden','clip'].includes(getComputedStyle(parent).overflowX))continue;
                        const bounds=parent.getBoundingClientRect();
                        if(rect.left<bounds.left-2||rect.right>bounds.right+2){
                            failures.push({tag:element.tagName,text:(element.innerText||element.placeholder||element.getAttribute('aria-label')||'').slice(0,60),width:rect.width,ancestor:parent.className,left:rect.left-bounds.left,right:rect.right-bounds.right});break;
                        }
                    }
                }
                return failures.slice(0,8);
            })()`);
            check(clipped.length === 0, `${label}: controles recortados ${JSON.stringify(clipped)}`);
        };
        for (const route of [
            '/public',
            '/quienes-somos',
            '/feedback',
            '/fundacion',
        ]) {
            await setWidth(320);
            await navigate(route, '!!document.querySelector("h1")');
            for (const width of viewportWidths) {
                await setWidth(width);
                await assertNoOverflow(`${route} ${width}px`);
                if (width < 1280) {
                    await click(
                        'button[aria-controls][aria-label="Abrir menú de navegación"]',
                    );
                    check(
                        await evaluate(
                            'document.querySelector("button[aria-controls]").getAttribute("aria-expanded")==="true"',
                        ),
                        `${route}: menú abierto`,
                    );
                    check(
                        await evaluate(
                            `[...document.querySelectorAll('nav[aria-label="Navegación móvil"] a')].filter(e=>e.getClientRects().length).length>=3`,
                        ),
                        `${route}: enlaces móviles accesibles`,
                    );
                    await assertNoOverflow(`${route} menú ${width}px`);
                    await escape();
                    check(
                        await evaluate(
                            'document.activeElement===document.querySelector("button[aria-controls]") && document.activeElement.getAttribute("aria-expanded")==="false"',
                        ),
                        `${route}: Escape cierra y devuelve foco`,
                    );
                } else {
                    check(
                        await evaluate(
                            `document.querySelector('nav[aria-label="Navegación principal"]').getBoundingClientRect().width>0`,
                        ),
                        `${route}: navegación desktop`,
                    );
                }
            }
            check(
                browser.exceptions.length === 0,
                `${route}: excepciones JS: ${browser.exceptions.join('\n')}`,
            );
            console.log(
                `PASS navegación pública ${route}: ${viewportWidths.join(', ')} px`,
            );
        }

        await setWidth(320);
        await navigate(
            '/marketplace',
            'document.body.innerText.includes("Marketplace General")',
        );
        for (const width of viewportWidths) {
            await setWidth(width);
            await assertNoOverflow(`layout React ${width}px`);
            if (width < 1280) {
                await click('[aria-label="Abrir mi cuenta"]');
                await waitFor(
                    '!!document.querySelector("[role=dialog][data-mobile=true]")',
                    'drawer derecho React',
                );
                check(
                    await evaluate(
                        'document.querySelector("[role=dialog][data-mobile=true]").getBoundingClientRect().width>0',
                    ),
                    `drawer derecho visible a ${width}px`,
                );
                await escape();
                await waitFor(
                    '!document.querySelector("[role=dialog][data-mobile=true]")',
                    'cierre drawer React',
                );
            } else {
                check(
                    await evaluate(
                        'document.querySelector("[data-side=right]").getBoundingClientRect().width>0',
                    ),
                    'sidebar derecho desktop visible',
                );
            }
        }
        check(
            browser.exceptions.length === 0,
            `layout React: excepciones JS: ${browser.exceptions.join('\n')}`,
        );
        console.log('PASS sidebar React móvil, tablet y escritorio');

        const backendFailures = [];
        for (const fixture of backendFixtures) {
            try {
            await setWidth(320);
            await navigate(fixture.path, fixture.ready ?? '!!document.querySelector("h1")');
            if (fixture.setup) {
                await evaluate(fixture.setup);
                await waitFor("document.body.innerText.includes('Países soportados')", 'pestaña Países');
            }
            for (const width of viewportWidths) {
                await setWidth(width);
                await assertNoOverflow(`${fixture.path} ${width}px`);
                await assertControlsUnclipped(`${fixture.path} ${width}px`);
                if (fixture.countryManagement) {
                    await evaluate("window.countryParentSubmits = 0; document.querySelector('#config-form')?.addEventListener('submit', event => { event.preventDefault(); window.countryParentSubmits++; }, {once:true})");
                    await evaluate("[...document.querySelectorAll('button')].find(button => button.innerText.trim() === 'Nuevo País').click()");
                    await waitFor("!!document.querySelector('[role=dialog] #country-code')", 'formulario país');
                    check(await evaluate('window.countryParentSubmits === 0'), 'nuevo país no guarda configuración general');
                    await assertNoOverflow(`${fixture.path} modal ${width}px`);
                    await assertControlsUnclipped(`${fixture.path} modal ${width}px`);
                    check(await evaluate("document.querySelector('#country-is_active').checked"), 'nuevo país activo por defecto');
                    check(await evaluate("!!document.querySelector('label[for=country-timezone]')"), 'campo zona horaria etiquetado');
                    check(await evaluate("(()=>{const form=document.querySelector('[role=dialog] form');return !form.checkValidity()})()"), 'formulario vacío no permite guardar');
                    check(await evaluate("(()=>{const b=[...document.querySelectorAll('[role=dialog] button')].find(e=>e.type==='submit');const r=b.getBoundingClientRect();return r.bottom<=innerHeight&&r.top>=0})()"), 'botón de guardar dentro de pantalla');
                    await escape();
                    await waitFor("!document.querySelector('[role=dialog] #country-code')", 'cierre formulario país');
                    await click('button[aria-pressed="false"]');
                    check(await evaluate("!document.querySelector('[aria-label=\"País Chile\"]') && !!document.querySelector('[aria-label=\"País Perú\"]')"), 'filtro solo activos excluye país inactivo');
                    await click('button[aria-pressed="true"]');
                    await click('button[aria-label="Editar Chile"]');
                    await waitFor("document.querySelector('#country-code')?.value === 'CL'", 'datos país editado');
                    check(await evaluate("document.querySelector('#country-code').disabled && document.querySelector('#country-name').value === 'Chile' && Number(document.querySelector('#country-tax_rate').value) === 19"), 'editar carga código nombre e impuesto');
                    await escape();
                    await pause(150);
                }
            }
            if (fixture.countryManagement) {
                await send('Emulation.setDeviceMetricsOverride', {width: 400, height: 320, deviceScaleFactor: 1, mobile: true});
                await pause(200);
                await evaluate("[...document.querySelectorAll('button')].find(button=>button.innerText.trim()==='Nuevo País').click()");
                await waitFor("!!document.querySelector('[role=dialog] #country-code')", 'país en pantalla horizontal');
                check(await evaluate("(()=>{const b=document.querySelector('[role=dialog] button[type=submit]');const r=b.getBoundingClientRect();return r.top>=0&&r.bottom<=320})()"), 'guardar accesible en pantalla baja');
                await escape();
                await send('Emulation.setDeviceMetricsOverride', {width: 1920, height: 900, deviceScaleFactor: 1, mobile: false});
                await pause(200);
                await assertNoOverflow(`${fixture.path} 1920px`);
                check(await evaluate("[...document.querySelectorAll('table')].some(table=>table.getBoundingClientRect().width>0)"), 'tabla de países visible en escritorio amplio');
                await click('button[aria-label="Editar Chile"]');
                await waitFor("document.querySelector('#country-code')?.value==='CL'", 'editar desde tabla de escritorio');
                await escape();
            }
            check(browser.exceptions.length === 0, `${fixture.path}: excepciones JS: ${browser.exceptions.join('\n')}`);
            console.log(`PASS pantalla interna ${fixture.path}: ${viewportWidths.join(', ')} px`);
            } catch (error) {
                backendFailures.push(error.message);
                console.error(`FAIL ${fixture.path}: ${error.message}`);
            }
        }

        await setWidth(400);
        await navigate('/configuracion-web', '!!document.querySelector("h1")');
        await evaluate(backendFixtures.find(fixture => fixture.path === '/configuracion-web').setup);
        await waitFor("document.body.innerText.includes('Países soportados')", 'lista de países');
        await evaluate("[...document.querySelectorAll('button')].find(button=>button.innerText.trim()==='Nuevo País').click()");
        await waitFor("!!document.querySelector('#country-code')", 'crear país completo');
        const newCountryValues={code:'VE',name:'Venezuela',currency_code:'VES',currency_symbol:'Bs',currency_decimals:'2',date_format:'DD/MM/YYYY',locale:'es-VE',timezone:'America/Caracas',phone_code:'+58',tax_name:'IVA',tax_rate:'16',fiscal_id_label:'RIF',fiscal_id_pattern:'^[VEJPGvejpg]-[0-9]{8}-[0-9]$'};
        for (const [key,value] of Object.entries(newCountryValues)) {
            await evaluate(`(()=>{const input=document.querySelector(${JSON.stringify('#country-'+key)});Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(input,${JSON.stringify(value)});input.dispatchEvent(new Event('input',{bubbles:true}))})()`);
            await pause(30);
        }
        await pause(200);
        check(await evaluate("document.querySelector('[role=dialog] form').checkValidity()"), 'datos completos del país válidos');
        writeRequests.length = 0;
        await click('[role=dialog] button[type=submit]');
        await waitFor("!document.querySelector('[role=dialog] #country-code') && !!document.querySelector('[aria-label=\"País Venezuela\"]')", 'país creado aparece tras guardar');
        check(writeRequests.length === 1 && writeRequests[0].method === 'POST' && writeRequests[0].path === '/paises', `Crear envía solo país y no configuración: ${JSON.stringify(writeRequests)}`);
        console.log('PASS creación completa de Venezuela y actualización de lista sin guardar configuración general');

        await fs.access(path.join(fixtureDirectory, 'store.html'));
        await setWidth(320);
        await navigate(
            '/store',
            '!!document.querySelector("h1") && !!window.Alpine',
        );
        for (const width of viewportWidths) {
            await setWidth(width);
            await assertNoOverflow(`tienda ${width}px`);
            const clipped = await evaluate(
                `(()=>{const buttons=[...document.querySelectorAll('button')].filter(e=>e.innerText.trim()==='Agregar');return {total:buttons.length,clipped:buttons.filter(e=>{const a=e.closest('.group');if(!a)return false;const r=e.getBoundingClientRect(),c=a.getBoundingClientRect();return r.right>c.right+1||r.left<c.left-1}).length}})()`,
            );
            check(
                clipped.total > 0 && clipped.clipped === 0,
                `tienda ${width}px: Agregar recortado ${JSON.stringify(clipped)}`,
            );
            if (width < 1024) {
                const filter = 'button[aria-controls="store-filters"]';
                await click(filter);
                check(
                    await evaluate(
                        'document.querySelector("#store-filters").getBoundingClientRect().height>0',
                    ),
                    'filtros móviles abiertos',
                );
                await click(filter);
                check(
                    await evaluate(
                        'document.querySelector("#store-filters").getBoundingClientRect().height===0',
                    ),
                    'filtros móviles plegados',
                );
            }
            if (width < 1280) {
                await click('[aria-label="Abrir mi cuenta"]');
                await pause(150);
                check(
                    await evaluate(
                        'document.querySelector("#app-right-sidebar").getBoundingClientRect().width>0',
                    ),
                    'sidebar Livewire visible',
                );
                check(
                    await evaluate(
                        'document.querySelector("#app-right-sidebar").contains(document.activeElement)',
                    ),
                    'foco dentro del sidebar Livewire',
                );
                check(
                    await evaluate(
                        '[document.body,document.documentElement].some(e=>getComputedStyle(e).overflowY==="hidden"||getComputedStyle(e).overflow==="hidden")',
                    ),
                    'scroll de fondo bloqueado',
                );
                await escape();
                check(
                    await evaluate(
                        `document.querySelector('[aria-label="Abrir mi cuenta"]').getAttribute("aria-expanded")==="false"`,
                    ),
                    'Escape cierra sidebar Livewire',
                );
            } else {
                await evaluate(
                    `[...document.querySelectorAll('button')].find(e=>e.getAttribute('x-on:click')==='carritoAbierto = true')?.click()`,
                );
                await pause(200);
                const accessible = await evaluate(
                    `(()=>{const e=document.querySelector('[aria-label="Cerrar carrito"]');const r=e.getBoundingClientRect();const hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return r.width>0&&(hit===e||e.contains(hit))})()`,
                );
                check(
                    accessible,
                    'cierre del carrito encima del sidebar derecho',
                );
                await click('[aria-label="Cerrar carrito"]');
            }
        }
        check(
            browser.exceptions.length === 0,
            `tienda: excepciones JS: ${browser.exceptions.join('\n')}`,
        );
        console.log(`PASS tienda Livewire: ${viewportWidths.join(', ')} px`);
        check(backendFailures.length === 0, backendFailures.join('\n'));
        console.log(
            `PASS ${checks} comprobaciones responsive sin modificar datos de la aplicación.`,
        );
    } finally {
        browser?.close();
        await new Promise((resolve) => server.close(resolve));
    }
}

if (
    process.argv[1] &&
    path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    runResponsiveChecks().catch((error) => {
        console.error(error);
        process.exitCode = 1;
    });
}
