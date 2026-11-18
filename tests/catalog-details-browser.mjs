import { build } from 'esbuild';
import { spawn } from 'node:child_process';
import {
    existsSync,
    mkdirSync,
    mkdtempSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const root = process.cwd();
const description =
    'Descripción completa de prueba. '.repeat(150) + 'FIN_DETALLE';
const category = {
    id: 42,
    nombre: 'Accesorios para autos y motos con nombre largo',
    descripcion: description,
    tipo: 'producto',
    activo: true,
    imagen: null,
    mostrar_en_perfil: true,
    public_profile_id: 1,
    created_at: '2026-11-17T10:00:00Z',
};
const product = {
    id: 1,
    codigo: 'SKU-PRUEBA',
    nombre: 'Producto con nombre largo para comprobar detalle',
    descripcion: description,
    precio_venta: 14990,
    precio_con_variantes: 14990,
    stock: 25,
    stock_minimo: 2,
    total_stock: 25,
    unidad_medida: 'unidad',
    peso_base: 1,
    contenido_por_unidad: 1,
    medida_pesable: false,
    imagen: null,
    tiene_variantes: false,
    skus: [],
    variantes: [],
    inventarios: [],
    activo: true,
    categoria: { id: 42, nombre: category.nombre },
    imagenes: [],
};
const fixtures = {
    categories: {
        categoriasPorTipo: [
            {
                tipo: 'producto',
                label: 'Producto',
                total: 1,
                activas: 1,
                marketplace: 1,
                data: [category],
            },
        ],
        tiposDisponibles: [{ value: 'producto', label: 'Producto' }],
        tiendaSlug: 'tienda-prueba',
        filters: {},
    },
    products: {
        productos: { data: [product], links: [], total: 1 },
        categorias: [{ id: 42, nombre: category.nombre }],
        almacenes: [],
        productosEnvase: [],
        variantesDisponibles: [],
        filters: {},
    },
};
const testing = path.join(root, 'storage/framework/testing');
mkdirSync(testing, { recursive: true });
const temporary = mkdtempSync(path.join(testing, 'catalog-details-'));
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = readFileSync(
    path.join('public/build', manifest['resources/css/app.css'].file),
    'utf8',
);
const compiled = await build({
    stdin: {
        contents: `import React from 'react'; import {createRoot} from 'react-dom/client';
        import Categories from './resources/js/pages/Backend/Categorias/Index';
        import Products from './resources/js/pages/Backend/Productos/Index';
        const fixtures = ${JSON.stringify(fixtures)};
        window.route=(name)=>'/test/'+name;
        const root=createRoot(document.getElementById('root'));
        window.renderCatalog=(kind,allowed)=>{window.fixturePermissions=allowed?['comercial.productos.viewAny','comercial.productos.view']:['comercial.productos.viewAny']; const Component=kind==='categories'?Categories:Products;root.render(<Component key={kind+allowed+innerWidth} {...fixtures[kind]}/>)};`,
        loader: 'tsx',
        resolveDir: root,
    },
    bundle: true,
    write: false,
    format: 'iife',
    jsx: 'automatic',
    define: { 'process.env.NODE_ENV': '"production"' },
    alias: { '@': path.join(root, 'resources/js') },
    plugins: [
        {
            name: 'maintenance-fixture',
            setup(builder) {
                builder.onResolve(
                    {
                        filter: /^@\/layouts\/app-layout$|^@inertiajs\/react$|^axios$/,
                    },
                    ({ path: name }) => ({ path: name, namespace: 'fixture' }),
                );
                builder.onLoad(
                    { filter: /.*/, namespace: 'fixture' },
                    ({ path: name }) => ({
                        loader: 'tsx',
                        resolveDir: root,
                        contents:
                            name === 'axios'
                                ? `export default {post: async () => ({data: {success: true, command:'route:clear', output:'${'long-output-'.repeat(500)}', exit_code:0, executed_at:'2026-11-16T20:00:00Z'}}), isAxiosError:()=>false};`
                                : name === '@inertiajs/react'
                                  ? `import {useState} from 'react'; export const Head=()=>null; export const usePage=()=>({props:{auth:{user:{id:1,permissions:window.fixturePermissions??[],roles:[]}},errors:{}},url:'/test'});export function useForm(initial={}){const [data,set]=useState(initial);return {data,setData:(key,value)=>set(old=>typeof key==='string'?{...old,[key]:value}:key),errors:{},processing:false,reset(){},clearErrors(){},get(){},post(){},put(){},patch(){},delete(){},transform(){return this}}}export const Link=({href,children,...props})=><a href={typeof href==='string'?href:href.url} {...props}>{children}</a>; export const router={reload(){},get(){},post(){},delete(){},put(){},patch(){}};`
                                  : `export default function Layout({children}) {return <div style={{display:'flex',width:'100%',minWidth:0}}>{innerWidth>=1024&&<aside style={{width:256,flexShrink:0}}>Menú</aside>}<main style={{minWidth:0,flex:1}}>{children}</main></div>}`,
                    }),
                );
            },
        },
    ],
});
const html = path.join(temporary, 'index.html');
writeFileSync(
    html,
    `<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}\n*,*::before,*::after{animation:none!important;transition:none!important}</style></head><body><div id="root"></div><script>${compiled.outputFiles[0].text.replaceAll('</script', '<\\/script')}</script></body></html>`,
);
const chrome = spawn(
    process.env.CHROME_BINARY,
    [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        '--remote-debugging-port=0',
        `--user-data-dir=${path.join(temporary, 'profile')}`,
        'about:blank',
    ],
    { stdio: 'ignore', windowsHide: true },
);
let socket;
try {
    const portFile = path.join(temporary, 'profile/DevToolsActivePort');
    for (let i = 0; !existsSync(portFile); i++) {
        if (i > 200) throw new Error('Chrome did not start');
        await delay(50);
    }
    const port = readFileSync(portFile, 'utf8').split('\n')[0].trim();
    const targets = await (
        await fetch(`http://127.0.0.1:${port}/json/list`)
    ).json();
    socket = new WebSocket(
        targets.find((target) => target.type === 'page').webSocketDebuggerUrl,
    );
    await new Promise((resolve, reject) => {
        socket.onopen = resolve;
        socket.onerror = reject;
    });
    let id = 0;
    const requests = new Map();
    socket.onmessage = ({ data }) => {
        const result = JSON.parse(data);
        if (requests.has(result.id)) {
            const { resolve, reject } = requests.get(result.id);
            requests.delete(result.id);
            result.error
                ? reject(new Error(JSON.stringify(result.error)))
                : resolve(result.result);
        }
    };
    const send = (method, params = {}) =>
        new Promise((resolve, reject) => {
            const key = ++id;
            requests.set(key, { resolve, reject });
            socket.send(JSON.stringify({ id: key, method, params }));
        });
    const results = [];
    for (const [width, height] of [
        [320, 568],
        [390, 844],
        [667, 375],
        [768, 1024],
        [1440, 900],
    ]) {
        await send('Emulation.setDeviceMetricsOverride', {
            width,
            height,
            deviceScaleFactor: 1,
            mobile: false,
        });
        const previousOrigin = (
            await send('Runtime.evaluate', {
                expression: 'performance.timeOrigin',
                returnByValue: true,
            })
        ).result.value;
        await send('Page.navigate', { url: pathToFileURL(html).href });
        for (let i = 0; i < 100; i++) {
            if (
                (
                    await send('Runtime.evaluate', {
                        expression: `performance.timeOrigin!==${previousOrigin}&&typeof window.renderCatalog==='function'`,
                        returnByValue: true,
                    })
                ).result.value === true
            )
                break;
            await delay(50);
        }
        for (const [kind, allowed] of [
            ['categories', true],
            ['products', true],
            ['products', false],
        ]) {
            const probe = await send('Runtime.evaluate', {
                awaitPromise: true,
                returnByValue: true,
                expression: `(async()=>{
                const failures=[];const check=(ok,message)=>{if(!ok)failures.push(message)};const wait=()=>new Promise(r=>setTimeout(r,100));
                window.renderCatalog(${JSON.stringify(kind)},${allowed});await wait();
                check(document.documentElement.scrollWidth<=innerWidth+1,'Page horizontal overflow');
                const visible=selector=>[...document.querySelectorAll(selector)].filter(el=>el.getBoundingClientRect().width&&el.getBoundingClientRect().height);
                const selector=${JSON.stringify(kind === 'categories' ? 'button[aria-label="Ver detalles"]' : 'button[aria-label^="Ver detalle de"]')};
                if(!${allowed}) {check(visible(selector).length===0,'Detail exposed without view permission');return {width:innerWidth,kind:${JSON.stringify(kind)},allowed:${allowed},failures};}
                for(const mode of ${JSON.stringify(kind === 'products' ? ['table', 'cards'] : ['table'])}){
                    if(mode==='cards'){document.querySelector('button:has(svg.lucide-layout-grid)').click();await wait();}
                    const button=visible(selector)[0];check(!!button,'Missing detail button');if(!button)continue;
                    if(${kind === 'products'}){const initial=button.getBoundingClientRect();check(initial.left>=0&&initial.right<=innerWidth,'Detail action requires horizontal scrolling: '+mode+' '+JSON.stringify({left:initial.left,right:initial.right,width:innerWidth,parents:(()=>{const result=[];let node=button.parentElement;for(let i=0;node&&i<6;i++,node=node.parentElement){result.push({tag:node.tagName,classes:node.className,width:node.getBoundingClientRect().width,display:getComputedStyle(node).display})}return result})()}));}
                    button.scrollIntoView({block:'center',inline:'nearest'});await wait();const r=button.getBoundingClientRect();
                    check(r.left>=0&&r.right<=innerWidth,'Detail button hidden outside viewport');
                    check(button.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)),'Detail button is covered');
                    button.click();await wait();const dialog=document.querySelector('[role="dialog"][data-state="open"]');
                    check(!!dialog,'Detail did not open');if(!dialog)continue;
                    const box=dialog.getBoundingClientRect();check(box.left>=8&&box.right<=innerWidth-8,'Dialog horizontal bounds');check(box.top>=8&&box.bottom<=innerHeight-8,'Dialog vertical bounds');
                    check(dialog.scrollWidth<=dialog.clientWidth+1,'Dialog content overflows horizontally');
                    const body=dialog.querySelector('[class*="overflow-y-auto"]');check(!!body,'Missing detail scroller');
                    check(body?.innerText.includes('FIN_DETALLE'),'Full description is missing');
                    if(body){body.scrollTop=body.scrollHeight;await wait();check(body.scrollTop>0,'Long detail cannot scroll');}
                    const close=dialog.querySelector('[data-slot="dialog-footer"] button');check(!!close,'Missing close button');
                    if(close){const c=close.getBoundingClientRect();check(c.top>=box.top&&c.bottom<=box.bottom,'Close button clipped');close.click();await wait();}
                    check(!document.querySelector('[role="dialog"][data-state="open"]'),'Detail did not close');
                }
                return {width:innerWidth,kind:${JSON.stringify(kind)},allowed:${allowed},failures};
            })()`,
            });
            if (probe.exceptionDetails)
                throw new Error(JSON.stringify(probe.exceptionDetails));
            results.push(probe.result.value);
        }
    }
    process.stdout.write(JSON.stringify(results));
} finally {
    socket?.close();
    chrome.kill();
    await new Promise((resolve) => {
        if (chrome.exitCode !== null) resolve();
        else {
            chrome.once('exit', resolve);
            setTimeout(resolve, 3000);
        }
    });
    if (!path.resolve(temporary).startsWith(path.resolve(testing) + path.sep))
        throw new Error('Unexpected fixture directory');
    rmSync(temporary, {
        recursive: true,
        force: true,
        maxRetries: 10,
        retryDelay: 200,
    });
}
